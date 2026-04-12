<?php

namespace App\Exports;

use App\Models\Order;
use App\Models\Setting;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Illuminate\Contracts\View\View;

class QuotationExport implements FromView, WithEvents
{
    protected $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function view(): View
    {
        return view('exports.quotation', [
            'order' => $this->order,
            'customer' => $this->order->customer,
            'sales_user' => $this->order->salesUser,
            'company' => [
                'name' => Setting::get('company_name', 'DAYANCO'),
                'address' => Setting::get('company_address', 'Company Address'),
                'phone' => Setting::get('company_phone', '+1234567890'),
            ],
            'quotation_number' => 'QT-' . $this->order->order_number,
            'quotation_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'no' => 1,
                    'item_name' => $this->order->product_name,
                    'reference_picture' => '',
                    'hs_code' => '',
                    'barcode' => '',
                    'material' => '',
                    'color' => '',
                    'size' => '',
                    'packaging_quantities' => '',
                    'carton_size' => '',
                    'loading_container' => '',
                    'pallets' => '',
                    'pcs' => $this->order->quantity,
                    'unit_cost' => $this->order->final_price / $this->order->quantity,
                    'subtotal_cost' => $this->order->final_price,
                    'production_lead_time' => $this->order->production_days ?? 30,
                ]
            ],
            'total_amount' => $this->order->final_price,
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // Set column widths
                $sheet->getColumnDimension('A')->setWidth(5);
                $sheet->getColumnDimension('B')->setWidth(25);
                $sheet->getColumnDimension('C')->setWidth(15);
                $sheet->getColumnDimension('D')->setWidth(10);
                $sheet->getColumnDimension('E')->setWidth(12);
                $sheet->getColumnDimension('F')->setWidth(15);
                $sheet->getColumnDimension('G')->setWidth(10);
                $sheet->getColumnDimension('H')->setWidth(10);
                $sheet->getColumnDimension('I')->setWidth(15);
                $sheet->getColumnDimension('J')->setWidth(15);
                $sheet->getColumnDimension('K')->setWidth(15);
                $sheet->getColumnDimension('L')->setWidth(10);
                $sheet->getColumnDimension('M')->setWidth(10);
                $sheet->getColumnDimension('N')->setWidth(12);
                $sheet->getColumnDimension('O')->setWidth(15);
                $sheet->getColumnDimension('P')->setWidth(20);

                // Set row height for header
                $sheet->getRowDimension(1)->setRowHeight(30);
                
                // Apply styles to header
                $sheet->getStyle('A1:P1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 12,
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => [
                            'rgb' => 'E3F2FD'
                        ]
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ]
                ]);

                // Apply borders to data range
                $sheet->getStyle('A2:P' . (2 + count($this->order->items ?? [1])))->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ]);

                // Add footer with sales person name
                $lastRow = $sheet->getHighestRow() + 2;
                $sheet->setCellValue('A' . $lastRow, 'Sales Representative: ' . $this->order->salesUser->name);
                $sheet->getStyle('A' . $lastRow)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'italic' => true,
                    ]
                ]);

                // Add QR code placeholder
                $qrCodeRow = $lastRow + 1;
                $sheet->setCellValue('A' . $qrCodeRow, 'QR Code for verification available in PDF version');
            },
        ];
    }
}
