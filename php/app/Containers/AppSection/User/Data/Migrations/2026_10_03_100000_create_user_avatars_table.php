<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Profile photos, written by Nova and by the Go API. They live in the
 * database because the PHP and Go containers share no file system.
 *
 * users.avatar_updated_at says whether a photo exists without reading it and
 * versions the photo URLs; the image itself is kept in its own table so that
 * loading a user never loads the image.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->timestamp('avatar_updated_at')->nullable()->after('is_merchant');
        });

        Schema::create('user_avatars', static function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('content_type', 32);
            $table->binary('content');
        });

        // Blueprint::binary() is a BLOB, which stops at 64 KB.
        DB::statement('ALTER TABLE user_avatars MODIFY content MEDIUMBLOB NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_avatars');

        Schema::table('users', static function (Blueprint $table): void {
            $table->dropColumn('avatar_updated_at');
        });
    }
};
