<?php

namespace Crwlr\Crawler\Steps\Refiners\String;

use Closure;
use Crwlr\Crawler\Steps\Refiners\AbstractRefiner;

abstract class AbstractStringRefiner extends AbstractRefiner
{
    /**
     * @param Closure $refiner
     * @return mixed
     */
    protected function apply(mixed $value, Closure $refiner, string $staticRefinerMethod): mixed
    {
        if (!is_string($value) && !is_array($value)) {
            $this->logTypeWarning($staticRefinerMethod, $value);

            return $value;
        }

        if (is_array($value)) {
            foreach ($value as $key => $element) {
                if (is_string($element)) {
                    $value[$key] = $refiner($element);
                }
            }
        } else {
            $value = $refiner($value);
        }

        return $value;
    }
}
