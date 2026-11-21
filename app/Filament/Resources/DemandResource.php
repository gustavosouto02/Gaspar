<?php

namespace App\Filament\Resources;

use App\Enums\DemandPriorityEnum;
use App\Enums\DemandStatusEnum;
use App\Enums\FieldPermissionEnum;
use App\Enums\ProcessStatusColorEnum;
use App\Enums\UserRoleEnum;
use App\Filament\Resources\DemandResource\Pages;
use App\Models\ActivityLog;
use App\Models\CustomEntity;
use App\Models\Demand;
use App\Models\DemandFieldValue;
use App\Models\ProcessStatus;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CustomFieldComponentFactory;
use App\Services\CustomFieldValueFormatter;
use App\Services\FieldPermissionResolver;
use App\Services\MoneyFieldVisibility;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

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
                                                ? new HtmlString('<a href="'.DemandResource::getUrl('edit', ['record' => $record->parent_demand_id]).'" class="text-primary-600 underline font-semibold">Acessar Demanda Mãe: '.e($record->parent?->title).' ('.($record->parent?->code ?? '#'.strtoupper(substr($record->parent_demand_id, 0, 8))).')</a>')
                                                : '-')
                                            ->visible(fn ($record) => (bool) $record?->parent_demand_id),

                                        Forms\Components\Placeholder::make('id_label')
                                            ->label('Número:')
                                            ->content(fn ($record) => $record ? ($record->code ?? '#'.strtoupper(substr($record->id, 0, 8))) : 'NOVA'),

                                        Forms\Components\Placeholder::make('created_at_label')
                                            ->label('Data de criação:')
                                            ->content(fn ($record) => $record ? $record->created_at->format('d/m/Y') : '-'),

                                        Forms\Components\Select::make('entity_id')
                                            ->label('Processo:')
                                            ->default(request()->query('entity_id'))
                                            ->options(fn () => CustomEntity::with('macroprocess')->where('is_active', true)->get()->pluck('full_display_name', 'id'))
                                            ->searchable()
                                            ->required()
                                            ->disabled(fn (string $operation) => $operation === 'edit')
                                            ->live()
                                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                                if ($state) {
                                                    $entity = CustomEntity::find($state);
                                                    if ($entity) {
                                                        // Situação "Nova" ou, sem ela, a primeira do processo
                                                        $set('process_status_id', $entity->initialProcessStatus()?->id);
                                                    } else {
                                                        $set('process_status_id', null);
                                                    }
                                                } else {
                                                    $set('process_status_id', null);
                                                }
                                            }),

                                        Forms\Components\Select::make('process_status_id')
                                            ->label('Situação:')
                                            // Processo pré-selecionado por ?entity_id= não dispara o afterStateUpdated
                                            // do select de processo: a situação inicial vem por default
                                            ->default(fn () => ($entityId = request()->query('entity_id'))
                                                ? CustomEntity::find($entityId)?->initialProcessStatus()?->id
                                                : null)
                                            ->options(function (Get $get) {
                                                $entityId = $get('entity_id');
                                                if (! $entityId) {
                                                    return [];
                                                }
                                                $entity = CustomEntity::find($entityId);
                                                if (! $entity) {
                                                    return [];
                                                }

                                                return $entity->processStatuses()->withoutConditional()->pluck('name', 'process_statuses.id');
                                            })
                                            ->disabled()
                                            ->dehydrated()
                                            ->required()
                                            ->placeholder('Selecione primeiro o processo...'),

                                        Forms\Components\Select::make('client_id')
                                            ->label('Cliente:')
                                            ->default(request()->query('client_id'))
                                            ->relationship('client', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->required(),

                                        Forms\Components\Select::make('project_id')
                                            ->label('Projeto:')
                                            ->default(request()->query('project_id'))
                                            ->options(fn () => ['none' => 'Sem vínculo'] + Project::query()
                                                ->orderBy('acronym')
                                                ->orderBy('name')
                                                ->get()
                                                ->mapWithKeys(fn (Project $project) => [$project->id => $project->display_name])
                                                ->all())
                                            ->searchable()
                                            ->required()
                                            ->afterStateHydrated(function (Forms\Components\Select $component, $state, $record) {
                                                if ($record && blank($state)) {
                                                    $component->state('none');
                                                }
                                            })
                                            ->dehydrateStateUsing(fn ($state) => $state === 'none' ? null : $state),

                                        Forms\Components\Select::make('supplier_id')
                                            ->label('Fornecedor:')
                                            ->default(request()->query('supplier_id'))
                                            ->options(function (?Demand $record): array {
                                                $suppliers = Supplier::query()->orderBy('name')->pluck('name', 'id')->all();

                                                if ($record?->supplier_id && ! isset($suppliers[$record->supplier_id])) {
                                                    $trashed = Supplier::withTrashed()->find($record->supplier_id);
                                                    if ($trashed) {
                                                        $suppliers[$trashed->id] = $trashed->name.' (excluído)';
                                                    }
                                                }

                                                return ['none' => 'Não atua'] + $suppliers;
                                            })
                                            ->in(fn (Forms\Components\Select $component): array => array_keys($component->getOptions()))
                                            ->searchable()
                                            ->required()
                                            ->afterStateHydrated(function (Forms\Components\Select $component, $state, $record) {
                                                if ($record && blank($state)) {
                                                    $component->state('none');
                                                }
                                            })
                                            ->dehydrateStateUsing(fn ($state) => $state === 'none' ? null : $state),

                                        Forms\Components\Select::make('requested_by')
                                            ->label('Demandante:')
                                            ->relationship('requester', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->default(fn () => auth()->id())
                                            ->disabled()
                                            ->dehydrated(),

                                        Forms\Components\TextInput::make('current_responsibles_label')
                                            ->label('Responsável:')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->placeholder('—')
                                            ->afterStateHydrated(function (Forms\Components\TextInput $component, $record) {
                                                $component->state($record ? $record->current_responsibles : null);
                                            }),
                                    ]),

                                Forms\Components\Group::make()
                                    ->schema([
                                        Forms\Components\Placeholder::make('sla_due_at_label')
                                            ->label('Prazo de atendimento:')
                                            ->content(function ($record, Get $get) {
                                                if ($record && $record->sla_due_at) {
                                                    $isClosed = in_array($record->status?->value, ['CLOSED', 'EVALUATED']);
                                                    $vencido = $record->sla_due_at->isPast() && ! $isClosed;

                                                    return $record->sla_due_at->format('d/m/Y H:i').($vencido ? ' (Vencido)' : '');
                                                }

                                                $entityId = $get('entity_id') ?? $record?->entity_id;
                                                if ($entityId) {
                                                    $entity = CustomEntity::find($entityId);
                                                    if ($entity && $entity->sla_hours) {
                                                        $dias = round($entity->sla_hours / 24, 1);
                                                        $previsao = now()->addHours($entity->sla_hours)->format('d/m/Y H:i');

                                                        return "{$entity->sla_hours}h (~{$dias} dias) — Previsão: {$previsao}";
                                                    }
                                                }

                                                return 'Sem prazo configurado';
                                            }),

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
                    ->schema(fn (Get $get, ?Demand $record) => static::buildDynamicFieldSchema(
                        $get('entity_id'),
                        $record?->process_status_id ?? $get('process_status_id'),
                        auth()->user(),
                        $record,
                    ))
                    ->columns(2)
                    ->visible(fn (Get $get) => filled($get('entity_id')))
                    ->description('Campos específicos configurados para este processo'),

                Forms\Components\Section::make('Subdemandas')
                    ->headerActions([
                        Forms\Components\Actions\Action::make('registrar_subdemanda')
                            ->label('Registrar Subdemanda')
                            ->icon('heroicon-m-plus')
                            ->color('warning')
                            ->url(fn ($record) => $record ? DemandResource::getUrl('create', [
                                'parent_demand_id' => $record->id,
                                'client_id' => $record->client_id,
                                'project_id' => $record->project_id ?? 'none',
                                'supplier_id' => $record->supplier_id ?? 'none',
                            ]) : null),
                    ])
                    ->schema([
                        Forms\Components\ViewField::make('demand_subdemands')
                            ->view('filament.forms.components.demand-subdemands')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record !== null)
                    ->collapsible(),

                Forms\Components\Section::make('Tratamento')
                    ->schema([
                        Forms\Components\ViewField::make('treatments_timeline')
                            ->view('filament.forms.components.demand-timeline')
                            ->visible(fn ($record) => $record !== null)
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record !== null)
                    ->collapsible(),

                Forms\Components\Hidden::make('created_by')
                    ->default(fn () => auth()->id()),

                Forms\Components\Hidden::make('status')
                    ->default(DemandStatusEnum::ACTIVE->value),

                Forms\Components\Hidden::make('priority')
                    ->default(DemandPriorityEnum::MEDIUM->value),

                Forms\Components\Hidden::make('parent_demand_id')
                    ->default(request()->query('parent_demand_id')),
            ]);
    }

    /**
     * Renderiza os campos customizados do processo no form.
     * Usa "field_data.{key}" como namespace — interceptado nas Pages para salvar em demand_field_values.
     * Quando situação e usuário são informados, aplica a matriz de permissões
     * de campos (obrigatório/opcional/somente leitura/oculto).
     */
    public static function buildDynamicFieldSchema(?string $entityId, ?string $processStatusId = null, ?User $user = null, ?Demand $record = null): array
    {
        if (! $entityId) {
            return [];
        }

        $entity = CustomEntity::find($entityId);
        if (! $entity) {
            return [];
        }

        $fields = $entity->fields()->get();
        $statusRecord = $processStatusId ? ProcessStatus::find($processStatusId) : null;
        $historyFields = static::historyFieldsSchema($record, $entity, $fields, $statusRecord, $user);

        if ($fields->isEmpty() && empty($historyFields)) {
            return [
                Forms\Components\Placeholder::make('no_fields')
                    ->label('')
                    ->content('Este processo não possui campos customizados configurados.')
                    ->columnSpanFull(),
            ];
        }

        $permissions = null;

        if ($statusRecord && $user) {
            $permissions = app(FieldPermissionResolver::class)->resolve($entity, $statusRecord, $user);
        }

        $fieldFactory = app(CustomFieldComponentFactory::class);

        $schema = [];
        foreach ($fields as $field) {
            $component = $fieldFactory->make($field, "field_data.{$field->key}");

            if ($permissions === null) {
                // Sem situação/usuário resolvidos, mantém o comportamento original
                $component->required($field->is_required);
            } else {
                match ($permissions[$field->id] ?? FieldPermissionEnum::OPTIONAL) {
                    FieldPermissionEnum::HIDDEN => $component->hidden(),
                    FieldPermissionEnum::READONLY => $component->disabled()->dehydrated(false),
                    // Rascunho ("Salvar" na criação ou edição de rascunho) ignora a obrigatoriedade
                    FieldPermissionEnum::REQUIRED => $component->required(
                        fn ($livewire) => ! method_exists($livewire, 'isSavingDraft') || ! $livewire->isSavingDraft()
                    ),
                    FieldPermissionEnum::OPTIONAL => $component->required(false),
                };
            }

            $schema[] = $component;
        }

        return array_merge($schema, $historyFields);
    }

    /**
     * Campos que a demanda preencheu mas que não fazem mais parte do processo
     * (desvinculados ou excluídos). Aparecem só como texto, com o valor da época:
     * sem estado em field_data (a chave do campo não é única) e sem validação.
     *
     * @param  Collection<int, \App\Models\CustomField>  $currentFields
     * @return array<int, Forms\Components\Component>
     */
    private static function historyFieldsSchema(?Demand $record, CustomEntity $entity, \Illuminate\Support\Collection $currentFields, ?ProcessStatus $status, ?User $user): array
    {
        $formatter = app(CustomFieldValueFormatter::class);

        return static::visibleHistoryValues($record, $entity, $currentFields, $status, $user)
            ->map(fn (DemandFieldValue $fv) => Forms\Components\Placeholder::make("history_{$fv->custom_field_id}")
                ->label($fv->customField->name.' (fora do formulário atual)')
                ->content($formatter->format($fv->customField, $fv->value)))
            ->values()
            ->all();
    }

    /**
     * Valores de campos fora do processo que o usuário pode ver. Segue a mesma
     * regra do PDF e do relatório: descarta os ocultos pela matriz de permissões
     * e os de moeda que o usuário não pode ver.
     *
     * @param  Collection<int, \App\Models\CustomField>  $currentFields
     * @return \Illuminate\Support\Collection<int, DemandFieldValue>
     */
    public static function visibleHistoryValues(?Demand $record, CustomEntity $entity, \Illuminate\Support\Collection $currentFields, ?ProcessStatus $status, ?User $user): \Illuminate\Support\Collection
    {
        if (! $record) {
            return collect();
        }

        $values = $record->fieldValues()
            ->whereNotIn('custom_field_id', $currentFields->pluck('id')->all())
            ->with('customField')
            ->get()
            ->filter(fn (DemandFieldValue $fv) => $fv->customField && filled($fv->value));

        if ($values->isEmpty()) {
            return $values;
        }

        $permissions = $status && $user
            ? app(FieldPermissionResolver::class)->resolveFor($entity, $status, $user, $values->pluck('customField')->unique('id'))
            : [];
        $money = app(MoneyFieldVisibility::class);

        return $values->reject(fn (DemandFieldValue $fv) => ($permissions[$fv->custom_field_id] ?? null) === FieldPermissionEnum::HIDDEN
            || $money->isHiddenFor($user, $fv->customField, $entity));
    }

    /**
     * Valores gravados para preencher field_data ao abrir a demanda. Só entram
     * campos atuais do processo e nunca os ocultos para o usuário na situação
     * atual (o estado do Livewire vai inteiro para o navegador).
     *
     * @return array<string, mixed> mapa key => valor
     */
    public static function fieldDataForFill(Demand $demand, ?User $user = null): array
    {
        $entity = $demand->entity;
        if (! $entity) {
            return [];
        }

        $fields = $entity->fields()->get();

        $status = $demand->processStatus;
        $hidden = $status && $user
            ? array_keys(array_filter(
                app(FieldPermissionResolver::class)->resolveFor($entity, $status, $user, $fields),
                fn (FieldPermissionEnum $permission) => $permission === FieldPermissionEnum::HIDDEN
            ))
            : [];

        $keys = $fields->reject(fn ($field) => in_array($field->id, $hidden, true))->pluck('key', 'id');

        $data = [];
        foreach ($demand->fieldValues()->whereIn('custom_field_id', $keys->keys())->get() as $fv) {
            $data[$keys[$fv->custom_field_id]] = $fv->value;
        }

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Número')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable(query: fn ($query, string $search) => $query->globalSearch($search))
                    ->sortable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('entity.name')
                    ->label('Processo')
                    ->formatStateUsing(fn ($record) => $record->entity?->full_display_name ?? '—')
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
                    ->options(fn () => CustomEntity::with('macroprocess')->get()->pluck('full_display_name', 'id'))
                    ->searchable(),

                Tables\Filters\SelectFilter::make('process_status_id')
                    ->label('Situação')
                    ->relationship('processStatus', 'name', fn ($query) => $query->withoutConditional()),

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
                    ->relationship('project', 'name', modifyQueryUsing: fn ($query) => $query->orderBy('acronym')->orderBy('name'))
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name),

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
                Tables\Actions\EditAction::make()
                    ->visible(fn (Demand $record) => auth()->user()->can('update', $record)),
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->url(fn (Demand $record) => route('demands.pdf', ['record' => $record->id]))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('cancelar')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cancelar Demanda')
                    ->modalDescription('Tem certeza que deseja cancelar esta demanda? Ela continuará salva, mas seu status será alterado para Cancelada.')
                    ->action(function (Demand $record) {
                        if ($record->hasPendingChildren()) {
                            Notification::make()
                                ->title('Não é possível cancelar a demanda')
                                ->body('Esta demanda possui subdemandas pendentes. Conclua ou cancele-as primeiro.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $canceledStatus = ProcessStatus::findBySystemKey('canceled');
                        $oldStatusId = $record->process_status_id;
                        $oldStatusName = $record->processStatus?->name ?? '—';
                        $newStatusName = $canceledStatus?->name ?? 'Cancelada';

                        $record->update([
                            'status' => DemandStatusEnum::CANCELED->value,
                            'completed_at' => now(),
                            'process_status_id' => $canceledStatus ? $canceledStatus->id : $record->process_status_id,
                        ]);

                        ActivityLog::create([
                            'user_id' => auth()->id(),
                            'event' => 'transition',
                            'auditable_type' => Demand::class,
                            'auditable_id' => (string) $record->id,
                            'old_values' => ['process_status_id' => $oldStatusId, 'status_name' => $oldStatusName],
                            'new_values' => [
                                'process_status_id' => $canceledStatus?->id ?? $oldStatusId,
                                'status_name' => $newStatusName,
                                'action' => 'Cancelamento da Demanda',
                            ],
                            'ip_address' => request()->ip(),
                            'user_agent' => request()->userAgent(),
                            'created_at' => now(),
                        ]);

                        Notification::make()->title('Demanda cancelada com sucesso!')->success()->send();
                    })
                    ->visible(
                        fn (Demand $record) => ! $record->processStatus?->isCanceled()
                            && ! $record->processStatus?->isClosed()
                            && ! $record->processStatus?->isEvaluated()
                            && (auth()->user()->user_role === UserRoleEnum::ADMIN
                                || $record->requested_by === auth()->id())
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('cancelar_selecionadas')
                        ->label('Cancelar Selecionadas')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $canceledStatus = ProcessStatus::findBySystemKey('canceled');
                            $canceledCount = 0;
                            foreach ($records as $record) {
                                if ($record->hasPendingChildren()) {
                                    continue;
                                }
                                if ($record->processStatus?->isCanceled() || $record->processStatus?->isClosed() || $record->processStatus?->isEvaluated()) {
                                    continue;
                                }
                                $oldStatusId = $record->process_status_id;
                                $oldStatusName = $record->processStatus?->name ?? '—';
                                $newStatusName = $canceledStatus?->name ?? 'Cancelada';

                                $record->update([
                                    'status' => DemandStatusEnum::CANCELED->value,
                                    'completed_at' => now(),
                                    'process_status_id' => $canceledStatus ? $canceledStatus->id : $record->process_status_id,
                                ]);

                                ActivityLog::create([
                                    'user_id' => auth()->id(),
                                    'event' => 'transition',
                                    'auditable_type' => Demand::class,
                                    'auditable_id' => (string) $record->id,
                                    'old_values' => ['process_status_id' => $oldStatusId, 'status_name' => $oldStatusName],
                                    'new_values' => [
                                        'process_status_id' => $canceledStatus?->id ?? $oldStatusId,
                                        'status_name' => $newStatusName,
                                        'action' => 'Cancelamento da Demanda',
                                    ],
                                    'ip_address' => request()->ip(),
                                    'user_agent' => request()->userAgent(),
                                    'created_at' => now(),
                                ]);

                                $canceledCount++;
                            }
                            Notification::make()->title("{$canceledCount} demanda(s) cancelada(s) com sucesso!")->success()->send();
                        })
                        ->visible(fn () => auth()->user()->user_role === UserRoleEnum::ADMIN),
                ]),
            ])
            ->recordUrl(fn (Demand $record): string => Pages\ViewDemand::getUrl(['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [
            // Relacionamentos movidos para o form
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDemands::route('/'),
            'create' => Pages\CreateDemand::route('/create'),
            'view' => Pages\ViewDemand::route('/{record}'),
            'edit' => Pages\EditDemand::route('/{record}/edit'),
        ];
    }
}
