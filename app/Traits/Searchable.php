<?php

declare(strict_types = 1);

namespace App\Traits;

/**
 * Trait Searchable
 * @package Plussimple\DtoApi\Traits
 *
 */
trait Searchable
{
    /**
     * Fields to use for research, order is important to weight the result (especially in Elastic search)
     * The first field is the most important
     * @var array
     */
    public array $searchables = [];


    /**
     * Return the fields from the object in which we can look up for data
     * @return array
     */
    public function getSearchableFields(): array
    {
        return $this->searchables;
    }

    /**
     * Merge new searchable attributes with existing searchable attributes on the model.
     *
     * @param  array  $searchables
     * @return $this
     */
    public function mergeSearchable($searchables): static
    {
        $this->searchables = array_merge($this->searchables, $searchables);
        return $this;
    }
}
