<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Concerns;

use Empire2\GazeGhostwriter\Models\GhostwriterUserData;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Add this trait to the host User model so the Ghostwriter package can
 * load the user's signing name and reply signatures without coupling to a
 * specific User class.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasGhostwriterUserData
{
    /**
     * @return HasOne<GhostwriterUserData, $this>
     */
    public function ghostwriterUserData(): HasOne
    {
        /** @var HasOne<GhostwriterUserData, $this> $relation */
        $relation = $this->hasOne(GhostwriterUserData::class, 'user_id');

        return $relation;
    }
}
