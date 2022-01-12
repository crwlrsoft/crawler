<?php

namespace Crwlr\Crawler;

class Results extends Collection
{
    /**
     * @return array[]
     */
    public function allToArray(): array
    {
        return array_map(function ($result) {
            return $result->toArray();
        }, $this->items);
    }
}
