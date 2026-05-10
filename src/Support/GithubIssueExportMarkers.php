<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Support;

/**
 * Fixed strings shown in GitHub issue bodies where content was redacted.
 */
final class GithubIssueExportMarkers
{
    public const PII_REMOVED = '[Persönliche Daten entfernt]';

    public const THREAD_HISTORY_OMITTED = '[Älterer E-Mail-Verlauf ausgeblendet]';
}
