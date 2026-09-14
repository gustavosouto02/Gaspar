<?php

namespace App\Filament\Resources\Concerns;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Forms\Form;
use App\Models\CustomField;
use App\Models\CustomRecordType;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic RelationManager for managing custom fields on permanent registration models.
 * 
 * This works by finding the CustomRecordType associated with the owner model's class,
 * and managing the fields relationship through that record type's pivot table.
 */
class CustomFieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'customFieldsViaType';

    protected static ?string $title = 'Campos Customizados';

    protected static ?string $modelLabel = 'Campo';

    protected static ?string $pluralModelLabel = 'Campos';

    /**
     * Override to get the correct record type for this model.
     */
    protected function getRecordType(): ?CustomRecordType
    {
        $ownerModel = $this->getOwnerRecord();
        return CustomRecordType::where('model_class', get_class($ownerModel))
            ->where('is_system', true)
            ->first();
    }

    /**
     * Override the table query to use the record type's fields relationship.
     */
    protected function getTableQuery(): ?\Illuminate\Database\Eloquent\Builder
    {
        $recordType = $this->getRecordType();
        if (! $recordType) {
            return CustomField::query()->whereRaw('1 = 0'); // empty result
        }

        return $recordType->fields()->getQuery();
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('key')
                    ->searchable()
                    ->sortable()
                    ->label('Chave (slug)'),

                Tables\Columns\TextColumn::make('field_type')
                    ->badge()
                    ->label('Tipo'),

                Tables\Columns\IconColumn::make('is_required')
                    ->boolean()
                    ->sortable()
                    ->label('Obrigatório'),
            ])
            ->defaultSort('field_order')
            ->reorderable('field_order')
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\Action::make('attach')
                    ->label('Vincular')
                    ->icon('heroicon-o-link')
                    ->form([
                        Forms\Components\Select::make('custom_field_id')
                            ->label('Campo')
                            ->options(function () {
                                $recordType = $this->getRecordType();
                                if (! $recordType) {
                                    return [];
                                }
                                $existingIds = $recordType->fields()->pluck('custom_fields.id')->toArray();
                                return CustomField::whereNotIn('id', $existingIds)
                                    ->pluck('name', 'id');
                            })
                            ->required()
                            ->searchable()
                            ->preload(),
                    ])
                    ->action(function (array $data) {
                        $recordType = $this->getRecordType();
                        if ($recordType) {
                            $maxOrder = \Illuminate\Support\Facades\DB::table('custom_record_type_custom_field')
                                ->where('custom_record_type_id', $recordType->id)
                                ->max('field_order') ?? 0;

                            $recordType->fields()->attach($data['custom_field_id'], [
                                'field_order' => $maxOrder + 1,
                            ]);
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('detach')
                    ->label('Desvincular')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Model $record) {
                        $recordType = $this->getRecordType();
                        if ($recordType) {
                            $recordType->fields()->detach($record->id);
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('detach_bulk')
                        ->label('Desvincular selecionados')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $recordType = $this->getRecordType();
                            if ($recordType) {
                                $recordType->fields()->detach($records->pluck('id'));
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
