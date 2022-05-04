# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Added
* The `BaseStep` class now has a `filter()` method. You can set 
  multiple filters that will be applied to all outputs. Outputs not
  matching one of the filters, are not yielded. Currently available
  filters are comparison filters (equal, greater/less than,...) and
  a few string filters (contains, starts/ends with).

## [0.3.0] - 2022-04-27
### Added
* By calling `monitorMemoryUsage()` you can tell the Crawler to add
  log messages with the current memory usage after every step
  invocation.
  You can also set a limit in bytes when to start monitoring and
  below the limit it won't log memory usage.

### Fixed
* Previously the __use of Generators__ actually didn't make a lot of
  sense, because the outputs of one step were only iterated and
  passed on to the next step, after the current step was invoked
  with all its inputs. That makes steps with a lot of inputs
  bottlenecks and causes bigger memory consumption. So, changed the
  crawler to immediately pass on outputs of one step to the next
  step if there is one.

## [0.2.0] - 2022-04-25
### Added
* `uniqueOutputs()` method to Steps to get only unique output values.
  If outputs are array or object, you can provide a key that will be
  used as identifier to check for uniqueness. Otherwise, the arrays
  or objects will be serialized for comparison which will probably be 
  slower.
* `runAndTraverse()` method to Crawler, so you don't need to manually
  traverse the Generator, if you don't need the results where you're
  calling the crawler.
* Implement the behaviour for when a `Group` step should add
  something to the Result using `setResultKey()` or
  `addKeysToResult()`, which was still missing. For groups this will
  only work when using `combineToSingleOutput`.
