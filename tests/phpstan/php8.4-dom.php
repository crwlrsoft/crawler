<?php
// Generated with PHP version 8.4.2 dom version 20031129

namespace Dom;

const INDEX_SIZE_ERR = 1;
const STRING_SIZE_ERR = 2;
const HIERARCHY_REQUEST_ERR = 3;
const WRONG_DOCUMENT_ERR = 4;
const INVALID_CHARACTER_ERR = 5;
const NO_DATA_ALLOWED_ERR = 6;
const NO_MODIFICATION_ALLOWED_ERR = 7;
const NOT_FOUND_ERR = 8;
const NOT_SUPPORTED_ERR = 9;
const INUSE_ATTRIBUTE_ERR = 10;
const INVALID_STATE_ERR = 11;
const SYNTAX_ERR = 12;
const INVALID_MODIFICATION_ERR = 13;
const NAMESPACE_ERR = 14;
const VALIDATION_ERR = 16;
const HTML_NO_DEFAULT_NS = 2147483648;
function dom_import_simplexml(object $node) {}
namespace Dom;
function import_simplexml(object $node) {}
namespace Dom;

use Exception;

enum AdjacentPosition
{
    case BeforeBegin;

    case AfterBegin;

    case BeforeEnd;

    case AfterEnd;

    public static function from(int|string $value) : static
    {
    }

    public static function tryFrom(int|string $value) : ?static
    {
    }
}

final class DOMException extends Exception
{
    public $code = 0;
}

interface DOMParentNode
{
    public function append(... $nodes) : void;
    public function prepend(... $nodes) : void;
    public function replaceChildren(... $nodes) : void;
}

namespace Dom;

interface ParentNode
{
    public function append(\Dom\Node|string ... $nodes) : void;
    public function prepend(\Dom\Node|string ... $nodes) : void;
    public function replaceChildren(\Dom\Node|string ... $nodes) : void;
    public function querySelector(string $selectors) : ?\Dom\Element;
    public function querySelectorAll(string $selectors) : \Dom\NodeList;
}

interface DOMChildNode
{
    public function remove() : void;
    public function before(... $nodes) : void;
    public function after(... $nodes) : void;
    public function replaceWith(... $nodes) : void;
}

namespace Dom;

interface ChildNode
{
    public function remove() : void;
    public function before(\Dom\Node|string ... $nodes) : void;
    public function after(\Dom\Node|string ... $nodes) : void;
    public function replaceWith(\Dom\Node|string ... $nodes) : void;
}

class DOMImplementation
{
    public function hasFeature(string $feature, string $version)
    {
    }

    public function createDocumentType(string $qualifiedName, string $publicId = '', string $systemId = '')
    {
    }

    public function createDocument(?string $namespace = null, string $qualifiedName = '', ?\DOMDocumentType $doctype = null)
    {
    }
}

namespace Dom;

class Implementation
{
    public function createDocumentType(string $qualifiedName, string $publicId, string $systemId) : \Dom\DocumentType
    {
    }

    public function createDocument(?string $namespace, string $qualifiedName, ?\Dom\DocumentType $doctype = null) : \Dom\XMLDocument
    {
    }

    public function createHTMLDocument(?string $title = null) : \Dom\HTMLDocument
    {
    }
}

class DOMNode
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

    /**
     * @var string
     */
    public string $nodeName;

    /**
     * @var ?string
     */
    public ?string $nodeValue;

    /**
     * @var int
     */
    public int $nodeType;

    /**
     * @var ?DOMNode
     */
    public ?\DOMNode $parentNode;

    /**
     * @var ?DOMElement
     */
    public ?\DOMElement $parentElement;

    /**
     * @var DOMNodeList
     */
    public \DOMNodeList $childNodes;

    /**
     * @var ?DOMNode
     */
    public ?\DOMNode $firstChild;

    /**
     * @var ?DOMNode
     */
    public ?\DOMNode $lastChild;

    /**
     * @var ?DOMNode
     */
    public ?\DOMNode $previousSibling;

    /**
     * @var ?DOMNode
     */
    public ?\DOMNode $nextSibling;

    /**
     * @var ?DOMNamedNodeMap
     */
    public ?\DOMNamedNodeMap $attributes;

    /**
     * @var bool
     */
    public bool $isConnected;

    /**
     * @var ?DOMDocument
     */
    public ?\DOMDocument $ownerDocument;

    /**
     * @var ?string
     */
    public ?string $namespaceURI;

    /**
     * @var string
     */
    public string $prefix;

    /**
     * @var ?string
     */
    public ?string $localName;

    /**
     * @var ?string
     */
    public ?string $baseURI;

    /**
     * @var string
     */
    public string $textContent;

    public function appendChild(\DOMNode $node)
    {
    }

    public function C14N(bool $exclusive = false, bool $withComments = false, ?array $xpath = null, ?array $nsPrefixes = null)
    {
    }

    public function C14NFile(string $uri, bool $exclusive = false, bool $withComments = false, ?array $xpath = null, ?array $nsPrefixes = null)
    {
    }

    public function cloneNode(bool $deep = false)
    {
    }

    public function getLineNo()
    {
    }

    public function getNodePath()
    {
    }

    public function hasAttributes()
    {
    }

    public function hasChildNodes()
    {
    }

    public function insertBefore(\DOMNode $node, ?\DOMNode $child = null)
    {
    }

    public function isDefaultNamespace(string $namespace)
    {
    }

    public function isSameNode(\DOMNode $otherNode)
    {
    }

    public function isEqualNode(?\DOMNode $otherNode) : bool
    {
    }

    public function isSupported(string $feature, string $version)
    {
    }

    public function lookupNamespaceURI(?string $prefix)
    {
    }

    public function lookupPrefix(string $namespace)
    {
    }

    public function normalize()
    {
    }

    public function removeChild(\DOMNode $child)
    {
    }

    public function replaceChild(\DOMNode $node, \DOMNode $child)
    {
    }

    public function contains(\DOMNameSpaceNode|\DOMNode|null $other) : bool
    {
    }

    public function getRootNode(?array $options = null) : \DOMNode
    {
    }

    public function compareDocumentPosition(\DOMNode $other) : int
    {
    }

    public function __sleep() : array
    {
    }

    public function __wakeup() : void
    {
    }
}

