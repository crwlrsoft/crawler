<?php

namespace tests\Loader;

use Crwlr\Crawler\Exceptions\UnknownLoaderKeyException;
use tests\_Stubs\LoaderCollectingStep;
use Crwlr\Crawler\Crawler;
use Crwlr\Crawler\Loader\AddLoadersToStepAction;
use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Logger\CliLogger;
use Crwlr\Crawler\Steps\Html;
use Crwlr\Crawler\UserAgents\UserAgent;
use tests\_Stubs\PhantasyLoader;

/**
 * @return array<string, LoaderInterface>
 */
function helper_getLoaders(): array
{
    $userAgent = new UserAgent('SomeUserAgent');

    $logger = new CliLogger();

    return [
        'http' => new HttpLoader($userAgent, logger: $logger),
        'phantasy' => new PhantasyLoader($userAgent, logger: $logger),
        'phantasy2' => new PhantasyLoader($userAgent, logger: $logger),
    ];
}

it('does not cause an error when called with a non loading step', function () {
    (new AddLoadersToStepAction(helper_getLoaders(), Html::root()->extract([])));
})->throwsNoExceptions();

it('adds the loader to the step when invoked with a single loader', function () {
    $loader = new HttpLoader(new UserAgent('Foo'), logger: new CliLogger());

    $step = new LoaderCollectingStep();

    (new AddLoadersToStepAction($loader, $step))->invoke();

    expect($step->loaders)->toHaveCount(1);

    expect($step->loaders[0])->toBe($loader);
});

it('adds all loaders one by one to the step when called with multiple loaders', function () {
    $step = new LoaderCollectingStep();

    (new AddLoadersToStepAction(helper_getLoaders(), $step))->invoke();

    expect($step->loaders)->toHaveCount(3);

    expect($step->loaders[0])->toBeInstanceOf(HttpLoader::class);

    expect($step->loaders[1])->toBeInstanceOf(PhantasyLoader::class);

    expect($step->loaders[2])->toBeInstanceOf(PhantasyLoader::class);
});

it('adds only the chosen loader when useLoader() was called on a step', function () {
    $step = new LoaderCollectingStep();

    $step->useLoader('http');

    (new AddLoadersToStepAction(helper_getLoaders(), $step))->invoke();

    expect($step->loaders)->toHaveCount(1);

    expect($step->loaders[0])->toBeInstanceOf(HttpLoader::class);
});

it('throws an UnknownLoaderKeyException when useLoader() is called with an undefined loader key', function () {
    $step = new LoaderCollectingStep();

    $step->useLoader('https');

    (new AddLoadersToStepAction(helper_getLoaders(), $step))->invoke();
})->throws(UnknownLoaderKeyException::class);

it('adds all loaders to a group step, and the group step adds it to its children', function () {
    $step1 = (new LoaderCollectingStep())->useLoader('http');

    $step2 = new LoaderCollectingStep();

    $groupStep = Crawler::group()
        ->addStep($step1)
        ->addStep($step2);

    (new AddLoadersToStepAction(helper_getLoaders(), $groupStep))->invoke();

    expect($step1->loaders)->toHaveCount(1);

    expect($step2->loaders)->toHaveCount(3);
});
