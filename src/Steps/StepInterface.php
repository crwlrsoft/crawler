<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Input;
use Psr\Log\LoggerInterface;

interface StepInterface
{
    /**
     * This should be called in the Crawler. It automatically calls the validateAndSanitizeInput() method.
     *
     * @param Input $input
     * @return array
     */
    public function invokeStep(Input $input): array;

    public function addLogger(LoggerInterface $logger);
}
