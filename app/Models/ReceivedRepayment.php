<?php

namespace App\Models;

use App\Enums\CurrencyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceivedRepayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'amount',
        'currency_code',
        'received_at',
    ];

    protected $casts = [
        'loan_id' => 'integer',
        'amount'  => 'integer',
        'currency_code' => CurrencyType::class,
        'received_at' => 'datetime'
    ];
}
