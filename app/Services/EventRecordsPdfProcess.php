<?php

namespace App\Services;

use App\Models\TransactionEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class EventRecordsPdfProcess
{
    private function directory(string $token): string
    {
        abort_unless(Str::isUuid($token), 404);

        return storage_path('app/private/event-pdf-exports/'.$token);
    }

    public function start(Collection $events, bool $isRice, string $dateLabel, int $owner, array $details = []): array
    {
        $root = storage_path('app/private/event-pdf-exports');
        File::ensureDirectoryExists($root);
        // These are private, short-lived export snapshots, never source records.
        foreach (File::directories($root) as $directory) {
            if (Str::isUuid(basename($directory)) && File::lastModified($directory) < now()->subHours(2)->timestamp) {
                File::deleteDirectory($directory);
            }
        }
        $token = (string) Str::uuid();
        $directory = $this->directory($token);
        File::ensureDirectoryExists($directory);
        $state = [
            'owner' => $owner, 'expires' => now()->addHours(2)->timestamp,
            'total' => $events->count(), 'completed' => 0, 'batches' => 0, 'ready' => false,
            'isRice' => $isRice, 'dateLabel' => $dateLabel, 'details' => $details,
        ];
        File::put($directory.'/records.json', json_encode($events->map(fn ($event) => $event->getAttributes())->values(), JSON_THROW_ON_ERROR));
        File::put($directory.'/state.json', json_encode($state, JSON_THROW_ON_ERROR));

        return ['token' => $token] + $this->progress($state);
    }

    private function state(string $directory, int $owner): array
    {
        abort_unless(File::exists($directory.'/state.json'), 404);
        $state = json_decode(File::get($directory.'/state.json'), true, 512, JSON_THROW_ON_ERROR);
        abort_unless($state['owner'] === $owner, 404);
        abort_if($state['expires'] < time(), 410, 'This export expired. Start a new PDF export.');

        return $state;
    }

    private function progress(array $state): array
    {
        return array_intersect_key($state, array_flip(['total', 'completed', 'ready']));
    }

    public function step(string $token, int $owner): array
    {
        $directory = $this->directory($token);
        $this->state($directory, $owner);
        $lock = fopen($directory.'/process.lock', 'c');
        abort_if($lock === false, 500);
        try {
            // A retry cannot render the same batch concurrently.
            abort_unless(flock($lock, LOCK_EX | LOCK_NB), 409, 'Export is still processing.');
            $state = $this->state($directory, $owner);
            if ($state['ready']) {
                return $this->progress($state);
            }
            $renderer = app(EventRecordsPdfExporter::class);
            if ($state['completed'] < $state['total'] || $state['batches'] === 0) {
                $records = json_decode(File::get($directory.'/records.json'), true, 512, JSON_THROW_ON_ERROR);
                $events = collect(array_slice($records, $state['completed'], EventRecordsPdfExporter::BATCH_SIZE))
                    ->map(fn ($attributes) => (new TransactionEvent())->setRawAttributes($attributes));
                File::put($directory.'/batch-'.$state['batches'].'.pdf', $renderer->renderBatch($events, $state['isRice'], $state['dateLabel'], $state['completed'], $state['details'] ?? []));
                $state['completed'] += $events->count();
                $state['batches']++;
            } else {
                $paths = array_map(fn ($index) => $directory.'/batch-'.$index.'.pdf', range(0, $state['batches'] - 1));
                File::put($directory.'/export.pdf', $renderer->mergeFiles($paths));
                $state['ready'] = true;
            }
            File::replace($directory.'/state.json', json_encode($state, JSON_THROW_ON_ERROR));
            if ($state['ready']) {
                File::delete($directory.'/records.json');
                foreach ($paths as $path) {
                    File::delete($path);
                }
            }

            return $this->progress($state);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function downloadPath(string $token, int $owner): string
    {
        $directory = $this->directory($token);
        $state = $this->state($directory, $owner);
        abort_unless($state['ready'], 409, 'The PDF is not ready yet.');

        return $directory.'/export.pdf';
    }
}
