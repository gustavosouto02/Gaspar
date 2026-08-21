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

    public ?array $data = [];

    #[\Livewire\Attributes\Url]
    public ?string $saved_report_id = null;

    public function mount(): void
    {
        if ($this->saved_report_id) {
            $report = \App\Models\SavedReport::where('user_id', auth()->id())
                ->find($this->saved_report_id);
            if ($report && is_array($report->filters_json)) {
                $this->tableFilters = $report->filters_json;
            }
        }
    }

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    
    protected static ?string $navigationLabel = 'Relatório de Demandas';
    protected static ?string $title = 'Relatório de Demandas';
    protected static ?string $navigationGroup = 'Relatórios';
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.demand-reports';

    private function getTableColumns(): array
    {
        $columns = [
            Tables\Columns\TextColumn::make('id')
                ->label('Demanda')
                ->formatStateUsing(fn ($state) => '#' . strtoupper(substr($state, 0, 8)))
                ->searchable()
                ->sortable()
                ->toggleable(),

            Tables\Columns\TextColumn::make('title')
                ->label('Título')
                ->searchable()
                ->sortable()
                ->limit(50)
                ->toggleable(),

            Tables\Columns\TextColumn::make('processStatus.name')
                ->label('Situação')
                ->badge()
                ->color(fn ($record) => $record->processStatus
                    ? ProcessStatusColorEnum::tryFrom($record->processStatus->color)?->filamentColor() ?? 'gray'
                    : 'gray')
                ->placeholder('—')
                ->toggleable(),

            Tables\Columns\TextColumn::make('priority')
                ->label('Prioridade')
                ->badge()
                ->formatStateUsing(fn ($state) => $state instanceof DemandPriorityEnum ? $state->label() : $state)
                ->color(fn ($state) => $state instanceof DemandPriorityEnum ? $state->filamentColor() : 'gray')
                ->sortable()
                ->toggleable(),

            Tables\Columns\TextColumn::make('status')
                ->label('Status')
                ->badge()
                ->formatStateUsing(fn ($state) => $state instanceof DemandStatusEnum ? $state->label() : $state)
                ->color(fn ($state) => $state instanceof DemandStatusEnum ? $state->filamentColor() : 'gray')
                ->sortable()
                ->toggleable(),

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
                ->toggleable(),
        ];

        // Se houver um processo selecionado no filtro, adiciona as colunas dele
        $entityId = $this->tableFilters['entity_id']['value'] ?? null;
        if (!empty($entityId)) {
            $customFields = \App\Models\CustomField::whereHas('customEntities', function ($q) use ($entityId) {
                $q->where('custom_entities.id', $entityId);
            })->get();
            
            foreach ($customFields as $field) {
                $columns[] = Tables\Columns\TextColumn::make('field_' . $field->id)
                    ->label($field->name)
                    ->getStateUsing(function (Demand $record) use ($field) {
                        $values = $record->demand_field_values ?? [];
                        return $values[$field->key] ?? '—';
                    })
                    ->toggleable(isToggledHiddenByDefault: true); // Campos customizados vêm ocultos por padrão
            }
        }

        return $columns;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Demand::query()
                ->when(
                    !empty($this->tableFilters['entity_id']['value']),
                    fn ($q) => $q->where('entity_id', $this->tableFilters['entity_id']['value'])
                )
            )
            ->columns($this->getTableColumns())
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('save_filter')
                    ->label('Salvar Consulta')
                    ->icon('heroicon-o-bookmark')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome da Consulta')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        \App\Models\SavedReport::create([
                            'user_id' => auth()->id(),
                            'name' => $data['name'],
                            'filters_json' => $this->tableFilters ?? [],
                        ]);
                        \Filament\Notifications\Notification::make()->title('Consulta salva com sucesso!')->success()->send();
                    }),
                Tables\Actions\Action::make('export_csv')
                    ->label('Exportar CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function ($livewire) {
                        $csv = fopen('php://temp', 'w');
                        // UTF-8 BOM para o Excel abrir corretamente
                        fputs($csv, $bom =(chr(0xEF) . chr(0xBB) . chr(0xBF)));
                        
                        fputcsv($csv, ['ID', 'Título', 'Situação', 'Status', 'Prioridade', 'Responsável', 'Prazo', 'Aberta Em'], ';');

                        $livewire->getFilteredTableQuery()->each(function ($demand) use ($csv) {
                            fputcsv($csv, [
                                '#' . strtoupper(substr($demand->id, 0, 8)),
                                $demand->title,
                                $demand->processStatus?->name ?? '—',
                                $demand->status instanceof \App\Enums\DemandStatusEnum ? $demand->status->label() : $demand->status,
                                $demand->priority instanceof \App\Enums\DemandPriorityEnum ? $demand->priority->label() : $demand->priority,
                                $demand->current_responsibles ?? '—',
                                $demand->sla_due_at ? $demand->sla_due_at->format('d/m/Y') : '—',
                                $demand->created_at ? $demand->created_at->format('d/m/Y H:i') : '—',
                            ], ';');
                        });

                        rewind($csv);
                        $content = stream_get_contents($csv);
                        fclose($csv);

                        return response()->streamDownload(function () use ($content) {
                            echo $content;
                        }, 'relatorio_demandas.csv');
                    })
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('entity_id')
                    ->label('Processo')
                    ->options(\App\Models\CustomEntity::pluck('name', 'id'))
                    ->searchable()
                    ->placeholder('Todos'),

                Tables\Filters\SelectFilter::make('process_status_id')
                    ->label('Situação')
                    ->relationship('processStatus', 'name')
                    ->multiple()
                    ->placeholder('Todos'),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(DemandStatusEnum::options())
                    ->multiple()
                    ->placeholder('Todos'),

                Tables\Filters\SelectFilter::make('priority')
                    ->label('Prioridade')
                    ->options(DemandPriorityEnum::options())
                    ->multiple()
                    ->placeholder('Todos'),

                Tables\Filters\SelectFilter::make('client_id')
                    ->label('Cliente')
                    ->relationship('client', 'name')
                    ->multiple()
                    ->placeholder('Todos'),

                Tables\Filters\SelectFilter::make('project_id')
                    ->label('Projeto')
                    ->relationship('project', 'name')
                    ->multiple()
                    ->placeholder('Todos'),

                Tables\Filters\SelectFilter::make('requested_by')
                    ->label('Demandante')
                    ->relationship('requester', 'name')
                    ->multiple()
                    ->placeholder('Todos'),

                Tables\Filters\SelectFilter::make('assigned_to')
                    ->label('Responsável')
                    ->relationship('assignee', 'name')
                    ->multiple()
                    ->placeholder('Todos'),

                Tables\Filters\Filter::make('periodo')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('from')->label('De'),
                                Forms\Components\DatePicker::make('until')->label('Até'),
                            ]),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
                            ->when($data['until'], fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
                    }),
            ], layout: Tables\Enums\FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Abrir')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Demand $record) => \App\Filament\Resources\DemandResource::getUrl('view', ['record' => $record])),
                Tables\Actions\Action::make('edit')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->url(fn (Demand $record) => \App\Filament\Resources\DemandResource::getUrl('edit', ['record' => $record]))
                    ->visible(fn (Demand $record) => 
                        auth()->user()->user_role === \App\Enums\UserRoleEnum::ADMIN
                        || $record->created_by === auth()->id()
                        || $record->requested_by === auth()->id()
                        || $record->assigned_to === auth()->id()
                        || $record->canBeTransitionedBy(auth()->user())
                    ),
            ])
            ->bulkActions([
                // Vazio
            ]);
    }
}
