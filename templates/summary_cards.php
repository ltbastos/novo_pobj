<?php

declare(strict_types=1);

if (($activeTab ?? 'resumo') !== 'resumo' || (($summaryFilterValues['visao_acumulada'] ?? 'MENSAL') === 'ANUAL')) {
    return;
}

$summary = $dashboardData['summary'] ?? [];
$indicatorPercent = (float) ($summary['taxa_atingimento'] ?? 0.0);
$pointsTotal = (float) ($summary['pontos_meta'] ?? 0.0);
$pointsAchieved = (float) ($summary['pontos_realizado'] ?? 0.0);
$pointsPercent = $pointsTotal > 0 ? ($pointsAchieved / $pointsTotal) * 100 : 0.0;
$variableTotal = (float) ($summary['variavel_meta'] ?? 0.0);
$variableAchieved = (float) ($summary['variavel_realizada'] ?? 0.0);
$variablePercent = $variableTotal > 0 ? ($variableAchieved / $variableTotal) * 100 : 0.0;

$resolveBarClass = static function (float $percent): string {
    if ($percent < 50) {
        return 'summary-kpi__hitbar--low';
    }

    if ($percent < 100) {
        return 'summary-kpi__hitbar--warn';
    }

    return 'summary-kpi__hitbar--ok';
};

$formatCssPercent = static function (float $percent): string {
    return number_format(max(0, min(100, $percent)), 2, '.', '') . '%';
};

$cards = [
    [
        'key' => 'indicadores',
        'title' => 'Indicadores',
        'title_emphasis' => '',
        'icon' => 'list-check',
        'achieved' => format_int_readable((float) ($summary['indicadores_atingidos'] ?? 0)),
        'total' => format_int_readable((float) ($summary['indicadores_total'] ?? 0)),
        'achieved_raw' => (float) ($summary['indicadores_atingidos'] ?? 0),
        'total_raw' => (float) ($summary['indicadores_total'] ?? 0),
        'percent' => $indicatorPercent,
    ],
    [
        'key' => 'pontos',
        'title' => 'Pontos',
        'title_emphasis' => '',
        'icon' => 'medal',
        'achieved' => format_points_readable($pointsAchieved),
        'total' => format_points_readable($pointsTotal),
        'achieved_raw' => $pointsAchieved,
        'total_raw' => $pointsTotal,
        'percent' => $pointsPercent,
    ],
    [
        'key' => 'variavel',
        'title' => 'Variável',
        'title_emphasis' => 'Estimada',
        'icon' => 'banknote',
        'achieved' => format_currency_readable($variableAchieved),
        'total' => format_currency_readable($variableTotal),
        'achieved_raw' => $variableAchieved,
        'total_raw' => $variableTotal,
        'percent' => $variablePercent,
    ],
];
?>
<section class="summary-kpi-section" aria-label="Indicadores de resumo" data-summary-kpi-root>
    <div class="summary-kpi-grid">
        <?php foreach ($cards as $index => $card): ?>
            <article class="summary-kpi-card" data-summary-kpi-card="<?= e((string) $card['key']) ?>" data-summary-kpi-original-achieved="<?= e(number_format((float) $card['achieved_raw'], 4, '.', '')) ?>" data-summary-kpi-original-total="<?= e(number_format((float) $card['total_raw'], 4, '.', '')) ?>" data-summary-kpi-original-percent="<?= e(number_format((float) $card['percent'], 4, '.', '')) ?>" style="--card-delay: <?= e((string) ($index * 90)) ?>ms; --bar-delay: <?= e((string) (180 + ($index * 90))) ?>ms;">
                <div class="summary-kpi-card__main">
                    <span class="summary-kpi-card__icon" aria-hidden="true">
                        <i data-lucide="<?= e($card['icon']) ?>"></i>
                    </span>

                    <div class="summary-kpi-card__text">
                        <span class="summary-kpi-card__label">
                            <?= e($card['title']) ?>
                            <?php if ($card['title_emphasis'] !== ''): ?>
                                <span class="summary-kpi-card__label-emphasis"><?= e($card['title_emphasis']) ?></span>
                            <?php endif; ?>
                        </span>

                        <div class="summary-kpi-card__stats">
                            <span class="summary-kpi-card__stat">Atg: <strong data-summary-kpi-achieved><?= e($card['achieved']) ?></strong></span>
                            <span class="summary-kpi-card__stat">Total: <strong data-summary-kpi-total><?= e($card['total']) ?></strong></span>
                        </div>
                    </div>
                </div>

                <div class="summary-kpi__hitbar <?= e($resolveBarClass((float) $card['percent'])) ?>" data-summary-kpi-progress role="progressbar" aria-valuenow="<?= e(number_format((float) $card['percent'], 1, '.', '')) ?>" aria-valuemin="0" aria-valuemax="100">
                    <span class="summary-kpi__track" data-summary-kpi-track style="--target: <?= e($formatCssPercent((float) $card['percent'])) ?>; --thumb: <?= e($formatCssPercent((float) $card['percent'])) ?>;">
                        <span class="summary-kpi__fill"></span>
                        <span class="summary-kpi__thumb">
                            <span class="summary-kpi__pct" data-summary-kpi-percent><?= e(number_format((float) $card['percent'], 1, ',', '.')) ?>%</span>
                        </span>
                    </span>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>