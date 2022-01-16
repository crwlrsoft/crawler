<?php

include 'vendor/autoload.php';

use Crwlr\Crawler\Cache\FileCache;
use Crwlr\Crawler\HttpCrawler;
use Crwlr\Crawler\Loader\LoaderInterface;
use Crwlr\Crawler\Steps\Html;
use Crwlr\Crawler\Steps\Loading\Http;
use Crwlr\Crawler\UserAgent;

class MyCrawler extends HttpCrawler
{
    public function userAgent(): UserAgent
    {
        return UserAgent::make('CrwlrBot');
    }

    public function loader(): LoaderInterface
    {
        $loader = parent::loader();
        $loader->setCache(new FileCache(__DIR__ . '/../cachedir'));

        return $loader;
    }
}

$crawler = new MyCrawler();
$crawler->addStep(Http::get());
$crawler->addStep(
    Html::getLinks('#content .tile .package-headline a')
        ->initResultResource('package')
        ->resultResourceProperty('link')
);
$crawler->addStep(Http::get());
$crawler->addStep(
    Html::querySelectorAll('#content a')
        ->innerText()
        ->resultResourceProperty('versions')
);

// The entrypoint uri(s) for the crawler is(/are) provided when the crawler is run.
$results = $crawler->run('https://crwlr.software/packages');

var_dump($results->allToArray());
