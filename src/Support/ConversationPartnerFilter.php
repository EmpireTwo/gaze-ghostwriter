<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Support;

final class ConversationPartnerFilter
{
    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    /**
     * @param  list<string>  $toEmails
     * @param  list<string>  $ccEmails
     */
    public static function touchesPartner(
        string $fromEmail,
        array $toEmails,
        array $ccEmails,
        string $partnerNormalized,
    ): bool {
        $from = self::normalizeEmail($fromEmail);
        if ($from === $partnerNormalized) {
            return true;
        }

        $recipients = array_merge(
            self::normalizeList($toEmails),
            self::normalizeList($ccEmails),
        );

        return in_array($partnerNormalized, $recipients, true);
    }

    /**
     * @param  list<string>  $toEmails
     * @param  list<string>  $ccEmails
     * @param  list<string>  $supportNeedlesNormalized
     */
    public static function isOutboundFromSupportToPartner(
        string $fromEmail,
        array $toEmails,
        array $ccEmails,
        array $supportNeedlesNormalized,
        string $partnerNormalized,
    ): bool {
        if ($supportNeedlesNormalized === []) {
            return false;
        }

        $from = self::normalizeEmail($fromEmail);
        if (! in_array($from, $supportNeedlesNormalized, true)) {
            return false;
        }

        $recipients = array_merge(
            self::normalizeList($toEmails),
            self::normalizeList($ccEmails),
        );

        return in_array($partnerNormalized, $recipients, true);
    }

    /**
     * @param  array<int, mixed>  $emails
     * @return list<string>
     */
    private static function normalizeList(array $emails): array
    {
        $out = [];
        foreach ($emails as $email) {
            if (! is_string($email) || trim($email) === '') {
                continue;
            }
            $out[] = self::normalizeEmail($email);
        }

        return array_values(array_unique($out));
    }
}
