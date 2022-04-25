# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
  something to the Result using `setResultKey` or `addKeysToResult`,
  which was still missing. For groups this will only work when using
  `combineToSingleOutput`.
