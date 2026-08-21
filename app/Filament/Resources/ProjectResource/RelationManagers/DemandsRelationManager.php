<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DemandsRelationManager extends RelationManager
{
    protected static string $relationship = 'demands';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('Título')->searchable(),
                Tables\Columns\TextColumn::make('status')->label('Situação')->badge(),
                Tables\Columns\TextColumn::make('assignee.name')->label('Responsável'),
                Tables\Columns\TextColumn::make('created_at')->label('Data de Criação')->dateTime('d/m/Y'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\Action::make('create_demand')
                    ->label('Registrar Demanda')
                    ->icon('heroicon-o-plus')
                    ->url(fn () => \App\Filament\Resources\DemandResource::getUrl('create', ['project_id' => $this->ownerRecord->id]))
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Ver Demanda')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => \App\Filament\Resources\DemandResource::getUrl('view', ['record' => $record->id]))
            ])
            ->bulkActions([
                //
            ]);
    }
}
