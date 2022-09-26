<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CurrencyType;
use App\Enums\PaymentStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Loan>
 */
class LoanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $amount = rand(100, 1000);
        $terms = [3, 6];

        return [
            'currency_code'  => $this->faker->randomElement(CurrencyType::cases()),
            'user_id' => User::factory()->create(),
            'amount' => $amount,
            'terms' => $terms[rand(0, 1)],
            'outstanding_amount' => $amount,
            'status' => PaymentStatus::DUE,
            'processed_at' => Carbon::now(),
        ];
    }
}
