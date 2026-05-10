<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Services;

use Empire2\GazeGhostwriter\Ai\Sanitizer;
use Empire2\GazeGhostwriter\Models\SupportDraft;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Empire2\GazeGhostwriter\Support\GithubIssueExportMarkers;
use Empire2\GazeGhostwriter\Support\MailReplyHistorySplitter;
use Empire2\GazeGhostwriter\Support\MailSignatureTrimmer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class GitHubIssueService
{
    public function __construct(
        private readonly Sanitizer $sanitizer,
    ) {}

    public function isConfigured(): bool
    {
        return filled(config('gaze-ghostwriter.github.repo')) && filled(config('gaze-ghostwriter.github.token'));
    }

    /**
     * @param  array<int, mixed>  $selectedOptional
     * @return list<string>
     */
    public function resolveLabelsForIssuePayload(array $selectedOptional): array
    {
        $all = config('gaze-ghostwriter.github.labels');
        if (! is_array($all) || $all === []) {
            return [];
        }

        $required = isset($all[0]) && is_string($all[0]) && $all[0] !== '' ? $all[0] : null;
        $optionalPool = array_values(array_filter(
            array_slice($all, 1),
            fn ($l): bool => is_string($l) && $l !== ''
        ));
        $allowed = array_flip($optionalPool);

        $picked = [];
        foreach ($selectedOptional as $l) {
            if (is_string($l) && $l !== '' && isset($allowed[$l])) {
                $picked[] = $l;
            }
        }
        $picked = array_values(array_unique($picked));

        if ($required !== null) {
            return array_values(array_unique(array_merge([$required], $picked)));
        }

        return $picked;
    }

    /**
     * @return array{title: string, body: string}
     */
    public function prefillFromDraft(SupportDraft $draft, bool $includeReply = false, ?string $resolvedReplyText = null): array
    {
        $draft->loadMissing('message');
        $msg = $draft->message;

        $subject = $msg->subject !== null && trim((string) $msg->subject) !== ''
            ? trim((string) $msg->subject)
            : 'Support-Mail · ID '.$draft->id;

        $bodyText = $this->plaintextMailBodyForGithubIssue($msg);
        $truncated = Str::limit($bodyText, 2000, ' …');

        $adminUrl = route('gaze-ghostwriter.drafts.show', $draft, absolute: true);

        $body = implode("\n\n", [
            '**Absender (maskiert):** '.self::maskSenderEmailForPublicIssue((string) $msg->from_email),
            '**Betreff:** '.($msg->subject ?? '—'),
            '**Eingegangen:** '.$msg->received_at->format('d.m.Y H:i'),
            '---',
            $truncated,
            '---',
            '> Erstellt aus Ghostwriter · Entwurf-ID '.$draft->id,
            '> '.$adminUrl,
        ]);

        if ($includeReply && is_string($resolvedReplyText) && trim($resolvedReplyText) !== '') {
            $replyBlock = Str::limit(trim($resolvedReplyText), 12000, ' …');
            $body .= "\n\n---\n\n**Antwort (Ghostwriter):**\n\n".$replyBlock;
        }

        return [
            'title' => $subject,
            'body' => $body,
        ];
    }

    private function plaintextMailBodyForGithubIssue(SupportMailMessage $msg): string
    {
        $rawFull = str_replace("\r\n", "\n", (string) ($msg->body_text ?? ''));

        $sanitized = $this->sanitizer->sanitize($rawFull);
        if (is_string($sanitized) && $sanitized !== '') {
            return $sanitized;
        }

        return $this->heuristicMailExcerptForGithub($rawFull);
    }

    private function heuristicMailExcerptForGithub(string $rawFull): string
    {
        $parts = MailReplyHistorySplitter::split($rawFull);
        $trimmed = MailSignatureTrimmer::trimForGithubIssueWithMeta($parts['latest']);
        $text = $trimmed['text'];

        $footnotes = [];
        if ($parts['history'] !== null) {
            $footnotes[] = GithubIssueExportMarkers::THREAD_HISTORY_OMITTED;
        }
        if ($trimmed['was_trimmed']) {
            $footnotes[] = GithubIssueExportMarkers::PII_REMOVED;
        }

        if ($footnotes !== []) {
            $text .= "\n\n".implode("\n", $footnotes);
        }

        return $text;
    }

    /**
     * @param  list<string>  $labels
     * @return array{number: int, html_url: string}
     */
    public function createIssue(string $title, string $body, array $labels = []): array
    {
        if (! $this->isConfigured()) {
            throw GitHubIssueCreateException::notConfigured();
        }

        $repo = (string) config('gaze-ghostwriter.github.repo');
        $token = (string) config('gaze-ghostwriter.github.token');

        $parts = array_values(array_filter(explode('/', trim($repo, '/')), fn (string $s): bool => $s !== ''));
        if (count($parts) !== 2) {
            throw GitHubIssueCreateException::invalidRepo($repo);
        }

        [$owner, $name] = $parts;

        $payload = [
            'title' => $title,
            'body' => $body,
        ];

        if ($labels !== []) {
            $payload['labels'] = array_values(array_filter($labels, fn (string $l): bool => $l !== ''));
        }

        $response = Http::withHeaders([
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ])
            ->withToken($token)
            ->post("https://api.github.com/repos/{$owner}/{$name}/issues", $payload);

        if (! $response->successful()) {
            $message = $response->json('message') ?? $response->body();
            if (! is_string($message) || $message === '') {
                $message = 'HTTP '.$response->status();
            }

            throw GitHubIssueCreateException::apiFailed($message);
        }

        $number = $response->json('number');
        $htmlUrl = $response->json('html_url');

        if (! is_int($number) || ! is_string($htmlUrl) || $htmlUrl === '') {
            throw GitHubIssueCreateException::apiFailed('Unerwartete API-Antwort.');
        }

        return [
            'number' => $number,
            'html_url' => $htmlUrl,
        ];
    }

    private static function maskSenderEmailForPublicIssue(string $email): string
    {
        $email = trim($email);
        if ($email === '' || ! str_contains($email, '@')) {
            return '—';
        }

        [$local, $domain] = explode('@', $email, 2);
        if ($local === '') {
            return '—';
        }

        $localSegments = explode('.', $local);
        $maskedSegments = [];
        foreach ($localSegments as $index => $segment) {
            if ($segment === '') {
                continue;
            }
            $len = mb_strlen($segment);
            if ($index === 0) {
                $maskedSegments[] = $len <= 2
                    ? mb_substr($segment, 0, 1).'***'
                    : mb_substr($segment, 0, 2).'****';
            } else {
                $maskedSegments[] = $len <= 1
                    ? '*'
                    : mb_substr($segment, 0, 1).'***';
            }
        }

        if ($maskedSegments === []) {
            return '—';
        }

        $maskedLocal = implode('.', $maskedSegments);

        $domainSegments = array_values(array_filter(explode('.', $domain), fn (string $s): bool => $s !== ''));
        if (count($domainSegments) >= 2) {
            $maskedDomain = $domainSegments[0].'.**';
        } elseif (count($domainSegments) === 1) {
            $single = $domainSegments[0];
            $maskedDomain = mb_substr($single, 0, min(2, mb_strlen($single))).'****';
        } else {
            $maskedDomain = '**';
        }

        return $maskedLocal.'@'.$maskedDomain;
    }
}
