<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Services;

use RuntimeException;

final class SupportDraftReplySendException extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self('Ghostwriter SMTP ist nicht konfiguriert (GHOSTWRITER_SMTP_HOST und GHOSTWRITER_REPLY_FROM_ADDRESS).');
    }

    public static function invalidStatus(): self
    {
        return new self('Dieser Entwurf kann in diesem Status nicht gesendet werden.');
    }

    public static function alreadySent(): self
    {
        return new self('Die Antwort wurde bereits gesendet.');
    }

    public static function missingRecipient(): self
    {
        return new self('Absenderadresse der Original-Mail fehlt — Empfänger unbekannt.');
    }

    public static function emptyBody(): self
    {
        return new self('Der Antworttext ist leer.');
    }

    public static function transportFailed(string $detail): self
    {
        return new self('SMTP-Versand fehlgeschlagen: '.$detail);
    }
}
