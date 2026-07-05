<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Desinstalar o Sistema Gaspar">
    <title>Desinstalar — Gaspar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --bg-primary: #0a0a0f;
            --bg-card: #16161f;
            --border-color: #2a2a3a;
            --text-primary: #f0f0f5;
            --text-secondary: #9090a8;
            --text-muted: #60607a;
            --danger: #ef4444;
            --danger-hover: #f87171;
            --danger-dark: #7f1d1d;
            --danger-glow: rgba(239, 68, 68, 0.15);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            line-height: 1.6;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                radial-gradient(ellipse at 50% 30%, rgba(239, 68, 68, 0.04) 0%, transparent 50%),
                radial-gradient(ellipse at 20% 80%, rgba(239, 68, 68, 0.02) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .uninstall-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 480px;
            text-align: center;
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Ícone de alerta */
        .danger-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 72px;
            height: 72px;
            background: var(--danger-glow);
            border-radius: 50%;
            margin-bottom: 1.5rem;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.2); }
            50% { box-shadow: 0 0 0 12px rgba(239, 68, 68, 0); }
        }

        .danger-icon svg {
            width: 36px;
            height: 36px;
            fill: var(--danger);
        }

        .uninstall-container h1 {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
            color: var(--danger);
        }

        .uninstall-container > p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 2rem;
        }

        /* Card de consequências */
        .consequences-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 1.5rem;
            margin-bottom: 1.75rem;
            text-align: left;
        }

        .consequences-title {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--danger);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .consequences-title svg {
            width: 14px;
            height: 14px;
            fill: var(--danger);
        }

        .consequences-list {
            list-style: none;
        }

        .consequences-list li {
            display: flex;
            align-items: flex-start;
            gap: 0.625rem;
            padding: 0.5rem 0;
            font-size: 0.85rem;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border-color);
        }

        .consequences-list li:last-child {
            border-bottom: none;
        }

        .consequences-list li svg {
            width: 16px;
            height: 16px;
            fill: var(--danger);
            flex-shrink: 0;
            margin-top: 2px;
        }

        /* Botões */
        .actions {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .btn-danger {
            width: 100%;
            padding: 0.8rem 1.5rem;
            font-size: 0.95rem;
            font-weight: 600;
            font-family: inherit;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            background: linear-gradient(135deg, var(--danger), #dc2626);
            color: white;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            position: relative;
            overflow: hidden;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, var(--danger-hover), var(--danger));
            transform: translateY(-1px);
            box-shadow: 0 6px 24px rgba(239, 68, 68, 0.3);
        }

        .btn-danger:active {
            transform: translateY(0);
        }

        .btn-danger:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-danger svg {
            width: 18px;
            height: 18px;
            fill: white;
        }

        .spinner {
            display: none;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .btn-danger.loading .spinner {
            display: block;
        }

        .btn-danger.loading .btn-text,
        .btn-danger.loading .btn-icon {
            display: none;
        }

        .btn-cancel {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.375rem;
            width: 100%;
            padding: 0.7rem 1.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            font-family: inherit;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            cursor: pointer;
            background: transparent;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-cancel:hover {
            border-color: #404050;
            color: var(--text-primary);
            background: rgba(255, 255, 255, 0.03);
        }

        .btn-cancel svg {
            width: 16px;
            height: 16px;
            fill: currentColor;
        }

        .installer-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.75rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    <div class="uninstall-container">
        {{-- Ícone pulsante de perigo --}}
        <div class="danger-icon">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/>
            </svg>
        </div>

        <h1>Desinstalar o Gaspar</h1>
        <p>Esta ação é irreversível e não pode ser desfeita.</p>

        {{-- Card listando o que será apagado --}}
        <div class="consequences-card">
            <div class="consequences-title">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                </svg>
                O que será removido
            </div>
            <ul class="consequences-list">
                <li>
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                    <span>Todas as tabelas do banco de dados (usuários, demandas, processos, campos, etc.)</span>
                </li>
                <li>
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                    <span>Configurações de conexão com o banco de dados (arquivo .env)</span>
                </li>
                <li>
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                    <span>Registro de instalação do sistema</span>
                </li>
            </ul>
        </div>

        {{-- Formulário com confirmação JS --}}
        <form method="POST" action="/desinstalar" id="uninstallForm">
            @csrf
            <div class="actions">
                <button type="submit" class="btn-danger" id="btnUninstall">
                    <svg class="btn-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                    </svg>
                    <span class="btn-text">Sim, apagar todos os dados</span>
                    <div class="spinner"></div>
                </button>

                <a href="/admin" class="btn-cancel">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
                    </svg>
                    Cancelar e voltar ao sistema
                </a>
            </div>
        </form>

        <div class="installer-footer">
            Gaspar — Plataforma Institucional de Gestão por Processos
        </div>
    </div>

    <script>
        document.getElementById('uninstallForm').addEventListener('submit', function (e) {
            const confirmed = confirm(
                'ATENÇÃO: Todos os dados do sistema serão apagados permanentemente.\n\n' +
                'Isso inclui todos os usuários, demandas, processos e configurações.\n\n' +
                'Tem certeza que deseja prosseguir?'
            );

            if (!confirmed) {
                e.preventDefault();
                return;
            }

            const btn = document.getElementById('btnUninstall');
            btn.classList.add('loading');
            btn.disabled = true;
        });
    </script>
</body>
</html>
