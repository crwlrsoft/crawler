<?php

namespace tests\UserAgents;

use Crwlr\Crawler\UserAgents\UserAgent;

test(
    'It can be created with any string in constructor and the __toString method returns that string',
    function ($string) {
        $userAgent = new UserAgent($string);
        expect($userAgent->__toString())->toBe($string);
    },
)->with([
    '',
    'Foo',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 ' .
    'Safari/537.36',
    '%$§$!")(=aäöüäö?ßß``2304980=)(§$/&!"=)=',
]);
