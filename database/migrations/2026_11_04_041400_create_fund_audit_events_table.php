<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_audit_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('fund_id')
                ->constrained('funds')
                ->restrictOnDelete();

            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('action', 20);
            $table->json('old_values')->nullable();
            $table->json('new_values');
            $table->timestamps();

            $table->index(['fund_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_audit_events');
    }
};
