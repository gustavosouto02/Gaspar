<?php

namespace App\Filament\Resources\CustomEntityResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'fields';

    protected static ?string $title = 'Campos Customizados';

    protected static ?string $modelLabel = 'Campo';

    protected static ?string $pluralModelLabel = 'Campos';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
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
                    ->helperText('Apenas para campos do tipo Caixa de Seleção.')
                    ->visible(fn (Forms\Get $get) => $get('field_type') === \App\Enums\FieldTypeEnum::SELECT->value)
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('field_order')
                    ->label('Ordem de Exibição')
                    ->numeric()
                    ->default(0)
                    ->required(),

                Forms\Components\Hidden::make('created_by')
                    ->default(fn () => auth()->id()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
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

                Tables\Columns\TextColumn::make('field_order')
                    ->numeric()
                    ->sortable()
                    ->label('Ordem'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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
}
