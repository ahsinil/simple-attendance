<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Models\UserSalaryComponent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalaryComponentController extends Controller
{
    /**
     * List all salary components.
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->can('admin.salary.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $components = SalaryComponent::orderBy('type')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $components,
        ]);
    }

    /**
     * Create a new salary component.
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->can('admin.salary.manage')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:FIXED,VARIABLE',
            'description' => 'nullable|string|max:500',
        ]);

        $component = SalaryComponent::create($data);

        return response()->json([
            'success' => true,
            'data' => $component,
            'message' => 'Salary component created successfully',
        ], 201);
    }

    /**
     * Update a salary component.
     */
    public function update(Request $request, SalaryComponent $salaryComponent): JsonResponse
    {
        if (!$request->user()->can('admin.salary.manage')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:FIXED,VARIABLE',
            'description' => 'nullable|string|max:500',
            'is_active' => 'sometimes|boolean',
        ]);

        $salaryComponent->update($data);

        return response()->json([
            'success' => true,
            'data' => $salaryComponent->fresh(),
            'message' => 'Salary component updated successfully',
        ]);
    }

    /**
     * Delete a salary component.
     */
    public function destroy(Request $request, SalaryComponent $salaryComponent): JsonResponse
    {
        if (!$request->user()->can('admin.salary.manage')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $salaryComponent->delete();

        return response()->json([
            'success' => true,
            'message' => 'Salary component deleted successfully',
        ]);
    }

    /**
     * Get a user's salary info (base salary + assigned components).
     */
    public function getUserSalary(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->can('admin.salary.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $userComponents = UserSalaryComponent::where('user_id', $user->id)
            ->with('salaryComponent')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'base_salary' => $user->base_salary,
                'components' => $userComponents,
                'total_fixed' => $user->totalFixedAllowances(),
                'total_variable' => $user->totalVariableAllowances(),
                'hourly_rate' => round($user->overtimeHourlyRate(), 2),
            ],
        ]);
    }

    /**
     * Update a user's salary info (base salary + component assignments).
     */
    public function updateUserSalary(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->can('admin.salary.manage')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'base_salary' => 'nullable|numeric|min:0',
            'components' => 'nullable|array',
            'components.*.salary_component_id' => 'required|exists:salary_components,id',
            'components.*.amount' => 'required|numeric|min:0',
        ]);

        // Update base salary
        if (array_key_exists('base_salary', $data)) {
            $user->update(['base_salary' => $data['base_salary']]);
        }

        // Sync salary components
        if (isset($data['components'])) {
            // Remove existing
            UserSalaryComponent::where('user_id', $user->id)->delete();

            // Add new
            foreach ($data['components'] as $comp) {
                UserSalaryComponent::create([
                    'user_id' => $user->id,
                    'salary_component_id' => $comp['salary_component_id'],
                    'amount' => $comp['amount'],
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Salary updated successfully',
            'data' => [
                'base_salary' => $user->fresh()->base_salary,
                'total_fixed' => $user->totalFixedAllowances(),
                'total_variable' => $user->totalVariableAllowances(),
                'hourly_rate' => round($user->overtimeHourlyRate(), 2),
            ],
        ]);
    }
}
