<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aip_activities', function (Blueprint $table) {
            $table->string('level', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('aip_activities', function (Blueprint $table) {
            $table->dropColumn('level');
        });
    }
};