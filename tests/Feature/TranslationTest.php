<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TranslationTest extends TestCase
{
    #[DataProvider('grupos')]
    public function test_todas_as_mensagens_padrao_estao_traduzidas(string $grupo): void
    {
        $original = Arr::dot(require base_path("vendor/laravel/framework/src/Illuminate/Translation/lang/en/{$grupo}.php"));
        $traducao = Arr::dot(require lang_path("pt_BR/{$grupo}.php"));

        foreach ($original as $chave => $mensagem) {
            // Exemplos e listas vazias que vêm no arquivo do framework.
            if (str_starts_with($chave, 'custom.') || $chave === 'attributes') {
                continue;
            }

            $this->assertArrayHasKey($chave, $traducao, "Falta traduzir {$grupo}.{$chave}");
            $this->assertEqualsCanonicalizing(
                $this->parametros($mensagem),
                $this->parametros($traducao[$chave]),
                "Parâmetros diferentes em {$grupo}.{$chave}",
            );
        }
    }

    public static function grupos(): array
    {
        return [['validation'], ['auth'], ['passwords'], ['pagination']];
    }

    private function parametros(string $mensagem): array
    {
        preg_match_all('/:[a-z]+/', $mensagem, $encontrados);

        return array_values(array_unique($encontrados[0]));
    }
}