namespace Dom;

class Node
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

    /**
     * @var int
     */
    public int $nodeType;

    /**
     * @var string
     */
    public string $nodeName;

    /**
     * @var string
     */
    public string $baseURI;

    /**
     * @var bool
     */
    public bool $isConnected;

    /**
     * @var ?Dom\Document
     */
    public ?\Dom\Document $ownerDocument;

    /**
     * @var ?Dom\Node
     */
    public ?\Dom\Node $parentNode;

    /**
     * @var ?Dom\Element
     */
    public ?\Dom\Element $parentElement;

    /**
     * @var Dom\NodeList
     */
    public \Dom\NodeList $childNodes;

    /**
     * @var ?Dom\Node
     */
    public ?\Dom\Node $firstChild;

    /**
     * @var ?Dom\Node
     */
    public ?\Dom\Node $lastChild;

    /**
     * @var ?Dom\Node
     */
    public ?\Dom\Node $previousSibling;

    /**
     * @var ?Dom\Node
     */
    public ?\Dom\Node $nextSibling;

    /**
     * @var ?string
     */
    public ?string $nodeValue;

    /**
     * @var ?string
     */
    public ?string $textContent;

    final private function __construct()
    {
    }

    public function getRootNode(array $options = []) : \Dom\Node
    {
    }

    public function hasChildNodes() : bool
    {
    }

    public function normalize() : void
    {
    }

    public function cloneNode(bool $deep = false) : \Dom\Node
    {
    }

    public function isEqualNode(?\Dom\Node $otherNode) : bool
    {
    }

    public function isSameNode(?\Dom\Node $otherNode) : bool
    {
    }

    public function compareDocumentPosition(\Dom\Node $other) : int
    {
    }

    public function contains(?\Dom\Node $other) : bool
    {
    }

    public function lookupPrefix(?string $namespace) : ?string
    {
    }

    public function lookupNamespaceURI(?string $prefix) : ?string
    {
    }

    public function isDefaultNamespace(?string $namespace) : bool
    {
    }

    public function insertBefore(\Dom\Node $node, ?\Dom\Node $child) : \Dom\Node
    {
    }

    public function appendChild(\Dom\Node $node) : \Dom\Node
    {
    }

    public function replaceChild(\Dom\Node $node, \Dom\Node $child) : \Dom\Node
    {
    }

    public function removeChild(\Dom\Node $child) : \Dom\Node
    {
    }

    public function getLineNo() : int
    {
    }

    public function getNodePath() : string
    {
    }

    public function C14N(bool $exclusive = false, bool $withComments = false, ?array $xpath = null, ?array $nsPrefixes = null) : string|false
    {
    }

    public function C14NFile(string $uri, bool $exclusive = false, bool $withComments = false, ?array $xpath = null, ?array $nsPrefixes = null) : int|false
    {
    }

    public function __sleep() : array
    {
    }

    public function __wakeup() : void
    {
    }
}

class DOMNameSpaceNode
{
    /**
     * @var string
     */
    public string $nodeName;

    /**
     * @var ?string
     */
    public ?string $nodeValue;

    /**
     * @var int
     */
    public int $nodeType;

    /**
     * @var string
     */
    public string $prefix;

    /**
     * @var ?string
     */
    public ?string $localName;

    /**
     * @var ?string
     */
    public ?string $namespaceURI;

    /**
     * @var bool
     */
    public bool $isConnected;

    /**
     * @var ?DOMDocument
     */
    public ?\DOMDocument $ownerDocument;

    /**
     * @var ?DOMNode
     */
    public ?\DOMNode $parentNode;

    /**
     * @var ?DOMElement
     */
    public ?\DOMElement $parentElement;

    public function __sleep() : array
    {
    }

    public function __wakeup() : void
    {
    }
}

namespace Dom;

