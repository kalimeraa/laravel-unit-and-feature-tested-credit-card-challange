<?php

namespace App\Models;

use App\Enums\CurrencyType;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'amount',
        'terms',
        'outstanding_amount',
        'currency_code',
        'status',
        'processed_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'amount'  => 'integer',
        'terms'  => 'integer',
        'outstanding_amount'  => 'integer',
        'currency_code' => CurrencyType::class,
        'status'  => PaymentStatus::class,
    ];

    public function scheduledRepayments(): HasMany
    {
        return $this->hasMany(ScheduledRepayment::class);
    }
}
