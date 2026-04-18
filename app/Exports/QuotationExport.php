<?php

namespace App\Exports;

use App\Models\Order;
use App\Models\Setting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QuotationExport implements FromArray, WithEvents, WithTitle
{
    protected $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function title(): string
    {
        return 'Quotation';
    }

    public function array(): array
    {
        return [[]]; // We build everything in AfterSheet
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $ws  = $event->sheet->getDelegate();
                $o   = $this->order;
                $qty = max(1, (int)$o->quantity);
                $unit = (float)($o->final_price ?? 0) / $qty;
                $total = (float)($o->final_price ?? 0);
                $days  = $o->production_days ?? 30;
                $salesName = optional($o->salesUser)->name ?? '';
                $custName  = optional($o->customer)->full_name ?? '?';
                $orderNum  = $o->order_number;
                $productName = $o->product_name;
                $companyName = 'DAYANCO TRADING CO. LIMITED';
                $companyAddress = 'ROOM 807-1, NO 1, 2ND QILIN STREET, HUANGGE TOWN, NANSHA DISTRICT, GUANGZHOU, 511455, P.R. CHINA';
                $companyPhone = '+86 188188 45411';
                $companyEmail = 'team@dayancoofficial.com';
                $companyAttn = 'Mr. Abdulmalek';

                $green  = 'D5E8D4';
                $blue   = 'DAE8FC';
                $white  = 'FFFFFF';
                $black  = '000000';
                $dkGreen = '82B366';
                $dkBlue  = '6C8EBF';

                // ── Column widths ──────────────────────────────────────
                $cols = ['A'=>5,'B'=>18,'C'=>14,'D'=>9,'E'=>10,'F'=>13,'G'=>8,'H'=>8,
                         'I'=>12,'J'=>12,'K'=>12,'L'=>8,'M'=>8,'N'=>11,'O'=>14,'P'=>14];
                foreach ($cols as $col => $w) {
                    $ws->getColumnDimension($col)->setWidth($w);
                }

                // ── Row heights ────────────────────────────────────────
                foreach ([1=>14,2=>14,3=>14,4=>12,5=>24,6=>30,7=>30,8=>30,9=>30,10=>28,11=>28,12=>22,13=>22] as $r=>$h) {
                    $ws->getRowDimension($r)->setRowHeight($h);
                }

                // ── R1: DAYANCO® brand (right side) ───────────────────
                $ws->setCellValue('N1', $companyName);
                $ws->getStyle('N1:P1')->applyFromArray([
                    'font' => ['bold'=>true,'size'=>18,'color'=>['rgb'=>'1A5276']],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_RIGHT],
                ]);
                $ws->mergeCells('N1:P1');

                // ── R2: Supply Chain Management ───────────────────────
                $ws->setCellValue('N2', '| Supply Chain Management |');
                $ws->getStyle('N2:P2')->applyFromArray([
                    'font' => ['size'=>8,'color'=>['rgb'=>'555555'],'italic'=>true],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_RIGHT],
                ]);
                $ws->mergeCells('N2:P2');

                // ── R3: Company Address ────────────────────────────────
                $ws->setCellValue('A3', $companyAddress);
                $ws->mergeCells('A3:P3');
                $ws->getStyle('A3')->applyFromArray([
                    'font' => ['size'=>7,'color'=>['rgb'=>'333333']],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
                ]);

                // ── R4: ATTN ──────────────────────────────────────────
                $ws->setCellValue('A4', 'ATTN: ' . $companyAttn . '  China Mobile: ' . $companyPhone . '  E-mail: ' . $companyEmail);
                $ws->mergeCells('A4:P4');
                $ws->getStyle('A4')->applyFromArray([
                    'font' => ['size'=>7,'color'=>['rgb'=>'333333']],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
                ]);

                // ── R5: Title ─────────────────────────────────────────
                $ws->setCellValue('A5', $productName . ' _Quotations');
                $ws->mergeCells('A5:P5');
                $ws->getStyle('A5')->applyFromArray([
                    'font' => ['bold'=>true,'size'=>13],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                ]);

                // ── R6: TO ────────────────────────────────────────────
                $ws->setCellValue('A6', 'TO Mr. ' . $custName . ' – Purchasing Manager');
                $ws->mergeCells('A6:H6');
                $ws->getStyle('A6')->applyFromArray([
                    'font' => ['bold'=>true,'size'=>10,'underline'=>true],
                    'alignment' => ['vertical'=>Alignment::VERTICAL_CENTER],
                ]);

                // ── R7: File No / Dates ───────────────────────────────
                $ws->setCellValue('A7', 'File No.: XXX-' . substr($orderNum, -9));
                $ws->mergeCells('A7:H7');
                $ws->getStyle('A7')->applyFromArray(['font'=>['size'=>9]]);

                $ws->setCellValue('I7', 'Quotation Date: ' . now()->format('F j\t\h, Y'));
                $ws->mergeCells('I7:M7');
                $ws->getStyle('I7')->applyFromArray(['font'=>['size'=>8]]);

                $ws->setCellValue('N7', 'Quotation Valid Date: ' . now()->addDays(21)->format('F j\t\h, Y'));
                $ws->mergeCells('N7:P7');
                $ws->getStyle('N7')->applyFromArray([
                    'font' => ['size'=>8],
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$green]],
                    'borders' => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>$dkGreen]]],
                ]);

                // ── R8-9: Column Headers ───────────────────────────────
                $headers = [
                    'A8'=>'No.','B8'=>'Item Name','C8'=>'Reference Picture',
                    'D8'=>'HS CODE','E8'=>'Barcode','F8'=>'Material','G8'=>'Color','H8'=>'Size',
                ];
                foreach ($headers as $cell => $val) {
                    $ws->setCellValue($cell, $val);
                    $ws->getStyle($cell)->applyFromArray([
                        'font' => ['bold'=>true,'size'=>8],
                        'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$blue]],
                        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
                        'borders' => ['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>$dkBlue]]],
                    ]);
                    $ws->mergeCells(str_replace('8', '8:'.substr($cell,0,1).'9', $cell));
                }

                // Packaging merged header
                $ws->setCellValue('I8', 'Packaging');
                $ws->mergeCells('I8:J8');
                $ws->getStyle('I8:J8')->applyFromArray([
                    'font'=>['bold'=>true,'size'=>8],'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER],
                    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$blue]],
                    'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>$dkBlue]]],
                ]);
                $ws->setCellValue('I9', 'Quantities Carton');
                $ws->setCellValue('J9', 'Carton Size');

                $ws->setCellValue('K8', 'Loading Container');
                $ws->mergeCells('K8:K9');
                $ws->setCellValue('L8', 'Quantities');
                $ws->mergeCells('L8:M8');
                $ws->setCellValue('L9', 'PALLETS');
                $ws->setCellValue('M9', 'PCS');
                $ws->setCellValue('N8', 'Final Price USD');
                $ws->mergeCells('N8:N9');
                $ws->setCellValue('O8', 'Sub-total USD');
                $ws->mergeCells('O8:O9');
                $ws->setCellValue('P8', 'Production Lead Time');
                $ws->mergeCells('P8:P9');

                // Style rows 8-9
                $ws->getStyle('I9:P9')->applyFromArray([
                    'font'=>['bold'=>true,'size'=>7.5],
                    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$blue]],
                    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
                    'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>$dkBlue]]],
                ]);
                foreach (['K8:K9','L8:M8','N8:N9','O8:O9','P8:P9'] as $r) {
                    $ws->getStyle($r)->applyFromArray([
                        'font'=>['bold'=>true,'size'=>7.5],
                        'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$blue]],
                        'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
                        'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>$dkBlue]]],
                    ]);
                }
                // Sub-headers unit labels
                $ws->setCellValue('N9', 'USD');
                $ws->setCellValue('O9', 'USD');
                $ws->setCellValue('P9', 'DAYS');

                // ── R10: Data Row ─────────────────────────────────────
                $ws->setCellValue('A10', 1);
                $ws->setCellValue('B10', $productName);
                $ws->setCellValue('M10', $qty);
                $ws->setCellValue('N10', $unit);
                $ws->setCellValue('O10', $total);
                $ws->setCellValue('P10', $days);

                $ws->getStyle('N10')->getNumberFormat()->setFormatCode('0.00');
                $ws->getStyle('O10')->getNumberFormat()->setFormatCode('0.00');
                $ws->getStyle('A10:P10')->applyFromArray([
                    'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'AAAAAA']]],
                    'alignment'=>['vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
                ]);
                $ws->getRowDimension(10)->setRowHeight(28);

                // ── R11: Total Row ────────────────────────────────────
                $ws->setCellValue('A11', '');
                $ws->setCellValue('N11', 'total');
                $ws->setCellValue('O11', $total);
                $ws->getStyle('N11:O11')->applyFromArray([
                    'font'=>['bold'=>true],
                    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$green]],
                    'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>$dkGreen]]],
                    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER],
                ]);
                $ws->getStyle('A11:P11')->applyFromArray([
                    'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'AAAAAA']]],
                ]);

                // ── Footer ────────────────────────────────────────────
                $ws->setCellValue('A13', 'Sales Representative: ' . $salesName);
                $ws->getStyle('A13')->applyFromArray(['font'=>['bold'=>true,'italic'=>true,'size'=>9]]);
                $ws->mergeCells('A13:H13');

                $ws->setCellValue('A14', 'Generated: ' . now()->format('Y-m-d H:i') . '  |  Order: ' . $orderNum);
                $ws->getStyle('A14')->applyFromArray(['font'=>['size'=>8,'color'=>['rgb'=>'555555']]]);
                $ws->mergeCells('A14:P14');

                $qrPng = QrCode::format('png')
                    ->size(100)
                    ->margin(1)
                    ->generate(route('orders.verify', ['orderNumber' => $orderNum]));

                $qrImage = @imagecreatefromstring($qrPng);

                if ($qrImage !== false) {
                    $drawing = new MemoryDrawing();
                    $drawing->setName('Verification QR');
                    $drawing->setDescription('Verification QR');
                    $drawing->setImageResource($qrImage);
                    $drawing->setRenderingFunction(MemoryDrawing::RENDERING_PNG);
                    $drawing->setMimeType(MemoryDrawing::MIMETYPE_PNG);
                    $drawing->setHeight(60);
                    $drawing->setCoordinates('N13');
                    $drawing->setWorksheet($ws);
                }

                // ── Global border for header rows 8-11 ────────────────
                $ws->getStyle('A8:P11')->applyFromArray([
                    'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'888888']]],
                ]);
            },
        ];
    }
}
