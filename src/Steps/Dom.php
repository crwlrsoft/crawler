<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Cache\Exceptions\MissingZlibExtensionException;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Steps\Dom\DomDocument;
use Crwlr\Crawler\Steps\Dom\HtmlDocument;
use Crwlr\Crawler\Steps\Dom\Node;
use Crwlr\Crawler\Steps\Dom\NodeList;
use Crwlr\Crawler\Steps\Dom\XmlDocument;
use Crwlr\Crawler\Steps\Html\CssSelector;
use Crwlr\Crawler\Steps\Html\DomQuery;
use Crwlr\Crawler\Steps\Html\Exceptions\InvalidDomQueryException;
use Crwlr\Crawler\Steps\Html\XPathQuery;
use Crwlr\Html2Text\Exceptions\InvalidHtmlException;
use Exception;
use Generator;
use InvalidArgumentException;

abstract class Dom extends Step
{
    protected bool $root = false;

    protected ?DomQuery $each = null;

    protected ?DomQuery $first = null;

    protected ?DomQuery $last = null;

    /**
     * @var array<int|string, string|DomQuery|Dom>
     */
    protected array $mapping = [];

    protected null|string|DomQuery $singleSelector = null;

    protected ?string $baseUrl = null;

    /**
     * @param string|DomQuery|array<int|string, string|DomQuery> $selectorOrMapping
     */
    final public function __construct(string|DomQuery|array $selectorOrMapping = [])
    {
        $this->extract($selectorOrMapping);
    }

    public static function root(): static
    {
        $instance = new static();

        $instance->root = true;

        return $instance;
    }

    public static function each(string|DomQuery $domQuery): static
    {
        $instance = new static();

        $instance->each = is_string($domQuery) ? $instance->makeDefaultDomQueryInstance($domQuery) : $domQuery;

        return $instance;
    }

    public static function first(string|DomQuery $domQuery): static
    {
        $instance = new static();

        $instance->first = is_string($domQuery) ? $instance->makeDefaultDomQueryInstance($domQuery) : $domQuery;

        return $instance;
    }

    public static function last(string|DomQuery $domQuery): static
    {
        $instance = new static();

        $instance->last = is_string($domQuery) ? $instance->makeDefaultDomQueryInstance($domQuery) : $domQuery;

        return $instance;
    }

    /**
     * @throws InvalidDomQueryException
     */
    public static function cssSelector(string $selector): CssSelector
    {
        return new CssSelector($selector);
    }

    /**
     * @throws InvalidDomQueryException
     * @deprecated As the usage of XPath queries is no longer an option with the new DOM API introduced in
     *             PHP 8.4, please switch to using CSS selectors instead!
     */
    public static function xPath(string $query): XPathQuery
    {
        return new XPathQuery($query);
    }

    abstract protected function makeDefaultDomQueryInstance(string $query): DomQuery;

    /**
     * @param string|DomQuery|array<string|DomQuery|Dom> $selectorOrMapping
     */
    public function extract(string|DomQuery|array $selectorOrMapping): static
    {
        if (is_array($selectorOrMapping)) {
            $this->mapping = $selectorOrMapping;
        } else {
            $this->singleSelector = $selectorOrMapping;
        }

        return $this;
    }

    public function outputType(): StepOutputType
    {
        return empty($this->mapping) && $this->singleSelector ?
            StepOutputType::Scalar :
            StepOutputType::AssociativeArrayOrObject;
    }

    /**
     * @param HtmlDocument|Node $input
     * @throws Exception
     */
    protected function invoke(mixed $input): Generator
    {
        $base = $this->getBase($input);

        if (!$base || ($base instanceof NodeList && $base->count() === 0)) {
            return;
        }

        if (empty($this->mapping) && $this->singleSelector) {
            yield from $this->singleSelector($base);
        } else {
            if ($this->each) {
                if ($base instanceof NodeList) {
                    foreach ($base as $element) {
                        yield $this->mapProperties($element);
                    }
                }
            } elseif ($base instanceof Node) {
                yield $this->mapProperties($base);
            }
        }
    }


    /**
     * @throws InvalidArgumentException|MissingZlibExtensionException
     */
    protected function validateAndSanitizeInput(mixed $input): HtmlDocument|XmlDocument
    {
        if ($input instanceof RespondedRequest) {
            $this->baseUrl = $input->effectiveUri();
        }

        return new HtmlDocument($this->validateAndSanitizeStringOrHttpResponse($input));
    }

    /**
     * @throws InvalidHtmlException
     * @throws Exception
     */
    protected function singleSelector(Node|NodeList $nodeOrNodeList): Generator
    {
        if ($this->singleSelector === null) {
            return;
        }

        $domQuery = is_string($this->singleSelector) ?
            $this->makeDefaultDomQueryInstance($this->singleSelector) :
            $this->singleSelector;

        if ($this->baseUrl !== null) {
            $domQuery->setBaseUrl($this->baseUrl);
        }

        if ($nodeOrNodeList instanceof NodeList) {
            $outputs = [];

            foreach ($nodeOrNodeList as $node) {
                $outputs[] = $domQuery->apply($node);
            }
        } else {
            $outputs = $domQuery->apply($nodeOrNodeList);
        }

        if (is_array($outputs)) {
            foreach ($outputs as $output) {
                yield $output;
            }
        } elseif ($outputs !== null) {
            yield $outputs;
        }
    }

    /**
     * @return mixed[]
     * @throws Exception
     */
    protected function mapProperties(Node $node): array
    {
        $mappedProperties = [];

        foreach ($this->mapping as $key => $domQuery) {
            if ($domQuery instanceof Dom) {
                $domQuery->baseUrl = $this->baseUrl;

                $mappedProperties[$key] = iterator_to_array($domQuery->invoke($node));
            } else {
                if (is_string($domQuery)) {
                    $domQuery = $this->makeDefaultDomQueryInstance($domQuery);
                }

                if ($this->baseUrl !== null) {
                    $domQuery->setBaseUrl($this->baseUrl);
                }

                $mappedProperties[$key] = $domQuery->apply($node);
            }
        }

        return $mappedProperties;
    }

    /**
     * @throws Exception
     */
    protected function getBase(DomDocument|Node $document): null|Node|NodeList
    {
        if ($this->root) {
            return $document;
        } elseif ($this->each) {
            return $this->each instanceof CssSelector ?
                $document->querySelectorAll($this->each->query) :
                $document->queryXPath($this->each->query);
        } elseif ($this->first) {
            return $this->first instanceof CssSelector ?
                $document->querySelector($this->first->query) :
                $document->queryXPath($this->first->query)->first();
        } elseif ($this->last) {
            return $this->last instanceof CssSelector ?
                $document->querySelectorAll($this->last->query)->last() :
                $document->queryXPath($this->last->query)->last();
        }

        throw new Exception('Invalid state: no base selector');
    }
}
