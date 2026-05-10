<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Services;

use Empire2\GazeGhostwriter\Support\ImapFolderResolver;
use Throwable;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;

final class GhostwriterImapDiagnostics
{
    public function __construct(
        private readonly ClientManager $clientManager,
        private readonly ImapInboundMailSync $imapInboundMailSync,
    ) {}

    /**
     * @return array{
     *     ok: bool,
     *     headline: string,
     *     notes: list<string>,
     *     folders: list<array{path: string, name: string, delimiter: string, hint: string}>,
     *     folder_checks: list<array{path: string, ok: bool, message: string|null}>
     * }
     */
    public function run(): array
    {
        $host = trim((string) config('gaze-ghostwriter.imap.host', ''));
        $user = trim((string) config('gaze-ghostwriter.imap.username', ''));

        if ($host === '' || $user === '') {
            return [
                'ok' => false,
                'headline' => 'Konfiguration unvollständig',
                'notes' => [
                    'GHOSTWRITER_IMAP_HOST und GHOSTWRITER_IMAP_USERNAME müssen in .env gesetzt sein.',
                ],
                'folders' => [],
                'folder_checks' => [],
            ];
        }

        $notes = [
            'Verbindung zu '.$host.' (Benutzer: '.$user.') …',
        ];

        try {
            $client = $this->clientManager->make($this->imapInboundMailSync->webklexAccountConfig());
            $client->connect();
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'headline' => 'Verbindung fehlgeschlagen',
                'notes' => array_merge($notes, [
                    $e->getMessage(),
                    'Häufige Ursachen: falsches Passwort/App-Passwort, Port/SSL (993/ssl), oder Provider-Einschränkungen.',
                ]),
                'folders' => [],
                'folder_checks' => [],
            ];
        }

        $notes[] = 'Verbindung und Anmeldung erfolgreich.';

        $folderObjects = ImapFolderResolver::flattenAllFolders($client);

        if ($folderObjects === []) {
            $notes[] = 'Keine Ordner zurückgegeben — ungewöhnlich. Prüfe Provider-Dokumentation.';
        }

        $folders = [];
        foreach ($folderObjects as $folder) {
            $folders[] = [
                'path' => $folder->path,
                'name' => $folder->name,
                'delimiter' => $folder->delimiter,
                'hint' => $this->sentFolderHint($folder),
            ];
        }

        usort($folders, static fn (array $a, array $b): int => strcmp($a['path'], $b['path']));

        $notes[] = 'Trage den exakten Wert aus „path“ in GHOSTWRITER_IMAP_EXTRA_FOLDERS ein (kommagetrennt bei mehreren).';

        $folderChecks = $this->verifyConfiguredFolders($client);

        $client->disconnect();

        $notes[] = 'Fertig.';

        return [
            'ok' => true,
            'headline' => 'IMAP-Verbindung in Ordnung',
            'notes' => $notes,
            'folders' => $folders,
            'folder_checks' => $folderChecks,
        ];
    }

    private function sentFolderHint(Folder $folder): string
    {
        $blob = strtolower($folder->path.' '.$folder->name.' '.$folder->full_name);
        $markers = ['sent', 'gesendet', 'gesendete', 'sent mail', 'postausgang', 'outbox', 'envoyé', 'enviados'];
        foreach ($markers as $needle) {
            if (str_contains($blob, $needle)) {
                return 'häufig „Gesendet“ / Sent';
            }
        }

        return '';
    }

    /**
     * @return list<array{path: string, ok: bool, message: string|null}>
     */
    private function verifyConfiguredFolders(Client $client): array
    {
        $names = $this->imapInboundMailSync->configuredFolderNames();
        if ($names === []) {
            return [];
        }

        $checks = [];
        foreach ($names as $label) {
            if ($label === '') {
                continue;
            }

            try {
                $folder = ImapFolderResolver::resolve($client, $label);
                if ($folder === null) {
                    $checks[] = ['path' => $label, 'ok' => false, 'message' => 'Nicht gefunden'];

                    continue;
                }
                $checks[] = ['path' => $label, 'ok' => true, 'message' => null];
            } catch (Throwable $e) {
                $checks[] = ['path' => $label, 'ok' => false, 'message' => $e->getMessage()];
            }
        }

        return $checks;
    }
}
