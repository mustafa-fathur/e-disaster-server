<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserTypeEnum;
use App\Enums\UserStatusEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasUuids, Notifiable, SoftDeletes, TwoFactorAuthenticatable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'status',
        'name',
        'email',
        'email_verified',
        'password',
        'password_changed_at',
        'timezone',
        'last_login_at',
        'location',
        'coordinate',
        'lat',
        'long',
        'nik',
        'phone',
        'address',
        'gender',
        'date_of_birth',
        'reason_to_join',
        'registered_at',
        'approved_at',
        'approved_by',
        'rejection_reason',
        'rejected_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => UserTypeEnum::class,
            'status' => UserStatusEnum::class,
            'email_verified' => 'boolean',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'date_of_birth' => 'date',
            'registered_at' => 'datetime',
            'approved_at' => 'datetime',
            'gender' => 'boolean',
            'lat' => 'float',
            'long' => 'float'
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            // Set default values for new users
            if (empty($user->type)) {
                $user->type = UserTypeEnum::VOLUNTEER;
            }
            if (empty($user->status)) {
                $user->status = UserStatusEnum::REGISTERED;
            }
        });
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Get the disasters reported by this user
     */
    public function reportedDisasters()
    {
        return $this->hasMany(Disaster::class, 'reported_by');
    }

    /**
     * Get the disaster volunteers for this user
     */
    public function disasterVolunteers()
    {
        return $this->hasMany(DisasterVolunteer::class);
    }

    /**
     * Get the notifications for this user
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get the disasters this user is volunteering for
     */
    public function disasters()
    {
        return $this->belongsToMany(Disaster::class, 'disaster_volunteers');
    }

    /**
     * Get all devices for this user
     */
    public function devices()
    {
        return $this->hasMany(UserDevice::class);
    }

    /**
     * Get active devices for this user
     */
    public function activeDevices()
    {
        return $this->hasMany(UserDevice::class)->where('is_active', true);
    }
}
