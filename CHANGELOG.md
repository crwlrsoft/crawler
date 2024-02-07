# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.5.2] - 2024-02-07
### Fixed
* Issue in `GetUrlsFromSitemap` (`Sitemap::getUrlsFromSitemap()`) step when XML content has no line breaks.

## [1.5.1] - 2024-02-06
### Fixed
* For being more flexible to build a separate headless browser loader (in an extension package) extract the most basic HTTP loader functionality to a new `HttpBaseLoader` and important functionality for the headless browser loader to a new `HeadlessBrowserLoaderHelper`. Further, also share functionality from the `Http` steps via a new abstract `HttpBase` step. It's considered a fix, because there's no new functionality, just refactoring existing code for better extendability.

## [1.5.0] - 2024-01-29
### Added
* The `DomQuery` class (parent of `CssSelector` (`Dom::cssSelector`) and `XPathQuery` (`Dom::xPath`)) has a new method `formattedText()` that uses the new crwlr/html-2-text package to convert the HTML to formatted plain text. You can also provide a customized instance of the `Html2Text` class to the `formattedText()` method.

### Fixed
* The `Http::crawl()` step won't yield a page again if a newly found URL responds with a redirect to a previously loaded URL.

## [1.4.0] - 2024-01-14
### Added
* The `QueryParamsPaginator` can now also increase and decrease non first level query param values like `foo[bar][baz]=5` using dot notation: `QueryParamsPaginator::paramsInUrl()->increaseUsingDotNotation('foo.bar.baz', 5)`.

## [1.3.5] - 2023-12-20
### Fixed
* The `FileCache` can now also read uncompressed cache files when compression is activated.

## [1.3.4] - 2023-12-19
### Fixed
* Reset paginator state after finishing paginating for one base input, to enable paginating multiple listings of the same structure.

## [1.3.3] - 2023-12-01
### Fixed
* Add forgotten getter method to get the DOM query that is attached to an `InvalidDomQueryException` instance.

## [1.3.2] - 2023-12-01
### Fixed
* When creating a `CssSelector` or `XPathQuery` instance with invalid selector/query syntax, an `InvalidDomQueryException` is now immediately thrown. This change is considered to be not only non-breaking, but actually a fix, because the `CssSelector` would otherwise throw an exception later when the `apply()` method is called. The `XPathQuery` would silently return no result without notifying you of the invalid query and generate a PHP warning.

## [1.3.1] - 2023-11-30
### Fixed
* Support usage with the new Symfony major version v7.

## [1.3.0] - 2023-10-28
### Added
* New methods `HttpLoader::useProxy()` and `HttpLoader::useRotatingProxies([...])` to define proxies that the loader shall use. They can be used with a guzzle HTTP client instance (default) and when the loader uses the headless Chrome browser. Using them when providing some other PSR-18 implementation will throw an exception.
* New `QueryParamsPaginator` to paginate by increasing and/or decreasing one or multiple query params, either in the URL or in the body of requests. Can be created via static method `Crwlr\Crawler\Steps\Loading\Http\Paginator::queryParams()`.
* New method `stopWhen` in the new `Crwlr\Crawler\Steps\Loading\Http\AbstractPaginator` class (for more info see the deprecation below). You can pass implementations of the new `StopRule` interface or custom closures to that method and then, every time the Paginator receives a loaded response to process, those stop rules are called with the response. If any of the conditions of the stop rules is met, the Paginator stops paginating. Of course also added a few stop rules to use with that new method: `IsEmptyInHtml`, `IsEmptyInJson`, `IsEmptyInXml` and `IsEmptyResponse`, also available via static methods: `PaginatorStopRules::isEmptyInHtml()`, `PaginatorStopRules::isEmptyInJson()`, `PaginatorStopRules::isEmptyInXml()` and `PaginatorStopRules::isEmptyResponse()`.

