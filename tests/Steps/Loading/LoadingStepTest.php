<?php

namespace tests\Steps\Loading;

use Crwlr\Crawler\Input;
use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Loader\Loader;
use Crwlr\Crawler\Steps\Loading\LoadingStep;
use Crwlr\Crawler\Steps\Step;
use Generator;
use Mockery;

use function tests\helper_invokeStepWithInput;
use function tests\helper_traverseIterable;

test('you can add a loader', function () {
    $step = new class () extends Step {
        use LoadingStep;

        protected function invoke(mixed $input): Generator
        {
            $this->getLoader()->load($input);

            yield [];
        }
    };

    $loader = Mockery::mock(HttpLoader::class);

    $loader->shouldReceive('load')->once();

    $step->setLoader($loader);

    helper_traverseIterable($step->invokeStep(new Input('https://www.digitalocean.com/blog')));
});

test(
    'you can provide a custom loader to a step via the withLoader() method, and it will be preferred to the loader ' .
    'provided via setLoader()',
    function () {
        $loaderOne = Mockery::mock(Loader::class);

        $loaderOne->shouldNotReceive('load');

        $loaderTwo = Mockery::mock(Loader::class);

        $loaderTwo->shouldReceive('load')->once()->andReturn('Hi');

        $step = new class () extends Step {
            use LoadingStep;

            protected function invoke(mixed $input): Generator
            {
                yield $this->getLoader()->load($input);
            }
        };

        $step->withLoader($loaderTwo);

        // The crawler will call the setLoader() method of the step after the step was added to the crawler.
        // So, the call to withLoader() will happen before that.
        // Nevertheless, the loader passed to withLoader() should be preferred.
        $step->setLoader($loaderOne);

        helper_invokeStepWithInput($step);
    },
);
