<?php

namespace Tests\Feature;

use App\Services\OAuthUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class OAuthUserServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_azure_avatar_refresh_does_not_block_user_authentication(): void
    {
        Http::fake([
            'graph.microsoft.com/*' => Http::response('', 404),
        ]);

        $socialiteUser = (new SocialiteUser)->map([
            'id' => 'azure-123',
            'name' => 'Fast Login User',
            'email' => 'fast.login@cityofimus.gov.ph',
        ]);
        $socialiteUser->token = 'access-token';

        $user = app(OAuthUserService::class)->findOrCreateUser('azure', $socialiteUser);

        $this->assertSame('fast.login@cityofimus.gov.ph', $user->email);
        Http::assertNothingSent();

        defer()->invoke();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://graph.microsoft.com/v1.0/me/photo/$value');
    }
}
