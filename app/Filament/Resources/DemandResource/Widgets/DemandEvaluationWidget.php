<?php

namespace App\Filament\Resources\DemandResource\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class DemandEvaluationWidget extends Widget
{
    protected static string $view = 'filament.resources.demand-resource.widgets.demand-evaluation-widget';

    protected int | string | array $columnSpan = 'full';

    public ?Model $record = null;
}
