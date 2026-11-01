<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add has_variants flag to menu_items
        if (!Schema::hasColumn('menu_items', 'has_variants')) {
            Schema::table('menu_items', function (Blueprint $table) {
                $table->boolean('has_variants')->default(false)->after('price');
            });
        }

        // 2. Create menu_item_variants table
        if (!Schema::hasTable('menu_item_variants')) {
            Schema::create('menu_item_variants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
                $table->string('name'); // e.g. 'Small', 'Medium', 'Large', 'Half', 'Full', '1 Litre'
                $table->decimal('price', 10, 2);
                $table->boolean('is_available')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // 3. Add variant tracking columns to restaurant_order_items
        Schema::table('restaurant_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('restaurant_order_items', 'menu_item_variant_id')) {
                $table->foreignId('menu_item_variant_id')->nullable()->after('menu_item_id')->constrained('menu_item_variants')->nullOnDelete();
            }
            if (!Schema::hasColumn('restaurant_order_items', 'variant_name')) {
                $table->string('variant_name')->nullable()->after('name_snapshot');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurant_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('restaurant_order_items', 'menu_item_variant_id')) {
                $table->dropForeign(['menu_item_variant_id']);
                $table->dropColumn('menu_item_variant_id');
            }
            if (Schema::hasColumn('restaurant_order_items', 'variant_name')) {
                $table->dropColumn('variant_name');
            }
        });

        Schema::dropIfExists('menu_item_variants');

        if (Schema::hasColumn('menu_items', 'has_variants')) {
            Schema::table('menu_items', function (Blueprint $table) {
                $table->dropColumn('has_variants');
            });
        }
    }
};
