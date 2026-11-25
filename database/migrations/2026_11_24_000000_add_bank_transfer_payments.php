<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('delivery_instructions')->nullable();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('method', 24)->default('paystack');
            $table->json('bank_details')->nullable();
            $table->timestamp('transfer_notified_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('bank_transaction_reference', 100)->nullable()->unique();
            $table->text('rejection_reason')->nullable();
            $table->index(['method', 'status', 'transfer_notified_at'], 'payments_method_status_notified');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropUnique(['bank_transaction_reference']);
            $table->dropIndex('payments_method_status_notified');
            $table->dropColumn(['method', 'bank_details', 'transfer_notified_at', 'reviewed_by', 'reviewed_at', 'bank_transaction_reference', 'rejection_reason']);
        });

        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('delivery_instructions'));
    }
};
