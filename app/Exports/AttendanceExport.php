<?php

namespace App\Exports;

use App\Models\Attendance;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttendanceExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $startDate;
    protected $endDate;
    protected $locationId;

    public function __construct($startDate = null, $endDate = null, $locationId = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->locationId = $locationId;
    }

    public function collection()
    {
        $query = Attendance::with(['user', 'location'])
            ->orderBy('scan_time', 'desc');

        if ($this->startDate) {
            $query->whereDate('scan_time', '>=', $this->startDate);
        }

        if ($this->endDate) {
            $query->whereDate('scan_time', '<=', $this->endDate);
        }

        if ($this->locationId) {
            $query->where('location_id', $this->locationId);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Employee Name',
            'Employee ID',
            'Date',
            'Time',
            'Check Type',
            'Location',
            'Status',
            'Late (min)',
            'Method',
            'IP Address',
        ];
    }

    public function map($attendance): array
    {
        return [
            $attendance->id,
            $attendance->user?->name ?? 'Unknown',
            $attendance->user?->employee_id ?? 'N/A',
            $attendance->scan_time?->format('Y-m-d'),
            $attendance->scan_time?->format('H:i:s'),
            $attendance->check_type,
            $attendance->location?->name ?? 'Unknown',
            $attendance->status,
            $attendance->late_min,
            $attendance->method,
            $attendance->ip_address,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
