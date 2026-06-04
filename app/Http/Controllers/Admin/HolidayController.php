<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HolidayController extends Controller
{
    /**
     * Display a listing of holidays.
     */
    public function index(Request $request)
    {
        $year = $request->query('year', Carbon::now()->year);

        $holidays = Holiday::whereYear('date', $year)
            ->orderBy('date', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $holidays
        ]);
    }

    /**
     * Store a newly created holiday.
     */
    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date|unique:holidays,date',
            'name' => 'required|string|max:255',
            'type' => 'required|in:NATIONAL,COMPANY,OPTIONAL',
            'overtime_multiplier' => 'required|numeric|min:1',
        ]);

        $holiday = Holiday::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Hari libur berhasil ditambahkan.',
            'data' => $holiday
        ], 201);
    }

    /**
     * Update the specified holiday.
     */
    public function update(Request $request, Holiday $holiday)
    {
        $request->validate([
            'date' => 'required|date|unique:holidays,date,' . $holiday->id,
            'name' => 'required|string|max:255',
            'type' => 'required|in:NATIONAL,COMPANY,OPTIONAL',
            'overtime_multiplier' => 'required|numeric|min:1',
        ]);

        $holiday->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Hari libur berhasil diperbarui.',
            'data' => $holiday
        ]);
    }

    /**
     * Remove the specified holiday.
     */
    public function destroy(Holiday $holiday)
    {
        $holiday->delete();

        return response()->json([
            'success' => true,
            'message' => 'Hari libur berhasil dihapus.'
        ]);
    }

    /**
     * Sync holidays from Google Calendar ICS for a specific year.
     */
    public function sync(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
        ]);

        $year = $request->input('year');
        $url = 'https://calendar.google.com/calendar/ical/id.indonesian%23holiday%40group.v.calendar.google.com/public/basic.ics';

        try {
            $response = Http::timeout(10)->get($url);
            
            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengambil data dari Google Calendar API.'
                ], 500);
            }

            $icsData = $response->body();
            
            // Extract events for the requested year
            $pattern = '/BEGIN:VEVENT.*?DTSTART;VALUE=DATE:' . $year . '(.*?)SUMMARY:(.*?)\r?\n.*?END:VEVENT/s';
            preg_match_all($pattern, $icsData, $matches, PREG_SET_ORDER);

            $syncedCount = 0;

            foreach ($matches as $match) {
                $monthDay = substr($match[1], 0, 4);
                $dateString = $year . '-' . substr($monthDay, 0, 2) . '-' . substr($monthDay, 2, 2);
                $name = trim($match[2]);

                Holiday::updateOrCreate(
                    ['date' => $dateString],
                    [
                        'name' => $name,
                        'type' => 'NATIONAL',
                        // Set multiplier to 2.0 only if it's newly created,
                        // to avoid overriding admin's custom changes on existing holidays.
                    ]
                );
                
                // Set default multiplier if not exists
                $holiday = Holiday::where('date', $dateString)->first();
                if ($holiday->wasRecentlyCreated && !$holiday->overtime_multiplier) {
                    $holiday->update(['overtime_multiplier' => 2.0]);
                }
                
                $syncedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => "Sinkronisasi berhasil. $syncedCount hari libur tahun $year diperbarui."
            ]);

        } catch (\Exception $e) {
            Log::error('Holiday Sync Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses data libur: ' . $e->getMessage()
            ], 500);
        }
    }
}
