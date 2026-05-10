<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Services;

use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Throwable;

final class GhostwriterSmtpDiagnostics
{
    /**
     * @return array{ok: bool, headline: string, notes: list<string>}
     */
    public function run(): array
    {
        $host = trim((string) config('gaze-ghostwriter.smtp.host', ''));
        $from = trim((string) config('gaze-ghostwriter.reply.from_address', ''));
        $driver = strtolower(trim((string) config('gaze-ghostwriter.smtp.driver', 'smtp')));
        $port = (int) config('gaze-ghostwriter.smtp.port', 587);
        $encryption = (string) config('gaze-ghostwriter.smtp.encryption', 'tls');
        $user = trim((string) config('gaze-ghostwriter.smtp.username', ''));

        if ($host === '' || $from === '') {
            return [
                'ok' => false,
                'headline' => 'SMTP-Konfiguration unvollständig',
                'notes' => [
                    'GHOSTWRITER_SMTP_HOST und GHOSTWRITER_REPLY_FROM_ADDRESS müssen in .env gesetzt sein.',
                ],
            ];
        }

        $notes = [
            'Ziel: '.$host.':'.$port.' (Verschlüsselung: '.$encryption.', Treiber: '.$driver.')',
            'Absender (Reply): '.$from,
        ];

        if ($user !== '') {
            $notes[] = 'SMTP-Authentifizierung: Benutzer „'.$user.'“.';
        } else {
            $notes[] = 'Kein SMTP-Benutzername — Server muss Relay ohne Auth erlauben.';
        }

        $transport = GhostwriterSmtpTransportFactory::make();

        if ($transport instanceof NullTransport) {
            $notes[] = 'GHOSTWRITER_SMTP_DRIVER=null: Es wird keine echte SMTP-Verbindung aufgebaut (sinnvoll für Tests).';

            return [
                'ok' => true,
                'headline' => 'SMTP (Null-Treiber)',
                'notes' => $notes,
            ];
        }

        if (! $transport instanceof SmtpTransport) {
            $notes[] = 'Unerwarteter Transport-Typ — kein Verbindungstest.';

            return [
                'ok' => false,
                'headline' => 'SMTP-Test nicht möglich',
                'notes' => $notes,
            ];
        }

        try {
            $transport->start();
            $notes[] = 'TCP-Verbindung, EHLO/STARTTLS und ggf. AUTH erfolgreich.';
            $transport->stop();
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'headline' => 'SMTP-Verbindung fehlgeschlagen',
                'notes' => array_merge($notes, [
                    $e->getMessage(),
                    'Häufige Ursachen: Port/SSL (587+tls vs 465), falsches Passwort, Firewall, oder Provider blockiert SMTP von dieser IP.',
                ]),
            ];
        }

        $notes[] = 'Verbindung getrennt (QUIT).';

        return [
            'ok' => true,
            'headline' => 'SMTP-Verbindung in Ordnung',
            'notes' => $notes,
        ];
    }
}
