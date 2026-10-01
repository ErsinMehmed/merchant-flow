<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', static function (Blueprint $table): void {
            $table->id();
            $table->string('business_name');
            $table->string('dba_name');
            $table->string('legal_name');
            $table->string('email');
            $table->string('mid', 32)->unique();
            $table->string('status', 16)->default('active');
            $table->boolean('active_yn')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('account_user', static function (Blueprint $table): void {
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['account_id', 'user_id']);
            // Scope lookups start from the user.
            $table->index(['user_id', 'account_id']);
        });

        Schema::create('agent_account', static function (Blueprint $table): void {
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->primary(['agent_id', 'account_id']);
            $table->index(['account_id', 'agent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_account');
        Schema::dropIfExists('account_user');
        Schema::dropIfExists('accounts');
    }
};
