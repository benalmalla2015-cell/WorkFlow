<?php

namespace App\Services\Exports;

use App\Models\Order;
use App\Services\WorkflowDocumentService;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * Generates a Word (.docx) invoice whose layout faithfully mirrors
 * the `documents.invoice-pdf-branded` Blade / CSS design:
 *
 *  ┌─────────────────────────────────┐
 *  │           DAYANCO logo (right)  │
 *  │ TO Mr. {name} – Purch. Manager  │
 *  │                    Invoice #    │
 *  │                    Date         │
 *  │         INVOICE                 │
 *  │  ─────────────────────────────  │
 *  │  Project Name: …                │
 *  │  [Date | Item | Desc | Amount]  │
 *  │  … rows …                       │
 *  │  Products Total / Bank Fee /    │
 *  │  Final Total (dark chip)        │
 *  │  Payment Method (underlined)    │
 *  │  [payment rows table]           │
 *  │  REMARK (red, gold border-top)  │
 *  │  Sales Rep  /  QR  Scan verify  │
 *  └─────────────────────────────────┘
 */
class InvoiceWordExporter
{
    // ── Color palette (RRGGBB, mirroring pdf-documents.css) ─────────────────
    private const C_BRAND    = '103F87'; // DAYANCO logo blue
    private const C_DARK     = '0f2f6f'; // meta labels, footer titles
    private const C_GOLD     = 'C49B2D'; // remark border-top / accent
    private const C_RED      = 'b91c1c'; // remark text
    private const C_BLACK    = '111827'; // body text / amount chip bg
    private const C_BLUE_HR  = '113f87'; // strong horizontal rule
    private const C_RULE     = 'cfd8e3'; // light separator
    private const C_BLUE_TH  = '6c8ebf'; // header bottom border
    private const C_GRAY_TH  = 'f3f4f6'; // payment table header bg
    private const C_GRAY_TXT = '4b5563'; // footer muted text
    private const C_SUBTLE   = '6b7280'; // "Scan to verify"
    private const C_DASHED   = '7b8794'; // project line bottom

    // ── Usable content width for A4 with 1.2 cm side margins ─────────────
    // A4 = 11906 twips wide; 1.2 cm = ~681 twips each side → ~10544 twips total
    private const W_TOTAL  = 10544;
    private const W_SPACER = 5483; // 52 % – brand left spacer
    private const W_BRAND  = 5061; // 48 % – brand right cell
    private const W_META_L = 6748; // 64 % – meta left (empty)
    private const W_META_R = 3796; // 36 % – meta right (values)
    private const W_DATE   = 1476; // 14 % – items Date col
    private const W_ITEM   = 2109; // 20 % – items Item col
    private const W_DESC   = 5061; // 48 % – items Description col
    private const W_AMT    = 1898; // 18 % – items Amount col
    private const W_PAY_L  = 3585; // 34 % – payment table label
    private const W_PAY_V  = 6959; // 66 % – payment table value

    public function __construct(private WorkflowDocumentService $documents) {}

