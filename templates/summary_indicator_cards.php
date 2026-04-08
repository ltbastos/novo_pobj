<?php

declare(strict_types=1);

if (($activeTab ?? 'resumo') !== 'resumo' || (($summaryFilterValues['visao_acumulada'] ?? 'MENSAL') === 'ANUAL')) {
    return;
}

$indicatorRows = $dashboardData['indicators'] ?? [];

if ($indicatorRows === []) {
    return;
}

$familyGroups = [];
$cardIndex = 0;

foreach ($indicatorRows as $row) {
    $familyLabel = trim((string) ($row['nome_familia'] ?? ''));
    $familyKey = $familyLabel !== '' ? $familyLabel : 'Sem família';

    if (!isset($familyGroups[$familyKey])) {
        $familyGroups[$familyKey] = [
            'label' => $familyKey,
            'pontos_atingidos' => 0.0,
            'pontos_total' => 0.0,
            'items' => [],
        ];
    }

    $cardIndex++;
    $familyGroups[$familyKey]['pontos_atingidos'] += (float) ($row['pontos'] ?? 0.0);
    $familyGroups[$familyKey]['pontos_total'] += (float) ($row['pontos_meta'] ?? 0.0);
    $familyGroups[$familyKey]['items'][] = array_merge($row, ['card_index' => $cardIndex]);
}

$resolveIndicatorBadgeClass = static function (float $percent): string {
    if ($percent < 50) {
        return 'summary-product-card__badge--low';
    }

    if ($percent < 100) {
        return 'summary-product-card__badge--warn';
    }

    return 'summary-product-card__badge--ok';
};

$resolveIndicatorBarClass = static function (float $percent): string {
    if ($percent < 50) {
        return 'summary-product-card__track--low';
    }

    if ($percent < 100) {
        return 'summary-product-card__track--warn';
    }

    return 'summary-product-card__track--ok';
};

$metricLabel = static function (string $metric): string {
    $metric = strtoupper(trim($metric));

    if ($metric === 'PERCENTUAL') {
        return 'Percentual';
    }

    if ($metric === 'QUANTIDADE') {
        return 'Quantidade';
    }

    return 'Valor';
};

