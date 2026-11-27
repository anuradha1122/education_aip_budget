<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('estimate_reference_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_year_id')->constrained('financial_years')->restrictOnDelete();
            $table->foreignId('office_id')->constrained('offices')->restrictOnDelete();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['financial_year_id', 'office_id'], 'estimate_reference_scope_unique');
        });
        Schema::table('estimates', function (Blueprint $table) {
            $table->foreignId('aip_activity_office_id')->nullable()->constrained('aip_activity_offices')->restrictOnDelete();
            $table->string('reference', 100)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('aip_activity_office_id');
            $table->dropUnique(['reference']);
            $table->dropColumn('reference');
        });
        Schema::dropIfExists('estimate_reference_sequences');
    }
};
