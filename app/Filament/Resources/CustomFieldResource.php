<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomFieldResource\Pages;
use App\Models\CustomField;
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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Configuração do Campo')->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome do Campo')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => 
                            $operation === 'create' ? $set('key', str($state)->slug('_')->toString()) : null
                        ),

                    Forms\Components\TextInput::make('key')
                        ->required()
                        ->maxLength(255)
                        ->regex('/^[a-z0-9_]+$/')
                        ->label('Chave (slug)')
                        ->helperText('Ex: destino_viagem. Apenas minúsculas e sublinhados.'),

                    Forms\Components\Select::make('field_type')
                        ->options(\App\Enums\FieldTypeEnum::options())
                        ->required()
                        ->live()
                        ->label('Tipo do Campo'),

                    Forms\Components\TextInput::make('placeholder')
                        ->maxLength(255),

                    Forms\Components\Toggle::make('is_required')
                        ->label('Obrigatório')
                        ->default(false),

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

                    Forms\Components\Hidden::make('created_by')
                        ->default(fn () => auth()->id()),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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

                Tables\Columns\IconColumn::make('is_required')
                    ->boolean()
                    ->sortable()
                    ->label('Obrigatório'),

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
