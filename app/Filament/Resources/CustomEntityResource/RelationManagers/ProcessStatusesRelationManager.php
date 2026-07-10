<?php

namespace App\Filament\Resources\CustomEntityResource\RelationManagers;

use App\Enums\ProcessStatusColorEnum;
use App\Models\ProcessStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ProcessStatusesRelationManager extends RelationManager
{
    protected static string $relationship = 'processStatuses';

    protected static ?string $inverseRelationship = 'entities';

    protected static ?string $title = 'Situações do Processo';

    protected static ?string $modelLabel = 'Situação';

    protected static ?string $pluralModelLabel = 'Situações';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('process_status_id')
                    ->label('Situação')
                    ->options(ProcessStatus::all()->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->placeholder('Selecione uma situação já cadastrada...'),

                Forms\Components\TextInput::make('display_order')
                    ->label('Ordem de Exibição')
                    ->numeric()
                    ->default(0)
                    ->required()
                    ->helperText('Menor número aparece primeiro'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Situação')
                    ->badge()
                    ->color(fn ($record) => ProcessStatusColorEnum::tryFrom($record->color)?->filamentColor() ?? 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('pivot.display_order')
                    ->label('Ordem')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('entity_process_status.display_order')
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $query->orderBy('name'))
                    ->form(fn (Tables\Actions\AttachAction $action) => [
                        $action->getRecordSelect()
                            ->label('Situação')
                            ->placeholder('Selecione uma situação já cadastrada...'),
                        Forms\Components\TextInput::make('display_order')
                            ->label('Ordem de Exibição')
                            ->numeric()
                            ->default(0)
                            ->required(),
                    ]),
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
