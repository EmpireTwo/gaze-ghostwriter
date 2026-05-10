<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Tests\Fixtures;

use Empire2\GazeGhostwriter\Concerns\HasGhostwriterUserData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Test-only User fixture that mirrors the surface area the package expects
 * from the host User model: Authenticatable + Notifiable + Spatie HasRoles +
 * the package's HasGhostwriterUserData trait. Also exposes a `customer`
 * stub relation so the SmartActionCustomerResolver tests can lean on a
 * package-local Customer fixture instead of host code.
 */
class User extends Authenticatable
{
    use HasFactory;
    use HasGhostwriterUserData;
    use HasRoles;
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = true;

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * @return HasOne<Customer, $this>
     */
    public function customer(): HasOne
    {
        /** @var HasOne<Customer, $this> $relation */
        $relation = $this->hasOne(Customer::class, 'user_id');

        return $relation;
    }
}
