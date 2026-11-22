<?php

namespace App\Filament\Resources;

use App\Enums\UserRoleEnum;
use App\Filament\Resources\Concerns\HasManagerField;
use App\Filament\Resources\CustomEntityResource\Pages;
use App\Filament\Resources\CustomEntityResource\RelationManagers;
use App\Models\CustomEntity;
use App\Services\ProcessVersioningService;
use Filament\Actions\MountableAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CustomEntityResource extends Resource
{
    use HasManagerField;

    protected static ?string $model = CustomEntity::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Processos';

    protected static ?string $modelLabel = 'Processo';

    protected static ?string $pluralModelLabel = 'Processos';

    protected static ?string $navigationGroup = 'Administração';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->user()?->user_role === UserRoleEnum::ADMIN;
    }

    public static function form(Form $form): Form
    {
        return $form
            // Versão antiga: nem os metadados são editáveis (só leitura)
            ->disabled(fn (?CustomEntity $record) => $record !== null && ! $record->is_current)
            ->schema([
                Forms\Components\Placeholder::make('id_placeholder')
                    ->label('Número')
                    ->content(fn ($record) => $record?->display_code ?? 'NOVO'),

                // Preenchida pelo sistema: nunca é um campo editável nem entra no payload
                Forms\Components\Placeholder::make('version_placeholder')
                    ->label('Versão')
                    ->content(fn ($record) => $record?->version_label ?? 'v1'),

                Forms\Components\Placeholder::make('created_at_placeholder')
                    ->label('Data de criação')
                    ->content(fn ($record) => $record?->created_at ? $record->created_at->format('d/m/Y') : '-'),

                Forms\Components\TextInput::make('name')
                    ->label('Nome do Processo')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('macroprocess_id')
                    ->label('Macroprocesso do qual faz parte')
                    ->relationship('macroprocess', 'name')
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => $record->full_display_name)
                    ->searchable(['name', 'acronym'])
                    ->preload()
                    ->required(),

                static::managerSelect(),

                Forms\Components\Toggle::make('is_active')
                    ->label('Situação (Ativo)')
                    ->required()
                    ->default(true),

                Forms\Components\Select::make('purpose')
                    ->label('Finalidade')
                    ->required()
                    ->options([
                        'Apoio' => 'Apoio',
                        'Finalístico' => 'Finalístico',
                        'Gerencial' => 'Gerencial',
                    ]),

                Forms\Components\TextInput::make('sla_hours')
                    ->label('Prazo de atendimento (horas)')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->placeholder('Ex: 48')
                    ->helperText('Prazo padrão em horas para demandas deste processo. Será calculado automaticamente ao criar a demanda.'),

                Forms\Components\Textarea::make('description')
                    ->label('Descrição')
                    ->required()
                    ->maxLength(65535)
                    ->columnSpanFull(),

                Forms\Components\Hidden::make('created_by')
                    ->default(fn () => auth()->id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Número')
                    ->formatStateUsing(fn (CustomEntity $record) => $record->display_code)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nome do Processo')
                    ->formatStateUsing(fn (CustomEntity $record) => $record->full_display_name)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição')
                    ->limit(50)
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('manager.name')
                    ->label('Gestor')
                    ->placeholder('Sem gestor')
                    ->sortable(),

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
                Tables\Actions\DeleteAction::make()
                    ->before(fn (Tables\Actions\DeleteAction $action, CustomEntity $record) => static::haltIfDeletionBlocked($action, [$record])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(fn (Tables\Actions\DeleteBulkAction $action, Collection $records) => static::haltIfDeletionBlocked($action, $records)),
                ]),
            ])
            ->recordUrl(fn (CustomEntity $record): string => Pages\ViewCustomEntity::getUrl(['record' => $record]));
    }

    /**
     * Trava de exclusão (regra do PO): a versão só pode ser apagada quando todas as
     * demandas dela estiverem encerradas (CLOSED, CANCELED, EVALUATED; rascunho é pendente).
     * O Model também recusa (deleting), então vale para qualquer caminho; aqui só
     * trocamos a exceção por um aviso amigável. Em lote, uma versão bloqueada cancela todas.
     *
     * @param  iterable<CustomEntity>  $records
     */
    public static function haltIfDeletionBlocked(MountableAction $action, iterable $records): void
    {
        $service = app(ProcessVersioningService::class);

        $blocked = collect($records)->filter(fn (CustomEntity $record) => $service->hasPendingDemands($record));

        if ($blocked->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('Exclusão bloqueada')
            ->body($blocked->count() === 1
                ? ProcessVersioningService::DELETION_BLOCKED_MESSAGE
                : 'Estas versões de processo ainda têm demandas não encerradas: '.$blocked->map(fn (CustomEntity $r) => "{$r->full_display_name}")->implode(', ').'.')
            ->danger()
            ->send();

        $action->cancel();
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\FieldsRelationManager::class,
            RelationManagers\MembersRelationManager::class,
            RelationManagers\ProcessStatusesRelationManager::class,
            RelationManagers\StatusTransitionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomEntities::route('/'),
            'create' => Pages\CreateCustomEntity::route('/create'),
            // Antes de 'view': a rota '/{record}' capturaria este caminho
            'inactive-versions' => Pages\ListInactiveCustomEntityVersions::route('/versoes-inativas'),
            'view' => Pages\ViewCustomEntity::route('/{record}'),
            'edit' => Pages\EditCustomEntity::route('/{record}/edit'),
            'permissions' => Pages\ManageFieldPermissions::route('/{record}/permissoes'),
        ];
    }
}
