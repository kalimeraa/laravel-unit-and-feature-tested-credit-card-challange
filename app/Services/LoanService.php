<?php

namespace App\Services;

use App\Enums\CurrencyType;
use App\Enums\PaymentStatus;
use App\Models\Loan;
use App\Models\User;
use Carbon\Carbon;
use InvalidArgumentException;

class LoanService
{
    public function createLoan(User $user, int $amount, CurrencyType $currencyCode, int $terms,Carbon $processedAt): Loan
    {
        if(!in_array($terms,[3,6],true)) {
            throw new InvalidArgumentException('terms should be 3 or 6');
        }
        
        $loan = Loan::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'outstanding_amount' => $amount,
            'currency_code' => $currencyCode->value,
            'terms' => $terms,
            'processed_at' => $processedAt,
            'status' => PaymentStatus::DUE
        ]);

        
        $fractional = is_float($amount / $terms);
        if($fractional) {
            $installmentPayment = (int)floor($amount / $terms);
            $shouldAddLastMonth = $amount % $terms;
        } else {
            $installmentPayment = $amount / $terms;
        }
        
        foreach(range(1,$terms) as $index) {
            if($fractional && $terms === $index) {
                $installmentPayment += $shouldAddLastMonth; 
            }

            $loan->scheduledRepayments()->create([
                'amount'             => $installmentPayment,
                'outstanding_amount' => $installmentPayment,
                'currency_code'      => $currencyCode->value,
                'due_date'           => $processedAt->clone()->addMonth($index),
                'status'             => PaymentStatus::DUE,
            ]);
        }
            
        return $loan;
    }
}