final readonly class NamespaceInfo
{
    /**
     * @var ?string
     */
    public readonly ?string $prefix;

    /**
     * @var ?string
     */
    public readonly ?string $namespaceURI;

    /**
     * @var Dom\Element
     */
    public readonly \Dom\Element $element;

    private function __construct()
    {
    }
}

class DOMDocumentFragment extends DOMNode implements DOMParentNode
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

    /**
     * @var ?DOMElement
     */
    public ?\DOMElement $firstElementChild;

    /**
     * @var ?DOMElement
     */
    public ?\DOMElement $lastElementChild;

    /**
     * @var int
     */
    public int $childElementCount;

    public function __construct()
    {
    }

    public function appendXML(string $data)
    {
    }

    public function append(... $nodes) : void
    {
    }

    public function prepend(... $nodes) : void
    {
    }

    public function replaceChildren(... $nodes) : void
    {
    }
}

namespace Dom;

class DocumentFragment extends Node implements ParentNode
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

    /**
     * @var ?Dom\Element
     */
    public ?\Dom\Element $firstElementChild;

    /**
     * @var ?Dom\Element
     */
    public ?\Dom\Element $lastElementChild;

    /**
     * @var int
     */
    public int $childElementCount;

    public function appendXml(string $data) : bool
    {
    }

    public function append(\Dom\Node|string ... $nodes) : void
    {
    }

    public function prepend(\Dom\Node|string ... $nodes) : void
    {
    }

    public function replaceChildren(\Dom\Node|string ... $nodes) : void
    {
    }

    public function querySelector(string $selectors) : ?\Dom\Element
    {
    }

    public function querySelectorAll(string $selectors) : \Dom\NodeList
    {
    }
}

namespace Dom;

abstract class Document extends Node implements ParentNode
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

    /**
     * @var Dom\Implementation
     */
    public \Dom\Implementation $implementation;

    /**
     * @var string
     */
    public string $URL;

    /**
     * @var string
     */
    public string $documentURI;

    /**
     * @var string
     */
    public string $characterSet;

    /**
     * @var string
     */
    public string $charset;

    /**
     * @var string
     */
    public string $inputEncoding;

    /**
     * @var ?Dom\DocumentType
     */
    public ?\Dom\DocumentType $doctype;

    /**
     * @var ?Dom\Element
     */
    public ?\Dom\Element $documentElement;

    /**
     * @var ?Dom\Element
     */
    public ?\Dom\Element $firstElementChild;

    /**
     * @var ?Dom\Element
     */
    public ?\Dom\Element $lastElementChild;

    /**
     * @var int
     */
    public int $childElementCount;

    /**
     * @var ?Dom\HTMLElement
     */
    public ?\Dom\HTMLElement $body;

    /**
     * @var ?Dom\HTMLElement
     */
    public ?\Dom\HTMLElement $head;

    /**
     * @var string
     */
    public string $title;

    public function getElementsByTagName(string $qualifiedName) : \Dom\HTMLCollection
    {
    }

    public function getElementsByTagNameNS(?string $namespace, string $localName) : \Dom\HTMLCollection
    {
    }

    public function createElement(string $localName) : \Dom\Element
    {
    }

    public function createElementNS(?string $namespace, string $qualifiedName) : \Dom\Element
    {
    }

    public function createDocumentFragment() : \Dom\DocumentFragment
    {
    }

    public function createTextNode(string $data) : \Dom\Text
    {
    }

    public function createCDATASection(string $data) : \Dom\CDATASection
    {
    }

    public function createComment(string $data) : \Dom\Comment
    {
    }

    public function createProcessingInstruction(string $target, string $data) : \Dom\ProcessingInstruction
    {
    }

    public function importNode(?\Dom\Node $node, bool $deep = false) : \Dom\Node
    {
    }

    public function adoptNode(\Dom\Node $node) : \Dom\Node
    {
    }

    public function createAttribute(string $localName) : \Dom\Attr
    {
    }

    public function createAttributeNS(?string $namespace, string $qualifiedName) : \Dom\Attr
    {
    }

    public function getElementById(string $elementId) : ?\Dom\Element
    {
    }

    public function registerNodeClass(string $baseClass, ?string $extendedClass) : void
    {
    }

    public function schemaValidate(string $filename, int $flags = 0) : bool
    {
    }

    public function schemaValidateSource(string $source, int $flags = 0) : bool
    {
    }

    public function relaxNgValidate(string $filename) : bool
    {
    }

    public function relaxNgValidateSource(string $source) : bool
    {
    }

    public function append(\Dom\Node|string ... $nodes) : void
    {
    }

    public function prepend(\Dom\Node|string ... $nodes) : void
    {
    }

    public function replaceChildren(\Dom\Node|string ... $nodes) : void
    {
    }

    public function importLegacyNode(\DOMNode $node, bool $deep = false) : \Dom\Node
    {
    }

    public function querySelector(string $selectors) : ?\Dom\Element
    {
    }

    public function querySelectorAll(string $selectors) : \Dom\NodeList
    {
    }
}

