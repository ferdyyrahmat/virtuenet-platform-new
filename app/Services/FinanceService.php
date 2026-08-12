<?php

namespace App\Services;

use App\Models\AiAccessCredential;
use App\Models\FinancialEntry;
use App\Models\PaymentInstrument;
use App\Models\StatementImport;
use App\Models\StatementLine;
use App\Models\Subscription;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class FinanceService
{
    public function createEntry(array $data, User $actor): FinancialEntry
    {
        return DB::transaction(function () use ($data, $actor): FinancialEntry {
            $subscription = isset($data['subscription_id']) ? Subscription::find($data['subscription_id']) : null;
            $amount = BigDecimal::of((string) $data['original_amount']);
            $fxRate = strtoupper($data['currency']) === 'IDR' ? BigDecimal::one() : BigDecimal::of((string) $data['fx_rate']);
            $normalized = $amount->multipliedBy($fxRate)->toScale(2, RoundingMode::HalfUp);

            return FinancialEntry::create([
                ...$data,
                'reference' => 'FIN-'.now()->format('Ym').'-'.Str::upper(Str::random(8)),
                'subscription_id' => $subscription?->id,
                'payment_instrument_id' => $data['payment_instrument_id'] ?? $subscription?->payment_instrument_id,
                'department_id' => $data['department_id'] ?? $subscription?->department_id,
                'owner_id' => $data['owner_id'] ?? $subscription?->owner_id,
                'cost_center' => $data['cost_center'] ?? $subscription?->cost_center,
                'vendor' => $data['vendor'] ?? $subscription?->vendor,
                'accounting_period' => Carbon::parse($data['accounting_period'])->startOfMonth(),
                'currency' => strtoupper($data['currency']),
                'normalized_idr' => (string) $normalized,
                'fx_rate' => (string) $fxRate,
                'fx_effective_at' => $data['fx_effective_at'] ?? now(),
                'normalization_method' => strtoupper($data['currency']) === 'IDR' ? 'original IDR amount' : 'original amount × recorded FX rate',
                'created_by' => $actor->id,
            ]);
        });
    }

    public function importStatement(UploadedFile $file, PaymentInstrument $instrument, string $period, string $fxRate, string $fxSource, User $actor): StatementImport
    {
        $hash = hash_file('sha256', $file->getRealPath());
        if (StatementImport::where('payment_instrument_id', $instrument->id)->where('sha256', $hash)->exists()) {
            throw ValidationException::withMessages(['statement' => 'This statement file was already imported for the selected instrument.']);
        }
        $rows = $this->readCsv($file, $fxRate, $fxSource);
        $path = $file->storeAs('finance/statements/'.$instrument->id, Str::uuid().'.csv', 'local');
        if (! $path) {
            throw ValidationException::withMessages(['statement' => 'The statement file could not be stored.']);
        }

        try {
            return DB::transaction(function () use ($file, $instrument, $period, $hash, $rows, $path, $fxRate, $fxSource, $actor): StatementImport {
                $import = StatementImport::create([
                    'payment_instrument_id' => $instrument->id,
                    'statement_period' => Carbon::parse($period)->startOfMonth(),
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'sha256' => $hash,
                    'fx_rate' => $fxRate,
                    'fx_source' => $fxSource,
                    'fx_effective_at' => now(),
                    'row_count' => count($rows),
                    'imported_by' => $actor->id,
                ]);
                foreach ($rows as $row) {
                    $line = $import->lines()->create($row);
                    $this->autoReconcile($line, $instrument, $actor);
                }
                activity('finance')->causedBy($actor)->event('statement_imported')->withProperties([
                    'instrument' => $instrument->masked_label, 'period' => $period, 'rows' => count($rows),
                    'fx_rate' => $fxRate, 'fx_source' => $fxSource,
                ])->log('Corporate-card statement imported.');

                return $import;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    public function reconcile(StatementLine $line, ?FinancialEntry $entry, string $status, string $note, User $actor): StatementLine
    {
        if ($entry && $line->statementImport->payment_instrument_id !== $entry->payment_instrument_id) {
            throw ValidationException::withMessages(['financial_entry_id' => 'The ledger entry belongs to another payment instrument.']);
        }

        $line->update([
            'financial_entry_id' => $entry?->id,
            'status' => $status,
            'review_note' => $note,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);
        activity('finance')->causedBy($actor)->performedOn($line)->event('reconciled')->withProperties([
            'status' => $status, 'financial_entry' => $entry?->reference,
        ])->log('Statement line reconciled.');

        return $line;
    }

    public function syncAiUsage(LiteLlmService $liteLlm, int $days, string $fxRate, string $fxSource, User $actor): int
    {
        $end = now()->toDateString();
        $start = now()->subDays($days)->toDateString();
        $usage = $liteLlm->usage(null, $start, $end);
        $created = 0;

        foreach ($usage['logs'] as $log) {
            if (! is_array($log)) {
                continue;
            }
            $amount = $this->firstNumeric($log, ['spend', 'response_cost']);
            if ($amount === null || BigDecimal::of($amount)->isZero()) {
                continue;
            }
            $sourceIdentity = data_get($log, 'request_id') ?? data_get($log, 'id') ?? data_get($log, 'call_id');
            $occurredAt = Carbon::parse(data_get($log, 'startTime') ?? data_get($log, 'start_time') ?? data_get($log, 'created_at') ?? now());
            $sourceKey = hash('sha256', 'litellm:'.($sourceIdentity ?: json_encode([
                $occurredAt->toIso8601String(), $amount, data_get($log, 'model'), data_get($log, 'user'),
            ], JSON_THROW_ON_ERROR)));
            if (FinancialEntry::where('source_key', $sourceKey)->exists()) {
                continue;
            }
            $alias = data_get($log, 'api_key') ?? data_get($log, 'key_alias') ?? data_get($log, 'metadata.user_api_key_alias');
            $credential = $alias ? AiAccessCredential::with('user.departments')->where('key_alias', $alias)->first() : null;
            $normalized = BigDecimal::of($amount)->multipliedBy($fxRate)->toScale(2, RoundingMode::HalfUp);
            FinancialEntry::create([
                'reference' => 'AI-'.now()->format('Ym').'-'.Str::upper(Str::random(8)),
                'kind' => 'ai_usage',
                'status' => 'accrued',
                'ai_credential_id' => $credential?->id,
                'service_request_id' => $credential?->service_request_id,
                'department_id' => $credential?->user?->departments->firstWhere('pivot.is_primary', true)?->id ?? $credential?->user?->departments->first()?->id,
                'owner_id' => $credential?->user_id,
                'vendor' => (string) (data_get($log, 'custom_llm_provider') ?? data_get($log, 'model_group') ?? 'LiteLLM Gateway'),
                'accounting_period' => $occurredAt->copy()->startOfMonth(),
                'occurred_on' => $occurredAt->toDateString(),
                'original_amount' => $amount,
                'currency' => 'USD',
                'normalized_idr' => (string) $normalized,
                'fx_rate' => $fxRate,
                'fx_source' => $fxSource,
                'fx_effective_at' => now(),
                'normalization_method' => 'LiteLLM spend × recorded USD/IDR rate',
                'source_key' => $sourceKey,
                'notes' => 'Imported from LiteLLM spend logs; no gateway function is cloned locally.',
                'created_by' => $actor->id,
            ]);
            $created++;
        }

        activity('finance')->causedBy($actor)->event('ai_spend_synced')->withProperties([
            'days' => $days, 'entries' => $created, 'fx_rate' => $fxRate, 'fx_source' => $fxSource,
        ])->log('AI spend imported into the corporate ledger.');

        return $created;
    }

    public function monthlyEquivalent(Subscription $subscription): string
    {
        if (! $subscription->currentVersion) {
            return '0.00';
        }
        $months = match ($subscription->currentVersion->billing_cycle) {
            'monthly' => 1,
            'quarterly' => 3,
            'annual', 'yearly' => 12,
            'multi_year', 'custom' => max(1, (int) $subscription->currentVersion->billing_interval_months),
            'usage_based' => 0,
            default => 1,
        };

        if ($months === 0) {
            return '0.00';
        }

        return (string) BigDecimal::of($subscription->currentVersion->normalized_idr)
            ->dividedBy($months, 2, RoundingMode::HalfUp);
    }

    private function readCsv(UploadedFile $file, string $fxRate, string $fxSource): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        $headers = fgetcsv($handle ?: throw ValidationException::withMessages(['statement' => 'Unable to read the CSV file.']));
        $headers = array_map(fn ($value): string => Str::snake(trim((string) $value)), $headers ?: []);
        foreach (['date', 'description', 'amount', 'currency'] as $required) {
            if (! in_array($required, $headers, true)) {
                fclose($handle);
                throw ValidationException::withMessages(['statement' => 'CSV headers must include date, description, amount, and currency.']);
            }
        }

        $rows = [];
        $rowNumber = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (count(array_filter($values, fn ($value): bool => trim((string) $value) !== '')) === 0) {
                continue;
            }
            if (count($values) !== count($headers)) {
                fclose($handle);
                throw ValidationException::withMessages(['statement' => "CSV row {$rowNumber} does not match the header columns."]);
            }
            $row = array_combine($headers, $values);
            try {
                $date = Carbon::parse($row['date'])->toDateString();
                $amount = BigDecimal::of(trim($row['amount']));
            } catch (Throwable) {
                fclose($handle);
                throw ValidationException::withMessages(['statement' => "CSV row {$rowNumber} has an invalid date or amount."]);
            }
            $currency = strtoupper(trim($row['currency']));
            $rate = $currency === 'IDR' ? BigDecimal::one() : BigDecimal::of($fxRate);
            $rows[] = [
                'row_number' => $rowNumber,
                'occurred_on' => $date,
                'description' => trim($row['description']),
                'external_reference' => filled($row['reference'] ?? null) ? trim($row['reference']) : null,
                'original_amount' => (string) $amount,
                'currency' => $currency,
                'normalized_idr' => (string) $amount->multipliedBy($rate)->toScale(2, RoundingMode::HalfUp),
                'fx_rate' => (string) $rate,
                'fx_source' => $fxSource,
                'status' => 'unexpected',
            ];
        }
        fclose($handle);
        if ($rows === []) {
            throw ValidationException::withMessages(['statement' => 'The statement contains no transaction rows.']);
        }

        return $rows;
    }

    private function autoReconcile(StatementLine $line, PaymentInstrument $instrument, User $actor): void
    {
        $entry = FinancialEntry::where('payment_instrument_id', $instrument->id)
            ->where('currency', $line->currency)
            ->where('original_amount', $line->original_amount)
            ->whereBetween('occurred_on', [$line->occurred_on->copy()->subDays(3), $line->occurred_on->copy()->addDays(3)])
            ->whereDoesntHave('statementLines')
            ->first();
        if ($entry) {
            $line->update(['financial_entry_id' => $entry->id, 'status' => 'matched', 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
        }
    }

    private function firstNumeric(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($data, $key);
            if (is_numeric($value)) {
                return (string) $value;
            }
        }

        return null;
    }
}
