<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Models;

use Empire2\GazeGhostwriter\Database\Factories\GhostwriterSmartActionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $marker
 * @property string $label
 * @property string $prompt_hint
 * @property string $route_template
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Eloquent
 */
class GhostwriterSmartAction extends Model
{
    use HasFactory;

    protected $table = 'ghostwriter_smart_actions';

    protected $fillable = [
        'marker',
        'label',
        'prompt_hint',
        'route_template',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): Factory
    {
        return GhostwriterSmartActionFactory::new();
    }

    /**
     * @return Collection<int, static>
     */
    public static function allActive(): Collection
    {
        return static::query()->where('is_active', true)->orderBy('marker')->get();
    }

    public static function buildPromptInstructions(): ?string
    {
        $actions = static::allActive();

        if ($actions->isEmpty()) {
            return null;
        }

        $lines = ['Kontextanalyse — Smart Actions:'];
        $lines[] = 'Prüfe die Kundenmail auf folgende Themen und füge die passenden Tags in das Feld smart_action_tags ein:';

        foreach ($actions as $action) {
            $lines[] = "- {$action->prompt_hint}: \"{$action->marker}\"";
        }

        $lines[] = 'Füge nur Tags ein, die klar zum Inhalt passen. Keine Vermutungen.';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, string|int>  $replacements
     */
    public function resolveRoute(array $replacements): string
    {
        $route = $this->route_template;

        foreach ($replacements as $placeholder => $value) {
            $route = str_replace('{'.$placeholder.'}', (string) $value, $route);
        }

        return $route;
    }
}
