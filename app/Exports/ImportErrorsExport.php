<?php

// 1. EXPORT CLASS: app/Exports/ImportErrorsExport.php
namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ImportErrorsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $errors;
    protected $batch;

    public function __construct($errors, $batch)
    {
        $this->errors = $errors;
        $this->batch = $batch;
    }

    public function collection()
    {
        return $this->errors;
    }

    public function headings(): array
    {
        return [
            'Row Number',
            'Username',
            'Error Type',
            'Error Message',
            'Original Data',
            'Status',
            'Created At',
            'Resolution Notes'
        ];
    }

    public function map($error): array
    {
        // Format original row data
        $originalData = '';
        if (is_array($error->row_data)) {
            $originalData = implode(' | ', array_map(function($value) {
                return is_null($value) ? '' : (string)$value;
            }, $error->row_data));
        }

        return [
            $error->row_number,
            $error->username ?? 'N/A',
            $error->error_type,
            $error->error_message,
            $originalData,
            $error->resolved ? 'Resolved' : 'Pending',
            $error->created_at->format('Y-m-d H:i:s'),
            $error->resolution_notes ?? ''
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Style the header row
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ]
        ]);

        // Add borders to all cells with data
        $highestRow = $sheet->getHighestRow();
        $sheet->getStyle('A1:H' . $highestRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ]
            ]
        ]);

        return [];
    }
}
