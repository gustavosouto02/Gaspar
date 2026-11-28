<?php

namespace App\Filament\Resources\CustomEntityResource\RelationManagers;

use App\Enums\UserRoleEnum;
use App\Filament\Resources\Concerns\LocksProcessStructure;
use App\Models\CustomEntity;
use Filament\Forms\Form;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class FieldsRelationManager extends RelationManager
{
    use LocksProcessStructure;

    protected static string $relationship = 'fields';

    protected static ?string $title = 'Campos Customizados';

    protected static ?string $modelLabel = 'Campo';

    protected static ?string $pluralModelLabel = 'Campos';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        // Na tela de visualização o Filament exibe a aba somente leitura (sem vincular/desvincular)
        $user = auth()->user();

        if ($user?->user_role === UserRoleEnum::ADMIN) {
            return true;
        }

        // Quem não é ADMIN só consulta os campos na visualização de um processo que pode ver
        return $ownerRecord instanceof CustomEntity
            && is_subclass_of($pageClass, ViewRecord::class)
            && (bool) $user?->can('view', $ownerRecord);
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->description(fn () => $this->structureLockedNotice())
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Número')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable(['name', 'key'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('field_type')
                    ->badge()
                    ->label('Tipo'),
            ])
            ->defaultSort('field_order')
            ->reorderable('field_order', fn (): bool => ! $this->isReadOnly())
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('Vincular Campo')
                    ->color('primary')
                    ->modalHeading('Vincular Campo')
                    ->modalSubmitActionLabel('Vincular')
                    ->preloadRecordSelect(),
            ])
            ->actions([
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
