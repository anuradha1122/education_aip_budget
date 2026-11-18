<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('aip_activity_funding_entries')
            ->select('aip_activity_id')
            ->groupBy('aip_activity_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicates) {
            throw new RuntimeException(
                'An AIP activity has more than one funding entry. Review the records before applying this migration.'
            );
        }

        Schema::table('aip_activity_funding_entries', function (Blueprint $table) {
            $table->unique('aip_activity_id', 'aip_funding_entry_activity_unique');
        });
    }

    public function down(): void
    {
        Schema::table('aip_activity_funding_entries', function (Blueprint $table) {
            $table->dropUnique('aip_funding_entry_activity_unique');
        });
    }
};
