<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('categories')
            ->where('is_initial', true)
            ->where('color', '#B85F06')
            ->update(['color' => '#B25900']);
    }

    public function down(): void
    {
        DB::table('categories')
            ->where('is_initial', true)
            ->where('color', '#B25900')
            ->update(['color' => '#B85F06']);
    }
};
