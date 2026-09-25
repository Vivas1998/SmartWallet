<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ThemePreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_profile_requires_authentication_and_shows_personal_and_session_data(): void
    {
        $this->get(route('profile.show'))->assertRedirect(route('login'));

        $user = User::factory()->create(['name' => 'Pablo García', 'email' => 'pablo@example.test']);
        $this->sessionRow($user, 'other-session', '192.168.1.20', 'Mozilla/5.0 (Windows NT 10.0) Chrome/140.0');

        $this->actingAs($user)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('PG')
            ->assertSee('pablo@example.test')
            ->assertSee('Sesión actual')
            ->assertSee('Chrome en Windows')
            ->assertSee('192.168.1.20')
            ->assertSee('Cerrar las demás');
    }

    public function test_a_user_can_update_their_visible_name(): void
    {
        $user = User::factory()->create(['name' => 'Nombre anterior']);

        $this->actingAs($user)->patch(route('profile.name.update'), [
            'name' => '  Pablo   García  ',
        ])->assertRedirect(route('profile.show'));

        $this->assertSame('Pablo García', $user->fresh()->name);
    }

    public function test_a_user_can_choose_a_theme_preference(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.theme.update'), [
            'theme_preference' => ThemePreference::Dark->value,
        ])->assertRedirect()->assertSessionHas('status', 'Apariencia actualizada.');

        $this->assertSame(ThemePreference::Dark, $user->fresh()->theme_preference);
        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-theme="dark"', false)
            ->assertSee('id="header-theme-preference"', false);

        $this->actingAs($user)->from(route('profile.show'))->patch(route('profile.theme.update'), [
            'theme_preference' => 'sepia',
        ])->assertRedirect(route('profile.show'))->assertSessionHasErrors('theme_preference');

        $this->assertSame(ThemePreference::Dark, $user->fresh()->theme_preference);
    }

    public function test_guest_pages_follow_the_device_theme(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-theme="auto"', false);
    }

    public function test_changing_email_requires_the_current_password_and_a_unique_normalized_address(): void
    {
        $user = User::factory()->create([
            'email' => 'anterior@example.test',
            'password' => 'clave actual familiar',
        ]);
        User::factory()->create(['email' => 'ocupado@example.test']);

        $this->actingAs($user)->from(route('profile.show'))->patch(route('profile.email.update'), [
            'email' => 'nuevo@example.test',
            'email_current_password' => 'incorrecta',
        ])->assertRedirect(route('profile.show'))->assertSessionHasErrors('email_current_password');

        $this->actingAs($user)->from(route('profile.show'))->patch(route('profile.email.update'), [
            'email' => ' OCUPADO@EXAMPLE.TEST ',
            'email_current_password' => 'clave actual familiar',
        ])->assertRedirect(route('profile.show'))->assertSessionHasErrors('email');

        $this->actingAs($user)->patch(route('profile.email.update'), [
            'email' => ' NUEVO@Example.Test ',
            'email_current_password' => 'clave actual familiar',
        ])->assertRedirect(route('profile.show'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'nuevo@example.test',
            'email_normalized' => 'nuevo@example.test',
            'email_verified_at' => null,
        ]);
    }

    public function test_a_password_change_can_close_other_sessions_and_invalidates_reset_tokens(): void
    {
        $user = User::factory()->create([
            'email' => 'pablo@example.test',
            'password' => 'clave anterior familiar',
        ]);
        $this->sessionRow($user, 'phone-session');
        $this->sessionRow($user, 'tablet-session');
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => 'token-pendiente',
            'created_at' => now(),
        ]);

        $this->actingAs($user)->from(route('profile.show'))->put(route('profile.password.update'), [
            'password_current_password' => 'incorrecta',
            'password' => 'clave nueva familiar',
            'password_confirmation' => 'clave nueva familiar',
            'logout_other_sessions' => '1',
        ])->assertRedirect(route('profile.show'))->assertSessionHasErrors('password_current_password');
        $this->assertSame(2, DB::table('sessions')->where('user_id', $user->id)->count());

        $this->actingAs($user)->put(route('profile.password.update'), [
            'password_current_password' => 'clave anterior familiar',
            'password' => 'clave nueva familiar',
            'password_confirmation' => 'clave nueva familiar',
            'logout_other_sessions' => '1',
        ])->assertRedirect(route('profile.show'));

        $this->assertTrue(Hash::check('clave nueva familiar', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_other_sessions_can_be_preserved_or_revoked_without_affecting_another_user(): void
    {
        $user = User::factory()->create(['password' => 'clave anterior familiar']);
        $other = User::factory()->create();
        $this->sessionRow($user, 'keep-session');
        $this->sessionRow($user, 'remove-session');
        $this->sessionRow($other, 'foreign-session');

        $this->actingAs($user)->put(route('profile.password.update'), [
            'password_current_password' => 'clave anterior familiar',
            'password' => 'otra clave familiar segura',
            'password_confirmation' => 'otra clave familiar segura',
        ])->assertRedirect(route('profile.show'));
        $this->assertDatabaseHas('sessions', ['id' => 'keep-session', 'user_id' => $user->id]);

        $this->actingAs($user)->delete(route('profile.sessions.destroy', 'remove-session'))
            ->assertRedirect(route('profile.show'));
        $this->assertDatabaseMissing('sessions', ['id' => 'remove-session']);

        $this->actingAs($user)->delete(route('profile.sessions.destroy', 'foreign-session'))
            ->assertNotFound();
        $this->assertDatabaseHas('sessions', ['id' => 'foreign-session', 'user_id' => $other->id]);

        $this->actingAs($user)->delete(route('profile.sessions.destroy-others'))
            ->assertRedirect(route('profile.show'));
        $this->assertDatabaseMissing('sessions', ['id' => 'keep-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'foreign-session', 'user_id' => $other->id]);
        $this->assertAuthenticatedAs($user);
    }

    private function sessionRow(
        User $user,
        string $id,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'PHPUnit',
    ): void {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);
    }
}
