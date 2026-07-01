<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalação do Gaspar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            display: inline-block;
            vertical-align: middle;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-lg">
        <h1 class="text-2xl font-bold mb-6 text-center text-gray-800">Instalação do Gaspar</h1>

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                {{ session('error') }}
            </div>
        @endif

        <form action="/install" method="POST" id="installForm" class="space-y-4">
            @csrf
            
            <div>
                <h2 class="text-lg font-semibold text-gray-700 border-b pb-2 mb-4">Banco de Dados</h2>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Host</label>
                        <input type="text" name="db_host" value="127.0.0.1" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Porta</label>
                        <input type="text" name="db_port" value="3306" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700">Nome do Banco</label>
                    <input type="text" name="db_database" value="gaspar" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div class="grid grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Usuário</label>
                        <input type="text" name="db_username" value="root" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Senha</label>
                        <input type="password" name="db_password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
            </div>

            <div class="mt-8">
                <h2 class="text-lg font-semibold text-gray-700 border-b pb-2 mb-4">Usuário Administrador</h2>
                
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700">Nome do Admin</label>
                    <input type="text" name="admin_name" value="Admin Gaspar" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700">E-mail</label>
                    <input type="email" name="admin_email" value="admin@gaspar.local" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700">Senha do Admin</label>
                    <input type="password" name="admin_password" required minlength="8" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Mínimo de 8 caracteres.</p>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t">
                <button type="submit" id="submitBtn" class="w-full bg-blue-600 text-white font-bold py-3 px-4 rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 flex items-center justify-center">
                    <span id="btnText">Instalar Gaspar</span>
                    <span id="loadingIcon" class="loader ml-3" style="display: none;"></span>
                </button>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('installForm').addEventListener('submit', function() {
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('submitBtn').classList.add('opacity-75', 'cursor-not-allowed');
            document.getElementById('btnText').innerText = 'Instalando banco de dados, aguarde...';
            document.getElementById('loadingIcon').style.display = 'inline-block';
        });
    </script>
</body>
</html>
