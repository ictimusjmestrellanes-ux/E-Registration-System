<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\User;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Session;
use Auth;
use DB;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    public function __construct()
    {
        $this->middleware('guest')->except(['logout', 'locked', 'unlock']);
    }

    /** Display the login page */
    public function login()
    {
        return view('auth.login');
    }

    /** Authenticate user and redirect */
    public function authenticate(Request $request)
    {
        $request->validate([
            'email'    => 'required|string',
            'password' => 'required|string',
        ]);
        try {
            $credentials = $request->only('email', 'password') + ['status' => 'Active'];
            if (Auth::attempt($credentials)) {
                $user = Auth::user();
                Session::put($this->getUserSessionData($user));
             
                // Update last login
                $user->update(['last_login' => Carbon::now()]);
                app(ActivityLogger::class)->record(
                    'login',
                    'User logged in.',
                    ['user_id' => $user->id, 'email' => $user->email]
                );
                return redirect()->intended(route('dashboard'))->with('success', 'Login successfully :)'); 
            }
            return redirect('login')->with('error', 'Wrong Username or Password');
        } catch (\Exception $e) {
            \Log::info($e);
            return redirect()->back()->with('error', 'Login failed. Please try again.');
        }
    }

    /** Redirect user to Google OAuth consent screen */
    public function redirectToGoogle(Request $request)
    {
        $clientId = config('services.google.client_id');
        $redirectUri = config('services.google.redirect');

        if (!$clientId || !$redirectUri) {
            return redirect('login')->with('error', 'Google login is not configured yet.');
        }

        $state = $this->makeGoogleOAuthState($request);

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?' . $query);
    }

    /** Handle Google OAuth callback and sign the user in */
    public function handleGoogleCallback(Request $request)
    {
        if ($request->filled('error')) {
            return redirect('login')->with('error', 'Google login was cancelled.');
        }

        $incomingState = (string) $request->query('state', '');

        if (!$this->hasValidGoogleOAuthState($request, $incomingState)) {
            return redirect('login')->with('error', 'Invalid Google login session. Please try again.');
        }

        if (!$request->filled('code')) {
            return redirect('login')->with('error', 'Google did not return an authorization code.');
        }

        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');
        $redirectUri = config('services.google.redirect');

        if (!$clientId || !$clientSecret || !$redirectUri) {
            return redirect('login')->with('error', 'Google login is not configured yet.');
        }

        try {
            $tokenResponse = Http::asForm()
                ->timeout(15)
                ->post('https://oauth2.googleapis.com/token', [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'redirect_uri' => $redirectUri,
                    'grant_type' => 'authorization_code',
                    'code' => $request->query('code'),
                ]);

            if (!$tokenResponse->successful() || !$tokenResponse->json('access_token')) {
                \Log::warning('Google token exchange failed.', [
                    'status' => $tokenResponse->status(),
                    'error' => $tokenResponse->json('error'),
                    'error_description' => $tokenResponse->json('error_description'),
                ]);

                return redirect('login')->with('error', 'Unable to verify Google login.');
            }

            $profileResponse = Http::withToken($tokenResponse->json('access_token'))
                ->timeout(15)
                ->get('https://www.googleapis.com/oauth2/v3/userinfo');

            if (!$profileResponse->successful()) {
                \Log::warning('Google profile request failed.', [
                    'status' => $profileResponse->status(),
                    'error' => $profileResponse->json('error'),
                    'error_description' => $profileResponse->json('error_description'),
                ]);

                return redirect('login')->with('error', 'Unable to retrieve Google account details.');
            }

            $googleUser = $profileResponse->json();
            $email = strtolower((string) ($googleUser['email'] ?? ''));
            $emailVerified = (bool) ($googleUser['email_verified'] ?? false);

            if (!$email || !$emailVerified) {
                return redirect('login')->with('error', 'Google account email must be verified.');
            }

            $user = User::where('email', $email)->first();

            if ($user && $user->status !== 'Active') {
                return redirect('login')->with('error', 'Your account is not active.');
            }

            if (!$user) {
                $user = new User;
                $user->name = $googleUser['name'] ?? $email;
                $user->email = $email;
                $user->join_date = Carbon::now()->toDayDateTimeString();
                $user->status = 'Active';
                $user->role_name = 'User';
                $user->email_verified_at = now();
                $user->password = Hash::make(Str::random(48));
                $user->save();
            } elseif (!$user->email_verified_at) {
                $user->email_verified_at = now();
                $user->save();
            }

            Auth::login($user);
            Session::put($this->getUserSessionData($user));

            $user->update(['last_login' => Carbon::now()]);
            app(ActivityLogger::class)->record(
                'login',
                'User logged in with Google.',
                ['user_id' => $user->id, 'email' => $user->email]
            );

            return redirect()->intended(route('dashboard'))->with('success', 'Login successfully :)');
        } catch (\Exception $e) {
            \Log::error($e);
            return redirect('login')->with('error', 'Google login failed. Please try again.');
        }
    }

    private function makeGoogleOAuthState(Request $request): string
    {
        $payload = [
            'nonce' => Str::random(40),
            'issued_at' => now()->timestamp,
        ];

        $request->session()->put('google_oauth_state', $payload['nonce']);

        return Crypt::encryptString(json_encode($payload));
    }

    private function hasValidGoogleOAuthState(Request $request, string $incomingState): bool
    {
        if ($incomingState === '') {
            return false;
        }

        try {
            $payload = json_decode(Crypt::decryptString($incomingState), true);
        } catch (\Throwable $e) {
            $expectedState = $request->session()->pull('google_oauth_state');

            return $expectedState && hash_equals($expectedState, $incomingState);
        }

        if (!is_array($payload)) {
            return false;
        }

        $nonce = (string) ($payload['nonce'] ?? '');
        $issuedAt = (int) ($payload['issued_at'] ?? 0);

        if (!$nonce || !$issuedAt || now()->timestamp - $issuedAt > 600) {
            return false;
        }

        $expectedNonce = $request->session()->pull('google_oauth_state');
        if ($expectedNonce && !hash_equals($expectedNonce, $nonce)) {
            return false;
        }

        return true;
    }

    /** Prepare User Session Data */
    private function getUserSessionData($user)
    {
        return [
            'name'                => $user->name,
            'email'               => $user->email,
            'user_id'             => $user->user_id,
            'join_date'           => $user->join_date,
            'phone_number'        => $user->phone_number,
            'status'              => $user->status,
            'role_name'           => $user->role_name,
            'avatar'              => $user->avatar,
            'position'            => $user->position,
            'department'          => $user->department,
            'line_manager'        => $user->line_manager,
            'second_line_manager' => $user->second_line_manager,
        ];
    }

    /** Logout and clear session */
    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            app(ActivityLogger::class)->record(
                'logout',
                'User logged out.',
                ['user_id' => $user->id, 'email' => $user->email]
            );
        }

        $request->session()->flush();
        Auth::logout();
        return redirect('login')->with('success', 'Logged out successfully!');
    }
}
