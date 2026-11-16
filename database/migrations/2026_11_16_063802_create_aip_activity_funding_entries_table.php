<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aip_activity_funding_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('aip_activity_id')
                ->constrained('aip_activities')
                ->restrictOnDelete();

            $table->foreignId('vote_id')
                ->constrained('votes')
                ->restrictOnDelete();

            $table->foreignId('fund_id')
                ->constrained('funds')
                ->restrictOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aip_activity_funding_entries');
    }
};