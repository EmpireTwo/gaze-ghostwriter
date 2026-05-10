<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Support;

/**
 * Stub: the Dashboard implementation looks up a User by email and returns
 * their attached Customer record (host domain model). Hosts should
 * subclass / replace this with their own resolution and bind it in the
 * service container.
 *
 * Returning `null` is always safe — the smart-action UI just hides the
 * "open customer" link.
 */
final class SmartActionCustomerResolver
{
    public static function resolve(string $fromEmail): mixed
    {
        return null;
    }
}
