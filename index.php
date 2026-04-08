<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Database;
use App\Services\PobjDashboardService;
use App\Services\ProfileSimulationService;

$connection = Database::connection();
$profileService = new ProfileSimulationService($connection);
$dashboardService = new PobjDashboardService($connection);

$requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$profileRedirectKeys = [
    'periodo',
    'tab',
    'advanced_filters',
    'grupo',
    'period_mode',
    'period_start',
    'period_end',
    'segmento',
    'diretoria',
    'regional',
    'agencia',
    'gerente_gestao',
    'gerente',
    'familia',
    'indicadores',
    'subindicador',
    'status_indicadores',
    'visao_acumulada',
];

if ($requestMethod === 'POST' && ($_POST['action'] ?? '') === 'select_profile') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Falha de validacao do formulario de perfil.');
    }

    $selectedFunctional = trim((string) ($_POST['selected_functional'] ?? ''));

    if ($selectedFunctional !== '' && $profileService->profileExists($selectedFunctional)) {
        $_SESSION['selected_profile_functional'] = $selectedFunctional;
    }

    $redirectQuery = ['page' => 'pobj-dashboard'];
    $requestedRedirectQuery = $_POST['redirect_query'] ?? null;

    if (is_array($requestedRedirectQuery)) {
        foreach ($requestedRedirectQuery as $queryKey => $queryValue) {
            if (!is_string($queryKey) || !in_array($queryKey, $profileRedirectKeys, true) || is_array($queryValue)) {
                continue;
            }

            $queryValue = trim((string) $queryValue);

            if ($queryValue === '') {
                continue;
            }

            $redirectQuery[$queryKey] = $queryValue;
        }
    }

    if (!isset($redirectQuery['periodo'])) {
        $redirectPeriod = trim((string) ($_POST['periodo'] ?? $_GET['periodo'] ?? ''));

        if ($redirectPeriod !== '') {
            $redirectQuery['periodo'] = $redirectPeriod;
        }
    }

    if (!isset($redirectQuery['tab'])) {
        $redirectQuery['tab'] = 'resumo';
    }

    header('Location: ' . app_url($redirectQuery));
    exit;
}

$profiles = $profileService->listProfiles();
$selectedProfileFunctional = $_SESSION['selected_profile_functional'] ?? ($profiles[0]['funcional'] ?? null);

if ($selectedProfileFunctional !== null) {
    $_SESSION['selected_profile_functional'] = $selectedProfileFunctional;
}

$context = $selectedProfileFunctional !== null ? $profileService->getContext($selectedProfileFunctional) : null;
$periods = $dashboardService->listPeriods();
$requestedPeriodStart = trim((string) ($_GET['period_start'] ?? ''));
$requestedPeriodEnd = trim((string) ($_GET['period_end'] ?? ''));
$requestedPeriodMode = trim((string) ($_GET['period_mode'] ?? ''));
$hasManualDateRange = $requestedPeriodMode === 'manual' && ($requestedPeriodStart !== '' || $requestedPeriodEnd !== '');
$hasLegacyAutoDateRange = !$hasManualDateRange && ($requestedPeriodStart !== '' || $requestedPeriodEnd !== '');
$requestedAccumulatedView = trim((string) ($_GET['visao_acumulada'] ?? 'MENSAL'));
$displayPeriodStart = $requestedPeriodStart;
$displayPeriodEnd = $requestedPeriodEnd;

if (!$hasManualDateRange) {
    $accumulatedRange = resolve_accumulated_view_date_range($requestedAccumulatedView);
    $displayPeriodStart = $accumulatedRange['start'];
    $displayPeriodEnd = $accumulatedRange['end'];
}

$selectedPeriodId = isset($_GET['periodo']) && is_string($_GET['periodo']) && $_GET['periodo'] !== ''
    ? $_GET['periodo']
    : ($periods[0]['id_periodo'] ?? null);

