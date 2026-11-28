<?php

namespace App\Filament\Resources;

use App\Enums\UserRoleEnum;
use App\Enums\ValueChainFunctionEnum;
use App\Filament\Resources\Concerns\CustomFieldsRelationManager;
use App\Filament\Resources\Concerns\HasManagerField;
use App\Filament\Resources\Concerns\HasRecordTypeLabel;
use App\Filament\Resources\MacroprocessResource\Pages;
use App\Models\Macroprocess;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MacroprocessResource extends Resource
{
    use HasManagerField, HasRecordTypeLabel;

    protected static ?string $model = Macroprocess::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Administração';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Macroprocesso';

    protected static ?string $pluralModelLabel = 'Macroprocessos';

    /**
     * No menu só para o admin, que mantém os macroprocessos. Os demais chegam a eles
     * pela Cadeia de Valor no Painel de Controle.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->user_role === UserRoleEnum::ADMIN;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->user_role === UserRoleEnum::ADMIN;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->user_role === UserRoleEnum::ADMIN;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->user_role === UserRoleEnum::ADMIN;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Placeholder::make('id_placeholder')
                    ->label('Número')
                    ->content(fn ($record) => $record?->code ?? 'NOVO'),

                Placeholder::make('created_at_placeholder')
                    ->label('Data de criação')
                    ->content(fn ($record) => $record?->created_at ? $record->created_at->format('d/m/Y') : '-'),

                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),

                TextInput::make('acronym')
                    ->label('Sigla')
                    ->required()
                    ->maxLength(255),

                Select::make('value_chain_function')
                    ->label('Função na Cadeia de Valor')
                    ->required()
                    ->options(ValueChainFunctionEnum::options())
                    ->in(array_keys(ValueChainFunctionEnum::options())),

                static::managerSelect(),

                Textarea::make('description')
                    ->label('Descrição')
                    ->required()
                    ->columnSpanFull(),

                Toggle::make('is_active')
                    ->label('Ativo')
                    ->required()
                    ->default(true),

                ...array_filter([Macroprocess::buildCustomFieldComponents()]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('MACROPROCESSO')
                    ->schema([
                        Infolists\Components\TextEntry::make('code')
                            ->label('Número'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Data de criação')
                            ->dateTime('d/m/Y'),
                        Infolists\Components\TextEntry::make('name')
                            ->label('Nome do Macroprocesso')
                            ->formatStateUsing(fn ($record) => $record->full_display_name),
                        Infolists\Components\TextEntry::make('acronym')
                            ->label('Sigla do Macroprocesso'),
                        Infolists\Components\TextEntry::make('is_active')
                            ->label('Situação')
                            ->formatStateUsing(fn ($state) => $state ? 'ATIVO' : 'INATIVO'),
                        Infolists\Components\TextEntry::make('value_chain_function')
                            ->label('Função na Cadeia de Valor'),
                        Infolists\Components\TextEntry::make('manager.name')
                            ->label('Gestor')
                            ->placeholder('Sem gestor'),

                        Infolists\Components\TextEntry::make('description')
                            ->label('Descrição')
                            ->columnSpanFull()
                            ->default('Macroprocesso compreende todos os processos vinculados.'),
                    ])
                    ->columns(1),

                Infolists\Components\Section::make('Processos agrupados neste macroprocesso:')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('currentProcesses')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->label('')
                                    ->formatStateUsing(fn ($record) => $record->full_display_name)
                                    // ADMIN vai para a edição; os demais, para a visualização somente leitura
                                    ->url(fn ($record) => CustomEntityResource::getUrl(auth()->user()?->user_role === UserRoleEnum::ADMIN ? 'edit' : 'view', ['record' => $record]))
                                    ->color('primary'),
                            ])
                            ->grid(1)
                            ->columnSpanFull(),
                    ]),

                // Versões antigas de processos (somente leitura): só para ADMIN e quando há alguma
                Infolists\Components\Section::make('Versões antigas de processos')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('inactiveProcessVersions')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->label('')
                                    ->formatStateUsing(fn ($record) => $record->full_display_name)
                                    ->url(fn ($record) => CustomEntityResource::getUrl('view', ['record' => $record]))
                                    ->color('gray'),
                            ])
                            ->grid(1)
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->visible(fn ($record) => auth()->user()?->user_role === UserRoleEnum::ADMIN
                        && $record?->inactiveProcessVersions()->exists()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Número')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Macroprocesso')
                    ->formatStateUsing(fn ($record) => $record->full_display_name)
                    ->searchable(['name', 'acronym', 'custom_data']),
                TextColumn::make('value_chain_function')
                    ->label('Função na Cadeia de Valor'),
                TextColumn::make('manager.name')
                    ->label('Gestor')
                    ->placeholder('Sem gestor')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime('d/m/Y')
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
            ->recordUrl(fn (Macroprocess $record): string => Pages\ViewMacroprocess::getUrl(['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [
            CustomFieldsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMacroprocesses::route('/'),
            'create' => Pages\CreateMacroprocess::route('/create'),
            'view' => Pages\ViewMacroprocess::route('/{record}'),
            'edit' => Pages\EditMacroprocess::route('/{record}/edit'),
        ];
    }
}
