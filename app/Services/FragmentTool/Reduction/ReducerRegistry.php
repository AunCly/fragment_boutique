<?php

namespace App\Services\FragmentTool\Reduction;

use InvalidArgumentException;

/**
 * Interchangeable reduction methods, by key. Adding one does not require UI changes.
 */
class ReducerRegistry
{
    /** @var array<string, Reducer> */
    private array $reducers = [];

    public function __construct()
    {
        foreach ([new GridReducer, new MeanReducer, new MedianReducer, new ModeReducer, new NearestReducer, new ContoursReducer] as $reducer) {
            $this->reducers[$reducer->key()] = $reducer;
        }
    }

    public function get(string $key): Reducer
    {
        return $this->reducers[$key] ?? throw new InvalidArgumentException("Méthode de réduction inconnue : {$key}.");
    }

    /**
     * @return array<string, string> key => label
     */
    public function labels(): array
    {
        return array_map(fn (Reducer $reducer) => $reducer->label(), $this->reducers);
    }
}
