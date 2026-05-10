<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Support;

use Illuminate\Support\Facades\Cache;

final class ConversationPartnerCache
{
    public const KEY = 'ghostwriter.conversation_partner_email';

    public static function get(): ?string
    {
        $value = Cache::get(self::KEY);
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : ConversationPartnerFilter::normalizeEmail($trimmed);
    }

    public static function put(string $email): void
    {
        Cache::put(
            self::KEY,
            ConversationPartnerFilter::normalizeEmail($email),
            now()->addDays(90),
        );
    }

    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }

    public static function effective(): ?string
    {
        $fromCache = self::get();
        if ($fromCache !== null) {
            return $fromCache;
        }

        $fromEnv = trim((string) config('gaze-ghostwriter.imap.only_conversation_with_email', ''));

        return $fromEnv !== '' ? ConversationPartnerFilter::normalizeEmail($fromEnv) : null;
    }

    public static function hasAdminOverride(): bool
    {
        return self::get() !== null;
    }
}
