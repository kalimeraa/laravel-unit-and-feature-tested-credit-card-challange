<?php

namespace Tests\Unit;

use App\Constants\Currency;
use App\Constants\PaymentStatus;
use App\Models\Loan;
use App\Models\ReceivedRepayment;
use App\Models\ScheduledRepayment;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LoanServiceTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected User $customer;
    protected LoanService $loanService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->create();
        $this->loanService = new LoanService();
    }

    /** @test */
    public function can_create_loan_for_a_customer(): void
    {
        // 1️⃣ Arrange 🏗
        $terms = 3;
        $amount = 5000;
        $currencyCode = $this->faker->randomElement(Currency::ALL);
        $processedAt = Carbon::now()->startOfMonth();

        // 2️⃣ Act 🏋🏻‍
        $loan = $this->loanService->createLoan($this->customer, $amount, $currencyCode, $terms, $processedAt);

        // 3️⃣ Assert ✅
        $this->assertDatabaseHas(Loan::class, [
            'id'                 => $loan->id,
            'user_id'            => $this->customer->id,
            'amount'             => $amount,
            'terms'              => $terms,
            'outstanding_amount' => $amount,
            'currency_code'      => $currencyCode,
            'processed_at'       => $processedAt,
            'status'             => PaymentStatus::DUE,
        ]);

        $this->assertCount($terms, $loan->scheduledRepayments);

        $this->assertDatabaseHas(ScheduledRepayment::class, [
            'loan_id'            => $loan->id,
            'amount'             => 1666,
            'outstanding_amount' => 1666,
            'currency_code'      => $currencyCode,
            'due_date'           => $processedAt->clone()->addMonth(),
            'status'             => PaymentStatus::DUE,
        ]);

        $this->assertDatabaseHas(ScheduledRepayment::class, [
            'loan_id'            => $loan->id,
            'amount'             => 1666,
            'outstanding_amount' => 1666,
            'currency_code'      => $currencyCode,
            'due_date'           => $processedAt->clone()->addMonths(2),
            'status'             => PaymentStatus::DUE,
        ]);

        $this->assertDatabaseHas(ScheduledRepayment::class, [
            'loan_id'            => $loan->id,
            'amount'             => 1668,
            'outstanding_amount' => 1668,
            'currency_code'      => $currencyCode,
            'due_date'           => $processedAt->clone()->addMonths(3),
            'status'             => PaymentStatus::DUE,
        ]);

        $this->assertEquals($amount, $loan->scheduledRepayments()->sum('amount'));
    }

    /** @test */
    public function can_pay_a_scheduled_payment(): void
    {
        // 1️⃣ Arrange 🏗
        $loan = $this->loanService->createLoan(
            $this->customer,
            5000,
            Currency::TRY,
            3,
            Carbon::parse('2022-01-20'),
        );

        $receivedRepayment = 1666;
        $currencyCode = Currency::TRY;
        $receivedAt = Carbon::parse('2022-02-20');

        // 2️⃣ Act 🏋🏻‍
        $loan = $this->loanService->repayLoan($loan, $receivedRepayment, $currencyCode, $receivedAt);

        // 3️⃣ Assert ✅
        // Assert loan values
        $this->assertDatabaseHas(Loan::class, [
            'id'                 => $loan->id,
            'user_id'            => $this->customer->id,
            'amount'             => 5000,
            'outstanding_amount' => 5000 - 1666,
            'currency_code'      => $currencyCode,
            'status'             => PaymentStatus::DUE,
            'processed_at'       => Carbon::parse('2022-01-20'),
        ]);

        // Asserting First Scheduled Repayment is Repaid
        $this->assertDatabaseHas(ScheduledRepayment::class, [
            'loan_id'            => $loan->id,
            'amount'             => 1666,
            'outstanding_amount' => 0,
            'currency_code'      => $currencyCode,
            'due_date'           => Carbon::parse('2022-02-20'),
            'status'             => PaymentStatus::REPAID,
        ]);

        // Asserting Second and Third Scheduled Repayments are still due
        $this->assertDatabaseHas(ScheduledRepayment::class, [
            'status'   => PaymentStatus::DUE,
            'due_date' => Carbon::parse('2022-03-20'),
        ]);

        $this->assertDatabaseHas(ScheduledRepayment::class, [
            'status'   => PaymentStatus::DUE,
            'due_date' => Carbon::parse('2022-04-20'),
        ]);

        // Asserting Received Repayment
        $this->assertDatabaseHas(ReceivedRepayment::class, [
            'loan_id'       => $loan->id,
            'amount'        => 1666,
            'currency_code' => $currencyCode,
            'received_at'   => $receivedAt,
        ]);
    }
}