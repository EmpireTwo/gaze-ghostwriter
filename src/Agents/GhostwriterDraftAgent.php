<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Agents;

use Empire2\GazeGhostwriter\Prompts\PromptResolver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

class GhostwriterDraftAgent implements Agent, Conversational, HasStructuredOutput, HasTools
{
    use Promptable;

    private readonly PromptResolver $promptResolver;

    public function __construct(
        private readonly string $localeLabel,
        ?PromptResolver $promptResolver = null,
        private readonly ?string $additionalInstructions = null,
    ) {
        $this->promptResolver = $promptResolver ?? new PromptResolver;
    }

    public function instructions(): Stringable|string
    {
        $core = $this->promptResolver->resolve('draft-system', [
            'localeLabel' => $this->localeLabel,
        ]);

        $additional = $this->additionalInstructions !== null ? trim($this->additionalInstructions) : '';

        if ($additional === '') {
            return $core;
        }

        return $core."\n\nZusätzliche Anweisungen (STRICT — vollständig einhalten):\n".$additional;
    }

    /**
     * @return list<Message>
     */
    public function messages(): iterable
    {
        return [];
    }

    public function tools(): iterable
    {
        return [];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'draft_body' => $schema->string()->required(),
            'thematische_begruendung' => $schema->string()->required(),
            'stilistische_begruendung' => $schema->string()->required(),
            'referenzierte_chunk_ids' => $schema->array()
                ->items($schema->integer())
                ->required(),
            'smart_action_tags' => $schema->array()
                ->items($schema->string())
                ->required(),
            'mentioned_entities' => $schema->array()
                ->items($schema->object([
                    'type' => $schema->string()->required(),
                    'query' => $schema->string()->required(),
                ])->withoutAdditionalProperties())
                ->required(),
            'detected_language' => $schema->string()->required(),
        ];
    }
}
