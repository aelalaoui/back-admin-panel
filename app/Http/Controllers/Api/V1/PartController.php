<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Part;
use Illuminate\Http\JsonResponse as Response;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Patch;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Put;

class partController extends ApiModelRestController
{
    public string $modelName = 'Part';

    public static string|null $model = 'App\Models\Part';

    #[GET('parts')]
    /**
     * List of parts with pagination.
     *
     * @hideFromAPIDocumentation
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function index(Request $request): Response
    {
        return parent::index($request);
    }

    #[POST('parts')]
    /**
     * Create a new part
     *
     * @param Request $request
     * @return Response
     * @throws \Exception|ValidationException
     */
    public function store(Request $request): Response
    {
        return parent::store($request);
    }

    #[POST('parts/{uuid}')]
    /**
     * Update a part.
     *
     * @param Request $request
     * @param string $uuid
     * @return Response
     * @throws ValidationException
     * @throws \Exception
     */
    public function update(Request $request, string $uuid): Response
    {
        return parent::update($request, $uuid);
    }

    #[GET('parts/{uuid}')]
    /**
     * Get one part from uuid.
     *
     * @param Request $request
     * @param mixed $uuid
     * @return Response
     * @throws \Exception
     */
    public function show(Request $request, mixed $uuid): Response
    {
        return parent::show($request, $uuid);
    }

    #[DELETE('parts/{uuid}')]
    /**
     * Delete a part.
     *
     * @param Request $request
     * @param string $uuid
     * @return Response
     */
    public function destroy(Request $request, string $uuid): Response
    {
        return parent::destroy($request, $uuid);
    }
}
