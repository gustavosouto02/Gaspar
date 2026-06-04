<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomEntityResource\Pages;
use App\Filament\Resources\CustomEntityResource\RelationManagers;
use App\Models\CustomEntity;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomEntityResource extends Resource
{
    protected static ?string $model = CustomEntity::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Cadastros Adicionais';

    protected static ?string $modelLabel = 'Cadastro Adicional';

    protected static ?string $pluralModelLabel = 'Cadastros Adicionais';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome do Cadastro')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Toggle::make('is_active')
                    ->label('Ativo')
                    ->required()
                    ->default(true),

                Forms\Components\Textarea::make('description')
                    ->label('Descrição')
                    ->maxLength(65535)
                    ->columnSpanFull(),

                Forms\Components\Hidden::make('created_by')
                    ->default(fn () => auth()->id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome do Cadastro')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição')
                    ->limit(50)
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Criado por')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            RelationManagers\FieldsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomEntities::route('/'),
            'create' => Pages\CreateCustomEntity::route('/create'),
            'view' => Pages\ViewCustomEntity::route('/{record}'),
            'edit' => Pages\EditCustomEntity::route('/{record}/edit'),
        ];
    }
}
