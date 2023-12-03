<?php

declare(strict_types = 1);

namespace App\Traits;

use App\Models\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

trait SearchQuery
{
    /**
     * Parse the "q" query param and qualify the query.
     *
     * @param Model $model
     * @param Builder $query
     * @param mixed $dataQ
     * @return void
     */
    public function qualifyCollectionQueryWithQ(
        Model $model,
        Builder $query,
        mixed $dataQ,
    ): void {
        if (empty($dataQ) || !method_exists($model, 'getSearchableFields')) {
            return;
        }

        // Manage params likes ?q={"$or": [{"id": "2","type": "poney"}, {"id": "3","type": "mouton"}]}
        if (!is_array($dataQ) && Str::contains($dataQ, 'or')
            && str_starts_with($dataQ, '{') && str_ends_with($dataQ, '}')) {
            $jsonParams = json_decode($dataQ);
            if (array_key_exists('or', $jsonParams)) {
                $first = true;
                foreach ($jsonParams->or as $elem) {
                    $elem = json_decode($elem);
                    if ($first) {
                        $query->where(function ($q) use ($elem) {
                            foreach (array_keys((array)$elem) as $key) {
                                $value = str_replace('\\', '\\\\', $elem->$key);
                                $q->where($key, 'LIKE', '%' . $value . '%');
                            }
                        });
                        $first = false;
                    } else {
                        $query->orWhere(function ($q) use ($elem) {
                            foreach (array_keys((array)$elem) as $key) {
                                $value = str_replace('\\', '\\\\', $elem->$key);
                                $q->where($key, 'LIKE', '%' . $value . '%');
                            }
                        });
                    }
                }
            }
        }
        if (is_array($dataQ)) {
            foreach ($dataQ as $valueSearch) {
                $query->where(function ($q) use ($valueSearch, $model) {
                    $first = true;
                    foreach ($model->getSearchableFields() as $key) {
                        if ($first) {
                            $q->where($key, 'LIKE', '%' . $valueSearch . '%');
                        } else {
                            $q->orWhere($key, 'LIKE', '%' . $valueSearch . '%');
                        }
                        $first = false;
                    }
                });
            }
        } else {
            $val = $dataQ;
            $query->where(function ($q) use ($val, $model) {
                $first = true;
                foreach ($model->getSearchableFields() as $key) {
                    if ($first) {
                        $q->where($key, 'LIKE', '%' . $val . '%');
                    } else {
                        $q->orWhere($key, 'LIKE', '%' . $val . '%');
                    }
                    $first = false;
                }
            });
        }
    }

    /**
     * Parse the filters and automatically qualify the collection query.
     *
     * @param Builder $query
     * @param array $filters
     */
    protected function qualifyCollectionQueryWithAutoFilter(Builder $query, array $filters): void
    {
        foreach (Arr::except($filters, $this->customFilters) as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $subk => $subv) {
                    $query->where($k, $subk, $subv);
                }
            } elseif (str_contains($v, '|')) {
                $query->whereIn($k, explode('|', $v));
            } else {
                $query->where($k, $v);
            }
        }
    }

    /**
     * Remove unnecessary operator = from HTTP filers.
     *
     * @param array $filters
     * @return Collection
     */
    protected function cleanFilters(array $filters): Collection
    {
        return Collection::make($filters)->only($this->customFilters)->map(function ($value) {
            if (is_array($value) && array_key_exists('=', $value)) {
                return $value['='];
            }

            return $value;
        });
    }

    /**
     * This method sort the collection query when listing resources.
     *
     * @param Builder $query
     * @param string|null $column
     * @param string $direction
     * @return void
     */
    public function sortCollectionQuery(
        Builder $query,
        ?string $column,
        string $direction = 'desc'
    ): void {
        if (empty($column)) {
            return;
        }

        $query->orderBy(Str::snake($column), $direction);
    }
}
