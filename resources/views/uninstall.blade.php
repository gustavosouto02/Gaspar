<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Desinstalação do Gaspar</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-lg border-t-4 border-red-500">
        <h1 class="text-2xl font-bold mb-4 text-center text-red-600">Área de Risco: Desinstalar</h1>

        <p class="text-gray-600 mb-6 text-center">
            Atenção: A desinstalação removerá <strong>TODAS</strong> as tabelas e dados do banco de dados configurado no servidor. 
            Esta ação é irreversível.
        </p>

        <form action="/uninstall" method="POST" id="uninstallForm">
            @csrf
            
            <button type="submit" class="w-full bg-red-600 text-white font-bold py-3 px-4 rounded hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50">
                Sim, apagar todos os dados
            </button>
        </form>

        <div class="mt-4 text-center">
            <a href="/" class="text-blue-500 hover:underline text-sm">Cancelar e voltar em segurança</a>
        </div>
    </div>

    <script>
        document.getElementById('uninstallForm').addEventListener('submit', function(e) {
            var confirmed = confirm("TEM CERTEZA DISSO?\n\nEsta ação apagará todas as demandas, processos, usuários e relatórios. O banco será limpo completamente.");
            
            if (!confirmed) {
                e.preventDefault();
            } else {
                // Previne duplo clique
                const btn = this.querySelector('button');
                btn.disabled = true;
                btn.innerText = 'Apagando dados...';
                btn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        });
    </script>
</body>
</html>
