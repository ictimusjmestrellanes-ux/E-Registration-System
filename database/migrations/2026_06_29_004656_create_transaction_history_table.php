<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_history', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id', 30)->index();
            $table->date('transaction_date');
            $table->string('source', 100)->nullable();
            $table->string('type', 100);
            $table->string('clerk', 100)->nullable();
            $table->string('status', 50)->nullable();
            $table->text('description')->nullable();
            $table->text('actions_taken')->nullable();
            $table->text('remarks')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_history');
    }
};
