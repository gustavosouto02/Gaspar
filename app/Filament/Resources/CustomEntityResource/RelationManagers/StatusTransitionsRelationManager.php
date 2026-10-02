<?php

namespace App\Filament\Resources\CustomEntityResource\RelationManagers;

use App\Enums\FieldTypeEnum;
use App\Enums\RuleOperatorEnum;
use App\Models\CustomField;
use App\Models\ProcessRole;
use App\Models\ProcessStatus;
use App\Models\StatusTransition;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StatusTransitionsRelationManager extends RelationManager
{
    protected static string $relationship = 'statusTransitions';

    protected static ?string $title = 'Fluxo de Situações';

    protected static ?string $modelLabel = 'Transição';

    protected static ?string $pluralModelLabel = 'Transições';

    /**
     * Id da situação Condicional (gateway), cacheado por request
     */
    private static ?string $conditionalStatusId = null;

    private static function conditionalStatusId(): ?string
    {
        return self::$conditionalStatusId ??= ProcessStatus::findBySystemKey('conditional')?->id;
    }

    private function isConditionalStatusId(?string $statusId): bool
    {
        return $statusId !== null && $statusId === self::conditionalStatusId();
    }

    public function form(Form $form): Form
    {
        $entity = $this->getOwnerRecord();

        // A Condicional (gateway) nunca é situação de origem, mas pode ser destino
        $fromStatusOptions = $entity->processStatuses()->withoutConditional()->pluck('name', 'process_statuses.id')->toArray();
        $toStatusOptions   = $entity->processStatuses()->pluck('name', 'process_statuses.id')->toArray();
        $roleOptions = [
            '__requester__' => 'Demandante (Quem abriu a demanda)',
            '__assignee__'  => 'Responsável (Executor atual)',
        ];

        // Busca todos os papéis e verifica se há usuários atrelados a eles neste processo
        $allRoles = \App\Models\ProcessRole::orderBy('name')->get();
        foreach ($allRoles as $role) {
            $roleMembers = \App\Models\ProcessMember::with('user')
                ->where('entity_id', $entity->id)
                ->where('process_role_id', $role->id)
                ->get();

            if ($roleMembers->isNotEmpty()) {
                $userNames = $roleMembers->map(fn($m) => $m->user?->name)->filter()->unique()->implode(', ');
                $roleOptions[$role->id] = "{$role->name} ({$userNames})";
            } else {
                $roleOptions[$role->id] = "{$role->name}";
            }
        }

        // Situações elegíveis como resultado de uma regra ou do "senão":
        // as do processo, exceto a Condicional e a situação de origem da transição
        $thenStatusOptions = function (?string $fromStatusId) use ($entity): array {
            return $entity->processStatuses()
                ->withoutConditional()
                ->when($fromStatusId, fn ($q) => $q->where('process_statuses.id', '!=', $fromStatusId))
                ->pluck('name', 'process_statuses.id')
                ->toArray();
        };

        return $form->schema([
            Forms\Components\Select::make('from_status_id')
                ->label('De (situação atual)')
                ->options($fromStatusOptions)
                ->nullable()
                ->live()
                ->placeholder('Qualquer situação (inicial)')
                ->helperText('Deixe vazio para permitir a transição de qualquer situação'),

            Forms\Components\Select::make('to_status_id')
                ->label('Para (próxima situação)')
                ->options($toStatusOptions)
                ->required()
                ->live(),

            Forms\Components\TextInput::make('label')
                ->label('Texto do botão')
                ->required()
                ->maxLength(100)
                ->placeholder('Ex: Aprovar, Rejeitar, Encaminhar...')
                ->helperText('Este texto aparecerá como botão de ação na demanda'),

            Forms\Components\Select::make('allowed_role_ids')
                ->label('Papéis que podem disparar')
                ->options($roleOptions)
                ->multiple()
                ->nullable()
                ->placeholder('Todos os membros/envolvidos')
                ->helperText('Vazio = qualquer envolvido na demanda ou membro do processo pode; selecione para restringir'),

            Forms\Components\Section::make('Tabela de decisão')
                ->description('Regras avaliadas em ordem; a primeira que casar define a situação de destino. Se nenhuma casar, vale o "Senão vai para".')
                ->visible(fn (Get $get) => $this->isConditionalStatusId($get('to_status_id')))
                ->columnSpanFull()
                ->schema([
                    Forms\Components\Repeater::make('rules')
                        ->label('Regras')
                        ->relationship()
                        ->orderColumn('rule_order')
                        ->reorderable()
                        ->minItems(1)
                        ->defaultItems(0)
                        ->addActionLabel('Adicionar regra')
                        ->itemLabel(fn (array $state) => $this->summarizeRule($state))
                        ->schema([
                            Forms\Components\Repeater::make('conditions')
                                ->label('Condições (todas devem ser verdadeiras)')
                                ->minItems(1)
                                ->defaultItems(1)
                                ->addActionLabel('Adicionar condição')
                                ->columns(3)
                                ->schema($this->conditionSchema($entity)),

                            Forms\Components\Select::make('to_status_id')
                                ->label('Então vai para')
                                ->options(fn (Get $get) => $thenStatusOptions($get('../../from_status_id')))
                                ->required(),
                        ]),

                    Forms\Components\Select::make('default_to_status_id')
                        ->label('Senão vai para')
                        ->options(fn (Get $get) => $thenStatusOptions($get('from_status_id')))
                        ->required(fn (Get $get) => $this->isConditionalStatusId($get('to_status_id')))
                        ->helperText('Situação aplicada quando nenhuma regra casar'),
                ]),
        ])->columns(2);
    }

    /**
     * Campos de uma condição: campo do processo, operador válido para o tipo
     * do campo e o valor no formato exigido pelo operador
     *
     * @return array<Forms\Components\Component>
     */
    private function conditionSchema($entity): array
    {
        $fieldOptions = fn () => $entity->fields()->pluck('custom_fields.name', 'custom_fields.id')->toArray();

        $fieldType = function (Get $get): ?FieldTypeEnum {
            $fieldId = $get('field_id');

            return $fieldId ? CustomField::find($fieldId)?->field_type : null;
        };

        $valueKind = function (Get $get): ?string {
            $operator = $get('operator');

            return $operator ? RuleOperatorEnum::tryFrom($operator)?->valueKind() : null;
        };

        $choiceOptions = function (Get $get): array {
            $fieldId = $get('field_id');
            $options = $fieldId ? (CustomField::find($fieldId)?->options_json ?? []) : [];

            return array_combine($options, $options) ?: [];
        };

        return [
            Forms\Components\Select::make('field_id')
                ->label('Campo')
                ->options($fieldOptions)
                ->required()
                ->live()
                ->afterStateUpdated(function (Set $set) {
                    $set('operator', null);
                    $set('value', null);
                }),

            Forms\Components\Select::make('operator')
                ->label('Operador')
                ->options(function (Get $get) use ($fieldType) {
                    $type = $fieldType($get);

                    return $type ? RuleOperatorEnum::optionsForFieldType($type) : [];
                })
                ->required()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('value', null)),

            // Valor único: número
            Forms\Components\TextInput::make('value')
                ->label('Valor')
                ->numeric()
                ->visible(fn (Get $get) => $valueKind($get) === 'single' && $fieldType($get) === FieldTypeEnum::NUMBER)
                ->required(fn (Get $get) => $valueKind($get) === 'single' && $fieldType($get) === FieldTypeEnum::NUMBER),

            // Valor único: data
            Forms\Components\DatePicker::make('value')
                ->label('Valor')
                ->visible(fn (Get $get) => $valueKind($get) === 'single' && $fieldType($get) === FieldTypeEnum::DATE)
                ->required(fn (Get $get) => $valueKind($get) === 'single' && $fieldType($get) === FieldTypeEnum::DATE),

            // Valor único: opção do campo (SELECT/RADIO)
            Forms\Components\Select::make('value')
                ->label('Valor')
                ->options($choiceOptions)
                ->visible(fn (Get $get) => $valueKind($get) === 'single' && in_array($fieldType($get), [FieldTypeEnum::SELECT, FieldTypeEnum::RADIO], true))
                ->required(fn (Get $get) => $valueKind($get) === 'single' && in_array($fieldType($get), [FieldTypeEnum::SELECT, FieldTypeEnum::RADIO], true)),

            // Valor único: texto (TEXT, TEXTAREA, EMAIL)
            Forms\Components\TextInput::make('value')
                ->label('Valor')
                ->visible(fn (Get $get) => $valueKind($get) === 'single' && in_array($fieldType($get), [FieldTypeEnum::TEXT, FieldTypeEnum::TEXTAREA, FieldTypeEnum::EMAIL], true))
                ->required(fn (Get $get) => $valueKind($get) === 'single' && in_array($fieldType($get), [FieldTypeEnum::TEXT, FieldTypeEnum::TEXTAREA, FieldTypeEnum::EMAIL], true)),

            // Lista de opções ("em lista")
            Forms\Components\Select::make('value')
                ->label('Valores')
                ->options($choiceOptions)
                ->multiple()
                ->visible(fn (Get $get) => $valueKind($get) === 'list')
                ->required(fn (Get $get) => $valueKind($get) === 'list'),

            // Intervalo "entre": número
            Forms\Components\TextInput::make('value.min')
                ->label('De')
                ->numeric()
                ->visible(fn (Get $get) => $valueKind($get) === 'range' && $fieldType($get) === FieldTypeEnum::NUMBER)
                ->required(fn (Get $get) => $valueKind($get) === 'range' && $fieldType($get) === FieldTypeEnum::NUMBER),
            Forms\Components\TextInput::make('value.max')
                ->label('Até')
                ->numeric()
                ->visible(fn (Get $get) => $valueKind($get) === 'range' && $fieldType($get) === FieldTypeEnum::NUMBER)
                ->required(fn (Get $get) => $valueKind($get) === 'range' && $fieldType($get) === FieldTypeEnum::NUMBER),

            // Intervalo "entre": data
            Forms\Components\DatePicker::make('value.min')
                ->label('De')
                ->visible(fn (Get $get) => $valueKind($get) === 'range' && $fieldType($get) === FieldTypeEnum::DATE)
                ->required(fn (Get $get) => $valueKind($get) === 'range' && $fieldType($get) === FieldTypeEnum::DATE),
            Forms\Components\DatePicker::make('value.max')
                ->label('Até')
                ->visible(fn (Get $get) => $valueKind($get) === 'range' && $fieldType($get) === FieldTypeEnum::DATE)
                ->required(fn (Get $get) => $valueKind($get) === 'range' && $fieldType($get) === FieldTypeEnum::DATE),
        ];
    }

    /**
     * Resumo da regra para o cabeçalho do item: "Se Valor > 1000 → Em aprovação"
     */
    private function summarizeRule(array $state): string
    {
        try {
            $conditions = array_values($state['conditions'] ?? []);
            $first      = $conditions[0] ?? null;

            if (! $first || empty($first['field_id']) || empty($first['operator'])) {
                return 'Nova regra';
            }

            $fieldName = CustomField::find($first['field_id'])?->name ?? '?';
            $operator  = RuleOperatorEnum::tryFrom($first['operator']);
            $value     = $first['value'] ?? null;

            if (is_array($value)) {
                $value = isset($value['min']) || isset($value['max'])
                    ? sprintf('%s e %s', $value['min'] ?? '?', $value['max'] ?? '?')
                    : implode(', ', $value);
            }

            $summary = trim(sprintf('Se %s %s %s', $fieldName, $operator?->symbol() ?? '?', $value ?? ''));

            if (count($conditions) > 1) {
                $summary .= sprintf(' (+%d condições)', count($conditions) - 1);
            }

            $statusName = ! empty($state['to_status_id'])
                ? ProcessStatus::find($state['to_status_id'])?->name
                : null;

            return $statusName ? "{$summary} → {$statusName}" : $summary;
        } catch (\Throwable $t) {
            return 'Regra';
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->modifyQueryUsing(fn ($query) => $query->with(['fromStatus', 'toStatus', 'defaultToStatus', 'rules.toStatus']))
            ->columns([
                Tables\Columns\TextColumn::make('fromStatus.name')
                    ->label('De')
                    ->placeholder('Qualquer situação')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('toStatus.name')
                    ->label('Para')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(function ($state, StatusTransition $record) {
                        if (! $record->toStatus?->isConditional()) {
                            return $state;
                        }

                        $targets = $record->rules
                            ->map(fn ($rule) => $rule->toStatus?->name)
                            ->push($record->defaultToStatus?->name)
                            ->filter()
                            ->unique()
                            ->implode(' | ');

                        return $targets !== '' ? "Condicional → {$targets}" : 'Condicional';
                    }),

                Tables\Columns\TextColumn::make('label')
                    ->label('Botão de ação')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('allowed_role_ids')
                    ->label('Papéis permitidos')
                    ->formatStateUsing(function ($state, $record) {
                        $ids = is_string($state) ? json_decode($state, true) : $state;

                        if (empty($ids)) {
                            return 'Todos os membros';
                        }

                        $labels = collect();

                        if (in_array('__requester__', $ids)) {
                            $labels->push('Demandante');
                        }
                        if (in_array('__assignee__', $ids)) {
                            $labels->push('Responsável (Atual)');
                        }

                        $staticRolesIds = array_diff($ids, ['__requester__', '__assignee__']);
                        if (! empty($staticRolesIds)) {
                            // Busca os papéis exigidos
                            $roles = \App\Models\ProcessRole::whereIn('id', $staticRolesIds)->get();

                            foreach ($roles as $role) {
                                // Busca os membros reais do processo que têm esse papel
                                $members = \App\Models\ProcessMember::with('user')
                                    ->where('entity_id', $record->entity_id)
                                    ->where('process_role_id', $role->id)
                                    ->get();

                                if ($members->isEmpty()) {
                                    $labels->push("{$role->name} (Sem usuários)");
                                } else {
                                    $names = $members->map(fn ($m) => $m->user?->name)->filter()->unique()->implode(', ');
                                    $labels->push("{$role->name} ({$names})");
                                }
                            }
                        }

                        return $labels->implode(' / ');
                    })
                    ->wrap(),
            ])
            ->defaultSort('display_order')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nova Transição')
                    ->modalWidth('5xl')
                    ->after(fn (StatusTransition $record) => $this->cleanupDecisionTable($record)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->modalWidth('5xl'),
                Tables\Actions\EditAction::make()
                    ->modalWidth('5xl')
                    ->after(fn (StatusTransition $record) => $this->cleanupDecisionTable($record)),
                Tables\Actions\DeleteAction::make(),
            ])
            ->reorderable('display_order')
            ->bulkActions([]);
    }

    /**
     * Se o destino deixou de ser a Condicional, remove regras e "senão" dormentes
     */
    private function cleanupDecisionTable(StatusTransition $record): void
    {
        if ($record->toStatus?->isConditional()) {
            return;
        }

        $record->rules()->delete();

        if ($record->default_to_status_id !== null) {
            $record->update(['default_to_status_id' => null]);
        }
    }
}
