<?php

namespace App\Traits;

trait ApiResponse
{
    protected function transformData($data, $transformer)
    {
        $transformation = fractal($data, new $transformer);
        return $transformation->toArray();
    }
}
