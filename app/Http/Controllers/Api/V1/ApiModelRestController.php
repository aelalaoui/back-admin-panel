<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Model;
use App\Traits\ApiResponse;
use App\Traits\SearchQuery;
use App\Transformers\SafeTransformer;
use Exception;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse as Response;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelIgnition\Recorders\QueryRecorder\Query;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @template TModel of Illuminate\Database\Eloquent\Model
 * @template TTransformer of \League\Fractal\TransformerAbstract
 * @template TValidator of Illuminate\Support\Facades\Validator
 */
abstract class ApiModelRestController extends BaseController
{
    use ApiResponse;
    use SearchQuery;

    /**
     * @var class-string<TModel>|null
     */
    public static string|null $model = null;

    /**
     * @var class-string<TValidator>|null
     */
    public static string|null $validator = null;

    /**
     * @var class-string<TTransformer>|null
     */
    public static string|null $transformer = SafeTransformer::class;

    const RESULTS_PER_PAGE = 25;

    /**
     * The default listing ordering params.
     *
     * @var array
     */
    protected static array $defaultOrder = [];

    /**
     * Fields that are custom filterable
     *
     * @var array
     */
    protected array $customFilters = [];

    /**
     * List resources with pagination.
     *
     * @hideFromAPIDocumentation
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function index(Request $request): Response
    {
        ////$this->checkRoles($request);

        $data = $request->validate([
            'per_page' => 'int|nullable|min:1|max:250',
            'page' => 'int|nullable|min:1',
            'filter' => 'array|nullable',
            'sort_by' => 'string|nullable',
            'order' => 'string|nullable|in:desc,asc',
        ]);

        $perPage = $data['per_page'] ?? self::RESULTS_PER_PAGE;
        $filters = $data['filter'] ?? [];
        $autoFilters = Arr::except($filters, $this->customFilters);
        $customFilters = Arr::only($filters, $this->customFilters);
        $dataQ = $request->input('q') ?? [];
        $sortBy = $data['sort_by'] ?? static::$defaultOrder['key'] ?? null;
        $direction = $data['order'] ?? static::$defaultOrder['order'] ?? 'desc';

        try {
            $model = new static::$model;
            $query = $model::query();

            $this->qualifyCollectionQueryWithQ($model, $query, $dataQ);
            $this->qualifyCollectionQueryWithAutoFilter($query, $autoFilters);
            $this->qualifyCollectionQueryWithFilter($query, $this->cleanFilters($customFilters));
            /** @var Builder $query */
            $query = $this->qualifyCollectionQuery($query, $request);
            $this->sortCollectionQuery($query, $sortBy, $direction);

            $paginator = $query->paginate($perPage)->appends($request->query());
            return response()->json($paginator);
            //return $this->response->paginator($paginator, static::$transformer);
        } catch (Exception $exception) {
            throw new BadRequestHttpException('Data error', $exception);
        }
    }

    /**
     * Create a new model.
     *
     * @hideFromAPIDocumentation
     * @param Request $request
     * @return Response
     * @throws Exception|ValidationException
     */
    public function store(Request $request): Response
    {
        ////$this->checkRoles($request);

        $model = new static::$model;

        if (!is_null(static::$validator)) {
            $validator = new static::$validator($model, $this->getValidatorParameters($request));
            $data = $this->validate($request, $validator->getStoreRules());
        } else {
            $data = $request->input();
        }

        $model->fill($data);
        if (method_exists($this, 'storeCustomization')) {
            $model = app()->call([$this, 'storeCustomization'], [
                'request' => $request,
                static::$model => $model,
            ]);
        }

        $model->save();

        if (method_exists($this, 'onPostStore')) {
            app()->call([$this, 'onPostStore'], [
                'request' => $request,
                static::$model => $model,
            ]);
        }

        return $this->respondWithNoContent(201);
    }

    /**
     * Get one resource from uuid.
     *
     * @hideFromAPIDocumentation
     * @param Request $request
     * @param mixed $uuid
     * @return Response
     * @throws Exception
     */
    public function show(Request $request, mixed $uuid): Response
    {
        ////$this->checkRoles($request);

        /** @var Model|null $model */
        $model = static::$model::find($uuid);

        if (is_null($model)) {
            throw new ModelNotFoundException();
        }

        return $this->respondWithModel($model);
    }

    /**
     * Update a resource.
     *
     * @hideFromAPIDocumentation
     * @param Request $request
     * @param string $uuid
     * @return Response
     * @throws ValidationException
     * @throws Exception
     */
    public function update(Request $request, string $uuid): Response
    {
        ////$this->checkRoles($request);

        /** @var Model|null $model */
        $model = static::$model::find($uuid);

        if (!is_null(static::$validator)) {
            $validator = new static::$validator($model, $this->getValidatorParameters($request));
            $data = $this->validate($request, $validator->getStoreRules());
        } else {
            $data = $request->input();
        }

        $model->fill($data);
        if (method_exists($this, 'updateCustomization')) {
            $model = app()->call([$this, 'updateCustomization'], [
                'request' => $request,
                static::$model => $model,
            ]);
        }

        $model->save();

        if (method_exists($this, 'onPostUpdate')) {
            app()->call([$this, 'onPostUpdate'], [
                'request' => $request,
                static::$model => $model,
            ]);
        }

        return $this->respondWithModel($model);
    }

    /**
     * Delete a resource.
     *
     * @hideFromAPIDocumentation
     * @param Request $request
     * @param string $uuid
     * @return Response
     */
    public function destroy(Request $request, string $uuid): Response
    {
        ////$this->checkRoles($request);

        /** @var Model|null $model */
        $model = static::$model::find($uuid);

        if (is_null($model)) {
            throw new ModelNotFoundException();
        }

        $model->delete();
        return $this->respondWithNoContent();
    }

    /**
     * Create a response from a model and its associated transformer.
     *
     * @param Arrayable $model
     * @param string|null $transformer
     * @param int $status
     * @return Response
     */
    protected function respondWithModel(Arrayable $model, ?string $transformer = null, int $status = 200): Response
    {
        $t = new ($transformer ?? static::$transformer);
        return response()->json($t->transform($model), $status);
    }

    /**
     * Create a response with no content
     *
     * @param int $status
     * @return Response
     */
    protected function respondWithNoContent(int $status = 200): Response
    {
        return response()->json([], $status);
    }

    /**
     * This method returns an array of specific parameters for the object validator
     * It could be useful sometimes to get some other objects to generate the rule especially for route like this :
     * /my-resources/{model}/sub-resources, if you need to create a sub-resource but with specific rules according
     * to {model} instance
     * @param Request $request
     * @return array
     */
    protected function getValidatorParameters(Request $request): array
    {
        return [];
    }


    /**
     * Parse the filters and manually qualify the collection query.
     *
     * @param Builder|Query $query
     * @param Collection $filters
     */
    protected function qualifyCollectionQueryWithFilter(Builder|Query $query, Collection $filters): void
    {
    }

    /**
     * This function can be used to add conditions to the query builder,
     * which will specify the currently logged in user's ownership of the model.
     *
     * @param Builder|Query $query
     * @param Request $request
     * @return Builder|Query
     */
    public function qualifyCollectionQuery(Builder|Query $query, Request $request): Builder|Query
    {
        return $query;
    }
}
