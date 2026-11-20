<?php

namespace App\Filament\Resources;

use App\Enums\UserRoleEnum;
use App\Filament\Resources\Concerns\HasManagerField;
use App\Filament\Resources\CustomEntityResource\RelationManagers\FieldsRelationManager;
use App\Filament\Resources\CustomRecordTypeResource\Pages;
use App\Models\CustomRecordType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CustomRecordTypeResource extends Resource
{
    use HasManagerField;

    protected static ?string $model = CustomRecordType::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Administração';

    protected static ?string $navigationLabel = 'Criar Cadastros';

    protected static ?string $modelLabel = 'Cadastro';

    protected static ?string $pluralModelLabel = 'Cadastros';

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        return auth()->user()?->user_role === UserRoleEnum::ADMIN;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Placeholder::make('code_placeholder')
                    ->label('Número')
                    ->content(fn ($record) => $record?->code ?? 'NOVO')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('name')
                    ->label('Nome (Ex: Produtos)')
                    ->required()
                    ->maxLength(fn (?CustomRecordType $record, ?string $state) => ($record && $record->name === $state) ? 255 : 15)
                    ->helperText(fn (?CustomRecordType $record) => $record?->is_system
                        ? 'Máximo de 15 caracteres. Altera o nome no menu e na listagem.'
                        : 'Máximo de 15 caracteres.')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Forms\Set $set, ?string $state, ?CustomRecordType $record) {
                        if (! $record?->is_system) {
                            $set('slug', Str::slug($state));
                        }
                    }),
                Forms\Components\TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->disabled(fn ($record) => $record?->is_system),
                static::managersSelect()
                    ->helperText('Vale para todos os registros deste cadastro. Pode haver mais de um gestor.'),
                Forms\Components\Toggle::make('is_active')
                    ->label('Ativo')
                    ->default(true)
                    ->disabled(fn ($record) => $record?->is_system),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Número')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\TextColumn::make('managers.name')
                    ->label('Gestores')
                    ->badge()
                    ->placeholder('Sem gestor'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('model_class')
                    ->label('Tipo')
                    ->formatStateUsing(fn ($state) => $state ? 'Sistema' : 'Customizado')
                    ->badge()
                    ->color(fn ($state) => $state ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record) => ! $record->is_system),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            FieldsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomRecordTypes::route('/'),
            'create' => Pages\CreateCustomRecordType::route('/create'),
            'edit' => Pages\EditCustomRecordType::route('/{record}/edit'),
        ];
    }
}
