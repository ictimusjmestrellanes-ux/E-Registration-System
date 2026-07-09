<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\HandlesClientStorage;
use Illuminate\Http\Request;

class CameraController extends Controller
{
    use HandlesClientStorage;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function upload(Request $request)
    {
        $validated = $request->validate([
            'photo_data' => ['required', 'string'],
        ]);

        $path = $this->storeClientPhoto($validated['photo_data']);

        if (empty($path)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid image data.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'path' => $path,
            'url' => asset('storage/' . $path),
        ]);
    }
}
