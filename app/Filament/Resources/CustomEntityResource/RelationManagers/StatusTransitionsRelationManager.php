<?php

namespace App\Filament\Resources\CustomEntityResource\RelationManagers;

use App\Models\ProcessRole;
use App\Models\ProcessStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StatusTransitionsRelationManager extends RelationManager
{
    protected static string $relationship = 'statusTransitions';

    protected static ?string $title = 'Fluxo de Situações';

    protected static ?string $modelLabel = 'Transição';

    protected static ?string $pluralModelLabel = 'Transições';

    public function form(Form $form): Form
    {
        $entity        = $this->getOwnerRecord();
        $statusOptions = $entity->processStatuses()->pluck('name', 'process_statuses.id')->toArray();
        $roleOptions = [
            '__requester__' => 'Demandante (Quem abriu a demanda)',
            '__assignee__'  => 'Responsável (Executor atual)',
        ];

        // Busca todos os papéis e verifica se há usuários atrelados a eles neste processo
        $allRoles = \App\Models\ProcessRole::orderBy('name')->get();
        foreach ($allRoles as $role) {
            $roleMembers = \App\Models\ProcessMember::with('user')
                ->where('entity_id', $entity->id)
                ->where('process_role_id', $role->id)
                ->get();
            
            if ($roleMembers->isNotEmpty()) {
                $userNames = $roleMembers->map(fn($m) => $m->user?->name)->filter()->unique()->implode(', ');
                $roleOptions[$role->id] = "{$role->name} ({$userNames})";
            } else {
                $roleOptions[$role->id] = "{$role->name}";
            }
        }

        return $form->schema([
            Forms\Components\Select::make('from_status_id')
                ->label('De (situação atual)')
                ->options($statusOptions)
                ->nullable()
                ->placeholder('Qualquer situação (inicial)')
                ->helperText('Deixe vazio para permitir a transição de qualquer situação'),

            Forms\Components\Select::make('to_status_id')
                ->label('Para (próxima situação)')
                ->options($statusOptions)
                ->required(),

            Forms\Components\TextInput::make('label')
                ->label('Texto do botão')
                ->required()
                ->maxLength(100)
                ->placeholder('Ex: Aprovar, Rejeitar, Encaminhar...')
                ->helperText('Este texto aparecerá como botão de ação na demanda'),

            Forms\Components\Select::make('allowed_role_ids')
                ->label('Papéis que podem disparar')
                ->options($roleOptions)
                ->multiple()
                ->nullable()
                ->placeholder('Todos os membros/envolvidos')
                ->helperText('Vazio = qualquer envolvido na demanda ou membro do processo pode; selecione para restringir'),

            Forms\Components\TextInput::make('display_order')
                ->label('Ordem')
                ->numeric()
                ->default(0)
                ->minValue(0),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                Tables\Columns\TextColumn::make('fromStatus.name')
                    ->label('De')
                    ->placeholder('Qualquer situação')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('toStatus.name')
                    ->label('Para')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('label')
                    ->label('Botão de ação')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('allowed_role_ids')
                    ->label('Papéis permitidos')
                    ->formatStateUsing(function ($state, $record) {
                        $ids = is_string($state) ? json_decode($state, true) : $state;

                        if (empty($ids)) {
                            return 'Todos os membros';
                        }

                        $labels = collect();
                        
                        if (in_array('__requester__', $ids)) {
                            $labels->push('Demandante');
                        }
                        if (in_array('__assignee__', $ids)) {
                            $labels->push('Responsável (Atual)');
                        }

                        $staticRolesIds = array_diff($ids, ['__requester__', '__assignee__']);
                        if (! empty($staticRolesIds)) {
                            // Busca os papéis exigidos
                            $roles = \App\Models\ProcessRole::whereIn('id', $staticRolesIds)->get();
                            
                            foreach ($roles as $role) {
                                // Busca os membros reais do processo que têm esse papel
                                $members = \App\Models\ProcessMember::with('user')
                                    ->where('entity_id', $record->entity_id)
                                    ->where('process_role_id', $role->id)
                                    ->get();

                                if ($members->isEmpty()) {
                                    $labels->push("{$role->name} (Sem usuários)");
                                } else {
                                    $names = $members->map(fn ($m) => $m->user?->name)->filter()->unique()->implode(', ');
                                    $labels->push("{$role->name} ({$names})");
                                }
                            }
                        }

                        return $labels->implode(' / ');
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('display_order')
                    ->label('Ordem')
                    ->sortable(),
            ])
            ->defaultSort('display_order')
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Nova Transição'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->reorderable('display_order')
            ->bulkActions([]);
    }
}
