<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseIsolationTest extends TestCase
{
    public function test_automatic_tests_use_only_the_isolated_database(): void
    {
        $database = DB::scalar('SELECT DATABASE()');

        $this->assertSame('testing', app()->environment());
        $this->assertSame('smartwallet_test', $database);
        $this->assertStringEndsWith('_test', $database);
    }
}
