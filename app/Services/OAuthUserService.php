<?php

namespace App\Services;

use App\Models\User;
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
            $user->role_name = 'User';
            $user->password = Hash::make(Str::random(48));
        }

        $user->name = $socialiteUser->getName() ?: $socialiteUser->getNickname() ?: $email;
        $user->{$providerColumn} = $providerId ?: $user->{$providerColumn};
        $user->auth_provider = $provider;
        $user->provider_avatar = $socialiteUser->getAvatar() ?: $user->provider_avatar;
        $user->email_verified_at = $user->email_verified_at ?: now();
        $user->save();

        return $user;
    }

    private function assertProviderAllowed(string $provider, SocialiteUserContract $socialiteUser, string $email): void
    {
        if (!in_array($provider, ['google', 'azure'], true)) {
            throw new RuntimeException('Unsupported identity provider.');
        }

        if ($provider === 'google' && !$this->googleEmailIsVerified($socialiteUser)) {
            throw new RuntimeException('Google account email must be verified.');
        }

        if ($provider === 'azure') {
            $allowedDomain = strtolower((string) config('authentication.azure_allowed_domain', 'cityofimus.gov.ph'));
            $domain = strtolower(Str::after($email, '@'));

            if ($domain !== $allowedDomain) {
                throw new RuntimeException("Azure sign-in is limited to @{$allowedDomain} accounts.");
            }
        }
    }

    private function googleEmailIsVerified(SocialiteUserContract $socialiteUser): bool
    {
        $raw = $socialiteUser->getRaw();

        return array_key_exists('email_verified', $raw)
            ? (bool) $raw['email_verified']
            : false;
    }

    private function providerColumn(string $provider): string
    {
        return match ($provider) {
            'google' => 'google_id',
            'azure' => 'azure_id',
            default => throw new RuntimeException('Unsupported identity provider.'),
        };
    }
}
