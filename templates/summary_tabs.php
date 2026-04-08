<?php

declare(strict_types=1);

$tabItems = [
    'resumo' => ['label' => 'Resumo', 'icon' => 'layout-dashboard'],
    'detalhamento' => ['label' => 'Detalhamento', 'icon' => 'list-tree'],
    'rankings' => ['label' => 'Rankings', 'icon' => 'trophy'],
    'visao-executiva' => ['label' => 'Visão executiva', 'icon' => 'chart-column'],
    'simuladores' => ['label' => 'Simuladores', 'icon' => 'calculator'],
    'campanhas' => ['label' => 'Campanhas', 'icon' => 'megaphone'],
];

$baseTabQuery = ['page' => 'pobj-dashboard', 'periodo' => $selectedPeriodId ?? '', 'advanced_filters' => $advancedFiltersState ?? 'open'];

foreach (($summaryFilterValues ?? []) as $filterKey => $filterValue) {
    if ($filterValue !== '') {
        $baseTabQuery[$filterKey] = $filterValue;
    }
}

if (($requestedRankingGroup ?? '') !== '') {
    $baseTabQuery['grupo'] = $requestedRankingGroup;
}

if (($hasManualDateRange ?? false) === true) {
    $baseTabQuery['period_mode'] = 'manual';

    if (($requestedPeriodStart ?? '') !== '') {
        $baseTabQuery['period_start'] = $requestedPeriodStart;
    }

    if (($requestedPeriodEnd ?? '') !== '') {
        $baseTabQuery['period_end'] = $requestedPeriodEnd;
    }
}

$todayDate = (new DateTimeImmutable('today'))->format('Y-m-d');
$displayPeriodStartRaw = clamp_date_to_today($displayPeriodStart ?? ($selectedPeriod['dt_inicio_periodo'] ?? null));
$displayPeriodEndRaw = clamp_date_to_today($displayPeriodEnd ?? ($selectedPeriod['dt_fim_periodo'] ?? null));
$periodStart = format_date_br($displayPeriodStartRaw);
$periodEnd = format_date_br($displayPeriodEndRaw);
$hasPeriodRange = $periodStart !== '' && $periodEnd !== '';
$periodPickerQuery = array_merge($baseTabQuery, ['tab' => $activeTab ?? 'resumo']);
$periodPickerItems = [];

$periodPickerStartValue = $displayPeriodStartRaw !== '' ? $displayPeriodStartRaw : $todayDate;
$periodPickerEndValue = $displayPeriodEndRaw !== '' ? $displayPeriodEndRaw : $todayDate;
$periodPickerMinDate = '';

foreach (($periods ?? []) as $periodRow) {
    $periodStartRaw = trim((string) ($periodRow['dt_inicio_periodo'] ?? ''));

    if ($periodStartRaw === '' || strcmp($periodStartRaw, $todayDate) > 0) {
        continue;
    }

    if ($periodPickerMinDate === '' || strcmp($periodStartRaw, $periodPickerMinDate) < 0) {
        $periodPickerMinDate = $periodStartRaw;
    }

    $periodPickerItems[] = [
        'id' => (string) ($periodRow['id_periodo'] ?? ''),
        'start' => $periodStartRaw,
        'end' => clamp_date_to_today($periodRow['dt_fim_periodo'] ?? null),
    ];
}

$periodPickerJson = e((string) json_encode($periodPickerItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
?>
<section class="summary-tabs-section" aria-label="Navegação da tela Resumo">
    <div class="summary-tabs-shell">
        <nav class="summary-tabs" aria-label="Abas do módulo POBJ">
            <?php foreach ($tabItems as $tabId => $tabItem): ?>
                <?php $tabQuery = array_merge($baseTabQuery, ['tab' => $tabId]); ?>
                <a class="summary-tabs__item<?= $activeTab === $tabId ? ' is-active' : '' ?>" href="<?= e(app_url($tabQuery)) ?>" data-preserve-filters-state>
                    <span class="summary-tabs__icon" aria-hidden="true"><i data-lucide="<?= e($tabItem['icon']) ?>"></i></span>
                    <span class="summary-tabs__label"><?= e($tabItem['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="summary-tabs__meta">
            <span class="summary-tabs__period-label">
                <?php if ($hasPeriodRange): ?>
                    <span class="summary-tabs__period-copy">De <strong><?= e($periodStart) ?></strong> até <strong><?= e($periodEnd) ?></strong></span>
                <?php else: ?>
                    <span class="summary-tabs__period-copy"><?= e($selectedPeriod['descricao_periodo'] ?? 'Período atual') ?></span>
                <?php endif; ?>
            </span>
            <div class="summary-period-picker" data-period-picker>
                <button class="summary-tabs__meta-button" type="button" aria-label="Alterar data do período" aria-expanded="false" data-period-trigger>
                    <i data-lucide="chevron-down" aria-hidden="true"></i>
                    <span>Alterar data</span>
                </button>

                <div class="summary-period-picker__popover" hidden data-period-popover>
                    <form class="summary-period-picker__form" method="get" action="index.php" data-period-form data-period-options="<?= $periodPickerJson ?>">
                        <?php foreach ($periodPickerQuery as $queryKey => $queryValue): ?>
                            <?php if (in_array($queryKey, ['page', 'periodo', 'period_mode', 'period_start', 'period_end'], true) || $queryValue === '') {
                                continue;
                            } ?>
                            <input type="hidden" name="<?= e($queryKey) ?>" value="<?= e((string) $queryValue) ?>">
                        <?php endforeach; ?>

                        <input type="hidden" name="page" value="pobj-dashboard">
                        <input type="hidden" name="periodo" value="<?= e($selectedPeriodId ?? '') ?>" data-period-id>
                        <input type="hidden" name="period_mode" value="manual">

                        <div class="summary-period-picker__header">Alterar data</div>

                        <div class="summary-period-picker__fields">
                            <label class="summary-period-picker__field">
                                <input type="date" name="period_start" value="<?= e($periodPickerStartValue) ?>" max="<?= e($todayDate) ?>"<?= $periodPickerMinDate !== '' ? ' min="' . e($periodPickerMinDate) . '"' : '' ?> data-period-start>
                            </label>

                            <label class="summary-period-picker__field">
                                <input type="date" name="period_end" value="<?= e($periodPickerEndValue) ?>" max="<?= e($todayDate) ?>"<?= $periodPickerMinDate !== '' ? ' min="' . e($periodPickerMinDate) . '"' : '' ?> data-period-end>
                            </label>
                        </div>

                        <div class="summary-period-picker__actions">
                            <button class="summary-period-picker__button summary-period-picker__button--ghost" type="button" data-period-cancel>Cancelar</button>
                            <button class="summary-period-picker__button summary-period-picker__button--primary" type="submit">Salvar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>