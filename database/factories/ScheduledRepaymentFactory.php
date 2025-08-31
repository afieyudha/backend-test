<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\ScheduledRepayment;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduledRepaymentFactory extends Factory
{
    protected $model = ScheduledRepayment::class;

    public function definition()
    {
        return [
            'loan_id' => Loan::factory(),
            'amount' => $this->faker->randomFloat(2, 100, 5000),
            'outstanding_amount' => function (array $attributes) {
                return $attributes['amount']; // Default outstanding = amount
            },
            'currency_code' => 'VND',
            'due_date' => $this->faker->dateTimeBetween('+1 month', '+12 months'),
            'status' => 'due',
        ];
    }

    public function repaid()
    {
        return $this->state([
            'status' => 'repaid',
            'outstanding_amount' => 0,
            'repaid_at' => now(),
        ]);
    }
}