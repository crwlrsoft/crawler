<?php

namespace tests\Steps\Refiners;

use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Steps\Refiners\AbstractRefiner;
use PHPUnit\Framework\TestCase;

class SomeRefiner extends AbstractRefiner
{
    public function refine(mixed $value): mixed
    {
        $this->logger?->info('logging works');

        return $value;
    }

    public function testLogTypeWarning(): void
    {
        $this->logTypeWarning('Some::staticMethodName()', 'foo');
    }
}

/** @var TestCase $this */

it('takes a logger that can be used in the Refiner', function () {
    $refiner = new SomeRefiner();

    $refiner->addLogger(new CliLogger());

    $refiner->refine('foo');

    $logOutput = $this->getActualOutputForAssertion();

    expect($logOutput)->toContain('logging works');
});

it('provides a method for children to log a warning if the type of the incoming value is wrong', function () {
    (new SomeRefiner())->addLogger(new CliLogger())->testLogTypeWarning();

    $logOutput = $this->getActualOutputForAssertion();

    expect($logOutput)->toContain('Refiner Some::staticMethodName() can\'t be applied to value of type string');
});
