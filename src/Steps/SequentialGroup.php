<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Output;

final class SequentialGroup extends Group
{
    public static function new(): GroupInterface
    {
        return new self();
    }

    /**
     * @param Input $input
     * @return Output[]
     */
    public function invokeStep(Input $input): array
    {
        $inputs = [$input];
        $outputs = [];

        foreach ($this->steps as $step) {
            $nextInputs = [];

            foreach ($inputs as $input) {
                $stepOutput = $step->invokeStep($input);
                array_push($outputs, ...$stepOutput);
                array_push($nextInputs, ...$stepOutput);
            }

            $inputs = $this->outputsToInputs($nextInputs);
        }

        return $outputs;
    }

    /**
     * @param array|Output[] $outputs
     * @return Input[]
     */
    private function outputsToInputs(array $outputs): array
    {
        return array_map(function ($output) {
            return new Input($output);
        }, $outputs);
    }
}
