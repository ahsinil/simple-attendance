<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected Collection $data;

    public function __construct(Collection $data)
    {
        $this->data = $data;
    }

    public function collection(): Collection
    {
        return $this->data->values();
    }

    public function headings(): array
    {
        return [
            'Employee ID',
            'Name',
            'Department',
            'Position',
            'Days Present',
            'On Time',
            'Late',
            'Total Late (min)',
            'Absent Days',
            'Leave Days',
            'Work Hours',
            'Overtime Hours',
            'Attendance Rate (%)',
        ];
    }

    public function map($row): array
    {
        return [
            $row['employee_id'] ?? '-',
            $row['name'],
            $row['department'] ?? '-',
            $row['position'] ?? '-',
            $row['days_present'],
            $row['on_time_count'],
            $row['late_count'],
            $row['total_late_minutes'],
            $row['absent_days'],
            $row['leave_days'],
            $row['total_work_hours'],
            $row['overtime_hours'],
            $row['attendance_rate'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
