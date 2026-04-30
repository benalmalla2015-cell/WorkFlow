<?php

namespace App\Services\Exports;

use App\Models\Order;
use App\Services\WorkflowDocumentService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds an Excel (.xlsx) representation of the Quotation, using the exact
 * same payload that the existing Quotation PDF generation relies on, so the
 * numbers stay 100% in sync without touching the PDF flow.
 */
class QuotationExcelExporter
{
    public function __construct(private WorkflowDocumentService $documents)
    {
    }

    /**
     * Build the Excel binary contents for the given order.
     *
     * @return array{filename: string, contents: string}
     */
    public function build(Order $order): array
    {
        $payload = $this->documents->buildDocumentPayload($order, 'quotation');

        $documentOrder = $payload['documentOrder'];
        $items = $payload['items'];
        $company = $payload['company'];
        $totals = $payload['totals'];
        $verificationUrl = (string) ($payload['verificationUrl'] ?? '');
        $salesRepresentative = (string) ($payload['salesRepresentative'] ?? '');
        $currency = (string) ($totals['currency'] ?? 'USD');

        $isArabic = app()->getLocale() === 'ar';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Quotation');
        $sheet->setRightToLeft($isArabic);

        // Default font with broad Arabic / Latin coverage.
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(11);

        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(28);
        $sheet->getColumnDimension('C')->setWidth(36);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(16);
        $sheet->getColumnDimension('F')->setWidth(16);

        $row = 1;

        // Company header
        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->setCellValue("A{$row}", (string) ($company['name'] ?? 'DAYANCO'));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(20);
        $sheet->getStyle("A{$row}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(30);
        $row++;

        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->setCellValue("A{$row}", (string) ($company['address'] ?? ''));
        $sheet->getStyle("A{$row}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setWrapText(true);
        $sheet->getRowDimension($row)->setRowHeight(30);
        $row++;

        $contactLine = trim((string) ($company['phone'] ?? ''))
            . '   '
            . trim((string) ($company['email'] ?? ''));
        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->setCellValue("A{$row}", $contactLine);
        $sheet->getStyle("A{$row}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row += 2;

        // Title
        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->setCellValue("A{$row}", $isArabic ? 'عرض سعر' : 'QUOTATION');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle("A{$row}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1F4E79');
        $sheet->getStyle("A{$row}")->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getRowDimension($row)->setRowHeight(26);
        $row += 2;

        // Meta block (two columns of label/value pairs)
        $meta = [
            [
                $isArabic ? 'رقم الطلب' : 'Order #',
                (string) $documentOrder['order_number'],
                $isArabic ? 'تاريخ الإصدار' : 'Issue Date',
                (string) $documentOrder['issue_date'],
            ],
            [
                $isArabic ? 'العميل' : 'Customer',
                (string) $documentOrder['customer_name'],
                $isArabic ? 'صالح حتى' : 'Valid Until',
                (string) $documentOrder['valid_until'],
            ],
            [
                $isArabic ? 'هاتف العميل' : 'Customer Phone',
                (string) $documentOrder['customer_phone'],
                $isArabic ? 'مندوب المبيعات' : 'Sales Representative',
                $salesRepresentative,
            ],
            [
                $isArabic ? 'عنوان العميل' : 'Customer Address',
                (string) $documentOrder['customer_address'],
                $isArabic ? 'مدة الإنتاج' : 'Production Days',
                (string) $documentOrder['production_days'],
            ],
        ];

        foreach ($meta as $line) {
            $sheet->setCellValue("A{$row}", $line[0]);
            $sheet->mergeCells("B{$row}:C{$row}");
            $sheet->setCellValue("B{$row}", $line[1]);
            $sheet->setCellValue("D{$row}", $line[2]);
            $sheet->mergeCells("E{$row}:F{$row}");
            $sheet->setCellValue("E{$row}", $line[3]);
            $sheet->getStyle("A{$row}:F{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->getStyle("D{$row}")->getFont()->setBold(true);
            $row++;
        }
        $row++;

        // Items header
        $headerRow = $row;
        $headers = $isArabic
            ? ['#', 'العنصر', 'الوصف', 'الكمية', 'سعر الوحدة', 'الإجمالي']
            : ['#', 'Item', 'Description', 'Quantity', 'Unit Price', 'Line Total'];

        $columns = ['A', 'B', 'C', 'D', 'E', 'F'];
        foreach ($columns as $i => $col) {
            $sheet->setCellValue("{$col}{$headerRow}", $headers[$i]);
        }
        $sheet->getStyle("A{$headerRow}:F{$headerRow}")
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('305496');
        $sheet->getStyle("A{$headerRow}:F{$headerRow}")->getFont()
            ->setBold(true)
            ->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A{$headerRow}:F{$headerRow}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($headerRow)->setRowHeight(22);
        $row++;

        // Items
        $firstItemRow = $row;
        $line = 1;
        foreach ($items as $item) {
            $sheet->setCellValue("A{$row}", $line++);
            $sheet->setCellValue("B{$row}", (string) ($item['item_name'] ?? ''));
            $sheet->setCellValue("C{$row}", (string) ($item['description'] ?? ''));
            $sheet->setCellValue("D{$row}", (int) ($item['quantity'] ?? 0));
            $sheet->setCellValue("E{$row}", (float) ($item['sales_price'] ?? $item['unit_cost'] ?? 0));
            $sheet->setCellValue("F{$row}", (float) ($item['line_total'] ?? $item['sales_total'] ?? 0));

            $sheet->getStyle("E{$row}:F{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$row}")->getAlignment()->setWrapText(true);
            $row++;
        }
        $lastItemRow = $row - 1;

        if ($lastItemRow >= $firstItemRow) {
            $sheet->getStyle("A{$headerRow}:F{$lastItemRow}")
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
        $row++;

        // Totals block
        $sheet->setCellValue("D{$row}", $isArabic ? 'الإجمالي قبل الضريبة' : 'Subtotal');
        $sheet->setCellValue("E{$row}", $currency);
        $sheet->setCellValue("F{$row}", (float) ($totals['subtotal'] ?? 0));
        $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("D{$row}")->getFont()->setBold(true);
        $row++;

        $sheet->setCellValue("D{$row}", ($isArabic ? 'الضريبة' : 'Tax') . ' (' . number_format((float) ($totals['tax_rate'] ?? 0), 2) . '%)');
        $sheet->setCellValue("E{$row}", $currency);
        $sheet->setCellValue("F{$row}", (float) ($totals['tax_amount'] ?? 0));
        $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $row++;

        $sheet->setCellValue("D{$row}", $isArabic ? 'الإجمالي النهائي' : 'Grand Total');
        $sheet->setCellValue("E{$row}", $currency);
        $sheet->setCellValue("F{$row}", (float) ($totals['grand_total'] ?? $totals['total'] ?? 0));
        $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("A{$row}:F{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9E1F2');
        $sheet->getStyle("A{$row}:F{$row}")->getFont()->setBold(true);
        $row += 2;

        // Notes / order notes if any.
        $orderNotes = trim((string) ($order->notes ?? ''));
        if ($orderNotes !== '') {
            $sheet->mergeCells("A{$row}:F{$row}");
            $sheet->setCellValue("A{$row}", $isArabic ? 'ملاحظات' : 'Notes');
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
            $sheet->mergeCells("A{$row}:F{$row}");
            $sheet->setCellValue("A{$row}", $orderNotes);
            $sheet->getStyle("A{$row}")->getAlignment()->setWrapText(true);
            $sheet->getRowDimension($row)->setRowHeight(40);
            $row += 2;
        }

        // Verification URL
        if ($verificationUrl !== '') {
            $sheet->mergeCells("A{$row}:F{$row}");
            $sheet->setCellValue("A{$row}", ($isArabic ? 'رابط التحقق: ' : 'Verification: ') . $verificationUrl);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$row}")->getFont()->setItalic(true);
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $contents = (string) ob_get_clean();

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        $filename = 'Quotation-' . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $documentOrder['order_number']) . '.xlsx';

        return [
            'filename' => $filename,
            'contents' => $contents,
        ];
    }
}
