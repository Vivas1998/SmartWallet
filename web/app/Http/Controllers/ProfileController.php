<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $currentSessionId = $request->session()->getId();
        $sessions = $this->sessionQuery()
            ->where('user_id', $request->user()->id)
            ->latest('last_activity')
            ->limit(20)
            ->get()
            ->map(fn (object $session): array => $this->sessionData(
                (string) $session->id,
                (string) ($session->ip_address ?? ''),
                (string) ($session->user_agent ?? ''),
                (int) $session->last_activity,
                $currentSessionId,
            ));

        if (! $sessions->contains(fn (array $session): bool => $session['is_current'])) {
            $sessions->prepend($this->sessionData(
                $currentSessionId,
                (string) $request->ip(),
                (string) $request->userAgent(),
                now()->timestamp,
                $currentSessionId,
            ));
        }

        return view('profile.show', [
            'user' => $request->user(),
            'sessions' => $sessions,
            'otherSessionCount' => $sessions->where('is_current', false)->count(),
        ]);
    }

    public function updateName(Request $request): RedirectResponse
    {
        $request->merge(['name' => Str::squish((string) $request->input('name'))]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
        ]);

        $request->user()->update(['name' => $validated['name']]);

        return redirect()->route('profile.show')->with('status', 'Nombre actualizado.');
    }

    public function updateEmail(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $validated = $request->validate([
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email_normalized')->ignore($request->user()->id),
            ],
            'email_current_password' => ['required', 'current_password:web'],
        ], [
            'email.unique' => 'Ya existe una cuenta con este correo electrónico.',
            'email_current_password.current_password' => 'La contraseña actual no es correcta.',
        ]);

        $request->user()->update(['email' => $validated['email']]);
        $request->session()->regenerate(true);

        return redirect()->route('profile.show')->with('status', 'Correo electrónico actualizado.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password_current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', Password::min(12)],
            'logout_other_sessions' => ['nullable', 'boolean'],
        ], [
            'password_current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.confirmed' => 'La confirmación de la nueva contraseña no coincide.',
        ]);

        $currentSessionId = $request->session()->getId();
        DB::transaction(function () use ($request, $validated, $currentSessionId): void {
            $request->user()->forceFill([
                'password' => Hash::make($validated['password']),
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('password_reset_tokens')->where('email', $request->user()->email)->delete();

            if ($request->boolean('logout_other_sessions')) {
                $this->sessionQuery()
                    ->where('user_id', $request->user()->id)
                    ->where('id', '!=', $currentSessionId)
                    ->delete();
            }
        });
        $request->session()->regenerate(true);

        return redirect()->route('profile.show')->with(
            'status',
            $request->boolean('logout_other_sessions')
                ? 'Contraseña actualizada y demás sesiones cerradas.'
                : 'Contraseña actualizada.',
        );
    }

    public function destroySession(Request $request, string $sessionId): RedirectResponse
    {
        if (hash_equals($request->session()->getId(), $sessionId)) {
            throw ValidationException::withMessages([
                'session' => 'La sesión actual se cierra desde el botón «Cerrar sesión».',
            ]);
        }

        $deleted = $this->sessionQuery()
            ->where('user_id', $request->user()->id)
            ->where('id', $sessionId)
            ->delete();
        abort_if($deleted === 0, 404);

        return redirect()->route('profile.show')->with('status', 'Sesión revocada.');
    }

    public function destroyOtherSessions(Request $request): RedirectResponse
    {
        $deleted = $this->sessionQuery()
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return redirect()->route('profile.show')->with(
            'status',
            $deleted === 1 ? 'Se ha cerrado otra sesión.' : "Se han cerrado {$deleted} sesiones.",
        );
    }

    private function sessionQuery(): Builder
    {
        return DB::connection(config('session.connection'))
            ->table((string) config('session.table', 'sessions'));
    }

    /** @return array{id: string, label: string, ip_address: string, last_activity_at: CarbonImmutable, is_current: bool, is_expired: bool} */
    private function sessionData(string $id, string $ipAddress, string $userAgent, int $lastActivity, string $currentSessionId): array
    {
        $isCurrent = hash_equals($currentSessionId, $id);

        return [
            'id' => $id,
            'label' => $this->deviceLabel($userAgent),
            'ip_address' => $ipAddress !== '' ? $ipAddress : 'No disponible',
            'last_activity_at' => CarbonImmutable::createFromTimestamp($lastActivity, 'Europe/Madrid'),
            'is_current' => $isCurrent,
            'is_expired' => ! $isCurrent && $lastActivity < now()->subMinutes((int) config('session.lifetime'))->timestamp,
        ];
    }

    private function deviceLabel(string $userAgent): string
    {
        if ($userAgent === '') {
            return 'Dispositivo no identificado';
        }

        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Microsoft Edge',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            str_contains($userAgent, 'PHPUnit') => 'Sesión de prueba',
            default => 'Navegador desconocido',
        };
        $device = match (true) {
            str_contains($userAgent, 'iPhone') => 'iPhone',
            str_contains($userAgent, 'iPad') => 'iPad',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Macintosh') => 'Mac',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };

        return $device === null ? $browser : $browser.' en '.$device;
    }
}
