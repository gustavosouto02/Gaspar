<?php

namespace App\Filament\Pages;

use App\Models\SavedReport;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;

class SavedReports extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-bookmark-square';
    protected static ?string $navigationLabel = 'Minhas Consultas';
    protected static ?string $title = 'Minhas Consultas Salvas';
    protected static ?string $navigationGroup = 'Relatórios';
    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.saved-reports';

    public function table(Table $table): Table
    {
        return $table
            ->query(SavedReport::query()->where('user_id', auth()->id()))
            ->columns([
                TextColumn::make('name')
                    ->label('Nome da Consulta')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Salva em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Action::make('apply')
                    ->label('Aplicar')
                    ->icon('heroicon-o-arrow-right')
                    ->url(fn (SavedReport $record) => DemandReports::getUrl(['saved_report_id' => $record->id])),
                DeleteAction::make(),
            ]);
    }
}
