<?php

namespace App\Filament\Resources\DemandResource\RelationManagers;

use App\Enums\DemandPriorityEnum;
use App\Enums\DemandStatusEnum;
use App\Enums\ProcessStatusColorEnum;
use App\Filament\Resources\DemandResource;
use App\Models\Demand;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SubDemandsRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    protected static ?string $title = 'Subdemandas';

    protected static ?string $modelLabel = 'Subdemanda';

    protected static ?string $pluralModelLabel = 'Subdemandas';

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        // Só exibe se NÃO for uma subdemanda
        return is_null($ownerRecord->parent_demand_id);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Título')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Textarea::make('description')
                    ->label('Descrição')
                    ->nullable()
                    ->rows(3)
                    ->columnSpanFull(),

                Forms\Components\Select::make('priority')
                    ->label('Prioridade')
                    ->options(DemandPriorityEnum::options())
                    ->default(DemandPriorityEnum::MEDIUM->value)
                    ->required()
                    ->native(false),

                Forms\Components\Select::make('assigned_to')
                    ->label('Responsável')
                    ->relationship('assignee', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->limit(50)
                    ->url(fn (Demand $record) => DemandResource::getUrl('edit', ['record' => $record])),

                Tables\Columns\TextColumn::make('processStatus.name')
                    ->label('Situação')
                    ->badge()
                    ->color(fn ($record) => $record->processStatus
                        ? ProcessStatusColorEnum::tryFrom($record->processStatus->color)?->filamentColor() ?? 'gray'
                        : 'gray')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('priority')
                    ->label('Prioridade')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof DemandPriorityEnum ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof DemandPriorityEnum ? $state->filamentColor() : 'gray'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof DemandStatusEnum ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof DemandStatusEnum ? $state->filamentColor() : 'gray'),

                Tables\Columns\TextColumn::make('assignee.name')
                    ->label('Responsável')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('sla_due_at')
                    ->label('Prazo')
                    ->dateTime('d/m/Y')
                    ->color(fn (Demand $record) => $record->sla_due_at?->isPast() ? 'danger' : null)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criada em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('createSubDemand')
                    ->label('Registrar Subdemanda')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->url(function () {
                        $parent = $this->getOwnerRecord();
                        return DemandResource::getUrl('create', [
                            'parent_demand_id' => $parent->id,
                            'client_id' => $parent->client_id,
                            'project_id' => $parent->project_id,
                        ]);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->url(fn (Demand $record) => DemandResource::getUrl('edit', ['record' => $record])),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('Nenhuma subdemanda')
            ->emptyStateDescription('Clique em "Registrar Subdemanda" para criar.')
            ->emptyStateIcon('heroicon-o-document-duplicate');
    }
}
