<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Admin authentication password. Nullable because player accounts
            // never have one (they authenticate with `pin_hash`). The
            // `admin:set-password` Artisan command is the only intended way
            // to populate this for production admins.
            $table->string('password')->nullable()->after('pin_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('password');
        });
    }
};
