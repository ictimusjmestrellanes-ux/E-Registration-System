<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    public function record(
        string $action,
        string $description,
        array $properties = [],
        Model|array|null $subject = null
    ): ActivityLog {
        $subjectType = null;
        $subjectId = null;

        if ($subject instanceof Model) {
            $subjectType = class_basename($subject);
            $subjectId = $subject->getKey();
        } elseif (is_array($subject)) {
            $subjectType = $subject['type'] ?? null;
            $subjectId = $subject['id'] ?? null;
        }

        return ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'description' => $description,
            'properties' => empty($properties) ? null : $properties,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
