<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('aip_activities')
            ->select('financial_year_id', 'activity_number')
            ->groupBy('financial_year_id', 'activity_number')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicates) {
            throw new RuntimeException('Duplicate AIP activity numbers exist within a financial year. Review the records before running this migration.');
        }

        Schema::table('aip_activities', function (Blueprint $table) {
            $table->unique(['financial_year_id', 'activity_number'], 'aip_year_activity_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('aip_activities', function (Blueprint $table) {
            $table->dropUnique('aip_year_activity_number_unique');
        });
    }
};
