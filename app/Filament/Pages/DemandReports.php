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

    public function mount(): void
    {
        $this->form->fill([
            'selected_fixed_fields' => ['id', 'title', 'processStatus.name', 'priority', 'status', 'current_responsibles', 'sla_due_at', 'created_at'],
        ]);
    }

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    
    protected static ?string $navigationLabel = 'Relatório de Demandas';
    protected static ?string $title = 'Relatório de Demandas';
    protected static ?string $navigationGroup = 'Relatórios';
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.demand-reports';

    public function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Filtros do Relatório')
                    ->schema([
                        Forms\Components\Select::make('entity_id')
                            ->label('Processo (Selecione para exibir as demandas e campos)')
                            ->options(\App\Models\CustomEntity::pluck('name', 'id'))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set) {
                                $set('selected_custom_fields', []);
                            }),

                        Forms\Components\CheckboxList::make('selected_fixed_fields')
                            ->label('Campos Fixos')
                            ->options([
                                'id' => 'Demanda',
                                'title' => 'Título',
                                'processStatus.name' => 'Situação',
                                'priority' => 'Prioridade',
                                'status' => 'Status',
                                'current_responsibles' => 'Responsável',
                                'sla_due_at' => 'Prazo',
                                'created_at' => 'Aberta em',
                            ])
                            ->columns(4),

                        Forms\Components\CheckboxList::make('selected_custom_fields')
                            ->label('Campos Customizáveis')
                            ->options(function (Forms\Get $get) {
                                $entityId = $get('entity_id');
                                if (! $entityId) return [];
                                
                                $entity = \App\Models\CustomEntity::with('fields')->find($entityId);
                                if (! $entity) return [];

                                return $entity->fields->pluck('name', 'id')->toArray();
                            })
                            ->columns(4)
                            ->visible(fn (Forms\Get $get) => filled($get('entity_id'))),
                            
                    ]),
            ])
            ->statePath('data');
    }

    private function getTableColumns(): array
    {
        $columns = [
            Tables\Columns\TextColumn::make('id')
                ->label('Demanda')
                ->formatStateUsing(fn ($state) => '#' . strtoupper(substr($state, 0, 8)))
                ->searchable()
                ->sortable()
                ->visible(fn () => in_array('id', $this->data['selected_fixed_fields'] ?? [])),

            Tables\Columns\TextColumn::make('title')
                ->label('Título')
                ->searchable()
                ->sortable()
                ->limit(50)
                ->visible(fn () => in_array('title', $this->data['selected_fixed_fields'] ?? [])),

            Tables\Columns\TextColumn::make('processStatus.name')
                ->label('Situação')
                ->badge()
                ->color(fn ($record) => $record->processStatus
                    ? ProcessStatusColorEnum::tryFrom($record->processStatus->color)?->filamentColor() ?? 'gray'
                    : 'gray')
                ->placeholder('—')
                ->visible(fn () => in_array('processStatus.name', $this->data['selected_fixed_fields'] ?? [])),

            Tables\Columns\TextColumn::make('priority')
                ->label('Prioridade')
                ->badge()
                ->formatStateUsing(fn ($state) => $state instanceof DemandPriorityEnum ? $state->label() : $state)
                ->color(fn ($state) => $state instanceof DemandPriorityEnum ? $state->filamentColor() : 'gray')
                ->sortable()
                ->visible(fn () => in_array('priority', $this->data['selected_fixed_fields'] ?? [])),

            Tables\Columns\TextColumn::make('status')
                ->label('Status')
                ->badge()
                ->formatStateUsing(fn ($state) => $state instanceof DemandStatusEnum ? $state->label() : $state)
                ->color(fn ($state) => $state instanceof DemandStatusEnum ? $state->filamentColor() : 'gray')
                ->sortable()
                ->visible(fn () => in_array('status', $this->data['selected_fixed_fields'] ?? [])),

            Tables\Columns\TextColumn::make('current_responsibles')
                ->label('Responsável')
                ->placeholder('—')
                ->limit(40)
                ->visible(fn () => in_array('current_responsibles', $this->data['selected_fixed_fields'] ?? [])),

            Tables\Columns\TextColumn::make('sla_due_at')
                ->label('Prazo')
                ->dateTime('d/m/Y')
                ->sortable()
                ->visible(fn () => in_array('sla_due_at', $this->data['selected_fixed_fields'] ?? [])),

            Tables\Columns\TextColumn::make('created_at')
                ->label('Aberta em')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->visible(fn () => in_array('created_at', $this->data['selected_fixed_fields'] ?? [])),
        ];

        // Custom fields based on selected entity_id
        $entityId = $this->data['entity_id'] ?? null;
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
                    ->visible(fn () => in_array($field->id, $this->data['selected_custom_fields'] ?? []));
            }
        }

        return $columns;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Demand::query()
                ->when(
                    !empty($this->data['entity_id']),
                    fn ($q) => $q->where('entity_id', $this->data['entity_id'])
                )
            )
            ->columns($this->getTableColumns())
            ->defaultSort('created_at', 'desc')
            ->filters([

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
            ])
            ->bulkActions([
                // Vazio
            ]);
    }
}
