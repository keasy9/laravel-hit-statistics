<?php

namespace Keasy9\HitStatistics\Dto;

use Illuminate\Support\Collection;

class HitsAggregationDto
{
    public function __construct(
        public Collection $data,
        public array $aggregations,
    ) {
    }

    public function toArray(): array
    {
        return $this->data->map(fn($item) => $item->toArray())
            ->groupBy(end($this->aggregations))
            ->toArray();
    }

    public function getAggregations(): array
    {
        return $this->aggregations;
    }

    public function getData(): Collection
    {
        return $this->data;
    }

    public function isEmpty(): bool
    {
        return $this->data->isEmpty();
    }
}