<?php

namespace App\Models;

use App\Enums\CurrencyType;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledRepayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'amount',
        'outstanding_amount',
        'currency_code',
        'status',
        'due_date',
    ];

    protected $casts = [
        'loan_id' => 'integer',
        'amount'  => 'integer',
        'outstanding_amount'  => 'integer',
        'currency_code' => CurrencyType::class,
        'status'  => PaymentStatus::class,
        'due_date' => 'datetime'
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
