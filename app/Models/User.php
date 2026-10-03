<?php

namespace App\Models;

use App\Notifications\ResetPasswordQueued;
use App\Notifications\VerifyEmailQueued;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    protected $attributes = ['is_admin' => false, 'balance_minor' => 0, 'password_reset_required' => false, 'totp_last_step' => -1];

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

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed', 'totp_secret' => 'encrypted', 'recovery_codes' => 'encrypted:array', 'totp_last_step' => 'integer',
            'is_admin' => 'boolean', 'password_reset_required' => 'boolean', 'balance_minor' => 'integer',
        ];
    }
}
