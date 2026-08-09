<?php

namespace App\Filament\Resources;

use App\Enums\DemandPriorityEnum;
use App\Enums\DemandStatusEnum;
use App\Enums\ProcessStatusColorEnum;
use App\Filament\Resources\DemandResource\Pages;
use App\Filament\Resources\DemandResource\RelationManagers;
use App\Models\CustomEntity;
use App\Models\Demand;
use App\Models\ProcessStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DemandResource extends Resource
{
    protected static ?string $model = Demand::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Demandas';

    protected static ?string $modelLabel = 'Demanda';

    protected static ?string $pluralModelLabel = 'Demandas';

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 1;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Group::make()
                                    ->schema([
                                        Forms\Components\Placeholder::make('parent_link')
                                            ->label('Demanda Mãe:')
                                            ->content(fn ($record) => $record?->parent_demand_id 
                                                ? new \Illuminate\Support\HtmlString('<a href="'.DemandResource::getUrl('edit', ['record' => $record->parent_demand_id]).'" class="text-primary-600 underline font-semibold">Acessar Demanda Mãe: ' . e($record->parent?->title) . ' (#' . strtoupper(substr($record->parent_demand_id, 0, 8)) . ')</a>') 
                                                : '-')
                                            ->visible(fn ($record) => (bool) $record?->parent_demand_id),

                                        Forms\Components\Placeholder::make('id_label')
                                            ->label('Demanda número:')
                                            ->content(fn ($record) => $record ? '#' . strtoupper(substr($record->id, 0, 8)) : 'NOVA'),

                                        Forms\Components\Placeholder::make('created_at_label')
                                            ->label('Data de criação:')
                                            ->content(fn ($record) => $record ? $record->created_at->format('d/m/Y') : '-'),

                                        Forms\Components\Select::make('entity_id')
                                            ->label('Processo:')
                                            ->default(request()->query('entity_id'))
                                            ->options(\App\Models\CustomEntity::where('is_active', true)->pluck('name', 'id'))
                                            ->searchable()
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                                if ($state) {
                                                    $entity = \App\Models\CustomEntity::find($state);
                                                    if ($entity) {
                                                        // Tenta buscar a situação "Nova"
                                                        $nova = $entity->processStatuses()->where('name', 'Nova')->first();
                                                        // Se não tiver "Nova", pega a primeira que achar
                                                        if (! $nova) {
                                                            $nova = $entity->processStatuses()->orderBy('display_order')->first();
                                                        }
                                                        $set('process_status_id', $nova?->id);
                                                    } else {
                                                        $set('process_status_id', null);
                                                    }
                                                } else {
                                                    $set('process_status_id', null);
                                                }
                                            }),

                                        Forms\Components\Select::make('process_status_id')
                                            ->label('Situação:')
                                            ->options(function (Get $get) {
                                                $entityId = $get('entity_id');
                                                if (! $entityId) return [];
                                                $entity = \App\Models\CustomEntity::find($entityId);
                                                if (! $entity) return [];
                                                return $entity->processStatuses()->pluck('name', 'process_statuses.id');
                                            })
                                            ->searchable()
                                            ->required()
                                            ->placeholder('Selecione primeiro o processo...'),

                                        Forms\Components\Select::make('client_id')
                                            ->label('Cliente:')
                                            ->default(request()->query('client_id'))
                                            ->relationship('client', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->nullable(),

                                        Forms\Components\Select::make('project_id')
                                            ->label('Projeto:')
                                            ->default(request()->query('project_id'))
                                            ->relationship('project', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->nullable(),

                                        Forms\Components\Select::make('requested_by')
                                            ->label('Demandante:')
                                            ->relationship('requester', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->default(fn () => auth()->id())
                                            ->disabled()
                                            ->dehydrated(),

                                        Forms\Components\Placeholder::make('current_responsibles_label')
                                            ->label('Responsável:')
                                            ->content(fn ($record) => $record ? $record->current_responsibles : 'Atribuição automática...'),
                                    ]),

                                Forms\Components\Group::make()
                                    ->schema([
                                        Forms\Components\Placeholder::make('sla_due_at_label')
                                            ->label('Prazo de atendimento:')
                                            ->content(fn ($record) => $record && $record->sla_due_at ? $record->sla_due_at->format('d/m/Y') : '-'),

                                        Forms\Components\Placeholder::make('updated_at_label')
                                            ->label('Última atualização:')
                                            ->content(fn ($record) => $record ? $record->updated_at->format('d/m/Y') : '-'),
                                    ]),

                                Forms\Components\Group::make()
                                    ->schema([
                                        Forms\Components\Placeholder::make('completed_at_label')
                                            ->label('Data de conclusão:')
                                            ->content(fn ($record) => $record && $record->completed_at ? $record->completed_at->format('d/m/Y') : '-'),

                                        Forms\Components\Placeholder::make('satisfaction_rating_label')
                                            ->label('Avaliação do demandante:')
                                            ->content(fn ($record) => $record && $record->satisfaction_rating ? $record->satisfaction_rating->label() : '-'),
                                    ]),
                            ]),

                        Forms\Components\TextInput::make('title')
                            ->label('Título:')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('description')
                            ->label('Descrição:')
                            ->nullable()
                            ->rows(4)
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('attachments')
                            ->label('Anexos:')
                            ->multiple()
                            ->directory('demand-attachments')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Campos do Processo')
                    ->schema(fn (Get $get) => static::buildDynamicFieldSchema($get('entity_id')))
                    ->columns(2)
                    ->visible(fn (Get $get) => filled($get('entity_id')))
                    ->description('Campos específicos configurados para este processo'),

                Forms\Components\Section::make('Tratamento')
                    ->schema([
                        Forms\Components\Textarea::make('new_treatment')
                            ->label('Novo Tratamento / Comentário')
                            ->placeholder('Escreva aqui seu novo tratamento...')
                            ->rows(3)
                            ->dehydrated(false) // Não salva diretamente na model Demand
                            ->visible(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord),

                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('enviar_tratamento')
                                ->label('Enviar Tratamento')
                                ->icon('heroicon-m-paper-airplane')
                                ->color('primary')
                                ->action(function (\Filament\Forms\Get $get, \Filament\Forms\Set $set, $record) {
                                    $content = $get('new_treatment');
                                    if (empty(trim((string)$content))) {
                                        return;
                                    }

                                    \App\Models\DemandComment::create([
                                        'demand_id' => $record->id,
                                        'user_id' => auth()->id(),
                                        'comment' => $content,
                                    ]);

                                    $set('new_treatment', null);

                                    \Filament\Notifications\Notification::make()
                                        ->title('Tratamento adicionado com sucesso!')
                                        ->success()
                                        ->send();
                                })
                        ])->visible(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord),

                        Forms\Components\ViewField::make('treatments_timeline')
                            ->view('filament.forms.components.demand-timeline')
                            ->visible(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord)
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord)
                    ->collapsible(),

                Forms\Components\Hidden::make('created_by')
                    ->default(fn () => auth()->id()),
                    
                Forms\Components\Hidden::make('status')
                    ->default(\App\Enums\DemandStatusEnum::ACTIVE->value),
                    
                Forms\Components\Hidden::make('priority')
                    ->default(\App\Enums\DemandPriorityEnum::MEDIUM->value),

                Forms\Components\Hidden::make('parent_demand_id')
                    ->default(request()->query('parent_demand_id')),
            ]);
    }

    /**
     * Renderiza os campos customizados do processo no form.
     * Usa "field_data.{key}" como namespace — interceptado nas Pages para salvar em demand_field_values.
     */
    public static function buildDynamicFieldSchema(?string $entityId): array
    {
        if (! $entityId) {
            return [];
        }

        $entity = \App\Models\CustomEntity::find($entityId);
        if (! $entity) {
            return [];
        }

        $fields = $entity->fields()->get();

        if ($fields->isEmpty()) {
            return [
                Forms\Components\Placeholder::make('no_fields')
                    ->label('')
                    ->content('Este processo não possui campos customizados configurados.')
                    ->columnSpanFull(),
            ];
        }

        $schema = [];
        foreach ($fields as $field) {
            $key       = "field_data.{$field->key}";
            $component = match ($field->field_type) {
                \App\Enums\FieldTypeEnum::TEXT     => Forms\Components\TextInput::make($key),
                \App\Enums\FieldTypeEnum::TEXTAREA => Forms\Components\Textarea::make($key)->rows(3),
                \App\Enums\FieldTypeEnum::NUMBER   => Forms\Components\TextInput::make($key)->numeric(),
                \App\Enums\FieldTypeEnum::DATE     => Forms\Components\DatePicker::make($key),
                \App\Enums\FieldTypeEnum::SELECT   => Forms\Components\Select::make($key)
                    ->options($field->options_json
                        ? array_combine($field->options_json, $field->options_json)
                        : []),
                \App\Enums\FieldTypeEnum::RADIO    => Forms\Components\Radio::make($key)
                    ->options($field->options_json
                        ? array_combine($field->options_json, $field->options_json)
                        : []),
                \App\Enums\FieldTypeEnum::CHECKBOX => Forms\Components\Toggle::make($key),
                \App\Enums\FieldTypeEnum::EMAIL    => Forms\Components\TextInput::make($key)->email(),
                default                            => Forms\Components\TextInput::make($key),
            };

            $component->label($field->name)
                ->required($field->is_required);

            if (method_exists($component, 'placeholder') && $field->placeholder) {
                $component->placeholder($field->placeholder);
            }

            if ($field->default_value) {
                $component->default($field->default_value);
            }

            $schema[] = $component;
        }

        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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
                    ->relationship('entity', 'name'),

                Tables\Filters\SelectFilter::make('process_status_id')
                    ->label('Situação')
                    ->relationship('processStatus', 'name'),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(DemandStatusEnum::options()),

                Tables\Filters\SelectFilter::make('priority')
                    ->label('Prioridade')
                    ->options(DemandPriorityEnum::options()),

                Tables\Filters\SelectFilter::make('client_id')
                    ->label('Cliente')
                    ->relationship('client', 'name'),

                Tables\Filters\SelectFilter::make('project_id')
                    ->label('Projeto')
                    ->relationship('project', 'name'),

                Tables\Filters\SelectFilter::make('requested_by')
                    ->label('Demandante')
                    ->relationship('requester', 'name'),

                Tables\Filters\SelectFilter::make('assigned_to')
                    ->label('Responsável')
                    ->relationship('assignee', 'name'),

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

                Tables\Filters\TernaryFilter::make('minhas')
                    ->label('Minhas demandas')
                    ->trueLabel('Apenas minhas')
                    ->falseLabel('Todas')
                    ->queries(
                        true: fn ($query) => $query->pendingForUser(auth()->user()),
                        false: fn ($query) => $query,
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \Filament\Resources\RelationManagers\RelationGroup::make('Relacionamentos', [
                RelationManagers\SubDemandsRelationManager::class,
            ]),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDemands::route('/'),
            'create' => Pages\CreateDemand::route('/create'),
            'view'   => Pages\ViewDemand::route('/{record}'),
            'edit'   => Pages\EditDemand::route('/{record}/edit'),
        ];
    }
}
