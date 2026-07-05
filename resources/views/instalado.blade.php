<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Instalação concluída — Sistema Gaspar">
    <title>Instalação Concluída — Gaspar</title>
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
            --accent: #f59e0b;
            --accent-hover: #fbbf24;
            --success-green: #22c55e;
            --success-glow: rgba(34, 197, 94, 0.15);
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
                radial-gradient(ellipse at 50% 30%, rgba(34, 197, 94, 0.05) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 70%, rgba(245, 158, 11, 0.03) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .success-container {
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

        /* Checkmark animado */
        .success-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 72px;
            height: 72px;
            background: var(--success-glow);
            border-radius: 50%;
            margin-bottom: 1.5rem;
            animation: pulseOnce 0.6s ease-out 0.3s;
        }

        @keyframes pulseOnce {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.3); }
            50% { transform: scale(1.08); box-shadow: 0 0 0 16px rgba(34, 197, 94, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
        }

        .success-icon svg {
            width: 36px;
            height: 36px;
        }

        .success-icon .checkmark-circle {
            stroke: var(--success-green);
            stroke-width: 2;
            fill: none;
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            animation: drawCircle 0.6s ease-out 0.2s forwards;
        }

        .success-icon .checkmark-check {
            stroke: var(--success-green);
            stroke-width: 3;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            animation: drawCheck 0.4s ease-out 0.7s forwards;
        }

        @keyframes drawCircle {
            to { stroke-dashoffset: 0; }
        }

        @keyframes drawCheck {
            to { stroke-dashoffset: 0; }
        }

        .success-container h1 {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
            color: var(--success-green);
        }

        .success-container > p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 2rem;
        }

        /* Card com credenciais */
        .credentials-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .credentials-title {
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

        .credentials-title svg {
            width: 14px;
            height: 14px;
            fill: var(--accent);
        }

        .credential-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.625rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .credential-row:last-child {
            border-bottom: none;
        }

        .credential-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .credential-value {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
            font-family: 'SFMono-Regular', 'Consolas', 'Liberation Mono', monospace;
        }

        /* Aviso de segurança */
        .warning-box {
            background: rgba(245, 158, 11, 0.08);
            border: 1px solid rgba(245, 158, 11, 0.2);
            border-radius: 10px;
            padding: 0.875rem 1rem;
            margin-bottom: 1.75rem;
            display: flex;
            align-items: flex-start;
            gap: 0.625rem;
            text-align: left;
        }

        .warning-box svg {
            width: 18px;
            height: 18px;
            fill: var(--accent);
            flex-shrink: 0;
            margin-top: 1px;
        }

        .warning-box span {
            font-size: 0.8rem;
            color: var(--accent);
            line-height: 1.5;
        }

        /* Botão de acesso */
        .btn-access {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
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
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-access:hover {
            background: linear-gradient(135deg, var(--accent-hover), #e5a00d);
            transform: translateY(-1px);
            box-shadow: 0 6px 24px rgba(245, 158, 11, 0.3);
        }

        .btn-access:active {
            transform: translateY(0);
        }

        .btn-access svg {
            width: 18px;
            height: 18px;
            fill: #0a0a0f;
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
    <div class="success-container">
        {{-- Checkmark animado --}}
        <div class="success-icon">
            <svg viewBox="0 0 52 52" xmlns="http://www.w3.org/2000/svg">
                <circle class="checkmark-circle" cx="26" cy="26" r="25"/>
                <path class="checkmark-check" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
            </svg>
        </div>

        <h1>Instalação Concluída!</h1>
        <p>O Gaspar foi instalado com sucesso no seu servidor.</p>

        {{-- Credenciais do Admin --}}
        <div class="credentials-card">
            <div class="credentials-title">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1s3.1 1.39 3.1 3.1v2z"/>
                </svg>
                Dados do Administrador
            </div>

            <div class="credential-row">
                <span class="credential-label">Nome</span>
                <span class="credential-value">{{ $admin_name }}</span>
            </div>
            <div class="credential-row">
                <span class="credential-label">E-mail</span>
                <span class="credential-value">{{ $admin_email }}</span>
            </div>
            <div class="credential-row">
                <span class="credential-label">Senha</span>
                <span class="credential-value">{{ $admin_password }}</span>
            </div>
        </div>

        {{-- Aviso --}}
        <div class="warning-box">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/>
            </svg>
            <span>Anote o e-mail e a senha acima. Após sair desta página, a senha não poderá ser visualizada novamente.</span>
        </div>

        {{-- Botão para acessar o sistema --}}
        <a href="/admin" class="btn-access">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
            </svg>
            Acessar o Sistema
        </a>

        <div class="installer-footer">
            Gaspar — Plataforma Institucional de Gestão por Processos
        </div>
    </div>
</body>
</html>
