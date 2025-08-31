<?php

namespace Database\Factories;

use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

class DebitCardTransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DebitCardTransaction::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'amount' => $this->faker->randomFloat(2, 1, 1000), // Random amount between 1-1000
            'currency_code' => $this->faker->randomElement(['USD', 'SGD', 'VND']),
            'description' => $this->faker->sentence(),
            'debit_card_id' => fn () => DebitCard::factory()->create(),
        ];
    }
}