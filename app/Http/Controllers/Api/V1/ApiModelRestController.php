<?php

namespace App\Http\Controllers\Api\V1;

use Exception;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelIgnition\Recorders\QueryRecorder\Query;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

abstract class ApiModelRestController extends BaseController
{
    /**
     * @var class-string<Model>|null
     */
    public static string|null $model = null;

    /**
     * @var class-string<Validator>|null
     */
    public static string|null $validator = null;

    /**
     * @var class-string<Transformer>|null
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
        $this->checkRoles($request);

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
            /** @var Query $query */
            $query = $this->qualifyCollectionQuery($query, $request);
            $this->sortCollectionQuery($query, $sortBy, $direction);

            $paginator = $query->paginate($perPage)->appends($request->query());
            return $this->response->paginator($paginator, static::$transformer);
        } catch (PDOException $exception) {
            captureException($exception);
            throw new BadRequestHttpException('Data error', $exception);
        }
    }

    /**
     * Create a new model.
     *
     * @hideFromAPIDocumentation
     * @param Request $request
     * @return Response
     * @throws TransformerException|ValidationException
     */
    public function store(Request $request): Response
    {
        $this->checkRoles($request);

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

        return $this->respondWithModel($model, null, 201);
    }

    /**
     * Get one resource from uuid.
     *
     * @hideFromAPIDocumentation
     * @param Request $request
     * @param mixed $uuid
     * @return Response
     * @throws TransformerException
     */
    public function show(Request $request, mixed $uuid): Response
    {
        $this->checkRoles($request);

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
        $this->checkRoles($request);

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
        $this->checkRoles($request);

        /** @var Model|null $model */
        $model = static::$model::find($uuid);

        if (is_null($model)) {
            throw new ModelNotFoundException();
        }

        $model->delete();
        return $this->response->noContent();
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
        return $this->response->item($model, new ($transformer ?? static::$transformer))->statusCode($status);
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
     * @param Query $query
     * @param Collection $filters
     */
    protected function qualifyCollectionQueryWithFilter(Query $query, Collection $filters): void
    {
    }

    /**
     * This function can be used to add conditions to the query builder,
     * which will specify the currently logged in user's ownership of the model.
     *
     * @param Query $query
     * @param Request $request
     * @return Builder|Query
     */
    public function qualifyCollectionQuery(Query $query, Request $request): Builder|Query
    {
        return $query;
    }
}
