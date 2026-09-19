<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScaleBenchmarkCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_scale_benchmark_rejects_counts_outside_the_safe_range(): void
    {
        $this->artisan('smartwallet:benchmark-scale', ['--movements' => 999])
            ->expectsOutput('El número de movimientos debe estar entre 1.000 y 100.000.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('movements', 0);
    }
}
