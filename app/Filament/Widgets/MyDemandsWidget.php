<?php

namespace App\Filament\Widgets;

use App\Enums\DemandPriorityEnum;
use App\Enums\DemandStatusEnum;
use App\Enums\ProcessStatusColorEnum;
use App\Filament\Resources\DemandResource;
use App\Filament\Widgets\Concerns\HasKanbanView;
use App\Models\Demand;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class MyDemandsWidget extends BaseWidget
{
    use HasKanbanView;

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Minhas Demandas';

    public function table(Table $table): Table
    {
        return $this->applyKanbanView($table)
            ->query(
                Demand::query()
                    ->with(['entity', 'processStatus', 'assignee', 'requester'])
                    ->whereNull('parent_demand_id')
                    ->pendingActionForUser(auth()->user())
                    ->orderByRaw('CASE WHEN satisfaction_rating IS NULL AND (status = \'' . DemandStatusEnum::CLOSED->value . '\' OR status = \'closed\') THEN 0 ELSE 1 END ASC')
                    ->orderByRaw('CASE WHEN sla_due_at IS NULL THEN 1 ELSE 0 END ASC')
                    ->orderBy('sla_due_at', 'asc')
                    ->orderBy('created_at', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable(query: fn ($query, string $search) => $query->globalSearch($search))
                    ->limit(60)
                    ->description(fn (Demand $record) => ($record->requested_by === auth()->id() && empty($record->satisfaction_rating) && ($record->status === DemandStatusEnum::CLOSED || $record->processStatus?->isClosed()))
                        ? '⭐ Aguardando sua avaliação de atendimento'
                        : null)
                    ->url(fn (Demand $record) => auth()->user()->can('update', $record)
                        ? DemandResource::getUrl('edit', ['record' => $record])
                        : DemandResource::getUrl('view', ['record' => $record])),

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
            ->actions([
                Tables\Actions\Action::make('rate_demand')
                    ->label('Avaliar')
                    ->icon('heroicon-m-star')
                    ->color('warning')
                    ->visible(fn (Demand $record) => $record->requested_by === auth()->id() && empty($record->satisfaction_rating) && ($record->status === DemandStatusEnum::CLOSED || $record->processStatus?->isClosed()))
                    ->form([
                        \Filament\Forms\Components\Select::make('satisfaction_rating')
                            ->label('Nota de Avaliação')
                            ->options(\App\Enums\SatisfactionRatingEnum::options())
                            ->required(),
                        \Filament\Forms\Components\Textarea::make('satisfaction_comment')
                            ->label('Comentário')
                            ->rows(3),
                    ])
                    ->action(function (Demand $record, array $data) {
                        $avaliadaStatus = \App\Models\ProcessStatus::findBySystemKey('evaluated');

                        $record->update([
                            'satisfaction_rating' => $data['satisfaction_rating'],
                            'satisfaction_comment' => $data['satisfaction_comment'],
                            'satisfaction_evaluated_at' => now(),
                            'process_status_id' => $avaliadaStatus ? $avaliadaStatus->id : $record->process_status_id,
                            'status' => \App\Enums\DemandStatusEnum::EVALUATED,
                        ]);
                        \Filament\Notifications\Notification::make()->title('Avaliação enviada com sucesso!')->success()->send();
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('entity_id')
                    ->label('Processo')
                    ->options(fn () => \App\Models\CustomEntity::with('macroprocess')->get()->pluck('full_display_name', 'id')),

                Tables\Filters\SelectFilter::make('priority')
                    ->label('Prioridade')
                    ->options(DemandPriorityEnum::options()),
            ])
            ->emptyStateHeading('Nenhuma demanda pendente')
            ->emptyStateDescription('Você não tem demandas ativas ou pendências atribuídas a você.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->recordUrl(fn (Demand $record): string => \App\Filament\Resources\DemandResource::getUrl('view', ['record' => $record]));
    }
}
