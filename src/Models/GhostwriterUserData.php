<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Models;

use Empire2\GazeGhostwriter\Database\Factories\GhostwriterUserDataFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $signing_name
 * @property string|null $reply_signature
 * @property string|null $reply_signature_html
 *
 * @mixin \Eloquent
 */
class GhostwriterUserData extends Model
{
    use HasFactory;

    protected $table = 'ghostwriter_user_data';

    protected $fillable = [
        'user_id',
        'signing_name',
        'reply_signature',
        'reply_signature_html',
    ];

    protected static function newFactory(): Factory
    {
        return GhostwriterUserDataFactory::new();
    }

    /**
     * @return BelongsTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    public function user(): BelongsTo
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $userModel */
        $userModel = (string) config('gaze-ghostwriter.user_model', \App\Models\User::class);

        return $this->belongsTo($userModel, 'user_id');
    }
}