### Deprecated
* Deprecated the `Crwlr\Crawler\Steps\Loading\Http\PaginatorInterface` and the `Crwlr\Crawler\Steps\Loading\Http\Paginators\AbstractPaginator`. Instead, added a new version of the `AbstractPaginator` as `Crwlr\Crawler\Steps\Loading\Http\AbstractPaginator` that can be used. Usually there shouldn't be a problem switching from the old to the new version. If you want to make your custom paginator implementation ready for v2 of the library, extend the new `AbstractPaginator` class, implement your own `getNextRequest` method (new requirement, with a default implementation in the abstract class, which will be removed in v2) and check if properties and methods of your existing class don't collide with the new properties and methods in the abstract class.

### Fixed
* The `HttpLoader::load()` implementation won't throw any exception, because it shouldn't kill a crawler run. When you want any loading error to end the whole crawler execution `HttpLoader::loadOrFail()` should be used. Also adapted the phpdoc in the `LoaderInterface`.

## [1.2.2] - 2023-09-19
### Fixed
* Fix in `HttpCrawl` (`Http::crawl()`) step: when a page contains a broken link, that can't be resolved and throws an `Exception` from the URL library, ignore the link and log a warning message.
* Minor fix for merging HTTP headers when an `Http` step gets both, statically defined headers and headers to use from array input.

## [1.2.1] - 2023-08-21
### Fixed
* When a URL redirects, the `trackRequestEndFor()` method of the `HttpLoader`'s `Throttler` instance is called only once at the end and with the original request URL.

## [1.2.0] - 2023-08-18
### Added
* New `onCacheHit` hook in the `Loader` class (in addition to `beforeLoad`, `onSuccess`, `onError` and `afterLoad`) that is called in the `HttpLoader` class when a response for a request was found in the cache.

### Deprecated
* Moved the `Microseconds` value object class to the crwlr/utils package, as it is a very useful and universal tool. The class in this package still exists, but just extends the class from the utils package and will be removed in v2. So, if you're using this class, please change to use the version from the utils package.

## [1.1.6] - 2023-07-20
### Fixed
* Throttling now also works when using the headless browser.

## [1.1.5] - 2023-07-14
### Fixed
* The `Http::crawl()` step, as well as the `Html::getLink()` and `Html::getLinks()` steps now ignore links, when the `href` attribute starts with `mailto:`, `tel:` or `javascript:`. For the crawl step it obviously makes no sense, but it's also considered a bugfix for the getLink(s) steps, because they are meant to deliver absolute HTTP URLs. If you want to get the values of such links, use the HTML data extraction step.

## [1.1.4] - 2023-07-14
### Fixed
* The `Http::crawl()` step now also work with sitemaps as input URL, where the `<urlset>` tag contains attributes that would cause the symfony DomCrawler to not find any elements.

## [1.1.3] - 2023-06-29
### Fixed
* Improved `Json` step: if the target of the "each" (like `Json::each('target', [...])`) does not exist in the input JSON data, the step yields nothing and logs a warning.

## [1.1.2] - 2023-05-28
### Fixed
* Using the `only()` method of the `MetaData` (`Html::metaData()`) step class, the `title` property was always contained in the output, even if not listed in the `only` properties. This is fixed now.

## [1.1.1] - 2023-05-28
### Fixed
* There was an issue when adding multiple associative arrays with the same key to a `Result` object: let's say you're having a step producing array output like: `['bar' => 'something', 'baz' => 'something else']` and it (the whole array) shall be added to the result property `foo`. When the step produced multiple such array outputs, that led to a result like `['bar' => '...', 'baz' => '...', ['bar' => '...', 'baz' => '...'], ['bar' => '...', 'baz' => '...']`. Now it's fixed to result in `[['bar' => '...', 'baz' => '...'], ['bar' => '...', 'baz' => '...'], ['bar' => '...', 'baz' => '...']`.

## [1.1.0] - 2023-05-21

