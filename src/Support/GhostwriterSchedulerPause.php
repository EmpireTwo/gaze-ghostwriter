<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Support;

use Empire2\GazeGhostwriter\Models\GhostwriterSetting;

/**
 * Runtime toggle for the Ghostwriter scheduler (admin UI). When paused,
 * automatic ProcessGhostwriterInboxJob dispatches should be skipped by the
 * host scheduler. Manual "fetch inbox" from the admin UI still works.
 */
final class GhostwriterSchedulerPause
{
    public const KEY = 'scheduler_paused';

    public static function isPaused(): bool
    {
        return GhostwriterSetting::getValue(self::KEY) === '1';
    }

    public static function pause(): void
    {
        GhostwriterSetting::setValue(self::KEY, '1');
    }

    public static function resume(): void
    {
        GhostwriterSetting::setValue(self::KEY, null);
    }

    public static function toggle(): bool
    {
        if (self::isPaused()) {
            self::resume();

            return false;
        }

        self::pause();

        return true;
    }
}
