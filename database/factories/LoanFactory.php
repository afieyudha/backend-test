<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Loan::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        $amount = $this->faker->numberBetween(1000, 10000);
        $terms = $this->faker->numberBetween(1, 12);

        return [
            'user_id'            => User::factory(),           // otomatis bikin user dummy
            'amount'             => $amount,
            'currency_code'      => 'VND',                     // sesuai requirement test
            'terms'              => $terms,
            'processed_at'       => $this->faker->dateTime(),  // tanggal acak
            'outstanding_amount' => $amount,                   // default outstanding = amount
            'status'             => 'ONGOING',                 // default ONGOING
        ];
    }
}
