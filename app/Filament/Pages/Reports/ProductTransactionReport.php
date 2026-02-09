<?php

namespace App\Filament\Pages\Reports;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\ProductTransaction;

class ProductTransactionReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Report Transaksi';
    protected static ?string $navigationGroup = 'Laporan';

    protected static string $view = 'filament.pages.reports.product-transaction-report';

    // LANGSUNG property, tanpa array
    public ?string $start_date = null;
    public ?string $end_date   = null;

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('start_date')
                ->label('Dari Tanggal')
                ->required(),

            Forms\Components\DatePicker::make('end_date')
                ->label('Sampai Tanggal')
                ->required()
                ->afterOrEqual('start_date'),
        ]);
    }

    public function generate()
    {
        $transactions = ProductTransaction::with('produk')
            ->whereBetween('created_at', [
                $this->start_date,
                $this->end_date,
            ])
            ->get();

        if ($transactions->isEmpty()) {
            Notification::make()
                ->title('Tidak ada data')
                ->warning()
                ->send();
            return;
        }

        $pdf = Pdf::loadView('pdf/monthly-report', [
            'transactions' => $transactions,
            'start_date'   => $this->start_date,
            'end_date'     => $this->end_date,
        ]);

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'report-transaksi.pdf'
        );
    }
}