### Added
* `Http` steps can now receive body and headers from input data (instead of statically defining them via argument like `Http::method(headers: ...)`) using the new methods `useInputKeyAsBody(<key>)` and `useInputKeyAsHeader(<key>, <asHeader>)` or `useInputKeyAsHeaders(<key>)`. Further, when invoked with associative array input data, the step will by default use the value from `url` or `uri` for the request URL. If the input array contains the URL in a key with a different name, you can use the new `useInputKeyAsUrl(<key>)` method. That was basically already possible with the existing `useInputKey(<key>)` method, because the URL is the main input argument for the step. But if you want to use it in combination with the other new `useInputKeyAsXyz()` methods, you have to use `useInputKeyAsUrl()`, because using `useInputKey(<key>)` would invoke the whole step with that key only.
* `Crawler::runAndDump()` as a simple way to just run a crawler and dump all results, each as an array.
* `addToResult()` now also works with serializable objects.
* If you know certain keys that the output of a step will contain, you can now also define aliases for those keys, to be used with `addToResult()`. The output of an `Http` step (`RespondedRequest`) contains the keys `requestUri` and `effectiveUri`. The aliases `url` and `uri` refer to `effectiveUri`, so `addToResult(['url'])` will add the `effectiveUri` as `url` to the result object.
* The `GetLink` (`Html::getLink()`) and `GetLinks` (`Html::getLinks()`) steps, as well as the abstract `DomQuery` (parent of `CssSelector` (/`Dom::cssSelector`) and `XPathQuery` (/`Dom::xPath`)) now have a method `withoutFragment()` to get links respectively URLs without their fragment part.
* The `HttpCrawl` step (`Http::crawl()`) has a new method `useCanonicalLinks()`. If you call it, the step will not yield responses if its canonical link URL was already yielded. And if it discovers a link, and some document pointing to that URL via canonical link was already loaded, it treats it as if it was already loaded. Further this feature also sets the canonical link URL as the `effectiveUri` of the response.
* All filters can now be negated by calling the `negate()` method, so the `evaluate()` method will return the opposite bool value when called. The `negate()` method returns an instance of `NegatedFilter` that wraps the original filter.
* New method `cacheOnlyWhereUrl()` in the `HttpLoader` class, that takes an instance of the `FilterInterface` as argument. If you define one or multiple filters using this method, the loader will cache only responses for URLs that match all the filters.

### Fixed
* The `HttpCrawl` step (`Http::crawl()`) by default now removes the fragment part of URLs to not load the same page multiple times, because in almost any case, servers won't respond with different content based on the fragment. That's why this change is considered non-breaking. For the rare cases when servers respond with different content based on the fragment, you can call the new `keepUrlFragment()` method of the step.
* Although the `HttpCrawl` step (`Http::crawl()`) already respected the limit of outputs defined via the `maxOutputs()` method, it actually didn't stop loading pages. The limit had no effect on loading, only on passing on outputs (responses) to the next step. This is fixed in this version.
* A so-called byte order mark at the beginning of a file (/string) can cause issues. So just remove it, when a step's input string starts with a UTF-8 BOM.
* There seems to be an issue in guzzle when it gets a PSR-7 request object with a header with multiple string values (as array, like: `['accept-encoding' => ['gzip', 'deflate', 'br']]`). When testing it happened that it only sent the last part (in this case `br`). Therefor the `HttpLoader` now prepares headers before sending (in this case to: `['accept-encoding' => ['gzip, deflate, br']]`).
* You can now also use the output key aliases when filtering step outputs. You can even use keys that are only present in the serialized version of an output object.

## [1.0.2] - 2023-03-20
### Fixed
* JSON step: another fix for JSON strings having keys without quotes with empty string value.

## [1.0.1] - 2023-03-17
### Fixed
* JSON step: improve attempt to fix JSON string having keys without quotes.

## [1.0.0] - 2023-02-08

