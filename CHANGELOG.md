# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Added
* With the `maxOutputs()` method of the abstract `Step` class you can now limit how many outputs a certain step should yield at max. That's for example helpful during development, when you want to run the crawler only with a small subset of the data/requests it will actually have to process when you eventually remove the limits. When a step has reached its limit, it won't even call the `invoke()` method any longer until the step is reset after a run.
* With the new `outputHook()` method of the abstract `Crawler` class you can set a closure that'll receive all the outputs from all the steps. Should be only for debugging reasons.
* The `extract()` method of the `Html` and `Xml` (children of `Dom`) steps now also works with a single selector instead of an array with a mapping. Sometimes you'll want to just get a simple string output e.g. for a next step, instead of an array with mapped extracted data.

### Changed
* Remove some not so important log messages.

### Fixed
* The static methods `Html::getLink()` and `Html::getLinks()` now also work without argument, like the `GetLink` and `GetLinks` classes.
* When a `DomQuery` (CSS selector or XPath query) doesn't match anything, its `apply()` method now returns `null` (instead of an empty string). When the `Html(/Xml)::extract()` method is used with a single, not matching selector/query, nothing is yielded. When it's used with an array with a mapping, it yields an array with null values. If the selector for one of the methods `Html(/Xml)::each()`, `Html(/Xml)::first()` or `Html(/Xml)::last()` doesn't match anything, that's not causing an error any longer, it just won't yield anything.
* Removed the (unnecessary) second argument from the `Loop::withInput()` method because when `keepLoopingWithoutOutput()` is called and `withInput()` is called after that call, it resets the behavior.

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
