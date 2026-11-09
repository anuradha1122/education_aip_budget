<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialYear extends Model
{
    protected $fillable = [
        'year',
        'start_date',
        'end_date',
        'is_closed',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_closed' => 'boolean',
        ];
    }
}