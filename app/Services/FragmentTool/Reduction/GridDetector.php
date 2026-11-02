<?php

namespace App\Services\FragmentTool\Reduction;

use App\Services\FragmentTool\PixelBuffer;

/**
 * Detects the size of the "big pixels" of an already pixelated image by analysing
 * colour transitions along rows and columns.
 */
class GridDetector
{
    /** How much stronger than average the transitions on the grid lines must be. */
    private const MIN_CONTRAST = 1.8;

    /** A period is kept if it scores at least this share of the best one (prefers the smallest). */
    private const NEAR_BEST = 0.85;

    /** With an expected size, periods are searched between expected / 2.5 and expected × 1.6. */
    private const EXPECTED_RANGE = [2.5, 1.6];

    /**
     * With an expected number of cells (AI projection), the search favours periods close to
     * the expected one: with few edges, a multiple of the true period can otherwise score best.
     *
     * @return array{x: array{period: float, offset: int}, y: array{period: float, offset: int}}|null
     */
    public function detect(PixelBuffer $pixels, ?int $expectedCols = null, ?int $expectedRows = null): ?array
    {
        $x = $this->detectAxis($this->profile($pixels, horizontal: true), $expectedCols ? $pixels->width / $expectedCols : null);
        $y = $this->detectAxis($this->profile($pixels, horizontal: false), $expectedRows ? $pixels->height / $expectedRows : null);

        if ($x === null || $y === null) {
            return null;
        }

        return ['x' => $x, 'y' => $y];
    }

    /**
     * One colour per detected big pixel (mean of its inner half, robust to JPEG noise).
     *
     * @param  array{x: array{period: float, offset: int}, y: array{period: float, offset: int}}  $grid
     */
    public function sample(PixelBuffer $pixels, array $grid): PixelBuffer
    {
        $columns = $this->centres($grid['x'], $pixels->width);
        $lines = $this->centres($grid['y'], $pixels->height);
        $halfX = max(0, (int) floor($grid['x']['period'] / 4));
        $halfY = max(0, (int) floor($grid['y']['period'] / 4));
        $r = $g = $b = $a = [];

        foreach ($lines as $cy) {
            foreach ($columns as $cx) {
                $indexes = [];
                for ($y = max(0, $cy - $halfY); $y <= min($pixels->height - 1, $cy + $halfY); $y++) {
                    for ($x = max(0, $cx - $halfX); $x <= min($pixels->width - 1, $cx + $halfX); $x++) {
                        $indexes[] = $pixels->index($x, $y);
                    }
                }
                [$r[], $g[], $b[]] = MeanReducer::average($pixels, $indexes);
                $a[] = 255;
            }
        }

        return new PixelBuffer(count($columns), count($lines), $r, $g, $b, $a);
    }

    /**
     * @param  array{period: float, offset: int}  $axis
     * @return list<int>
     */
    private function centres(array $axis, int $length): array
    {
        $centres = [];
        $start = $axis['offset'] > $axis['period'] / 2 ? $axis['offset'] - $axis['period'] : $axis['offset'];

        for ($k = 0; ; $k++) {
            $centre = (int) floor($start + $axis['period'] * ($k + 0.5));
            if ($centre >= $length) {
                break;
            }
            if ($centre >= 0) {
                $centres[] = $centre;
            }
        }

        return $centres;
    }

    /**
     * Transition strength between position i-1 and i, summed across the other axis.
     *
     * @return list<float>
     */
    private function profile(PixelBuffer $pixels, bool $horizontal): array
    {
        $length = $horizontal ? $pixels->width : $pixels->height;
        $across = $horizontal ? $pixels->height : $pixels->width;
        $profile = [0.0];

        for ($i = 1; $i < $length; $i++) {
            $sum = 0;
            for ($j = 0; $j < $across; $j++) {
                $p = $horizontal ? $pixels->index($i, $j) : $pixels->index($j, $i);
                $q = $horizontal ? $pixels->index($i - 1, $j) : $pixels->index($j, $i - 1);
                $sum += abs($pixels->r[$p] - $pixels->r[$q]) + abs($pixels->g[$p] - $pixels->g[$q]) + abs($pixels->b[$p] - $pixels->b[$q]);
            }
            $profile[] = $sum / $across;
        }

        // Anti-aliased edges spread over two pixels: keep the strongest of each pair.
        $widened = [];
        foreach ($profile as $i => $value) {
            $widened[] = max($value, $profile[$i + 1] ?? 0.0);
        }

        return $widened;
    }

