<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Models;

use Empire2\GazeGhostwriter\Database\Factories\SupportMailMessageFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $rfc_message_id
 * @property int|null $imap_uid
 * @property string $from_email
 * @property string|null $from_name
 * @property list<string> $to_emails
 * @property list<string>|null $cc_emails
 * @property string|null $subject
 * @property string $body_text
 * @property string|null $body_html
 * @property Carbon $received_at
 * @property bool $matches_support_address
 * @property string|null $processing_status
 * @property string $channel
 * @property int|null $client_user_id
 * @property array<string, mixed>|null $client_context
 * @property string|null $source_url
 * @property string|null $topic
 *
 * @mixin \Eloquent
 */
class SupportMailMessage extends Model
{
    use HasFactory;

    protected $table = 'ghostwriter_support_mail_messages';

    protected $fillable = [
        'rfc_message_id',
        'imap_uid',
        'from_email',
        'from_name',
        'to_emails',
        'cc_emails',
        'subject',
        'body_text',
        'body_html',
        'received_at',
        'matches_support_address',
        'processing_status',
        'channel',
        'client_user_id',
        'client_context',
        'source_url',
        'topic',
    ];

    protected function casts(): array
    {
        return [
            'to_emails' => 'array',
            'cc_emails' => 'array',
            'received_at' => 'datetime',
            'matches_support_address' => 'boolean',
            'client_context' => 'array',
            'client_user_id' => 'integer',
        ];
    }

    protected static function newFactory(): Factory
    {
        return SupportMailMessageFactory::new();
    }

    /**
     * @return HasMany<SupportMailChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(SupportMailChunk::class, 'support_mail_message_id');
    }

    /**
     * @return HasMany<SupportDraft, $this>
     */
    public function drafts(): HasMany
    {
        return $this->hasMany(SupportDraft::class, 'support_mail_message_id');
    }
}
