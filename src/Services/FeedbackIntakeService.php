<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Services;

use Empire2\GazeGhostwriter\DTO\FeedbackIntakeDto;
use Empire2\GazeGhostwriter\Jobs\GenerateDraftForFeedbackJob;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Str;

final class FeedbackIntakeService
{
    public const ANONYMOUS_SENDER_SENTINEL = 'anonymous@web.local';

    public function __construct(private ConfigRepository $config) {}

    public function intake(
        FeedbackIntakeDto $dto,
        ?Authenticatable $user,
        ?string $sourceUrl,
    ): SupportMailMessage {
        $supportAddresses = (array) $this->config->get('gaze-ghostwriter.support_addresses', []);
        $primarySupport = $supportAddresses[0] ?? 'support@unknown.local';

        // Authenticatable does not declare email/name; rely on duck-typing on the host User model.
        $fromEmail = $user !== null
            ? (string) ($user->email ?? '')
            : trim($dto->guestEmail);
        if ($fromEmail === '') {
            $fromEmail = self::ANONYMOUS_SENDER_SENTINEL;
        }

        $fromName = $user !== null
            ? (string) ($user->name ?? '')
            : trim($dto->guestName);
        $fromName = $fromName !== '' ? $fromName : null;

        $subject = trim($dto->subject);
        if ($subject === '') {
            $subject = 'Web feedback'.($fromName !== null ? ' from '.$fromName : '');
        }

        $clientContext = $user !== null
            ? [
                'id' => $user->getAuthIdentifier(),
                'email' => $user->email ?? null,
                'name' => $user->name ?? null,
            ]
            : null;

        $body = $this->buildContextBlock($clientContext, $sourceUrl, $dto->topic)
            ."\n\n---\n\n"
            .$dto->message;

        $host = parse_url((string) $this->config->get('app.url'), PHP_URL_HOST) ?: 'local';

        $message = SupportMailMessage::create([
            'channel' => 'web',
            'rfc_message_id' => 'web-'.Str::uuid()->toString().'@'.$host,
            'imap_uid' => null,
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'to_emails' => [$primarySupport],
            'cc_emails' => null,
            'subject' => $subject,
            'body_text' => $body,
            'body_html' => null,
            'received_at' => now(),
            'matches_support_address' => true,
            'client_user_id' => $user?->getAuthIdentifier(),
            'client_context' => $clientContext,
            'source_url' => $sourceUrl,
            'topic' => $dto->topic,
        ]);

        GenerateDraftForFeedbackJob::dispatch($message->id);

        return $message;
    }

    /**
     * @param  array<string, mixed>|null  $clientContext
     */
    private function buildContextBlock(?array $clientContext, ?string $sourceUrl, ?string $topic): string
    {
        $lines = ['[Web feedback]'];
        $lines[] = 'Submitted from: '.($sourceUrl ?? '—');
        $lines[] = 'Topic: '.($topic ?? '—');

        if ($clientContext !== null) {
            $email = (string) ($clientContext['email'] ?? '');
            $id = $clientContext['id'] ?? null;
            $lines[] = 'Client: '.($email !== '' ? $email : 'guest').($id !== null ? ' (id: '.$id.')' : '');
        } else {
            $lines[] = 'Client: guest';
        }

        return implode("\n", $lines);
    }
}
