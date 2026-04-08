<?php

declare(strict_types=1);

$layoutProfile = $context['profile'] ?? null;
$headerName = $layoutProfile['nome'] ?? 'Usuario de teste';
$headerFirstName = first_name($headerName);
$headerInitials = user_initials($headerName);
$dashboardUrl = app_url(['page' => 'pobj-dashboard', 'periodo' => $selectedPeriodId ?? '']);
$profileSwitchQuery = [
    'periodo' => (string) ($selectedPeriodId ?? ''),
    'tab' => $activeTab ?? 'resumo',
    'advanced_filters' => $advancedFiltersState ?? 'open',
];

foreach (($summaryFilterValues ?? []) as $filterKey => $filterValue) {
    if (!is_string($filterValue) || $filterValue === '') {
        continue;
    }

    $profileSwitchQuery[$filterKey] = $filterValue;
}

if (($requestedRankingGroup ?? '') !== '') {
    $profileSwitchQuery['grupo'] = $requestedRankingGroup;
}

if (($hasManualDateRange ?? false) === true) {
    $profileSwitchQuery['period_mode'] = 'manual';

    if (($requestedPeriodStart ?? '') !== '') {
        $profileSwitchQuery['period_start'] = $requestedPeriodStart;
    }

    if (($requestedPeriodEnd ?? '') !== '') {
        $profileSwitchQuery['period_end'] = $requestedPeriodEnd;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Novo POBJ') ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
    <noscript><style>.global-loading-overlay{display:none !important;}</style></noscript>
</head>
<body class="is-booting">
    <header class="topbar" role="banner">
        <nav id="main-navigation" class="topbar__left" aria-label="Navegacao principal">
            <a href="<?= e($dashboardUrl) ?>" class="brand">
                <img class="brand__logo" src="assets/img/logo-bradesco-white.svg" alt="Bradesco">
            </a>
        </nav>

        <nav class="topbar__right" aria-label="Menu do usuario">
            <?php if (($profiles ?? []) !== []): ?>
                <form class="topbar-profile" method="post" action="index.php" aria-label="Selecionar usuario logado temporario">
                    <input type="hidden" name="action" value="select_profile">
                    <?= csrf_field() ?>
                    <?php foreach ($profileSwitchQuery as $queryKey => $queryValue): ?>
                        <?php if ($queryValue === '') {
                            continue;
                        } ?>
                        <input type="hidden" name="redirect_query[<?= e((string) $queryKey) ?>]" value="<?= e((string) $queryValue) ?>">
                    <?php endforeach; ?>

                    <label class="topbar-profile__label" for="topbar-profile-functional">Usuario logado</label>
                    <div class="topbar-profile__field">
                        <select id="topbar-profile-functional" name="selected_functional" class="topbar-profile__select" data-autosubmit aria-label="Selecionar usuario logado temporario">
                            <?php foreach ($profiles as $profileItem): ?>
                                <?php
                                $profileOptionName = trim((string) ($profileItem['nome'] ?? ''));
                                $profileOptionLevel = trim((string) ($profileItem['nivel'] ?? ''));
                                $profileOptionJunction = trim((string) ($profileItem['juncao_principal'] ?? ''));
                                $profileOptionMeta = $profileOptionLevel;

                                if ($profileOptionJunction !== '') {
                                    $profileOptionMeta .= ' • ' . $profileOptionJunction;
                                }

                                $profileOptionLabel = $profileOptionMeta !== ''
                                    ? $profileOptionName . ' • ' . $profileOptionMeta
                                    : $profileOptionName;
                                ?>
                                <option value="<?= e((string) ($profileItem['funcional'] ?? '')) ?>"<?= selected_attr((string) ($profileItem['funcional'] ?? ''), $selectedProfileFunctional ?? null) ?>><?= e($profileOptionLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            <?php endif; ?>

            <div class="userbox" data-userbox>
                <button class="userbox__trigger" id="btn-user-menu" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="user-menu" aria-label="Menu do usuario: <?= e($headerName) ?>" data-userbox-trigger>
                    <span class="userbox__avatar"><?= e($headerInitials) ?></span>
                    <span class="userbox__identity">
                        <span class="userbox__name"><?= e($headerFirstName) ?></span>
                    </span>
                    <span class="userbox__chevron" aria-hidden="true"></span>
                </button>

                <div class="userbox__menu" id="user-menu" hidden data-userbox-menu>
                    <div class="userbox__menu-header">
                        <span class="userbox__menu-title">Links uteis</span>
                    </div>

                    <div class="userbox__menu-section">
                        <button class="userbox__menu-item" type="button" data-userbox-dismiss>Portal PJ</button>

                        <div class="userbox__submenu">
                            <button class="userbox__menu-item userbox__menu-item--has-sub" type="button" aria-expanded="false" aria-controls="user-submenu-manuais" data-userbox-submenu-trigger>
                                <span>Manuais</span>
                                <span class="userbox__submenu-chevron" aria-hidden="true"></span>
                            </button>

                            <div class="userbox__submenu-list" id="user-submenu-manuais" hidden data-userbox-submenu>
                                <button class="userbox__menu-item" type="button" data-userbox-dismiss>Manual do POBJ</button>
                                <button class="userbox__menu-item" type="button" data-userbox-dismiss>Manual do Omega</button>
                            </div>
                        </div>

                        <button class="userbox__menu-item" type="button" data-userbox-dismiss>Mapao de Oportunidades</button>
                        <button class="userbox__menu-item" type="button" data-userbox-dismiss>Omega</button>
                        <button class="userbox__menu-item" type="button" data-userbox-dismiss>EncantaBra</button>
                    </div>
                    <div class="userbox__divider"></div>
                    <button class="userbox__menu-item userbox__menu-item--logout" type="button" data-userbox-dismiss>
                        <span class="userbox__logout-icon" aria-hidden="true"></span>
                        <span>Sair</span>
                    </button>
                </div>
            </div>
        </nav>
    </header>

    <div class="app-shell">
        <main id="main-content" class="main-content" aria-label="Area principal da tela Resumo">
            <?php include __DIR__ . '/summary_filters.php'; ?>
            <?php include __DIR__ . '/summary_tabs.php'; ?>
            <?php include __DIR__ . '/summary_mode_toggle.php'; ?>
            <?php include __DIR__ . '/summary_cards.php'; ?>
            <?php include __DIR__ . '/summary_indicator_cards.php'; ?>
            <?php include __DIR__ . '/summary_legacy.php'; ?>
            <?php include __DIR__ . '/summary_annual.php'; ?>
            <?php include __DIR__ . '/rankings.php'; ?>
            <?php include __DIR__ . '/detail_view.php'; ?>
            <?php include __DIR__ . '/executive_view.php'; ?>
            <?php include __DIR__ . '/coming_soon.php'; ?>
        </main>
    </div>

    <div class="global-loading-overlay" aria-live="polite" aria-busy="true" data-loading-overlay>
        <div class="global-loading-overlay__panel">
            <div class="global-loading-overlay__mark" aria-hidden="true">
                <span class="global-loading-overlay__ring global-loading-overlay__ring--outer"></span>
                <span class="global-loading-overlay__ring global-loading-overlay__ring--inner"></span>
                <span class="global-loading-overlay__core"></span>
            </div>
            <strong class="global-loading-overlay__title">Atualizando painel</strong>
            <span class="global-loading-overlay__copy" data-loading-text>Carregando dados do POBJ...</span>
        </div>
    </div>

    <script src="assets/vendor/lucide.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>