class DOMDocument extends DOMNode implements DOMParentNode
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

    /**
     * @var ?DOMDocumentType
     */
    public ?\DOMDocumentType $doctype;

    /**
     * @var DOMImplementation
     */
    public \DOMImplementation $implementation;

    /**
     * @var ?DOMElement
     */
    public ?\DOMElement $documentElement;

    /**
     * @var ?string
     */
    public ?string $actualEncoding;

    /**
     * @var ?string
     */
    public ?string $encoding;

    /**
     * @var ?string
     */
    public ?string $xmlEncoding;

    /**
     * @var bool
     */
    public bool $standalone;

    /**
     * @var bool
     */
    public bool $xmlStandalone;

    /**
     * @var ?string
     */
    public ?string $version;

    /**
     * @var ?string
     */
    public ?string $xmlVersion;

    /**
     * @var bool
     */
    public bool $strictErrorChecking;

    /**
     * @var ?string
     */
    public ?string $documentURI;

    /**
     * @var mixed
     */
    public mixed $config;

    /**
     * @var bool
     */
    public bool $formatOutput;

    /**
     * @var bool
     */
    public bool $validateOnParse;

    /**
     * @var bool
     */
    public bool $resolveExternals;

    /**
     * @var bool
     */
    public bool $preserveWhiteSpace;

    /**
     * @var bool
     */
    public bool $recover;

    /**
     * @var bool
     */
    public bool $substituteEntities;

    /**
     * @var ?DOMElement
     */
    public ?\DOMElement $firstElementChild;

    /**
     * @var ?DOMElement
     */
    public ?\DOMElement $lastElementChild;

    /**
     * @var int
     */
    public int $childElementCount;

    public function __construct(string $version = '1.0', string $encoding = '')
    {
    }

    public function createAttribute(string $localName)
    {
    }

    public function createAttributeNS(?string $namespace, string $qualifiedName)
    {
    }

    public function createCDATASection(string $data)
    {
    }

    public function createComment(string $data)
    {
    }

    public function createDocumentFragment()
    {
    }

    public function createElement(string $localName, string $value = '')
    {
    }

    public function createElementNS(?string $namespace, string $qualifiedName, string $value = '')
    {
    }

    public function createEntityReference(string $name)
    {
    }

    public function createProcessingInstruction(string $target, string $data = '')
    {
    }

    public function createTextNode(string $data)
    {
    }

    public function getElementById(string $elementId)
    {
    }

    public function getElementsByTagName(string $qualifiedName)
    {
    }

    public function getElementsByTagNameNS(?string $namespace, string $localName)
    {
    }

    public function importNode(\DOMNode $node, bool $deep = false)
    {
    }

    public function load(string $filename, int $options = 0)
    {
    }

    public function loadXML(string $source, int $options = 0)
    {
    }

    public function normalizeDocument()
    {
    }

    public function registerNodeClass(string $baseClass, ?string $extendedClass)
    {
    }

    public function save(string $filename, int $options = 0)
    {
    }

    public function loadHTML(string $source, int $options = 0)
    {
    }

    public function loadHTMLFile(string $filename, int $options = 0)
    {
    }

    public function saveHTML(?\DOMNode $node = null)
    {
    }

    public function saveHTMLFile(string $filename)
    {
    }

    public function saveXML(?\DOMNode $node = null, int $options = 0)
    {
    }

    public function schemaValidate(string $filename, int $flags = 0)
    {
    }

    public function schemaValidateSource(string $source, int $flags = 0)
    {
    }

    public function relaxNGValidate(string $filename)
    {
    }

    public function relaxNGValidateSource(string $source)
    {
    }

    public function validate()
    {
    }

    public function xinclude(int $options = 0)
    {
    }

    public function adoptNode(\DOMNode $node)
    {
    }

    public function append(... $nodes) : void
    {
    }

    public function prepend(... $nodes) : void
    {
    }

    public function replaceChildren(... $nodes) : void
    {
    }
}

namespace Dom;

final class HTMLDocument extends Document
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

    public static function createEmpty(string $encoding = 'UTF-8') : \Dom\HTMLDocument
    {
    }

    public static function createFromFile(string $path, int $options = 0, ?string $overrideEncoding = null) : \Dom\HTMLDocument
    {
    }

    public static function createFromString(string $source, int $options = 0, ?string $overrideEncoding = null) : \Dom\HTMLDocument
    {
    }

    public function saveXml(?\Dom\Node $node = null, int $options = 0) : string|false
    {
    }

    public function saveXmlFile(string $filename, int $options = 0) : int|false
    {
    }

    public function saveHtml(?\Dom\Node $node = null) : string
    {
    }

    public function saveHtmlFile(string $filename) : int|false
    {
    }
}

namespace Dom;

