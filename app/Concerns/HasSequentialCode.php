<?php

namespace App\Concerns;

use App\Services\CodeGeneratorService;
use Illuminate\Support\Facades\Schema;

trait HasSequentialCode
{
    /**
     * Intercepta o evento 'creating' para atribuir o código sequencial se ainda não informado.
     */
    public static function bootHasSequentialCode(): void
    {
        static::creating(function ($model) {
            $column = $model->getSequentialCodeColumn();
            if (! Schema::hasColumn($model->getTable(), $column)) {
                return;
            }
            if (empty($model->{$column})) {
                $year = $model->created_at ? $model->created_at->year : null;
                $model->{$column} = app(CodeGeneratorService::class)->generate(
                    table: $model->getTable(),
                    column: $column,
                    prefix: $model->getSequentialCodePrefix(),
                    year: $year
                );
            }
        });
    }

    /**
     * Nome da coluna que armazena o código amigável.
     */
    public function getSequentialCodeColumn(): string
    {
        return property_exists($this, 'sequentialCodeColumn') ? $this->sequentialCodeColumn : 'code';
    }

    /**
     * Prefixo utilizado para o código amigável.
     */
    public function getSequentialCodePrefix(): string
    {
        return property_exists($this, 'sequentialCodePrefix') ? $this->sequentialCodePrefix : 'REG';
    }
}
