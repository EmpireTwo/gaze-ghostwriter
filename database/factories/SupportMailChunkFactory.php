<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Database\Factories;

use Empire2\GazeGhostwriter\Enums\MailChunkRole;
use Empire2\GazeGhostwriter\Models\SupportMailChunk;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupportMailChunk> */
class SupportMailChunkFactory extends Factory
{
    protected $model = SupportMailChunk::class;

    public function definition(): array
    {
        return [
            'support_mail_message_id' => SupportMailMessage::factory(),
            'role' => MailChunkRole::INBOUND,
            'content' => fake()->paragraph(),
            'embedding' => null,
        ];
    }
}
