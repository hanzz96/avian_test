<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint dashboard (Soal 5 no. 1 & 2). Logika ada di DashboardService.
 */
class DashboardController extends Controller
{
    public function __construct(private DashboardService $service)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->service->dashboard());
    }

    public function machine(string $id): JsonResponse
    {
        return response()->json($this->service->machine($id));
    }
}
