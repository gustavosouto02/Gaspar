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

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Customizáveis';

    protected static ?string $modelLabel = 'Cadastramento';

    protected static ?string $pluralModelLabel = 'Customizáveis';

    protected static ?string $navigationGroup = 'Administração';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('entity_id')
                    ->relationship('entity', 'name')
                    ->required()
                    ->live()
                    ->label('Selecionar Processo')
                    ->placeholder('Selecione o processo para preenchimento...'),

                Forms\Components\Hidden::make('created_by')
                    ->default(fn () => auth()->id()),

                Forms\Components\Section::make('Campos Dinâmicos')
                    ->schema(function (Forms\Get $get) {
                        $entityId = $get('entity_id');
                        if (! $entityId) {
                            return [];
                        }

                        $fields = \App\Models\CustomField::where('entity_id', $entityId)
                            ->orderBy('field_order')
                            ->get();

                        $schema = [];
                        foreach ($fields as $field) {
                            $component = match ($field->field_type) {
                                \App\Enums\FieldTypeEnum::TEXT => Forms\Components\TextInput::make("data_json.{$field->key}"),
                                \App\Enums\FieldTypeEnum::TEXTAREA => Forms\Components\Textarea::make("data_json.{$field->key}"),
                                \App\Enums\FieldTypeEnum::NUMBER => Forms\Components\TextInput::make("data_json.{$field->key}")->numeric(),
                                \App\Enums\FieldTypeEnum::DATE => Forms\Components\DatePicker::make("data_json.{$field->key}"),
                                \App\Enums\FieldTypeEnum::SELECT => Forms\Components\Select::make("data_json.{$field->key}")
                                    ->options($field->options_json ? array_combine($field->options_json, $field->options_json) : []),
                                \App\Enums\FieldTypeEnum::RADIO => Forms\Components\Radio::make("data_json.{$field->key}")
                                    ->options($field->options_json ? array_combine($field->options_json, $field->options_json) : []),
                                \App\Enums\FieldTypeEnum::CHECKBOX => Forms\Components\Toggle::make("data_json.{$field->key}"),
                                \App\Enums\FieldTypeEnum::EMAIL => Forms\Components\TextInput::make("data_json.{$field->key}")->email(),
                                default => Forms\Components\TextInput::make("data_json.{$field->key}"),
                            };

                            $component->label($field->name)
                                ->required($field->is_required);

                            if (method_exists($component, 'placeholder')) {
                                $component->placeholder($field->placeholder);
                            }

                            if ($field->default_value) {
                                $component->default($field->default_value);
                            }

                            $schema[] = $component;
                        }

                        return $schema;
                    })
                    ->columns(2)
                    ->visible(fn (Forms\Get $get) => filled($get('entity_id'))),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('entity.name')
                    ->label('Processo')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('data_json')
                    ->label('Dados Registrados')
                    ->formatStateUsing(fn ($state) => collect($state)->map(fn ($val, $key) => "{$key}: {$val}")->implode(' | '))
                    ->limit(100),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Preenchido por')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data de Registro')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
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
}
