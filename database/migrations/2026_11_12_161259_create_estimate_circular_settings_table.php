<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimate_circular_settings', function (Blueprint $table) {
            $table->id();
            $table->string('circular_number')->nullable();
            $table->text('rule_paragraph')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimate_circular_settings');
    }
};