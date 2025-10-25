<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     */
    public function up(): void
    {
        Schema::dropIfExists('accounting_transactions');

        Schema::create('accounting_transactions', function (Blueprint $table) {
            $table->id();

            // Group
            $table->foreignId('group_id')->constrained('groups')->onDelete('cascade');

            $table->enum('transaction_type', ['income', 'expense'])->index();
            $table->string('category', 50)->index();

            $table->foreignId('invoice_id')->nullable()->constrained('invoice_homepasses')->onDelete('set null');
            $table->string('invoice_number')->nullable()->index();

            $table->string('member_name')->nullable(); 

            $table->decimal('amount', 15, 2);

            $table->enum('payment_method', ['cash', 'bank_transfer', 'payment_gateway'])->nullable();

            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('bank_name')->nullable();

            $table->foreignId('received_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('paid_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamp('transaction_date')->index();

            $table->string('description')->nullable();
            $table->text('notes')->nullable();
            $table->string('receipt_number', 100)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['group_id', 'transaction_date'], 'idx_group_date');
            $table->index(['transaction_type', 'category'], 'idx_type_category');
            $table->index(['group_id', 'transaction_type', 'transaction_date'], 'idx_group_type_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_transactions');
    }
};
