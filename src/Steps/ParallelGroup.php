<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Output;

final class ParallelGroup extends Group
{
    public static function new(): static
    {
        return new self();
    }

    /**
     * @param Input $input
     * @return Output[]
     */
    public function invokeStep(Input $input): array
    {
        $outputs = [];

        foreach ($this->steps as $step) {
            array_push($outputs, ...$step->invokeStep($input));
        }

        return $outputs;
    }
}
