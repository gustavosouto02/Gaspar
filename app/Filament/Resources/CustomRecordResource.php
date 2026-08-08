<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomRecordResource\Pages;
use App\Filament\Resources\CustomRecordResource\RelationManagers;
use App\Models\CustomRecord;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomRecordResource extends Resource
{
    protected static ?string $model = CustomRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getModelLabel(): string
    {
        if ($typeId = request()->query('type_id')) {
            $type = \App\Models\CustomRecordType::find($typeId);
            if ($type) {
                return $type->name; // Poderia usar Str::singular($type->name)
            }
        }
        return 'Registro';
    }

    public static function getPluralModelLabel(): string
    {
        if ($typeId = request()->query('type_id')) {
            $type = \App\Models\CustomRecordType::find($typeId);
            if ($type) {
                return $type->name;
            }
        }
        return 'Registros';
    }

    public static function getNavigationItems(): array
    {
        $items = [];
        try {
            $types = \App\Models\CustomRecordType::where('is_active', true)->get();
            foreach ($types as $type) {
                $items[] = \Filament\Navigation\NavigationItem::make($type->name)
                    ->group('Cadastros')
                    ->icon('heroicon-o-document-text')
                    ->url(static::getUrl('index', ['type_id' => $type->id]))
                    ->isActiveWhen(fn () => request()->query('type_id') == $type->id);
            }
        } catch (\Exception $e) {
            // Ignora erro caso a tabela não exista ainda (durante migrations)
        }
        return $items;
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        if ($typeId = request()->query('type_id')) {
            $query->where('custom_record_type_id', $typeId);
        }
        return $query;
    }

    public static function form(Form $form): Form
    {
        // Pega o type_id da request (query ou livewire state se estiver editando)
        $typeId = request()->query('type_id');
        if (! $typeId && $form->getRecord()) {
            $typeId = $form->getRecord()->custom_record_type_id;
        }

        $schema = [];
        if ($typeId) {
            $type = \App\Models\CustomRecordType::with('fields')->find($typeId);
            if ($type) {
                foreach ($type->fields as $field) {
                    $component = match ($field->type) {
                        'text'     => Forms\Components\TextInput::make("data_json.{$field->name}"),
                        'textarea' => Forms\Components\Textarea::make("data_json.{$field->name}")->rows(3),
                        'number'   => Forms\Components\TextInput::make("data_json.{$field->name}")->numeric(),
                        'date'     => Forms\Components\DatePicker::make("data_json.{$field->name}")->native(false),
                        'datetime' => Forms\Components\DateTimePicker::make("data_json.{$field->name}")->native(false),
                        'boolean'  => Forms\Components\Toggle::make("data_json.{$field->name}"),
                        'select'   => Forms\Components\Select::make("data_json.{$field->name}")
                                        ->options(array_combine($field->options ?? [], $field->options ?? []))
                                        ->native(false),
                        default    => Forms\Components\TextInput::make("data_json.{$field->name}"),
                    };

                    $component->label($field->label)
                              ->required($field->is_required);

                    $schema[] = $component;
                }
            }
        }

        return $form->schema($schema);
    }

    public static function table(Table $table): Table
    {
        $typeId = request()->query('type_id');
        $columns = [];

        if ($typeId) {
            $type = \App\Models\CustomRecordType::with('fields')->find($typeId);
            if ($type) {
                foreach ($type->fields->take(4) as $field) { // Mostra só os 4 primeiros campos na tabela
                    $columns[] = Tables\Columns\TextColumn::make("data_json.{$field->name}")
                        ->label($field->label)
                        ->searchable()
                        ->sortable();
                }
            }
        }

        $columns[] = Tables\Columns\TextColumn::make('created_at')
            ->label('Criado em')
            ->dateTime('d/m/Y H:i')
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);

        return $table
            ->columns($columns)
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->url(fn (\App\Models\CustomRecord $record) => static::getUrl('edit', ['record' => $record, 'type_id' => $record->custom_record_type_id])),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomRecords::route('/'),
            'create' => Pages\CreateCustomRecord::route('/create'),
            'edit' => Pages\EditCustomRecord::route('/{record}/edit'),
        ];
    }
}
