# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
* New functionality to paginate: There is the new `Paginate` child class of the `Http` step class (easy access via `Http::get()->paginate()`). It takes an instance of the `PaginatorInterface` and uses it to iterate through pagination links. There is one implementation of that interface, the `SimpleWebsitePaginator`. The `Http::get()->paginate()` method uses it by default, when called just with a CSS selector to get pagination links. Paginators receive all loaded pages and implement the logic to find pagination links. The paginator class is also called before sending a request, with the request object that is about to be sent as an argument (`prepareRequest()`). This way, it should even be doable to implement more complex pagination functionality. For example when pagination is built using POST request with query strings in the request body.
* New methods `stopOnErrorResponse()` and `yieldErrorResponses()` that can be used with `Http` steps. By calling `stopOnErrorResponse()` the step will throw a `LoadingException` when a response has a 4xx or 5xx status code. By calling the `yieldErrorResponse()` even error responses will be yielded and passed on to the next steps (this was default behaviour until this version. See the breaking change below).
* The body of HTTP responses with a `Content-Type` header containing `application/x-gzip` are automatically decoded when `Http::getBodyString()` is used. Therefor added `ext-zlib` to suggested in `composer.json`.
* The `FileCache` class can compress the cache data now to save disk space. Use the `useCompression()` method to do so.
* New method `retryCachedErrorResponses()` in `HttpLoader`. When called, the loader will only use successful responses (status code < 400) from the cache and therefore retry already cached error responses.
* New method `writeOnlyCache()` in `HttpLoader` to only write to, but don't read from the response cache. Can be used to renew cached responses.

### Changed
* __BREAKING__: Error responses (4xx as well as 5xx), by default, won't produce any step outputs any longer. If you want to receive error responses, use the new `yieldErrorResponses()` method.
* __BREAKING__: Removed the `httpClient()` method in the `HttpCrawler` class. If you want to provide your own HTTP client, implement a custom `loader` method passing your client to the `HttpLoader` instead.
* In case of a 429 (Too Many Requests) response, the `HttpLoader` now automatically waits and retries. By default, it retries twice and waits 10 seconds for the first retry and a minute for the second one. In case the response also contains a `Retry-After` header with a value in seconds, it complies to that. Exception: by default it waits at max `60` seconds (you can set your own limit if you want), if the `Retry-After` value is higher, it will stop crawling. If all the retries also receive a `429` it also throws an Exception.
* Removed logger from `Throttler` as it doesn't log anything.
* Fail silently when `robots.txt` can't be parsed.
* Default timeout configuration for the default guzzle HTTP client: `connect_timeout` is `10` seconds and `timeout` is `60` seconds.

### Fixed
* The `CookieJar` now also works with `localhost` or other hosts without a registered domain name.

## [0.6.0] - 2022-10-03

### Added
* New step `Http::crawl()` (class `HttpCrawl` extending the normal `Http` step class) for conventional crawling. It loads all pages of a website (same host or domain) by following links. There's also a lot of options like depth, filtering by paths, and so on.
* New steps `Sitemap::getSitemapsFromRobotsTxt()` (`GetSitemapsFromRobotsTxt`) and `Sitemap::getUrlsFromSitemap()` (`GetUrlsFromSitemap`) to get sitemap (URLs) from a robots.txt file and to get all the URLs from those sitemaps.
* New step `Html::metaData()` to get data from meta tags (and title tag) in HTML documents.
* New step `Html::schemaOrg()` (`SchemaOrg`) to get schema.org structured data in JSON-LD format from HTML documents.
* The abstract `DomQuery` class (parent of the `CssSelector` and `XPathQuery` classes) now has some methods to narrow the selected matches further: `first()`, `last()`, `nth(n)`, `even()`, `odd()`.

### Changed
* __BREAKING__: Removed `PoliteHttpLoader` and traits `WaitPolitely` and `CheckRobotsTxt`. Converted the traits to classes `Throttler` and `RobotsTxtHandler` which are dependencies of the `HttpLoader`. The `HttpLoader` internally gets default instances of those classes. The `RobotsTxtHandler` will respect robots.txt rules by default if you use a `BotUserAgent` and it won't if you use a normal `UserAgent`. You can access the loader's `RobotsTxtHandler` via `HttpLoader::robotsTxt()`. You can pass your own instance of the `Throttler` to the loader and also access it via `HttpLoader::throttle()` to change settings.

### Fixed
* Getting absolute links via the `GetLink` and `GetLinks` steps and the `toAbsoluteUrl()` method of the `CssSelector` and `XPathQuery` classes, now also look for `<base>` tags in HTML when resolving the URLs.
* The `SimpleCsvFileStore` can now also save results with nested data (but only second level). It just concatenates the values separated with a ` | `.

