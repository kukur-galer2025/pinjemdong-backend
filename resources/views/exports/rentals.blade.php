<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Transaksi PinjemDong</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 12px; color: #334155; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #e2e8f0; }
        .header h1 { margin: 0; font-size: 28px; color: #6366f1; letter-spacing: -1px; font-weight: 800; }
        .header p { margin: 5px 0 0 0; color: #64748b; font-size: 13px; }
        
        .summary-container { width: 100%; margin-bottom: 30px; }
        .summary-box { display: inline-block; width: 31%; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; text-align: center; margin-right: 2%; }
        .summary-box:last-child { margin-right: 0; }
        .summary-label { font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; margin-bottom: 5px; }
        .summary-value { font-size: 18px; color: #0f172a; font-weight: bold; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px 15px; text-align: left; }
        th { background-color: #f1f5f9; font-weight: 700; color: #475569; font-size: 11px; text-transform: uppercase; border-bottom: 2px solid #cbd5e1; }
        td { border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        tr:nth-child(even) td { background-color: #f8fafc; }
        
        .status { font-weight: bold; padding: 4px 8px; border-radius: 4px; font-size: 10px; text-transform: uppercase; display: inline-block; }
        .status-completed { background-color: #d1fae5; color: #065f46; }
        .status-rented { background-color: #dbeafe; color: #1e40af; }
        .status-cancelled { background-color: #fee2e2; color: #991b1b; }
        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-other { background-color: #f1f5f9; color: #475569; }

        .invoice-text { font-family: monospace; font-weight: 600; color: #3b82f6; }
        .price-text { font-weight: 700; color: #0f172a; }
        .sub-text { font-size: 10px; color: #64748b; display: block; margin-top: 2px; }
        
        .footer { text-align: right; margin-top: 40px; font-size: 10px; color: #94a3b8; font-style: italic; border-top: 1px solid #e2e8f0; padding-top: 10px; }
    </style>
</head>
<body>

    @php
        $totalRevenue = $rentals->where('status', '!=', 'cancelled')->sum('total_amount');
        $totalDP = $rentals->where('status', '!=', 'cancelled')->sum('dp_amount');
        $completedRentals = $rentals->where('status', 'returned')->count();
        $totalItems = $rentals->count();
    @endphp

    <div class="header">
        <h1>PINJEMDONG</h1>
        <p>Laporan Resmi Transaksi Penyewaan</p>
        <p style="font-size: 11px; margin-top: 10px;">Dicetak pada: {{ now()->format('d F Y, H:i') }}</p>
    </div>

    <div class="summary-container">
        <div class="summary-box">
            <div class="summary-label">Total Transaksi</div>
            <div class="summary-value">{{ $totalItems }} Pesanan</div>
        </div>
        <div class="summary-box">
            <div class="summary-label">Transaksi Selesai</div>
            <div class="summary-value">{{ $completedRentals }} Pesanan</div>
        </div>
        <div class="summary-box" style="background: #eff6ff; border-color: #bfdbfe;">
            <div class="summary-label" style="color: #2563eb;">Total Nilai Transaksi</div>
            <div class="summary-value" style="color: #1d4ed8;">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 15%;">Invoice & Tgl</th>
                <th style="width: 15%;">Pelanggan</th>
                <th style="width: 25%;">Barang Sewa & Periode</th>
                <th style="width: 15%;">Total Tagihan</th>
                <th style="width: 15%;">Total DP</th>
                <th style="width: 10%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rentals as $index => $rental)
            <tr>
                <td style="text-align: center; color: #64748b;">{{ $index + 1 }}</td>
                <td>
                    <span class="invoice-text">{{ $rental->invoice_number }}</span>
                    <span class="sub-text">{{ $rental->created_at->format('d M Y, H:i') }}</span>
                </td>
                <td>
                    <strong style="color: #334155;">{{ $rental->user->name ?? '-' }}</strong>
                    <span class="sub-text">{{ $rental->user->email ?? '-' }}</span>
                </td>
                <td>
                    <div style="font-size: 11px; line-height: 1.4;">
                        @foreach($rental->items as $item)
                            &bull; {{ $item->product ? $item->product->name : 'Barang Dihapus' }}<br>
                        @endforeach
                    </div>
                    <span class="sub-text" style="margin-top: 4px;">Periode: {{ \Carbon\Carbon::parse($rental->start_date)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($rental->end_date)->format('d/m/Y') }}</span>
                </td>
                <td>
                    <span class="price-text">Rp {{ number_format($rental->total_amount, 0, ',', '.') }}</span>
                </td>
                <td>
                    <span class="price-text" style="color: #059669;">Rp {{ number_format($rental->dp_amount, 0, ',', '.') }}</span>
                </td>
                <td>
                    @php
                        $statusClass = 'status-other';
                        if ($rental->status == 'returned') $statusClass = 'status-completed';
                        elseif ($rental->status == 'rented') $statusClass = 'status-rented';
                        elseif ($rental->status == 'cancelled') $statusClass = 'status-cancelled';
                        elseif (str_contains($rental->status, 'pending')) $statusClass = 'status-pending';
                    @endphp
                    <span class="status {{ $statusClass }}">{{ str_replace('_', ' ', $rental->status) }}</span>
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" style="text-align: right; font-weight: 800; background-color: #f1f5f9; border-top: 2px solid #cbd5e1;">TOTAL KESELURUHAN:</td>
                <td style="font-weight: 800; color: #1d4ed8; background-color: #f1f5f9; border-top: 2px solid #cbd5e1;">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</td>
                <td style="font-weight: 800; color: #059669; background-color: #f1f5f9; border-top: 2px solid #cbd5e1;">Rp {{ number_format($totalDP, 0, ',', '.') }}</td>
                <td style="background-color: #f1f5f9; border-top: 2px solid #cbd5e1;"></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        Dokumen ini dibuat otomatis oleh Sistem Manajemen PinjemDong &copy; {{ date('Y') }}
    </div>

</body>
</html>
