<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductionResultRequest;
use App\Services\ProductionResultService;
use Illuminate\Http\JsonResponse;

class ProductionResultController extends Controller
{
    public function __construct(private ProductionResultService $service)
    {
    }

    public function store(StoreProductionResultRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated());

        return response()->json(['message' => 'Production result saved', 'data' => $result], 201);
    }
}
