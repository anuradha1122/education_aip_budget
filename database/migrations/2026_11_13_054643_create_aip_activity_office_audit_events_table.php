<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aip_activity_office_audit_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('aip_activity_office_id')
                ->constrained('aip_activity_offices')
                ->restrictOnDelete();

            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('action', 50);
            $table->json('new_values')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aip_activity_office_audit_events');
    }
};