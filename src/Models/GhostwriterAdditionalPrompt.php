<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Models;

use App\Models\User;
use Empire2\GazeGhostwriter\Database\Factories\GhostwriterAdditionalPromptFactory;
use Empire2\GazeGhostwriter\Enums\AdditionalPromptScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property AdditionalPromptScope $scope
 * @property int|null $user_id
 * @property string|null $label
 * @property string $body
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Eloquent
 */
class GhostwriterAdditionalPrompt extends Model
{
    use HasFactory;

    protected $table = 'ghostwriter_additional_prompts';

    protected $fillable = [
        'scope',
        'user_id',
        'label',
        'body',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'scope' => AdditionalPromptScope::class,
            'position' => 'integer',
        ];
    }

    protected static function newFactory(): Factory
    {
        return GhostwriterAdditionalPromptFactory::new();
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = (string) config('gaze-ghostwriter.user_model', User::class);

        return $this->belongsTo($userModel, 'user_id');
    }

    /**
     * @return Collection<int, static>
     */
    public static function globalPrompts(): Collection
    {
        return static::query()
            ->where('scope', AdditionalPromptScope::GLOBAL)
            ->orderBy('position')
            ->get();
    }

    /**
     * @return Collection<int, static>
     */
    public static function forUser(int $userId): Collection
    {
        return static::query()
            ->where('scope', AdditionalPromptScope::USER)
            ->where('user_id', $userId)
            ->orderBy('position')
            ->get();
    }

    public static function nextPosition(AdditionalPromptScope $scope, ?int $userId = null): int
    {
        $query = static::query()->where('scope', $scope);

        if ($scope === AdditionalPromptScope::USER && $userId !== null) {
            $query->where('user_id', $userId);
        }

        return ((int) $query->max('position')) + 1;
    }
}
