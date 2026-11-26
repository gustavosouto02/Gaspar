<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomFieldResource\Pages;
use App\Models\CustomField;
use App\Services\LookupResolver;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomFieldResource extends Resource
{
    protected static ?string $model = CustomField::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Criar Campos';

    protected static ?string $modelLabel = 'Campo';

    protected static ?string $pluralModelLabel = 'Campos';

    protected static ?string $navigationGroup = 'Administração';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return auth()->user()?->user_role === \App\Enums\UserRoleEnum::ADMIN;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Configuração do Campo')->schema([
                    Forms\Components\Placeholder::make('code_placeholder')
                        ->label('Número')
                        ->content(fn ($record) => $record?->code ?? 'NOVO')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('name')
                        ->label('Nome do Campo')
                        ->required()
                        ->maxLength(255)
                        // A chave do campo é gerada do nome, que precisa ter algo aproveitável.
                        ->regex('/[\pL\pN]/u')
                        ->validationMessages(['regex' => 'O nome do campo deve conter letras ou números.']),

                    Forms\Components\Select::make('field_type')
                        ->options(\App\Enums\FieldTypeEnum::options())
                        ->required()
                        ->live()
                        // Zera só quando o estado não é uma lista (config do Combo ou null);
                        // Caixa de Seleção e Rádio mantêm as opções.
                        ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                            $options = $get('options_json');
                            if (! is_array($options) || ! array_is_list($options)) {
                                $set('options_json', []);
                            }
                        })
                        ->disabled(fn (?CustomField $record) => (bool) $record?->hasStoredValues())
                        ->dehydrated()
                        ->helperText(fn (?CustomField $record) => $record?->hasStoredValues()
                            ? 'Campo em uso: o tipo não pode ser alterado. Crie um novo campo.'
                            : null)
                        ->label('Tipo do Campo'),

                    Forms\Components\TextInput::make('placeholder')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('default_value')
                        ->label('Valor Padrão')
                        ->maxLength(65535)
                        ->visible(fn (Forms\Get $get) => $get('field_type') !== \App\Enums\FieldTypeEnum::COMBO->value),

                    Forms\Components\TagsInput::make('options_json')
                        ->label('Opções')
                        ->placeholder('Escreva uma opção e aperte Enter')
                        ->helperText('Apenas para campos do tipo Caixa de Seleção ou Botão de Rádio.')
                        ->visible(fn (Forms\Get $get) => in_array($get('field_type'), [
                            \App\Enums\FieldTypeEnum::SELECT->value,
                            \App\Enums\FieldTypeEnum::RADIO->value,
                        ]))
                        ->columnSpanFull(),

                    Forms\Components\Select::make('lookup_source')
                        ->label('Origem do vínculo')
                        ->options(LookupResolver::sourceOptions())
                        ->required()
                        ->disabled(fn (?CustomField $record) => (bool) $record?->hasStoredValues())
                        ->dehydrated()
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('lookup_record_type_id', null))
                        ->helperText(fn (?CustomField $record) => $record?->hasStoredValues()
                            ? 'Campo em uso: a origem não pode ser alterada. Crie um novo campo com a origem certa.'
                            : 'Cadastro de onde o usuário vai escolher o registro (seleção única).')
                        ->visible(fn (Forms\Get $get) => $get('field_type') === \App\Enums\FieldTypeEnum::COMBO->value),

                    Forms\Components\Select::make('lookup_record_type_id')
                        ->label('Cadastro')
                        ->options(fn (Forms\Get $get) => \App\Models\CustomRecordType::where('is_system', false)
                            ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $get('lookup_record_type_id')))
                            ->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->required()
                        ->disabled(fn (?CustomField $record) => (bool) $record?->hasStoredValues())
                        ->dehydrated()
                        ->visible(fn (Forms\Get $get) => $get('field_type') === \App\Enums\FieldTypeEnum::COMBO->value
                            && $get('lookup_source') === LookupResolver::RECORD_TYPE),

                    Forms\Components\Hidden::make('created_by')
                        ->default(fn () => auth()->id()),
                ])->columns(2),
            ]);
    }

    /**
     * Vínculo: os selects da tela são campos planos (lookup_*) e viram o
     * options_json do campo ao salvar. Um caminho aninhado dentro de
     * options_json colidiria com o TagsInput das opções no navegador.
     */
    public static function packLookup(array $data): array
    {
        if (($data['field_type'] ?? null) === \App\Enums\FieldTypeEnum::COMBO->value) {
            $source = $data['lookup_source'] ?? null;
            $data['options_json'] = array_filter([
                'source' => $source,
                'record_type_id' => $source === LookupResolver::RECORD_TYPE ? ($data['lookup_record_type_id'] ?? null) : null,
            ]);
            $data['default_value'] = null;
        } elseif (! in_array($data['field_type'] ?? null, [
            \App\Enums\FieldTypeEnum::SELECT->value,
            \App\Enums\FieldTypeEnum::RADIO->value,
        ], true)) {
            // Tipos sem opções não carregam config de outro tipo (ex.: origem do Combo)
            $data['options_json'] = null;
        }

        unset($data['lookup_source'], $data['lookup_record_type_id']);

        return $data;
    }

    public static function unpackLookup(array $data): array
    {
        if (($data['field_type'] ?? null) === \App\Enums\FieldTypeEnum::COMBO->value) {
            $data['lookup_source'] = $data['options_json']['source'] ?? null;
            $data['lookup_record_type_id'] = $data['options_json']['record_type_id'] ?? null;
            $data['options_json'] = [];
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

                Tables\Columns\TextColumn::make('name')
                    ->searchable(['name', 'key'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('field_type')
                    ->badge()
                    ->label('Tipo'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomFields::route('/'),
            'create' => Pages\CreateCustomField::route('/create'),
            'edit' => Pages\EditCustomField::route('/{record}/edit'),
        ];
    }
}
