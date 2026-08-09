<?php

namespace App\Filament\Resources;

use App\Enums\ProcessStatusColorEnum;
use App\Enums\UserRoleEnum;
use App\Filament\Resources\ProcessStatusResource\Pages;
use App\Models\ProcessStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProcessStatusResource extends Resource
{
    protected static ?string $model = ProcessStatus::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Situações';

    protected static ?string $modelLabel = 'Situação';

    protected static ?string $pluralModelLabel = 'Situações';

    protected static ?string $navigationGroup = 'Administração';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return auth()->user()?->user_role === UserRoleEnum::ADMIN;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome da Situação')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Ex: Nova, Em Orçamento, Quitada'),

                Forms\Components\Select::make('color')
                    ->label('Cor')
                    ->required()
                    ->options(ProcessStatusColorEnum::options())
                    ->default('gray')
                    ->native(false),

                Forms\Components\Hidden::make('created_by')
                    ->default(fn () => auth()->id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Situação')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('color')
                    ->label('Cor')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ProcessStatusColorEnum::options()[$state] ?? $state)
                    ->color(fn (string $state) => ProcessStatusColorEnum::from($state)->filamentColor()),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Criado por')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_system')
                    ->label('Padrão do Sistema')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-pencil')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->tooltip(fn ($state) => $state ? 'Situação obrigatória e fixa' : 'Situação customizada'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->hidden(fn (ProcessStatus $record) => $record->is_system),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (ProcessStatus $record) => $record->is_system),
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
            'index'  => Pages\ListProcessStatuses::route('/'),
            'create' => Pages\CreateProcessStatus::route('/create'),
            'edit'   => Pages\EditProcessStatus::route('/{record}/edit'),
        ];
    }
}
