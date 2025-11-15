<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\ReceivedRepayment;
use App\Models\ScheduledRepayment;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReceivedRepaymentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ReceivedRepayment::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            // TODO: Complete factory
            'loan_id' => Loan::factory(),
            'amount' => $this->faker->randomNumber(),
            'currency_code' => \App\Models\Loan::CURRENCY_VND,
            'received_at' => $this->faker->date(),
        ];
    }
}
