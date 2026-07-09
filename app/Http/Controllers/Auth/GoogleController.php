<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            $todayDate = Carbon::now()->toDayDateTimeString();

            $user = User::where('google_id', $googleUser->getId())
                ->orWhere('email', $googleUser->getEmail())
                ->first();

            if ($user) {
                $user->update([
                    'google_id' => $googleUser->getId(),
                    'auth_provider' => 'google',
                    'avatar' => $googleUser->getAvatar() ?: $user->avatar,
                    'status' => $user->status ?: 'Active',
                ]);
            } else {
                $user = User::create([
                    'name' => $googleUser->getName() ?: $googleUser->getNickname(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'auth_provider' => 'google',
                    'avatar' => $googleUser->getAvatar(),
                    'join_date' => $todayDate,
                    'role_name' => 'User',
                    'status' => 'Active',
                    'password' => Hash::make(Str::random(32)),
                ]);
            }

            if ($user->status !== 'Active') {
                return redirect()->route('login')->with('error', 'Your account is not active.');
            }

            Auth::login($user, true);
            Session::put($this->getUserSessionData($user));

            $user->update(['last_login' => Carbon::now()]);

            app(ActivityLogger::class)->record(
                'login',
                'User logged in with Google.',
                ['user_id' => $user->id, 'email' => $user->email]
            );

            return redirect()->intended(route('dashboard'))->with('success', 'Login successfully :)');
        } catch (\Throwable $e) {
            \Log::error($e);

            return redirect()->route('login')->with('error', 'Google login failed. Please try again.');
        }
    }

    private function getUserSessionData(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'user_id' => $user->user_id,
            'join_date' => $user->join_date,
            'phone_number' => $user->phone_number,
            'status' => $user->status,
            'role_name' => $user->role_name,
            'avatar' => $user->avatar,
            'position' => $user->position,
            'department' => $user->department,
            'line_manager' => $user->line_manager,
            'second_line_manager' => $user->second_line_manager,
        ];
    }
}
