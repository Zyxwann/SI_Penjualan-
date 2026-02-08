<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Filament\Resources\CategoryResource\RelationManagers;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\actions\ActionGroup;
use Illuminate\Support\Facades\Auth;

class CategoryResource extends Resource
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

    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // input nama kategori dengan validasi nama kategori tidak sama dengan nama kategori yang sudah ada
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->validationMessages([
                        'unique' => 'Nama Categori sudah ada',
                    ]),
                // input icon
                Forms\Components\FileUpload::make('icon')
                    ->image()
                    ->disk('public')
                    ->directory('categories')
                    ->maxSize(1024)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\ImageColumn::make('icon')->disk('public')->circular(),
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
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