## [0.5.0] - 2022-09-03
### Added
* You can now call the new `useHeadlessBrowser` method on the `HttpLoader` class to use a headless Chrome browser to load pages. This is enough to get HTML after executing javascript in the browser. For more sophisticated tasks a separate Loader and/or Steps should better be created.
* With the `maxOutputs()` method of the abstract `Step` class you can now limit how many outputs a certain step should yield at max. That's for example helpful during development, when you want to run the crawler only with a small subset of the data/requests it will actually have to process when you eventually remove the limits. When a step has reached its limit, it won't even call the `invoke()` method any longer until the step is reset after a run.
* With the new `outputHook()` method of the abstract `Crawler` class you can set a closure that'll receive all the outputs from all the steps. Should be only for debugging reasons.
* The `extract()` method of the `Html` and `Xml` (children of `Dom`) steps now also works with a single selector instead of an array with a mapping. Sometimes you'll want to just get a simple string output e.g. for a next step, instead of an array with mapped extracted data.
* In addition to `uniqueOutputs()` there is now also `uniqueInputs()`. It works exactly the same as `uniqueOutputs()`, filtering duplicate input values instead. Optionally also by a key when expected input is an array or an object.
* In order to be able to also get absolute links when using the `extract()` method of Dom steps, the abstract `DomQuery` class now has a method `toAbsoluteUrl()`. The Dom step will automatically provide the `DomQuery` instance with the base url, presumed that the input was an instance of the `RespondedRequest` class and resolve the selected value against that base url.

### Changed
* Remove some not so important log messages.
* Improve behavior of group step's `combineToSingleOutput()`. When steps yield multiple outputs, don't combine all yielded outputs to one. Instead, combine the first output from the first step with the first output from the second step, and so on.
* When results are not explicitly composed, but the outputs of the last step are arrays with string keys, it sets those keys on the Result object instead of setting a key `unnamed` with the whole array as value.

### Fixed
* The static methods `Html::getLink()` and `Html::getLinks()` now also work without argument, like the `GetLink` and `GetLinks` classes.
* When a `DomQuery` (CSS selector or XPath query) doesn't match anything, its `apply()` method now returns `null` (instead of an empty string). When the `Html(/Xml)::extract()` method is used with a single, not matching selector/query, nothing is yielded. When it's used with an array with a mapping, it yields an array with null values. If the selector for one of the methods `Html(/Xml)::each()`, `Html(/Xml)::first()` or `Html(/Xml)::last()` doesn't match anything, that's not causing an error any longer, it just won't yield anything.
* Removed the (unnecessary) second argument from the `Loop::withInput()` method because when `keepLoopingWithoutOutput()` is called and `withInput()` is called after that call, it resets the behavior.
* Issue when date format for expires date in cookie doesn't have dashes in `d-M-Y` (so `d M Y`).

## [0.4.1] - 2022-05-10
### Fixed
* The `Json` step now also works with Http responses as input.

## [0.4.0] - 2022-05-06
### Added
* The `BaseStep` class now has `where()` and `orWhere()` methods to filter step outputs. You can set multiple filters that will be applied to all outputs. When setting a filter using `orWhere` it's linked to the previously added Filter with "OR". Outputs not matching one of the filters, are not yielded. The available filters can be accessed through static methods on the new `Filter` class. Currently available filters are comparison filters (equal, greater/less than,...), a few string filters (contains, starts/ends with) and url filters (scheme, domain, host,...).
* The `GetLink` and `GetLinks` steps now have methods `onSameDomain()`, `notOnSameDomain()`, `onDomain()`, `onSameHost()`, `notOnSameHost()`, `onHost()` to restrict the which links to find.
* Automatically add the crawler's logger to the `Store` so you can also log messages from there. This can be breaking as the `StoreInterface` now also requires the `addLogger` method. The new abstract `Store` class already implements it, so you can just extend it.

### Changed
* The `Csv` step can now also be used without defining a column mapping. In that case it will use the values from the first line (so this makes sense when there are column headlines) as output array keys.

## [0.3.0] - 2022-04-27
### Added
* By calling `monitorMemoryUsage()` you can tell the Crawler to add log messages with the current memory usage after every step invocation. You can also set a limit in bytes when to start monitoring and below the limit it won't log memory usage.

### Fixed
* Previously the __use of Generators__ actually didn't make a lot of sense, because the outputs of one step were only iterated and passed on to the next step, after the current step was invoked with all its inputs. That makes steps with a lot of inputs bottlenecks and causes bigger memory consumption. So, changed the crawler to immediately pass on outputs of one step to the next step if there is one.

## [0.2.0] - 2022-04-25
### Added
* `uniqueOutputs()` method to Steps to get only unique output values. If outputs are array or object, you can provide a key that will be used as identifier to check for uniqueness. Otherwise, the arrays or objects will be serialized for comparison which will probably be slower.
* `runAndTraverse()` method to Crawler, so you don't need to manually traverse the Generator, if you don't need the results where you're calling the crawler.
* Implement the behaviour for when a `Group` step should add something to the Result using `setResultKey()` or `addKeysToResult()`, which was still missing. For groups this will only work when using `combineToSingleOutput`.
