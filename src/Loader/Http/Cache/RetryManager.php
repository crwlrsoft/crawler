<?php

namespace Crwlr\Crawler\Loader\Http\Cache;

/**
 * @internal
 */
class RetryManager
{
    /**
     * @param int[]|null $only
     * @param int[]|null $except
     */
    public function __construct(
        private ?array $only = null,
        private ?array $except = null,
    ) {}

    /**
     * @param int|int[] $statusCodes
     */
    public function only(int|array $statusCodes): static
    {
        $statusCodes = is_array($statusCodes) ? $statusCodes : [$statusCodes];

        $this->only = $statusCodes;

        return $this;
    }

    /**
     * @param int|int[] $statusCodes
     */
    public function except(int|array $statusCodes): static
    {
        $statusCodes = is_array($statusCodes) ? $statusCodes : [$statusCodes];

        $this->except = $statusCodes;

        return $this;
    }

    public function shallBeRetried(int $statusCode): bool
    {
        return $statusCode >= 400 &&
            ($this->except === null || !in_array($statusCode, $this->except, true)) &&
            ($this->only === null || in_array($statusCode, $this->only, true));
    }
}
