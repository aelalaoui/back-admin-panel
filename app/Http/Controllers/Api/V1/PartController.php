<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Part;
use Illuminate\Http\JsonResponse as Response;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Post;

class partController extends ApiModelRestController
{
    public static string|null $model = 'App\Models\Part';

    #[GET('parts')]
    /**
     * List resources with pagination.
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
     * Create a new model
     *
     * @param Request $request
     * @return Response
     * @throws \Exception|ValidationException
     */
    public function store(Request $request): Response
    {
        return parent::store($request);
    }
}