if ($hasManualDateRange) {
    $selectedPeriodId = resolve_period_id_by_date_range($periods, $requestedPeriodStart, $requestedPeriodEnd, $selectedPeriodId);
}

if ($hasLegacyAutoDateRange && $context !== null && $selectedPeriodId !== null && !$dashboardService->periodHasVisibleData($context, $selectedPeriodId)) {
    foreach ($periods as $periodRow) {
        $candidatePeriodId = trim((string) ($periodRow['id_periodo'] ?? ''));

        if ($candidatePeriodId === '' || !$dashboardService->periodHasVisibleData($context, $candidatePeriodId)) {
            continue;
        }

        $selectedPeriodId = $candidatePeriodId;
        break;
    }
}

if ($requestMethod === 'GET' && $hasLegacyAutoDateRange) {
    $canonicalQuery = ['page' => trim((string) ($_GET['page'] ?? 'pobj-dashboard'))];

    foreach ($_GET as $queryKey => $queryValue) {
        if (!is_string($queryKey) || in_array($queryKey, ['period_mode', 'period_start', 'period_end'], true) || is_array($queryValue)) {
            continue;
        }

        $trimmedValue = trim((string) $queryValue);

        if ($trimmedValue === '') {
            continue;
        }

        $canonicalQuery[$queryKey] = $trimmedValue;
    }

    if ($selectedPeriodId !== null) {
        $canonicalQuery['periodo'] = $selectedPeriodId;
    }

    header('Location: ' . app_url($canonicalQuery));
    exit;
}

$requestedAdvancedFiltersState = trim((string) ($_GET['advanced_filters'] ?? 'open'));
$advancedFiltersState = in_array($requestedAdvancedFiltersState, ['open', 'closed'], true)
    ? $requestedAdvancedFiltersState
    : 'open';
$requestedRankingGroup = trim((string) ($_GET['grupo'] ?? ''));

$requestedSummaryFilters = [
    'segmento' => trim((string) ($_GET['segmento'] ?? '')),
    'diretoria' => trim((string) ($_GET['diretoria'] ?? '')),
    'regional' => trim((string) ($_GET['regional'] ?? '')),
    'agencia' => trim((string) ($_GET['agencia'] ?? '')),
    'gerente_gestao' => trim((string) ($_GET['gerente_gestao'] ?? '')),
    'gerente' => trim((string) ($_GET['gerente'] ?? '')),
    'familia' => trim((string) ($_GET['familia'] ?? '')),
    'indicadores' => trim((string) ($_GET['indicadores'] ?? '')),
    'subindicador' => trim((string) ($_GET['subindicador'] ?? '')),
    'status_indicadores' => trim((string) ($_GET['status_indicadores'] ?? '')),
    'visao_acumulada' => trim((string) ($_GET['visao_acumulada'] ?? 'MENSAL')),
];

$summaryFilters = $context !== null && $selectedPeriodId !== null
    ? $dashboardService->prepareSummaryFilters($context, $selectedPeriodId, $requestedSummaryFilters)
    : [
        'options' => [
            'segmento' => [],
            'diretoria' => [],
            'regional' => [],
            'agencia' => [],
            'gerente_gestao' => [],
            'gerente' => [],
            'familia' => [],
            'indicadores' => [],
            'subindicador' => [],
            'status_indicadores' => [],
            'visao_acumulada' => [
                ['value' => 'MENSAL', 'label' => 'Mensal'],
                ['value' => 'SEMESTRAL', 'label' => 'Semestral'],
                ['value' => 'ANUAL', 'label' => 'Anual'],
            ],
        ],
        'values' => $requestedSummaryFilters,
    ];

$summaryFilterOptions = $summaryFilters['options'];
$summaryFilterValues = $summaryFilters['values'];

$allowedTabs = ['resumo', 'detalhamento', 'rankings', 'visao-executiva', 'simuladores', 'campanhas'];
$requestedTab = trim((string) ($_GET['tab'] ?? 'resumo'));
$activeTab = in_array($requestedTab, $allowedTabs, true) ? $requestedTab : 'resumo';

