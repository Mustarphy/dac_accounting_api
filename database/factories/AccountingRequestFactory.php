<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Enums\ServiceType;
use App\Models\AccountingRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountingRequest>
 */
class AccountingRequestFactory extends Factory
{
    protected $model = AccountingRequest::class;

    public function definition(): array
    {
        return [
            'client_id' => User::factory(),
            'reference' => 'DAC-'.now()->format('Y').'-'.fake()->unique()->numerify('#####'),
            'title' => fake()->randomElement([
                'Quarterly bookkeeping review',
                'Annual tax filing',
                'Payroll setup for new hires',
                'Year-end financial statements',
            ]),
            'description' => fake()->sentence(12),
            'service_type' => fake()->randomElement(ServiceType::cases()),
            'status' => RequestStatus::Opened,
        ];
    }

    public function ongoing(): static
    {
        return $this->state(['status' => RequestStatus::Ongoing]);
    }

    public function concluded(): static
    {
        return $this->state([
            'status' => RequestStatus::Concluded,
            'concluded_at' => now(),
        ]);
    }
}
