<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->nomor_invoice_vendor }}</title>
    <style>
        @page {
            margin: 35px 40px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #263238;
            font-size: 11px;
            line-height: 1.6;
        }

        .header {
            border-bottom: 3px solid #2563eb;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }

        .title {
            font-size: 25px;
            font-weight: bold;
            color: #1e40af;
            margin: 0;
        }

        .subtitle {
            color: #64748b;
            font-size: 10px;
        }

        .invoice-number {
            font-size: 13px;
            font-weight: bold;
            margin-top: 8px;
        }

        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #1e40af;
            margin: 18px 0 7px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 4px 3px;
            vertical-align: top;
        }

        .label {
            color: #64748b;
            width: 125px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .items-table th {
            background: #1e40af;
            color: white;
            padding: 9px 7px;
            text-align: left;
            font-size: 10px;
        }

        .items-table td {
            border-bottom: 1px solid #e2e8f0;
            padding: 9px 7px;
        }

        .text-right {
            text-align: right;
        }

        .total-section {
            margin-top: 18px;
            text-align: right;
        }

        .total-label {
            color: #64748b;
            font-size: 11px;
        }

        .total-value {
            font-size: 19px;
            font-weight: bold;
            color: #1e40af;
        }

        .footer {
            margin-top: 45px;
            border-top: 1px solid #cbd5e1;
            padding-top: 12px;
            text-align: center;
            color: #64748b;
            font-size: 9px;
        }

        .signature {
            margin-top: 40px;
            width: 100%;
        }

        .signature td {
            width: 50%;
            text-align: center;
            vertical-align: top;
        }
    </style>
</head>
<body>

    <div class="header">
        <p class="title">INVOICE</p>
        <p class="subtitle">Dokumen Tagihan Digital PAGORA</p>
        <p class="invoice-number">
            Nomor: {{ $invoice->nomor_invoice_vendor }}
        </p>
    </div>

    <div class="section-title">INFORMASI VENDOR</div>

    <table class="info-table">
        <tr>
            <td class="label">Nama Perusahaan</td>
            <td>: {{ $invoice->vendor->company_name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">NPWP</td>
            <td>: {{ $invoice->vendor->tax_number ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Email</td>
            <td>: {{ $invoice->vendor->contact_email ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Telepon</td>
            <td>: {{ $invoice->vendor->phone ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Alamat</td>
            <td>: {{ $invoice->vendor->address ?? '-' }}</td>
        </tr>
    </table>

    <div class="section-title">INFORMASI TAGIHAN</div>

    <table class="info-table">
        <tr>
            <td class="label">Nomor Invoice</td>
            <td>: {{ $invoice->nomor_invoice_vendor }}</td>
        </tr>
        <tr>
            <td class="label">Tanggal Invoice</td>
            <td>: {{ $invoice->tanggal_invoice?->format('d/m/Y') ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Nomor Purchase Order</td>
            <td>: {{ $invoice->purchaseOrder->nomor_po ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Status Matching</td>
            <td>: {{ ucfirst($invoice->status_matching ?? 'pending') }}</td>
        </tr>
    </table>

    <div class="section-title">RINCIAN BARANG</div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">No.</th>
                <th style="width: 35%;">Nama Komoditas</th>
                <th class="text-right" style="width: 15%;">Jumlah</th>
                <th class="text-right" style="width: 20%;">Harga Satuan</th>
                <th class="text-right" style="width: 25%;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoice->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        {{ $item->purchaseOrderItem->komoditas->name ?? '-' }}
                    </td>
                    <td class="text-right">
                        {{ number_format((float) $item->quantity, 3, ',', '.') }}
                    </td>
                    <td class="text-right">
                        Rp {{ number_format((float) $item->unit_price, 2, ',', '.') }}
                    </td>
                    <td class="text-right">
                        Rp {{ number_format((float) $item->subtotal, 2, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center;">
                        Tidak ada rincian barang.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="total-section">
        <div class="total-label">TOTAL TAGIHAN</div>
        <div class="total-value">
            Rp {{ number_format((float) $invoice->jumlah_tagihan, 2, ',', '.') }}
        </div>
    </div>

    <table class="signature">
        <tr>
            <td>
                Vendor,
                <br><br><br><br>
                <strong>{{ $invoice->vendor->company_name ?? '-' }}</strong>
            </td>
            <td>
                Tanggal Penerbitan,
                <br><br><br><br>
                <strong>{{ $invoice->tanggal_invoice?->format('d/m/Y') ?? '-' }}</strong>
            </td>
        </tr>
    </table>

    <div class="footer">
        Dokumen ini diterbitkan secara digital melalui sistem PAGORA.
        <br>
        Invoice: {{ $invoice->nomor_invoice_vendor }}
    </div>

</body>
</html>
