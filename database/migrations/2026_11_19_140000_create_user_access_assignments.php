<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_access_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('role', 50);
            $table->foreignId('office_id')->nullable()->constrained('offices')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('user_access_assignment_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 30);
            $table->json('old_values')->nullable();
            $table->json('new_values');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_access_assignment_audit_events');
        Schema::dropIfExists('user_access_assignments');
    }
};
