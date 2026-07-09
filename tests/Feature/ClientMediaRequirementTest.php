<?php

namespace Tests\Feature;

use App\Models\ArchivedClient;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientMediaRequirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_client_requires_photo_and_fingerprint_capture(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->post(route('clients.store'), $this->clientPayload());

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'photo_data',
            'fingerprint_data',
            'fingerprint_template',
        ]);
        $this->assertDatabaseCount('clients', 0);
    }

    public function test_store_client_accepts_valid_photo_and_fingerprint_capture(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        $response = $this->post(route('clients.store'), $this->clientPayload([
            'photo_data' => $this->dataUri('photo-capture'),
            'fingerprint_data' => $this->dataUri('fingerprint-capture'),
            'fingerprint_template' => '<FingerprintTemplate>valid</FingerprintTemplate>',
        ]));

        $response->assertRedirect(route('clients'));
        $response->assertSessionHas('success', 'Client saved successfully.');

        $client = Client::query()->firstOrFail();

        $this->assertNotEmpty($client->photo_path);
        $this->assertNotEmpty($client->fingerprint_path);
        $this->assertNotEmpty($client->fingerprint_template);
        Storage::disk('public')->assertExists($client->photo_path);
        Storage::disk('public')->assertExists($client->fingerprint_path);
    }

    public function test_update_client_allows_existing_media_without_new_capture(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        Storage::disk('public')->put('clients/existing-photo.png', 'photo-bytes');
        Storage::disk('public')->put('fingerprints/existing-fingerprint.png', 'fingerprint-bytes');

        $client = Client::create($this->clientPayload([
            'first_name' => 'Old',
            'last_name' => 'Client',
            'photo_path' => 'clients/existing-photo.png',
            'fingerprint_path' => 'fingerprints/existing-fingerprint.png',
            'fingerprint_template' => '<FingerprintTemplate>existing</FingerprintTemplate>',
        ]));

        $response = $this->put(route('clients.update', $client), [
            'first_name' => 'Updated',
            'last_name' => 'Client',
            'contact' => '09111111111',
        ]);

        $response->assertRedirect(route('client.list'));
        $response->assertSessionHas('success', 'Client updated successfully.');

        $client->refresh();

        $this->assertSame('Updated', $client->first_name);
        $this->assertSame('clients/existing-photo.png', $client->photo_path);
        $this->assertSame('fingerprints/existing-fingerprint.png', $client->fingerprint_path);
        $this->assertSame('<FingerprintTemplate>existing</FingerprintTemplate>', $client->fingerprint_template);
        Storage::disk('public')->assertExists('clients/existing-photo.png');
        Storage::disk('public')->assertExists('fingerprints/existing-fingerprint.png');
    }

    public function test_update_client_rejects_incomplete_record_without_media(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        $client = Client::create($this->clientPayload([
            'first_name' => 'Legacy',
            'last_name' => 'Client',
            'photo_path' => null,
            'fingerprint_path' => null,
            'fingerprint_template' => null,
        ]));

        $response = $this->put(route('clients.update', $client), [
            'first_name' => 'Legacy',
            'last_name' => 'Client',
            'contact' => '09999999999',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors([
            'photo_data',
            'fingerprint_data',
            'fingerprint_template',
        ]);

        $client->refresh();
        $this->assertNull($client->photo_path);
        $this->assertNull($client->fingerprint_path);
        $this->assertNull($client->fingerprint_template);
    }

    public function test_restore_archived_client_rejects_missing_media(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        $archivedClient = ArchivedClient::create($this->clientPayload([
            'original_client_id' => 999,
            'first_name' => 'Archived',
            'last_name' => 'Client',
            'photo_path' => null,
            'fingerprint_path' => null,
            'fingerprint_template' => null,
            'archived_at' => now(),
        ]));

        $response = $this->post(route('archive.restore', $archivedClient));

        $response->assertRedirect(route('archive.list'));
        $response->assertSessionHas('error', 'Archived clients must have both a photo and fingerprint before they can be restored.');
        $this->assertDatabaseHas('archived_clients', [
            'id' => $archivedClient->id,
        ]);
        $this->assertDatabaseMissing('clients', [
            'first_name' => 'Archived',
            'last_name' => 'Client',
        ]);
    }

    private function clientPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Jane',
            'middle_name' => null,
            'last_name' => 'Doe',
            'suffix' => null,
            'age' => 30,
            'birth_date' => '2000-01-01',
            'birthplace' => 'Sample Birthplace',
            'education' => 'HIGH SCHOOL GRADUATE',
            'course' => 'N/A',
            'sector' => 'COMMON CITIZEN',
            'position_organization' => 'NONE',
            'gender' => 'Female',
            'civil_status' => 'Single',
            'email' => 'jane.doe@example.com',
            'contact' => '09123456789',
            'contact_2' => null,
            'address' => 'Sample Address',
            'province' => 'CAVITE',
            'city' => 'CITY OF IMUS',
            'barangay' => 'BAGONG SILANG',
        ], $overrides);
    }

    private function dataUri(string $seed): string
    {
        return 'data:image/png;base64,' . base64_encode($seed);
    }
}
