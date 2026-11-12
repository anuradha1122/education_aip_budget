<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_category_audit_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('expense_category_id')
                ->constrained('expense_categories')
                ->restrictOnDelete();

            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('action', 50);
            $table->json('old_values')->nullable();
            $table->json('new_values');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_category_audit_events');
    }
};