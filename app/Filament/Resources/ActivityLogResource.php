<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use App\Models\ActivityLog;
use App\Models\User;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-eye';

    protected static ?string $navigationLabel = 'Auditoria';

    protected static ?string $modelLabel = 'Log de Atividade';

    protected static ?string $pluralModelLabel = 'Logs de Atividade';

    protected static ?string $navigationGroup = 'Configurações';

    protected static ?int $navigationSort = 99;

    /** Somente ADMIN acessa */
    public static function canAccess(): bool
    {
        return auth()->user()?->user_role?->value === 'ADMIN';
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Identificação')
                    ->schema([
                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Usuário'),

                        Infolists\Components\TextEntry::make('event_label')
                            ->label('Evento')
                            ->badge()
                            ->color(fn ($record) => $record->event_color),

                        Infolists\Components\TextEntry::make('auditable_type_label')
                            ->label('Entidade'),

                        Infolists\Components\TextEntry::make('auditable_id')
                            ->label('ID do Registro')
                            ->fontFamily('mono')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('ip_address')
                            ->label('IP'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Data/Hora')
                            ->dateTime('d/m/Y H:i:s'),
                    ])->columns(3),

                Infolists\Components\Section::make('Valores Anteriores')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('old_values')
                            ->label('')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => ! empty($record->old_values))
                    ->collapsible(),

                Infolists\Components\Section::make('Novos Valores')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('new_values')
                            ->label('')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => ! empty($record->new_values))
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data/Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuário')
                    ->searchable()
                    ->placeholder('Sistema'),

                Tables\Columns\TextColumn::make('event')
                    ->label('Evento')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'created' => 'Criado',
                        'updated' => 'Atualizado',
                        'deleted' => 'Excluído',
                        default   => ucfirst($state),
                    })
                    ->color(fn ($state) => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        default   => 'gray',
                    }),

                Tables\Columns\TextColumn::make('auditable_type')
                    ->label('Entidade')
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('auditable_id')
                    ->label('ID')
                    ->fontFamily('mono')
                    ->limit(12)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->label('Evento')
                    ->options([
                        'created' => 'Criado',
                        'updated' => 'Atualizado',
                        'deleted' => 'Excluído',
                    ]),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Usuário')
                    ->options(User::pluck('name', 'id')),

                Tables\Filters\SelectFilter::make('auditable_type')
                    ->label('Entidade')
                    ->options([
                        'App\Models\Demand'        => 'Demanda',
                        'App\Models\CustomEntity'  => 'Processo',
                        'App\Models\ProcessStatus' => 'Situação',
                        'App\Models\ProcessRole'   => 'Papel',
                        'App\Models\Client'        => 'Cliente',
                        'App\Models\Project'       => 'Projeto',
                    ]),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('De'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Até'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
                            ->when($data['until'], fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->poll('30s');  // Atualização automática a cada 30s
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListActivityLogs::route('/'),
            'view'   => Pages\ViewActivityLog::route('/{record}'),
        ];
    }
}
