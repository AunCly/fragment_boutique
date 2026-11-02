<?php

namespace App\Services\FragmentTool\Matching;

use InvalidArgumentException;

/**
 * Interchangeable colour matching methods, by key.
 */
class MatcherRegistry
{
    /** @var array<string, Matcher> */
    private array $matchers = [];

    public function __construct()
    {
        foreach ([new RgbMatcher, new RedmeanMatcher, new LabMatcher, new Ciede2000Matcher, new ToneMatcher] as $matcher) {
            $this->matchers[$matcher->key()] = $matcher;
        }
    }

    public function get(string $key): Matcher
    {
        return $this->matchers[$key] ?? throw new InvalidArgumentException("Méthode d'association inconnue : {$key}.");
    }

    /**
     * @return array<string, string> key => label
     */
    public function labels(): array
    {
        return array_map(fn (Matcher $matcher) => $matcher->label(), $this->matchers);
    }
}
