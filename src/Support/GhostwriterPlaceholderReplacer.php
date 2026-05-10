<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Support;

use Illuminate\Contracts\Auth\Authenticatable;

final class GhostwriterPlaceholderReplacer
{
    /**
     * Replace bracketed placeholders in the reply body with the user's
     * Ghostwriter signing name. The user model must `use HasGhostwriterUserData`
     * so the `ghostwriterUserData()` relation resolves.
     */
    public static function apply(string $body, Authenticatable $user): string
    {
        $signing = self::signingName($user);
        $first = self::firstNameOnly($user);

        $map = [
            '[Dein Name]' => $signing,
            '[Your Name]' => $signing,
            '[Dein Vorname]' => $first,
            '[Your First Name]' => $first,
        ];

        return str_replace(array_keys($map), array_values($map), $body);
    }

    public static function replySignature(Authenticatable $user): string
    {
        self::loadGhostwriterRelation($user);
        $raw = self::ghostwriterDataAttribute($user, 'reply_signature');
        if (! is_string($raw)) {
            return '';
        }

        return trim($raw);
    }

    public static function appendReplySignature(string $body, Authenticatable $user): string
    {
        $signature = self::replySignature($user);
        if ($signature === '') {
            return $body;
        }

        return $body."\n\n".$signature;
    }

    public static function replySignatureHtml(Authenticatable $user): string
    {
        self::loadGhostwriterRelation($user);
        $raw = self::ghostwriterDataAttribute($user, 'reply_signature_html');
        if (! is_string($raw)) {
            return '';
        }

        return trim($raw);
    }

    public static function buildHtmlReplyBody(string $textBody, Authenticatable $user): string
    {
        $signatureHtml = self::replySignatureHtml($user);
        if ($signatureHtml === '') {
            return '';
        }

        $escapedBody = nl2br(e($textBody));

        return '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222;">'
            .$escapedBody
            .'</div>'
            .'<br>'
            .$signatureHtml;
    }

    public static function signingName(Authenticatable $user): string
    {
        self::loadGhostwriterRelation($user);
        $custom = self::ghostwriterDataAttribute($user, 'signing_name');
        if (is_string($custom) && trim($custom) !== '') {
            return trim($custom);
        }

        return '[Dein Name]';
    }

    public static function firstNameOnly(Authenticatable $user): string
    {
        return self::signingName($user);
    }

    private static function loadGhostwriterRelation(Authenticatable $user): void
    {
        if (method_exists($user, 'loadMissing')) {
            $user->loadMissing('ghostwriterUserData');
        }
    }

    private static function ghostwriterDataAttribute(Authenticatable $user, string $attribute): mixed
    {
        $relation = null;
        if (method_exists($user, 'getAttribute')) {
            $relation = $user->getAttribute('ghostwriterUserData');
        } elseif (isset($user->ghostwriterUserData)) {
            $relation = $user->ghostwriterUserData;
        }

        if ($relation === null) {
            return null;
        }

        if (method_exists($relation, 'getAttribute')) {
            return $relation->getAttribute($attribute);
        }

        return $relation->{$attribute} ?? null;
    }
}
