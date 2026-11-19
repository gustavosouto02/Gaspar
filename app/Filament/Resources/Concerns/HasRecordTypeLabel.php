<?php

namespace App\Filament\Resources\Concerns;

use App\Models\CustomRecordType;

trait HasRecordTypeLabel
{
    protected static function recordTypeName(): ?string
    {
        return once(function () {
            try {
                return CustomRecordType::where('model_class', static::$model)
                    ->where('is_system', true)
                    ->value('name');
            } catch (\Throwable) {
                return null;
            }
        });
    }

    public static function getNavigationLabel(): string
    {
        return static::recordTypeName() ?? static::$navigationLabel ?? static::$pluralModelLabel;
    }

    public static function getPluralModelLabel(): string
    {
        return static::recordTypeName() ?? static::$pluralModelLabel;
    }
}
