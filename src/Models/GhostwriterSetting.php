<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Models;

use Empire2\GazeGhostwriter\Database\Factories\GhostwriterSettingFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $key
 * @property string|null $value
 *
 * @mixin \Eloquent
 */
class GhostwriterSetting extends Model
{
    use HasFactory;

    protected $table = 'ghostwriter_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    protected static function newFactory(): Factory
    {
        return GhostwriterSettingFactory::new();
    }

    public static function getValue(string $key): ?string
    {
        $row = static::query()->find($key);

        return $row?->value;
    }

    public static function setValue(string $key, ?string $value): void
    {
        if ($value === null || trim($value) === '') {
            static::query()->where('key', $key)->delete();

            return;
        }

        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => trim($value)]
        );
    }
}
