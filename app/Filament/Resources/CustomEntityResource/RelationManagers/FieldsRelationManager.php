<?php

namespace App\Filament\Resources\CustomEntityResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'fields';

    protected static ?string $title = 'Campos Customizados';

    protected static ?string $modelLabel = 'Campo';

    protected static ?string $pluralModelLabel = 'Campos';

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        // Na tela de visualização o Filament exibe a aba somente leitura (sem vincular/desvincular)
        return auth()->user()?->user_role === \App\Enums\UserRoleEnum::ADMIN;
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Número')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('key')
                    ->searchable()
                    ->sortable()
                    ->label('Chave (slug)'),

                Tables\Columns\TextColumn::make('field_type')
                    ->badge()
                    ->label('Tipo'),

                Tables\Columns\IconColumn::make('is_required')
                    ->boolean()
                    ->sortable()
                    ->label('Obrigatório'),

            ])
            ->defaultSort('field_order')
            ->reorderable('field_order', fn (): bool => ! $this->isReadOnly())
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('Vincular Campo')
                    ->color('primary')
                    ->modalHeading('Vincular Campo')
                    ->modalSubmitActionLabel('Vincular')
                    ->preloadRecordSelect(),
            ])
            ->actions([
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
