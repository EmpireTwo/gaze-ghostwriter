<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Models;

use Empire2\GazeGhostwriter\Enums\MailChunkRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $support_mail_message_id
 * @property MailChunkRole $role
 * @property string $content
 * @property list<float>|null $embedding
 *
 * @mixin \Eloquent
 */
class SupportMailChunk extends Model
{
    protected $table = 'ghostwriter_support_mail_chunks';

    protected $fillable = [
        'support_mail_message_id',
        'role',
        'content',
        'embedding',
    ];

    protected function casts(): array
    {
        return [
            'role' => MailChunkRole::class,
            'embedding' => 'array',
        ];
    }

    /**
     * @return BelongsTo<SupportMailMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(SupportMailMessage::class, 'support_mail_message_id');
    }
}
