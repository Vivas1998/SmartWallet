<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_guest_layout_offers_a_keyboard_skip_link(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('href="#main-content"', false)
            ->assertSee('Saltar al contenido principal')
            ->assertSee('id="main-content" tabindex="-1"', false);
    }

    public function test_the_development_seeder_creates_a_login_ready_account(): void
    {
        $this->seed();

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'email_normalized' => 'test@example.com',
        ]);
        $this->assertTrue(Hash::check('clave de prueba local', User::query()->where('email_normalized', 'test@example.com')->sole()->password));
    }

    public function test_a_person_can_register_without_email_verification(): void
    {
        $response = $this->post('/registro', [
            'name' => 'Pablo García',
            'email' => '  PABLO@Example.Test  ',
            'password' => 'una clave familiar',
            'password_confirmation' => 'una clave familiar',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'Pablo García',
            'email' => 'pablo@example.test',
            'email_normalized' => 'pablo@example.test',
            'email_verified_at' => null,
        ]);
    }

    public function test_normalized_email_cannot_be_registered_twice(): void
    {
        User::factory()->create(['email' => 'familia@example.test']);

        $this->from('/registro')->post('/registro', [
            'name' => 'Otra persona',
            'email' => ' FAMILIA@example.test ',
            'password' => 'otra clave familiar',
            'password_confirmation' => 'otra clave familiar',
        ])->assertRedirect('/registro')->assertSessionHasErrors('email');
    }

    public function test_password_must_have_at_least_twelve_characters(): void
    {
        $this->from('/registro')->post('/registro', [
            'name' => 'Pablo',
            'email' => 'pablo@example.test',
            'password' => 'muy corta',
            'password_confirmation' => 'muy corta',
        ])->assertRedirect('/registro')->assertSessionHasErrors('password');
    }

    public function test_a_user_can_log_in_with_normalized_email_and_log_out(): void
    {
        $user = User::factory()->create([
            'email' => 'pablo@example.test',
            'password' => 'clave de acceso 2026',
        ]);

        $this->post('/acceder', [
            'email' => ' PABLO@EXAMPLE.TEST ',
            'password' => 'clave de acceso 2026',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);

        $this->post('/salir')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_registration_can_be_disabled_by_configuration(): void
    {
        config(['smartwallet.registration_enabled' => false]);

        $this->get('/registro')->assertNotFound();
        $this->post('/registro', [])->assertNotFound();
    }

    public function test_password_reset_request_does_not_reveal_if_account_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'pablo@example.test']);

        $known = $this->post('/contrasena/olvidada', ['email' => $user->email]);
        $unknown = $this->post('/contrasena/olvidada', ['email' => 'nadie@example.test']);

        $known->assertSessionHas('status');
        $unknown->assertSessionHas('status', $known->getSession()->get('status'));
        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_a_valid_reset_token_changes_password_and_invalidates_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'pablo@example.test',
            'password' => 'clave anterior 2026',
        ]);

        DB::table('sessions')->insert([
            'id' => 'another-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $token = Password::createToken($user);

        $this->post('/contrasena/restablecer', [
            'token' => $token,
            'email' => 'PABLO@example.test',
            'password' => 'clave nueva familiar',
            'password_confirmation' => 'clave nueva familiar',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('clave nueva familiar', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'another-session']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }
}
