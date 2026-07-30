<?php

namespace App\Filament\Pages;

use App\Enums\DemandPriorityEnum;
use App\Enums\DemandStatusEnum;
use App\Enums\ProcessStatusColorEnum;
use App\Models\Demand;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Forms;

class DemandReports extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    #[\Livewire\Attributes\Url]
    public $tableSearch = '';

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    
    protected static ?string $navigationLabel = 'Relatório de Demandas';
    protected static ?string $title = 'Relatório de Demandas';
    protected static ?string $navigationGroup = 'Relatórios';
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.demand-reports';

    public function table(Table $table): Table
    {
        return $table
            ->query(Demand::query())
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Demanda')
                    ->formatStateUsing(fn ($state) => '#' . strtoupper(substr($state, 0, 8)))
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('entity.name')
                    ->label('Processo')
                    ->searchable()
                    ->sortable(),

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
                    ->color(fn ($state) => $state instanceof DemandPriorityEnum ? $state->filamentColor() : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof DemandStatusEnum ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof DemandStatusEnum ? $state->filamentColor() : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('current_responsibles')
                    ->label('Responsável')
                    ->placeholder('—')
                    ->limit(40)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sla_due_at')
                    ->label('Prazo')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Aberta em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('entity_id')
                    ->label('Processo')
                    ->relationship('entity', 'name')
                    ->multiple(),

                Tables\Filters\SelectFilter::make('process_status_id')
                    ->label('Situação')
                    ->relationship('processStatus', 'name')
                    ->multiple(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(DemandStatusEnum::options())
                    ->multiple(),

                Tables\Filters\SelectFilter::make('priority')
                    ->label('Prioridade')
                    ->options(DemandPriorityEnum::options())
                    ->multiple(),

                Tables\Filters\SelectFilter::make('client_id')
                    ->label('Cliente')
                    ->relationship('client', 'name')
                    ->multiple(),

                Tables\Filters\SelectFilter::make('project_id')
                    ->label('Projeto')
                    ->relationship('project', 'name')
                    ->multiple(),

                Tables\Filters\SelectFilter::make('requested_by')
                    ->label('Demandante')
                    ->relationship('requester', 'name')
                    ->multiple(),

                Tables\Filters\SelectFilter::make('assigned_to')
                    ->label('Responsável')
                    ->relationship('assignee', 'name')
                    ->multiple(),

                Tables\Filters\Filter::make('periodo')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('De'),
                        Forms\Components\DatePicker::make('until')->label('Até'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
                            ->when($data['until'], fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Abrir')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Demand $record) => \App\Filament\Resources\DemandResource::getUrl('view', ['record' => $record])),
            ])
            ->bulkActions([
                // Vazio
            ]);
    }
}
