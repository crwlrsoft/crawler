<?php

namespace tests\UserAgents;

use Crwlr\Crawler\UserAgents\BotUserAgent;
use PHPUnit\Framework\TestCase;

/** @var TestCase $this */

test('Manually create UserAgent instance', function () {
    $userAgent = new BotUserAgent('SomeBot');
    $this->assertStringContainsString('SomeBot', $userAgent);
});

test('Create UserAgent instance via static make method', function () {
    $userAgent = BotUserAgent::make('CrwlrBot');
    $this->assertStringContainsString('CrwlrBot', $userAgent);
});

test('Create instance with info uri', function () {
    $userAgent = new BotUserAgent('SomeBot', 'https://www.example.com/somebot');
    $this->assertStringContainsString('SomeBot; +https://www.example.com/somebot', $userAgent);
});

test('Create instance with info uri and version', function () {
    $userAgent = new BotUserAgent('SomeBot', 'https://www.example.com/somebot', '1.3');
    $this->assertStringContainsString('SomeBot/1.3; +https://www.example.com/somebot', $userAgent);
});

test('Create instance with version but without info uri', function () {
    $userAgent = new BotUserAgent('SomeBot', version: '1.3');
    $this->assertStringContainsString('SomeBot/1.3)', $userAgent);
});

test('User agent string starts with Mozilla/5.0', function () {
    $userAgent = new BotUserAgent('ExampleBot', 'https://www.example.com/bot', '2.0');
    expect($userAgent->__toString())->toStartWith('Mozilla/5.0');
});
