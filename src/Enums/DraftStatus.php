<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Enums;

enum DraftStatus: string
{
    case PENDING_REVIEW = 'pending_review';
    case DISMISSED = 'dismissed';
    case ACCEPTED = 'accepted';
    case SUPERSEDED = 'superseded';
    case SENT = 'sent';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_REVIEW => 'Offen',
            self::DISMISSED => 'Keine Antwort nötig',
            self::ACCEPTED => 'Übernommen',
            self::SUPERSEDED => 'Ersetzt',
            self::SENT => 'Gesendet',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::PENDING_REVIEW => 'bg-blue-50 text-blue-700',
            self::SENT => 'bg-emerald-50 text-emerald-700',
            self::DISMISSED => 'bg-amber-50 text-amber-700',
            self::ACCEPTED => 'bg-violet-50 text-violet-700',
            self::SUPERSEDED => 'bg-zinc-100 text-zinc-500',
        };
    }
}
