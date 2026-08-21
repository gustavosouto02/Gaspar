<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectResource\Pages;
use App\Filament\Resources\ProjectResource\RelationManagers;
use App\Models\Project;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';
    protected static ?string $navigationLabel = 'Projetos';
    protected static ?string $modelLabel = 'Projeto';
    protected static ?string $pluralModelLabel = 'Projetos';
    protected static ?string $navigationGroup = 'Cadastros';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Group::make()
                                    ->schema([
                                        Forms\Components\Placeholder::make('id_label')
                                            ->label('Projeto número:')
                                            ->content(fn ($record) => $record ? '#' . strtoupper(substr($record->id, 0, 8)) : 'NOVO'),

                                        Forms\Components\Placeholder::make('created_at_label')
                                            ->label('Data de criação:')
                                            ->content(fn ($record) => $record ? $record->created_at->format('d/m/Y') : '-'),

                                        Forms\Components\Select::make('entity_id')
                                            ->label('Processo:')
                                            ->options(\App\Models\CustomEntity::where('is_active', true)->pluck('name', 'id'))
                                            ->searchable()
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                                if ($state) {
                                                    $entity = \App\Models\CustomEntity::find($state);
                                                    if ($entity) {
                                                        $nova = $entity->processStatuses()->where('name', 'Nova')->first() 
                                                                ?? $entity->processStatuses()->orderBy('display_order')->first();
                                                        $set('process_status_id', $nova?->id);
                                                    } else {
                                                        $set('process_status_id', null);
                                                    }
                                                } else {
                                                    $set('process_status_id', null);
                                                }
                                            }),

                                        Forms\Components\Select::make('process_status_id')
                                            ->label('Situação:')
                                            ->options(function (\Filament\Forms\Get $get) {
                                                $entityId = $get('entity_id');
                                                if (! $entityId) return [];
                                                $entity = \App\Models\CustomEntity::find($entityId);
                                                if (! $entity) return [];
                                                return $entity->processStatuses()->pluck('name', 'process_statuses.id');
                                            })
                                            ->searchable()
                                            ->required()
                                            ->live()
                                            ->placeholder('Selecione primeiro o processo...'),
                                            
                                        Forms\Components\Select::make('client_id')
                                            ->label('Cliente:')
                                            ->relationship('client', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->nullable(),
                                    ]),

                                Forms\Components\Group::make()
                                    ->schema([
                                        Forms\Components\Placeholder::make('start_date_forecast_label')
                                            ->label('Previsão de início:')
                                            ->content(fn ($record) => $record && $record->start_date_forecast ? $record->start_date_forecast->format('d/m/Y') : '-'),

                                        Forms\Components\Placeholder::make('end_date_forecast_label')
                                            ->label('Previsão de conclusão:')
                                            ->content(fn ($record) => $record && $record->end_date_forecast ? $record->end_date_forecast->format('d/m/Y') : '-'),
                                            
                                        Forms\Components\Placeholder::make('updated_at_label')
                                            ->label('Última atualização:')
                                            ->content(fn ($record) => $record ? $record->updated_at->format('d/m/Y') : '-'),
                                    ]),

                                Forms\Components\Group::make()
                                    ->schema([
                                        Forms\Components\Placeholder::make('end_date_label')
                                            ->label('Data de conclusão:')
                                            ->content(fn ($record) => $record && $record->end_date ? $record->end_date->format('d/m/Y') : '-'),

                                        Forms\Components\Placeholder::make('sponsor_evaluation_label')
                                            ->label('Avaliação do Sponsor:')
                                            ->content(fn ($record) => $record && $record->sponsor_evaluation ? $record->sponsor_evaluation : '-'),
                                    ]),
                            ]),
                            
                        Forms\Components\TextInput::make('name')
                            ->label('Título:')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Equipe e Contatos')
                    ->schema([
                        Forms\Components\Select::make('contact_name')
                            ->label('Contato')
                            ->options(\App\Models\User::pluck('name', 'name'))
                            ->searchable(),
                        Forms\Components\Select::make('manager_name')
                            ->label('Gerente')
                            ->options(\App\Models\User::pluck('name', 'name'))
                            ->searchable(),
                        Forms\Components\Select::make('sponsor_name')
                            ->label('Sponsor')
                            ->options(\App\Models\User::pluck('name', 'name'))
                            ->searchable(),
                        Forms\Components\Select::make('scrum_master_name')
                            ->label('Scrum Master')
                            ->options(\App\Models\User::pluck('name', 'name'))
                            ->searchable(),
                        Forms\Components\Select::make('product_owner_name')
                            ->label('Product Owner')
                            ->options(\App\Models\User::pluck('name', 'name'))
                            ->searchable(),
                    ])->columns(2),

                Forms\Components\Section::make('Financeiro e Avaliação')
                    ->schema([
                        Forms\Components\TextInput::make('budget')
                            ->label('Orçamento (R$)')
                            ->numeric()
                            ->prefix('R$'),
                        Forms\Components\TextInput::make('spent')
                            ->label('Despendido (R$)')
                            ->numeric()
                            ->prefix('R$'),
                        Forms\Components\TextInput::make('sponsor_evaluation')
                            ->label('Avaliação do Sponsor')
                            ->maxLength(255),
                    ])->columns(3),

                Forms\Components\Section::make('Detalhes')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Descrição:')
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('attachments')
                            ->label('Anexos:')
                            ->multiple()
                            ->preserveFilenames()
                            ->downloadable()
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Ativo')
                            ->default(true),
                    ]),

                Forms\Components\Section::make('Demandas')
                    ->headerActions([
                        Forms\Components\Actions\Action::make('registrar_demanda')
                            ->label('Registrar Demanda')
                            ->icon('heroicon-m-plus')
                            ->color('warning')
                            ->url(fn ($record) => $record ? \App\Filament\Resources\DemandResource::getUrl('create', ['project_id' => $record->id]) : null)
                    ])
                    ->schema([
                        Forms\Components\ViewField::make('project_demands')
                            ->view('filament.forms.components.project-demands')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record !== null)
                    ->collapsible(),

                Forms\Components\Section::make('Tratamento')
                    ->schema([
                        Forms\Components\ViewField::make('treatments_timeline')
                            ->view('filament.forms.components.project-timeline')
                            ->visible(fn ($record) => $record !== null)
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record !== null)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Número')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Título')
                    ->searchable(),
                Tables\Columns\TextColumn::make('client.name')
                    ->label('Cliente')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Situação')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->recordUrl(fn (Project $record): string => Pages\ViewProject::getUrl(['record' => $record]));
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
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'view' => Pages\ViewProject::route('/{record}'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
        ];
    }
}
