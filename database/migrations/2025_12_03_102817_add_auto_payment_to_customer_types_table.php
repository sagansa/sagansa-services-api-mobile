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
        Schema::table('customer_types', function (Blueprint $table) {
            $table->boolean('auto_payment')->default(false)->after('order');
            $table->foreignUuid('linked_payment_method_id')->nullable()->after('auto_payment')->constrained('payment_methods')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_types', function (Blueprint $table) {
            $table->dropForeign(['linked_payment_method_id']);
            $table->dropColumn(['auto_payment', 'linked_payment_method_id']);
        });
    }
};
