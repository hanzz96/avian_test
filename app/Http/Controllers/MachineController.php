<?php

namespace App\Http\Controllers;

use App\Services\MachineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MachineController extends Controller
{
    /**
     * @param  MachineService  $service
     */
    public function __construct(private MachineService $service)
    {
    }

    /**
     * @param  Request  $request
     * @param  string  $id
     * @return JsonResponse
     */
    public function workOrders(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'status' => 'nullable|in:OPEN,RUNNING,FINISHED,CANCELLED',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        return response()->json($this->service->workOrders($id, $data['status'] ?? null, $data['limit'] ?? 10));
    }
}
