<?php

namespace App\Models;

use App\Notifications\ResetPasswordQueued;
use App\Notifications\VerifyEmailQueued;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    protected $attributes = ['is_admin' => false, 'balance_minor' => 0, 'password_reset_required' => false, 'totp_last_step' => -1];

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if (blank($user->username)) {
                $user->username = self::uniqueUsername($user->name, $user->email);
            }
        });
    }

    public static function uniqueUsername(?string $name, ?string $email = null): string
    {
        $source = Str::before(Str::lower(trim((string) ($email ?: $name))), '@');
        $base = Str::slug($source ?: (string) $name);
        $base = substr(preg_replace('/[^a-z0-9]+/', '-', $base) ?: 'user', 0, 24);
        $base = trim($base, '-') ?: 'user';
        $username = $base;
        $suffix = 2;
        while (static::where('username', $username)->exists()) {
            $username = substr($base, 0, 31 - strlen((string) $suffix)).'-'.$suffix++;
        }

        return $username;
    }

    public function avatarUrl(int $size = 96): string
    {
        if ($this->avatar_content) {
            return route('profile.avatar').'?v='.($this->avatar_updated_at?->timestamp ?? $this->updated_at?->timestamp ?? time());
        }

        $hash = hash('sha256', Str::lower(trim((string) $this->email)));

        return 'https://www.gravatar.com/avatar/'.$hash.'?s='.$size.'&d=identicon&r=g';
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmailQueued);
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordQueued($token));
    }

    public function apiCredentialFingerprint(): string
    {
        return hash('sha256', $this->getAuthPassword().'|'.($this->totp_secret ?? ''));
    }

    public function staffRole()
    {
        return $this->belongsTo(StaffRole::class);
    }

    public function isStaff(): bool
    {
        return $this->is_admin || ($this->staffRole && count($this->staffRole->permissions) > 0);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->is_admin || in_array($permission, $this->staffRole?->permissions ?? [], true);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function domains()
    {
        return $this->hasMany(DomainRegistration::class);
    }

    public function affiliate()
    {
        return $this->hasOne(Affiliate::class);
    }

    public function contacts()
    {
        return $this->hasMany(AccountContact::class, 'owner_id');
    }

    public function delegatedAccounts()
    {
        return $this->hasMany(AccountContact::class, 'contact_user_id');
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'tax_id',
        'company_name',
        'phone',
        'billing_address',
        'referred_by_id',
        'external_source',
        'external_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password', 'avatar_content',
        'remember_token', 'totp_secret', 'recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime', 'avatar_updated_at' => 'datetime',
            'password' => 'hashed', 'totp_secret' => 'encrypted', 'recovery_codes' => 'encrypted:array', 'totp_last_step' => 'integer',
            'is_admin' => 'boolean', 'password_reset_required' => 'boolean', 'balance_minor' => 'integer',
        ];
    }
}
