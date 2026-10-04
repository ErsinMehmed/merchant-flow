<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // A second-resolution URL can keep a replaced photo cached for a day.
        DB::statement('ALTER TABLE users MODIFY avatar_updated_at TIMESTAMP(6) NULL DEFAULT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users MODIFY avatar_updated_at TIMESTAMP NULL DEFAULT NULL');
    }
};
