<?php

namespace App\Filament\Widgets;

use App\Enums\DemandPriorityEnum;
use App\Enums\DemandStatusEnum;
use App\Enums\ProcessStatusColorEnum;
use App\Filament\Resources\DemandResource;
use App\Models\Demand;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class MyDemandsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Minhas Demandas';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Demand::query()
                    ->with(['entity', 'processStatus', 'assignee', 'requester'])
                    ->where('status', DemandStatusEnum::ACTIVE->value)
                    ->pendingForUser(auth()->user())
                    ->orderByRaw('CASE WHEN sla_due_at IS NULL THEN 1 ELSE 0 END ASC')
                    ->orderBy('sla_due_at', 'asc')
                    ->orderBy('created_at', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->limit(60)
                    ->url(fn (Demand $record) => DemandResource::getUrl('edit', ['record' => $record])),

                Tables\Columns\TextColumn::make('entity.name')
                    ->label('Processo')
                    ->badge()
                    ->color('gray'),

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

                Tables\Columns\TextColumn::make('current_responsibles')
                    ->label('Responsável')
                    ->limit(40)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('sla_due_at')
                    ->label('Prazo SLA')
                    ->dateTime('d/m/Y')
                    ->color(fn (Demand $record) => $record->sla_due_at?->isPast() ? 'danger' : null)
                    ->icon(fn (Demand $record) => $record->sla_due_at?->isPast() ? 'heroicon-o-exclamation-triangle' : null)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('entity_id')
                    ->label('Processo')
                    ->relationship('entity', 'name'),

                Tables\Filters\SelectFilter::make('priority')
                    ->label('Prioridade')
                    ->options(DemandPriorityEnum::options()),
            ])
            ->emptyStateHeading('Nenhuma demanda pendente')
            ->emptyStateDescription('Você não tem demandas ativas atribuídas a você.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([10, 25, 50]);
    }
}