final class XMLDocument extends Document
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

    /**
     * @var string
     */
    public string $xmlEncoding;

    /**
     * @var bool
     */
    public bool $xmlStandalone;

    /**
     * @var string
     */
    public string $xmlVersion;

    /**
     * @var bool
     */
    public bool $formatOutput;

    public static function createEmpty(string $version = '1.0', string $encoding = 'UTF-8') : \Dom\XMLDocument
    {
    }

    public static function createFromFile(string $path, int $options = 0, ?string $overrideEncoding = null) : \Dom\XMLDocument
    {
    }

    public static function createFromString(string $source, int $options = 0, ?string $overrideEncoding = null) : \Dom\XMLDocument
    {
    }

    public function createEntityReference(string $name) : \Dom\EntityReference
    {
    }

    public function validate() : bool
    {
    }

    public function xinclude(int $options = 0) : int
    {
    }

    public function saveXml(?\Dom\Node $node = null, int $options = 0) : string|false
    {
    }

    public function saveXmlFile(string $filename, int $options = 0) : int|false
    {
    }
}

use Countable;
use IteratorAggregate;

class DOMNodeList implements IteratorAggregate, Countable
{
    /**
     * @var int
     */
    public int $length;

    public function count()
    {
    }

    public function getIterator() : \Iterator
    {
    }

    public function item(int $index)
    {
    }
}

namespace Dom;

class NodeList implements \IteratorAggregate, \Countable
{
    /**
     * @var int
     */
    public int $length;

    public function count() : int
    {
    }

    public function getIterator() : \Iterator
    {
    }

    public function item(int $index) : ?\Dom\Node
    {
    }
}

use Countable;
use IteratorAggregate;

class DOMNamedNodeMap implements IteratorAggregate, Countable
{
    /**
     * @var int
     */
    public int $length;

    public function getNamedItem(string $qualifiedName)
    {
    }

    public function getNamedItemNS(?string $namespace, string $localName)
    {
    }

    public function item(int $index)
    {
    }

    public function count()
    {
    }

    public function getIterator() : \Iterator
    {
    }
}

namespace Dom;

class NamedNodeMap implements \IteratorAggregate, \Countable
{
    /**
     * @var int
     */
    public int $length;

    public function item(int $index) : ?\Dom\Attr
    {
    }

    public function getNamedItem(string $qualifiedName) : ?\Dom\Attr
    {
    }

    public function getNamedItemNS(?string $namespace, string $localName) : ?\Dom\Attr
    {
    }

    public function count() : int
    {
    }

    public function getIterator() : \Iterator
    {
    }
}

namespace Dom;

class DtdNamedNodeMap implements \IteratorAggregate, \Countable
{
    /**
     * @var int
     */
    public int $length;

    public function item(int $index) : \Dom\Entity|\Dom\Notation|null
    {
    }

    public function getNamedItem(string $qualifiedName) : \Dom\Entity|\Dom\Notation|null
    {
    }

    public function getNamedItemNS(?string $namespace, string $localName) : \Dom\Entity|\Dom\Notation|null
    {
    }

    public function count() : int
    {
    }

    public function getIterator() : \Iterator
    {
    }
}

namespace Dom;

class HTMLCollection implements \IteratorAggregate, \Countable
{
    /**
     * @var int
     */
    public int $length;

    public function item(int $index) : ?\Dom\Element
    {
    }

    public function namedItem(string $key) : ?\Dom\Element
    {
    }

    public function count() : int
    {
    }

    public function getIterator() : \Iterator
    {
    }
}

class DOMCharacterData extends DOMNode implements DOMChildNode
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

    /**
     * @var string
     */
    public string $data;

    /**
     * @var int
     */
    public int $length;

    /**
     * @var ?DOMElement
     */
    public ?\DOMElement $previousElementSibling;

    /**
     * @var ?DOMElement
     */
    public ?\DOMElement $nextElementSibling;

    public function appendData(string $data)
    {
    }

    public function substringData(int $offset, int $count)
    {
    }

    public function insertData(int $offset, string $data)
    {
    }

    public function deleteData(int $offset, int $count)
    {
    }

    public function replaceData(int $offset, int $count, string $data)
    {
    }

    public function replaceWith(... $nodes) : void
    {
    }

    public function remove() : void
    {
    }

    public function before(... $nodes) : void
    {
    }

    public function after(... $nodes) : void
    {
    }
}

namespace Dom;

class CharacterData extends Node implements ChildNode
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

    /**
     * @var ?Dom\Element
     */
    public ?\Dom\Element $previousElementSibling;

    /**
     * @var ?Dom\Element
     */
    public ?\Dom\Element $nextElementSibling;

    /**
     * @var string
     */
    public string $data;

    /**
     * @var int
     */
    public int $length;

    public function substringData(int $offset, int $count) : string
    {
    }

    public function appendData(string $data) : void
    {
    }

    public function insertData(int $offset, string $data) : void
    {
    }

    public function deleteData(int $offset, int $count) : void
    {
    }

    public function replaceData(int $offset, int $count, string $data) : void
    {
    }

    public function remove() : void
    {
    }

    public function before(\Dom\Node|string ... $nodes) : void
    {
    }

    public function after(\Dom\Node|string ... $nodes) : void
    {
    }

    public function replaceWith(\Dom\Node|string ... $nodes) : void
    {
    }
}

