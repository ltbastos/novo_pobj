<?php

declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Novo POBJ') ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <div class="shell">
        <aside class="sidebar">
            <div>
                <p class="sidebar-eyebrow">Projeto</p>
                <h1 class="sidebar-title">Novo POBJ</h1>
                <p class="sidebar-copy">Modo de teste com perfil simulado, sem autenticação, para validar hierarquia, filtros e visão de cada nível da rede.</p>
            </div>

            <div class="sidebar-panel">
                <p class="sidebar-section-title">Estado Atual</p>
                <ul class="status-list">
                    <li><span class="status-dot status-dot-live"></span> Banco conectado em <strong>novo_pobj</strong></li>
                    <li><span class="status-dot status-dot-live"></span> Perfil selecionável por funcional</li>
                    <li><span class="status-dot status-dot-live"></span> Dashboard POBJ com filtro hierárquico</li>
                </ul>
            </div>

            <div class="sidebar-panel">
                <p class="sidebar-section-title">Escopo</p>
                <p class="sidebar-copy compact">Primeiro slice focado em validar visão por GC, AG, GR, DR e DP com dados de teste reais no banco.</p>
            </div>
        </aside>

        <main class="main-content">
            <?php include __DIR__ . '/pobj_dashboard.php'; ?>
        </main>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>