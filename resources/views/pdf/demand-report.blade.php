<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Demanda #{{ strtoupper(substr($demand->id, 0, 8)) }} - {{ $demand->title }}</title>
    <style>
        @page {
            margin: 28px 32px 35px 32px;
        }
        * {
            box-sizing: border-box;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        }
        body {
            font-size: 11px;
            color: #334155;
            line-height: 1.45;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }

        /* Header do Relatório */
        .report-header {
            border-bottom: 2px solid #2563eb;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        .report-header table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-title h1 {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 4px 0;
        }
        .header-title .subtitle {
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header-meta {
            text-align: right;
            vertical-align: middle;
        }
        .demand-badge-id {
            background-color: #0f172a;
            color: #ffffff;
            font-weight: 700;
            font-size: 13px;
            padding: 5px 10px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 4px;
        }
        .emission-date {
            font-size: 9px;
            color: #64748b;
        }

        /* Seções */
        .section {
            margin-bottom: 16px;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #1e3a8a;
            background-color: #f1f5f9;
            padding: 6px 10px;
            border-left: 3px solid #2563eb;
            margin-bottom: 8px;
        }

        /* Tabelas de Grid */
        table.grid-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        table.grid-table td, table.grid-table th {
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            vertical-align: top;
        }
        table.grid-table th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 600;
            font-size: 10px;
            text-align: left;
        }
        .label {
            font-size: 9.5px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 2px;
            display: block;
        }
        .value {
            font-size: 11px;
            color: #0f172a;
            font-weight: 500;
        }

        /* Badges de Status e Prioridade */
        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 3px;
            font-size: 9.5px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-active { background: #e0f2fe; color: #0369a1; }
        .badge-completed { background: #dcfce7; color: #15803d; }
        .badge-closed { background: #fef3c7; color: #b45309; }
        .badge-canceled { background: #fee2e2; color: #b91c1c; }
        .badge-evaluated { background: #f3e8ff; color: #6b21a8; }
        .badge-draft { background: #f1f5f9; color: #475569; }

        /* Caixa de Descrição e Relatos */
        .text-box {
            background: #fafafa;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 10px;
            font-size: 11px;
            color: #1e293b;
            white-space: pre-line;
            line-height: 1.5;
        }

        /* Histórico de Transições / Linha do tempo */
        .timeline-item {
            border-left: 2px solid #cbd5e1;
            padding-left: 12px;
            margin-left: 6px;
            margin-bottom: 10px;
            position: relative;
        }
        .timeline-bullet {
            width: 8px;
            height: 8px;
            background: #2563eb;
            border-radius: 50%;
            position: absolute;
            left: -5px;
            top: 4px;
        }
        .timeline-date {
            font-size: 9.5px;
            font-weight: 700;
            color: #2563eb;
        }
        .timeline-user {
            font-weight: 600;
            color: #0f172a;
        }
        .timeline-desc {
            font-size: 10.5px;
            color: #475569;
            margin-top: 2px;
        }

        /* Rodapé */
        .footer {
            position: fixed;
            bottom: -15px;
            left: 0;
            right: 0;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
            font-size: 8.5px;
            color: #94a3b8;
            text-align: center;
        }
        .footer table {
            width: 100%;
        }
    </style>
</head>
<body>

    <!-- CABEÇALHO -->
    <div class="report-header">
        <table>
            <tr>
                <td class="header-title" style="width: 70%;">
                    <div class="subtitle">GASPAR — Gestão por Processos (BPM)</div>
                    <h1>Registro Completo da Demanda</h1>
                    <div style="font-size: 11px; color: #475569; font-weight: 600;">
                        {{ $demand->title }}
                    </div>
                </td>
                <td class="header-meta" style="width: 30%;">
                    <div class="demand-badge-id">#{{ strtoupper(substr($demand->id, 0, 8)) }}</div>
                    <div class="emission-date">Emissão: {{ now()->format('d/m/Y H:i:s') }}</div>
                    <div style="font-size: 8.5px; color: #94a3b8; word-break: break-all;">UUID: {{ $demand->id }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- SEÇÃO 1: INFORMAÇÕES GERAIS E CLASSIFICAÇÃO -->
    <div class="section">
        <div class="section-title">1. Dados Gerais da Demanda</div>
        <table class="grid-table">
            <tr>
                <td style="width: 25%;">
                    <span class="label">Status Geral</span>
                    <span class="badge badge-{{ strtolower($demand->status?->value ?? 'draft') }}">
                        {{ $demand->status?->label() ?? '—' }}
                    </span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Situação do Fluxo</span>
                    <span class="value" style="color: #2563eb; font-weight: 700;">
                        {{ $demand->processStatus?->name ?? '—' }}
                    </span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Prioridade</span>
                    <span class="value">{{ $demand->priority?->label() ?? '—' }}</span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Macroprocesso</span>
                    <span class="value">
                        @if($demand->entity && $demand->entity->macroprocess)
                            <strong>{{ $demand->entity->macroprocess->acronym }}</strong> - {{ $demand->entity->macroprocess->name }}
                        @else
                            —
                        @endif
                    </span>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="label">Processo Vinculado</span>
                    <span class="value" style="font-size: 12px; font-weight: 700;">
                        {{ $demand->entity?->full_display_name ?? '—' }}
                    </span>
                </td>
                <td>
                    <span class="label">Cliente</span>
                    <span class="value">{{ $demand->client?->name ?? '—' }}</span>
                </td>
                <td>
                    <span class="label">Projeto</span>
                    <span class="value">{{ $demand->project?->name ?? '—' }}</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- SEÇÃO 2: ENVOLVIDOS E RESPONSABILIDADES -->
    <div class="section">
        <div class="section-title">2. Pessoas Envolvidas</div>
        <table class="grid-table">
            <tr>
                <td style="width: 34%;">
                    <span class="label">Solicitante (Demandante)</span>
                    <span class="value"><strong>{{ $demand->requester?->name ?? '—' }}</strong></span>
                    <div style="font-size: 9.5px; color: #64748b;">{{ $demand->requester?->email ?? '' }}</div>
                </td>
                <td style="width: 33%;">
                    <span class="label">Responsável Atual pelo Atendimento</span>
                    <span class="value"><strong>{{ $demand->assignee?->name ?? 'Não atribuído' }}</strong></span>
                    <div style="font-size: 9.5px; color: #64748b;">{{ $demand->assignee?->email ?? '' }}</div>
                </td>
                <td style="width: 33%;">
                    <span class="label">Registrado / Criado por</span>
                    <span class="value">{{ $demand->creator?->name ?? '—' }}</span>
                    <div style="font-size: 9.5px; color: #64748b;">{{ $demand->creator?->email ?? '' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- SEÇÃO 3: DATAS E CONTROLE DE SLA -->
    <div class="section">
        <div class="section-title">3. Prazos e Controle de Tempo (SLA)</div>
        <table class="grid-table">
            <tr>
                <td style="width: 25%;">
                    <span class="label">Data de Abertura</span>
                    <span class="value">{{ $demand->created_at ? $demand->created_at->format('d/m/Y H:i') : '—' }}</span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Prazo Limite (SLA)</span>
                    <span class="value" style="font-weight: 700; color: {{ $demand->sla_due_at && $demand->sla_due_at->isPast() && $demand->status?->value === 'ACTIVE' ? '#b91c1c' : '#0f172a' }};">
                        {{ $demand->sla_due_at ? $demand->sla_due_at->format('d/m/Y H:i') : 'Sem prazo' }}
                    </span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Início do Atendimento</span>
                    <span class="value">{{ $demand->started_at ? $demand->started_at->format('d/m/Y H:i') : '—' }}</span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Conclusão / Término</span>
                    <span class="value">{{ $demand->completed_at ? $demand->completed_at->format('d/m/Y H:i') : 'Em andamento' }}</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- SEÇÃO 4: DESCRIÇÃO DETALHADA -->
    <div class="section">
        <div class="section-title">4. Descrição da Demana</div>
        <div class="text-box">
            {{ $demand->description ?: 'Nenhuma descrição detalhada informada.' }}
        </div>
    </div>

    <!-- SEÇÃO 5: CAMPOS CUSTOMIZADOS DO PROCESSO (METADADOS) -->
    @if($demand->fieldValues && $demand->fieldValues->isNotEmpty())
    <div class="section">
        <div class="section-title">5. Formulário do Processo (Campos Customizados)</div>
        <table class="grid-table">
            <thead>
                <tr>
                    <th style="width: 40%;">Campo</th>
                    <th style="width: 60%;">Valor Preenchido</th>
                </tr>
            </thead>
            <tbody>
                @foreach($demand->fieldValues as $fieldVal)
                    @if($fieldVal->customField)
                    <tr>
                        <td style="background-color: #fafafa;">
                            <strong>{{ $fieldVal->customField->name }}</strong>
                            <div style="font-size: 8.5px; color: #94a3b8;">{{ $fieldVal->customField->key }}</div>
                        </td>
                        <td>
                            @php
                                $val = $fieldVal->value;
                                $decoded = json_decode($val, true);
                                if (is_array($decoded)) {
                                    $val = implode(', ', $decoded);
                                }
                            @endphp
                            {{ $val ?: '—' }}
                        </td>
                    </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- SEÇÃO 6: HISTÓRICO DE TRAMITAÇÃO E RESPONSÁVEIS (POR QUEM PASSOU) -->
    <div class="section">
        <div class="section-title">6. Histórico de Tramitação e Transições (Por quem passou)</div>
        @if($transitionsHistory && $transitionsHistory->isNotEmpty())
            <div style="margin-top: 6px;">
                @foreach($transitionsHistory as $history)
                    <div class="timeline-item">
                        <div class="timeline-bullet"></div>
                        <div class="timeline-date">
                            {{ $history->created_at ? $history->created_at->format('d/m/Y H:i:s') : '—' }}
                        </div>
                        <div class="timeline-user">
                            {{ $history->user?->name ?? 'Sistema' }}
                            @if($history->user)
                                <span style="font-size: 9px; color: #64748b; font-weight: normal;">({{ $history->user->email }})</span>
                            @endif
                        </div>
                        <div class="timeline-desc">
                            @php
                                $from = $history->old_values['status_name'] ?? 'Início';
                                $to = $history->new_values['status_name'] ?? '—';
                                $action = $history->new_values['action'] ?? 'Transição';
                            @endphp
                            <strong>Ação:</strong> {{ $action }} | <strong>Mudança:</strong> "{{ $from }}" ➔ <strong>"{{ $to }}"</strong>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-box" style="color: #64748b;">
                Nenhuma transição de situação registrada até o momento.
            </div>
        @endif
    </div>

    <!-- SEÇÃO 7: RELATOS DE TRATAMENTO (HISTÓRICO DE COMENTÁRIOS) -->
    <div class="section">
        <div class="section-title">7. Histórico de Tratamentos e Relatos</div>
        @if($demand->comments && $demand->comments->isNotEmpty())
            <table class="grid-table">
                <thead>
                    <tr>
                        <th style="width: 25%;">Data / Autor</th>
                        <th style="width: 75%;">Relato / Conteúdo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($demand->comments as $comment)
                    <tr>
                        <td style="background-color: #fafafa;">
                            <div style="font-weight: 700; color: #0f172a;">{{ $comment->user?->name ?? 'Usuário' }}</div>
                            <div style="font-size: 9px; color: #64748b;">{{ $comment->created_at ? $comment->created_at->format('d/m/Y H:i') : '—' }}</div>
                        </td>
                        <td style="white-space: pre-line;">
                            {{ $comment->comment }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="text-box" style="color: #64748b;">
                Nenhum relato ou comentário adicional registrado.
            </div>
        @endif
    </div>

    <!-- SEÇÃO 8: SUBDEMANDAS VINCULADAS (SE HOUVER) -->
    @if($demand->children && $demand->children->isNotEmpty())
    <div class="section">
        <div class="section-title">8. Subdemandas Vinculadas (Filhas)</div>
        <table class="grid-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Código</th>
                    <th style="width: 45%;">Título</th>
                    <th style="width: 20%;">Situação</th>
                    <th style="width: 20%;">Responsável</th>
                </tr>
            </thead>
            <tbody>
                @foreach($demand->children as $child)
                <tr>
                    <td><strong>#{{ strtoupper(substr($child->id, 0, 8)) }}</strong></td>
                    <td>{{ $child->title }}</td>
                    <td>{{ $child->processStatus?->name ?? '—' }}</td>
                    <td>{{ $child->assignee?->name ?? 'Não atribuído' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- SEÇÃO 9: AVALIAÇÃO DE SATISFAÇÃO (SE HOUVER) -->
    @if($demand->satisfaction_rating)
    <div class="section">
        <div class="section-title">9. Pesquisa de Satisfação (Avaliação do Solicitante)</div>
        <table class="grid-table">
            <tr>
                <td style="width: 30%; background: #fefce8;">
                    <span class="label">Nota Atribuída</span>
                    <span style="font-size: 14px; font-weight: 700; color: #b45309;">
                        ★ {{ $demand->satisfaction_rating->label() }}
                    </span>
                    <div style="font-size: 9px; color: #78350f;">Avaliado em: {{ $demand->satisfaction_evaluated_at ? $demand->satisfaction_evaluated_at->format('d/m/Y H:i') : '—' }}</div>
                </td>
                <td style="width: 70%;">
                    <span class="label">Comentário do Solicitante</span>
                    <div style="font-size: 11px; color: #1e293b;">
                        {{ $demand->satisfaction_comment ?: 'Nenhum comentário adicional registrado.' }}
                    </div>
                </td>
            </tr>
        </table>
    </div>
    @endif

    <!-- RODAPÉ FIXO -->
    <div class="footer">
        <table>
            <tr>
                <td style="text-align: left; width: 50%;">
                    GASPAR • Sistema Institucional de Gestão por Processos
                </td>
                <td style="text-align: right; width: 50%;">
                    Documento Oficial Gerado Eletronicamente
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
