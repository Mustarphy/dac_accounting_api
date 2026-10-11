<?php

namespace Tests\Feature\Staff;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\AccountingRequest;
use App\Models\InformationRequest;
use App\Models\RequestDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.auth('api')->login($user)];
    }

    // --- Access control --------------------------------------------------

    public function test_a_client_cannot_access_any_staff_route(): void
    {
        $client = User::factory()->create(['role' => UserRole::Client]);

        $this->getJson('/api/v1/staff/dashboard', $this->authHeader($client))->assertStatus(403);
        $this->getJson('/api/v1/staff/requests', $this->authHeader($client))->assertStatus(403);
        $this->getJson('/api/v1/staff/clients', $this->authHeader($client))->assertStatus(403);
    }

    public function test_an_unauthenticated_request_is_rejected_not_redirected(): void
    {
        $this->getJson('/api/v1/staff/dashboard')->assertStatus(401);
    }

    public function test_both_staff_and_admin_roles_can_access_the_panel(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->getJson('/api/v1/staff/dashboard', $this->authHeader($staff))->assertStatus(200);
        $this->getJson('/api/v1/staff/dashboard', $this->authHeader($admin))->assertStatus(200);
    }

    public function test_registering_never_grants_staff_access_regardless_of_payload(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Attempted Escalation',
            'email' => 'escalate@example.com',
            'password' => 'Password123',
            'passwordConfirmation' => 'Password123',
            'role' => 'admin',
        ]);

        $response->assertStatus(201)->assertJsonPath('data.user.role', 'client');

        $user = User::where('email', 'escalate@example.com')->first();
        $this->getJson('/api/v1/staff/dashboard', $this->authHeader($user))->assertStatus(403);
    }

    // --- Dashboard ---------------------------------------------------------

    public function test_staff_dashboard_reflects_all_clients_not_just_one(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $clientA = User::factory()->create();
        $clientB = User::factory()->create();
        AccountingRequest::factory()->create(['client_id' => $clientA->id]);
        AccountingRequest::factory()->create(['client_id' => $clientB->id]);

        $response = $this->getJson('/api/v1/staff/dashboard', $this->authHeader($staff));

        $response->assertStatus(200)->assertJsonPath('data.counts.total', 2);
    }

    // --- Client management ---------------------------------------------

    public function test_staff_can_list_and_search_clients(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        User::factory()->create(['name' => 'Findable Client', 'role' => UserRole::Client]);
        User::factory()->create(['name' => 'Someone Else', 'role' => UserRole::Client]);

        $response = $this->getJson('/api/v1/staff/clients?search=Findable', $this->authHeader($staff));

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.clients'));
    }

    public function test_staff_cannot_see_a_clients_password_hash(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $client = User::factory()->create();

        $response = $this->getJson("/api/v1/staff/clients/{$client->id}", $this->authHeader($staff));

        $response->assertStatus(200)->assertJsonMissingPath('data.client.password');
    }

    // --- Status transitions ---------------------------------------------

    public function test_staff_can_move_a_request_from_opened_to_ongoing(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $request = AccountingRequest::factory()->create(['status' => RequestStatus::Opened]);

        $response = $this->patchJson(
            "/api/v1/staff/requests/{$request->id}/status",
            ['status' => 'ongoing'],
            $this->authHeader($staff)
        );

        $response->assertStatus(200)->assertJsonPath('data.request.status', 'ongoing');
        $this->assertDatabaseHas('request_activities', ['accounting_request_id' => $request->id, 'action' => 'status_changed']);
        $this->assertDatabaseHas('client_notifications', ['accounting_request_id' => $request->id, 'type' => 'status_updated']);
    }

    public function test_cannot_skip_directly_from_opened_to_concluded(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $request = AccountingRequest::factory()->create(['status' => RequestStatus::Opened]);

        $this->patchJson(
            "/api/v1/staff/requests/{$request->id}/status",
            ['status' => 'concluded'],
            $this->authHeader($staff)
        )->assertStatus(422);

        $this->assertSame('opened', $request->fresh()->status->value);
    }

    public function test_cannot_conclude_with_a_pending_information_request(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $request = AccountingRequest::factory()->create(['status' => RequestStatus::Ongoing]);
        InformationRequest::create(['accounting_request_id' => $request->id, 'message' => 'Need a document.']);

        $response = $this->patchJson(
            "/api/v1/staff/requests/{$request->id}/status",
            ['status' => 'concluded'],
            $this->authHeader($staff)
        );

        $response->assertStatus(422);
        $this->assertSame('ongoing', $request->fresh()->status->value);
    }

    public function test_can_conclude_once_information_requests_are_resolved(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $request = AccountingRequest::factory()->create(['status' => RequestStatus::Ongoing]);
        $informationRequest = InformationRequest::create([
            'accounting_request_id' => $request->id,
            'message' => 'Need a document.',
        ]);
        // status/response_message/responded_at aren't fillable (by
        // design — see InformationRequest) so the real respond flow uses
        // forceFill; this test does the same to simulate an already-resolved record.
        $informationRequest->forceFill([
            'status' => 'responded',
            'response_message' => 'Here it is.',
            'responded_at' => now(),
        ])->save();

        $response = $this->patchJson(
            "/api/v1/staff/requests/{$request->id}/status",
            ['status' => 'concluded'],
            $this->authHeader($staff)
        );

        $response->assertStatus(200)->assertJsonPath('data.request.status', 'concluded');
        $this->assertNotNull($request->fresh()->concluded_at);
    }

    public function test_reopening_a_concluded_request_clears_concluded_at(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $request = AccountingRequest::factory()->concluded()->create();

        $response = $this->patchJson(
            "/api/v1/staff/requests/{$request->id}/status",
            ['status' => 'ongoing'],
            $this->authHeader($staff)
        );

        $response->assertStatus(200);
        $this->assertNull($request->fresh()->concluded_at);
    }

    public function test_setting_the_same_status_is_rejected(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $request = AccountingRequest::factory()->create(['status' => RequestStatus::Opened]);

        $this->patchJson(
            "/api/v1/staff/requests/{$request->id}/status",
            ['status' => 'opened'],
            $this->authHeader($staff)
        )->assertStatus(422);
    }

    // --- Information requests (staff-created) ---------------------------

    public function test_staff_can_create_an_information_request_and_it_appears_to_the_client(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $client = User::factory()->create();
        $request = AccountingRequest::factory()->create(['client_id' => $client->id]);

        $this->postJson(
            "/api/v1/staff/requests/{$request->id}/information-requests",
            ['message' => 'Please send your receipts.'],
            $this->authHeader($staff)
        )->assertStatus(201);

        $clientResponse = $this->getJson('/api/v1/client/information-requests', $this->authHeader($client));
        $clientResponse->assertStatus(200);
        $this->assertCount(1, $clientResponse->json('data.informationRequests'));
    }

    // --- Document review --------------------------------------------------

    public function test_staff_can_approve_a_client_submitted_document(): void
    {
        Storage::fake('local');
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $client = User::factory()->create();
        $request = AccountingRequest::factory()->create(['client_id' => $client->id]);
        $document = RequestDocument::create([
            'accounting_request_id' => $request->id,
            'uploaded_by_id' => $client->id,
            'source' => 'client',
            'category' => 'invoice',
            'original_filename' => 'invoice.pdf',
            'stored_path' => 'fake/path.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);

        $response = $this->postJson(
            "/api/v1/staff/documents/{$document->id}/review",
            ['status' => 'approved', 'notes' => 'All good.'],
            $this->authHeader($staff)
        );

        $response->assertStatus(200)->assertJsonPath('data.document.reviewStatus', 'approved');
    }

    public function test_cannot_review_a_dac_issued_deliverable(): void
    {
        Storage::fake('local');
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $request = AccountingRequest::factory()->create();
        $deliverable = RequestDocument::create([
            'accounting_request_id' => $request->id,
            'uploaded_by_id' => $staff->id,
            'source' => 'dac',
            'category' => 'deliverable',
            'original_filename' => 'final.pdf',
            'stored_path' => 'fake/path.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);

        $this->postJson(
            "/api/v1/staff/documents/{$deliverable->id}/review",
            ['status' => 'approved'],
            $this->authHeader($staff)
        )->assertStatus(422);
    }

    public function test_staff_can_download_a_clients_document(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('fake/path.pdf', 'contents');
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $client = User::factory()->create();
        $request = AccountingRequest::factory()->create(['client_id' => $client->id]);
        $document = RequestDocument::create([
            'accounting_request_id' => $request->id,
            'uploaded_by_id' => $client->id,
            'source' => 'client',
            'category' => 'invoice',
            'original_filename' => 'invoice.pdf',
            'stored_path' => 'fake/path.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);

        $this->get("/api/v1/staff/documents/{$document->id}/download", $this->authHeader($staff))
            ->assertStatus(200);
    }

    public function test_a_client_cannot_use_the_staff_download_route(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('fake/path.pdf', 'contents');
        $client = User::factory()->create();
        $request = AccountingRequest::factory()->create(['client_id' => $client->id]);
        $document = RequestDocument::create([
            'accounting_request_id' => $request->id,
            'uploaded_by_id' => $client->id,
            'source' => 'client',
            'category' => 'invoice',
            'original_filename' => 'invoice.pdf',
            'stored_path' => 'fake/path.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);

        $this->get("/api/v1/staff/documents/{$document->id}/download", $this->authHeader($client))
            ->assertStatus(403);
    }

    // --- Deliverables -----------------------------------------------------

    public function test_staff_can_publish_a_deliverable_and_client_can_see_it(): void
    {
        Storage::fake('local');
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $client = User::factory()->create();
        $request = AccountingRequest::factory()->create(['client_id' => $client->id]);
        $file = UploadedFile::fake()->create('final-report.pdf', 50, 'application/pdf');

        $this->postJson(
            "/api/v1/staff/requests/{$request->id}/deliverables",
            ['file' => $file],
            $this->authHeader($staff)
        )->assertStatus(201);

        $clientView = $this->getJson("/api/v1/client/requests/{$request->id}", $this->authHeader($client));
        $clientView->assertStatus(200);
        $documents = $clientView->json('data.request.documents');
        $this->assertSame('dac', $documents[0]['source']);
        $this->assertDatabaseHas('client_notifications', ['accounting_request_id' => $request->id, 'type' => 'deliverable_available']);
    }

    // --- Internal notes stay internal ------------------------------------

    public function test_internal_notes_never_appear_in_the_client_facing_request_detail(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $client = User::factory()->create();
        $request = AccountingRequest::factory()->create(['client_id' => $client->id]);

        $this->postJson(
            "/api/v1/staff/requests/{$request->id}/notes",
            ['body' => 'Confidential: this client disputes invoices often.'],
            $this->authHeader($staff)
        )->assertStatus(201);

        $clientView = $this->getJson("/api/v1/client/requests/{$request->id}", $this->authHeader($client));
        $clientView->assertStatus(200);
        $this->assertStringNotContainsString('disputes invoices', $clientView->getContent());
        $clientView->assertJsonMissingPath('data.request.internalNotes');
    }

    public function test_internal_notes_appear_in_the_staff_facing_request_detail(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $request = AccountingRequest::factory()->create();

        $this->postJson(
            "/api/v1/staff/requests/{$request->id}/notes",
            ['body' => 'Internal note for staff only.'],
            $this->authHeader($staff)
        )->assertStatus(201);

        $staffView = $this->getJson("/api/v1/staff/requests/{$request->id}", $this->authHeader($staff));
        $staffView->assertStatus(200);
        $this->assertStringContainsString('Internal note for staff only.', $staffView->getContent());
    }
}
