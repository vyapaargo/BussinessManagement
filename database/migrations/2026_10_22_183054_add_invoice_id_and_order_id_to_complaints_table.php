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
        Schema::table('complaints', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('client_id')->constrained('invoices')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->after('invoice_id')->constrained('restaurant_orders')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropForeign(['order_id']);
            $table->dropColumn(['invoice_id', 'order_id']);
        });
    }
};
