<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserAdditionalAllowance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserAdditionalAllowanceController extends Controller
{
    /**
     * List additional allowances for a user, optionally filtered by period.
     */
    public function index(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->can('admin.salary.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $query = UserAdditionalAllowance::where('user_id', $user->id)
            ->with('createdBy:id,name')
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->orderBy('name');

        if ($request->filled('year') && $request->filled('month')) {
            $query->where('period_year', (int) $request->year)
                  ->where('period_month', (int) $request->month);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->get(),
        ]);
    }

    /**
     * Create a new additional allowance for a user.
     */
    public function store(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->can('admin.salary.manage')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'amount'       => 'required|numeric|min:0',
            'description'  => 'nullable|string|max:500',
            'period_year'  => 'required|integer|min:2020|max:2100',
            'period_month' => 'required|integer|min:1|max:12',
        ]);

        $allowance = UserAdditionalAllowance::create([
            ...$data,
            'user_id'    => $user->id,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $allowance->load('createdBy:id,name'),
            'message' => 'Additional allowance created successfully',
        ], 201);
    }

    /**
     * Update an additional allowance.
     */
    public function update(Request $request, User $user, UserAdditionalAllowance $allowance): JsonResponse
    {
        if (!$request->user()->can('admin.salary.manage')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        if ($allowance->user_id !== $user->id) {
            return response()->json(['success' => false, 'error' => 'Not found'], 404);
        }

        $data = $request->validate([
            'name'         => 'sometimes|required|string|max:255',
            'amount'       => 'sometimes|required|numeric|min:0',
            'description'  => 'nullable|string|max:500',
            'period_year'  => 'sometimes|required|integer|min:2020|max:2100',
            'period_month' => 'sometimes|required|integer|min:1|max:12',
        ]);

        $allowance->update($data);

        return response()->json([
            'success' => true,
            'data'    => $allowance->fresh()->load('createdBy:id,name'),
            'message' => 'Additional allowance updated successfully',
        ]);
    }

    /**
     * Delete an additional allowance.
     */
    public function destroy(Request $request, User $user, UserAdditionalAllowance $allowance): JsonResponse
    {
        if (!$request->user()->can('admin.salary.manage')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        if ($allowance->user_id !== $user->id) {
            return response()->json(['success' => false, 'error' => 'Not found'], 404);
        }

        $allowance->delete();

        return response()->json([
            'success' => true,
            'message' => 'Additional allowance deleted successfully',
        ]);
    }
}
