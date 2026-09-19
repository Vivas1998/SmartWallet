<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email_normalized')->nullable()->after('email');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
        });

        DB::table('users')
            ->select(['id', 'email'])
            ->orderBy('id')
            ->each(function (object $user): void {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'email' => trim((string) $user->email),
                        'email_normalized' => Str::lower(trim((string) $user->email)),
                    ]);
            });

        DB::statement('ALTER TABLE users MODIFY email_normalized VARCHAR(255) NOT NULL');

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('email_normalized');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['email_normalized']);
            $table->dropColumn(['email_normalized', 'last_login_at']);
        });
    }
};
