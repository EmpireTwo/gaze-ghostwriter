<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Models;

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
 *
 * @mixin \Eloquent
 */
class SupportMailMessage extends Model
{
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
    ];

    protected function casts(): array
    {
        return [
            'to_emails' => 'array',
            'cc_emails' => 'array',
            'received_at' => 'datetime',
            'matches_support_address' => 'boolean',
        ];
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
