<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProdukResource\Pages;
use App\Filament\Resources\ProdukResource\RelationManagers;
use App\Models\Produk;
use Dom\Text;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\actions\ActionGroup;
use function Laravel\Prompts\select;
use Illuminate\Support\Facades\Auth;

class ProdukResource extends Resource
{
    protected static ?string $model = Produk::class;

    // Icon navigasi di sidebar Filament
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    /**
     * Konfigurasi Form (Create & Edit)
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Fieldset utama informasi produk
                Forms\Components\Fieldset::make('Product Information')
                    ->schema([
                        // Grid 2 kolom (kiri & kanan)
                        Forms\Components\Grid::make(2)
                            ->schema([
                                // =====================
                                // KIRI
                                // =====================
                                Forms\Components\Group::make()
                                    ->schema([
                                        // Input nama produk
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->maxLength(255)
                                            ->unique(ignoreRecord: true)
                                            ->validationMessages([
                                                'unique' => 'Nama Produk sudah ada',
                                            ]),

                                        // Upload thumbnail produk
                                        Forms\Components\FileUpload::make('thumnail')
                                            ->label('Thumnail')
                                            ->image()
                                            ->disk('public')
                                            ->directory('categories')
                                            ->maxSize(1024)
                                            ->required(),

                                        // Repeater untuk size produk
                                        Repeater::make('sizes')
                                            ->label('Size')
                                            ->relationship()
                                            ->schema([
                                                // Input size sepatu
                                                Forms\Components\TextInput::make('size')
                                                    ->numeric()
                                                    ->minValue(30)
                                                    ->maxValue(45)
                                                    ->required()
                                                    // Validasi custom agar size tidak duplikat
                                                    ->rules([
                                                        function (callable $get) {
                                                            return function (string $attribute, $value, callable $fail) use ($get) {
                                                                $sizes = collect($get('../../sizes'))
                                                                    ->pluck('size')
                                                                    ->filter()
                                                                    ->toArray();

                                                                if (count(array_keys($sizes, $value)) > 1) {
                                                                    $fail('Size tidak boleh sama.');
                                                                }
                                                            };
                                                        },
                                                    ]),
                                            ])
                                    ]),

                                // =====================
                                // KANAN
                                // =====================
                                Forms\Components\Group::make()
                                    ->schema([
                                        // Input harga produk
                                        Forms\Components\TextInput::make('price')
                                            ->numeric()
                                            ->prefix('Rp')
                                            ->minValue(100000)
                                            ->validationMessages(['Min' => 'Minimal Rp 100000'])
                                            ->required(),

                                        // Repeater upload foto produk
                                        Repeater::make('photos')
                                            ->relationship()
                                            ->schema([
                                                Forms\Components\FileUpload::make('photo')
                                                    ->label('Photos')
                                                    ->image()
                                                    ->disk('public')
                                                    ->directory('categories/photos')
                                                    ->maxSize(1024)
                                                    ->required(),
                                            ])
                                    ]),
                            ]),

                        // Fieldset informasi tambahan
                        Fieldset::make('Informasi Tambahan')
                            ->schema([
                                // Deskripsi produk
                                TextInput::make('about')
                                    ->required()
                                    ->label('About'),

                                // Status populer
                                Select::make('is_populer')
                                    ->required()
                                    ->label('Popular')
                                    ->options([
                                        1 => 'populer',
                                        0 => 'tidak populer',
                                    ]),

                                // Relasi kategori
                                select::make('category_id')
                                    ->label('Category')
                                    ->relationship('category', 'name')
                                    ->required(),

                                // Relasi brand
                                select::make('brand_id')
                                    ->label('Brand')
                                    ->relationship('brand', 'name')
                                    ->required(),

                                // Stok produk
                                TextInput::make('stock')
                                    ->numeric()
                                    ->prefix('pcs')
                                    ->required(),
                            ])
                    ]),
            ]);
    }
    public static function canViewAny(): bool
    {
        return in_array(Auth::user()->role, [
            'super-admin',
            'kasir',
        ]);
    }
    public static function canCreate(): bool
    {
        return Auth::user()->role === 'super-admin';
    }

    public static function canEdit($record): bool
    {
        return Auth::user()->role === 'super-admin';
    }

    public static function canDelete($record): bool
    {
        return Auth::user()->role === 'super-admin';
    }

    /**
     * Konfigurasi Table (List Data)
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Kolom gambar thumbnail
                ImageColumn::make('thumnail')
                    ->label('Thumbnail'),

                // Nama produk
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Name'),

                // Harga produk
                TextColumn::make('price')
                    ->sortable()
                    ->label('Harga')
                    ->money('IDR')
                    ->sortable(),

                // Nama kategori
                TextColumn::make('category.name')
                    ->sortable()
                    ->label('Kategory'),

                // Nama brand
                TextColumn::make('brand.name')
                    ->sortable()
                    ->label('Merk'),

                // Stok produk
                TextColumn::make('stock')
                    ->sortable()
                    ->label('Stok'),

                // Status populer (boolean icon)
                IconColumn::make('is_populer')
                    ->boolean()
                    ->label('Populer'),
            ])
            ->filters([
                // Filter data yang di-soft delete
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                // Action group (Edit & Delete)
                ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])->icon('heroicon-m-ellipsis-horizontal')
            ])
            ->bulkActions([
                // Bulk delete
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Relasi tambahan (belum digunakan)
     */
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * Routing halaman resource
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProduks::route('/'),
            'create' => Pages\CreateProduk::route('/create'),
            'edit' => Pages\EditProduk::route('/{record}/edit'),
        ];
    }
}
