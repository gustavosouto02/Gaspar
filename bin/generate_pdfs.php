<?php

require dirname(__DIR__) . '/vendor/autoload.php';

$app = require_once dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

$relatorios = [
    [
        'md' => dirname(__DIR__) . '/docs/relatorios/relatorio_executivo_gaspar.md',
        'pdf' => dirname(__DIR__) . '/docs/relatorios/relatorio_executivo_gaspar.pdf',
        'title' => 'GASPAR — Relatório Executivo',
        'badge' => 'Documento Executivo para Assembleia Geral',
    ],
    [
        'md' => dirname(__DIR__) . '/docs/relatorios/relatorio_tecnico_gaspar.md',
        'pdf' => dirname(__DIR__) . '/docs/relatorios/relatorio_tecnico_gaspar.pdf',
        'title' => 'GASPAR — Relatório Técnico de Engenharia',
        'badge' => 'Documento Técnico e Arquitetural',
    ],
];

$css = <<<CSS
@page {
    margin: 20mm 18mm 18mm 18mm;
    @bottom-right {
        content: counter(page);
    }
}

body {
    font-family: "DejaVu Sans", "Helvetica Neue", Helvetica, Arial, sans-serif;
    color: #1e293b;
    font-size: 10pt;
    line-height: 1.55;
}

.header {
    border-bottom: 2px solid #0284c7;
    padding-bottom: 10px;
    margin-bottom: 18px;
}

.badge {
    display: inline-block;
    background-color: #e0f2fe;
    color: #0369a1;
    font-size: 8pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 3px 8px;
    border-radius: 4px;
    margin-bottom: 4px;
}

.doc-meta {
    font-size: 8pt;
    color: #64748b;
    float: right;
    text-align: right;
    margin-top: 2px;
}

.clear {
    clear: both;
}

h1 {
    font-size: 16pt;
    color: #0f172a;
    margin: 0 0 4px 0;
    font-weight: bold;
    letter-spacing: -0.5px;
}

h2 {
    font-size: 12pt;
    color: #0369a1;
    margin-top: 18px;
    margin-bottom: 8px;
    padding-bottom: 3px;
    border-bottom: 1px solid #e2e8f0;
    page-break-after: avoid;
    font-weight: bold;
}

h3 {
    font-size: 10.5pt;
    color: #0f172a;
    margin-top: 14px;
    margin-bottom: 5px;
    page-break-after: avoid;
    font-weight: bold;
}

p {
    margin: 0 0 8px 0;
    text-align: justify;
}

ul, ol {
    margin: 0 0 10px 0;
    padding-left: 18px;
}

li {
    margin-bottom: 4px;
}

li strong {
    color: #0f172a;
}

blockquote {
    margin: 10px 0;
    padding: 8px 12px;
    background-color: #f8fafc;
    border-left: 3px solid #0284c7;
    color: #334155;
    font-style: italic;
    border-radius: 0 4px 4px 0;
}

blockquote p {
    margin: 0;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin: 14px 0;
    font-size: 9pt;
    page-break-inside: avoid;
}

th {
    background-color: #f1f5f9;
    color: #0f172a;
    font-weight: bold;
    text-align: left;
    padding: 7px 9px;
    border: 1px solid #cbd5e1;
}

td {
    padding: 6px 9px;
    border: 1px solid #e2e8f0;
    vertical-align: top;
}

tr:nth-child(even) td {
    background-color: #f8fafc;
}

code {
    font-family: "DejaVu Sans Mono", monospace;
    font-size: 8.5pt;
    background-color: #f1f5f9;
    color: #0369a1;
    padding: 1px 3px;
    border-radius: 3px;
    border: 1px solid #e2e8f0;
}

pre {
    background-color: #f8fafc;
    color: #0f172a;
    border: 1px solid #cbd5e1;
    border-left: 4px solid #0284c7;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 8.5pt;
    line-height: 1.4;
    margin: 10px 0;
    font-family: "DejaVu Sans Mono", monospace;
    white-space: pre-wrap;
    word-break: break-word;
}

pre code {
    background-color: transparent;
    color: inherit;
    padding: 0;
    border: none;
    font-size: 8.5pt;
}

hr {
    border: none;
    border-top: 1px solid #e2e8f0;
    margin: 16px 0;
}

.footer {
    position: fixed;
    bottom: -10mm;
    left: 0;
    right: 0;
    font-size: 7.5pt;
    color: #94a3b8;
    border-top: 1px solid #e2e8f0;
    padding-top: 3px;
    text-align: left;
}

.footer-right {
    float: right;
}
CSS;

foreach ($relatorios as $item) {
    if (! file_exists($item['md'])) {
        echo "Arquivo não encontrado: {$item['md']}\n";
        continue;
    }

    $rawMd = file_get_contents($item['md']);
    // Normaliza quebras de linha
    $rawMd = str_replace(["\r\n", "\r"], "\n", $rawMd);

    // Substitui caracteres de caixas/setas Unicode não renderizáveis por fontes nativas do PDF
    $rawMd = str_replace(
        ['➔', '➜', '➝', '→', '▼', '▲', '├─', '└─', '│', '┌', '┐', '└', '┘', '─', '•', '⭐'],
        ['->', '->', '->', '->', 'v', '^', '+--', '+--', '|', '+', '+', '+', '+', '-', '*', '[★]'],
        $rawMd
    );
    
    // Converte Markdown para HTML estruturado
    $htmlContent = Str::markdown($rawMd);

    $dataEmissao = date('d/m/Y');
    
    $fullHtml = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{$item['title']}</title>
    <style>
        {$css}
    </style>
</head>
<body>
    <div class="footer">
        <span>GASPAR • Sistema Institucional de Gestão por Processos</span>
        <span class="footer-right">Documento Oficial • Emitido em {$dataEmissao}</span>
    </div>

    <div class="header">
        <div class="doc-meta">
            <strong>Versão Oficial</strong><br>
            Data: {$dataEmissao}
        </div>
        <div class="badge">{$item['badge']}</div>
        <div class="clear"></div>
    </div>

    {$htmlContent}
</body>
</html>
HTML;

    $pdf = Pdf::loadHTML($fullHtml)
        ->setPaper('a4', 'portrait')
        ->setOption('isHtml5ParserEnabled', true)
        ->setOption('isRemoteEnabled', true);

    file_put_contents($item['pdf'], $pdf->output());
    $sizeKb = round(filesize($item['pdf']) / 1024, 1);
    echo "✓ PDF gerado: {$item['pdf']} ({$sizeKb} KB)\n";
}
