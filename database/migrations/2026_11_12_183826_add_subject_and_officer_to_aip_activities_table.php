<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aip_activities', function (Blueprint $table) {
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->restrictOnDelete();
            $table->foreignId('officer_id')->nullable()->constrained('officers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('aip_activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subject_id');
            $table->dropConstrainedForeignId('officer_id');
        });
    }
};
