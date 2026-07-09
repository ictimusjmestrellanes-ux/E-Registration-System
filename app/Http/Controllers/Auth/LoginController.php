<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\OAuthUserService;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Laravel\Socialite\Facades\Socialite;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    public function __construct()
    {
        $this->middleware('guest')->except(['logout', 'locked', 'unlock']);
    }

    public function login()
    {
        return view('auth.login', [
            'googleConfigured' => $this->providerIsConfigured('google'),
            'azureConfigured' => $this->providerIsConfigured('azure'),
        ]);
    }

    public function authenticate(Request $request)
    {
        if (!config('authentication.local_auth_enabled')) {
            return redirect('login')->with('error', 'Local login is disabled in this environment.');
        }

        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password') + ['status' => 'Active'];

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return redirect('login')->with('error', 'Wrong Username or Password');
        }

        $request->session()->regenerate();
        $this->finishLogin(Auth::user(), 'login', 'User logged in.');

        return redirect()->intended(route('dashboard'))->with('success', 'Login successfully :)');
    }

    public function redirectToGoogle(Request $request)
    {
        return $this->redirectToProvider('google');
    }

    public function handleGoogleCallback(Request $request, OAuthUserService $users)
    {
        return $this->handleProviderCallback($request, $users, 'google');
    }

    public function redirectToAzure(Request $request)
    {
        return $this->redirectToProvider('azure');
    }

    public function handleAzureCallback(Request $request, OAuthUserService $users)
    {
        return $this->handleProviderCallback($request, $users, 'azure');
    }

    public function redirectToProvider(string $provider)
    {
        if (!$this->providerIsConfigured($provider)) {
            return redirect('login')->with('error', ucfirst($provider) . ' login is not configured yet.');
        }

        $driver = Socialite::driver($provider);

        if ($provider === 'google') {
            $driver->scopes(['openid', 'profile', 'email']);
        }

        return $driver->redirect();
    }

    public function handleProviderCallback(Request $request, OAuthUserService $users, string $provider)
    {
        if ($request->filled('error')) {
            return redirect('login')->with('error', ucfirst($provider) . ' login was cancelled.');
        }

        if (!$this->providerIsConfigured($provider)) {
            return redirect('login')->with('error', ucfirst($provider) . ' login is not configured yet.');
        }

        try {
            $socialiteUser = Socialite::driver($provider)->user();
            $user = $users->findOrCreateUser($provider, $socialiteUser);

            Auth::login($user);
            $request->session()->regenerate();

            $this->finishLogin($user, 'login', 'User logged in with ' . ucfirst($provider) . '.');

            return redirect()->intended(route('dashboard'))->with('success', 'Login successfully :)');
        } catch (\Throwable $e) {
            report($e);

            return redirect('login')->with('error', $e->getMessage() ?: ucfirst($provider) . ' login failed.');
        }
    }

    private function finishLogin($user, string $action, string $description): void
    {
        Session::put($this->getUserSessionData($user));
        $user->update(['last_login' => Carbon::now()]);

        app(ActivityLogger::class)->record(
            $action,
            $description,
            ['user_id' => $user->id, 'email' => $user->email],
            ['type' => 'User', 'id' => $user->id]
        );
    }

    private function providerIsConfigured(string $provider): bool
    {
        $config = config("services.{$provider}");

        return filled($config['client_id'] ?? null)
            && filled($config['client_secret'] ?? null)
            && filled($config['redirect'] ?? null);
    }

    private function getUserSessionData($user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'user_id' => $user->user_id,
            'join_date' => $user->join_date,
            'phone_number' => $user->phone_number,
            'status' => $user->status,
            'role_name' => $user->role_name,
            'avatar' => $user->avatar_url,
            'position' => $user->position,
            'department' => $user->department,
            'line_manager' => $user->line_manager,
            'second_line_manager' => $user->second_line_manager,
        ];
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            app(ActivityLogger::class)->record(
                'logout',
                'User logged out.',
                ['user_id' => $user->id, 'email' => $user->email],
                ['type' => 'User', 'id' => $user->id]
            );
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('login')->with('success', 'Logged out successfully!');
    }
}
