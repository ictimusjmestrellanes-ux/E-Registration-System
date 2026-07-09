<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_DSWD = 'DSWD';
    public const ROLE_CONG_STAFF = 'Cong Staff';
    public const ROLE_ADMIN = 'Admin';
    public const ROLE_SUPER_ADMIN = 'Super Admin';

    public const ROLES = [
        self::ROLE_DSWD,
        self::ROLE_CONG_STAFF,
        self::ROLE_ADMIN,
        self::ROLE_SUPER_ADMIN,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $table = 'users'; // Specify the table name if it's not pluralized

    protected $fillable = [
        'name',
        'email',
        'last_login',
        'phone_number',
        'status',
        'role_name',
        'avatar',
        'provider_avatar',
        'google_id',
        'azure_id',
        'auth_provider',
    ];


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function getAvatarUrlAttribute()
    {
        if (!$this->avatar && $this->provider_avatar) {
            return $this->provider_avatar;
        }

        if (!$this->avatar) {
            return asset('assets/images/avatar-1.jpg');
        }
        if (str_starts_with($this->avatar, 'http://') || str_starts_with($this->avatar, 'https://')) {
            return $this->avatar;
        }
        return asset('storage/' . $this->avatar);
    }

    public function getCoverUrlAttribute()
    {
        if (!$this->cover_photo) {
            return null;
        }
        if (str_starts_with($this->cover_photo, 'http://') || str_starts_with($this->cover_photo, 'https://')) {
            return $this->cover_photo;
        }
        return asset('storage/' . $this->cover_photo);
    }

    /** generate id */
    protected static function boot()
    {
        parent::boot();

        self::creating(function ($model) {
            $latestUser = self::orderBy('user_id', 'desc')->first();
            $nextID = $latestUser ? intval(substr($latestUser->user_id, 3)) + 1 : 1;
            $model->user_id = 'KH-' . sprintf("%04d", $nextID);

            // Ensure the user_id is unique
            while (self::where('user_id', $model->user_id)->exists()) {
                $nextID++;
                $model->user_id = 'KH-' . sprintf("%04d", $nextID);
            }
        });
    }

    /** Insert New Users */
    public function saveNewuser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|max:255',
            'email'     => 'required|string|email|max:255|unique:users,email',
            'role_name' => 'required|string|in:' . implode(',', self::ROLES),
            'password'  => 'required|string|min:8|confirmed',
        ], [
            'email.unique' => 'This email is already registered. Please use another.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Please fix the errors below.');
        }

        try {
            $todayDate = Carbon::now()->toDayDateTimeString();
            $save             = new User;
            $save->name       = $request->name;
            $save->avatar     = $request->image;
            $save->email      = $request->email;
            $save->join_date  = $todayDate;
            $save->role_name  = $request->role_name;
            $save->status     = 'Active';
            $save->password   = Hash::make($request->password);
            $save->save();
            return redirect('login')->with('success', 'Account created successfully :)');
        } catch (\Exception $e) {
            \Log::error($e);
            return redirect()->back()->with('error', 'Failed to Create Account. Please try again.');
        }
    }
}
