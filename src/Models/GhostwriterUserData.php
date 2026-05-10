<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Models;

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
    protected $table = 'ghostwriter_user_data';

    protected $fillable = [
        'user_id',
        'signing_name',
        'reply_signature',
        'reply_signature_html',
    ];

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
