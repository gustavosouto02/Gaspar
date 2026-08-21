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

    public static function getTypeId(): ?string
    {
        if ($typeId = request()->query('type_id')) {
            return $typeId;
        }

        if (request()->header('referer')) {
            parse_str(parse_url(request()->header('referer'), PHP_URL_QUERY) ?? '', $query);
            if (isset($query['type_id'])) {
                return $query['type_id'];
            }
        }

        return null;
    }

    public static function getModelLabel(): string
    {
        if ($typeId = static::getTypeId()) {
            $type = \App\Models\CustomRecordType::find($typeId);
            if ($type) {
                return $type->name; // Poderia usar Str::singular($type->name)
            }
        }
        return 'Registro';
    }

    public static function getPluralModelLabel(): string
    {
        if ($typeId = static::getTypeId()) {
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
        if ($typeId = static::getTypeId()) {
            $query->where('custom_record_type_id', $typeId);
        }
        return $query;
    }

    public static function form(Form $form): Form
    {
        $livewire = $form->getLivewire();
        $typeId = property_exists($livewire, 'type_id') ? $livewire->type_id : static::getTypeId();
        
        if (! $typeId && $form->getRecord()) {
            $typeId = $form->getRecord()->custom_record_type_id;
        }

        $schema = [];
        if ($typeId) {
            $type = \App\Models\CustomRecordType::with('fields')->find($typeId);
            if ($type) {
                foreach ($type->fields as $field) {
                    $key = "data_json.{$field->key}";
                    $component = match ($field->field_type) {
                        \App\Enums\FieldTypeEnum::TEXT     => Forms\Components\TextInput::make($key),
                        \App\Enums\FieldTypeEnum::TEXTAREA => Forms\Components\Textarea::make($key)->rows(3),
                        \App\Enums\FieldTypeEnum::NUMBER   => Forms\Components\TextInput::make($key)->numeric(),
                        \App\Enums\FieldTypeEnum::DATE     => Forms\Components\DatePicker::make($key),
                        \App\Enums\FieldTypeEnum::SELECT   => Forms\Components\Select::make($key)
                            ->options($field->options_json
                                ? array_combine($field->options_json, $field->options_json)
                                : []),
                        \App\Enums\FieldTypeEnum::RADIO    => Forms\Components\Radio::make($key)
                            ->options($field->options_json
                                ? array_combine($field->options_json, $field->options_json)
                                : []),
                        \App\Enums\FieldTypeEnum::CHECKBOX => Forms\Components\Toggle::make($key),
                        \App\Enums\FieldTypeEnum::EMAIL    => Forms\Components\TextInput::make($key)->email(),
                        default                            => Forms\Components\TextInput::make($key),
                    };

                    $component->label($field->name)
                              ->required($field->is_required);

                    if (method_exists($component, 'placeholder') && $field->placeholder) {
                        $component->placeholder($field->placeholder);
                    }

                    if ($field->default_value) {
                        $component->default($field->default_value);
                    }

                    $schema[] = $component;
                }
            }
        }

        return $form->schema($schema);
    }

    public static function table(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $typeId = property_exists($livewire, 'type_id') ? $livewire->type_id : static::getTypeId();
        
        $columns = [];

        if ($typeId) {
            $type = \App\Models\CustomRecordType::with('fields')->find($typeId);
            if ($type) {
                foreach ($type->fields->take(4) as $field) {
                    $columns[] = Tables\Columns\TextColumn::make("data_json.{$field->key}")
                        ->label($field->name)
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
                Tables\Actions\ViewAction::make()
                    ->url(fn (\App\Models\CustomRecord $record) => static::getUrl('view', ['record' => $record, 'type_id' => $record->custom_record_type_id])),
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
            'view' => Pages\ViewCustomRecord::route('/{record}'),
            'edit' => Pages\EditCustomRecord::route('/{record}/edit'),
        ];
    }

    public static function getUrl(string $name = 'index', array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?\Illuminate\Database\Eloquent\Model $tenant = null): string
    {
        if (! isset($parameters['type_id']) && ($typeId = static::getTypeId())) {
            $parameters['type_id'] = $typeId;
        }

        return parent::getUrl($name, $parameters, $isAbsolute, $panel, $tenant);
    }
}
