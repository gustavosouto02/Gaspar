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

                Forms\Components\Textarea::make('description')
                    ->label('Descrição')
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_active')
                    ->label('Ativo')
                    ->default(true),

                Forms\Components\Hidden::make('created_by')
                    ->default(fn () => auth()->id()),
            ]);
    }

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Section::make('PAPEL')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('id')
                            ->label('Papel número'),
                        \Filament\Infolists\Components\TextEntry::make('created_at')
                            ->label('Data de criação')
                            ->dateTime('d/m/Y'),
                        \Filament\Infolists\Components\TextEntry::make('name')
                            ->label('Nome do Papel'),
                        \Filament\Infolists\Components\TextEntry::make('is_active')
                            ->label('Situação')
                            ->formatStateUsing(fn ($state) => $state ? 'ATIVO' : 'INATIVO'),
                        \Filament\Infolists\Components\TextEntry::make('description')
                            ->label('Descrição')
                            ->columnSpanFull(),
                    ])->columns(2),

                \Filament\Infolists\Components\Section::make('Processos que utilizam este papel:')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('members')
                            ->label('')
                            ->formatStateUsing(function ($record) {
                                $entities = $record->members->map(fn($m) => $m->entity)->filter()->unique('id');
                                if ($entities->isEmpty()) return 'Nenhum processo utiliza este papel.';
                                return $entities->map(fn($e) => "<a href='" . \App\Filament\Resources\CustomEntityResource::getUrl('edit', ['record' => $e]) . "' class='text-primary-600 underline'>{$e->id} - {$e->name}</a>")->implode('<br>');
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),
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
                
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),

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
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->recordUrl(fn (ProcessRole $record): string => Pages\ViewProcessRole::getUrl(['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProcessRoles::route('/'),
            'create' => Pages\CreateProcessRole::route('/create'),
            'view'   => Pages\ViewProcessRole::route('/{record}'),
            'edit'   => Pages\EditProcessRole::route('/{record}/edit'),
        ];
    }
}
