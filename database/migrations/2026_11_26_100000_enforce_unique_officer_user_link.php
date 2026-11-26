<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $duplicates = DB::table('user_officer_links')
            ->select('officer_id')->groupBy('officer_id')->havingRaw('COUNT(*) > 1')->get();

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('Cannot enforce one account per officer: duplicate officer links exist. Review and unlink the extra accounts before migrating.');
        }

        if (! Schema::hasIndex('user_officer_links', 'user_officer_links_officer_lookup')) {
            Schema::table('user_officer_links', function (Blueprint $table) {
                $table->index('officer_id', 'user_officer_links_officer_lookup');
            });
        }

        Schema::table('user_officer_links', function (Blueprint $table) {
            $table->unique('officer_id', 'user_officer_links_officer_unique');
        });
    }

    public function down(): void
    {
        Schema::table('user_officer_links', function (Blueprint $table) {
            $table->dropUnique('user_officer_links_officer_unique');
            // Keep the supporting index for the officer foreign key.
        });
    }
};
