<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LarkApprovalContract extends Model
{
    protected $fillable = ['approval_code', 'contract_hash', 'controls', 'nodes', 'active', 'observed_at'];

    protected function casts(): array
    {
        return ['controls' => 'array', 'nodes' => 'array', 'active' => 'boolean', 'observed_at' => 'datetime'];
    }
}
