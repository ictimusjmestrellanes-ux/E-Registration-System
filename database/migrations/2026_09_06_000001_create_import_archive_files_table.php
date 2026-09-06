<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('import_archive_files', function (Blueprint $table) {
            $table->id();
            $table->string('filename')->unique();
            $table->string('original_filename')->nullable();
            $table->unsignedInteger('rows_count')->default(0);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('source', 20)->default('import');
            $table->unsignedBigInteger('imported_by_id')->nullable();
            $table->string('imported_by')->nullable();
            $table->string('role', 50)->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index('imported_at');
        });

        $this->backfillFromDisk();
    }

    /**
     * Register archive files that were written before this table existed.
     * Never allowed to break the migration (storage may be absent).
     */
    private function backfillFromDisk(): void
    {
        try {
            $directory = 'transaction-events-archive';

            if (! Storage::disk('local')->exists($directory)) {
                return;
            }

            foreach (Storage::disk('local')->files($directory) as $path) {
                if (preg_match('/\.(csv|xlsx|xls)$/i', strtolower($path)) !== 1) {
                    continue;
                }

                $filename = basename($path);

                if (DB::table('import_archive_files')->where('filename', $filename)->exists()) {
                    continue;
                }

                $importedById = null;
                $importedBy = null;
                $role = null;
                $metaPath = $path . '.importer.json';
                if (Storage::disk('local')->exists($metaPath)) {
                    $meta = json_decode(Storage::disk('local')->get($metaPath), true);
                    if (is_array($meta)) {
                        $importedById = $meta['imported_by_id'] ?? null;
                        $importedBy = $meta['imported_by'] ?? null;
                        $role = $meta['role'] ?? null;
                    }
                }

                $originalFilename = null;
                if (preg_match('/^transaction-events_\d{8}_\d{6}_(?:[a-z0-9]+\_)?(.+)$/i', $filename, $matches)) {
                    $originalFilename = $matches[1];
                }

                $rowsCount = 0;
                try {
                    $contents = Storage::disk('local')->get($path);
                    $rowsCount = max(0, count(array_filter(explode("\n", trim((string) $contents)))) - 1);
                } catch (\Throwable) {
                    $rowsCount = 0;
                }

                DB::table('import_archive_files')->insert([
                    'filename' => $filename,
                    'original_filename' => $originalFilename,
                    'rows_count' => $rowsCount,
                    'file_size' => Storage::disk('local')->size($path),
                    'source' => 'import',
                    'imported_by_id' => $importedById,
                    'imported_by' => $importedBy,
                    'role' => $role,
                    'imported_at' => gmdate(
                        'Y-m-d H:i:s',
                        $this->extractTimestamp($filename)
                            ?? Storage::disk('local')->lastModified($path)
                    ),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable) {
            // Backfill is best-effort only; the table itself is already created.
        }
    }

    private function extractTimestamp(string $filename): ?int
    {
        if (preg_match('/transaction-events_(\d{8})_(\d{6})/i', $filename, $matches)) {
            $parsed = \DateTime::createFromFormat('YmdHis', $matches[1] . $matches[2], new \DateTimeZone('UTC'));
            if ($parsed !== false) {
                return $parsed->getTimestamp();
            }
        }

        return null;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_archive_files');
    }
};
