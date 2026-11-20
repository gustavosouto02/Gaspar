<?php

namespace App\Filament\Resources;

use App\Enums\UserRoleEnum;
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
use Filament\Navigation\NavigationItem;
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

    protected static ?string $navigationGroup = 'Cadastros';

    protected static ?string $modelLabel = 'Macroprocesso';

    protected static ?string $pluralModelLabel = 'Macroprocessos';

    public static function getNavigationItems(): array
    {
        $label = static::getNavigationLabel();

        $items = [
            NavigationItem::make($label)
                ->group('Cadastros')
                ->icon(static::getNavigationIcon())
                ->sort(5)
                ->url(static::getUrl('index'))
                ->isActiveWhen(fn () => request()->routeIs(static::getRouteBaseName().'.*')),
        ];

        if (auth()->user()?->user_role === UserRoleEnum::ADMIN) {
            $items[] = NavigationItem::make($label)
                ->group('Administração')
                ->icon(static::getNavigationIcon())
                ->sort(2)
                ->url(static::getUrl('index'))
                ->isActiveWhen(fn () => request()->routeIs(static::getRouteBaseName().'.*'));
        }

        return $items;
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
                    ->options([
                        'Suportar Processos' => 'Suportar Processos',
                        'Definir Diretrizes e Estratégia' => 'Definir Diretrizes e Estratégia',
                        'Entregar Produtos e Serviços' => 'Entregar Produtos e Serviços',
                        'Manter Relacionamentos' => 'Manter Relacionamentos',
                    ])
                    ->in(fn (Select $component): array => array_keys($component->getOptions())),

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
                        Infolists\Components\RepeatableEntry::make('processes')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->label('')
                                    ->formatStateUsing(fn ($record) => $record->full_display_name)
                                    ->url(fn ($record) => auth()->user()?->user_role === UserRoleEnum::ADMIN ? CustomEntityResource::getUrl('edit', ['record' => $record]) : null)
                                    ->color(fn () => auth()->user()?->user_role === UserRoleEnum::ADMIN ? 'primary' : 'gray'),
                            ])
                            ->grid(1)
                            ->columnSpanFull(),
                    ]),
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
