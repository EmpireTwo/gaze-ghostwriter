<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\DTO;

final readonly class FeedbackIntakeDto
{
    public function __construct(
        public string $message,
        public string $subject,
        public string $guestEmail,
        public string $guestName,
        public ?string $topic,
    ) {}
}
