<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BrandResource\Pages;
use App\Filament\Resources\BrandResource\RelationManagers;
use App\Models\Brand;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\actions\ActionGroup;
use Illuminate\Support\Facades\Auth;

class BrandResource extends Resource
{
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

    protected static ?string $model = Brand::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // input nama brand dengan validasi nama brand tidak sama dengan nama brand yang sudah ada
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->validationMessages([
                        'unique' => 'Nama Brand sudah ada',
                    ]),
                // input logo
                Forms\Components\FileUpload::make('logo')
                    ->image()
                    ->disk('public')
                    ->directory('brands')
                    ->maxSize(1024)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\ImageColumn::make('logo')->disk('public')->circular(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])->icon('heroicon-m-ellipsis-horizontal')

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBrands::route('/'),
            'create' => Pages\CreateBrand::route('/create'),
            'edit' => Pages\EditBrand::route('/{record}/edit'),
        ];
    }
}
