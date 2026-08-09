<?php

namespace App\Filament\Resources;

use App\Enums\ProcessStatusColorEnum;
use App\Enums\UserRoleEnum;
use App\Filament\Resources\ProcessRoleResource\Pages;
use App\Models\ProcessRole;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProcessRoleResource extends Resource
{
    protected static ?string $model = ProcessRole::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Papéis';

    protected static ?string $modelLabel = 'Papel';

    protected static ?string $pluralModelLabel = 'Papéis';

    protected static ?string $navigationGroup = 'Administração';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->user_role === UserRoleEnum::ADMIN;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome do Papel')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Ex: Aprovador, Demandante, Validador'),

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
                    ->label('Nome')
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
            'index'  => Pages\ListProcessRoles::route('/'),
            'create' => Pages\CreateProcessRole::route('/create'),
            'edit'   => Pages\EditProcessRole::route('/{record}/edit'),
        ];
    }
}
