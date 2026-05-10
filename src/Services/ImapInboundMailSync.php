<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Services;

use Carbon\Carbon;
use Empire2\GazeGhostwriter\Enums\MailChunkRole;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Empire2\GazeGhostwriter\Support\ConversationPartnerCache;
use Empire2\GazeGhostwriter\Support\ConversationPartnerFilter;
use Empire2\GazeGhostwriter\Support\HtmlToPlainText;
use Empire2\GazeGhostwriter\Support\ImapFolderResolver;
use Empire2\GazeGhostwriter\Support\SupportAddressMatcher;
use Illuminate\Support\Facades\Log;
use Throwable;
use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\Attribute;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\IMAP;
use Webklex\PHPIMAP\Message;

class ImapInboundMailSync
{
    public function __construct(
        private readonly ClientManager $clientManager,
        private readonly SupportAddressMatcher $addressMatcher,
        private readonly ChunkEmbeddingService $chunkEmbeddingService,
    ) {}

    public function sync(): int
    {
        $imported = 0;
        $partner = ConversationPartnerCache::effective();

        try {
            $client = $this->clientManager->make($this->webklexAccountConfig());
            $client->connect();

            $since = Carbon::now()->subDays((int) config('gaze-ghostwriter.imap.lookback_days', 30));
            $limit = (int) config('gaze-ghostwriter.imap.fetch_limit', 75);

            foreach ($this->folderNamesToSync() as $folderLabel) {
                $folder = ImapFolderResolver::resolve($client, $folderLabel);
                if ($folder === null) {
                    Log::warning('Ghostwriter IMAP folder skipped', [
                        'folder' => $folderLabel,
                        'error' => 'Ordner nicht aufgelöst (Trennzeichen . vs / — siehe ImapFolderResolver).',
                    ]);

                    continue;
                }

                $query = $folder->query()->since($since)->setFetchOrder('desc')->limit($limit);

                if ($partner !== null && $partner !== '') {
                    $query->where('CUSTOM '.$this->imapPartnerTripleOrCriteria($partner));
                }

                if ($this->fetchWithoutSettingSeen()) {
                    $query->leaveUnread();
                }

                $messages = $query->get();

                foreach ($messages as $message) {
                    if ($this->persistFromImapMessage($message, $partner)) {
                        $imported++;
                    }
                }
            }

            $client->disconnect();
        } catch (Throwable $e) {
            Log::error('Ghostwriter IMAP sync failed', [
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $imported;
    }

    /**
     * @return list<string>
     */
    public function configuredFolderNames(): array
    {
        return $this->folderNamesToSync();
    }

    /**
     * @return array<string, mixed>
     */
    public function webklexAccountConfig(): array
    {
        /** @var mixed $defaults */
        $defaults = config('imap.accounts.default', []);
        if (! is_array($defaults)) {
            $defaults = [];
        }

        $peek = $this->fetchWithoutSettingSeen();

        return array_replace_recursive($defaults, [
            'host' => config('gaze-ghostwriter.imap.host'),
            'port' => (int) config('gaze-ghostwriter.imap.port'),
            'encryption' => config('gaze-ghostwriter.imap.encryption'),
            'validate_cert' => (bool) config('gaze-ghostwriter.imap.validate_cert'),
            'username' => config('gaze-ghostwriter.imap.username'),
            'password' => config('gaze-ghostwriter.imap.password'),
            'protocol' => 'imap',
            'timeout' => (int) config('gaze-ghostwriter.imap.timeout', 45),
            'options' => [
                'fetch' => $peek ? IMAP::FT_PEEK : 0,
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    private function folderNamesToSync(): array
    {
        $primary = (string) config('gaze-ghostwriter.imap.folder', 'INBOX');
        /** @var mixed $extras */
        $extras = config('gaze-ghostwriter.imap.extra_folders', []);
        if (! is_array($extras)) {
            $extras = [];
        }

        /** @var list<string> $extraList */
        $extraList = array_values(array_filter(array_map(
            static fn (mixed $v): string => is_string($v) ? trim($v) : '',
            $extras
        )));

        return array_values(array_unique(array_filter(array_merge([$primary], $extraList))));
    }

    private function fetchWithoutSettingSeen(): bool
    {
        return (bool) config('gaze-ghostwriter.imap.fetch_without_setting_seen', true);
    }

    private function persistFromImapMessage(Message $message, ?string $partnerNormalized): bool
    {
        $rfcId = $this->resolveRfcMessageId($message);

        if (SupportMailMessage::query()->where('rfc_message_id', $rfcId)->exists()) {
            return false;
        }

        $toEmails = $this->emailsFromAttribute($message->getTo());
        $ccEmails = $this->emailsFromAttribute($message->getCc());
        $from = $this->firstAddress($message->getFrom());

        if ($partnerNormalized !== null && ! ConversationPartnerFilter::touchesPartner(
            $from['email'],
            $toEmails,
            $ccEmails,
            $partnerNormalized,
        )) {
            return false;
        }

        $allRecipients = array_merge($toEmails, $ccEmails);

        $supportNeedles = $this->addressMatcher->normalizedAddressesFromConfig();
        $matchesSupport = $this->addressMatcher->matches(
            $supportNeedles,
            $this->addressMatcher->normalizeList($allRecipients)
        );

        $htmlBody = $message->getHTMLBody();
        $bodyText = $message->getTextBody();
        if ($bodyText === '') {
            $bodyText = $htmlBody !== '' ? HtmlToPlainText::convert($htmlBody) : '';
        }

        try {
            $receivedAt = $message->getDate()->toDate();
        } catch (Throwable) {
            $receivedAt = now();
        }

        $record = SupportMailMessage::query()->create([
            'rfc_message_id' => $rfcId,
            'imap_uid' => $message->getUid(),
            'from_email' => $from['email'],
            'from_name' => $from['name'],
            'to_emails' => $toEmails,
            'cc_emails' => $ccEmails === [] ? null : $ccEmails,
            'subject' => (string) $message->getSubject(),
            'body_text' => $bodyText,
            'body_html' => $htmlBody !== '' ? $htmlBody : null,
            'received_at' => $receivedAt,
            'matches_support_address' => $matchesSupport,
        ]);

        $this->maybeCreateChunkForMessage(
            $record,
            $from['email'],
            $toEmails,
            $ccEmails,
            $bodyText,
            $matchesSupport,
            $partnerNormalized,
            $supportNeedles,
        );

        return true;
    }

    /**
     * @param  list<string>  $toEmails
     * @param  list<string>  $ccEmails
     * @param  list<string>  $supportNeedles
     */
    private function maybeCreateChunkForMessage(
        SupportMailMessage $record,
        string $fromEmail,
        array $toEmails,
        array $ccEmails,
        string $bodyText,
        bool $matchesSupport,
        ?string $partnerNormalized,
        array $supportNeedles,
    ): void {
        if ($bodyText === '') {
            return;
        }

        $fromNorm = ConversationPartnerFilter::normalizeEmail($fromEmail);

        $inboundBecausePartner = $partnerNormalized !== null && $fromNorm === $partnerNormalized;
        $shouldInboundChunk = $matchesSupport || $inboundBecausePartner;

        $shouldOutboundChunk = $partnerNormalized !== null
            && ConversationPartnerFilter::isOutboundFromSupportToPartner(
                $fromEmail,
                $toEmails,
                $ccEmails,
                $supportNeedles,
                $partnerNormalized,
            );

        if ($shouldInboundChunk) {
            $chunk = $record->chunks()->create([
                'role' => MailChunkRole::INBOUND,
                'content' => $bodyText,
                'embedding' => null,
            ]);
            $this->chunkEmbeddingService->embedChunk($chunk);

            return;
        }

        if ($shouldOutboundChunk) {
            $chunk = $record->chunks()->create([
                'role' => MailChunkRole::OUTBOUND,
                'content' => $bodyText,
                'embedding' => null,
            ]);
            $this->chunkEmbeddingService->embedChunk($chunk);
        }
    }

    private function resolveRfcMessageId(Message $message): string
    {
        $id = (string) $message->getMessageId();

        if ($id !== '') {
            return $id;
        }

        return 'ghostwriter-synthetic-'.hash('sha256', $message->getUid().'|'.$message->getFolderPath().'|'.$message->getDate());
    }

    /**
     * @return list<string>
     */
    private function emailsFromAttribute(Attribute $attribute): array
    {
        $emails = [];
        foreach ($attribute->toArray() as $value) {
            if ($value instanceof Address && $value->mail !== '') {
                $emails[] = $value->mail;
            }
        }

        return $emails;
    }

    /**
     * @return array{email: string, name: ?string}
     */
    private function firstAddress(Attribute $attribute): array
    {
        $first = $attribute->first();
        if ($first instanceof Address) {
            return [
                'email' => $first->mail !== '' ? $first->mail : 'unknown@invalid',
                'name' => $first->personal !== '' ? $first->personal : null,
            ];
        }

        return ['email' => 'unknown@invalid', 'name' => null];
    }

    /**
     * IMAP SEARCH fragment (without "CUSTOM "): triple OR via two nested binary ORs.
     */
    private function imapPartnerTripleOrCriteria(string $normalizedPartnerEmail): string
    {
        $quoted = self::imapQuotedSearchAddress($normalizedPartnerEmail);

        return "OR OR FROM {$quoted} TO {$quoted} CC {$quoted}";
    }

    private static function imapQuotedSearchAddress(string $email): string
    {
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], trim($email));

        return '"'.$escaped.'"';
    }
}
