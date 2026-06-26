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

    protected static ?string $navigationGroup = 'Demandas';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Processo e Situação')
                    ->schema([
                        Forms\Components\Select::make('entity_id')
                            ->label('Processo')
                            ->options(CustomEntity::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('process_status_id', null)),

                        Forms\Components\Select::make('process_status_id')
                            ->label('Situação')
                            ->options(function (Get $get) {
                                $entityId = $get('entity_id');
                                if (! $entityId) {
                                    return [];
                                }
                                $entity = CustomEntity::find($entityId);
                                if (! $entity) {
                                    return [];
                                }
                                return $entity->processStatuses()->pluck('name', 'process_statuses.id');
                            })
                            ->searchable()
                            ->nullable()
                            ->placeholder('Selecione primeiro o processo...'),
                    ])->columns(2),

                Forms\Components\Section::make('Identificação')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->label('Descrição')
                            ->nullable()
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Pessoas')
                    ->schema([
                        Forms\Components\Select::make('requested_by')
                            ->label('Demandante')
                            ->relationship('requester', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn () => auth()->id())
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\Placeholder::make('current_responsibles')
                            ->label('Responsável Atual')
                            ->content(fn ($record) => $record ? $record->current_responsibles : 'Atribuição automática após salvar...')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Vinculações')
                    ->schema([
                        Forms\Components\Select::make('client_id')
                            ->label('Cliente')
                            ->relationship('client', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->placeholder('Sem cliente'),

                        Forms\Components\Select::make('project_id')
                            ->label('Projeto')
                            ->relationship('project', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->placeholder('Sem projeto'),
                    ])->columns(2),

                Forms\Components\Section::make('Controle')
                    ->schema([
                        Forms\Components\Select::make('priority')
                            ->label('Prioridade')
                            ->options(DemandPriorityEnum::options())
                            ->default(DemandPriorityEnum::MEDIUM->value)
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(DemandStatusEnum::options())
                            ->default(DemandStatusEnum::ACTIVE->value)
                            ->required()
                            ->native(false),

                        Forms\Components\DateTimePicker::make('sla_due_at')
                            ->label('Prazo (ANS)')
                            ->nullable()
                            ->displayFormat('d/m/Y H:i'),

                        Forms\Components\DateTimePicker::make('completed_at')
                            ->label('Concluída em')
                            ->nullable()
                            ->displayFormat('d/m/Y H:i'),
                    ])->columns(2),

                Forms\Components\Section::make('Campos do Processo')
                    ->schema(fn (Get $get) => static::buildDynamicFieldSchema($get('entity_id')))
                    ->columns(2)
                    ->visible(fn (Get $get) => filled($get('entity_id')))
                    ->description('Campos específicos configurados para este processo'),

                Forms\Components\Hidden::make('created_by')
                    ->default(fn () => auth()->id()),
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

        $fields = \App\Models\CustomField::where('entity_id', $entityId)
            ->orderBy('field_order')
            ->get();

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

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(DemandStatusEnum::options()),

                Tables\Filters\SelectFilter::make('priority')
                    ->label('Prioridade')
                    ->options(DemandPriorityEnum::options()),
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
            RelationManagers\CommentsRelationManager::class,
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
