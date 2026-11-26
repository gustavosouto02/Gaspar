<?php

namespace App\Filament\Resources\DemandResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'Comentários';

    protected static ?string $modelLabel = 'Comentário';

    protected static ?string $pluralModelLabel = 'Comentários';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Textarea::make('comment')
                    ->label('Relato')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_internal')
                    ->label('Comentário interno (não visível ao cliente)')
                    ->default(false),

                Forms\Components\Hidden::make('user_id')
                    ->default(fn () => auth()->id()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('comment')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Relator')
                    ->sortable(),

                Tables\Columns\TextColumn::make('comment')
                    ->label('Relato')
                    ->limit(120)
                    ->wrap(),

                Tables\Columns\IconColumn::make('is_internal')
                    ->label('Interno')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-globe-alt')
                    ->trueColor('warning')
                    ->falseColor('success'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data/Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Adicionar Relato'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([]);
    }
}
