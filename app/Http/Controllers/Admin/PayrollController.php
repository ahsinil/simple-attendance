<?php

namespace App\Http\Controllers\Admin;

use App\Exports\PayrollExport;
use App\Http\Controllers\Controller;
use App\Services\OvertimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class PayrollController extends Controller
{
    protected OvertimeService $overtimeService;

    public function __construct(OvertimeService $overtimeService)
    {
        $this->overtimeService = $overtimeService;
    }

    /**
     * Get payroll summary data.
     */
    public function summary(Request $request): JsonResponse
    {
        if (!$request->user()->can('admin.payroll.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'user_id' => 'nullable|exists:users,id',
            'location_id' => 'nullable|exists:locations,id',
            'department' => 'nullable|string',
        ]);

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $payrollData = $this->overtimeService->getPayrollSummary(
            $startDate,
            $endDate,
            $request->input('user_id'),
            $request->input('location_id'),
            $request->input('department')
        );

        $kpis = $this->overtimeService->getPayrollKpis($payrollData);

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $payrollData,
                'kpis' => $kpis,
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ],
        ]);
    }

    /**
     * Export payroll summary to Excel.
     */
    public function export(Request $request)
    {
        if (!$request->user()->can('admin.payroll.export')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'user_id' => 'nullable|exists:users,id',
            'location_id' => 'nullable|exists:locations,id',
            'department' => 'nullable|string',
        ]);

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $payrollData = $this->overtimeService->getPayrollSummary(
            $startDate,
            $endDate,
            $request->input('user_id'),
            $request->input('location_id'),
            $request->input('department')
        );

        $filename = "payroll_summary_{$startDate}_to_{$endDate}.xlsx";

        return Excel::download(new PayrollExport($payrollData), $filename);
    }

    /**
     * Get available departments for filter.
     */
    public function departments(Request $request): JsonResponse
    {
        if (!$request->user()->can('admin.payroll.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $departments = \App\Models\User::whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->pluck('department')
            ->sort()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $departments,
        ]);
    }
}
