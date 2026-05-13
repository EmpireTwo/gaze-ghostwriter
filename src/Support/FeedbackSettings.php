<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Support;

use Empire2\GazeGhostwriter\Models\GhostwriterSetting;

final readonly class FeedbackSettings
{
    /**
     * @param  list<string>  $topics
     */
    public function __construct(
        public bool $enabled,
        public bool $requireSubject,
        public bool $requireEmailForGuests,
        public array $topics,
        public int $rateLimitPerMinute,
    ) {}

    public static function all(): self
    {
        return new self(
            enabled: self::bool('feedback_enabled', false),
            requireSubject: self::bool('feedback_require_subject', false),
            requireEmailForGuests: self::bool('feedback_require_email_for_guests', true),
            topics: self::topics(),
            rateLimitPerMinute: self::int('feedback_rate_limit_per_minute', 5),
        );
    }

    private static function bool(string $key, bool $default): bool
    {
        $raw = GhostwriterSetting::getValue($key);
        if ($raw === null) {
            return $default;
        }

        return in_array(strtolower(trim($raw)), ['1', 'true', 'yes', 'on'], true);
    }

    private static function int(string $key, int $default): int
    {
        $raw = GhostwriterSetting::getValue($key);
        if ($raw === null || ! is_numeric($raw)) {
            return $default;
        }

        return max(0, (int) $raw);
    }

    /** @return list<string> */
    private static function topics(): array
    {
        $raw = GhostwriterSetting::getValue('feedback_topics');
        if ($raw === null) {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($v): string => is_string($v) ? trim($v) : '',
            $decoded,
        ), static fn (string $v): bool => $v !== ''));
    }
}
