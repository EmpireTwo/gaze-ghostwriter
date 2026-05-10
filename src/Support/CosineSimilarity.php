<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Support;

final class CosineSimilarity
{
    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    public static function score(array $a, array $b): float
    {
        if ($a === [] || $b === [] || count($a) !== count($b)) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $va) {
            $vb = $b[$i];
            $dot += $va * $vb;
            $normA += $va * $va;
            $normB += $vb * $vb;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