class DOMAttr extends DOMNode
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

    /**
     * @var string
     */
    public string $name;

    /**
     * @var bool
     */
    public bool $specified;

    /**
     * @var string
     */
    public string $value;

    /**
     * @var ?DOMElement
     */
    public ?\DOMElement $ownerElement;

    /**
     * @var mixed
     */
    public mixed $schemaTypeInfo;

    public function __construct(string $name, string $value = '')
    {
    }

    public function isId()
    {
    }
}

namespace Dom;

class Attr extends Node
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

    /**
     * @var ?string
     */
    public ?string $namespaceURI;

    /**
     * @var ?string
     */
    public ?string $prefix;

    /**
     * @var string
     */
    public string $localName;

    /**
     * @var string
     */
    public string $name;

    /**
     * @var string
     */
    public string $value;

    /**
     * @var ?Dom\Element
     */
    public ?\Dom\Element $ownerElement;

    /**
     * @var bool
     */
    public bool $specified;

    public function isId() : bool
    {
    }

    public function rename(?string $namespaceURI, string $qualifiedName) : void
    {
    }
}

class DOMElement extends DOMNode implements DOMParentNode, DOMChildNode
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

    /**
     * @var string
     */
    public string $tagName;

    /**
     * @var string
     */
    public string $className;

    /**
     * @var string
     */
    public string $id;

    /**
     * @var mixed
     */
    public mixed $schemaTypeInfo;

    /**
     * @var ?DOMElement
     */
    public ?\DOMElement $firstElementChild;

    /**
     * @var ?DOMElement
     */
    public ?\DOMElement $lastElementChild;

    /**
     * @var int
     */
    public int $childElementCount;

    /**
     * @var ?DOMElement
     */
    public ?\DOMElement $previousElementSibling;

    /**
     * @var ?DOMElement
     */
    public ?\DOMElement $nextElementSibling;

    public function __construct(string $qualifiedName, ?string $value = null, string $namespace = '')
    {
    }

    public function getAttribute(string $qualifiedName)
    {
    }

    public function getAttributeNames() : array
    {
    }

    public function getAttributeNS(?string $namespace, string $localName)
    {
    }

    public function getAttributeNode(string $qualifiedName)
    {
    }

    public function getAttributeNodeNS(?string $namespace, string $localName)
    {
    }

    public function getElementsByTagName(string $qualifiedName)
    {
    }

    public function getElementsByTagNameNS(?string $namespace, string $localName)
    {
    }

    public function hasAttribute(string $qualifiedName)
    {
    }

    public function hasAttributeNS(?string $namespace, string $localName)
    {
    }

    public function removeAttribute(string $qualifiedName)
    {
    }

    public function removeAttributeNS(?string $namespace, string $localName)
    {
    }

    public function removeAttributeNode(\DOMAttr $attr)
    {
    }

    public function setAttribute(string $qualifiedName, string $value)
    {
    }

    public function setAttributeNS(?string $namespace, string $qualifiedName, string $value)
    {
    }

    public function setAttributeNode(\DOMAttr $attr)
    {
    }

    public function setAttributeNodeNS(\DOMAttr $attr)
    {
    }

    public function setIdAttribute(string $qualifiedName, bool $isId)
    {
    }

    public function setIdAttributeNS(string $namespace, string $qualifiedName, bool $isId)
    {
    }

    public function setIdAttributeNode(\DOMAttr $attr, bool $isId)
    {
    }

    public function toggleAttribute(string $qualifiedName, ?bool $force = null) : bool
    {
    }

    public function remove() : void
    {
    }

    public function before(... $nodes) : void
    {
    }

    public function after(... $nodes) : void
    {
    }

    public function replaceWith(... $nodes) : void
    {
    }

    public function append(... $nodes) : void
    {
    }

    public function prepend(... $nodes) : void
    {
    }

    public function replaceChildren(... $nodes) : void
    {
    }

    public function insertAdjacentElement(string $where, \DOMElement $element) : ?\DOMElement
    {
    }

    public function insertAdjacentText(string $where, string $data) : void
    {
    }
}

namespace Dom;

