<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class RentalsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithEvents
{
    protected $rentals;

    public function __construct(Collection $rentals)
    {
        $this->rentals = $rentals;
    }

    public function collection()
    {
        return $this->rentals;
    }

    public function headings(): array
    {
        return [
            'Invoice',
            'Tanggal Dibuat',
            'Nama Pelanggan',
            'Email Pelanggan',
            'Barang yang Disewa',
            'Tanggal Mulai',
            'Tanggal Selesai',
            'Total Tagihan (Rp)',
            'Total DP/Dibayar (Rp)',
            'Status Pesanan',
        ];
    }

    public function map($rental): array
    {
        $itemNames = $rental->items->map(function ($item) {
            return $item->product ? $item->product->name : 'Barang Dihapus';
        })->implode(', ');

        return [
            $rental->invoice_number,
            $rental->created_at->format('Y-m-d H:i'),
            $rental->user->name ?? '-',
            $rental->user->email ?? '-',
            $itemNames,
            $rental->start_date,
            $rental->end_date,
            $rental->total_amount,
            $rental->dp_amount,
            strtoupper($rental->status),
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20, // Invoice
            'B' => 18, // Tanggal
            'C' => 25, // Pelanggan
            'D' => 30, // Email
            'E' => 45, // Barang
            'F' => 15, // Mulai
            'G' => 15, // Selesai
            'H' => 20, // Total Tagihan
            'I' => 20, // DP
            'J' => 18, // Status
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style header row 1
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF1E40AF'], // Tailwind blue-800
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $totalRow = $highestRow + 1;

                // Add "TOTAL" label
                $sheet->setCellValue('G' . $totalRow, 'TOTAL KESELURUHAN:');
                
                // Add SUM formulas
                $sheet->setCellValue('H' . $totalRow, '=SUM(H2:H' . $highestRow . ')');
                $sheet->setCellValue('I' . $totalRow, '=SUM(I2:I' . $highestRow . ')');

                // Style the Total row
                $sheet->getStyle('G' . $totalRow . ':I' . $totalRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFF3F4F6'], // gray-100
                    ],
                ]);
                $sheet->getStyle('G' . $totalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                
                // Format Currency
                $sheet->getStyle('H2:I' . $totalRow)
                      ->getNumberFormat()
                      ->setFormatCode('_("Rp"* #,##0_);_("Rp"* \(#,##0\);_("Rp"* "-"??_);_(@_)');

                // Apply borders to all data cells
                $sheet->getStyle('A1:J' . $highestRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FFD1D5DB'],
                        ],
                    ],
                ]);
            },
        ];
    }
}