    /** @return array{filename: string, contents: string} */
    public function build(Order $order): array
    {
        $payload = $this->documents->buildDocumentPayload($order, 'invoice');

        $documentOrder  = $payload['documentOrder'];
        $items          = $payload['items'];
        $company        = $payload['company'];
        $totals         = $payload['totals'];
        $verificationQr = (string) ($payload['verificationQr'] ?? '');
        $salesRep       = (string) ($payload['salesRepresentative'] ?? '');
        $generatedAt    = $payload['generatedAt'];
        $currency       = (string) ($totals['currency'] ?? 'USD');

        $bankFee         = 40.00;
        $productsTotal   = round((float) ($totals['subtotal'] ?? 0), 2);
        $invoiceTotalDue = round($productsTotal + $bankFee, 2);

        $paymentRows = [
            'Beneficiary Name'            => (string) ($company['beneficiary_name'] ?? ''),
            'Beneficiary Bank'            => (string) ($company['beneficiary_bank'] ?? ''),
            'Beneficiary Account Numbers' => (string) ($company['account_number'] ?? ''),
            'Beneficiary Address'         => (string) ($company['beneficiary_address'] ?? ''),
            'Bank Address'                => (string) ($company['bank_address'] ?? ''),
            'SWIFT'                       => (string) ($company['swift_code'] ?? ''),
            'COUNTRY'                     => (string) ($company['country'] ?? ''),
            'PURPOSE OF PAYMENTS'         => strtoupper((string) ($company['payment_purpose'] ?? '')),
        ];

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(9);

        $section = $phpWord->addSection([
            'marginTop'    => Converter::cmToTwip(1.6),
            'marginBottom' => Converter::cmToTwip(1.6),
            'marginLeft'   => Converter::cmToTwip(1.2),
            'marginRight'  => Converter::cmToTwip(1.2),
        ]);

        // ── 1. BRAND HEADER (logo-style text, right-aligned) ─────────────────
        $phpWord->addTableStyle('WF_BrandHdr', ['borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMargin' => 0]);
        $tBrand = $section->addTable('WF_BrandHdr');
        $tBrand->addRow();
        $tBrand->addCell(self::W_SPACER, $this->noBorder());
        $cBrand = $tBrand->addCell(self::W_BRAND, $this->noBorder());
        $cBrand->addText('DAYANCO®', ['name' => 'Georgia', 'bold' => true, 'size' => 22, 'color' => self::C_BRAND], ['alignment' => Jc::END]);
        $cBrand->addText('Supply Chain Management', ['size' => 10, 'color' => '4B5563'], ['alignment' => Jc::END]);

        $section->addTextBreak(1, ['size' => 4]);

        // ── 2. RECIPIENT ROW ("TO Mr. … – Purchasing Manager") ───────────────
        $phpWord->addTableStyle('WF_Recipient', ['borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMargin' => 60]);
        $tRec = $section->addTable('WF_Recipient');
        $tRec->addRow();
        $cRec = $tRec->addCell(self::W_TOTAL, [
            'borderTopSize' => 0, 'borderTopColor' => 'FFFFFF',
            'borderLeftSize' => 0, 'borderLeftColor' => 'FFFFFF',
            'borderRightSize' => 0, 'borderRightColor' => 'FFFFFF',
            'borderBottomSize' => 6, 'borderBottomColor' => self::C_RULE,
        ]);
        $rRec = $cRec->addTextRun(['alignment' => Jc::START]);
        $rRec->addText('TO  ', ['bold' => true, 'size' => 11, 'color' => self::C_DARK, 'underline' => 'single']);
        $rRec->addText(
            'Mr. ' . $documentOrder['customer_name'] . ' - Purchasing Manager',
            ['bold' => true, 'size' => 11, 'color' => self::C_BLACK]
        );

        $section->addTextBreak(1, ['size' => 3]);

        // ── 3. META (right side: Invoice Number / Date) ───────────────────────
        $phpWord->addTableStyle('WF_Meta', ['borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMargin' => 30]);
        $tMeta = $section->addTable('WF_Meta');
        $tMeta->addRow();
        $tMeta->addCell(self::W_META_L, $this->noBorder());
        $cMeta = $tMeta->addCell(self::W_META_R, $this->noBorder());

        $rNum = $cMeta->addTextRun(['alignment' => Jc::END]);
        $rNum->addText('Invoice Number: ', ['bold' => true, 'size' => 8, 'color' => self::C_DARK]);
        $rNum->addText($documentOrder['order_number'], ['size' => 8, 'color' => self::C_BLACK]);

        $rDt = $cMeta->addTextRun(['alignment' => Jc::END]);
        $rDt->addText('Invoice Date: ', ['bold' => true, 'size' => 8, 'color' => self::C_DARK]);
        $rDt->addText($documentOrder['issue_date_long'], ['size' => 8, 'color' => self::C_BLACK]);

        $section->addTextBreak(1, ['size' => 4]);

        // ── 4. DOCUMENT TITLE ─────────────────────────────────────────────────
        $section->addText('INVOICE', [
            'bold' => true, 'size' => 17, 'color' => self::C_BLACK,
        ], ['alignment' => Jc::CENTER, 'spaceBefore' => 60, 'spaceAfter' => 60]);

        // ── 5. STRONG RULE (blue, 1.6 px → sz=12) ─────────────────────────────
        $this->addHRule($phpWord, $section, self::C_BLUE_HR, 12);

        $section->addTextBreak(1, ['size' => 4]);

        // ── 6. PROJECT LINE (Project Name: … with dashed bottom) ─────────────
        $phpWord->addTableStyle('WF_Proj', ['borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMargin' => 40]);
        $tProj = $section->addTable('WF_Proj');
        $tProj->addRow();
        $cProj = $tProj->addCell(self::W_TOTAL, [
            'borderTopSize' => 0, 'borderTopColor' => 'FFFFFF',
            'borderLeftSize' => 0, 'borderLeftColor' => 'FFFFFF',
            'borderRightSize' => 0, 'borderRightColor' => 'FFFFFF',
            'borderBottomSize' => 4, 'borderBottomColor' => self::C_DASHED,
        ]);
        $rProj = $cProj->addTextRun(['alignment' => Jc::START]);
        $rProj->addText('Project Name: ', ['bold' => true, 'size' => 9, 'color' => self::C_BLACK]);
        $rProj->addText($documentOrder['product_name'], ['size' => 9, 'color' => '1f2937']);

        $section->addTextBreak(1, ['size' => 3]);

        // ── 7. INVOICE ITEMS TABLE ─────────────────────────────────────────────
        // Header cell: top + bottom border only (white bg)
        $hdrCellStyle = [
            'borderTopSize' => 6, 'borderTopColor' => self::C_BLACK,
            'borderBottomSize' => 6, 'borderBottomColor' => self::C_BLUE_TH,
            'borderLeftSize' => 0, 'borderLeftColor' => 'FFFFFF',
            'borderRightSize' => 0, 'borderRightColor' => 'FFFFFF',
            'bgColor' => 'FFFFFF',
        ];
        // Data cell: bottom border only
        $dataCellStyle = [
            'borderTopSize' => 0, 'borderTopColor' => 'FFFFFF',
            'borderBottomSize' => 6, 'borderBottomColor' => self::C_RULE,
            'borderLeftSize' => 0, 'borderLeftColor' => 'FFFFFF',
            'borderRightSize' => 0, 'borderRightColor' => 'FFFFFF',
        ];

        $phpWord->addTableStyle('WF_Items', ['borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMargin' => 60]);
        $tItems = $section->addTable('WF_Items');

        // Header row
        $tItems->addRow();
        $tItems->addCell(self::W_DATE, $hdrCellStyle)->addText('Date',             ['bold' => true, 'size' => 8], ['alignment' => Jc::START]);
        $tItems->addCell(self::W_ITEM, $hdrCellStyle)->addText('Item',             ['bold' => true, 'size' => 8], ['alignment' => Jc::START]);
        $tItems->addCell(self::W_DESC, $hdrCellStyle)->addText('Description',      ['bold' => true, 'size' => 8], ['alignment' => Jc::START]);
        $tItems->addCell(self::W_AMT,  $hdrCellStyle)->addText('Amount (' . $currency . ')', ['bold' => true, 'size' => 8], ['alignment' => Jc::END]);

        $dateStr = $generatedAt->format('M d');

        foreach ($items as $item) {
            $qty         = number_format((float) ($item['quantity'] ?? 0));
            $lineTotal   = number_format((float) ($item['line_total'] ?? $item['sales_total'] ?? 0), 2);
            $desc        = (string) ($item['description'] ?? '') ?: 'As approved quotation';
            $prodDays    = (string) $documentOrder['production_days'];
            $orderNumber = (string) $documentOrder['order_number'];

            $tItems->addRow();
            $tItems->addCell(self::W_DATE, $dataCellStyle)->addText($dateStr,                             ['size' => 8], ['alignment' => Jc::START]);
            $tItems->addCell(self::W_ITEM, $dataCellStyle)->addText((string) ($item['item_name'] ?? ''), ['size' => 8], ['alignment' => Jc::START]);

            $cDesc = $tItems->addCell(self::W_DESC, $dataCellStyle);
            $cDesc->addText('100% for ' . $qty . ' pcs',                                            ['size' => 8], ['alignment' => Jc::START]);
            $cDesc->addText('- Refer to quotation number ' . $orderNumber,                          ['size' => 8], ['alignment' => Jc::START]);
            $cDesc->addText('- Specifications: ' . $desc,                                           ['size' => 8], ['alignment' => Jc::START]);
            $cDesc->addText('- Production Lead Time: around ' . $prodDays . ' days',                ['size' => 8], ['alignment' => Jc::START]);

            $tItems->addCell(self::W_AMT, $dataCellStyle)->addText($lineTotal, ['size' => 8], ['alignment' => Jc::END]);
        }

        // Products Total row
        $tItems->addRow();
        $tItems->addCell(self::W_DATE, $dataCellStyle)->addText('', ['size' => 8]);
        $tItems->addCell(self::W_ITEM, $dataCellStyle)->addText('', ['size' => 8]);
        $tItems->addCell(self::W_DESC, $dataCellStyle)->addText('Products Total Amount:', ['bold' => true, 'size' => 8], ['alignment' => Jc::END]);
        $tItems->addCell(self::W_AMT,  $dataCellStyle)->addText(number_format($productsTotal, 2), ['bold' => true, 'size' => 8], ['alignment' => Jc::END]);

        // Local Bank Fee row
        $tItems->addRow();
        $tItems->addCell(self::W_DATE, $dataCellStyle)->addText($dateStr,               ['size' => 8], ['alignment' => Jc::START]);
        $tItems->addCell(self::W_ITEM, $dataCellStyle)->addText('Local Bank Fee',       ['size' => 8], ['alignment' => Jc::START]);
        $tItems->addCell(self::W_DESC, $dataCellStyle)->addText('Local Bank Charge of IMT', ['size' => 8], ['alignment' => Jc::START]);
        $tItems->addCell(self::W_AMT,  $dataCellStyle)->addText(number_format($bankFee, 2), ['size' => 8], ['alignment' => Jc::END]);

        // Grand Total row (dark bg chip for amount)
        $grandBase = [
            'borderTopSize' => 0, 'borderTopColor' => 'FFFFFF',
            'borderBottomSize' => 6, 'borderBottomColor' => self::C_RULE,
            'borderLeftSize' => 0, 'borderLeftColor' => 'FFFFFF',
            'borderRightSize' => 0, 'borderRightColor' => 'FFFFFF',
        ];
        $tItems->addRow();
        $tItems->addCell(self::W_DATE, $grandBase)->addText('', ['size' => 8]);
        $tItems->addCell(self::W_ITEM, $grandBase)->addText('', ['size' => 8]);
        $tItems->addCell(self::W_DESC, $grandBase)->addText('Final Total Amount:',   ['bold' => true, 'size' => 8], ['alignment' => Jc::END]);
        $cGrand = $tItems->addCell(self::W_AMT, array_merge($grandBase, ['bgColor' => self::C_BLACK]));
        $cGrand->addText($currency . number_format($invoiceTotalDue, 2), ['bold' => true, 'size' => 8, 'color' => 'FFFFFF'], ['alignment' => Jc::END]);

        $section->addTextBreak(1, ['size' => 5]);

        // ── 8. PAYMENT METHOD TITLE ────────────────────────────────────────────
        $section->addText('Payment Method ( For USD remittance )', [
            'bold' => true, 'size' => 9, 'color' => self::C_DARK, 'underline' => 'single',
        ], ['alignment' => Jc::START, 'spaceAfter' => 60]);

        // ── 9. PAYMENT TABLE ──────────────────────────────────────────────────
        $phpWord->addTableStyle('WF_Payment', ['borderSize' => 4, 'borderColor' => '7e8ea8', 'cellMargin' => 60]);
        $tPay = $section->addTable('WF_Payment');
        foreach ($paymentRows as $label => $value) {
            $tPay->addRow();
            $tPay->addCell(self::W_PAY_L, ['bgColor' => self::C_GRAY_TH, 'borderSize' => 4, 'borderColor' => '7e8ea8'])
                 ->addText($label, ['bold' => true, 'size' => 8, 'color' => self::C_BLACK], ['alignment' => Jc::START]);
            $tPay->addCell(self::W_PAY_V, ['borderSize' => 4, 'borderColor' => '7e8ea8'])
                 ->addText($value,         ['size' => 8, 'color' => self::C_BLACK], ['alignment' => Jc::START]);
        }

        $section->addTextBreak(1, ['size' => 4]);

        // ── 10. REMARK BOX (red text, gold border-top) ────────────────────────
        $phpWord->addTableStyle('WF_Remark', ['borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMargin' => 60]);
        $tRemark = $section->addTable('WF_Remark');
        $tRemark->addRow();
        $cRemark = $tRemark->addCell(self::W_TOTAL, [
            'borderTopSize' => 8, 'borderTopColor' => self::C_GOLD,
            'borderLeftSize' => 0, 'borderLeftColor' => 'FFFFFF',
            'borderRightSize' => 0, 'borderRightColor' => 'FFFFFF',
            'borderBottomSize' => 0, 'borderBottomColor' => 'FFFFFF',
        ]);
        $cRemark->addText(
            'REMARK: PLEASE USE THE FULL BENEFICIARY NAME ABOVE WHEN REMITTING. THANK YOU.',
            ['bold' => true, 'size' => 7, 'color' => self::C_RED],
            ['alignment' => Jc::START]
        );

        $section->addTextBreak(1, ['size' => 5]);

        // ── 11. FOOTER (Sales Rep left / QR right) ────────────────────────────
        $phpWord->addTableStyle('WF_Footer', ['borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMargin' => 30]);
        $tFoot = $section->addTable('WF_Footer');
        $tFoot->addRow();

        $cLeft = $tFoot->addCell(7340, array_merge($this->noBorder(), ['valign' => 'bottom']));
        $rRep  = $cLeft->addTextRun(['alignment' => Jc::START]);
        $rRep->addText('Sales Representative: ', ['bold' => true, 'size' => 8, 'color' => self::C_DARK]);
        $rRep->addText($salesRep ?: 'Sales Team', ['size' => 8, 'color' => self::C_GRAY_TXT]);
        $rGen = $cLeft->addTextRun(['alignment' => Jc::START]);
        $rGen->addText('Generated: ', ['bold' => true, 'size' => 8, 'color' => self::C_DARK]);
        $rGen->addText($generatedAt->format('Y-m-d H:i'), ['size' => 8, 'color' => self::C_GRAY_TXT]);

        $cRight = $tFoot->addCell(3204, array_merge($this->noBorder(), ['valign' => 'bottom']));
        if ($verificationQr !== '') {
            try {
                $cRight->addImage($verificationQr, [
                    'width'         => 74,
                    'height'        => 74,
                    'alignment'     => Jc::END,
                    'wrappingStyle' => 'inline',
                ]);
            } catch (\Throwable) {
                // QR image is optional; skip silently if not renderable.
            }
        }
        $cRight->addText('Scan to verify', ['size' => 7, 'italic' => true, 'color' => self::C_SUBTLE], ['alignment' => Jc::END]);

        // ── Serialize ─────────────────────────────────────────────────────────
        $writer = WordIOFactory::createWriter($phpWord, 'Word2007');
        ob_start();
        $writer->save('php://output');
        $contents = (string) ob_get_clean();

        $filename = 'Invoice-' . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $documentOrder['order_number']) . '.docx';

