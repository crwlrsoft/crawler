<?php

namespace tests\Loader\Http\Browser;

use Crwlr\Crawler\Loader\Http\Browser\ScreenshotConfig;
use HeadlessChromium\Clip;
use HeadlessChromium\Page;
use Mockery;

it('can be constructed with a store path only', function () {
    $instance = new ScreenshotConfig('/some/path');

    expect($instance->storePath)->toBe('/some/path')
        ->and($instance->fileType)->toBe('png')
        ->and($instance->quality)->toBeNull()
        ->and($instance->fullPage)->toBeFalse();
});

it('can be constructed via the static make() method', function () {
    $instance = ScreenshotConfig::make('/some/different/path');

    expect($instance->storePath)->toBe('/some/different/path')
        ->and($instance->fileType)->toBe('png')
        ->and($instance->quality)->toBeNull()
        ->and($instance->fullPage)->toBeFalse();
});

test('the image file type can be changed to jpeg via the setImageFileType() method', function () {
    $instance = ScreenshotConfig::make('/some/path')->setImageFileType('jpeg');

    expect($instance->fileType)->toBe('jpeg')
        ->and($instance->quality)->toBe(80);
});

test('the image file type can be changed to webp via the setImageFileType() method', function () {
    $instance = ScreenshotConfig::make('/some/path')->setImageFileType('webp');

    expect($instance->fileType)->toBe('webp')
        ->and($instance->quality)->toBe(80);
});

test('the image file type can be changed to png via the setImageFileType() method', function () {
    $instance = ScreenshotConfig::make('/some/path')->setImageFileType('jpeg');

    $instance->setImageFileType('png');

    expect($instance->fileType)->toBe('png')
        ->and($instance->quality)->toBeNull();
});

test('setting the image file type to something different than png, jpeg or webp does not work', function () {
    $instance = ScreenshotConfig::make('/some/path')->setImageFileType('gif');

    expect($instance->fileType)->toBe('png');
});

test('the image quality can be changed via setQuality()', function () {
    $instance = ScreenshotConfig::make('/some/path')->setImageFileType('jpeg')->setQuality(65);

    expect($instance->quality)->toBe(65);
});

test('the image quality can not be changed via setQuality() when the file type is png', function () {
    $instance = ScreenshotConfig::make('/some/path')->setQuality(65);

    expect($instance->quality)->toBeNull();
});

test('the full page param can be set to true via setFullPage()', function () {
    $instance = ScreenshotConfig::make('/some/path')->setFullPage();

    expect($instance->fullPage)->toBeTrue();
});

it('creates a config array for the chrome-php library', function () {
    $pageMock = Mockery::mock(Page::class);

    $instance = ScreenshotConfig::make('/some/path');

    expect($instance->toChromePhpScreenshotConfig($pageMock))->toBe(['format' => 'png']);
});

test('the config array for the chrome-php library contains the image quality', function () {
    $pageMock = Mockery::mock(Page::class);

    $instance = ScreenshotConfig::make('/some/path')->setImageFileType('webp')->setQuality(75);

    expect($instance->toChromePhpScreenshotConfig($pageMock))->toBe(['format' => 'webp', 'quality' => 75]);
});

test('the config array has the necessary properties when fullPage is set to true', function () {
    $pageMock = Mockery::mock(Page::class);

    $pageMock->shouldReceive('getFullPageClip')->andReturn(Mockery::mock(Clip::class));

    $instance = ScreenshotConfig::make('/some/path')->setFullPage();

    $configArray = $instance->toChromePhpScreenshotConfig($pageMock);

    expect($configArray['format'])->toBe('png')
        ->and($configArray['captureBeyondViewport'])->toBeTrue()
        ->and($configArray['clip'])->toBeInstanceOf(Clip::class);
});