    /**
     * @param  list<float>  $profile
     * @return array{period: float, offset: int}|null
     */
    private function detectAxis(array $profile, ?float $expectedPeriod = null): ?array
    {
        $length = count($profile);
        $mean = array_sum($profile) / max(1, $length);

        if ($mean <= 0.0 || $length < 8) {
            return null;
        }

        $minPeriod = 2;
        $maxPeriod = intdiv($length, 4);
        if ($expectedPeriod !== null) {
            $minPeriod = max($minPeriod, (int) floor($expectedPeriod / self::EXPECTED_RANGE[0]));
            $maxPeriod = min($maxPeriod, (int) ceil($expectedPeriod * self::EXPECTED_RANGE[1]));
        }

        $scores = [];
        for ($period = $minPeriod; $period <= $maxPeriod; $period++) {
            $scores[$period] = $this->bestOffset($profile, $period, $mean);
        }

        if ($scores === [] || max(array_column($scores, 'score')) < self::MIN_CONTRAST) {
            return null;
        }

        $period = $expectedPeriod === null
            ? $this->smallestNearBest($scores)
            : $this->closestToExpected($scores, $expectedPeriod, $profile, $mean);

        return $period === null ? null : ['period' => (float) $period, 'offset' => $scores[$period]['offset'] ?? $this->bestOffset($profile, $period, $mean)['offset']];
    }

    /**
     * @param  array<int, array{score: float, offset: int}>  $scores
     */
    private function smallestNearBest(array $scores): ?int
    {
        $best = max(array_column($scores, 'score'));

        foreach ($scores as $period => $candidate) {
            if ($candidate['score'] >= self::NEAR_BEST * $best) {
                return $period;
            }
        }

        return null;
    }

    /**
     * Best score weighted by the closeness to the expected period, then refined downwards
     * when a divisor explains the edges as well (the model drew finer than expected).
     *
     * @param  array<int, array{score: float, offset: int}>  $scores
     * @param  list<float>  $profile
     */
    private function closestToExpected(array $scores, float $expectedPeriod, array $profile, float $mean): ?int
    {
        $chosen = null;
        $chosenWeighted = 0.0;

        foreach ($scores as $period => $candidate) {
            if ($candidate['score'] < self::MIN_CONTRAST) {
                continue;
            }
            $weighted = $candidate['score'] * exp(-2 * abs(log($period / $expectedPeriod)));
            if ($weighted > $chosenWeighted) {
                $chosenWeighted = $weighted;
                $chosen = $period;
            }
        }

        if ($chosen === null) {
            return null;
        }

        foreach ([3, 2] as $divisor) {
            $finer = (int) round($chosen / $divisor);
            if ($finer >= 2 && abs($finer * $divisor - $chosen) <= 1
                && $this->bestOffset($profile, $finer, $mean)['score'] >= self::NEAR_BEST * $scores[$chosen]['score']) {
                return $finer;
            }
        }

        return $chosen;
    }

    /**
     * Mean transition strength on the lattice positions of a period, for its best offset,
     * relative to the mean of the profile.
     *
     * @param  list<float>  $profile
     * @return array{score: float, offset: int}
     */
    private function bestOffset(array $profile, int $period, float $mean): array
    {
        $length = count($profile);
        $bestOffset = 0;
        $bestScore = 0.0;

        for ($offset = 0; $offset < $period; $offset++) {
            $sum = 0.0;
            $n = 0;
            for ($i = $offset; $i < $length; $i += $period) {
                if ($i === 0) {
                    continue;
                }
                $sum += $profile[$i];
                $n++;
            }
            $score = $n > 0 ? $sum / $n / $mean : 0.0;
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestOffset = $offset;
            }
        }

        return ['score' => $bestScore, 'offset' => $bestOffset];
    }
}
