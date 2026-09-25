<?php

namespace App\Models;

use App\Enums\ThemePreference;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'email_normalized', 'password', 'last_login_at', 'theme_preference'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @var array<string, mixed> */
    protected $attributes = [
        'theme_preference' => ThemePreference::Auto->value,
    ];

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            $user->email = trim((string) $user->email);
            $user->email_normalized = Str::lower($user->email);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'theme_preference' => ThemePreference::class,
        ];
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->wherePivotNull('removed_at')
            ->withPivot(['role', 'joined_at', 'removed_at', 'added_by_user_id'])
            ->withTimestamps();
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->squish()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
