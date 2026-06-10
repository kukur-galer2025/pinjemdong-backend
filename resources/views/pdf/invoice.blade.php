<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Invoice - {{ $rental->invoice_number }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            line-height: 1.5;
            font-size: 14px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #10b981;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            color: #10b981;
            font-size: 24px;
        }
        .header p {
            margin: 0;
            color: #666;
            font-size: 12px;
        }
        .info-table {
            width: 100%;
            margin-bottom: 20px;
        }
        .info-table td {
            vertical-align: top;
        }
        .title-td {
            font-weight: bold;
            width: 120px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th, .items-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .items-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        .items-table .text-right {
            text-align: right;
        }
        .items-table .text-center {
            text-align: center;
        }
        .totals {
            width: 50%;
            float: right;
            border-collapse: collapse;
        }
        .totals td {
            padding: 5px 0;
        }
        .totals .total-row {
            border-top: 2px solid #333;
            font-weight: bold;
            font-size: 16px;
        }
        .footer {
            clear: both;
            margin-top: 50px;
            text-align: center;
            color: #888;
            font-size: 11px;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>INVOICE PENYEWAAN</h1>
        <p>Invoice #{{ $rental->invoice_number }} | Tanggal: {{ \Carbon\Carbon::parse($rental->created_at)->format('d M Y H:i') }}</p>
    </div>

    <table class="info-table">
        <tr>
            <td width="50%">
                <strong>Kepada:</strong><br>
                {{ $rental->user->name }}<br>
                {{ $rental->user->email }}<br>
                <br>
                <strong>Metode Pengambilan:</strong><br>
                {{ $rental->delivery_method === 'pickup' ? 'Ambil Sendiri' : 'Diantar' }}
                @if($rental->delivery_method === 'delivery' && $rental->delivery_address)
                    <br>{{ $rental->delivery_address }}
                @endif
            </td>
            <td width="50%">
                <table width="100%">
                    <tr>
                        <td class="title-td">Mulai Sewa:</td>
                        <td>{{ \Carbon\Carbon::parse($rental->start_date)->format('d M Y') }}</td>
                    </tr>
                    <tr>
                        <td class="title-td">Selesai Sewa:</td>
                        <td>{{ \Carbon\Carbon::parse($rental->end_date)->format('d M Y') }}</td>
                    </tr>
                    <tr>
                        <td class="title-td">Status:</td>
                        <td>{{ strtoupper($rental->status) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Barang</th>
                <th class="text-center">Jml</th>
                <th class="text-right">Harga/Hari</th>
                <th class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rental->items as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>
                    {{ $item->product->name }}
                    @if($item->productUnit)
                        <br><small style="color:#666">S/N: {{ $item->productUnit->serial_number }}</small>
                    @endif
                </td>
                <td class="text-center">{{ $item->quantity }}</td>
                <td class="text-right">Rp {{ number_format($item->price_per_day, 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Subtotal Barang:</td>
            <td class="text-right">Rp {{ number_format($rental->subtotal, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Ongkos Kirim:</td>
            <td class="text-right">Rp {{ number_format($rental->delivery_cost, 0, ',', '.') }}</td>
        </tr>
        @if($rental->late_fee_total > 0)
        <tr style="color: red;">
            <td>Denda Keterlambatan:</td>
            <td class="text-right">Rp {{ number_format($rental->late_fee_total, 0, ',', '.') }}</td>
        </tr>
        @endif
        <tr class="total-row">
            <td>TOTAL TAGIHAN:</td>
            <td class="text-right">Rp {{ number_format($rental->total_amount, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Telah Dibayar (DP):</td>
            <td class="text-right">- Rp {{ number_format($rental->dp_amount, 0, ',', '.') }}</td>
        </tr>
        <tr style="font-weight: bold; color: {{ $rental->remaining_amount > 0 ? 'red' : 'green' }}">
            <td>SISA TAGIHAN:</td>
            <td class="text-right">Rp {{ number_format($rental->remaining_amount, 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="footer">
        Dicetak otomatis oleh Sistem Pinjemdong pada {{ date('d M Y H:i:s') }}<br>
        Harap simpan nota ini sebagai bukti transaksi yang sah.
    </div>

</body>
</html>
