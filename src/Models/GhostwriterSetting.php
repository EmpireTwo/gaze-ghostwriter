<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $key
 * @property string|null $value
 *
 * @mixin \Eloquent
 */
class GhostwriterSetting extends Model
{
    protected $table = 'ghostwriter_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

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
