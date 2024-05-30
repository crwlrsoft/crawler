<?php

namespace tests\Steps\Html;

use Crwlr\Crawler\Steps\Html;
use Spatie\SchemaOrg\Article;
use Spatie\SchemaOrg\JobPosting;

use function tests\helper_invokeStepWithInput;

function helper_schemaOrgExampleOneJobPostingInBody(): string
{
    return <<<HTML
        <!DOCTYPE html>
        <html lang="de">
        <head><title>Foo Bar</title></head>
        <body>
        <script type="application/ld+json">{"@context":"https:\/\/schema.org","@type":"JobPosting","title":"Senior Full Stack PHP Developer (w\/m\/d)","employmentType":["FULL_TIME"],"datePosted":"2022-07-25","description":"foo bar baz","hiringOrganization":{"@type":"Organization","name":"Foo Ltd.","logo":"https:\/\/www.example.com\/logo.png"},"jobLocation":{"@type":"Place","address":{"@type":"PostalAddress","addressLocality":"Linz","addressRegion":"Upper Austria","addressCountry":"Austria"}},"identifier":{"@type":"PropertyValue","name":"foo","value":123456},"directApply":true} </script>
        <h1>Baz</h1> <p>Other content</p>
        </body>
        </html>
        HTML;
}

function helper_schemaOrgExampleMultipleObjects(): string
{
    return <<<HTML
        <!DOCTYPE html>
        <html lang="de-AT">
        <head>
        <title>Foo Bar</title>
        <script type="application/ld+json">
        {
            "mainEntity": [{
                "name": "Some Question?",
                "acceptedAnswer": {
                    "text": "bli bla blub!",
                    "@type": "Answer"
                },
                "@type": "Question"
            }, {
                "name": "Another question?",
                "acceptedAnswer": {
                    "text": "bla blu blo!",
                    "@type": "Answer"
                },
                "@type": "Question"
            }],
            "@type": "FAQPage",
            "@context": "http://schema.org"
        }
        </script>
        <meta property="og:title" content="Some Article" />
        <meta property="og:type" content="website" />
        <script type="application/ld+json">
        { "@context": "http://schema.org",
        "@type": "Organization",
        "name": "Example Company",
        "url": "https://www.example.com",
        "logo": "https://www.example.com/logo.png", "sameAs": [ "https://some.social-media.app/example-company" ] }
        </script>
        </head>
        <body>
        <h1>Some Article</h1>
        <h2>This is some article about something.</h2>
        <script type="application/ld+json">
        {
            "@context": "https:\/\/schema.org",
            "@type": "Article",
            "name": "Some Article",
            "url": "https:\/\/de.example.org\/articles\/some",
            "sameAs": "http:\/\/www.example.org\/articles\/A123456789",
            "mainEntity": "http:\/\/www.example.org\/articles\/A123456789",
            "author": {
                "@type": "Person",
                "name": "Jane Doe",
                "url": "https://example.com/profile/janedoe123"
            },
            "publisher": {
                "@type": "Organization",
                "name": "Some Organization, Inc.",
                "logo": {
                    "@type": "ImageObject",
                    "url": "https:\/\/www.example.org\/images\/organization-logo.png"
                }
            },
            "datePublished": "2023-09-07T21:57:44Z",
            "image": "https:\/\/images.example.org\/2023\/A123456789.jpg",
            "headline": "This is some article about something."
        }
        </script>
        </body>
        </html>
        HTML;
}

it('extracts schema.org data in JSON-LD format from an HTML document', function () {
    $html = helper_schemaOrgExampleOneJobPostingInBody();

    $outputs = helper_invokeStepWithInput(Html::schemaOrg(), $html);

    expect($outputs)->toHaveCount(1);

    expect($outputs[0]->get())->toBeInstanceOf(JobPosting::class);
});

it('converts the spatie schema.org objects to arrays when calling the toArray() method', function () {
    $html = helper_schemaOrgExampleOneJobPostingInBody();

    $outputs = helper_invokeStepWithInput(Html::schemaOrg()->toArray(), $html);

    expect($outputs)->toHaveCount(1);

    expect($outputs[0]->get())->toBeArray();

    expect($outputs[0]->get()['hiringOrganization'])->toBeArray();

    expect($outputs[0]->get()['hiringOrganization'])->toHaveKey('name');

    expect($outputs[0]->get()['hiringOrganization']['name'])->toBe('Foo Ltd.');
});

it('gets all the schema.org objects contained in a document', function () {
    $html = helper_schemaOrgExampleMultipleObjects();

    $outputs = helper_invokeStepWithInput(Html::schemaOrg(), $html);

    expect($outputs)->toHaveCount(3);
});

it('gets only schema.org objects of a certain type if you use the onlyType method', function () {
    $html = helper_schemaOrgExampleMultipleObjects();

    $outputs = helper_invokeStepWithInput(
        Html::schemaOrg()->onlyType('Article'),
        $html,
    );

    expect($outputs)->toHaveCount(1);

    expect($outputs[0]->get())->toBeInstanceOf(Article::class);
});

it('also finds schema.org objects of a certain type in children of another schema.org object', function () {
    $html = helper_schemaOrgExampleMultipleObjects();

    $outputs = helper_invokeStepWithInput(
        Html::schemaOrg()->onlyType('Organization'),
        $html,
    );

    expect($outputs)->toHaveCount(2);

    expect($outputs[0]->get()->getProperty('name'))->toBe('Example Company');

    expect($outputs[1]->get()->getProperty('name'))->toBe('Some Organization, Inc.');
});

it('extracts certain data from schema.org objects when using the extract() method', function () {
    $html = helper_schemaOrgExampleMultipleObjects();

    $outputs = helper_invokeStepWithInput(
        Html::schemaOrg()->onlyType('Article')->extract(['url', 'headline', 'publisher' => 'publisher.name']),
        $html,
    );

    expect($outputs)->toHaveCount(1);

    expect($outputs[0]->get())->toBe([
        'url' => 'https://de.example.org/articles/some',
        'headline' => 'This is some article about something.',
        'publisher' => 'Some Organization, Inc.',
    ]);
});

test('If an object doesn\'t contain a property from the extract mapping, it\'s just null in the output', function () {
    $html = helper_schemaOrgExampleMultipleObjects();

    $outputs = helper_invokeStepWithInput(
        Html::schemaOrg()->onlyType('Article')->extract(['url', 'headline', 'alternativeHeadline']),
        $html,
    );

    expect($outputs)->toHaveCount(1);

    expect($outputs[0]->get())->toBe([
        'url' => 'https://de.example.org/articles/some',
        'headline' => 'This is some article about something.',
        'alternativeHeadline' => null,
    ]);
});