        return ['filename' => $filename, 'contents' => $contents];
    }

    /**
     * Adds a thin horizontal rule using a transparent single-cell table
     * with only the top border visible, matching the PDF's `<hr class="rule-strong">`.
     */
    private function addHRule(PhpWord $phpWord, mixed $section, string $color, int $sz): void
    {
        static $seq = 0;
        $seq++;
        $name = 'WF_HRule_' . $seq;
        $phpWord->addTableStyle($name, ['borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMargin' => 0]);
        $t = $section->addTable($name);
        $t->addRow(10);
        $t->addCell(self::W_TOTAL, [
            'borderTopSize' => $sz, 'borderTopColor' => $color,
            'borderLeftSize' => 0, 'borderLeftColor' => 'FFFFFF',
            'borderRightSize' => 0, 'borderRightColor' => 'FFFFFF',
            'borderBottomSize' => 0, 'borderBottomColor' => 'FFFFFF',
        ]);
    }

    /** Returns a style array with all four cell borders invisible. */
    private function noBorder(): array
    {
        return [
            'borderTopSize' => 0, 'borderTopColor' => 'FFFFFF',
            'borderLeftSize' => 0, 'borderLeftColor' => 'FFFFFF',
            'borderRightSize' => 0, 'borderRightColor' => 'FFFFFF',
            'borderBottomSize' => 0, 'borderBottomColor' => 'FFFFFF',
        ];
    }
}
