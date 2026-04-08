<?php

declare(strict_types=1);

$filterPlaceholders = [
    'segmento' => 'Todos',
    'diretoria' => 'Todas',
    'regional' => 'Todas',
    'agencia' => 'Todas',
    'gerente_gestao' => 'Todos',
    'gerente' => 'Todos',
    'familia' => 'Selecione...',
    'indicadores' => 'Selecione...',
    'subindicador' => 'Selecione...',
    'status_indicadores' => 'Todos',
    'visao_acumulada' => 'Mensal',
];

$renderOptions = static function (array $options, ?string $selectedValue, string $placeholderLabel, string $placeholderValue = ''): void {
    echo '<option value="' . e($placeholderValue) . '">' . e($placeholderLabel) . '</option>';

    foreach ($options as $option) {
        $value = (string) ($option['value'] ?? '');
        $label = (string) ($option['label'] ?? '');

        if ($value === '' || $label === '') {
            continue;
        }

        echo '<option value="' . e($value) . '"' . selected_attr($value, $selectedValue) . '>' . e($label) . '</option>';
    }
};

$advancedFiltersExpanded = ($advancedFiltersState ?? 'open') !== 'closed';
?>
<section class="summary-filters-section" aria-label="Filtros da tela Resumo">
    <?php
    $clearFiltersQuery = [
        'page' => 'pobj-dashboard',
        'periodo' => $selectedPeriodId ?? '',
        'tab' => $activeTab ?? 'resumo',
        'advanced_filters' => $advancedFiltersExpanded ? 'open' : 'closed',
    ];

    if (($hasManualDateRange ?? false) === true) {
        $clearFiltersQuery['period_mode'] = 'manual';

        if (($requestedPeriodStart ?? '') !== '') {
            $clearFiltersQuery['period_start'] = $requestedPeriodStart;
        }

        if (($requestedPeriodEnd ?? '') !== '') {
            $clearFiltersQuery['period_end'] = $requestedPeriodEnd;
        }
    }

    $clearFiltersUrl = app_url($clearFiltersQuery);
    ?>
    <div class="filters-card">
        <header class="filters-card__header">
            <div>
                <h1 class="filters-card__title">POBJ Produções</h1>
                <p class="filters-card__subtitle">Acompanhe dados atualizados de performance.</p>
            </div>
        </header>

        <form class="filters-form" method="get" action="index.php">
            <input type="hidden" name="page" value="pobj-dashboard">
            <input type="hidden" name="periodo" value="<?= e($selectedPeriodId ?? '') ?>">
            <input type="hidden" name="tab" value="<?= e($activeTab ?? 'resumo') ?>">
            <input type="hidden" name="advanced_filters" value="<?= e($advancedFiltersExpanded ? 'open' : 'closed') ?>" data-filters-state>
            <?php if (($activeTab ?? 'resumo') === 'rankings' && ($requestedRankingGroup ?? '') !== ''): ?>
                <input type="hidden" name="grupo" value="<?= e($requestedRankingGroup) ?>">
            <?php endif; ?>
            <?php if (($hasManualDateRange ?? false) === true): ?>
                <input type="hidden" name="period_mode" value="manual">
                <?php if (($requestedPeriodStart ?? '') !== ''): ?>
                    <input type="hidden" name="period_start" value="<?= e($requestedPeriodStart) ?>">
                <?php endif; ?>
                <?php if (($requestedPeriodEnd ?? '') !== ''): ?>
                    <input type="hidden" name="period_end" value="<?= e($requestedPeriodEnd) ?>">
                <?php endif; ?>
            <?php endif; ?>

            <div class="filters-grid filters-grid--top">
                <div class="filter-field">
                    <label class="filter-field__label" for="filter-segmento">Segmento</label>
                    <select id="filter-segmento" class="filter-field__control" name="segmento" data-filter-cascade data-enhanced-select="search" data-enhanced-label="Segmento" data-enhanced-placeholder="<?= e($filterPlaceholders['segmento']) ?>">
                        <?php $renderOptions($summaryFilterOptions['segmento'] ?? [], $summaryFilterValues['segmento'] ?? '', $filterPlaceholders['segmento']); ?>
                    </select>
                </div>

                <div class="filter-field">
                    <label class="filter-field__label" for="filter-diretoria">Diretoria</label>
                    <select id="filter-diretoria" class="filter-field__control" name="diretoria" data-filter-cascade data-enhanced-select="search" data-enhanced-label="Diretoria" data-enhanced-placeholder="<?= e($filterPlaceholders['diretoria']) ?>">
                        <?php $renderOptions($summaryFilterOptions['diretoria'] ?? [], $summaryFilterValues['diretoria'] ?? '', $filterPlaceholders['diretoria']); ?>
                    </select>
                </div>

                <div class="filter-field">
                    <label class="filter-field__label" for="filter-regional">Regional</label>
                    <select id="filter-regional" class="filter-field__control" name="regional" data-filter-cascade data-enhanced-select="search" data-enhanced-label="Regional" data-enhanced-placeholder="<?= e($filterPlaceholders['regional']) ?>">
                        <?php $renderOptions($summaryFilterOptions['regional'] ?? [], $summaryFilterValues['regional'] ?? '', $filterPlaceholders['regional']); ?>
                    </select>
                </div>

                <div class="filters-actions">
                    <button class="filters-button filters-button--primary" type="submit">Filtrar</button>
                    <a class="filters-button filters-button--ghost" href="<?= e($clearFiltersUrl) ?>" data-preserve-filters-state>Limpar filtros</a>
                </div>
            </div>

            <div class="filters-toggle-row">
                <button class="filters-advanced-toggle<?= $advancedFiltersExpanded ? ' is-open' : '' ?>" type="button" aria-expanded="<?= $advancedFiltersExpanded ? 'true' : 'false' ?>" aria-controls="advanced-filters" data-filters-toggle>
                    <?= $advancedFiltersExpanded ? 'Fechar filtros avançados' : 'Abrir filtros avançados' ?>
                </button>
            </div>

            <div class="filters-advanced-shell<?= $advancedFiltersExpanded ? '' : ' is-collapsed' ?>" id="advanced-filters" data-filters-shell>
                <div class="filters-advanced" data-filters-advanced>
                    <div class="filters-grid filters-grid--advanced">
                    <div class="filter-field">
                        <label class="filter-field__label" for="filter-agencia">Agência</label>
                        <select id="filter-agencia" class="filter-field__control" name="agencia" data-filter-cascade data-enhanced-select="search" data-enhanced-label="Agência" data-enhanced-placeholder="<?= e($filterPlaceholders['agencia']) ?>">
                            <?php $renderOptions($summaryFilterOptions['agencia'] ?? [], $summaryFilterValues['agencia'] ?? '', $filterPlaceholders['agencia']); ?>
                        </select>
                    </div>

                    <div class="filter-field">
                        <label class="filter-field__label" for="filter-gerente-gestao">Gerente de gestão</label>
                        <select id="filter-gerente-gestao" class="filter-field__control" name="gerente_gestao" data-filter-cascade data-enhanced-select="search" data-enhanced-label="Gerente de gestão" data-enhanced-placeholder="<?= e($filterPlaceholders['gerente_gestao']) ?>">
                            <?php $renderOptions($summaryFilterOptions['gerente_gestao'] ?? [], $summaryFilterValues['gerente_gestao'] ?? '', $filterPlaceholders['gerente_gestao']); ?>
                        </select>
                    </div>

                    <div class="filter-field">
                        <label class="filter-field__label" for="filter-gerente">Gerente</label>
                        <select id="filter-gerente" class="filter-field__control" name="gerente" data-filter-cascade data-enhanced-select="search" data-enhanced-label="Gerente" data-enhanced-placeholder="<?= e($filterPlaceholders['gerente']) ?>">
                            <?php $renderOptions($summaryFilterOptions['gerente'] ?? [], $summaryFilterValues['gerente'] ?? '', $filterPlaceholders['gerente']); ?>
                        </select>
                    </div>

                    <div class="filter-field">
                        <label class="filter-field__label" for="filter-familia">Família</label>
                        <select id="filter-familia" class="filter-field__control" name="familia" data-filter-cascade data-enhanced-select="search" data-enhanced-label="Família" data-enhanced-placeholder="<?= e($filterPlaceholders['familia']) ?>">
                            <?php $renderOptions($summaryFilterOptions['familia'] ?? [], $summaryFilterValues['familia'] ?? '', $filterPlaceholders['familia']); ?>
                        </select>
                    </div>

                    <div class="filter-field">
                        <label class="filter-field__label" for="filter-indicadores">Indicadores</label>
                        <select id="filter-indicadores" class="filter-field__control" name="indicadores" data-filter-cascade data-enhanced-select="search" data-enhanced-label="Indicadores" data-enhanced-placeholder="<?= e($filterPlaceholders['indicadores']) ?>">
                            <?php $renderOptions($summaryFilterOptions['indicadores'] ?? [], $summaryFilterValues['indicadores'] ?? '', $filterPlaceholders['indicadores']); ?>
                        </select>
                    </div>

                    <div class="filter-field">
                        <label class="filter-field__label" for="filter-subindicador">Subindicador</label>
                        <select id="filter-subindicador" class="filter-field__control" name="subindicador" data-enhanced-select="search" data-enhanced-label="Subindicador" data-enhanced-placeholder="<?= e($filterPlaceholders['subindicador']) ?>">
                            <?php $renderOptions($summaryFilterOptions['subindicador'] ?? [], $summaryFilterValues['subindicador'] ?? '', $filterPlaceholders['subindicador']); ?>
                        </select>
                    </div>

                    <div class="filter-field">
                        <label class="filter-field__label" for="filter-status-indicadores">Status dos indicadores</label>
                        <select id="filter-status-indicadores" class="filter-field__control" name="status_indicadores" data-enhanced-select="basic" data-enhanced-label="Status dos indicadores" data-enhanced-placeholder="<?= e($filterPlaceholders['status_indicadores']) ?>">
                            <?php $renderOptions($summaryFilterOptions['status_indicadores'] ?? [], $summaryFilterValues['status_indicadores'] ?? '', $filterPlaceholders['status_indicadores']); ?>
                        </select>
                    </div>

                    <div class="filter-field">
                        <label class="filter-field__label" for="filter-visao-acumulada">Visão acumulada</label>
                        <select id="filter-visao-acumulada" class="filter-field__control" name="visao_acumulada" data-enhanced-select="basic" data-enhanced-label="Visão acumulada" data-enhanced-placeholder="<?= e($filterPlaceholders['visao_acumulada']) ?>">
                            <?php foreach (($summaryFilterOptions['visao_acumulada'] ?? []) as $option): ?>
                                <option value="<?= e((string) ($option['value'] ?? '')) ?>"<?= selected_attr((string) ($option['value'] ?? ''), $summaryFilterValues['visao_acumulada'] ?? '') ?>><?= e((string) ($option['label'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            </div>
        </form>
    </div>
</section>