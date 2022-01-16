<?php

namespace Crwlr\Crawler\Steps\Loading;

use Closure;
use Crwlr\Crawler\Input;
use Crwlr\Url\Psr\Uri;
use Crwlr\Url\Url;
use GuzzleHttp\Psr7\Request;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

class Http extends LoadingStep
{
    protected RequestInterface $request;
    protected ?Closure $beforeInvokeCallback;

    public function __construct(RequestInterface $request, ?Closure $beforeInvokeCallback = null)
    {
        $this->request = $request;
        $this->beforeInvokeCallback = $beforeInvokeCallback;
    }

    public static function get(?Closure $beforeInvokeCallback = null): self
    {
        return new self(self::getRequestObjectWithMethod('GET'), $beforeInvokeCallback);
    }

    public static function post(?Closure $beforeInvokeCallback = null): self
    {
        return new self(self::getRequestObjectWithMethod('POST'), $beforeInvokeCallback);
    }

    public static function put(?Closure $beforeInvokeCallback = null): self
    {
        return new self(self::getRequestObjectWithMethod('PUT'), $beforeInvokeCallback);
    }

    public static function patch(?Closure $beforeInvokeCallback = null): self
    {
        return new self(self::getRequestObjectWithMethod('PATCH'), $beforeInvokeCallback);
    }

    public static function delete(?Closure $beforeInvokeCallback = null): self
    {
        return new self(self::getRequestObjectWithMethod('DELETE'), $beforeInvokeCallback);
    }

    public function validateAndSanitizeInput(Input $input): UriInterface
    {
        $inputValue = $input->get();

        if ($inputValue instanceof UriInterface) {
            return $inputValue;
        }

        if (is_string($inputValue)) {
            return Url::parsePsr7($inputValue);
        }

        throw new InvalidArgumentException('Input must be string or an instance of the PSR-7 UriInterface');
    }

    public function invoke(Input $input): array
    {
        $request = $this->request->withUri($input->get());

        if ($this->beforeInvokeCallback) {
            $callbackReturnValue = call_user_func($this->beforeInvokeCallback, $request);

            if ($callbackReturnValue instanceof RequestInterface) {
                $request = $callbackReturnValue;
            }
        }

        return $this->output(
            $this->loader->load($request),
            $input
        );
    }

    /*protected function withMethod(string $method): static
    {
        return new static($this->request->withMethod($method));
    }

    public function withHeader(string $name, string $value): static
    {
        return new static($this->request->withHeader($name, $value));
    }

    public function withAddedHeader(string $name, string $value): static
    {
        return new static($this->request->withHeader($name, $value));
    }

    public function withBody(string|StreamInterface $body): static
    {
        return new static($this->request->withBody($body));
    }

    public function withProtocolVersion(string $protocolVersion): static
    {
        return new static($this->request->withProtocolVersion($protocolVersion));
    }*/

    protected static function getRequestObjectWithMethod(string $method = 'GET'): RequestInterface
    {
        return new Request('GET', new Uri('/')); // uri is just a placeholder, is set in invoke method.
    }
}
