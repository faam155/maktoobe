<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Notifications\ResetAccountPassword;
use App\Notifications\VerifyAccountEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'username', 'password'])]
#[Hidden(['password', 'remember_token', 'security_version'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $attributes = ['security_version' => 1, 'status' => 'pending', 'locale' => 'en', 'timezone' => 'UTC'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'phone_verified_at' => 'datetime',
            'disabled_at' => 'datetime',
            'status' => AccountStatus::class,
            'security_version' => 'integer',
        ];
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function otpChallenges(): HasMany
    {
        return $this->hasMany(OtpChallenge::class);
    }

    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class, 'owner_id');
    }

    public function aiConversations(): HasMany
    {
        return $this->hasMany(AiConversation::class);
    }

    public function organizedEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'organizer_id');
    }

    public function accessibleEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_user_access')->withPivot(['granted_by', 'created_at']);
    }

    public function favoritePrompts(): BelongsToMany
    {
        return $this->belongsToMany(Prompt::class, 'prompt_favorites')->withPivot('created_at');
    }

    public function preferredLocale(): string
    {
        return $this->locale ?? 'en';
    }

    public function notifications()
    {
        return $this->morphMany(WorkspaceDatabaseNotification::class, 'notifiable')->latest();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyAccountEmail);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetAccountPassword($token));
    }
}
