<?php

namespace Crwlr\Crawler;

class Results extends Collection
{
    /**
     * @return mixed[]
     */
    public function allToArray(): array
    {
        return array_map(function (Result $result) {
            return $result->toArray();
        }, $this->all());
    }
}
