<?php

namespace Crwlr\Crawler\Steps\Filters;

use Crwlr\Crawler\Steps\BaseStep;
use Exception;
use InvalidArgumentException;

trait Filterable
{
    /**
     * @var FilterInterface[]
     */
    protected array $filters = [];

    public function where(string|FilterInterface $keyOrFilter, ?FilterInterface $filter = null): static
    {
        if (is_string($keyOrFilter) && $filter === null) {
            throw new InvalidArgumentException('You have to provide a Filter (instance of FilterInterface)');
        } elseif (is_string($keyOrFilter)) {
            if ($this instanceof BaseStep && $this->isOutputKeyAlias($keyOrFilter)) {
                $keyOrFilter = $this->getOutputKeyAliasRealKey($keyOrFilter);
            }

            $filter->useKey($keyOrFilter);

            $this->filters[] = $filter;
        } else {
            $this->filters[] = $keyOrFilter;
        }

        return $this;
    }

    /**
     * @throws Exception
     */
    public function orWhere(string|FilterInterface $keyOrFilter, ?FilterInterface $filter = null): static
    {
        if (empty($this->filters)) {
            throw new Exception('No where before orWhere');
        } elseif (is_string($keyOrFilter) && $filter === null) {
            throw new InvalidArgumentException('You have to provide a Filter (instance of FilterInterface)');
        } elseif (is_string($keyOrFilter)) {
            $filter->useKey($keyOrFilter);
        } else {
            $filter = $keyOrFilter;
        }

        $lastFilter = end($this->filters);

        $lastFilter->addOr($filter);

        return $this;
    }

    protected function passesAllFilters(mixed $output): bool
    {
        foreach ($this->filters as $filter) {
            if (!$filter->evaluate($output)) {
                if ($filter->getOr()) {
                    $orFilter = $filter->getOr();

                    while ($orFilter) {
                        if ($orFilter->evaluate($output)) {
                            continue 2;
                        }

                        $orFilter = $orFilter->getOr();
                    }
                }

                return false;
            }
        }

        return true;
    }
}
