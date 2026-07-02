<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('transactions');
    }

    public function down(): void
    {
        Schema::create('transactions', function ($table) {
            $table->id();
            $table->string('client_id', 20)->index();
            $table->date('transaction_date');
            $table->string('category', 100);
            $table->string('type', 100);
            $table->text('description')->nullable();
            $table->string('addressed_to')->nullable();
            $table->string('transaction_id', 30)->nullable();
            $table->timestamps();
        });
    }
};
