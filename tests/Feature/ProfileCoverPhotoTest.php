<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileCoverPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_displays_the_cover_crop_controls(): void
    {
        $user = User::factory()->create([
            'role_name' => User::ROLE_STAFF,
        ]);

        $this->actingAs($user)
            ->get(route('settings'))
            ->assertOk()
            ->assertSee('Crop Cover Photo')
            ->assertSee('cover-crop-stage', false)
            ->assertSee('coverPhotoZoomRange', false)
            ->assertSee('Everything inside the frame will be saved.');
    }

    public function test_profile_cover_photo_can_be_updated_without_posting_the_disabled_email_field(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'role_name' => User::ROLE_STAFF,
            'email' => 'staff@example.test',
        ]);

        $response = $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'Updated Staff Name',
            'phone_number' => '09171234567',
            'cover_photo_data' => 'data:image/png;base64,' . base64_encode('cover-image'),
        ]);

        $response
            ->assertRedirect(route('settings'))
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('staff@example.test', $user->email);
        $this->assertSame('Updated Staff Name', $user->name);
        $this->assertNotNull($user->cover_photo);
        Storage::disk('public')->assertExists($user->cover_photo);
    }
}
