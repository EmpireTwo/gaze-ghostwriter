<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Database\Factories;

use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupportMailMessage> */
class SupportMailMessageFactory extends Factory
{
    protected $model = SupportMailMessage::class;

    public function definition(): array
    {
        return [
            'rfc_message_id' => '<'.fake()->unique()->uuid().'@example.test>',
            'imap_uid' => fake()->unique()->numberBetween(1, 1_000_000),
            'channel' => 'smtp',
            'from_email' => fake()->unique()->safeEmail(),
            'from_name' => fake()->name(),
            'to_emails' => ['support@example.test'],
            'cc_emails' => null,
            'subject' => fake()->sentence(),
            'body_text' => fake()->paragraph(),
            'body_html' => null,
            'received_at' => now(),
            'matches_support_address' => true,
            'processing_status' => null,
        ];
    }

    public function web(): self
    {
        return $this->state(fn (): array => [
            'channel' => 'web',
            'rfc_message_id' => 'web-'.fake()->unique()->uuid().'@example.test',
            'imap_uid' => null,
        ]);
    }
}
