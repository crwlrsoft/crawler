# Contributing to this Package

That you're reading this must mean you consider contributing to
this package. So first off: Awesome! 👍🤘

## Bugs

In case you encounter any bugs please
[file an issue](https://github.com/crwlrsoft/crawler/issues/new).
Describe the issue as well as you can and provide an example to
reproduce it.  
Maybe you're not 100 percent sure whether what you've discovered
is a bug or the intended behavior. You can still file an issue
and tell us which results you'd expect.

If you know how to fix the issue you're welcome to send a pull
request. 💪

## New Features

If you have ideas for new features you can tell us about it on
[Twitter](https://twitter.com/crwlrsoft) or via
[crwlr.software](https://www.crwlr.software/contact) or just
send a pull request. Please keep in mind that there is no
guarantee that your feature will be merged.

## Conventions

### Coding Style

This package follows the
[PSR-12](https://www.php-fig.org/psr/psr-12/) coding standard.
You can run PHP CS Fixer via `composer cs` for a dry run or
`composer cs-fix` to automatically fix code style issues.

### Branching

The repo contains branches for every minor version and a master
branch up to date with the latest tagged version. For a bugfix
please send your pull request to the branch of the latest version
affected by the issue. If you're developing a new feature, branch
out from the master branch.

### Code quality tools

When you're making changes to this package please always run
tests and linting. Commands:  
`composer test`  
`composer test-integration`
`composer cs`
`composer stan`

Ideally you add the pre-commit git hook that is shipped with
this repo that will run tests and linting. Add it to your local
clone by running:  
`composer add-git-hooks`

The integration tests start a simple PHP web server for the
testing purpose on port 8000. If you have anything else running
on that port, the integration tests won't work.

Also please don't forget to add new test cases if necessary.

### Documentation

For any code change that changes/adds something for users of
the package, please don't forget to add an entry to the
`CHANGELOG.md` file.

## Appreciation

When your pull request is merged I will show some love and tweet
about it. Also, if you meet me in person I will be glad to buy you
a beer.
