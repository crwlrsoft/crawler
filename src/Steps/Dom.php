<?php

namespace Crwlr\Crawler\Steps;

use Crwlr\Crawler\Cache\Exceptions\MissingZlibExtensionException;
use Crwlr\Crawler\Loader\Http\Messages\RespondedRequest;
use Crwlr\Crawler\Logger\PreStepInvocationLogger;
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
        $this->addLogger(new PreStepInvocationLogger());

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

        if (trim($instance->each->query) === '') {
            $instance->logger?->warning(
                'The selector you provided for the ‘each’ option is empty. This option is intended to allow ' .
                'extracting multiple output objects from a single page, so an empty selector most likely doesn’t ' .
                'make sense, as it will definitely result in only one output object.',
            );
        }

        return $instance;
    }

    public static function first(string|DomQuery $domQuery): static
    {
        $instance = new static();

        $instance->first = is_string($domQuery) ? $instance->makeDefaultDomQueryInstance($domQuery) : $domQuery;

        if (trim($instance->first->query) === '') {
            $instance->logger?->warning(
                'The selector you provided for the ‘first’ option is empty. This option is meant to restrict your ' .
                'extraction to a specific parent element, so an empty selector most likely doesn’t make sense. ' .
                'Either define the desired selector or use the root() method instead.',
            );
        }

        return $instance;
    }

    public static function last(string|DomQuery $domQuery): static
    {
        $instance = new static();

        $instance->last = is_string($domQuery) ? $instance->makeDefaultDomQueryInstance($domQuery) : $domQuery;

        if (trim($instance->last->query) === '') {
            $instance->logger?->warning(
                'The selector you provided for the ‘last’ option is empty. This option is meant to restrict your ' .
                'extraction to a specific parent element, so an empty selector most likely doesn’t make sense. ' .
                'Either define the desired selector or use the root() method instead.',
            );
        }

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

                $mappedProperties[$key] = $this->getDataFromChildDomStep($domQuery, $node);
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
            return $this->getBaseFromDomNode($document, $this->each, each: true);
        } elseif ($this->first) {
            return $this->getBaseFromDomNode($document, $this->first, first: true);
        } elseif ($this->last) {
            return $this->getBaseFromDomNode($document, $this->last, last: true);
        }

        throw new Exception('Invalid state: no base selector');
    }

    /**
     * @throws Exception
     */
    private function getBaseFromDomNode(
        DomDocument|Node $document,
        DomQuery $query,
        bool $each = false,
        bool $first = false,
        bool $last = false,
    ): Node|NodeList|null {
        if (trim($query->query) === '') {
            return $each ? new NodeList([$document]) : $document;
        }

        if ($each) {
            return $query instanceof CssSelector ?
                $document->querySelectorAll($query->query) :
                $document->queryXPath($query->query);
        } elseif ($first) {
            return $this->first instanceof CssSelector ?
                $document->querySelector($query->query) :
                $document->queryXPath($query->query)->first();
        } elseif ($last) {
            return $this->last instanceof CssSelector ?
                $document->querySelectorAll($query->query)->last() :
                $document->queryXPath($query->query)->last();
        }

        return $document;
    }

    /**
     * @return mixed[]
     * @throws Exception
     */
    protected function getDataFromChildDomStep(Dom $step, Node $node): array
    {
        $childValue = iterator_to_array($step->invoke($node));

        // When the child step was not used with each() as base and the result is an array with one
        // element (index/key "0") being an array, use that child array.
        if (!$step->each && count($childValue) === 1 && isset($childValue[0]) && is_array($childValue[0])) {
            return $childValue[0];
        }

        return $childValue;
    }
}
