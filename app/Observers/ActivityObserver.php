<?php

namespace App\Observers;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ActivityObserver
{
    public function created(Model $model): void
    {
        $this->record($model, 'created', [], $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = array_diff_key($model->getChanges(), array_flip(['updated_at', 'created_at', 'last_login', 'remember_token']));
        if ($changes !== []) {
            $this->record($model, 'updated', array_intersect_key($model->getRawOriginal(), $changes), $changes);
        }
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted', $model->getAttributes(), []);
    }

    private function record(Model $model, string $operation, array $before, array $after): void
    {
        // Seeders, migrations and unauthenticated lookups are not user changes.
        if (!auth()->check() || !request()->route()) {
            return;
        }

        $type = class_basename($model);
        $label = Str::headline($type).' #'.$model->getKey();
        $name = $model->getAttribute('full_name') ?: $model->getAttribute('transaction_id') ?: $model->getAttribute('name');
        if ($name) {
            $label .= ' ('.$name.')';
        }
        $properties = [
            'route' => request()->route()?->getName(),
            'before' => $this->safeAttributes($before),
            'after' => $this->safeAttributes($after),
        ];
        if ($operation === 'updated') {
            $properties['changed_fields'] = array_keys($after);
        }
        app(ActivityLogger::class)->record(
            Str::snake($type).'_'.$operation,
            ucfirst($operation).' '.$label.'.',
            $properties,
            $model
        );
    }

    private function safeAttributes(array $attributes): array
    {
        unset($attributes['created_at'], $attributes['updated_at'], $attributes['remember_token']);
        foreach ($attributes as $field => $value) {
            if (preg_match('/password|token|secret|fingerprint/i', $field)) {
                $attributes[$field] = $value === null || $value === '' ? null : '[redacted]';
            }
        }

        return $attributes;
    }
}
