<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListProductionOrderRequest;
use App\Services\ProductionOrderService;
use Illuminate\Http\JsonResponse;

class ProductionOrderController extends Controller
{
    public function __construct(private ProductionOrderService $service)
    {
    }

    public function index(ListProductionOrderRequest $request): JsonResponse
    {
        $page = $this->service->paginate($request->validated());

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }
}