$formatCssPercent = static function (float $percent): string {
    return number_format(max(0, min(100, $percent)), 2, '.', '') . '%';
};
?>
<section class="summary-products-section" aria-label="Cards de indicadores" data-resumo-pane="cards">
    <?php foreach ($familyGroups as $familyGroup): ?>
        <section class="summary-products-family">
            <header class="summary-products-family__header">
                <h2 class="summary-products-family__title"><?= e($familyGroup['label']) ?></h2>
                <span class="summary-products-family__meta">Pontos: <?= e(format_points_readable((float) $familyGroup['pontos_atingidos'])) ?> / <?= e(format_points_readable((float) $familyGroup['pontos_total'])) ?></span>
            </header>

            <div class="summary-products-family__grid">
                <?php foreach ($familyGroup['items'] as $item): ?>
                    <?php
                    $progressPercent = (float) ($item['percentual_atingimento'] ?? 0.0);
                    $pointsAchieved = (float) ($item['pontos'] ?? 0.0);
                    $pointsTotal = (float) ($item['pontos_meta'] ?? 0.0);
                    $pointsPercent = $pointsTotal > 0 ? ($pointsAchieved / $pointsTotal) * 100 : 0.0;
                    $metaValue = format_metric((float) ($item['meta'] ?? 0.0), (string) ($item['id_tipo_indicador'] ?? 'VALOR'));
                    $metaValueReadable = format_metric_readable((float) ($item['meta'] ?? 0.0), (string) ($item['id_tipo_indicador'] ?? 'VALOR'));
                    $realizadoValue = format_metric((float) ($item['realizado'] ?? 0.0), (string) ($item['id_tipo_indicador'] ?? 'VALOR'));
                    $realizadoValueReadable = format_metric_readable((float) ($item['realizado'] ?? 0.0), (string) ($item['id_tipo_indicador'] ?? 'VALOR'));
                    $absoluteRatioValue = '';
                    $absoluteRatioLabel = '';

                    if (($item['id_tipo_indicador'] ?? '') === 'PERCENTUAL' && (bool) ($item['usa_base_absoluta'] ?? false)) {
                        $absoluteRatioValue = format_quantity_readable((float) ($item['realizado_absoluto'] ?? 0.0)) . ' / ' . format_quantity_readable((float) ($item['base_absoluta'] ?? 0.0));
                        $absoluteRatioLabel = trim((string) ($item['realizado_absoluto_label'] ?? 'Realizado')) . ' / ' . trim((string) ($item['base_absoluta_label'] ?? 'Base'));
                    }

                    $updatedAt = format_date_br($item['ultima_atualizacao'] ?? null);
                    $tooltipId = 'indicator-tooltip-' . (string) ($item['id_indicador'] ?? $item['card_index']);
                    ?>
                    <article class="summary-product-card" style="--card-delay: <?= e((string) (max(0, ((int) $item['card_index'] - 1) * 70))) ?>ms; --bar-delay: <?= e((string) (200 + max(0, ((int) $item['card_index'] - 1) * 70))) ?>ms;" data-tip-card>
                        <div class="summary-product-card__title">
                            <span class="summary-product-card__icon" aria-hidden="true">
                                <i data-lucide="chart-line"></i>
                            </span>
                            <span class="summary-product-card__name" title="<?= e((string) ($item['nome_indicador'] ?? '')) ?>"><?= e((string) ($item['nome_indicador'] ?? '')) ?></span>
                            <button class="summary-product-card__badge <?= e($resolveIndicatorBadgeClass($progressPercent)) ?>" type="button" aria-label="Atingimento do indicador: <?= e(number_format($progressPercent, 1, ',', '.')) ?>%" aria-describedby="<?= e($tooltipId) ?>" data-tip-trigger>
                                <?= e(number_format($progressPercent, $progressPercent >= 100 ? 0 : 1, ',', '.')) ?>%
                            </button>
                            <div class="summary-product-card__tooltip" id="<?= e($tooltipId) ?>" role="tooltip" data-tip>
                                <h3 class="summary-product-card__tooltip-title">Resumo do indicador</h3>
                                <div class="summary-product-card__tooltip-row"><span>Meta</span><strong><?= e($metaValue) ?></strong></div>
                                <div class="summary-product-card__tooltip-row"><span>Realizado</span><strong><?= e($realizadoValue) ?></strong></div>
                                <?php if ($absoluteRatioValue !== ''): ?>
                                    <div class="summary-product-card__tooltip-row"><span><?= e($absoluteRatioLabel) ?></span><strong><?= e($absoluteRatioValue) ?></strong></div>
                                <?php endif; ?>
                                <div class="summary-product-card__tooltip-row"><span>Atingimento</span><strong><?= e(number_format($progressPercent, 1, ',', '.')) ?>%</strong></div>
                                <div class="summary-product-card__tooltip-row"><span>Pontos</span><strong><?= e(format_points_readable($pointsAchieved)) ?> / <?= e(format_points_readable($pointsTotal)) ?></strong></div>
                                <div class="summary-product-card__tooltip-row"><span>Peso</span><strong><?= e(format_points_readable((float) ($item['peso'] ?? 0.0))) ?></strong></div>
                                <div class="summary-product-card__tooltip-row"><span>Variável estimada</span><strong><?= e(format_currency_readable((float) ($item['variavel'] ?? 0.0))) ?></strong></div>
                                <div class="summary-product-card__tooltip-row"><span>Atualizado em</span><strong><?= e($updatedAt !== '' ? $updatedAt : 'N/A') ?></strong></div>
                            </div>
                        </div>

                        <div class="summary-product-card__meta">
                            <span class="summary-product-card__pill">Pontos: <?= e(format_points_readable($pointsAchieved)) ?> / <?= e(format_points_readable($pointsTotal)) ?></span>
                            <span class="summary-product-card__pill">Peso: <?= e(format_points_readable((float) ($item['peso'] ?? 0.0))) ?></span>
                            <span class="summary-product-card__pill"><?= e($metricLabel((string) ($item['id_tipo_indicador'] ?? 'VALOR'))) ?></span>
                        </div>

                        <div class="summary-product-card__kpis">
                            <div class="summary-product-card__kv">
                                <small>Meta</small>
                                <strong title="<?= e($metaValue) ?>"><?= e($metaValueReadable) ?></strong>
                            </div>
                            <div class="summary-product-card__kv">
                                <small>Realizado</small>
                                <strong title="<?= e($realizadoValue) ?>"><?= e($realizadoValueReadable) ?></strong>
                            </div>
                        </div>

                        <div class="summary-product-card__progress">
                            <div class="summary-product-card__progress-head">
                                <small>Atingimento de pontos</small>
                            </div>

                            <div class="summary-product-card__progress-body">
                                <span class="summary-product-card__progress-goal"><?= e(format_points_readable($pointsTotal)) ?> pts</span>
                                <div class="summary-product-card__track <?= e($resolveIndicatorBarClass($pointsPercent)) ?>" role="progressbar" aria-valuenow="<?= e(number_format(max(0, min(100, $pointsPercent)), 1, '.', '')) ?>" aria-valuemin="0" aria-valuemax="100">
                                    <span class="summary-product-card__fill" style="--target: <?= e($formatCssPercent($pointsPercent)) ?>;"></span>
                                    <span class="summary-product-card__progress-label" style="--target: <?= e($formatCssPercent($pointsPercent)) ?>;">
                                        <span class="summary-product-card__progress-value"><?= e(number_format($pointsPercent, 1, ',', '.')) ?>%</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="summary-product-card__foot">Atualizado em <?= e($updatedAt !== '' ? $updatedAt : 'N/A') ?></div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</section>