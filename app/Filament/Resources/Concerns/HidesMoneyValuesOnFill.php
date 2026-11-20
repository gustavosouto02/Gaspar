<?php

namespace App\Filament\Resources\Concerns;

use App\Services\MoneyFieldVisibility;

/**
 * Páginas de edição/visualização de registros com campos customizados
 * (`custom_data` ou `data_json`): tira do estado do formulário os campos de
 * valor que o usuário não pode ver, para que não cheguem ao navegador.
 */
trait HidesMoneyValuesOnFill
{
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return app(MoneyFieldVisibility::class)->stripHiddenFromFormData($data, $this->getRecord());
    }
}
