<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Database\Factories;

use Empire2\GazeGhostwriter\Enums\DraftStatus;
use Empire2\GazeGhostwriter\Models\SupportDraft;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupportDraft> */
class SupportDraftFactory extends Factory
{
    protected $model = SupportDraft::class;

    public function definition(): array
    {
        return [
            'support_mail_message_id' => SupportMailMessage::factory(),
            'draft_body' => fake()->paragraph(),
            'edited_body' => null,
            'rationale' => [],
            'status' => DraftStatus::PENDING_REVIEW,
            'user_rating' => null,
            'rating_comment' => null,
        ];
    }
}
