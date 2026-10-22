<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('duration_value')->default(30)->after('name');
            $table->string('duration_unit', 20)->default('days')->after('duration_value'); // days, months, years
            $table->decimal('price', 10, 2)->default(0.00)->after('duration_unit');
        });

        // Backfill existing plans
        DB::table('plans')->where('price_monthly', '>', 0)->update([
            'price' => DB::raw('price_monthly'),
            'duration_value' => 30,
            'duration_unit' => 'days',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['duration_value', 'duration_unit', 'price']);
        });
    }
};
