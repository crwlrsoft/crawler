<?php

namespace tests\_Integration\Http;

use Crwlr\Crawler\HttpCrawler;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Steps\Html;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\Steps\Step;
use Crwlr\Crawler\UserAgents\BotUserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Generator;
use Psr\Log\LoggerInterface;

use Symfony\Component\DomCrawler\Crawler;

use function tests\helper_generatorToArray;
use function tests\helper_getFastLoader;

class HeadlessBrowserCrawler extends HttpCrawler
{
    protected function userAgent(): UserAgentInterface
    {
        return new BotUserAgent('HeadlessBrowserBot');
    }

    public function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface
    {
        $loader = helper_getFastLoader($userAgent, $logger);

        $loader->useHeadlessBrowser();

        return $loader;
    }
}

class GetJsonFromResponseHtmlBody extends Step
{
    protected function invoke(mixed $input): Generator
    {
        $html = Http::getBodyString($input->response);

        $jsonString = (new Crawler($html))->filter('body pre')->text();

        yield json_decode($jsonString, true);
    }
}

class GetStringFromResponseHtmlBody extends Step
{
    protected function invoke(mixed $input): Generator
    {
        $html = Http::getBodyString($input->response);

        yield (new Crawler($html))->filter('body')->text();
    }
}

it('automatically uses the Loader\'s user agent', function () {
    $crawler = new HeadlessBrowserCrawler();

    $crawler->input('http://localhost:8000/print-headers')
        ->addStep(Http::get())
        ->addStep('responseBody', new GetJsonFromResponseHtmlBody());

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(1);

    expect($results[0]->get('responseBody'))->toBeArray();

    expect($results[0]->get('responseBody'))->toHaveKey('User-Agent');

    expect($results[0]->get('responseBody')['User-Agent'])->toBe('Mozilla/5.0 (compatible; HeadlessBrowserBot)');
});

it('uses cookies', function () {
    $crawler = new HeadlessBrowserCrawler();

    $crawler->input('http://localhost:8000/set-cookie')
        ->addStep(Http::get())
        ->addStep(new class () extends Step {
            protected function invoke(mixed $input): Generator
            {
                yield 'http://localhost:8000/print-cookie';
            }
        })
        ->addStep(Http::get())
        ->addStep('printed-cookie', new GetStringFromResponseHtmlBody());

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(1);

    expect($results[0]->get('printed-cookie'))->toBeString();

    expect($results[0]->get('printed-cookie'))->toBe('foo123');
});

it('renders javascript', function () {
    $crawler = new HeadlessBrowserCrawler();

    $crawler->input('http://localhost:8000/js-rendering')
        ->addStep(Http::get())
        ->addStep(
            Html::root()
                ->extract(['content' => '#content p'])
        );

    $results = helper_generatorToArray($crawler->run());

    expect($results)->toHaveCount(1);

    expect($results[0]->toArray())->toBe([
        'content' => 'This was added through javascript',
    ]);
});
