<?php

namespace App\Models;

use App\Support\EventDuplicateName;
use App\Support\ImportName;
use App\Support\PayrollCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionEvent extends Model
{
    use HasFactory;

    protected $table = 'transaction_events';

    protected $fillable = [
        'transaction_id',
        'full_name',
        'contact_no',
        'address',
        'age',
        'birth_date',
        'client_category',
        'payroll_category',
        'sector',
        'status',
        'imported_by',
        'transaction_category',
        'transaction_type',
        'event_date',
        'remarks',
        'transferred_at',
        'transferred_transaction_id',
        'not_duplicate',
        'duplicate_merged_at',
        'duplicate_person_key',
        'duplicate_middle_is_initial',
        'duplicate_name_parsed',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'event_date' => 'date',
        'transferred_at' => 'datetime',
        'transferred_transaction_id' => 'integer',
        'age' => 'integer',
        'not_duplicate' => 'boolean',
        'duplicate_merged_at' => 'datetime',
        'duplicate_middle_is_initial' => 'boolean',
        'duplicate_name_parsed' => 'boolean',
    ];

    protected $attributes = [
        'status' => 'Pending',
    ];

    public const STATUSES = ['Pending', 'Claimed', 'Unclaimed'];

    public function getDisplayNameAttribute(): string
    {
        return ImportName::format((string) $this->full_name);
    }

    public function transferredTransaction(): BelongsTo
    {
        return $this->belongsTo(TransactionHistory::class, 'transferred_transaction_id');
    }

    protected static function booted(): void
    {
        static::saving(static function (self $event): void {
            if ($event->isDirty('transferred_transaction_id')) {
                $event->transaction_id = $event->transferred_transaction_id
                    ? TransactionHistory::whereKey($event->transferred_transaction_id)->value('transaction_id')
                    : null;
            }

            if ($event->isDirty('full_name') || $event->display_name_sort === null) {
                $event->display_name_sort = mb_strtolower(ImportName::format((string) $event->full_name));
                $event->forceFill(EventDuplicateName::attributes((string) $event->full_name));
            }

            if ($event->isDirty('client_category') || $event->payroll_category === null) {
                $event->payroll_category = PayrollCategory::fromClientCategory($event->client_category);
            }
        });

        // Event imports, transfers, and undo operations can affect the
        // dashboard's caravan trend. Share the same request-level cache
        // invalidation as transaction history writes.
        static::created(static function (self $event): void {
            TransactionHistory::flushDashboardCache();
        });

        static::updated(static function (self $event): void {
            TransactionHistory::flushDashboardCache();
        });

        static::deleted(static function (self $event): void {
            TransactionHistory::flushDashboardCache();
        });
    }
}
