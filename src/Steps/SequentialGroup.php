<?php

namespace Crwlr\Crawler\Steps;

use AppendIterator;
use Crwlr\Crawler\Input;
use Crwlr\Crawler\Output;
use Generator;
use NoRewindIterator;

final class SequentialGroup extends Group
{
    public static function new(): GroupInterface
    {
        return new self();
    }

    /**
     * @param Input $input
     * @return Generator<Output>
     */
    public function invokeStep(Input $input): Generator
    {
        $inputs = [$input];

        foreach ($this->steps as $step) {
            $outputs = new AppendIterator();

            foreach ($inputs as $input) {
                if ($input instanceof Output) {
                    $input = new Input($input);
                }

                $outputs->append(new NoRewindIterator($step->invokeStep($input)));
            }

            $inputs = [];

            foreach ($outputs as $output) {
                if ($output === null) {
                    continue;
                }

                if ($step !== end($this->steps)) {
                    $inputs[] = $output;
                } else {
                    yield $output;
                }
            }
        }
    }
}
