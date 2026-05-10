<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Services;

use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Empire2\GazeGhostwriter\Prompts\PromptResolver;
use Empire2\GazeGhostwriter\Support\CustomerMailReplyLanguageHint;
use Empire2\GazeGhostwriter\Support\MailReplyHistorySplitter;
use Empire2\GazeGhostwriter\Support\MailSignatureTrimmer;

final class DraftPromptComposer
{
    public function __construct(
        private readonly PromptResolver $resolver,
    ) {}

    /**
     * @param  list<array{chunk_id: int, score: float, excerpt: string}>  $snippets
     */
    public function compose(
        SupportMailMessage $message,
        array $snippets,
        bool $withholdRag,
        ?string $previousDraftBody,
    ): string {
        $snippetsBlock = $this->formatSnippetsBlock($snippets, $withholdRag);
        $regenerateSection = $this->formatRegenerateSection($previousDraftBody);
        $bareGreetingHint = $this->formatBareGreetingHint($withholdRag);

        $bodyParts = $this->splitAndCleanBody(
            (string) $message->body_text,
            (string) $message->from_name,
        );

        $locale = config('gaze-ghostwriter.locale', 'de');
        $fallbackLocaleLabel = $locale === 'de' ? 'Deutsch' : (string) $locale;
        $replyLanguageDirective = CustomerMailReplyLanguageHint::buildPromptDirective(
            (string) $message->subject,
            $bodyParts['latest'],
            $fallbackLocaleLabel,
        );

        return $this->resolver->resolve('draft-user', [
            'snippetsBlock' => $snippetsBlock,
            'subject' => (string) $message->subject,
            'fromName' => (string) $message->from_name,
            'fromEmail' => (string) $message->from_email,
            'bodyTextLatest' => $bodyParts['latest'],
            'replyLanguageDirective' => $replyLanguageDirective,
            'regenerateSection' => $regenerateSection,
            'bareGreetingHint' => $bareGreetingHint,
        ]);
    }

    /**
     * @param  list<array{chunk_id: int, score: float, excerpt: string}>  $snippets
     */
    public function formatSnippetsBlock(array $snippets, bool $withholdRag): string
    {
        if ($withholdRag) {
            return 'Referenz-Snippets: absichtlich nicht einbezogen (Kunden-Text ist nur eine kurze Gruß-/Kontaktmeldung ohne erkennbare Frage). Übernimm keine Themen oder Formulierungen aus früheren Mails — nutze ausschließlich Betreff und Inhalt dieser Mail.';
        }

        if ($snippets === []) {
            return 'Referenz-Snippets: (keine passenden historischen Einträge im Index)';
        }

        $lines = ['Referenz-Snippets (je Zeile: ID | Ähnlichkeit 0–1 | Auszug):'];
        foreach ($snippets as $row) {
            $lines[] = sprintf(
                '%d | %.4f | %s',
                $row['chunk_id'],
                $row['score'],
                str_replace(["\r\n", "\n"], ' ', $row['excerpt'])
            );
        }

        return implode("\n", $lines);
    }

    public function formatRegenerateSection(?string $previousDraftBody): string
    {
        $previous = $previousDraftBody !== null ? trim($previousDraftBody) : '';
        if ($previous === '') {
            return '';
        }

        return <<<TXT


WICHTIG bei Regenerierung — Sprache: Der frühere Entwurf unten kann in einer anderen Sprache stehen als die Kunden-Mail in [ORIGINAL_EMAIL] weiter oben. Die Antwortsprache von draft_body richtet sich ausschließlich nach der Kunden-Mail (Betreff und Text) und der Pflichtzeile „Pflicht — Antwortsprache" direkt unter [ORIGINAL_EMAIL] — nicht nach dem früheren Entwurf. Wenn der frühere Entwurf die falsche Sprache nutzte, verwerfe dessen Sprache vollständig.

Der folgende Text war ein früherer Entwurf, der ersetzt werden soll. Verbessere ihn klar und merklich oder schreibe eine bessere Alternative zur gleichen Kunden-Mail. Übernimm schwache oder unpassende Formulierungen nicht ohne Anpassung. Ist die Kunden-Mail sehr knapp, darf die bessere Alternative deutlich kürzer sein als der frühere Entwurf.

Früherer Entwurf:
---
{$previous}
---
TXT;
    }

    /**
     * @return array{latest: string}
     */
    public function splitAndCleanBody(string $bodyText, string $senderName = ''): array
    {
        $parts = MailReplyHistorySplitter::split($bodyText);

        $latest = MailSignatureTrimmer::trimForGithubIssue($parts['latest']);
        $latest = $this->trimSenderNameSignature($latest, $senderName);

        return [
            'latest' => $latest,
        ];
    }

    private function trimSenderNameSignature(string $text, string $senderName): string
    {
        $senderName = trim($senderName);
        if ($senderName === '' || mb_strlen($senderName) < 3) {
            return $text;
        }

        $escaped = preg_quote($senderName, '/');
        $pattern = '/\n\s*\n\s*(?:\*{0,2}|_{0,2})'.$escaped.'(?:\*{0,2}|_{0,2})\s*\n/iu';

        if (preg_match($pattern, $text, $matches, PREG_OFFSET_CAPTURE) === 1) {
            $pos = (int) $matches[0][1];
            if ($pos >= 12) {
                return rtrim(substr($text, 0, $pos));
            }
        }

        return $text;
    }

    public function formatBareGreetingHint(bool $withholdRag): string
    {
        if (! $withholdRag) {
            return '';
        }

        return <<<'TXT'


Hinweis für den Entwurf: Die Kunden-Mail ist nur eine kurze Gruß-/Kontaktmeldung ohne konkrete Frage. Antworte in wenigen Sätzen, freundlich und neutral. Keine Vorschläge zu Terminen, Zusammenarbeit, „Ideen besprechen" oder Projekten, wenn der Kunde nichts dergleichen geschrieben hat.
TXT;
    }
}
