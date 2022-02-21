<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Output;
use Generator;

final class ParallelGroup extends Group
{
    public static function new(): static
    {
        return new self();
    }

    /**
     * @param Input $input
     * @return Generator<Output>
     */
    public function invokeStep(Input $input): Generator
    {
        foreach ($this->steps as $step) {
            yield from $step->invokeStep($input);
        }
    }
}
