<?php

namespace Tests\Feature\Client;

use App\Enums\RequestStatus;
use App\Models\AccountingRequest;
use App\Models\InformationRequest;
use App\Models\RequestDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.auth('api')->login($user)];
    }

    // --- Requests ---------------------------------------------------

    public function test_client_can_create_a_request_with_a_generated_reference(): void
    {
        $client = User::factory()->create();

        $response = $this->postJson('/api/v1/client/requests', [
            'title' => 'Bookkeeping for March',
            'description' => 'Please reconcile March transactions.',
            'serviceType' => 'bookkeeping',
        ], $this->authHeader($client));

        $response->assertStatus(201)
            ->assertJsonPath('data.request.title', 'Bookkeeping for March')
            ->assertJsonPath('data.request.status', 'opened');

        $this->assertStringStartsWith('DAC-', $response->json('data.request.reference'));
        $this->assertDatabaseHas('accounting_requests', ['client_id' => $client->id, 'title' => 'Bookkeeping for March']);
        $this->assertDatabaseHas('request_activities', ['accounting_request_id' => $response->json('data.request.id'), 'action' => 'request_created']);
        $this->assertDatabaseHas('client_notifications', ['user_id' => $client->id, 'type' => 'request_submitted']);
    }

    public function test_client_cannot_set_the_status_of_a_new_request(): void
    {
        $client = User::factory()->create();

        $response = $this->postJson('/api/v1/client/requests', [
            'title' => 'Attempted status injection',
            'serviceType' => 'bookkeeping',
            'status' => 'concluded',
        ], $this->authHeader($client));

        $response->assertStatus(201)->assertJsonPath('data.request.status', 'opened');
    }

    public function test_client_only_sees_their_own_requests_in_the_list(): void
    {
        $client = User::factory()->create();
        $other = User::factory()->create();
        AccountingRequest::factory()->count(2)->create(['client_id' => $client->id]);
        AccountingRequest::factory()->count(3)->create(['client_id' => $other->id]);

        $response = $this->getJson('/api/v1/client/requests', $this->authHeader($client));

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.requests'));
    }

    public function test_request_list_can_be_filtered_by_status(): void
    {
        $client = User::factory()->create();
        AccountingRequest::factory()->create(['client_id' => $client->id, 'status' => RequestStatus::Opened]);
        AccountingRequest::factory()->concluded()->create(['client_id' => $client->id]);

        $response = $this->getJson('/api/v1/client/requests?status=concluded', $this->authHeader($client));

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.requests'));
        $this->assertSame('concluded', $response->json('data.requests.0.status'));
    }

    public function test_client_cannot_view_another_clients_request(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $request = AccountingRequest::factory()->create(['client_id' => $owner->id]);

        $this->getJson("/api/v1/client/requests/{$request->id}", $this->authHeader($intruder))
            ->assertStatus(403);
    }

    public function test_request_detail_includes_documents_information_requests_and_activity(): void
    {
        $client = User::factory()->create();
        $request = AccountingRequest::factory()->create(['client_id' => $client->id]);
        InformationRequest::create([
            'accounting_request_id' => $request->id,
            'message' => 'Need your invoice.',
        ]);

        $response = $this->getJson("/api/v1/client/requests/{$request->id}", $this->authHeader($client));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.request.informationRequests')
            ->assertJsonPath('data.request.informationRequests.0.status', 'pending');
    }

    // --- Documents ----------------------------------------------------

    public function test_client_can_upload_a_document_to_their_own_request(): void
    {
        Storage::fake('local');
        $client = User::factory()->create();
        $request = AccountingRequest::factory()->create(['client_id' => $client->id]);
        $file = UploadedFile::fake()->create('statement.pdf', 50, 'application/pdf');

        $response = $this->postJson(
            "/api/v1/client/requests/{$request->id}/documents",
            ['file' => $file, 'category' => 'bank_statement'],
            $this->authHeader($client)
        );

        $response->assertStatus(201)->assertJsonPath('data.document.filename', 'statement.pdf');
        $this->assertDatabaseHas('request_documents', ['accounting_request_id' => $request->id, 'source' => 'client']);
    }

    public function test_client_cannot_upload_a_document_to_another_clients_request(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $request = AccountingRequest::factory()->create(['client_id' => $owner->id]);
        $file = UploadedFile::fake()->create('statement.pdf', 50, 'application/pdf');

        $this->postJson(
            "/api/v1/client/requests/{$request->id}/documents",
            ['file' => $file, 'category' => 'bank_statement'],
            $this->authHeader($intruder)
        )->assertStatus(403);
    }

    public function test_upload_rejects_a_disallowed_file_type(): void
    {
        Storage::fake('local');
        $client = User::factory()->create();
        $request = AccountingRequest::factory()->create(['client_id' => $client->id]);
        $file = UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload');

        $this->postJson(
            "/api/v1/client/requests/{$request->id}/documents",
            ['file' => $file, 'category' => 'supporting'],
            $this->authHeader($client)
        )->assertStatus(422);
    }

    public function test_client_can_download_their_own_document(): void
    {
        Storage::fake('local');
        $client = User::factory()->create();
        $request = AccountingRequest::factory()->create(['client_id' => $client->id]);
        Storage::disk('local')->put('accounting-requests/'.$request->id.'/file.pdf', 'contents');
        $document = RequestDocument::create([
            'accounting_request_id' => $request->id,
            'uploaded_by_id' => $client->id,
            'source' => 'client',
            'category' => 'invoice',
            'original_filename' => 'file.pdf',
            'stored_path' => 'accounting-requests/'.$request->id.'/file.pdf',
            'mime_type' => 'application/pdf',
            'size' => 8,
        ]);

        $this->get("/api/v1/client/documents/{$document->id}/download", $this->authHeader($client))
            ->assertStatus(200);
    }

    public function test_client_cannot_download_another_clients_document(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $request = AccountingRequest::factory()->create(['client_id' => $owner->id]);
        Storage::disk('local')->put('accounting-requests/'.$request->id.'/file.pdf', 'contents');
        $document = RequestDocument::create([
            'accounting_request_id' => $request->id,
            'uploaded_by_id' => $owner->id,
            'source' => 'client',
            'category' => 'invoice',
            'original_filename' => 'file.pdf',
            'stored_path' => 'accounting-requests/'.$request->id.'/file.pdf',
            'mime_type' => 'application/pdf',
            'size' => 8,
        ]);

        $this->get("/api/v1/client/documents/{$document->id}/download", $this->authHeader($intruder))
            ->assertStatus(403);
    }

    // --- Information requests -----------------------------------------

    public function test_client_can_respond_to_an_information_request(): void
    {
        $client = User::factory()->create();
        $request = AccountingRequest::factory()->create(['client_id' => $client->id]);
        $infoRequest = InformationRequest::create([
            'accounting_request_id' => $request->id,
            'message' => 'Please provide last month\'s receipts.',
        ]);

        $response = $this->postJson(
            "/api/v1/client/information-requests/{$infoRequest->id}/respond",
            ['message' => 'Attached.'],
            $this->authHeader($client)
        );

        $response->assertStatus(200)->assertJsonPath('data.informationRequest.status', 'responded');
    }

    public function test_client_cannot_respond_twice(): void
    {
        $client = User::factory()->create();
        $request = AccountingRequest::factory()->create(['client_id' => $client->id]);
        $infoRequest = InformationRequest::create([
            'accounting_request_id' => $request->id,
            'message' => 'Please provide last month\'s receipts.',
        ]);

        $this->postJson("/api/v1/client/information-requests/{$infoRequest->id}/respond", ['message' => 'First.'], $this->authHeader($client))
            ->assertStatus(200);

        $this->postJson("/api/v1/client/information-requests/{$infoRequest->id}/respond", ['message' => 'Second.'], $this->authHeader($client))
            ->assertStatus(422);
    }

    public function test_client_cannot_respond_to_another_clients_information_request(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $request = AccountingRequest::factory()->create(['client_id' => $owner->id]);
        $infoRequest = InformationRequest::create([
            'accounting_request_id' => $request->id,
            'message' => 'Please provide last month\'s receipts.',
        ]);

        $this->postJson(
            "/api/v1/client/information-requests/{$infoRequest->id}/respond",
            ['message' => 'Hijack attempt.'],
            $this->authHeader($intruder)
        )->assertStatus(403);
    }

    // --- Dashboard, notifications, account -----------------------------

    public function test_dashboard_reflects_real_counts_with_no_fabricated_data(): void
    {
        $client = User::factory()->create();
        AccountingRequest::factory()->create(['client_id' => $client->id, 'status' => RequestStatus::Opened]);
        AccountingRequest::factory()->concluded()->create(['client_id' => $client->id]);

        $response = $this->getJson('/api/v1/client/dashboard', $this->authHeader($client));

        $response->assertStatus(200)
            ->assertJsonPath('data.counts.opened', 1)
            ->assertJsonPath('data.counts.concluded', 1)
            ->assertJsonPath('data.counts.ongoing', 0);
    }

    public function test_new_client_gets_an_honest_empty_dashboard(): void
    {
        $client = User::factory()->create();

        $this->getJson('/api/v1/client/dashboard', $this->authHeader($client))
            ->assertStatus(200)
            ->assertJsonPath('data.counts.opened', 0)
            ->assertJsonPath('data.recentRequests', []);
    }

    public function test_notifications_are_scoped_to_the_authenticated_client(): void
    {
        $client = User::factory()->create();
        $other = User::factory()->create();
        $request = AccountingRequest::factory()->create(['client_id' => $client->id]);

        $this->postJson('/api/v1/client/requests', [
            'title' => 'Trigger a notification',
            'serviceType' => 'other',
        ], $this->authHeader($client));

        $otherResponse = $this->getJson('/api/v1/client/notifications', $this->authHeader($other));
        $this->assertCount(0, $otherResponse->json('data.notifications'));
    }

    public function test_client_can_update_their_name_but_not_their_role(): void
    {
        $client = User::factory()->create(['name' => 'Original Name']);

        $response = $this->patchJson('/api/v1/client/account', [
            'name' => 'Updated Name',
            'role' => 'admin',
        ], $this->authHeader($client));

        $response->assertStatus(200)->assertJsonPath('data.user.name', 'Updated Name');
        $this->assertSame('client', $client->fresh()->role->value);
    }

    public function test_client_can_change_their_password_with_correct_current_password(): void
    {
        $client = User::factory()->create(['password' => 'OldPassword123']);

        $this->postJson('/api/v1/client/account/password', [
            'currentPassword' => 'OldPassword123',
            'newPassword' => 'NewPassword456',
            'newPasswordConfirmation' => 'NewPassword456',
        ], $this->authHeader($client))->assertStatus(200);

        $this->postJson('/api/v1/auth/login', ['email' => $client->email, 'password' => 'NewPassword456'])
            ->assertStatus(200);
    }

    public function test_password_change_rejects_wrong_current_password(): void
    {
        $client = User::factory()->create(['password' => 'OldPassword123']);

        $this->postJson('/api/v1/client/account/password', [
            'currentPassword' => 'WrongPassword',
            'newPassword' => 'NewPassword456',
            'newPasswordConfirmation' => 'NewPassword456',
        ], $this->authHeader($client))->assertStatus(422);
    }
}
