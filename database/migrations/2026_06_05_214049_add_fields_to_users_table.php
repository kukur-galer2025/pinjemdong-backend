<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('avatar')->nullable()->after('phone');
            $table->enum('role', ['admin', 'customer'])->default('customer')->after('avatar');
            $table->string('google_id')->nullable()->unique()->after('role');
            $table->boolean('is_blacklisted')->default(false)->after('google_id');
            $table->text('blacklist_reason')->nullable()->after('is_blacklisted');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'avatar', 'role', 'google_id', 'is_blacklisted', 'blacklist_reason']);
        });
    }
};
