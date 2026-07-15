<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashAdvance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashAdvanceController extends Controller
{
    /**
     * Display a listing of the cash advances.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $query = CashAdvance::with(['user', 'admin'])->orderBy('date', 'desc');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        $advances = $query->get();

        return response()->json([
            'success' => true,
            'data' => $advances
        ]);
    }

    /**
     * Store a newly created cash advance in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $advance = CashAdvance::create([
            'user_id' => $request->user_id,
            'date' => $request->date,
            'amount' => $request->amount,
            'notes' => $request->notes,
            'admin_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => $advance->load('user', 'admin'),
            'message' => 'Kasbon berhasil ditambahkan.'
        ], 201);
    }

    /**
     * Update the specified cash advance in storage.
     */
    public function update(Request $request, CashAdvance $cashAdvance): JsonResponse
    {
        $request->validate([
            'date' => 'sometimes|required|date',
            'amount' => 'sometimes|required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $cashAdvance->update($request->only(['date', 'amount', 'notes']));

        return response()->json([
            'success' => true,
            'data' => $cashAdvance->load('user', 'admin'),
            'message' => 'Kasbon berhasil diperbarui.'
        ]);
    }

    /**
     * Remove the specified cash advance from storage.
     */
    public function destroy(CashAdvance $cashAdvance): JsonResponse
    {
        $cashAdvance->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kasbon berhasil dihapus.'
        ]);
    }
}
