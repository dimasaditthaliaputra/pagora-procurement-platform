<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatusItemPenerimaan;
use App\Models\PenerimaanBarangItem;
use App\Models\Sengketa;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Pure-PHP PDF 1.4 generator for official Procurement Dispute & Audit Log exports
 * (`UC-18` step 8, `FR-31`, `NFR-15`: Bahasa Indonesia, WIB, IDR — zero external dependencies).
 */
class SengketaPdfExporter
{
    private const PAGE_WIDTH = 595.28;

    private const PAGE_HEIGHT = 841.89;

    private const MARGIN_X = 42.0;

    private const TOP_Y = 795.0;

    private const BOTTOM_Y = 62.0;

    private const CONTENT_WIDTH = 511.28;

    /**
     * Indonesian short month names for consistent WIB formatting.
     *
     * @var array<int, string>
     */
    private const MONTHS_ID = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'Mei',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Agu',
        9 => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Des',
    ];

    /**
     * Raw PDF content stream commands per page.
     *
     * @var list<string>
     */
    private array $pages = [];

    private int $currentPage = -1;

    private float $cursorY = self::TOP_Y;

    private string $headerSubline = '';

    /**
     * Render a complete PDF 1.4 document binary for the given dispute.
     *
     * @return array{content: string, filename: string}
     */
    public function generate(Sengketa $sengketa, User $exportedBy): array
    {
        $this->pages = [];
        $this->currentPage = -1;
        $this->cursorY = self::TOP_Y;

        $po = $sengketa->purchaseOrder;
        $tenant = $po?->perusahaanBuyer;
        $vendor = $po?->vendor;
        $poItem = $sengketa->poItem;
        $commodity = $poItem?->komoditas;
        $invoice = $sengketa->invoice;

        $poNumber = $po?->nomor_po ?? 'PO';
        $shortUuid = Str::upper(substr($sengketa->uuid, 0, 8));
        $this->headerSubline = "Ref. Sengketa #{$shortUuid}  |  PO: {$poNumber}";

        $this->startNewPage(true);

        // Banner Header
        $this->drawRect(self::MARGIN_X, $this->cursorY - 54.0, self::CONTENT_WIDTH, 54.0, [0.09, 0.09, 0.11], null);
        $this->drawText(
            self::MARGIN_X + 14.0,
            $this->cursorY - 20.0,
            'DOKUMEN KENDALA PENGADAAN & LOG SENGKETA',
            'F2',
            13.0,
            [1.0, 1.0, 1.0]
        );
        $tenantTitle = $tenant?->company_name ?? 'Sistem E-Procurement B2B PAGORA';
        $this->drawText(
            self::MARGIN_X + 14.0,
            $this->cursorY - 36.0,
            "{$tenantTitle}  |  Kode Sengketa: #{$shortUuid}  |  Status: ".$sengketa->status->label(),
            'F1',
            9.0,
            [0.82, 0.84, 0.88]
        );
        $this->drawText(
            self::MARGIN_X + 14.0,
            $this->cursorY - 48.0,
            'Dicetak oleh: '.$exportedBy->name.' pada '.$this->formatDateTimeWib(now()),
            'F1',
            8.0,
            [0.68, 0.71, 0.76]
        );
        $this->cursorY -= 68.0;

        // Section 1: Ringkasan Kendala & Dokumen Purchase Order
        $this->drawSectionHeading('1. Ringkasan Kendala & Purchase Order Terkait');
        $openedByLabel = $sengketa->pembuka !== null
            ? $sengketa->pembuka->name.' ('.($sengketa->pembuka->roleName()?->label() ?? 'Pengguna').')'
            : 'Sistem Otomatis';

        $summaryRows = [
            ['UUID Sengketa', $sengketa->uuid],
            ['Status Sengketa', $sengketa->status->label()],
            ['Kategori Pemicu', $sengketa->triggerLabel()],
            ['Tanggal Dibuka', $this->formatDateTimeWib($sengketa->tanggal_dibuka)],
            ['Tanggal Selesai', $sengketa->tanggal_selesai ? $this->formatDateTimeWib($sengketa->tanggal_selesai) : 'Belum Selesai (Masih Aktif)'],
            ['Dibuka Oleh', $openedByLabel],
            ['Nomor Purchase Order', $poNumber.' (Status PO: '.($po?->status?->label() ?? '-').')'],
            ['Tanggal PO', $this->formatDateOnly($po?->tanggal_po)],
            ['Perusahaan Pembeli (Tenant)', $tenant?->company_name ?? '-'],
            ['Pemasok (Vendor)', ($vendor?->company_name ?? '-').($vendor?->contact_email ? ' — '.$vendor->contact_email : '')],
        ];
        $this->drawKeyValueRows($summaryRows);

        $this->cursorY -= 6.0;
        $this->drawParagraphBlock(
            'Alasan / Kronologi Pembukaan Sengketa:',
            $sengketa->reason ?: 'Tidak ada catatan alasan tambahan.'
        );

        // Section 2: Rincian Objek yang Dipersengketakan (Item QC / Invoice)
        $this->cursorY -= 8.0;
        $this->drawSectionHeading('2. Rincian Objek Sengketa & Bukti Pemeriksaan');

        if ($poItem !== null) {
            $unit = $commodity?->unit ?? 'kg';
            $orderedQty = (float) $poItem->jumlah_pesanan;
            $unitPrice = (float) ($poItem->harga_satuan_po ?? 0);
            $subtotal = (float) ($poItem->subtotal ?? ($orderedQty * $unitPrice));
            $tenantId = $po !== null ? (int) $po->perusahaan_buyer_id : null;
            $tolerance = PenerimaanBarangService::resolveEffectiveShrinkageTolerance($commodity, $tenantId);

            /** @var PenerimaanBarangItem|null $rejectedReceiptItem */
            $rejectedReceiptItem = null;
            if ($poItem->relationLoaded('penerimaanBarangItems')) {
                $rejectedReceiptItem = $poItem->penerimaanBarangItems
                    ->first(static fn (PenerimaanBarangItem $ri): bool => ($ri->status instanceof StatusItemPenerimaan ? $ri->status->value : (string) $ri->status) === 'rejected')
                    ?? $poItem->penerimaanBarangItems->sortByDesc('id')->first();
            }

            $itemRows = [
                ['Komoditas Dipersoalkan', ($commodity?->name ?? 'Komoditas').' (Satuan: '.$unit.')'],
                ['Kuantitas Pesanan PO', $this->formatQty($orderedQty).' '.$unit],
                ['Harga Satuan Final PO', $this->formatRupiah($unitPrice).' / '.$unit.' (Subtotal: '.$this->formatRupiah($subtotal).')'],
                ['Toleransi Susut Tenant', $this->formatQty($tolerance).'%'],
            ];

            if ($rejectedReceiptItem !== null) {
                $receivedQty = (float) $rejectedReceiptItem->received_quantity;
                $shortage = max(0.0, round($orderedQty - $receivedQty, 3));
                $shrinkagePct = $orderedQty > 0 ? round(($shortage / $orderedQty) * 100, 2) : 0.0;
                $photos = is_array($rejectedReceiptItem->rejection_photos) ? $rejectedReceiptItem->rejection_photos : [];
                $receipt = $rejectedReceiptItem->goodsReceipt;

                $itemRows[] = ['Kuantitas Fisik Diterima QC', $this->formatQty($receivedQty).' '.$unit.' (Selisih: '.$this->formatQty($shortage).' '.$unit.' / '.$this->formatQty($shrinkagePct).'%)'];
                $itemRows[] = ['Petugas Inspeksi QC', ($receipt?->inspector?->name ?? '-').' pada '.$this->formatDateTimeWib($receipt?->inspected_at)];
                $itemRows[] = ['Plat Armada Dicocokkan', $receipt?->matched_plate_number ?? '-'];
                $itemRows[] = ['Foto Bukti Penolakan QC', count($photos).' berkas foto bukti tersimpan permanen (immutable)'];
            }

            $this->drawKeyValueRows($itemRows);

            if ($rejectedReceiptItem !== null && filled($rejectedReceiptItem->notes)) {
                $this->cursorY -= 4.0;
                $this->drawParagraphBlock('Catatan Pemeriksaan Fisik QC Gudang:', (string) $rejectedReceiptItem->notes);
            }
        } elseif ($invoice !== null) {
            $invoiceRows = [
                ['Nomor Invoice Vendor', $invoice->nomor_invoice_vendor],
                ['Tanggal Invoice', $this->formatDateOnly($invoice->tanggal_invoice)],
                ['Nominal Tagihan Invoice', $this->formatRupiah((float) $invoice->jumlah_tagihan)],
                ['Status 3-Way Matching', strtoupper((string) ($invoice->status_matching ?? 'mismatched'))],
                ['Diverifikasi Oleh', $invoice->verifikator?->name ?? '-'],
            ];
            $this->drawKeyValueRows($invoiceRows);
        } else {
            $poTotal = (float) ($po?->poItems?->sum('subtotal') ?? 0);
            $itemCount = $po?->poItems?->count() ?? 0;
            $this->drawKeyValueRows([
                ['Cakupan Sengketa', 'Seluruh Dokumen Purchase Order ('.$itemCount.' item komoditas)'],
                ['Total Nilai Kesepakatan PO', $this->formatRupiah($poTotal)],
            ]);
        }

        // Section 3: Riwayat Smart Negotiation Chat Terkait PO
        $this->cursorY -= 8.0;
        $this->drawSectionHeading('3. Riwayat Smart Negotiation Chat pada Purchase Order');
        $messages = ($po !== null && $po->relationLoaded('chatNegosiasi')) ? $po->chatNegosiasi : collect();

        if ($messages->isEmpty()) {
            $this->ensureSpace(22.0);
            $this->drawText(
                self::MARGIN_X + 4.0,
                $this->cursorY - 12.0,
                'Tidak terdapat riwayat percakapan pada Smart Negotiation Chat untuk PO ini.',
                'F1',
                9.0,
                [0.42, 0.45, 0.50]
            );
            $this->cursorY -= 20.0;
        } else {
            foreach ($messages as $idx => $msg) {
                $senderName = $msg->pengirim?->name ?? 'Pengguna';
                $senderRole = $msg->pengirim?->roleName()?->label() ?? '';
                $timestamp = $this->formatDateTimeWib($msg->created_at);
                $offerPart = $msg->nominal_tawar !== null && (float) $msg->nominal_tawar > 0
                    ? '  [Tawaran Harga: '.$this->formatRupiah((float) $msg->nominal_tawar).']'
                    : '';
                $headerLine = ($idx + 1).'. '.$senderName.($senderRole !== '' ? " ({$senderRole})" : '').'  —  '.$timestamp.$offerPart;
                $bodyText = trim((string) ($msg->pesan_teks ?? '-'));
                $this->drawTimelineEntry($headerLine, $bodyText);
            }
        }

        // Section 4: Jejak Audit Log Sengketa (Immutable)
        $this->cursorY -= 8.0;
        $this->drawSectionHeading('4. Jejak Audit Log Sengketa (Permanen / Immutable)');
        $logs = $sengketa->relationLoaded('logs') ? $sengketa->logs : collect();

        if ($logs->isEmpty()) {
            $this->ensureSpace(22.0);
            $this->drawText(
                self::MARGIN_X + 4.0,
                $this->cursorY - 12.0,
                'Belum terdapat entri pada Log Sengketa.',
                'F1',
                9.0,
                [0.42, 0.45, 0.50]
            );
            $this->cursorY -= 20.0;
        } else {
            foreach ($logs as $idx => $log) {
                $authorName = $log->penulis?->name ?? 'Sistem Otomatis';
                $authorRole = $log->penulis?->roleName()?->label() ?? 'Sistem';
                $typeLabel = $this->logTypeLabel((string) ($log->type ?? 'note'));
                $attachmentNote = filled($log->file_bukti)
                    ? '  [Lampiran Bukti: '.basename((string) $log->file_bukti).']'
                    : '';
                $headerLine = ($idx + 1).'. ['.$typeLabel.'] '.$authorName." ({$authorRole})  —  ".$this->formatDateTimeWib($log->created_at).$attachmentNote;
                $bodyText = trim((string) ($log->isi_pesan ?? '-'));
                $this->drawTimelineEntry($headerLine, $bodyText);
            }
        }

        $safePo = preg_replace('/[^A-Za-z0-9\-]/', '-', $poNumber) ?: 'PO';
        $filename = "Dokumen-Sengketa-{$safePo}-{$shortUuid}.pdf";

        return [
            'content' => $this->compilePdfBinary(),
            'filename' => $filename,
        ];
    }

    private function startNewPage(bool $isFirstPage = false): void
    {
        $this->pages[] = '';
        $this->currentPage = count($this->pages) - 1;
        $this->cursorY = self::TOP_Y;

        if (! $isFirstPage) {
            $this->drawText(
                self::MARGIN_X,
                $this->cursorY,
                'DOKUMEN LOG SENGKETA PENGADAAN (LANJUTAN)  —  '.$this->headerSubline,
                'F2',
                8.5,
                [0.38, 0.41, 0.46]
            );
            $this->drawLine(self::MARGIN_X, $this->cursorY - 6.0, self::MARGIN_X + self::CONTENT_WIDTH, $this->cursorY - 6.0, [0.82, 0.84, 0.88]);
            $this->cursorY -= 20.0;
        }
    }

    private function ensureSpace(float $requiredHeight): void
    {
        if (($this->cursorY - $requiredHeight) < self::BOTTOM_Y) {
            $this->startNewPage(false);
        }
    }

    private function drawSectionHeading(string $title): void
    {
        $this->ensureSpace(34.0);
        $this->drawRect(self::MARGIN_X, $this->cursorY - 20.0, self::CONTENT_WIDTH, 20.0, [0.95, 0.96, 0.98], [0.84, 0.86, 0.90]);
        $this->drawText(self::MARGIN_X + 8.0, $this->cursorY - 13.5, $title, 'F2', 9.5, [0.10, 0.12, 0.16]);
        $this->cursorY -= 26.0;
    }

    /**
     * @param  list<array{0: string, 1: string}>  $rows
     */
    private function drawKeyValueRows(array $rows): void
    {
        $labelWidth = 158.0;
        foreach ($rows as [$label, $value]) {
            $wrappedLines = $this->wrapText($value, 68);
            $rowHeight = max(16.0, count($wrappedLines) * 12.5 + 4.0);
            $this->ensureSpace($rowHeight + 4.0);

            $this->drawText(self::MARGIN_X + 4.0, $this->cursorY - 11.0, $label, 'F2', 8.5, [0.32, 0.35, 0.40]);
            $this->drawText(self::MARGIN_X + $labelWidth - 8.0, $this->cursorY - 11.0, ':', 'F1', 8.5, [0.32, 0.35, 0.40]);

            foreach ($wrappedLines as $lineIndex => $line) {
                $this->drawText(
                    self::MARGIN_X + $labelWidth,
                    $this->cursorY - 11.0 - ($lineIndex * 12.0),
                    $line,
                    'F1',
                    8.8,
                    [0.09, 0.10, 0.12]
                );
            }

            $this->drawLine(
                self::MARGIN_X,
                $this->cursorY - $rowHeight,
                self::MARGIN_X + self::CONTENT_WIDTH,
                $this->cursorY - $rowHeight,
                [0.91, 0.92, 0.94]
            );
            $this->cursorY -= $rowHeight;
        }
    }

    private function drawParagraphBlock(string $heading, string $body): void
    {
        $lines = $this->wrapText($body, 88);
        $boxHeight = 20.0 + (count($lines) * 12.0) + 8.0;
        $this->ensureSpace($boxHeight + 6.0);

        $this->drawRect(self::MARGIN_X, $this->cursorY - $boxHeight, self::CONTENT_WIDTH, $boxHeight, [0.985, 0.985, 0.99], [0.86, 0.88, 0.91]);
        $this->drawText(self::MARGIN_X + 8.0, $this->cursorY - 13.0, $heading, 'F2', 8.5, [0.22, 0.25, 0.30]);

        foreach ($lines as $idx => $line) {
            $this->drawText(
                self::MARGIN_X + 8.0,
                $this->cursorY - 26.0 - ($idx * 12.0),
                $line,
                'F1',
                8.8,
                [0.12, 0.14, 0.17]
            );
        }

        $this->cursorY -= ($boxHeight + 4.0);
    }

    private function drawTimelineEntry(string $headerLine, string $bodyText): void
    {
        $headerLines = $this->wrapText($headerLine, 88);
        $bodyLines = $this->wrapText($bodyText, 88);
        $entryHeight = (count($headerLines) * 11.5) + (count($bodyLines) * 11.5) + 12.0;

        $this->ensureSpace($entryHeight + 6.0);
        $this->drawRect(self::MARGIN_X, $this->cursorY - $entryHeight, self::CONTENT_WIDTH, $entryHeight, [1.0, 1.0, 1.0], [0.87, 0.89, 0.92]);

        $offsetY = 12.0;
        foreach ($headerLines as $hLine) {
            $this->drawText(self::MARGIN_X + 8.0, $this->cursorY - $offsetY, $hLine, 'F2', 8.3, [0.14, 0.18, 0.26]);
            $offsetY += 11.5;
        }

        $offsetY += 1.5;
        foreach ($bodyLines as $bLine) {
            $this->drawText(self::MARGIN_X + 8.0, $this->cursorY - $offsetY, $bLine, 'F1', 8.6, [0.18, 0.20, 0.24]);
            $offsetY += 11.5;
        }

        $this->cursorY -= ($entryHeight + 4.5);
    }

    /**
     * @param  array{0: float, 1: float, 2: float}|null  $fillRgb
     * @param  array{0: float, 1: float, 2: float}|null  $strokeRgb
     */
    private function drawRect(float $x, float $y, float $w, float $h, ?array $fillRgb, ?array $strokeRgb): void
    {
        $cmd = "q\n";
        if ($fillRgb !== null) {
            $cmd .= sprintf("%.3F %.3F %.3F rg\n", $fillRgb[0], $fillRgb[1], $fillRgb[2]);
        }
        if ($strokeRgb !== null) {
            $cmd .= sprintf("0.6 w %.3F %.3F %.3F RG\n", $strokeRgb[0], $strokeRgb[1], $strokeRgb[2]);
        }

        $op = ($fillRgb !== null && $strokeRgb !== null) ? 'B' : ($fillRgb !== null ? 'f' : 'S');
        $cmd .= sprintf("%.2F %.2F %.2F %.2F re %s\nQ\n", $x, $y, $w, $h, $op);
        $this->pages[$this->currentPage] .= $cmd;
    }

    /**
     * @param  array{0: float, 1: float, 2: float}  $rgb
     */
    private function drawLine(float $x1, float $y1, float $x2, float $y2, array $rgb): void
    {
        $this->pages[$this->currentPage] .= sprintf(
            "q 0.5 w %.3F %.3F %.3F RG %.2F %.2F m %.2F %.2F l S Q\n",
            $rgb[0],
            $rgb[1],
            $rgb[2],
            $x1,
            $y1,
            $x2,
            $y2
        );
    }

    /**
     * @param  array{0: float, 1: float, 2: float}  $rgb
     */
    private function drawText(float $x, float $y, string $text, string $fontKey, float $fontSize, array $rgb): void
    {
        $escaped = $this->escapePdfText($text);
        $this->pages[$this->currentPage] .= sprintf(
            "BT /%s %.2F Tf %.3F %.3F %.3F rg 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            $fontKey,
            $fontSize,
            $rgb[0],
            $rgb[1],
            $rgb[2],
            $x,
            $y,
            $escaped
        );
    }

    private function compilePdfBinary(): string
    {
        $totalPages = count($this->pages);
        foreach ($this->pages as $i => $stream) {
            $pageNum = $i + 1;
            $footerLeft = 'Dokumen Jejak Audit Permanen Sistem E-Procurement PAGORA (Immutable)';
            $footerRight = "Halaman {$pageNum} dari {$totalPages}";

            $footerCmd = sprintf(
                "q 0.5 w 0.84 0.86 0.90 RG %.2F 44.00 m %.2F 44.00 l S Q\n",
                self::MARGIN_X,
                self::MARGIN_X + self::CONTENT_WIDTH
            );
            $footerCmd .= sprintf(
                "BT /F1 7.80 Tf 0.45 0.48 0.53 rg 1 0 0 1 %.2F 32.00 Tm (%s) Tj ET\n",
                self::MARGIN_X,
                $this->escapePdfText($footerLeft)
            );
            $footerCmd .= sprintf(
                "BT /F2 7.80 Tf 0.35 0.38 0.43 rg 1 0 0 1 %.2F 32.00 Tm (%s) Tj ET\n",
                self::MARGIN_X + self::CONTENT_WIDTH - 78.0,
                $this->escapePdfText($footerRight)
            );

            $this->pages[$i] = $stream.$footerCmd;
        }

        // Object IDs:
        // 1: Catalog
        // 2: Pages
        // 3: Helvetica (/F1)
        // 4: Helvetica-Bold (/F2)
        // 5: Courier (/F3)
        // 6..N: Page + ContentStream pairs
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objects[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>';

        $kids = [];
        $nextObjId = 6;

        foreach ($this->pages as $contentStream) {
            $pageObjId = $nextObjId++;
            $streamObjId = $nextObjId++;
            $kids[] = "{$pageObjId} 0 R";

            $objects[$pageObjId] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R >> >> /Contents %d 0 R >>',
                self::PAGE_WIDTH,
                self::PAGE_HEIGHT,
                $streamObjId
            );

            $streamLen = strlen($contentStream);
            $objects[$streamObjId] = "<< /Length {$streamLen} >>\nstream\n{$contentStream}endstream";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxId = max(array_keys($objects));
        $size = $maxId + 1;

        $pdf .= "xref\n0 {$size}\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= $maxId; $i++) {
            $offset = $offsets[$i] ?? 0;
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size {$size} /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    /**
     * Wrap multi-paragraph text into lines fitting the given max character width.
     *
     * @return list<string>
     */
    private function wrapText(string $text, int $maxChars): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", trim($text));
        if ($normalized === '') {
            return ['-'];
        }

        $paragraphs = explode("\n", $normalized);
        $lines = [];

        foreach ($paragraphs as $paragraph) {
            $clean = preg_replace('/\s+/', ' ', trim($paragraph)) ?? '';
            if ($clean === '') {
                $lines[] = '';

                continue;
            }

            $wrapped = wordwrap($clean, $maxChars, "\n", true);
            foreach (explode("\n", $wrapped) as $line) {
                $lines[] = $line;
            }
        }

        return $lines === [] ? ['-'] : $lines;
    }

    private function escapePdfText(string $text): string
    {
        $normalized = str_replace(
            ['—', '–', '•', '·', '“', '”', '‘', '’'],
            ['-', '-', '*', '-', '"', '"', "'", "'"],
            $text
        );

        $converted = function_exists('iconv')
            ? @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $normalized)
            : false;

        $latin = is_string($converted) && $converted !== ''
            ? $converted
            : (preg_replace('/[^\x20-\x7E]/', '?', $normalized) ?? '');

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $latin);
    }

    private function logTypeLabel(string $type): string
    {
        return match ($type) {
            'status_change' => 'Perubahan Status',
            'evidence' => 'Bukti Tambahan',
            'chat_snapshot' => 'Snapshot Negosiasi',
            'resolution' => 'Penyelesaian',
            default => 'Catatan Tinjauan / Mediasi',
        };
    }

    private function formatDateTimeWib(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        $dt = ($value instanceof Carbon ? $value->copy() : Carbon::parse((string) $value))
            ->setTimezone('Asia/Jakarta');

        $month = self::MONTHS_ID[(int) $dt->format('n')] ?? $dt->format('M');

        return $dt->format('d').' '.$month.' '.$dt->format('Y, H:i').' WIB';
    }

    private function formatDateOnly(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        $dt = $value instanceof Carbon ? $value : Carbon::parse((string) $value);
        $month = self::MONTHS_ID[(int) $dt->format('n')] ?? $dt->format('M');

        return $dt->format('d').' '.$month.' '.$dt->format('Y');
    }

    private function formatRupiah(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    private function formatQty(float $qty): string
    {
        $formatted = number_format($qty, 3, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }
}
