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

class MySubDemandsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Minhas Subdemandas';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Demand::query()
                    ->with(['entity', 'processStatus', 'assignee', 'requester', 'parent'])
                    ->where('status', DemandStatusEnum::ACTIVE->value)
                    ->whereNotNull('parent_demand_id')
                    ->pendingForUser(auth()->user())
                    ->orderByRaw('CASE WHEN sla_due_at IS NULL THEN 1 ELSE 0 END ASC')
                    ->orderBy('sla_due_at', 'asc')
                    ->orderBy('created_at', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable(query: function ($query, string $search) {
                        $clean = ltrim(trim($search), '#');
                        return $query->where(function ($q) use ($search, $clean) {
                            $q->where('title', 'like', "%{$search}%");
                            if ($clean !== '') {
                                $q->orWhere('id', 'like', "{$clean}%");
                            }
                        });
                    })
                    ->limit(50)
                    ->url(fn (Demand $record) => DemandResource::getUrl('edit', ['record' => $record])),

                Tables\Columns\TextColumn::make('parent.title')
                    ->label('Demanda Pai')
                    ->limit(40)
                    ->url(fn (Demand $record) => $record->parent
                        ? DemandResource::getUrl('edit', ['record' => $record->parent])
                        : null)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('entity.name')
                    ->label('Processo')
                    ->formatStateUsing(fn ($record) => $record->entity?->full_display_name ?? '—')
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
                    ->options(fn () => \App\Models\CustomEntity::with('macroprocess')->get()->pluck('full_display_name', 'id')),

                Tables\Filters\SelectFilter::make('priority')
                    ->label('Prioridade')
                    ->options(DemandPriorityEnum::options()),
            ])
            ->emptyStateHeading('Nenhuma subdemanda pendente')
            ->emptyStateDescription('Você não tem subdemandas ativas atribuídas a você.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([10, 25, 50]);
    }
}
