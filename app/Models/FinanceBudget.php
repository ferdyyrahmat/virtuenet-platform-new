<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceBudget extends Model
{
    protected $fillable = ['department_id', 'period', 'amount_idr', 'status', 'notes', 'created_by', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return ['period' => 'date', 'amount_idr' => 'decimal:2', 'approved_at' => 'datetime'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