### Added
* New method `Step::refineOutput()` to manually refine step output values. It takes either a `Closure` or an instance of the new `RefinerInterface` as argument. If the step produces array output, you can provide a key from the array output, to refine, as first argument and the refiner as second argument. You can call the method multiple times and all the refiners will be applied to the outputs in the order you add them. If you want to refine multiple output array keys with a `Closure`, you can skip providing a key and the `Closure` will receive the full output array for refinement. As mentioned you can provide an instance of the `RefinerInterface`. There are already a few implementations: `StringRefiner::afterFirst()`, `StringRefiner::afterLast()`, `StringRefiner::beforeFirst()`, `StringRefiner::beforeLast()`, `StringRefiner::betweenFirst()`, `StringRefiner::betweenLast()` and `StringRefiner::replace()`.
* New method `Step::excludeFromGroupOutput()` to exclude a normal steps output from the combined output of a group that it's part of.
* New method `HttpLoader::setMaxRedirects()` to customize the limit of redirects to follow. Works only when using the HTTP client.
* New filters to filter by string length, with the same options as the comparison filters (equal, not equal, greater than,...).
* New `Filter::custom()` that you can use with a Closure, so you're not limited to the available filters only.
* New method `DomQuery::link()` as a shortcut for `DomQuery::attribute('href')->toAbsoluteUrl()`.
* New static method `HttpCrawler::make()` returning an instance of the new class `AnonymousHttpCrawlerBuilder`. This makes it possible to create your own Crawler instance with a one-liner like: `HttpCrawler::make()->withBotUserAgent('MyCrawler')`. There's also a `withUserAgent()` method to create an instance with a normal (non bot) user agent.

### Changed
* __BREAKING__: The `FileCache` now also respects the `ttl` (time to live) argument and by default it is one hour (3600 seconds). If you're using the cache and expect the items to live (basically) forever, please provide a high enough value for default the time to live. When you try to get a cache item that is already expired, it (the file) is immediately deleted.
* __BREAKING__: The `TooManyRequestsHandler` (and with that also the constructor argument in the `HttpLoader`) was renamed to `RetryErrorResponseHandler`. It now reacts the same to 503 (Service Unavailable) responses as to the 429 (Too Many Requests) responses. If you're actively passing your own instance to the `HttpLoader`, you need to update it.
* You can now have multiple different loaders in a `Crawler`. To use this, return an array containing your loaders from the protected `Crawler::loader()` method with keys to name them. You can then selectively use them by calling the `Step::useLoader()` method on a loading step with the key of the loader it should use.

### Removed
* __BREAKING__: The loop feature. The only real world use case should be paginating listings and this should be solved with the Paginator feature.
* __BREAKING__: `Step::dontCascade()` and `Step::cascades()` because with the change in v0.7, that groups can only produce combined output, there should be no use case for this anymore. If you want to exclude one steps output from the combined group output, you can use the new `Step::excludeFromGroupOutput()` method.

## [0.7.0] - 2023-01-13

