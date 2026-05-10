<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Support;

/**
 * Stub: the Dashboard implementation reaches into host-specific Customer /
 * Artist / Release models. Hosts that want to resolve AI-extracted entity
 * mentions should subclass this resolver (or replace the binding) and call
 * `MentionedEntityResolver::register()` with their own implementation.
 *
 * The package keeps this class so the contract the AI agent talks to
 * (`mentioned_entities[] = ['type' => …, 'query' => …]`) remains stable;
 * resolution into actionable URLs is a host concern.
 */
final class MentionedEntityResolver
{
    /**
     * @param  list<array{type: string, query: string}>  $mentionedEntities
     * @return list<array{type: string, name: string, url: string}>
     */
    public static function resolve(mixed $customer, array $mentionedEntities): array
    {
        // Host integration point — see class docblock.
        return [];
    }
}
