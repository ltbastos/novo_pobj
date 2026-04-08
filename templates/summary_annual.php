<?php

declare(strict_types=1);

if (($activeTab ?? 'resumo') !== 'resumo' || (($summaryFilterValues['visao_acumulada'] ?? 'MENSAL') !== 'ANUAL')) {
    return;
}

$annualRows = $annualPerformanceData['rows'] ?? [];
$annualSummary = $annualPerformanceData['summary'] ?? ['atingiu' => 0, 'quase' => 0, 'distante' => 0];
$annualYear = (int) ($annualPerformanceData['year'] ?? date('Y'));
?>
<section class="annual-view" aria-label="Desempenho anual">
    <header class="annual-view__header">
        <div>
            <span class="annual-view__eyebrow">Visão anual</span>
            <h2 class="annual-view__title">Desempenho mês a mês em <?= e((string) $annualYear) ?></h2>
            <p class="annual-view__copy">Os meses em verde bateram a meta, os em laranja ficaram próximos e os em vermelho ficaram distantes da meta.</p>
        </div>

        <div class="annual-view__legend" aria-label="Legenda de status">
            <span class="annual-view__legend-item annual-view__legend-item--ok">Atingiu: <?= e((string) ($annualSummary['atingiu'] ?? 0)) ?></span>
            <span class="annual-view__legend-item annual-view__legend-item--warn">Quase: <?= e((string) ($annualSummary['quase'] ?? 0)) ?></span>
            <span class="annual-view__legend-item annual-view__legend-item--danger">Distante: <?= e((string) ($annualSummary['distante'] ?? 0)) ?></span>
        </div>
    </header>

    <div class="annual-view__table-wrapper">
        <table class="annual-view__table">
            <?php if ($annualRows === []): ?>
                <tbody>
                    <tr>
                        <td class="annual-view__empty">Ainda não há dados mensais suficientes para a visão anual.</td>
                    </tr>
                </tbody>
            <?php else: ?>
                <thead>
                    <tr>
                        <th scope="col">Métrica</th>
                        <?php foreach ($annualRows as $annualRow): ?>
                            <th scope="col" class="annual-view__month-col"><?= e((string) ($annualRow['month_label'] ?? '')) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th scope="row">Meta</th>
                        <?php foreach ($annualRows as $annualRow): ?>
                            <td class="annual-view__metric-cell"><?= e(format_points_readable((float) ($annualRow['meta'] ?? 0.0))) ?> pts</td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <th scope="row">Realizado</th>
                        <?php foreach ($annualRows as $annualRow): ?>
                            <td class="annual-view__metric-cell"><?= e(format_points_readable((float) ($annualRow['realizado'] ?? 0.0))) ?> pts</td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <th scope="row">Atingimento</th>
                        <?php foreach ($annualRows as $annualRow): ?>
                            <td class="annual-view__status-cell annual-view__status-cell--<?= e((string) ($annualRow['status'] ?? 'distante')) ?>">
                                <strong><?= e(number_format((float) ($annualRow['percentual'] ?? 0.0), 1, ',', '.')) ?>%</strong>
                                <span><?= e((string) ($annualRow['status_label'] ?? 'Não atingiu')) ?></span>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            <?php endif; ?>
        </table>
    </div>
</section>