<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CurrencyType;
use App\Enums\PaymentStatus;
use App\Exceptions\AlreadyRepaidException;
use App\Exceptions\AmountHigherThanOutstandingAmountException;
use App\Models\Loan;
use App\Models\User;
use Carbon\Carbon;
use InvalidArgumentException;

class LoanService
{
    public function createLoan(User $user, int $amount, CurrencyType $currencyCode, int $terms, Carbon $processedAt): Loan
    {
        if (! in_array($terms, [3, 6], true)) {
            throw new InvalidArgumentException('terms should be 3 or 6');
        }

        $loan = Loan::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'outstanding_amount' => $amount,
            'currency_code' => $currencyCode,
            'terms' => $terms,
            'processed_at' => $processedAt,
            'status' => PaymentStatus::DUE,
        ]);

        $shouldAddLastMonth = $amount % $terms;
        if ($shouldAddLastMonth !== 0) {
            $installmentPayment = (int) floor($amount / $terms);
        } else {
            $installmentPayment = $amount / $terms;
        }

        foreach (range(1, $terms) as $index) {
            if ($terms === $index) {
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

    public function repayLoan(Loan $loan, int $receivedRepayment, CurrencyType $currencyCode, Carbon $receivedAt): Loan
    {
        if ($loan->outstanding_amount === 0 && $loan->status === PaymentStatus::REPAID) {
            throw new AlreadyRepaidException();
        }

        if ($loan->outstanding_amount < $receivedRepayment) {
            throw new AmountHigherThanOutstandingAmountException();
        }

        $totalRepaidScheduledRepayments = $loan->scheduledRepayments()
            ->where('status', PaymentStatus::REPAID)
            ->get(['amount'])
            ->sum('amount');

        $outstandingAmount = ($loan->amount - $totalRepaidScheduledRepayments) - $receivedRepayment;

        $loan->update(['outstanding_amount' => $outstandingAmount]);

        $scheduledRepayment = $loan->scheduledRepayments
            ->where('due_date', $receivedAt)
            ->first();

        $remainingDebt = 0;
        $scheduledRepaymentOutstandingAmount = $scheduledRepayment->amount - $receivedRepayment;
        if ($receivedRepayment > $scheduledRepayment->amount) {
            $scheduledRepaymentOutstandingAmount = 0;
            $remainingDebt = $receivedRepayment - $scheduledRepayment->amount;
        }

        $scheduledRepayment->update([
            'status' => $scheduledRepaymentOutstandingAmount === 0 ? PaymentStatus::REPAID : PaymentStatus::PARTIAL,
            'outstanding_amount' => $scheduledRepaymentOutstandingAmount,
        ]);

        $loan->receivedRepayments()->create([
            'amount'        => $receivedRepayment,
            'currency_code' => $currencyCode,
            'received_at'   => $receivedAt,
        ]);

        if ($loan->outstanding_amount === 0) {
            $loan->update(['status' => PaymentStatus::REPAID]);
        }

        $loan = $loan->refresh();

        if ($remainingDebt > 0) {
            return $this->repayLoan($loan, $remainingDebt, $currencyCode, $receivedAt->addMonth());
        }

        return $loan;
    }
}
