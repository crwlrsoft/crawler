<?php

include 'vendor/autoload.php';

use Crwlr\Crawler\HttpCrawler;
use Crwlr\Crawler\Loader\HttpLoader;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Result;
use Crwlr\Crawler\Steps\Html\GetLinks;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\UserAgents\UserAgent;
use Crwlr\Crawler\UserAgents\UserAgentInterface;
use Psr\Log\LoggerInterface;

class BookingComCrawler extends HttpCrawler
{
    public function userAgent(): UserAgentInterface
    {
        return new UserAgent(
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) ' .
            'Chrome/98.0.4758.102 Safari/537.36'
        );
    }

    public function loader(UserAgentInterface $userAgent, LoggerInterface $logger): LoaderInterface
    {
        return new HttpLoader($userAgent, $this->httpClient(), $logger);
    }
}

$crawler = new BookingComCrawler();
$crawler->addStep(Http::get());
$crawler->addStep(
    new GetLinks(
        '.lp-bui-section.bui-spacer--large .bui-segment-header-stacked.bui-segment-header-exit ' .
        '.bui-segment-header-exit-wrap a'
    )
);
$crawler->addStep(Http::get(['Accept-Encoding' => ['gzip', 'deflate']]));

//$results = $crawler->run('https://www.booking.com/searchresults.de.html?dest_id=14&dest_type=country');
$results = $crawler->run('https://www.booking.com/country/at.de.html');

foreach ($results as $result) {
    /** @var Result $result */
    //var_dump($result->get('unnamed'));
}
