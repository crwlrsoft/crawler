<?php

namespace tests\_Integration\Http;

use Crwlr\Crawler\HttpCrawler;
use Crwlr\Crawler\Loader\Http\HttpLoader;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\UserAgents\UserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/** @var TestCase $this */

it('Fails when timeout is exceeded', function () {
    $crawler = new class () extends HttpCrawler {
        protected function userAgent(): UserAgentInterface
        {
            return new UserAgent('SomeUserAgent');
        }

        public function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface
        {
            return new HttpLoader($userAgent, logger: $logger, defaultGuzzleClientConfig: [
                'connect_timeout' => 1,
                'timeout' => 1,
            ]);
        }
    };

    $crawler->input('http://localhost:8000/sleep')
        ->addStep(Http::get());

    $crawler->runAndTraverse();

    expect($this->getActualOutput())->toContain('Operation timed out');
});
