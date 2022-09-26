<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CurrencyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'received_at' => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
