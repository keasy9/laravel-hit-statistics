<?php

namespace Keasy9\HitStatistics\Dto;

class OrchidAggregationDto extends HitsAggregationDto
{
    public function toArray(bool $normalizeKeys = false): array
    {
        return $normalizeKeys ? $this->toArrayNormalized() : $this->toArraySimple();
    }

    protected function toArrayNormalized(): array
    {
        $data = $this->data->groupBy(reset($this->aggregations));

        $allKeys = [];
        $dataset = [];
        foreach ($data as $key => $Hits) {
            $keys = $Hits->pluck(end($this->aggregations))->toArray();
            $allKeys = array_merge($allKeys, $keys);

            $dataset[$key] = array_combine(
                $keys,
                $Hits->pluck('count')->toArray(),
            );
        }

        sort($allKeys);

        $default = array_fill_keys($allKeys, 0);
        foreach ($dataset as $key => &$Hits) {

            $Hits = array_merge($default, $Hits);

            $Hits = [
                'name' => $key,
                'labels' => array_keys($Hits),
                'values' => array_values($Hits),
            ];
        }

        return array_values($dataset);
    }

    protected function toArraySimple(): array
    {
        $data = $this->data->groupBy(reset($this->aggregations));

        $dataset = [];

        foreach ($data as $key => &$Hits) {
            $Hits = $Hits->sortBy(end($this->aggregations));

            $dataset[] = [
                'labels' => $Hits->pluck(end($this->aggregations))->toArray(),
                'name' => $key,
                'values' => $Hits->pluck('count')->toArray(),
            ];
        }

        return $dataset;
    }
}