<p align="center"><a href="https://www.crwlr.software" target="_blank"><img src="https://github.com/crwlrsoft/graphics/blob/eee6cf48ee491b538d11b9acd7ee71fbcdbe3a09/crwlr-logo.png" alt="crwlr.software logo" width="260"></a></p>

# Library for Rapid (Web) Crawler and Scraper Development

This library provides kind of a framework and a lot of ready to use, so-called __steps__, that you can use as building blocks, to build your own crawlers and scrapers with.

To give you an overview, here's a list of things that it helps you with:
* [Crawler __Politeness__](https://www.crwlr.software/packages/crawler/the-crawler/politeness) &#128519; (respecting robots.txt, throttling,...)
* Load URLs using
    * [a __(PSR-18) HTTP client__](https://www.crwlr.software/packages/crawler/the-crawler/loaders) (default is of course Guzzle)
    * or a [__headless browser__](https://www.crwlr.software/packages/crawler/the-crawler/loaders#using-a-headless-browser) (chrome) to get source after Javascript execution
* [Get __absolute links__ from HTML documents](https://www.crwlr.software/packages/crawler/included-steps/html#html-get-link) &#x1F517;
* [Get __sitemaps__ from robots.txt and get all URLs from those sitemaps](https://www.crwlr.software/packages/crawler/included-steps/sitemap)
* [__Crawl__ (load) all pages of a website](https://www.crwlr.software/packages/crawler/included-steps/http#crawling) &#x1F577;
* [Use __cookies__ (or don't)](https://www.crwlr.software/packages/crawler/the-crawler/loaders#http-loader) &#x1F36A;
* [Use any __HTTP methods__ (GET, POST,...) and send any headers or body](https://www.crwlr.software/packages/crawler/included-steps/http#http-requests)
* [Easily iterate over __paginated__ list pages](https://www.crwlr.software/packages/crawler/included-steps/http#paginating) &#x1F501;
* Extract data from:
    * [__HTML__](https://www.crwlr.software/packages/crawler/included-steps/html#extracting-data) and also [__XML__](https://www.crwlr.software/packages/crawler/included-steps/xml) (using CSS selectors or XPath queries)
    * [__JSON__](https://www.crwlr.software/packages/crawler/included-steps/json) (using dot notation)
    * [__CSV__](https://www.crwlr.software/packages/crawler/included-steps/csv) (map columns)
* [Extract __schema.org__ structured data](https://www.crwlr.software/packages/crawler/included-steps/html#schema-org) in __JSON-LD__ format from HTML documents
* [Keep memory usage low](https://www.crwlr.software/packages/crawler/crawling-procedure#memory-usage) by using PHP __Generators__ &#x1F4AA;
* [__Cache__ HTTP responses](https://www.crwlr.software/packages/crawler/response-cache) during development, so you don't have to load pages again and again after every code change
* [Get __logs__](https://www.crwlr.software/packages/crawler/the-crawler#loggers) about what your crawler is doing (accepts any PSR-3 LoggerInterface)
* And a lot more...

## Documentation

You can find the documentation at [crwlr.software](https://www.crwlr.software/packages/crawler/getting-started).

## Contributing

If you consider contributing something to this package, read the [contribution guide (CONTRIBUTING.md)](CONTRIBUTING.md).
