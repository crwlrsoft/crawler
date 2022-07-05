<?php

namespace tests\Loader\Http\Cookies;

use Crwlr\Crawler\Loader\Http\Cookies\Date;
use DateTimeZone;

test('It can be created from a valid http header date format', function () {
    $date = new Date('Tue, 22-Feb-2022 16:04:55 GMT');

    expect($date)->toBeInstanceOf(Date::class);

    expect($date->dateTime()->format('Y-m-d H:i:s'))->toBe('2022-02-22 16:04:55');
});

test('It gets the timezone right', function () {
    $date = new Date('Tue, 22-Feb-2022 20:04:29 GMT');

    expect(
        $date->dateTime()->setTimezone(new DateTimeZone('Europe/Vienna'))->format('d.m.Y H:i:s')
    )->toBe('22.02.2022 21:04:29');
});

test('It also works without the dashes between d-M-Y in the format', function () {
    $date = new Date('Wed, 05 Jul 2023 15:19:55 GMT');

    expect(
        $date->dateTime()->setTimezone(new DateTimeZone('Europe/Vienna'))->format('d.m.Y H:i:s')
    )->toBe('05.07.2023 17:19:55');
});
