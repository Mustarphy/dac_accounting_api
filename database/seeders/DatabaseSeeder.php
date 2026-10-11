<?php

namespace Database\Seeders;

use App\Enums\ActivityAction;
use App\Enums\RequestStatus;
use App\Enums\ServiceType;
use App\Models\AccountingRequest;
use App\Models\InformationRequest;
use App\Models\RequestActivity;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * The accounting-request demo data below exists ONLY because this
     * client-only phase has no staff/admin panel to create an
     * InformationRequest or move a request to "ongoing" through the real
     * workflow — without it, those states would be entirely untestable
     * locally. None of this is production data; nothing here is faked
     * through the API, only seeded directly into the dev database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $opened = AccountingRequest::factory()->create([
            'client_id' => $user->id,
            'title' => 'Quarterly bookkeeping review',
            'service_type' => ServiceType::Bookkeeping,
            'status' => RequestStatus::Opened,
        ]);
        $this->logCreated($opened);

        $ongoing = AccountingRequest::factory()->create([
            'client_id' => $user->id,
            'title' => 'Annual tax filing — FY2025',
            'service_type' => ServiceType::TaxFiling,
            'status' => RequestStatus::Ongoing,
        ]);
        $this->logCreated($ongoing);

        // `status` isn't fillable (see InformationRequest) — left unset
        // here deliberately, relying on the column's own 'pending' default,
        // same as every real information request created via the service.
        InformationRequest::create([
            'accounting_request_id' => $ongoing->id,
            'requested_by_id' => null,
            'message' => 'Could you upload your Q4 bank statement? We need it to reconcile the figures you submitted.',
        ]);
        RequestActivity::create([
            'accounting_request_id' => $ongoing->id,
            'actor_id' => null,
            'actor_type' => 'staff',
            'action' => ActivityAction::InformationRequested,
            'description' => 'DAC Accounting requested additional information: Q4 bank statement.',
        ]);

        $concluded = AccountingRequest::factory()->concluded()->create([
            'client_id' => $user->id,
            'title' => 'Payroll setup for new hires',
            'service_type' => ServiceType::Payroll,
        ]);
        $this->logCreated($concluded);
    }

    private function logCreated(AccountingRequest $request): void
    {
        RequestActivity::create([
            'accounting_request_id' => $request->id,
            'actor_id' => $request->client_id,
            'actor_type' => 'client',
            'action' => ActivityAction::RequestCreated,
            'description' => "Request \"{$request->title}\" was submitted.",
        ]);
    }
}
