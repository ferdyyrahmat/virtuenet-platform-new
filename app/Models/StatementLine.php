<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatementLine extends Model
{
    protected $fillable = [
        'statement_import_id', 'row_number', 'occurred_on', 'description', 'external_reference',
        'original_amount', 'currency', 'normalized_idr', 'fx_rate', 'fx_source', 'status', 'financial_entry_id',
        'review_note', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date', 'original_amount' => 'decimal:4', 'normalized_idr' => 'decimal:2', 'fx_rate' => 'decimal:8',
            'reviewed_at' => 'datetime',
        ];
    }

    public function statementImport(): BelongsTo
    {
        return $this->belongsTo(StatementImport::class);
    }

    public function financialEntry(): BelongsTo
    {
        return $this->belongsTo(FinancialEntry::class);
    }
}
