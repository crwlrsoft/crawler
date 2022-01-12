<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Output;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

interface StepInterface
{
    /**
     * Validate and sanitize the incoming Input object
     *
     * The Input object can hold a value of any type. If the input isn't valid for the step throw an
     * InvalidArgumentException.
     * Also perform sanitization within this method and return the value that is the input for the invoke method.
     *
     * @throws InvalidArgumentException  Throw this if the input value is invalid for this step.
     */
    public function validateAndSanitizeInput(Input $input): mixed;

    /**
     * This should be called in the Crawler. It automatically calls the validateAndSanitizeInput() method.
     *
     * @param Input $input
     * @return array
     */
    public function invokeStep(Input $input): array;

    /**
     * This must be defined in the Step implementation.
     * It receives an Input object with the validated and sanitized value.
     *
     * @param Input $input
     * @return Output[]
     */
    public function invoke(Input $input): array;

    public function addLogger(LoggerInterface $logger);
}
