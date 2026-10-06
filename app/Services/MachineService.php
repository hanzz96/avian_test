<?php

namespace App\Services;

use App\Models\Machine;

class MachineService
{
    /**
     * @param  string  $machineCode
     * @param  string|null  $status
     * @param  int  $limit
     * @return array{machine_code: string, machine_name: string, work_orders_count: int, work_orders: array}
     */
    public function workOrders(string $machineCode, ?string $status, int $limit): array
    {
        $machine = Machine::with(['workOrders' => fn ($query) => $query
                ->when($status, fn ($q) => $q->where('status', $status))
                ->orderByDesc('plan_start')
                ->limit($limit)])
            ->find($machineCode);
        // dd($machine->toArray());
        abort_if(! $machine, 404, 'Machine not found');

        return [
            'machine_code' => $machine->machine_code,
            'machine_name' => $machine->machine_name,
            // 'work_orders_count' => $machine->work_orders_count,
            'work_orders' => $machine->workOrders->map(fn ($wo) => [
                'wo_number' => $wo->wo_number,
                'status' => $wo->status,
                'shift' => $wo->shift,
                'target_qty' => $wo->target_qty,
                'plan_start' => $wo->plan_start,
            ])->all(),
        ];
    }
}
