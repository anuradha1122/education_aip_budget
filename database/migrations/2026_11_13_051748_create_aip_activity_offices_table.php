<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aip_activity_offices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('aip_activity_id')
                ->constrained('aip_activities')
                ->restrictOnDelete();

            $table->foreignId('office_id')
                ->constrained('offices')
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique(
                ['aip_activity_id', 'office_id'],
                'aip_activity_office_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aip_activity_offices');
    }
};