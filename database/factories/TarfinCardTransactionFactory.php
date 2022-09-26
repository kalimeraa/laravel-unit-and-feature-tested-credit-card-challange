<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CurrencyType;
use App\Models\TarfinCard;
use App\Models\TarfinCardTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

class TarfinCardTransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TarfinCardTransaction::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'tarfin_card_id' => TarfinCard::factory(),
            'amount'         => $this->faker->numberBetween(100, 999) * 10,
            'currency_code'  => $this->faker->randomElement(CurrencyType::cases()),
        ];
    }

    /**
     * Set customer for Tarfin Card.
     */
    public function forTarfinCard(TarfinCard $tarfinCard): Factory
    {
        return $this->state(fn (): array => [
            'tarfin_card_id' => $tarfinCard->id,
        ]);
    }
}
