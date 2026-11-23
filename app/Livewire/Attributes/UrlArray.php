<?php

namespace App\Livewire\Attributes;

use Livewire\Features\SupportQueryString\BaseUrl;

/**
 * Igual ao `#[Url]`, mas ignora valor que não seja array: numa propriedade `?array`,
 * `?prop=abc` na URL estourava TypeError (500) ao hidratar a página.
 */
#[\Attribute]
class UrlArray extends BaseUrl
{
    public function setValue($value, ?bool $nullable = false)
    {
        if (! is_array($value)) {
            return;
        }

        parent::setValue($value, $nullable);
    }
}
