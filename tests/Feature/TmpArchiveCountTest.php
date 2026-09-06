<?php

namespace Tests\Feature;

use App\Models\ImportArchiveFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TmpArchiveCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_diag_count(): void
    {
        echo "\nimport_archive_files count fresh=" . ImportArchiveFile::count() . "\n";
        echo 'storage dir exists=' . var_export(
            \Illuminate\Support\Facades\Storage::disk('local')->exists('transaction-events-archive'),
            true
        ) . "\n";
        $this->assertTrue(true);
    }
}
