<?php

namespace tests\_Integration\Http;

use Crwlr\Crawler\HttpCrawler;
use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\Steps\Step;
use Crwlr\Crawler\UserAgents\UserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Generator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

use function tests\helper_generatorToArray;

class RedirectTestCrawler extends HttpCrawler
{
    protected function userAgent(): UserAgentInterface
    {
        return new UserAgent('RedirectBot');
    }
}

class GetResponseBodyAsString extends Step
{
    /**
     * @param RespondedRequest $input
     */
    protected function invoke(mixed $input): Generator
    {
        yield Http::getBodyString($input);
    }
}

/** @var TestCase $this */

it('follows redirects', function () {
    $crawler = new RedirectTestCrawler();

    $crawler
        ->input('http://localhost:8000/redirect?stopAt=5')
        ->addStep(Http::get())
        ->addStep('body', new GetResponseBodyAsString());

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(1);

    expect($results[0]->get('body'))->toBe('success after 5 redirects');
});

it('stops at 10 redirects by default', function () {
    $crawler = new RedirectTestCrawler();

    $crawler
        ->input('http://localhost:8000/redirect?stopAt=11')
        ->addStep(Http::get())
        ->addStep('body', new GetResponseBodyAsString());

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(0);

    $logOutput = $this->getActualOutput();

    expect($logOutput)->toContain('Failed to load http://localhost:8000/redirect?stopAt=11: Too many redirects.');
});

test('you can set your own max redirects limit', function () {
    $crawler = new class () extends HttpCrawler {
        protected function userAgent(): UserAgentInterface
        {
            return new UserAgent('RedirectBot');
        }

        protected function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface|array
        {
            $loader = parent::loader($userAgent, $logger);

            if ($loader instanceof HttpLoader) {
                $loader->setMaxRedirects(15);
            }

            return $loader;
        }
    };

    $crawler
        ->input('http://localhost:8000/redirect?stopAt=11')
        ->addStep(Http::get())
        ->addStep('body', new GetResponseBodyAsString());

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(1);

    expect($results[0]->get('body'))->toBe('success after 11 redirects');
});
