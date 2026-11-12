<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('officer_subject_assignments', function (Blueprint $table) {
            $table->timestamp('ended_at')->nullable();
            $table->unsignedTinyInteger('active_marker')->nullable()->default(1);
            $table->index('officer_id', 'osa_officer_idx');
        });
        Schema::table('officer_subject_assignments', function (Blueprint $table) {
            $table->dropUnique(['officer_id', 'subject_id']);
            $table->unique(['officer_id', 'subject_id', 'active_marker'], 'osa_active_pair_unique');
        });
    }

    public function down(): void
    {
        $hasDuplicates = DB::table('officer_subject_assignments')
            ->select('officer_id', 'subject_id')->groupBy('officer_id', 'subject_id')
            ->havingRaw('COUNT(*) > 1')->exists();
        if ($hasDuplicates) {
            throw new RuntimeException('Cannot roll back assignment history while a pair has multiple records.');
        }
        Schema::table('officer_subject_assignments', function (Blueprint $table) {
            $table->dropUnique('osa_active_pair_unique');
            $table->unique(['officer_id', 'subject_id']);
            $table->dropColumn(['ended_at', 'active_marker']);
        });
        Schema::table('officer_subject_assignments', function (Blueprint $table) {
            $table->dropIndex('osa_officer_idx');
        });
    }
};
