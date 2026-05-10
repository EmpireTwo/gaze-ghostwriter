<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Ai\Contracts;

use Empire2\GazeGhostwriter\Ai\DTO\GuardedAgentResponse;
use Laravel\Ai\Contracts\Agent;

interface GuardedAgentRunnerContract
{
    /**
     * @param  array{provider?: string, model?: string}  $options
     */
    public function run(
        Agent $agent,
        string $message,
        array $options = [],
    ): GuardedAgentResponse;
}
