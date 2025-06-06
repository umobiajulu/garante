<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone_number',
        'role',
        'trust_score',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'deleted_at' => 'datetime'
    ];

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function ownedBusinesses(): HasMany
    {
        return $this->hasMany(Business::class, 'owner_id');
    }

    public function sellerGuarantees(): HasMany
    {
        return $this->hasMany(Guarantee::class, 'seller_id');
    }

    public function buyerGuarantees(): HasMany
    {
        return $this->hasMany(Guarantee::class, 'buyer_id');
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class, 'initiated_by');
    }

    public function resolvedDisputes(): HasMany
    {
        return $this->hasMany(Dispute::class, 'assigned_arbitrator_id');
    }

    public function verifiedProfiles(): HasMany
    {
        return $this->hasMany(Profile::class, 'verified_by');
    }

    public function verifiedBusinesses(): HasMany
    {
        return $this->hasMany(Business::class, 'verified_by');
    }

    public function isArbitrator(): bool
    {
        return $this->role === 'arbitrator';
    }
}
