<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductTransactionResource\Pages;
use App\Models\ProductTransaction;
use App\Models\Produk;
use App\Models\PromoCode;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;

use Filament\Tables\Actions\ActionGroup;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;




class ProductTransactionResource extends Resource
{

    public static function canCreate(): bool
    {
        return in_array(Auth::user()->role, ['super-admin', 'kasir']);
    }

    public static function canEdit($record): bool
    {
        return in_array(Auth::user()->role, ['super-admin', 'kasir']);
    }

    public static function canDelete($record): bool
    {
        return in_array(Auth::user()->role, ['super-admin', 'kasir']);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withTrashed(); // ⬅️ INI KUNCI UTAMANYA
    }
    protected static ?string $model = ProductTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'Product Transactions';

    /* =========================
        HELPER HITUNG TOTAL
    ========================== */
    private static function calculateGrandTotal(callable $get): int
    {
        $price = (int) $get('sub_total_amount');
        $qty   = max((int) $get('quantity'), 1);
        $total = $price * $qty;

        $promoId = $get('promo_code_id');

        if ($promoId) {
            $promo = PromoCode::find($promoId);

            if ($promo) {
                if ($promo->discount_percent) {
                    $total -= ($total * $promo->discount_percent / 100);
                }

                if ($promo->discount_amount) {
                    $total -= $promo->discount_amount;
                }
            }
        }

        return max((int) $total, 0);
    }

