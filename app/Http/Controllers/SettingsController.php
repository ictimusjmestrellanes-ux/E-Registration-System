<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\HandlesClientStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    use HandlesClientStorage;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        return view('pages.client_profile.settings');
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'cover_photo_file' => ['nullable', 'image', 'max:4096'],
            'cover_photo_data' => ['nullable', 'string'],
            'cover_photo' => ['nullable', 'image', 'max:4096'],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->phone_number = $validated['phone_number'] ?? null;

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            $user->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        if (!empty($validated['cover_photo_data'])) {
            if ($user->cover_photo) {
                Storage::disk('public')->delete($user->cover_photo);
            }

            $user->cover_photo = $this->storeBase64Image($validated['cover_photo_data'], 'covers', 'cover_');
        } elseif ($request->hasFile('cover_photo_file')) {
            if ($user->cover_photo) {
                Storage::disk('public')->delete($user->cover_photo);
            }

            $user->cover_photo = $request->file('cover_photo_file')->store('covers', 'public');
        }

        $user->save();

        $this->recordActivity(
            'profile_updated',
            'Updated profile information.',
            ['user_id' => $user->id],
            ['type' => 'User', 'id' => $user->id]
        );

        return redirect()->route('settings')->with('success', 'Profile updated successfully.');
    }
}
