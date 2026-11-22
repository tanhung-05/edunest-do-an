<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P0 — 09 §2: mở rộng users → roles → role_user → categories → levels → tags
 * → teacher_profiles → settings. Bảng passkeys của spike được dọn (01 Q-01).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Đường nâng từ spike đã chạy migration passkey (đường cài sạch không có bảng này).
        Schema::dropIfExists('passkeys');

        Schema::table('users', function (Blueprint $table) {
            $table->string('name', 120)->change();
            $table->string('status', 10)->default('HOAT_DONG')->after('password');
            $table->dateTime('locked_at', 3)->nullable()->after('status');
            $table->string('locked_reason', 500)->nullable()->after('locked_at');
            $table->unsignedBigInteger('auth_generation')->default(0)->after('locked_reason');
            $table->boolean('must_change_password')->default(false)->after('auth_generation');
            $table->foreignId('created_by_id')->nullable()->after('must_change_password')
                ->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->string('avatar_path')->nullable()->after('created_by_id');
            $table->index('status');
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 50);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('assigned_by_id')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->dateTime('assigned_at');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->unique(['user_id', 'role_id']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete()->restrictOnUpdate();
            $table->string('name', 100)->collation('utf8mb4_unicode_ci');
            $table->string('slug', 120);
            $table->unsignedSmallInteger('position')->default(0);
            $table->dateTime('deleted_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->unsignedBigInteger('parent_key')->storedAs('IFNULL(parent_id, 0)');
            $table->tinyInteger('alive_key')->nullable()->storedAs('IF(deleted_at IS NULL, 1, NULL)');
            $table->unique(['parent_key', 'name', 'alive_key'], 'categories_name_alive_unique');
            $table->unique(['slug', 'alive_key'], 'categories_slug_alive_unique');
        });

        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->collation('utf8mb4_unicode_ci');
            $table->unsignedSmallInteger('position')->default(0);
            $table->dateTime('deleted_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->tinyInteger('alive_key')->nullable()->storedAs('IF(deleted_at IS NULL, 1, NULL)');
            $table->unique(['name', 'alive_key'], 'levels_name_alive_unique');
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->collation('utf8mb4_unicode_ci');
            $table->string('slug', 80);
            $table->dateTime('deleted_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->tinyInteger('alive_key')->nullable()->storedAs('IF(deleted_at IS NULL, 1, NULL)');
            $table->unique(['name', 'alive_key'], 'tags_name_alive_unique');
            $table->unique(['slug', 'alive_key'], 'tags_slug_alive_unique');
        });

        Schema::create('teacher_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->string('photo_path')->nullable();
            $table->text('bio')->nullable();
            $table->text('achievements')->nullable();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->json('value');
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('teacher_profiles');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('levels');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['created_by_id']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'status', 'locked_at', 'locked_reason', 'auth_generation',
                'must_change_password', 'created_by_id', 'avatar_path',
            ]);
        });
    }
};
