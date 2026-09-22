<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'age',
        'birth_date',
        'birthplace',
        'education',
        'course',
        'sector',
        'position_organization',
        'gender',
        'civil_status',
        'email',
        'contact',
        'contact_2',
        'address',
        'province',
        'city',
        'barangay',
        'photo_path',
        'fingerprint_path',
        'fingerprint_template',
    ];

    public static function generateClientId(): string
    {
        $year = now()->format('y');
        $prefix = $year;

        // Consider live clients AND archived clients so an ID is never reused
        // after a client is archived and later restored.
        $latest = max(
            (string) self::query()
                ->where('client_id', 'like', "{$prefix}%")
                ->orderBy('client_id', 'desc')
                ->value('client_id'),
            (string) \App\Models\ArchivedClient::query()
                ->where('client_id', 'like', "{$prefix}%")
                ->orderBy('client_id', 'desc')
                ->value('client_id')
        );

        if ($latest === '') {
            $num = 1;
        } else {
            $num = (int) substr($latest, -5) + 1;
        }

        return $prefix . str_pad((string) $num, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Create a client with an auto-generated client_id, retrying with a fresh
     * ID if a concurrent insert claims the same one first. An explicit
     * client_id (archive restore) is honored on the first attempt.
     */
    public static function createWithGeneratedId(array $attributes): self
    {
        $preferredId = $attributes['client_id'] ?? null;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $attributes['client_id'] = ($attempt === 0 && filled($preferredId))
                    ? $preferredId
                    : self::generateClientId();

                return static::create($attributes);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                continue; // ID taken — fall through to a freshly generated one
            }
        }

        throw new \RuntimeException('Unable to generate a unique client ID. Please try again.');
    }

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function getPhotoUrlAttribute(): string
    {
        if (!empty($this->photo_path) && Storage::disk('public')->exists($this->photo_path)) {
            return asset('storage/' . $this->photo_path);
        }

        return asset('assets/images/profile.png');
    }

    public function getFingerprintUrlAttribute(): string
    {
        if (!empty($this->fingerprint_path) && Storage::disk('public')->exists($this->fingerprint_path)) {
            return asset('storage/' . $this->fingerprint_path);
        }

        return asset('assets/images/fingerprint.png');
    }

    public function getFullNameAttribute(): string
    {
        return $this->formatDisplayName();
    }

    public function getListDisplayNameAttribute(): string
    {
        return $this->full_name;
    }

    private function formatDisplayName(): string
    {
        $lastName = trim((string) $this->last_name);
        $firstName = trim((string) $this->first_name);
        $middleName = trim((string) $this->middle_name);

        // Older imports of "LASTNAME FIRSTNAME M.I." may have stored the
        // trailing initial as last_name. Present those existing records in
        // the intended order on both the client list and client details.
        if ($middleName !== '' && preg_match('/^[\p{L}]\.?$/u', $lastName)) {
            [$lastName, $firstName, $middleName] = [$firstName, $middleName, $lastName];
        }

        $middleDisplay = mb_strlen($middleName) === 1 ? $middleName . '.' : $middleName;

        $name = implode(', ', array_filter([$lastName, $firstName], fn ($part) => $part !== ''));

        return mb_strtoupper(trim(implode(' ', array_filter([
            $name,
            $middleDisplay,
            trim((string) $this->suffix),
        ], fn ($part) => $part !== ''))));
    }

    /**
     * SQL expressions that follow the same legacy-name correction as
     * list_display_name. They keep paginated client-based lists ordered by
     * the surname users actually see.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    public static function listDisplaySortExpressions(string $table = 'clients'): array
    {
        $lengthFunction = DB::connection()->getDriverName() === 'mysql' ? 'CHAR_LENGTH' : 'LENGTH';
        $legacyInitial = "TRIM(COALESCE({$table}.middle_name, '')) <> ''"
            ." AND {$lengthFunction}(REPLACE(TRIM(COALESCE({$table}.last_name, '')), '.', '')) = 1";

        return [
            "LOWER(CASE WHEN ({$legacyInitial}) THEN TRIM(COALESCE({$table}.first_name, '')) ELSE TRIM(COALESCE({$table}.last_name, '')) END)",
            "LOWER(CASE WHEN ({$legacyInitial}) THEN TRIM(COALESCE({$table}.middle_name, '')) ELSE TRIM(COALESCE({$table}.first_name, '')) END)",
            "LOWER(CASE WHEN ({$legacyInitial}) THEN TRIM(COALESCE({$table}.last_name, '')) ELSE TRIM(COALESCE({$table}.middle_name, '')) END)",
        ];
    }

    /**
     * Latest transferred Event Record linked through this client's
     * transaction history. Imported event names are authoritative when the
     * client's originally split name fields do not match the source record.
     */
    public function latestLinkedEvent(): HasOneThrough
    {
        return $this->hasOneThrough(
            TransactionEvent::class,
            TransactionHistory::class,
            'client_id',
            'transferred_transaction_id',
            'client_id',
            'id'
        )->latestOfMany();
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'client_id', 'client_id');
    }

    protected static function booted()
    {
        static::saved(function () {
            Cache::forget('duplicate_clients_v2');
        });

        static::deleted(function () {
            Cache::forget('duplicate_clients_v2');
        });

        // Keep the dashboard's live-client total and registration trend in
        // sync for manual registration, imports, archive, and restore flows.
        static::created(static function (self $client): void {
            TransactionHistory::flushDashboardCache();
        });

        static::updated(static function (self $client): void {
            TransactionHistory::flushDashboardCache();
        });

        static::deleted(static function (self $client): void {
            TransactionHistory::flushDashboardCache();
        });
    }
}
