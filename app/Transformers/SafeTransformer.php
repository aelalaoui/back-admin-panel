<?php


namespace App\Transformers;

use Exception;
use App\Models\Model;
use League\Fractal\TransformerAbstract;

/**
 * Transformers class that does not implicitly return all loaded relationships.
 */
class SafeTransformer extends TransformerAbstract
{
    /**
     * Transform the resource into a serializable array.
     *
     * @param $model
     * @return array
     * @throws Exception
     */
    public function transform($model): array
    {
        return match (true) {
            $model instanceof Model => $model->attributesToArray(),
            default => new Exception(sprintf('This model of class [%s] is not serializable', get_class($model))),
        };
    }
}
