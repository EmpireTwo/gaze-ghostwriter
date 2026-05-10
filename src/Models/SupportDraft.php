<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Models;

use App\Models\User;
use Empire2\GazeGhostwriter\Database\Factories\SupportDraftFactory;
use Empire2\GazeGhostwriter\Enums\DraftStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $support_mail_message_id
 * @property string $draft_body
 * @property string|null $edited_body
 * @property array<string, mixed> $rationale
 * @property DraftStatus $status
 * @property int|null $user_rating
 * @property string|null $rating_comment
 * @property Carbon|null $rated_at
 * @property int|null $rated_by_user_id
 * @property Carbon|null $sent_at
 * @property string|null $sent_message_id
 * @property int|null $sent_by_user_id
 * @property string|null $github_issue_url
 * @property list<string>|null $smart_action_tags
 * @property list<array{type: string, query: string}>|null $mentioned_entities
 * @property string|null $detected_language
 * @property string|null $mail_translation
 * @property string|null $draft_translation
 * @property string|null $edited_draft_translation
 * @property list<array{stage: string, argv: list<string>, stdin_preview: string, stdin_bytes: int, duration_ms: int}>|null $gaze_invocations
 *
 * @mixin \Eloquent
 */
class SupportDraft extends Model
{
    use HasFactory;

    protected $table = 'ghostwriter_support_drafts';

    protected $fillable = [
        'support_mail_message_id',
        'draft_body',
        'edited_body',
        'rationale',
        'status',
        'user_rating',
        'rating_comment',
        'rated_at',
        'rated_by_user_id',
        'sent_at',
        'sent_message_id',
        'sent_by_user_id',
        'github_issue_url',
        'smart_action_tags',
        'mentioned_entities',
        'detected_language',
        'mail_translation',
        'draft_translation',
        'edited_draft_translation',
        'gaze_warnings',
        'clean_prompt',
        'llm_raw_response',
        'gaze_detections',
        'gaze_duration_ms',
        'gaze_ran_at',
        'gaze_invocations',
    ];

    protected function casts(): array
    {
        return [
            'rationale' => 'array',
            'status' => DraftStatus::class,
            'rated_at' => 'datetime',
            'sent_at' => 'datetime',
            'smart_action_tags' => 'array',
            'mentioned_entities' => 'array',
            'gaze_warnings' => 'array',
            'llm_raw_response' => 'array',
            'gaze_detections' => 'integer',
            'gaze_duration_ms' => 'integer',
            'gaze_ran_at' => 'datetime',
            'gaze_invocations' => 'array',
        ];
    }

    protected static function newFactory(): Factory
    {
        return SupportDraftFactory::new();
    }

    /**
     * @return BelongsTo<SupportMailMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(SupportMailMessage::class, 'support_mail_message_id');
    }

    /**
     * Belongs-to relation against the host's User model. The class is resolved
     * at runtime via `config('gaze-ghostwriter.user_model')` so the package
     * does not depend on a specific User class.
     *
     * @return BelongsTo<Model, $this>
     */
    public function ratedByUser(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = (string) config('gaze-ghostwriter.user_model', User::class);

        return $this->belongsTo($userModel, 'rated_by_user_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function sentByUser(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = (string) config('gaze-ghostwriter.user_model', User::class);

        return $this->belongsTo($userModel, 'sent_by_user_id');
    }

    /**
     * Text used at send time: manual edit if set, otherwise the AI draft.
     */
    public function resolvedReplyBody(): string
    {
        $edited = $this->edited_body;
        if (is_string($edited) && trim($edited) !== '') {
            return $edited;
        }

        return $this->draft_body;
    }

    public function hasEditedBody(): bool
    {
        return is_string($this->edited_body) && trim($this->edited_body) !== '';
    }

    public function needsTranslation(): bool
    {
        return $this->detected_language !== null && $this->detected_language !== 'de';
    }

    public function translationsReady(): bool
    {
        return $this->needsTranslation()
            && $this->mail_translation !== null
            && $this->draft_translation !== null;
    }

    public function resolvedDraftTranslation(): ?string
    {
        if ($this->draft_translation === null) {
            return null;
        }

        return $this->hasEditedDraftTranslation()
            ? $this->edited_draft_translation
            : $this->draft_translation;
    }

    public function hasEditedDraftTranslation(): bool
    {
        return is_string($this->edited_draft_translation) && trim($this->edited_draft_translation) !== '';
    }
}
