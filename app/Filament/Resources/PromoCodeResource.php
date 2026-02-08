<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PromoCodeResource\Pages;
use App\Filament\Resources\PromoCodeResource\RelationManagers;
use App\Models\PromoCode;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\actions\ActionGroup;
use Illuminate\Support\Facades\Auth;



class PromoCodeResource extends Resource
{
    protected static ?string $model = PromoCode::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // input code dengan validasi code tidak sama dengan code yang sudah ada
                Forms\Components\TextInput::make('code')
                    ->label('Code')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->validationMessages([
                        'unique' => 'Promo Code sudah ada',
                    ]),
                // input nilai discount
                Forms\Components\TextInput::make('discount_amount')
                    ->label('Discount Amount')
                    ->numeric()
                    ->prefix('IDR')
                    ->required()
                    ->minValue(0)
                    ->step(1000),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //kolom code di table
                Tables\Columns\TextColumn::make('code')->searchable()->label('Promo Code')->sortable(),
                //kolom discount di table
                Tables\Columns\TextColumn::make('discount_amount')->searchable()->label('Discount Amount')->sortable()->money('IDR'),
                //kolom created at
                Tables\Columns\TextColumn::make('created_at')->dateTime()
                    ->label('Created at')->sortable()
                    ->dateTime('d M Y H:i:s')->sortable()
                    ->Timezone('Asia/Jakarta')
                    ->sortable(),

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
            'index' => Pages\ListPromoCodes::route('/'),
            'create' => Pages\CreatePromoCode::route('/create'),
            'edit' => Pages\EditPromoCode::route('/{record}/edit'),
        ];
    }
}
