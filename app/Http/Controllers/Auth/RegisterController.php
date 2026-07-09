<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    /** Show the registration page */
    public function register()
    {
        abort_unless(config('authentication.local_auth_enabled'), 404);

        return view('auth.register', [
            'roles' => User::ROLES,
        ]);
    }

    /** Store New User */
    public function storeUser(Request $request)
    {
        abort_unless(config('authentication.local_auth_enabled'), 404);

        try {
            $users = new User();
            return $users->saveNewuser($request);
        } catch (\Exception $e) {
            \Log::error($e);
            return redirect()->back();
        }
    }
}