class Element extends Node implements ParentNode, ChildNode
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

    /**
     * @var ?string
     */
    public ?string $namespaceURI;

    /**
     * @var ?string
     */
    public ?string $prefix;

    /**
     * @var string
     */
    public string $localName;

    /**
     * @var string
     */
    public string $tagName;

    /**
     * @var string
     */
    public string $id;

    /**
     * @var string
     */
    public string $className;

    /**
     * @var Dom\TokenList
     */
    public \Dom\TokenList $classList;

    /**
     * @var Dom\NamedNodeMap
     */
    public \Dom\NamedNodeMap $attributes;

    /**
     * @var ?Dom\Element
     */
    public ?\Dom\Element $firstElementChild;

    /**
     * @var ?Dom\Element
     */
    public ?\Dom\Element $lastElementChild;

    /**
     * @var int
     */
    public int $childElementCount;

    /**
     * @var ?Dom\Element
     */
    public ?\Dom\Element $previousElementSibling;

    /**
     * @var ?Dom\Element
     */
    public ?\Dom\Element $nextElementSibling;

    /**
     * @var string
     */
    public string $innerHTML;

    /**
     * @var string
     */
    public string $substitutedNodeValue;

    public function hasAttributes() : bool
    {
    }

    public function getAttributeNames() : array
    {
    }

    public function getAttribute(string $qualifiedName) : ?string
    {
    }

    public function getAttributeNS(?string $namespace, string $localName) : ?string
    {
    }

    public function setAttribute(string $qualifiedName, string $value) : void
    {
    }

    public function setAttributeNS(?string $namespace, string $qualifiedName, string $value) : void
    {
    }

    public function removeAttribute(string $qualifiedName) : void
    {
    }

    public function removeAttributeNS(?string $namespace, string $localName) : void
    {
    }

    public function toggleAttribute(string $qualifiedName, ?bool $force = null) : bool
    {
    }

    public function hasAttribute(string $qualifiedName) : bool
    {
    }

    public function hasAttributeNS(?string $namespace, string $localName) : bool
    {
    }

    public function getAttributeNode(string $qualifiedName) : ?\Dom\Attr
    {
    }

    public function getAttributeNodeNS(?string $namespace, string $localName) : ?\Dom\Attr
    {
    }

    public function setAttributeNode(\Dom\Attr $attr) : ?\Dom\Attr
    {
    }

    public function setAttributeNodeNS(\Dom\Attr $attr) : ?\Dom\Attr
    {
    }

    public function removeAttributeNode(\Dom\Attr $attr) : \Dom\Attr
    {
    }

    public function getElementsByTagName(string $qualifiedName) : \Dom\HTMLCollection
    {
    }

    public function getElementsByTagNameNS(?string $namespace, string $localName) : \Dom\HTMLCollection
    {
    }

    public function insertAdjacentElement(\Dom\AdjacentPosition $where, \Dom\Element $element) : ?\Dom\Element
    {
    }

    public function insertAdjacentText(\Dom\AdjacentPosition $where, string $data) : void
    {
    }

    public function setIdAttribute(string $qualifiedName, bool $isId) : void
    {
    }

    public function setIdAttributeNS(?string $namespace, string $qualifiedName, bool $isId) : void
    {
    }

    public function setIdAttributeNode(\Dom\Attr $attr, bool $isId) : void
    {
    }

    public function remove() : void
    {
    }

    public function before(\Dom\Node|string ... $nodes) : void
    {
    }

    public function after(\Dom\Node|string ... $nodes) : void
    {
    }

    public function replaceWith(\Dom\Node|string ... $nodes) : void
    {
    }

    public function append(\Dom\Node|string ... $nodes) : void
    {
    }

    public function prepend(\Dom\Node|string ... $nodes) : void
    {
    }

    public function replaceChildren(\Dom\Node|string ... $nodes) : void
    {
    }

    public function querySelector(string $selectors) : ?\Dom\Element
    {
    }

    public function querySelectorAll(string $selectors) : \Dom\NodeList
    {
    }

    public function closest(string $selectors) : ?\Dom\Element
    {
    }

    public function matches(string $selectors) : bool
    {
    }

    public function getInScopeNamespaces() : array
    {
    }

    public function getDescendantNamespaces() : array
    {
    }

    public function rename(?string $namespaceURI, string $qualifiedName) : void
    {
    }
}

namespace Dom;

class HTMLElement extends Element
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;
}

class DOMText extends DOMCharacterData
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

    /**
     * @var string
     */
    public string $wholeText;

    public function __construct(string $data = '')
    {
    }

    public function isWhitespaceInElementContent()
    {
    }

    public function isElementContentWhitespace()
    {
    }

    public function splitText(int $offset)
    {
    }
}

namespace Dom;

class Text extends CharacterData
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

    /**
     * @var string
     */
    public string $wholeText;

    public function splitText(int $offset) : \Dom\Text
    {
    }
}

class DOMComment extends DOMCharacterData
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

    public function __construct(string $data = '')
    {
    }
}

namespace Dom;

class Comment extends CharacterData
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;
}

class DOMCdataSection extends DOMText
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

    public function __construct(string $data)
    {
    }
}

namespace Dom;

class CDATASection extends Text
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;
}

class DOMDocumentType extends DOMNode
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

    /**
     * @var string
     */
    public string $name;

    /**
     * @var DOMNamedNodeMap
     */
    public \DOMNamedNodeMap $entities;

    /**
     * @var DOMNamedNodeMap
     */
    public \DOMNamedNodeMap $notations;

    /**
     * @var string
     */
    public string $publicId;

    /**
     * @var string
     */
    public string $systemId;

    /**
     * @var ?string
     */
    public ?string $internalSubset;
}

namespace Dom;

