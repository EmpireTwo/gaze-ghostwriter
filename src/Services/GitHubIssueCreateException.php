<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Services;

use RuntimeException;

final class GitHubIssueCreateException extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self('GitHub ist nicht konfiguriert (GITHUB_REPO und GITHUB_TOKEN in .env).');
    }

    public static function alreadyLinked(): self
    {
        return new self('Für diesen Entwurf existiert bereits ein GitHub-Issue.');
    }

    public static function invalidRepo(string $repo): self
    {
        return new self('GITHUB_REPO muss im Format „owner/repo“ angegeben sein, aktuell: '.$repo);
    }

    public static function apiFailed(string $detail): self
    {
        return new self('GitHub-API-Fehler: '.$detail);
    }
}