    /* =========================
        FORM
    ========================== */
    public static function form(Form $form): Form
    {
        return $form->schema([

            /* =====================
            CUSTOMER INFO
        ====================== */
            Section::make('Customer Information')
                ->schema([
                    // input nama
                    TextInput::make('name')
                        ->required()
                        ->extraInputAttributes([
                            'oninput' => "this.value = this.value.replace(/[0-9]/g, '')"
                        ]),
                    // input nomor telepon
                    TextInput::make('phone')
                        ->label('Nomor Telepon')
                        ->required()
                        ->tel()
                        ->regex('/^08[0-9]{9,13}$/')
                        ->extraInputAttributes([
                            'inputmode' => 'numeric',
                            'pattern' => '[0-9]*',
                            'oninput' => "this.value = this.value.replace(/[^0-9]/g, '')",
                        ])
                        ->validationMessages([
                            'regex' => 'Nomor telepon harus diawali 08 dan hanya angka.',
                        ]),
                    // input email
                    TextInput::make('email')
                        ->email()
                        ->required(),
                ])
                ->columns(3)
                ->collapsible(),

            /* =====================
            ADDRESS
        ====================== */
            Section::make('Address')
                ->schema([
                    // input kota
                    TextInput::make('city')->required(),
                    // input kode pos hanya dengan angka
                    TextInput::make('post_code')->numeric()->required(),
                    // input alamat
                    Forms\Components\Textarea::make('address')->required(),
                ])
                ->columns(2)
                ->collapsible(),

            /* =====================
            PRODUCT
        ====================== */
            Section::make('Product')
                ->schema([
                    // input produk dengan menggunakan select yang sudah diambil di tabel produk
                    Select::make('produk_id')
                        ->label('Product')
                        ->options(Produk::pluck('name', 'id'))
                        ->searchable()
                        ->reactive()
                        // Field akan non-aktif (tidak bisa diubah) jika transaksi sudah dibayar
                        ->disabled(fn($record) => $record?->is_paid === 1)
                        // $state berisi value terbaru dari field (produk_id yang dipilih)
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            // Ambil data produk berdasarkan ID yang dipilih
                            $produk = Produk::find($state);
                            // Set harga produk ke field sub_total_amount
                            // Jika produk tidak ditemukan, default ke 0
                            $set('sub_total_amount', $produk?->price ?? 0);
                            // Set quantity default ke 1 setiap kali produk berubah
                            $set('quantity', 1);
                            // Hitung ulang grand total berdasarkan harga produk, quantity dan promo kalo misalnya ada
                            $set('grand_total_amount', self::calculateGrandTotal($get));
                        })
                        ->required(),
                    // input size
                    Select::make('shoe_size')
                        ->label('Size')
                        // input size hanya bisa diisi ketika produk sudah dipilih
                        ->options(function (callable $get) {
                            $produkId = $get('produk_id');

                            if (! $produkId) {
                                return [];
                            }

                            $produk = Produk::with('sizes')->find($produkId);

                            return $produk
                                ? $produk->sizes
                                ->pluck('size', 'size')
                                ->map(fn($v) => 'Size ' . $v)
                                ->toArray()
                                : [];
                        })
                        //field akan non-aktif (tidak bisa diubah) jika transaksi sudah dibayar
                        ->disabled(
                            fn($record, callable $get) =>
                            $record?->is_paid === 1 || ! $get('produk_id')
                        )
                        ->required(),

                    // input quantity
                    TextInput::make('quantity')
                        ->numeric()
                        ->reactive()
                        ->required()
                        ->minValue(1)
                        // Field akan non-aktif (tidak bisa diubah) jika transaksi sudah dibayar
                        ->disabled(fn($record) => $record?->is_paid === 1)
                        // quantity akan menyesuaikan dengan stok produk dan jika stok tidak mencukupi akan muncul pesan error
                        ->rules([
                            function (callable $get) {
                                return function ($attribute, $value, $fail) use ($get) {
                                    $produk = Produk::find($get('produk_id'));

                                    if ($produk && $value > $produk->stock) {
                                        $fail('Stok tidak mencukupi');
                                    }
                                };
                            },
                        ])
                        // hitung ulang grand total berdasarkan harga produk, quantity dan promo kalo misalnya ada
                        ->afterStateUpdated(
                            fn($state, callable $set, callable $get) =>
                            $set('grand_total_amount', self::calculateGrandTotal($get))
                        ),
                    //input promo
                    Select::make('promo_code_id')
                        ->label('Promo Code')
                        ->options(PromoCode::pluck('code', 'id'))
                        ->searchable()
                        ->nullable()
                        ->reactive()
                        // Field akan non-aktif (tidak bisa diubah) jika transaksi sudah dibayar
                        ->disabled(fn($record) => $record?->is_paid === 1)
                        // grand total akan menyesuaikan dengan promo kalo misalnya ada
                        ->afterStateUpdated(
                            fn($state, callable $set, callable $get) =>
                            $set('grand_total_amount', self::calculateGrandTotal($get))
                        ),
                ])
                ->columns(2)
                ->collapsible(),

            /* =====================
            PAYMENT
        ====================== */
            Section::make('Payment')
                ->schema([
                    // otomatis input booking_trx_id
                    TextInput::make('booking_trx_id')
                        ->label('Booking ID')
                        ->default(fn() => ProductTransaction::generateUniqueTrxId())
                        ->disabled()
                        ->dehydrated(),
                    // otomatis input sub_total_amount sesuai harga dari produk nya
                    TextInput::make('sub_total_amount')
                        ->label('Sub Total')
                        ->prefix('Rp')
                        ->disabled()
                        ->dehydrated(),
                    // otomatis input grand_total_amount sesuai harga dari produk dan quantity
                    TextInput::make('grand_total_amount')
                        ->label('Grand Total')
                        ->prefix('Rp')
                        ->disabled()
                        ->dehydrated(),
                    // input is_paid untuk menandai bahwa transaksi sudah dibayar atau belum
                    Select::make('is_paid')
                        ->options([
                            1 => 'Sudah Bayar',
                            0 => 'Belum Bayar',
                        ])
                        ->reactive()
                        // Field akan non-aktif (tidak bisa diubah) jika transaksi sudah dibayar
                        ->disabled(fn($record) => $record?->is_paid === 1)
                        ->required()
                        // jika transaksi belum dibayar, maka field proof nilai nya menjadi null
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state === 0) {
                                $set('proof', null);
                            }
                        }),
                    // input proof
                    Forms\Components\FileUpload::make('proof')
                        ->directory('proofs')
                        ->image()
                        ->nullable()
                        // proof akan muncul jika kondisi nya sudah dibayar
                        ->visible(fn(callable $get) => $get('is_paid') == 1)
                        // Proof menjadi wajib diisi jika kondisi nya sudah dibayar
                        ->required(fn(callable $get) => $get('is_paid') == 1)
                        // Field akan non-aktif (tidak bisa diubah) jika transaksi sudah dibayar
                        ->disabled(fn($record) => $record?->is_paid === 1),
                ])
                ->columns(2)
                ->collapsible(),
        ]);
    }



    /* =========================
        TABLE
    ========================== */
    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('booking_trx_id')->label('TRX ID')->sortable(),
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('produk.name')->label('Product'),
            Tables\Columns\TextColumn::make('quantity'),
            Tables\Columns\TextColumn::make('grand_total_amount')->money('IDR'),
            Tables\Columns\IconColumn::make('is_paid')->boolean(),
            Tables\Columns\TextColumn::make('created_at')->dateTime(),
        ])->headerActions([
            Tables\Actions\Action::make('monthly_report')
                ->label('Generate Laporan Bulanan')
                ->visible(fn()=> Auth::User()->role === 'super-admin')
                ->icon('heroicon-o-document-text')
                ->form([
                    Forms\Components\DatePicker::make('start_date')
                        ->label('Dari Tanggal')
                        ->required(),

                    Forms\Components\DatePicker::make('end_date')
                        ->label('Sampai Tanggal')
                        ->required()
                        ->afterOrEqual('start_date'),
                ])

                ->action(function (array $data) {

                    $transactions = ProductTransaction::with('produk')
                        ->whereBetween('created_at', [
                            $data['start_date'],
                            $data['end_date'],
                        ])
                        ->get();

                    // ⛔ JIKA DATA KOSONG
                    if ($transactions->isEmpty()) {
                        Notification::make()
                            ->title('Tidak ada data')
                            ->body('Tidak ada transaksi pada rentang tanggal yang dipilih.')
                            ->warning()
                            ->send();

                        return; // ⛔ STOP, PDF tidak dibuat
                    }

                    $totalPaid   = $transactions->where('is_paid', 1)->sum('grand_total_amount');
                    $totalUnpaid = $transactions->where('is_paid', 0)->sum('grand_total_amount');

                    $pdf = Pdf::loadView(
                        'pdf.monthly-report',
                        compact('transactions', 'totalPaid', 'totalUnpaid', 'data')
                    );


                    return response()->streamDownload(
                        fn() => print($pdf->output()),
                        'laporan-transaksi.pdf'
                    );
                }),
        ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\Action::make('print_invoice')
                        ->label('Invoice PDF')
                        ->icon('heroicon-o-printer')
                        ->color('gray')
                        ->action(function (ProductTransaction $record) {
                            $pdf = Pdf::loadView('pdf.transaction-detail', [
                                'trx' => $record->load('produk'),
                            ]);

                            return response()->streamDownload(
                                fn() => print($pdf->output()),
                                'invoice-' . $record->booking_trx_id . '.pdf'
                            );
                        }),
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    // tombol untuk menandai bahwa transaksi sudah dibayar
                    Action::make('markAsPaid')
                        ->label('Bayar')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        // tombol ini hanya muncul jika transaksi belum dibayar
                        ->visible(fn($record) => $record->is_paid == 0)
                        ->requiresConfirmation()
                        ->modalHeading('Konfirmasi Pembayaran')
                        ->modalDescription('Upload bukti pembayaran untuk menandai transaksi sebagai sudah dibayar.')
                        ->modalSubmitActionLabel('Ya, Bayar')
                        ->modalCancelActionLabel('Batal')
                        // form untuk upload bukti pembayaran
                        ->form([
                            FileUpload::make('proof')
                                ->label('Bukti Pembayaran')
                                ->directory('proofs')
                                // proof menjadi wajib diisi untuk mengkonfirm pembayaran
                                ->required()
                                ->image()
                                ->maxSize(2048),

                        ])
                        // aksi untuk menandai bahwa transaksi sudah dibayar
                        ->action(function ($record, array $data) {
                            $record->update([
                                'is_paid' => 1,
                                'proof' => $data['proof'],
                            ]);
                        }),
                    // tombol untuk mengunduh bukti pembayaran
                    Tables\Actions\Action::make('download_proof')
                        ->label('Download Proof')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->url(
                            fn(ProductTransaction $record) =>
                            $record->proof
                                ? asset('storage/' . $record->proof)
                                : null
                        )
                        ->openUrlInNewTab()
                        // tombol ini hanya muncul jika proof tidak kosong
                        ->visible(fn(ProductTransaction $record) => ! empty($record->proof)),
                    // tombol untuk menghapus transaksi
                    Tables\Actions\DeleteAction::make()
                        // kondisi jika transaksi belum dibayar, maka stock produk akan kembali
                        ->before(function (ProductTransaction $record) {
                            if ($record->is_paid == 0) {
                                $produk = Produk::find($record->produk_id);

                                if ($produk) {
                                    $produk->increment('stock', $record->quantity);
                                }
                            }
                        }),
                ])->icon('heroicon-m-ellipsis-horizontal')

            ])->filters([
                Tables\Filters\TrashedFilter::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProductTransactions::route('/'),
            'create' => Pages\CreateProductTransaction::route('/create'),
            'edit'   => Pages\EditProductTransaction::route('/{record}/edit'),
        ];
    }
}