class DocumentType extends Node implements ChildNode
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

    /**
     * @var string
     */
    public string $name;

    /**
     * @var Dom\DtdNamedNodeMap
     */
    public \Dom\DtdNamedNodeMap $entities;

    /**
     * @var Dom\DtdNamedNodeMap
     */
    public \Dom\DtdNamedNodeMap $notations;

    /**
     * @var string
     */
    public string $publicId;

    /**
     * @var string
     */
    public string $systemId;

    /**
     * @var ?string
     */
    public ?string $internalSubset;

    public function remove() : void
    {
    }

    public function before(\Dom\Node|string ... $nodes) : void
    {
    }

    public function after(\Dom\Node|string ... $nodes) : void
    {
    }

    public function replaceWith(\Dom\Node|string ... $nodes) : void
    {
    }
}

class DOMNotation extends DOMNode
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

    /**
     * @var string
     */
    public string $publicId;

    /**
     * @var string
     */
    public string $systemId;
}

namespace Dom;

class Notation extends Node
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

    /**
     * @var string
     */
    public string $publicId;

    /**
     * @var string
     */
    public string $systemId;
}

class DOMEntity extends DOMNode
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

    /**
     * @var ?string
     */
    public ?string $publicId;

    /**
     * @var ?string
     */
    public ?string $systemId;

    /**
     * @var ?string
     */
    public ?string $notationName;

    /**
     * @var ?string
     */
    public ?string $actualEncoding;

    /**
     * @var ?string
     */
    public ?string $encoding;

    /**
     * @var ?string
     */
    public ?string $version;
}

namespace Dom;

class Entity extends Node
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

    /**
     * @var ?string
     */
    public ?string $publicId;

    /**
     * @var ?string
     */
    public ?string $systemId;

    /**
     * @var ?string
     */
    public ?string $notationName;
}

class DOMEntityReference extends DOMNode
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

    public function __construct(string $name)
    {
    }
}

namespace Dom;

class EntityReference extends Node
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;
}

class DOMProcessingInstruction extends DOMNode
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

    /**
     * @var string
     */
    public string $target;

    /**
     * @var string
     */
    public string $data;

    public function __construct(string $name, string $value = '')
    {
    }
}

namespace Dom;

class ProcessingInstruction extends CharacterData
{
    public const DOCUMENT_POSITION_DISCONNECTED = 1;

    public const DOCUMENT_POSITION_PRECEDING = 2;

    public const DOCUMENT_POSITION_FOLLOWING = 4;

    public const DOCUMENT_POSITION_CONTAINS = 8;

    public const DOCUMENT_POSITION_CONTAINED_BY = 16;

    public const DOCUMENT_POSITION_IMPLEMENTATION_SPECIFIC = 32;

    /**
     * @var string
     */
    public string $target;
}

class DOMXPath
{
    /**
     * @var DOMDocument
     */
    public \DOMDocument $document;

    /**
     * @var bool
     */
    public bool $registerNodeNamespaces;

    public function __construct(\DOMDocument $document, bool $registerNodeNS = true)
    {
    }

    public function evaluate(string $expression, ?\DOMNode $contextNode = null, bool $registerNodeNS = true)
    {
    }

    public function query(string $expression, ?\DOMNode $contextNode = null, bool $registerNodeNS = true)
    {
    }

    public function registerNamespace(string $prefix, string $namespace)
    {
    }

    public function registerPhpFunctions(string|array|null $restrict = null)
    {
    }

    public function registerPhpFunctionNS(string $namespaceURI, string $name, callable $callable) : void
    {
    }

    public static function quote(string $str) : string
    {
    }
}

namespace Dom;

final class XPath
{
    /**
     * @var Dom\Document
     */
    public \Dom\Document $document;

    /**
     * @var bool
     */
    public bool $registerNodeNamespaces;

    public function __construct(\Dom\Document $document, bool $registerNodeNS = true)
    {
    }

    public function evaluate(string $expression, ?\Dom\Node $contextNode = null, bool $registerNodeNS = true) : \Dom\NodeList|bool|float|string|null
    {
    }

    public function query(string $expression, ?\Dom\Node $contextNode = null, bool $registerNodeNS = true) : \Dom\NodeList
    {
    }

    public function registerNamespace(string $prefix, string $namespace) : bool
    {
    }

    public function registerPhpFunctions(string|array|null $restrict = null) : void
    {
    }

    public function registerPhpFunctionNS(string $namespaceURI, string $name, callable $callable) : void
    {
    }

    public static function quote(string $str) : string
    {
    }
}

namespace Dom;

final class TokenList implements \IteratorAggregate, \Countable
{
    /**
     * @var int
     */
    public int $length;

    /**
     * @var string
     */
    public string $value;

    private function __construct()
    {
    }

    public function item(int $index) : ?string
    {
    }

    public function contains(string $token) : bool
    {
    }

    public function add(string ... $tokens) : void
    {
    }

    public function remove(string ... $tokens) : void
    {
    }

    public function toggle(string $token, ?bool $force = null) : bool
    {
    }

    public function replace(string $token, string $newToken) : bool
    {
    }

    public function supports(string $token) : bool
    {
    }

    public function count() : int
    {
    }

    public function getIterator() : \Iterator
    {
    }
}

