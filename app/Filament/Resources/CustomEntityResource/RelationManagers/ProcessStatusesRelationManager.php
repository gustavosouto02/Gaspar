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

                Tables\Columns\IconColumn::make('is_system')
                    ->label('Padrão')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('danger')
                    ->falseColor('gray'),
            ])
            ->defaultSort('entity_process_status.display_order')
            ->reorderable('display_order')
            ->checkIfRecordIsSelectableUsing(fn ($record) => !$record->is_system)
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $query->orderBy('name'))
                    ->form(fn (Tables\Actions\AttachAction $action) => [
                        $action->getRecordSelect()
                            ->label('Situação')
                            ->placeholder('Selecione uma situação já cadastrada...'),
                    ]),
            ])
            ->actions([
                Tables\Actions\DetachAction::make()
                    ->hidden(fn ($record) => $record->is_system),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
