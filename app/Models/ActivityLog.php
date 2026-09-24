<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'properties',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    /**
     * Activity actions surfaced in the navbar notification dropdown:
     * import / transfer / force-create / tag / delete / undo updates.
     */
    public const NOTIFICATION_ACTIONS = [
        'events_imported',
        'event_transferred',
        'events_transfer_selected',
        'event_force_created',
        'events_force_created_all',
        'event_status_tagged',
        'events_status_tagged',
        'events_marked_not_duplicate',
        'events_not_duplicate_review_undone',
        'event_deleted',
        'events_bulk_deleted',
        'event_transfer_undone',
        'events_transfer_undone',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
