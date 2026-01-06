<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Get all settings.
     */
    /**
     * Get all settings.
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->can('admin.settings.view')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $settings = AppSetting::all()->pluck('value', 'key');

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Update settings.
     */
    public function update(Request $request): JsonResponse
    {
        if (!$request->user()->can('admin.settings.update')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'nullable',
        ]);

        foreach ($data['settings'] as $key => $value) {
            AppSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value !== null ? (string) $value : null]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
        ]);
    }
}