$dashboardData = $context !== null && $selectedPeriodId !== null
    ? $dashboardService->getDashboardData($context, $selectedPeriodId, $summaryFilterValues, $displayPeriodStart, $displayPeriodEnd)
    : ['summary' => [], 'indicators' => []];

$detailData = $context !== null && $selectedPeriodId !== null && $activeTab === 'detalhamento'
    ? $dashboardService->getDetailData($context, $selectedPeriodId, $summaryFilterValues, $displayPeriodStart, $displayPeriodEnd)
    : ['summary' => [], 'rows' => []];

$rankingGroupOptions = $context !== null && $selectedPeriodId !== null && $activeTab === 'rankings'
    ? $dashboardService->listRankingGroupOptions($context, $selectedPeriodId, $summaryFilterValues, $displayPeriodStart, $displayPeriodEnd)
    : [];

$requestedRankingGroup = $dashboardService->sanitizeFilterSelection($requestedRankingGroup, $rankingGroupOptions);
$rankingFilters = array_merge($summaryFilterValues, ['grupo' => $requestedRankingGroup]);

$rankingData = $context !== null && $selectedPeriodId !== null && $activeTab === 'rankings'
    ? $dashboardService->getRankingData($context, $selectedPeriodId, $rankingFilters, $displayPeriodStart, $displayPeriodEnd)
    : ['level' => 'gerente_gestao', 'level_label' => 'Gerente de gestão', 'rows' => []];

$executiveData = $context !== null && $selectedPeriodId !== null && $activeTab === 'visao-executiva'
    ? $dashboardService->getExecutiveViewData($context, $selectedPeriodId, $summaryFilterValues, $displayPeriodStart, $displayPeriodEnd)
    : [
        'focus' => ['level' => 'regional', 'label' => 'Regional', 'ranking_title' => 'Desempenho por Regional', 'status_title' => 'Status por Regional'],
        'reference' => ['period_label' => '', 'range_label' => '', 'month_label' => '', 'window_label' => 'Últimos 6 meses'],
        'kpis' => [],
        'chart' => ['labels' => [], 'series' => [], 'max_value' => 100.0],
        'ranking' => ['top' => [], 'bottom' => []],
        'status' => ['hit' => [], 'quase' => [], 'longe' => [], 'summary' => ['hit' => 0, 'quase' => 0, 'longe' => 0]],
        'heatmap' => [
            'default_mode' => 'secoes',
            'secoes' => ['title' => '', 'row_axis_label' => 'GR', 'row_axis_sublabel' => 'Famílias', 'rows' => [], 'columns' => [], 'data' => []],
            'meta' => ['title' => '', 'row_axis_label' => 'Hierarquia', 'row_axis_sublabel' => 'Mês', 'rows' => [], 'months' => [], 'data' => []],
        ],
    ];

$annualPerformanceData = $context !== null
    && ($summaryFilterValues['visao_acumulada'] ?? 'MENSAL') === 'ANUAL'
    && $activeTab === 'resumo'
    ? $dashboardService->getAnnualPerformanceData($context, $summaryFilterValues, $displayPeriodEnd)
    : ['year' => (int) date('Y'), 'rows' => [], 'summary' => ['atingiu' => 0, 'quase' => 0, 'distante' => 0]];

$selectedPeriod = null;

foreach ($periods as $periodRow) {
    if (($periodRow['id_periodo'] ?? null) === $selectedPeriodId) {
        $selectedPeriod = $periodRow;
        break;
    }
}

$legacyBusinessSnapshot = resolve_business_day_snapshot(
    $displayPeriodStart !== '' ? $displayPeriodStart : ($selectedPeriod['dt_inicio_periodo'] ?? null),
    $displayPeriodEnd !== '' ? $displayPeriodEnd : ($selectedPeriod['dt_fim_periodo'] ?? null)
);

$pageTitle = 'Novo POBJ - Dashboard de Teste';

require __DIR__ . '/templates/layout.php';