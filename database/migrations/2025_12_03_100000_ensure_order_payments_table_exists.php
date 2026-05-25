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
        if (!Schema::hasTable('order_payments')) {
            Schema::create('order_payments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
                $table->string('payment_method');
                $table->decimal('amount', 15, 2);
                $table->decimal('fee', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2);
                $table->string('status')->default('pending');
                $table->string('reference_number')->nullable();
                $table->string('payment_proof')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('expired_at')->nullable();
                $table->json('metadata')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            if (!Schema::hasColumn('order_payments', 'status')) {
                Schema::table('order_payments', function (Blueprint $table) {
                    $table->string('status')->default('pending')->after('amount');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We don't want to drop the table if it existed before, 
        // but for this specific migration which ensures existence, 
        // we might leave it as is or drop the column if we added it.
        // For safety, let's just drop the column if we added it, but checking that is hard in down().
        // So we'll leave down() empty or just drop column if exists.
        
        if (Schema::hasTable('order_payments') && Schema::hasColumn('order_payments', 'status')) {
             // Schema::table('order_payments', function (Blueprint $table) {
             //    $table->dropColumn('status');
             // });
        }
    }
};
