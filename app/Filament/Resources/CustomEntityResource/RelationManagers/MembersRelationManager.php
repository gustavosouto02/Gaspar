<?php

namespace App\Filament\Resources\CustomEntityResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Enums\ProcessStatusColorEnum;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $title = 'Papéis no Processo';

    protected static ?string $modelLabel = 'Papel';

    protected static ?string $pluralModelLabel = 'Papéis';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('Usuário')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->placeholder('Selecione o usuário...'),

                Forms\Components\Select::make('process_role_id')
                    ->label('Papel no Processo')
                    ->relationship('processRole', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->placeholder('Selecione o papel...')
                    ->helperText('Ex: Aprovador, Demandante, Validador'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('user.name')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuário')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.user_role')
                    ->label('Perfil do Sistema')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('processRole.name')
                    ->label('Papel no Processo')
                    ->badge()
                    ->color(fn ($record) => $record->processRole
                        ? ProcessStatusColorEnum::tryFrom($record->processRole->color)?->filamentColor() ?? 'gray'
                        : 'gray')
                    ->sortable(),
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
