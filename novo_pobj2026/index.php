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

if ($requestMethod === 'POST' && ($_POST['action'] ?? '') === 'select_profile') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Falha de validacao do formulario de perfil.');
    }

    $selectedFunctional = trim((string) ($_POST['selected_functional'] ?? ''));

    if ($selectedFunctional !== '' && $profileService->profileExists($selectedFunctional)) {
        $_SESSION['selected_profile_functional'] = $selectedFunctional;
    }

    header('Location: ' . app_url(['page' => 'pobj-dashboard', 'periodo' => $_GET['periodo'] ?? ($_POST['periodo'] ?? '')]));
    exit;
}

$profiles = $profileService->listProfiles();
$selectedProfileFunctional = $_SESSION['selected_profile_functional'] ?? ($profiles[0]['funcional'] ?? null);

if ($selectedProfileFunctional !== null) {
    $_SESSION['selected_profile_functional'] = $selectedProfileFunctional;
}

$context = $selectedProfileFunctional !== null ? $profileService->getContext($selectedProfileFunctional) : null;
$periods = $dashboardService->listPeriods();
$selectedPeriodId = isset($_GET['periodo']) && is_string($_GET['periodo']) && $_GET['periodo'] !== ''
    ? $_GET['periodo']
    : ($periods[0]['id_periodo'] ?? null);

$dashboardData = $context !== null && $selectedPeriodId !== null
    ? $dashboardService->getDashboardData($context, $selectedPeriodId)
    : ['summary' => [], 'indicators' => []];

$pageTitle = 'Novo POBJ - Dashboard de Teste';

require __DIR__ . '/templates/layout.php';