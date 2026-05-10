<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Test-only Customer fixture standing in for the host's billing-domain
 * Customer model. The Ghostwriter package itself does not depend on a
 * Customer concept — it only enters the test suite through
 * SmartActionCustomerResolver tests that reference a host customer.
 */
class Customer extends Model
{
    use HasFactory;

    protected $table = 'customers';

    protected $guarded = [];

    public $timestamps = true;

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
