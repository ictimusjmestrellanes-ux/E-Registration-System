<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Hash;

class LockScreenController extends Controller
{
    /** Display the lock screen page */
    public function lockscreen()
    {
        if (!session('locked')) {
            return redirect()->route(auth()->check() ? 'dashboard' : 'login');
        }
        return view('auth.lock-screen');
    }

    /** Unlock the screen */
    public function unlock(Request $request)
    {
        $request->validate([
            'password' => 'required'
        ]);

        if (!auth()->check()) {
            return redirect()->route('login');
        }

        if (Hash::check($request->password, auth()->user()->password)) {
            session()->forget('locked');
            return redirect()->route('dashboard')->with('success', 'Unlocked successfully!');
        }

        return back()->with(['error' => 'Incorrect password']);
    }
}
