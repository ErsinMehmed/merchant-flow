<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Stored responses for the Go API's Idempotency-Key header. The row is written
 * in the same transaction as the charge change it describes, so a retried
 * request either replays the stored response or runs for the first time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('idempotency_key');
            // SHA-256 of method, path and body; a reused key with a different
            // request is rejected instead of replayed.
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('response_status');
            // Raw bytes, not JSON: a JSON column normalises key order and
            // spacing, and a replay must be byte-for-byte the original.
            $table->binary('response_body');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'idempotency_key']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