### Added
* New functionality to paginate: There is the new `Paginate` child class of the `Http` step class (easy access via `Http::get()->paginate()`). It takes an instance of the `PaginatorInterface` and uses it to iterate through pagination links. There is one implementation of that interface, the `SimpleWebsitePaginator`. The `Http::get()->paginate()` method uses it by default, when called just with a CSS selector to get pagination links. Paginators receive all loaded pages and implement the logic to find pagination links. The paginator class is also called before sending a request, with the request object that is about to be sent as an argument (`prepareRequest()`). This way, it should even be doable to implement more complex pagination functionality. For example when pagination is built using POST request with query strings in the request body.
* New methods `stopOnErrorResponse()` and `yieldErrorResponses()` that can be used with `Http` steps. By calling `stopOnErrorResponse()` the step will throw a `LoadingException` when a response has a 4xx or 5xx status code. By calling the `yieldErrorResponse()` even error responses will be yielded and passed on to the next steps (this was default behaviour until this version. See the breaking change below).
* The body of HTTP responses with a `Content-Type` header containing `application/x-gzip` are automatically decoded when `Http::getBodyString()` is used. Therefor added `ext-zlib` to suggested in `composer.json`.
* New methods `addToResult()` and `addLaterToResult()`. `addToResult()` is a single replacement for `setResultKey()` and `addKeysToResult()` (they are removed, see `Changed` below) that can be used for array and non array output. `addLaterToResult()` is a new method that does not create a Result object immediately, but instead adds the output of the current step to all the Results that will later be created originating from the current output.
* New methods `outputKey()` and `keepInputData()` that can be used with any step. Using the `outputKey()` method, the step will convert non array output to an array and use the key provided as an argument to this method as array key for the output value. The `keepInputData()` method allows you to forward data from the step's input to the output. If the input is non array you can define a key using the method's argument. This is useful e.g. if you're having data in the initial inputs that you also want to add to the final crawling results.
* New method `createsResult()` that can be used with any step, so you can differentiate if a step creates a Result object, or just keeps data to add to results later (new `addLaterToResult()` method). But primarily relevant for library internal use.
* The `FileCache` class can compress the cache data now to save disk space. Use the `useCompression()` method to do so.
* New method `retryCachedErrorResponses()` in `HttpLoader`. When called, the loader will only use successful responses (status code < 400) from the cache and therefore retry already cached error responses.
* New method `writeOnlyCache()` in `HttpLoader` to only write to, but don't read from the response cache. Can be used to renew cached responses.
* `Filter::urlPathMatches()` to filter URL paths using a regex.
* Option to provide a chrome executable name to the `chrome-php/chrome` library via `HttpLoader::setChromeExecutable()`.

### Changed
* __BREAKING__: Group steps can now only produce combined outputs, as previously done when `combineToSingleOutput()` method was called. The method is removed. 
* __BREAKING__: `setResultKey()` and `addKeysToResult()` are removed. Calls to those methods can both be replaced with calls to the new `addToResult()` method.
* __BREAKING__: `getResultKey()` is also removed with `setResultKey()`. It's removed without replacement, as it doesn't really make sense any longer.
* __BREAKING__: Error responses (4xx as well as 5xx), by default, won't produce any step outputs any longer. If you want to receive error responses, use the new `yieldErrorResponses()` method.
* __BREAKING__: Removed the `httpClient()` method in the `HttpCrawler` class. If you want to provide your own HTTP client, implement a custom `loader` method passing your client to the `HttpLoader` instead.
* __Deprecated__ the loop feature (class `Loop` and `Crawler::loop()` method). Probably the only use case is iterating over paginated list pages, which can be done using the new Paginator functionality. It will be removed in v1.0.
* In case of a 429 (Too Many Requests) response, the `HttpLoader` now automatically waits and retries. By default, it retries twice and waits 10 seconds for the first retry and a minute for the second one. In case the response also contains a `Retry-After` header with a value in seconds, it complies to that. Exception: by default it waits at max `60` seconds (you can set your own limit if you want), if the `Retry-After` value is higher, it will stop crawling. If all the retries also receive a `429` it also throws an Exception.
* Removed logger from `Throttler` as it doesn't log anything.
* Fail silently when `robots.txt` can't be parsed.
* Default timeout configuration for the default guzzle HTTP client: `connect_timeout` is `10` seconds and `timeout` is `60` seconds.
* The `validateAndSanitize...()` methods in the abstract `Step` class, when called with an array with one single element, automatically try to use that array element as input value.
* With the `Html` and `Xml` data extraction steps you can now add layers to the data that is being extracted, by just adding further `Html`/`Xml` data extraction steps as values in the mapping array that you pass as argument to the `extract()` method.
* The base `Http` step can now also be called with an array of URLs as a single input. Crawl and Paginate steps still require a single URL input.

### Fixed
* The `CookieJar` now also works with `localhost` or other hosts without a registered domain name.
* Improve the `Sitemap::getUrlsFromSitemap()` step to also work when the `<urlset>` tag contains attributes that would cause the symfony DomCrawler to not find any elements.
* Fixed possibility of infinite redirects in `HttpLoader` by adding a redirects limit of 10.

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
