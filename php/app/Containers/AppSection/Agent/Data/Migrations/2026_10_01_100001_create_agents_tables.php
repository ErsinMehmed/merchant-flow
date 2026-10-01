<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', static function (Blueprint $table): void {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('company_legal_name');
            $table->string('company_dba');
            $table->string('type', 32);
            // Residual share in percent, e.g. 12.50.
            $table->decimal('default_split', 5, 2)->default(0);
            $table->foreignId('parent_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('agent_user', static function (Blueprint $table): void {
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['agent_id', 'user_id']);
            // Scope lookups start from the user.
            $table->index(['user_id', 'agent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_user');
        Schema::dropIfExists('agents');
    }
};
