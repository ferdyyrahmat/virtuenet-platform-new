<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StatementImport extends Model
{
    protected $fillable = ['payment_instrument_id', 'statement_period', 'disk', 'path', 'original_name', 'sha256', 'fx_rate', 'fx_source', 'fx_effective_at', 'row_count', 'imported_by'];

    protected function casts(): array
    {
        return ['statement_period' => 'date', 'fx_rate' => 'decimal:8', 'fx_effective_at' => 'datetime'];
    }

    public function paymentInstrument(): BelongsTo
    {
        return $this->belongsTo(PaymentInstrument::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StatementLine::class);
    }
}
