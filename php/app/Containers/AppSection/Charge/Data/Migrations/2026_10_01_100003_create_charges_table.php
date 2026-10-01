<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charges', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            // All amounts are integer minor units (cents).
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('amount_captured')->default(0);
            $table->unsignedBigInteger('amount_refunded')->default(0);
            $table->unsignedBigInteger('amount_voided')->default(0);
            $table->char('currency', 3);
            $table->string('status', 16);
            $table->string('external_transaction_id', 64)->unique();
            $table->string('customer_email');
            $table->boolean('refunded')->default(false);
            $table->boolean('disputed')->default(false);
            $table->string('void_reason', 32)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // Listing is always newest-first within the caller's accounts.
            $table->index(['account_id', 'created_at', 'id']);
            $table->index(['created_at', 'id']);
            $table->index(['account_id', 'status']);
            $table->index('customer_email');
        });

        // The same invariants are enforced by PHP and Go; the database is the
        // last line of defence if either side gets an update wrong.
        DB::statement(<<<'SQL'
            ALTER TABLE charges
                ADD CONSTRAINT charges_status_check
                    CHECK (status IN ('authorized', 'captured', 'refunded', 'voided', 'failed')),
                ADD CONSTRAINT charges_amount_positive_check CHECK (amount > 0),
                ADD CONSTRAINT charges_captured_check CHECK (amount_captured <= amount),
                ADD CONSTRAINT charges_refunded_check CHECK (amount_refunded <= amount_captured),
                ADD CONSTRAINT charges_voided_check CHECK (amount_voided <= amount),
                ADD CONSTRAINT charges_refunded_flag_check
                    CHECK (refunded = (status = 'refunded')),
                ADD CONSTRAINT charges_void_reason_check
                    CHECK (void_reason IS NULL
                        OR void_reason IN ('requested_by_customer', 'duplicate', 'fraudulent', 'abandoned'))
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('charges');
    }
};
