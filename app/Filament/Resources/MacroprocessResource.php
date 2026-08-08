<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MacroprocessResource\Pages;
use App\Filament\Resources\MacroprocessResource\RelationManagers;
use App\Models\Macroprocess;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MacroprocessResource extends Resource
{
    protected static ?string $model = Macroprocess::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Administração';
    protected static ?string $modelLabel = 'Macroprocesso';
    protected static ?string $pluralModelLabel = 'Macroprocessos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                \Filament\Forms\Components\Placeholder::make('id_placeholder')
                    ->label('Número do macroprocesso')
                    ->content(fn ($record) => $record?->id ?? '-'),
                
                \Filament\Forms\Components\Placeholder::make('created_at_placeholder')
                    ->label('Data de criação')
                    ->content(fn ($record) => $record?->created_at ? $record->created_at->format('d/m/Y') : '-'),

                \Filament\Forms\Components\TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),

                \Filament\Forms\Components\TextInput::make('acronym')
                    ->label('Sigla')
                    ->maxLength(255),

                \Filament\Forms\Components\Select::make('value_chain_function')
                    ->label('Função na Cadeia de Valor')
                    ->options([
                        'Suportar Processos' => 'Suportar Processos',
                        'Definir Diretrizes e Estratégia' => 'Definir Diretrizes e Estratégia',
                        'Entregar Produtos e Serviços' => 'Entregar Produtos e Serviços',
                        'Manter Relacionamentos' => 'Manter Relacionamentos',
                    ]),

                \Filament\Forms\Components\Toggle::make('is_active')
                    ->label('Ativo')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                \Filament\Tables\Columns\TextColumn::make('acronym')
                    ->label('Sigla')
                    ->searchable(),
                \Filament\Tables\Columns\TextColumn::make('value_chain_function')
                    ->label('Função na Cadeia de Valor'),
                \Filament\Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),
                \Filament\Tables\Columns\TextColumn::make('created_at')
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageMacroprocesses::route('/'),
        ];
    }
}
