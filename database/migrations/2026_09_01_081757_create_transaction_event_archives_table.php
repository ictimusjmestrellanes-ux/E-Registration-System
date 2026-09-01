<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transaction_event_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_event_id')->constrained()->onDelete('cascade');
            $table->string('full_name', 150);
            $table->string('contact_no', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('transaction_type')->nullable();
            $table->string('category')->nullable();
            $table->date('event_date')->nullable();
            $table->unsignedBigInteger('transferred_transaction_id')->nullable();
            $table->timestamp('transferred_at')->nullable();
            $table->boolean('not_duplicate')->default(false);
            $table->text('archive_reason')->nullable();
            $table->unsignedBigInteger('archived_by')->nullable();
            $table->timestamps();

            $table->index('transaction_event_id');
            $table->index('archived_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_event_archives');
    }
};
