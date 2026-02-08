<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice Transaksi</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #000; padding: 6px; }
        th { background: #eee; }
    </style>
</head>
<body>

<h3>Detail Transaksi</h3>

<p>
    <strong>Booking ID:</strong> {{ $trx->booking_trx_id }} <br>
    <strong>Nama:</strong> {{ $trx->name }} <br>
    <strong>Email:</strong> {{ $trx->email }} <br>
    <strong>No Telp:</strong> {{ $trx->phone }}
</p>

<table>
    <tr>
        <th>Produk</th>
        <th>Size</th>
        <th>Qty</th>
        <th>Harga</th>
        <th>Total</th>
    </tr>
    <tr>
        <td>{{ $trx->produk->name }}</td>
        <td>{{ $trx->shoe_size }}</td>
        <td>{{ $trx->quantity }}</td>
        <td>Rp {{ number_format($trx->sub_total_amount) }}</td>
        <td>Rp {{ number_format($trx->grand_total_amount) }}</td>
    </tr>
</table>

<p>
    <strong>Status:</strong>
    {{ $trx->is_paid ? 'Sudah Bayar' : 'Belum Bayar' }}
</p>

</body>
</html>
