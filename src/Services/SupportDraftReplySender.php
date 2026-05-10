<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Services;

use App\Models\User;
use Empire2\GazeGhostwriter\Enums\DraftStatus;
use Empire2\GazeGhostwriter\Models\SupportDraft;
use Empire2\GazeGhostwriter\Support\GhostwriterPlaceholderReplacer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

final class SupportDraftReplySender
{
    public function isConfigured(): bool
    {
        $host = trim((string) config('gaze-ghostwriter.smtp.host', ''));
        $from = trim((string) config('gaze-ghostwriter.reply.from_address', ''));

        return $host !== '' && $from !== '';
    }

    /**
     * @throws SupportDraftReplySendException
     */
    public function send(SupportDraft $draft, int $sentByUserId): void
    {
        if (! $this->isConfigured()) {
            throw SupportDraftReplySendException::notConfigured();
        }

        if (! in_array($draft->status, [DraftStatus::PENDING_REVIEW, DraftStatus::ACCEPTED], true)) {
            throw SupportDraftReplySendException::invalidStatus();
        }

        if ($draft->sent_at !== null) {
            throw SupportDraftReplySendException::alreadySent();
        }

        $draft->loadMissing('message');
        $message = $draft->message;

        $toEmail = trim($message->from_email);
        if ($toEmail === '') {
            throw SupportDraftReplySendException::missingRecipient();
        }

        $fromAddress = trim((string) config('gaze-ghostwriter.reply.from_address'));
        $fromName = trim((string) config('gaze-ghostwriter.reply.from_name'));

        $sender = $this->resolveSender($sentByUserId);

        $replyText = GhostwriterPlaceholderReplacer::apply($draft->resolvedReplyBody(), $sender);
        $body = GhostwriterPlaceholderReplacer::appendReplySignature($replyText, $sender);
        if (trim($body) === '') {
            throw SupportDraftReplySendException::emptyBody();
        }

        $outboundMessageIdInner = $this->generateOutboundMessageIdInner($fromAddress);
        $inReplyToInner = $this->stripAngleBrackets($message->rfc_message_id);
        $subject = $this->buildReplySubject($message->subject);

        $email = (new Email)
            ->from($fromName !== '' ? new Address($fromAddress, $fromName) : new Address($fromAddress))
            ->to($toEmail)
            ->subject($subject)
            ->text($body, 'utf-8')
            ->replyTo($fromAddress);

        $htmlBody = GhostwriterPlaceholderReplacer::buildHtmlReplyBody($replyText, $sender);
        if ($htmlBody !== '') {
            $email->html($htmlBody, 'utf-8');
        }

        $email->getHeaders()->addIdHeader('Message-ID', $outboundMessageIdInner);

        if ($inReplyToInner !== '') {
            $email->getHeaders()->addIdHeader('In-Reply-To', $inReplyToInner);
            $email->getHeaders()->addIdHeader('References', $inReplyToInner);
        }

        $mailer = new Mailer(GhostwriterSmtpTransportFactory::make());

        try {
            $mailer->send($email);
        } catch (Throwable $e) {
            Log::error('Ghostwriter support reply SMTP send failed', [
                'draft_id' => $draft->id,
                'exception' => $e->getMessage(),
            ]);

            throw SupportDraftReplySendException::transportFailed($e->getMessage());
        }

        $draft->update([
            'status' => DraftStatus::SENT,
            'sent_at' => now(),
            'sent_message_id' => $outboundMessageIdInner,
            'sent_by_user_id' => $sentByUserId,
        ]);
    }

    private function resolveSender(int $userId): Authenticatable
    {
        /** @var class-string<Model> $userModel */
        $userModel = (string) config('gaze-ghostwriter.user_model', User::class);

        /** @var Model|Authenticatable $sender */
        $sender = $userModel::query()->findOrFail($userId);

        if (! $sender instanceof Authenticatable) {
            throw new \RuntimeException('Configured user_model must implement Authenticatable.');
        }

        if (method_exists($sender, 'loadMissing')) {
            $sender->loadMissing('ghostwriterUserData');
        }

        return $sender;
    }

    private function buildReplySubject(?string $original): string
    {
        $s = trim((string) $original);
        if ($s === '') {
            return 'Re: ';
        }

        if (preg_match('/^\s*Re:\s+/i', $s) === 1) {
            return $s;
        }

        return 'Re: '.$s;
    }

    private function stripAngleBrackets(string $messageId): string
    {
        return trim($messageId, " \t\n\r\0\x0B<>");
    }

    private function generateOutboundMessageIdInner(string $fromEmail): string
    {
        $domain = 'localhost';
        if (preg_match('/@([^>\s]+)/', $fromEmail, $matches) === 1) {
            $domain = $matches[1];
        }

        $token = bin2hex(random_bytes(16));

        return 'gw.'.$token.'@'.$domain;
    }
}
