<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Penjualan Bulanan</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 6px; }
        th { background: #eee; }
    </style>
</head>
<body>

<h3>Laporan Penjualan Bulan {{ $data ['start_date']}} s/d {{ $data ['end_date'] }}</h3>

<table>
    <thead>
        <tr>
            <th>TRX ID</th>
            <th>Nama</th>
            <th>Produk</th>
            <th>Qty</th>
            <th>Total</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($transactions as $trx)
        <tr>
            <td>{{ $trx->booking_trx_id }}</td>
            <td>{{ $trx->name }}</td>
            <td>{{ $trx->produk->name }}</td>
            <td>{{ $trx->quantity }}</td>
            <td>Rp {{ number_format($trx->grand_total_amount) }}</td>
            <td>{{ $trx->is_paid ? 'Paid' : 'Unpaid' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<p>
    <strong>Total Paid:</strong> Rp {{ number_format($totalPaid) }} <br>
    <strong>Total Unpaid:</strong> Rp {{ number_format($totalUnpaid) }}
</p>

</body>
</html>
