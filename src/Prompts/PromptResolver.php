<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Prompts;

use InvalidArgumentException;

final class PromptResolver
{
    private readonly string $basePath;

    public function __construct(?string $overridePath = null)
    {
        $this->basePath = $overridePath
            ?? $this->configuredPath()
            ?? __DIR__;
    }

    /**
     * @param  array<string, string>  $variables
     */
    public function resolve(string $name, array $variables = []): string
    {
        $path = $this->basePath.DIRECTORY_SEPARATOR.$name.'.php';

        if (! file_exists($path)) {
            throw new InvalidArgumentException("Prompt file not found: {$path}");
        }

        $template = require $path;

        if (! is_string($template)) {
            throw new InvalidArgumentException("Prompt file must return a string: {$path}");
        }

        if ($variables === []) {
            return $template;
        }

        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements["{{ {$key} }}"] = $value;
        }

        return strtr($template, $replacements);
    }

    private function configuredPath(): ?string
    {
        if (! function_exists('config')) {
            return null;
        }

        $path = config('gaze-ghostwriter.prompts.path');

        return is_string($path) && $path !== '' ? $path : null;
    }
}
