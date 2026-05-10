<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Console\Commands;

use Empire2\GazeGhostwriter\Services\GhostwriterImapDiagnostics;
use Illuminate\Console\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class GhostwriterImapTestCommand extends Command
{
    protected $signature = 'ghostwriter:imap-test';

    protected $description = 'Ghostwriter: IMAP-Verbindung testen und Ordnernamen (inkl. Gesendet) anzeigen';

    public function handle(GhostwriterImapDiagnostics $diagnostics): int
    {
        $result = $diagnostics->run();

        if (! $result['ok']) {
            error($result['headline']);
            foreach ($result['notes'] as $line) {
                note($line);
            }

            return Command::FAILURE;
        }

        note($result['headline']);
        foreach ($result['notes'] as $line) {
            note($line);
        }

        if ($result['folders'] !== []) {
            $rows = [];
            foreach ($result['folders'] as $row) {
                $rows[] = [$row['path'], $row['name'], $row['delimiter'], $row['hint']];
            }
            table(
                ['path (für .env)', 'name', 'Trenner', 'Hinweis'],
                $rows,
            );
        }

        foreach ($result['folder_checks'] as $check) {
            if ($check['ok']) {
                info('OK: '.$check['path']);
            } else {
                $detail = $check['message'] ?? 'fehlgeschlagen';
                warning('Nicht erreichbar: '.$check['path'].' — '.$detail);
            }
        }

        return Command::SUCCESS;
    }
}
