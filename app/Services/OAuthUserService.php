<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use RuntimeException;

class OAuthUserService
{
    public function findOrCreateUser(string $provider, SocialiteUserContract $socialiteUser): User
    {
        $provider = strtolower($provider);
        $email = strtolower(trim((string) $socialiteUser->getEmail()));

        if ($email === '') {
            throw new RuntimeException('The identity provider did not return an email address.');
        }

        $this->assertProviderAllowed($provider, $socialiteUser, $email);

        $providerColumn = $this->providerColumn($provider);
        $providerId = (string) $socialiteUser->getId();

        $user = null;
        if ($providerId !== '') {
            $user = User::where($providerColumn, $providerId)->first();
        }

        if (!$user) {
            $user = User::where('email', $email)->first();
        }

        if ($user && $user->status !== 'Active') {
            throw new RuntimeException('Your account is not active.');
        }

        if (!$user) {
            $user = new User();
            $user->email = $email;
            $user->join_date = now()->toDayDateTimeString();
            $user->status = 'Active';
            $user->role_name = User::ROLE_DSWD;
            $user->password = Hash::make(Str::random(48));
        }

        $user->name = $socialiteUser->getName() ?: $socialiteUser->getNickname() ?: $email;
        $user->{$providerColumn} = $providerId ?: $user->{$providerColumn};
        $user->auth_provider = $provider;
        $user->provider_avatar = $socialiteUser->getAvatar() ?: $user->provider_avatar;
        $user->email_verified_at = $user->email_verified_at ?: now();
        $user->save();

        if ($provider === 'azure') {
            $this->refreshAzureAvatar($user, (string) $socialiteUser->token);
        }

        return $user;
    }

    private function refreshAzureAvatar(User $user, string $token): void
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get('https://graph.microsoft.com/v1.0/me/photo/$value');

            if (!$response->successful()) {
                return;
            }

            $mime = $response->header('Content-Type');
            $extension = str_contains((string) $mime, 'png') ? 'png' : 'jpg';
            $filename = 'avatars/azure-' . Str::uuid() . '.' . $extension;

            Storage::disk('public')->put($filename, $response->body());

            $oldProviderAvatar = $user->provider_avatar;
            $user->provider_avatar = asset('storage/' . $filename);
            $user->save();

            if ($oldProviderAvatar && str_contains($oldProviderAvatar, '/storage/avatars/azure-')) {
                $oldPath = Str::after($oldProviderAvatar, '/storage/');
                Storage::disk('public')->delete($oldPath);
            }
        } catch (\Throwable $e) {
            // Keep the existing avatar if the photo cannot be fetched.
        }
    }

    private function assertProviderAllowed(string $provider, SocialiteUserContract $socialiteUser, string $email): void
    {
        if (!in_array($provider, ['azure'], true)) {
            throw new RuntimeException('Unsupported identity provider.');
        }

        if ($provider === 'azure') {
            $allowedDomain = strtolower((string) config('authentication.azure_allowed_domain', 'cityofimus.gov.ph'));
            $domain = strtolower(Str::after($email, '@'));

            if ($domain !== $allowedDomain) {
                throw new RuntimeException("Azure sign-in is limited to @{$allowedDomain} accounts.");
            }
        }
    }

    private function providerColumn(string $provider): string
    {
        return match ($provider) {
            'azure' => 'azure_id',
            default => throw new RuntimeException('Unsupported identity provider.'),
        };
    }
}