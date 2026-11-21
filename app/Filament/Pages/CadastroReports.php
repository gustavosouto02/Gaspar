<?php

namespace App\Filament\Pages;

use App\Enums\FieldTypeEnum;
use App\Filament\Resources\ClientResource;
use App\Filament\Resources\CustomRecordResource;
use App\Filament\Resources\MacroprocessResource;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\SupplierResource;
use App\Filament\Resources\UserResource;
use App\Models\Client;
use App\Models\CustomField;
use App\Models\CustomRecordType;
use App\Models\Macroprocess;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CadastroReportSource;
use App\Services\CustomFieldComponentFactory;
use App\Services\CustomFieldValueFormatter;
use App\Services\MoneyFieldVisibility;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Relatório de Cadastros: escolhe-se o cadastro, filtra-se pelos campos dele e
 * exporta-se CSV. Atende os cadastros criados pelo admin e os base (clientes,
 * projetos, fornecedores, usuários e macroprocessos) pela mesma tela.
 *
 * É uma consulta: campo de valor só aparece para ADMIN e gestor do cadastro
 * (MoneyFieldVisibility), na tela, nos filtros e no CSV.
 */
class CadastroReports extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationLabel = 'Relatório de Cadastros';

    protected static ?string $title = 'Relatório de Cadastros';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.cadastro-reports';

    #[Url]
    public ?string $type_id = null;

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('type_id')
                ->label('Cadastro')
                ->options(fn () => CadastroReportSource::availableTypes()->pluck('name', 'id'))
                ->searchable()
                ->live()
                ->placeholder('Escolha o cadastro')
                ->afterStateUpdated(fn () => $this->resetTable()),
        ]);
    }

    private function source(): ?CadastroReportSource
    {
        if (! $this->type_id) {
            return null;
        }

        $type = CustomRecordType::with('fields')->find($this->type_id);

        return $type && CadastroReportSource::availableTypes()->contains('id', $type->id)
            ? CadastroReportSource::for($type)
            : null;
    }

    /**
     * Campos que o usuário pode ver neste cadastro (valor só para admin/gestor).
     *
     * @return Collection<int, CustomField>
     */
    private function visibleFields(CadastroReportSource $source)
    {
        $visibility = app(MoneyFieldVisibility::class);

        return $source->fields()->reject(
            fn (CustomField $f) => $visibility->isHiddenFor(auth()->user(), $f, $source->type)
        );
    }

    public function table(Table $table): Table
    {
        $source = $this->source();

        if (! $source) {
            return $table->query(User::query()->whereRaw('1 = 0'))->columns([]);
        }

        $fields = $this->visibleFields($source);
        $formatter = app(CustomFieldValueFormatter::class);

        $columns = [];
        foreach ($source->fixedColumns() as $key => $column) {
            if ($column['kind'] === 'bool') {
                $columns[] = Tables\Columns\IconColumn::make($key)->label($column['label'])->boolean();

                continue;
            }

            $textColumn = Tables\Columns\TextColumn::make($key)
                ->label($column['label'])
                ->getStateUsing(fn (Model $record) => $source->fixedValue($record, $key));

            // Relação e lista de valores guardam o id/código: buscar e ordenar pela coluna só faz sentido no texto e na data
            if (in_array($column['kind'], ['text', 'date'], true)) {
                $textColumn->sortable();
            }
            if ($column['kind'] === 'text') {
                $textColumn->searchable();
            }

            $columns[] = $textColumn;
        }

        foreach ($fields as $field) {
            $columns[] = Tables\Columns\TextColumn::make('field_'.$field->id)
                ->label($field->name)
                ->getStateUsing(fn (Model $record) => $formatter->format($field, $source->rawValue($record, $field)));
        }

        $filters = [];
        foreach ($source->fixedColumns() as $key => $column) {
            $filters[] = $this->fixedFilter($source, $key, $column);
        }
        foreach ($fields as $field) {
            if ($filter = $this->fieldFilter($source, $field)) {
                $filters[] = $filter;
            }
        }

        return $table
            ->query(fn () => $this->source()?->query() ?? User::query()->whereRaw('1 = 0'))
            ->columns($columns)
            ->filters($filters, layout: Tables\Enums\FiltersLayout::AboveContent)
            ->deferFilters()
            ->filtersApplyAction(
                fn (Tables\Actions\Action $action) => $action
                    ->label('Consultar')
                    ->icon('heroicon-m-magnifying-glass')
                    ->color('primary')
            )
            ->filtersFormColumns(3)
            ->headerActions([
                Tables\Actions\Action::make('export_csv')
                    ->label('Exportar CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn ($livewire) => $this->exportCsv($livewire)),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Abrir')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Model $record) => $this->recordUrl($record)),
            ])
            ->recordUrl(fn (Model $record) => $this->recordUrl($record))
            ->defaultSort($source->isSystem() ? 'name' : 'code');
    }

    /**
     * @param  array<string, mixed>  $column
     */
    private function fixedFilter(CadastroReportSource $source, string $key, array $column): Tables\Filters\BaseFilter
    {
        $label = $column['label'];

        if ($column['kind'] === 'bool') {
            return Tables\Filters\TernaryFilter::make($key)->label($label);
        }

        $schema = match ($column['kind']) {
            'options', 'relation' => [
                Forms\Components\Select::make('values')->label($label)->multiple()->searchable()
                    ->options(fn () => $source->fixedOptions($key)),
            ],
            'date' => [
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\DatePicker::make('from')->label($label.' — de'),
                    Forms\Components\DatePicker::make('until')->label($label.' — até'),
                ]),
            ],
            default => [Forms\Components\TextInput::make('value')->label($label.' (contém)')],
        };

        return Tables\Filters\Filter::make('fixed_'.$key)
            ->form($schema)
            ->query(fn ($query, array $data) => $source->applyFixedFilter($query, $key, $data));
    }

    private function fieldFilter(CadastroReportSource $source, CustomField $field): ?Tables\Filters\Filter
    {
        $name = 'field_'.$field->id;

        $schema = match ($field->field_type) {
            FieldTypeEnum::TEXT, FieldTypeEnum::TEXTAREA, FieldTypeEnum::EMAIL => [
                Forms\Components\TextInput::make('value')->label($field->name.' (contém)'),
            ],
            FieldTypeEnum::SELECT, FieldTypeEnum::RADIO => [
                Forms\Components\Select::make('value')->label($field->name)
                    ->options(array_combine($field->options_json ?? [], $field->options_json ?? [])),
            ],
            FieldTypeEnum::COMBO => [
                app(CustomFieldComponentFactory::class)->make($field, 'value')
                    ->label($field->name),
            ],
            FieldTypeEnum::CHECKBOX => [
                Forms\Components\Select::make('value')->label($field->name)
                    ->options(['1' => 'Sim', '0' => 'Não']),
            ],
            FieldTypeEnum::DATE => [
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\DatePicker::make('from')->label($field->name.' — de'),
                    Forms\Components\DatePicker::make('until')->label($field->name.' — até'),
                ]),
            ],
            FieldTypeEnum::NUMBER, FieldTypeEnum::CURRENCY => [
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('min')->numeric()->label($field->name.' — mínimo'),
                    Forms\Components\TextInput::make('max')->numeric()->label($field->name.' — máximo'),
                ]),
            ],
            default => null,
        };

        if ($schema === null) {
            return null;
        }

        return Tables\Filters\Filter::make($name)
            ->form($schema)
            ->query(fn ($query, array $data) => $source->applyFieldFilter($query, $field, $data));
    }

    private function exportCsv($livewire)
    {
        $source = $this->source();
        $fields = $this->visibleFields($source);
        $formatter = app(CustomFieldValueFormatter::class);

        $csv = fopen('php://temp', 'w');
        // UTF-8 BOM para o Excel abrir corretamente
        fwrite($csv, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($csv, array_merge(
            array_map(fn ($c) => $c['label'], array_values($source->fixedColumns())),
            $fields->map(fn (CustomField $f) => $f->name)->all(),
        ), ';');

        $livewire->getFilteredSortedTableQuery()->each(function (Model $record) use ($csv, $source, $fields, $formatter) {
            $row = [];
            foreach (array_keys($source->fixedColumns()) as $key) {
                $row[] = $source->fixedValue($record, $key);
            }
            foreach ($fields as $field) {
                $row[] = $formatter->format($field, $source->rawValue($record, $field));
            }
            fputcsv($csv, array_map(fn ($v) => $this->csvSafe((string) $v), $row), ';');
        });

        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        $filename = 'relatorio_'.str($source->type->name)->slug('_').'.csv';

        return response()->streamDownload(fn () => print ($content), $filename);
    }

    /**
     * Evita que o Excel execute como fórmula um texto que começa com = + - @.
     */
    private function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) && ! is_numeric($value) ? "'".$value : $value;
    }

    private function recordUrl(Model $record): ?string
    {
        $resource = match (true) {
            $record instanceof Client => ClientResource::class,
            $record instanceof Project => ProjectResource::class,
            $record instanceof Supplier => SupplierResource::class,
            $record instanceof User => UserResource::class,
            $record instanceof Macroprocess => MacroprocessResource::class,
            default => null,
        };

        if ($resource) {
            return $resource::getUrl('view', ['record' => $record]);
        }

        return CustomRecordResource::getUrl('view', [
            'record' => $record,
            'type_id' => $record->custom_record_type_id,
        ]);
    }
}
