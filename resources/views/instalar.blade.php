<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Instalador do Sistema Gaspar — Plataforma Institucional de Gestão por Processos">
    <title>Instalar — Gaspar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --bg-primary: #0a0a0f;
            --bg-secondary: #12121a;
            --bg-card: #16161f;
            --bg-input: #1c1c28;
            --border-color: #2a2a3a;
            --border-focus: #f59e0b;
            --text-primary: #f0f0f5;
            --text-secondary: #9090a8;
            --text-muted: #60607a;
            --accent: #f59e0b;
            --accent-hover: #fbbf24;
            --accent-glow: rgba(245, 158, 11, 0.15);
            --error-bg: #1f0a0a;
            --error-border: #7f1d1d;
            --error-text: #fca5a5;
            --success-bg: #0a1f0a;
            --success-border: #166534;
            --success-text: #86efac;
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

        /* Fundo animado sutil */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                radial-gradient(ellipse at 20% 50%, rgba(245, 158, 11, 0.04) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 20%, rgba(245, 158, 11, 0.03) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .installer-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 520px;
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

        /* Logo e cabeçalho */
        .installer-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .installer-logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--accent), #d97706);
            border-radius: 14px;
            margin-bottom: 1.25rem;
            box-shadow: 0 8px 32px rgba(245, 158, 11, 0.2);
        }

        .installer-logo svg {
            width: 28px;
            height: 28px;
            fill: #0a0a0f;
        }

        .installer-header h1 {
            font-size: 1.625rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 0.375rem;
        }

        .installer-header p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        /* Card principal */
        .installer-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.3);
        }

        /* Seções do formulário */
        .form-section {
            margin-bottom: 1.75rem;
        }

        .form-section:last-of-type {
            margin-bottom: 0;
        }

        .section-title {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--accent);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border-color);
        }

        .section-title svg {
            width: 14px;
            height: 14px;
            fill: var(--accent);
            flex-shrink: 0;
        }

        /* Campos */
        .form-grid {
            display: grid;
            gap: 0.875rem;
        }

        .form-grid-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.875rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 0.375rem;
        }

        .form-group input {
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.625rem 0.875rem;
            font-size: 0.875rem;
            font-family: inherit;
            color: var(--text-primary);
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }

        .form-group input::placeholder {
            color: var(--text-muted);
        }

        .form-group input:focus {
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }

        .form-group .input-hint {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        /* Separador */
        .form-divider {
            height: 1px;
            background: var(--border-color);
            margin: 1.75rem 0;
        }

        /* Botão */
        .btn-install {
            width: 100%;
            padding: 0.8rem 1.5rem;
            font-size: 0.95rem;
            font-weight: 600;
            font-family: inherit;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            background: linear-gradient(135deg, var(--accent), #d97706);
            color: #0a0a0f;
            transition: all 0.2s;
            margin-top: 1.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            position: relative;
            overflow: hidden;
        }

        .btn-install:hover {
            background: linear-gradient(135deg, var(--accent-hover), #e5a00d);
            transform: translateY(-1px);
            box-shadow: 0 6px 24px rgba(245, 158, 11, 0.3);
        }

        .btn-install:active {
            transform: translateY(0);
        }

        .btn-install:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-install svg {
            width: 18px;
            height: 18px;
            fill: #0a0a0f;
        }

        /* Spinner de loading */
        .spinner {
            display: none;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(10, 10, 15, 0.3);
            border-top-color: #0a0a0f;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .btn-install.loading .spinner {
            display: block;
        }

        .btn-install.loading .btn-text,
        .btn-install.loading .btn-icon {
            display: none;
        }

        /* Alertas */
        .alert {
            padding: 0.875rem 1rem;
            border-radius: 10px;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.625rem;
            line-height: 1.5;
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .alert svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .alert-error {
            background: var(--error-bg);
            border: 1px solid var(--error-border);
            color: var(--error-text);
        }

        .alert-error svg {
            fill: var(--error-text);
        }

        .alert-success {
            background: var(--success-bg);
            border: 1px solid var(--success-border);
            color: var(--success-text);
        }

        .alert-success svg {
            fill: var(--success-text);
        }

        /* Lista de erros de validação */
        .validation-errors {
            list-style: none;
        }

        .validation-errors li {
            padding: 0.125rem 0;
        }

        /* Rodapé */
        .installer-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* Responsivo */
        @media (max-width: 540px) {
            .form-grid-row {
                grid-template-columns: 1fr;
            }

            .installer-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <div class="installer-container">
        <div class="installer-header">
            <div class="installer-logo">
                {{-- Ícone de engrenagem/setup --}}
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58a.49.49 0 0 0 .12-.61l-1.92-3.32a.49.49 0 0 0-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54a.48.48 0 0 0-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96a.49.49 0 0 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.07.62-.07.94s.02.64.07.94l-2.03 1.58a.49.49 0 0 0-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6A3.6 3.6 0 1 1 12 8.4a3.6 3.6 0 0 1 0 7.2z" />
                </svg>
            </div>
            <h1>Instalar Gaspar</h1>
            <p>Configure o banco de dados e crie o administrador</p>
        </div>

        <div class="installer-card">
            {{-- Mensagem de sucesso (após desinstalação) --}}
            @if (request()->query('desinstalado'))
                <div class="alert alert-success">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
                    </svg>
                    <span>Sistema desinstalado com sucesso. Configure novamente para reinstalar.</span>
                </div>
            @endif

            {{-- Mensagem de erro --}}
            @if (session('error'))
                <div class="alert alert-error">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" />
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            {{-- Erros de validação --}}
            @if ($errors->any())
                <div class="alert alert-error">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" />
                    </svg>
                    <ul class="validation-errors">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('instalar.post') }}" id="installForm">
                @csrf

                {{-- Seção: Banco de Dados --}}
                <div class="form-section">
                    <div class="section-title">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M12 3C7.58 3 4 4.79 4 7v10c0 2.21 3.58 4 8 4s8-1.79 8-4V7c0-2.21-3.58-4-8-4zm0 2c3.87 0 6 1.5 6 2s-2.13 2-6 2-6-1.5-6-2 2.13-2 6-2zM4 17v-2.34c1.37 1.07 3.6 1.74 6 1.84v2.44c-3.42-.24-6-1.64-6-2.94zm14 0c0 1.3-2.58 2.7-6 2.94v-2.44c2.4-.1 4.63-.77 6-1.84V17z" />
                        </svg>
                        Banco de Dados MySQL
                    </div>
                    <div class="form-grid">
                        <div class="form-grid-row">
                            <div class="form-group">
                                <label for="db_host">Host</label>
                                <input type="text" id="db_host" name="db_host" value="{{ old('db_host', 'localhost') }}"
                                    placeholder="localhost" required>
                            </div>
                            <div class="form-group">
                                <label for="db_port">Porta</label>
                                <input type="number" id="db_port" name="db_port" value="{{ old('db_port', '3306') }}"
                                    placeholder="3306" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="db_database">Nome do Banco</label>
                            <input type="text" id="db_database" name="db_database"
                                value="{{ old('db_database', 'gaspar') }}" placeholder="gaspar" required>
                            <span class="input-hint">Será criado automaticamente se não existir</span>
                        </div>
                        <div class="form-grid-row">
                            <div class="form-group">
                                <label for="db_username">Usuário MySQL</label>
                                <input type="text" id="db_username" name="db_username"
                                    value="{{ old('db_username', 'root') }}" placeholder="root" required>
                            </div>
                            <div class="form-group">
                                <label for="db_password">Senha MySQL</label>
                                <input type="password" id="db_password" name="db_password"
                                    value="{{ old('db_password') }}" placeholder="••••••••">
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-install" id="btnInstall">
                    <svg class="btn-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z" />
                    </svg>
                    <span class="btn-text">Instalar Gaspar</span>
                    <div class="spinner"></div>
                </button>
            </form>
        </div>

        <div class="installer-footer">
            Gaspar — Plataforma Institucional de Gestão por Processos
        </div>
    </div>

    <script>
        document.getElementById('installForm').addEventListener('submit', function () {
            const btn = document.getElementById('btnInstall');
            btn.classList.add('loading');
            btn.disabled = true;
        });
    </script>
</body>

</html>