<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Features;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Pennant-compatible feature gate for the Gaze boundary.
 *
 * Default resolution simply mirrors the `gaze-ghostwriter.gaze_enabled`
 * config flag. Hosts that use Laravel Pennant can still register this class
 * as a feature and add their own scope-aware resolution; the
 * `GuardedAgentRunner` only checks the config flag, so this class is purely
 * informational unless wired up in the host.
 */
class GhostwriterGaze
{
    public function resolve(?Authenticatable $user = null): bool
    {
        return (bool) config('gaze-ghostwriter.gaze_enabled', false);
    }
}
