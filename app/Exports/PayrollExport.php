<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

class PayrollExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnFormatting
{
    protected Collection $payrollData;

    public function __construct(Collection $payrollData)
    {
        $this->payrollData = $payrollData;
    }

    public function collection(): Collection
    {
        return $this->payrollData;
    }

    public function headings(): array
    {
        return [
            'Employee Name',
            'Employee ID',
            'Department',
            'Position',
            'Work Days',
            'Absent Days',
            'Leave Days',
            'Total Hours',
            'Regular Hours',
            'OT Hours',
            'Holiday OT',
            'Weekend OT',
            'Base Salary',
            'Fixed Allowances',
            'Variable Allowances',
            'Additional Allowances',
            'Hourly Rate (OT)',
            'Overtime Pay',
            'Variable Deductions',
            'Estimated Total',
        ];
    }

    public function map($row): array
    {
        return [
            $row['name'],
            $row['employee_id'] ?? '-',
            $row['department'] ?? '-',
            $row['position'] ?? '-',
            $row['work_days_present'],
            $row['absent_days'],
            $row['leave_days'],
            $row['total_work_hours'],
            $row['regular_hours'],
            $row['overtime_hours'],
            $row['holiday_ot_hours'],
            $row['weekend_ot_hours'],
            $row['base_salary'],
            $row['fixed_allowances'],
            $row['variable_allowances'],
            $row['additional_allowances'] ?? 0,
            $row['hourly_rate'],
            $row['overtime_pay'],
            $row['variable_deduction'],
            $row['estimated_total'],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'M' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'N' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'O' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'P' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'Q' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'R' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'S' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'T' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
