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
                        ->live(debounce: 500)
                        ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => 
                            $operation === 'create' ? $set('key', str($state)->slug('_')->toString()) : null
                        ),

                    Forms\Components\TextInput::make('key')
                        ->label('Chave (slug)')
                        ->disabled()
                        ->dehydrated()
                        ->required()
                        ->maxLength(255)
                        ->regex('/^[a-z0-9_]+$/')
                        ->helperText('Gerado automaticamente a partir do nome do campo.'),

                    Forms\Components\Select::make('field_type')
                        ->options(\App\Enums\FieldTypeEnum::options())
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('options_json', []))
                        ->label('Tipo do Campo'),

                    Forms\Components\TextInput::make('placeholder')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('default_value')
                        ->label('Valor Padrão')
                        ->maxLength(65535),

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
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('lookup_record_type_id', null))
                        ->helperText('Cadastro de onde o usuário vai escolher o registro (seleção única).')
                        ->visible(fn (Forms\Get $get) => $get('field_type') === \App\Enums\FieldTypeEnum::COMBO->value),

                    Forms\Components\Select::make('lookup_record_type_id')
                        ->label('Cadastro')
                        ->options(fn () => \App\Models\CustomRecordType::where('is_system', false)->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->required()
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
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('key')
                    ->searchable()
                    ->sortable()
                    ->label('Chave (slug)'),

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
