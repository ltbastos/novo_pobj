<?php

declare(strict_types=1);

if (($activeTab ?? 'resumo') !== 'visao-executiva') {
    return;
}

$executive = $executiveData ?? [];
$focus = $executive['focus'] ?? [];
$reference = $executive['reference'] ?? [];
$kpis = $executive['kpis'] ?? [];
$chart = $executive['chart'] ?? ['labels' => [], 'series' => [], 'max_value' => 100.0];
$ranking = $executive['ranking'] ?? ['top' => [], 'bottom' => []];
$status = $executive['status'] ?? ['hit' => [], 'quase' => [], 'longe' => [], 'summary' => ['hit' => 0, 'quase' => 0, 'longe' => 0]];
$heatmap = $executive['heatmap'] ?? [
    'default_mode' => 'secoes',
    'secoes' => ['title' => '', 'row_axis_label' => 'GR', 'row_axis_sublabel' => 'Famílias', 'rows' => [], 'columns' => [], 'data' => []],
    'meta' => ['title' => '', 'row_axis_label' => 'Hierarquia', 'row_axis_sublabel' => 'Mês', 'rows' => [], 'months' => [], 'data' => []],
];

$sectionsHeatmap = $heatmap['secoes'] ?? ['title' => '', 'row_axis_label' => 'GR', 'row_axis_sublabel' => 'Famílias', 'rows' => [], 'columns' => [], 'data' => []];
$metaHeatmap = $heatmap['meta'] ?? ['title' => '', 'row_axis_label' => 'Hierarquia', 'row_axis_sublabel' => 'Mês', 'rows' => [], 'months' => [], 'data' => []];
$heatmapDefaultMode = (($heatmap['default_mode'] ?? 'secoes') === 'meta') ? 'meta' : 'secoes';

$formatPoints = static function (float $value): string {
    return format_points_readable($value) . ' pts';
};

$formatPercent = static function (float $value): string {
    return format_decimal($value, 1, '%');
};

$resolvePerformanceToneClass = static function (?float $value): string {
    if ($value === null) {
        return 'is-empty';
    }

    if ($value >= 100.0) {
        return 'is-hit';
    }

    if ($value >= 90.0) {
        return 'is-close';
    }

    return 'is-far';
};

$resolveMetaTone = static function (?float $currentMeta, ?float $previousMeta): array {
    if ($previousMeta === null) {
        return ['text' => '—', 'class' => 'is-empty'];
    }

    if ((float) $previousMeta === 0.0) {
        if ((float) $currentMeta === 0.0) {
            return ['text' => '0,0%', 'class' => 'is-up-ok'];
        }

        return ['text' => '—', 'class' => 'is-empty'];
    }

    $delta = (((float) $currentMeta - (float) $previousMeta) / (float) $previousMeta) * 100.0;
    $text = ($delta > 0 ? '+' : '') . format_decimal($delta, 1, '%');

    if ($delta < 0) {
        return ['text' => $text, 'class' => 'is-down'];
    }

    if ($delta <= 5.0) {
        return ['text' => $text, 'class' => 'is-up-ok'];
    }

    if ($delta <= 10.0) {
        return ['text' => $text, 'class' => 'is-up-warn'];
    }

    return ['text' => $text, 'class' => 'is-up-alert'];
};

$chartLabels = array_values(array_filter(array_map(static function ($label): string {
    return trim((string) $label);
}, (array) ($chart['labels'] ?? [])), static function (string $label): bool {
    return $label !== '';
}));
$chartSeries = array_values(array_filter((array) ($chart['series'] ?? []), static function ($series): bool {
    return is_array($series) && trim((string) ($series['label'] ?? '')) !== '';
}));
$chartMaxValue = max(100.0, (float) ($chart['max_value'] ?? 100.0));
$chartWidth = 760.0;
$chartHeight = 208.0;
$paddingLeft = 46.0;
$paddingRight = 14.0;
$paddingTop = 12.0;
$paddingBottom = 30.0;
$plotWidth = max(1.0, $chartWidth - $paddingLeft - $paddingRight);
$plotHeight = max(1.0, $chartHeight - $paddingTop - $paddingBottom);
$chartCount = count($chartLabels);
$xStep = $chartCount > 1 ? $plotWidth / ($chartCount - 1) : 0.0;

$buildChartPoints = static function (array $values) use ($chartCount, $xStep, $paddingLeft, $paddingTop, $plotWidth, $plotHeight, $chartMaxValue): array {
    if ($chartCount === 0) {
        return [];
    }

    $points = [];

    foreach ($values as $index => $value) {
        $safeValue = max(0.0, min($chartMaxValue, (float) $value));
        $x = $chartCount > 1
            ? $paddingLeft + ($xStep * $index)
            : $paddingLeft + ($plotWidth / 2);
        $y = $paddingTop + $plotHeight - (($safeValue / $chartMaxValue) * $plotHeight);
        $points[] = ['x' => $x, 'y' => $y, 'value' => $safeValue];
    }

    return $points;
};

$topList = (array) ($ranking['top'] ?? []);
$bottomList = (array) ($ranking['bottom'] ?? []);
$sectionRows = (array) ($sectionsHeatmap['rows'] ?? []);
$sectionColumns = (array) ($sectionsHeatmap['columns'] ?? []);
$sectionData = (array) ($sectionsHeatmap['data'] ?? []);
$metaRows = (array) ($metaHeatmap['rows'] ?? []);
$metaMonths = (array) ($metaHeatmap['months'] ?? []);
$metaData = (array) ($metaHeatmap['data'] ?? []);
$monthlyGap = (float) ($kpis['gap'] ?? 0.0);
$monthlyForecast = (float) ($kpis['forecast'] ?? 0.0);
$businessDays = $kpis['business_days'] ?? ['elapsed' => 0, 'total' => 0, 'remaining' => 0];

$kpiCards = [
    [
        'title' => 'Atingimento mensal',
        'icon' => 'target',
        'value' => $formatPercent((float) ($kpis['percent_mens'] ?? 0.0)),
        'detail_primary' => 'Realizado: ' . $formatPoints((float) ($kpis['real_mens'] ?? 0.0)),
        'detail_secondary' => 'Meta: ' . $formatPoints((float) ($kpis['meta_mens'] ?? 0.0)),
        'foot' => (string) ($reference['month_label'] ?? ''),
        'tone' => $resolvePerformanceToneClass((float) ($kpis['percent_mens'] ?? 0.0)),
    ],
    [
        'title' => 'Defasagem',
        'icon' => 'gauge',
        'value' => $formatPoints(abs($monthlyGap)),
        'detail_primary' => $monthlyGap > 0 ? 'Meta – realizado' : 'Realizado acima da meta',
        'detail_secondary' => 'Acumulado: ' . $formatPoints((float) ($kpis['real_acum'] ?? 0.0)) . ' / ' . $formatPoints((float) ($kpis['meta_acum'] ?? 0.0)),
        'foot' => 'Recorte atual',
        'tone' => $monthlyGap > 0 ? 'is-far' : 'is-hit',
    ],
    [
        'title' => 'Forecast',
        'icon' => 'chart-no-axes-column',
        'value' => $formatPercent((float) ($kpis['percent_forecast'] ?? 0.0)),
        'detail_primary' => 'Projetado: ' . $formatPoints($monthlyForecast),
        'detail_secondary' => 'Dias úteis: ' . format_int_readable((float) ($businessDays['elapsed'] ?? 0)) . ' / ' . format_int_readable((float) ($businessDays['total'] ?? 0)),
        'foot' => 'Projeção do mês corrente',
        'tone' => $resolvePerformanceToneClass((float) ($kpis['percent_forecast'] ?? 0.0)),
    ],
];

$statusCards = [
    ['key' => 'hit', 'title' => 'Atingidas', 'tone' => 'is-hit', 'summary_copy' => '100% ou mais no mês atual.'],
    ['key' => 'quase', 'title' => 'Próximas', 'tone' => 'is-close', 'summary_copy' => 'Entre 90% e 99,9% no mês atual.'],
    ['key' => 'longe', 'title' => 'Longe', 'tone' => 'is-far', 'summary_copy' => 'Abaixo de 90% no mês atual.'],
];
?>
<section class="executive-view" aria-label="Visão executiva" data-executive-view>
    <div class="executive-kpi-grid">
        <?php foreach ($kpiCards as $card): ?>
            <article class="executive-kpi-card <?= e((string) ($card['tone'] ?? 'is-close')) ?>">
                <div class="executive-kpi-card__header">
                    <span class="executive-kpi-card__icon" aria-hidden="true">
                        <i data-lucide="<?= e((string) ($card['icon'] ?? 'sparkles')) ?>"></i>
                    </span>
                    <span class="executive-kpi-card__title"><?= e((string) ($card['title'] ?? '')) ?></span>
                </div>

                <div class="executive-kpi-card__value"><?= e((string) ($card['value'] ?? '0')) ?></div>
                <div class="executive-kpi-card__detail"><?= e((string) ($card['detail_primary'] ?? '')) ?></div>
                <div class="executive-kpi-card__detail executive-kpi-card__detail--muted"><?= e((string) ($card['detail_secondary'] ?? '')) ?></div>
                <div class="executive-kpi-card__foot"><?= e((string) ($card['foot'] ?? '')) ?></div>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="executive-main-grid">
        <section class="executive-panel executive-panel--chart">
            <header class="executive-panel__header">
                <div>
                    <span class="executive-panel__eyebrow">Evolução mensal</span>
                    <h3 class="executive-panel__title">Evolução mensal por seção</h3>
                </div>
                <span class="executive-panel__tag"><?= e((string) ($reference['window_label'] ?? 'Últimos 6 meses')) ?></span>
            </header>

            <?php if ($chartSeries === [] || $chartLabels === []): ?>
                <div class="executive-empty">Não há dados suficientes para montar a série mensal com os filtros atuais.</div>
            <?php else: ?>
                <div class="executive-chart">
                    <div class="executive-chart__legend">
                        <?php foreach ($chartSeries as $series): ?>
                            <span class="executive-chart__legend-item">
                                <span class="executive-chart__legend-dot" style="background: <?= e((string) ($series['color'] ?? '#cc092f')) ?>;"></span>
                                <span><?= e((string) ($series['label'] ?? '')) ?></span>
                            </span>
                        <?php endforeach; ?>
                    </div>

                    <div class="executive-chart__canvas">
                        <svg class="executive-chart__svg" viewBox="0 0 <?= e(format_decimal($chartWidth, 0)) ?> <?= e(format_decimal($chartHeight, 0)) ?>" role="img" aria-label="Gráfico de linhas com evolução mensal por seção">
                            <?php for ($step = 0; $step <= 4; $step++): ?>
                                <?php
                                $gridValue = $chartMaxValue - (($chartMaxValue / 4) * $step);
                                $gridY = $paddingTop + (($plotHeight / 4) * $step);
                                ?>
                                <line class="executive-chart__grid-line" x1="<?= e((string) $paddingLeft) ?>" y1="<?= e(number_format($gridY, 2, '.', '')) ?>" x2="<?= e(number_format($chartWidth - $paddingRight, 2, '.', '')) ?>" y2="<?= e(number_format($gridY, 2, '.', '')) ?>"></line>
                                <text class="executive-chart__axis-label" x="<?= e(number_format($paddingLeft - 10, 2, '.', '')) ?>" y="<?= e(number_format($gridY + 4, 2, '.', '')) ?>"><?= e(format_decimal($gridValue, 0, '%')) ?></text>
                            <?php endfor; ?>

                            <?php foreach ($chartLabels as $index => $label): ?>
                                <?php
                                $x = $chartCount > 1
                                    ? $paddingLeft + ($xStep * $index)
                                    : $paddingLeft + ($plotWidth / 2);
                                ?>
                                <text class="executive-chart__month-label" x="<?= e(number_format($x, 2, '.', '')) ?>" y="<?= e(number_format($chartHeight - 8, 2, '.', '')) ?>"><?= e($label) ?></text>
                            <?php endforeach; ?>

                            <?php foreach ($chartSeries as $series): ?>
                                <?php $seriesPoints = $buildChartPoints((array) ($series['values'] ?? [])); ?>
                                <?php if ($seriesPoints === []): ?>
                                    <?php continue; ?>
                                <?php endif; ?>
                                <polyline class="executive-chart__line" points="<?= e(implode(' ', array_map(static function (array $point): string {
                                    return number_format($point['x'], 2, '.', '') . ',' . number_format($point['y'], 2, '.', '');
                                }, $seriesPoints))) ?>" stroke="<?= e((string) ($series['color'] ?? '#cc092f')) ?>"></polyline>
                                <?php foreach ($seriesPoints as $pointIndex => $point): ?>
                                    <circle class="executive-chart__point" cx="<?= e(number_format((float) $point['x'], 2, '.', '')) ?>" cy="<?= e(number_format((float) $point['y'], 2, '.', '')) ?>" r="3.8" fill="<?= e((string) ($series['color'] ?? '#cc092f')) ?>" tabindex="0">
                                        <title><?= e(sprintf('%s • %s: %s', (string) ($series['label'] ?? 'Seção'), (string) ($chartLabels[$pointIndex] ?? ''), $formatPercent((float) ($point['value'] ?? 0.0)))) ?></title>
                                    </circle>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </svg>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="executive-status-summary-grid" aria-label="Status rápido">
        <?php foreach ($statusCards as $statusCard): ?>
            <article class="executive-panel executive-panel--status-quick <?= e($statusCard['tone']) ?>">
                <div class="executive-status-quick__head">
                    <span class="executive-panel__eyebrow">Status rápido</span>
                    <span class="executive-status-quick__scope"><?= e((string) ($focus['status_title'] ?? 'Status por Regional')) ?></span>
                </div>
                <h3 class="executive-panel__title"><?= e($statusCard['title']) ?></h3>
                <strong class="executive-status-quick__value"><?= e(format_int_readable((float) ($status['summary'][$statusCard['key']] ?? 0))) ?></strong>
                <p class="executive-status-quick__copy"><?= e((string) ($statusCard['summary_copy'] ?? '')) ?></p>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="executive-list-grid">
        <section class="executive-panel" aria-label="Top 5">
            <header class="executive-panel__header">
                <div>
                    <span class="executive-panel__eyebrow">Ranking</span>
                    <h3 class="executive-panel__title"><?= e((string) ($focus['ranking_title'] ?? 'Desempenho por Regional')) ?> - Top 5</h3>
                </div>
            </header>

            <div class="executive-list">
                <?php if ($topList === []): ?>
                    <div class="executive-empty">Sem dados disponíveis.</div>
                <?php else: ?>
                    <?php foreach ($topList as $item): ?>
                        <?php $percent = (float) ($item['p_mens'] ?? 0.0); ?>
                        <article class="executive-list-item <?= e($resolvePerformanceToneClass($percent)) ?>">
                            <div class="executive-list-item__head">
                                <strong><?= e((string) ($item['display_label'] ?? '')) ?></strong>
                                <span><?= e($formatPercent($percent)) ?></span>
                            </div>
                            <div class="executive-list-item__meta"><?= e($formatPoints((float) ($item['real_mens'] ?? 0.0))) ?> / <?= e($formatPoints((float) ($item['meta_mens'] ?? 0.0))) ?></div>
                            <div class="executive-list-item__meter"><span style="width: <?= e(number_format(max(6.0, min(100.0, $percent)), 2, '.', '')) ?>%;"></span></div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="executive-panel" aria-label="Bottom 5">
            <header class="executive-panel__header">
                <div>
                    <span class="executive-panel__eyebrow">Ranking</span>
                    <h3 class="executive-panel__title"><?= e((string) ($focus['ranking_title'] ?? 'Desempenho por Regional')) ?> - Bottom 5</h3>
                </div>
            </header>

            <div class="executive-list">
                <?php if ($bottomList === []): ?>
                    <div class="executive-empty">Sem dados disponíveis.</div>
                <?php else: ?>
                    <?php foreach ($bottomList as $item): ?>
                        <?php $percent = (float) ($item['p_mens'] ?? 0.0); ?>
                        <article class="executive-list-item <?= e($resolvePerformanceToneClass($percent)) ?>">
                            <div class="executive-list-item__head">
                                <strong><?= e((string) ($item['display_label'] ?? '')) ?></strong>
                                <span><?= e($formatPercent($percent)) ?></span>
                            </div>
                            <div class="executive-list-item__meta"><?= e($formatPoints((float) ($item['real_mens'] ?? 0.0))) ?> / <?= e($formatPoints((float) ($item['meta_mens'] ?? 0.0))) ?></div>
                            <div class="executive-list-item__meter"><span style="width: <?= e(number_format(max(6.0, min(100.0, $percent)), 2, '.', '')) ?>%;"></span></div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="executive-status-grid">
        <?php foreach ($statusCards as $statusCard): ?>
            <?php $statusItems = (array) ($status[$statusCard['key']] ?? []); ?>
            <section class="executive-panel" aria-label="<?= e($statusCard['title']) ?>">
                <header class="executive-panel__header">
                    <div>
                        <span class="executive-panel__eyebrow"><?= e((string) ($focus['status_title'] ?? 'Status por Regional')) ?></span>
                        <h3 class="executive-panel__title"><?= e($statusCard['title']) ?></h3>
                    </div>
                </header>

                <div class="executive-list executive-list--compact">
                    <?php if ($statusItems === []): ?>
                        <div class="executive-empty">Sem dados disponíveis.</div>
                    <?php else: ?>
                        <?php foreach ($statusItems as $item): ?>
                            <?php $percent = (float) ($item['p_mens'] ?? 0.0); ?>
                            <article class="executive-list-item <?= e($statusCard['tone']) ?>">
                                <div class="executive-list-item__head">
                                    <strong><?= e((string) ($item['display_label'] ?? '')) ?></strong>
                                    <?php if ($statusCard['key'] === 'longe'): ?>
                                        <span>-<?= e($formatPoints((float) ($item['deficit'] ?? 0.0))) ?></span>
                                    <?php else: ?>
                                        <span><?= e($formatPercent($percent)) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="executive-list-item__meta"><?= e($formatPoints((float) ($item['real_mens'] ?? 0.0))) ?> / <?= e($formatPoints((float) ($item['meta_mens'] ?? 0.0))) ?></div>
                                <div class="executive-list-item__meter"><span style="width: <?= e(number_format(max(6.0, min(100.0, $percent)), 2, '.', '')) ?>%;"></span></div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <section class="executive-panel executive-panel--heatmap" data-executive-heatmap data-default-mode="<?= e($heatmapDefaultMode) ?>">
        <header class="executive-panel__header executive-panel__header--heatmap">
            <div>
                <span class="executive-panel__eyebrow">Heatmaps</span>
                <h3 class="executive-panel__title">Leitura de seções e variação de meta</h3>
            </div>

            <div class="executive-switch">
                <button class="executive-switch__button<?= $heatmapDefaultMode === 'secoes' ? ' is-active' : '' ?>" type="button" data-executive-heatmap-button="secoes" aria-pressed="<?= $heatmapDefaultMode === 'secoes' ? 'true' : 'false' ?>">Seções</button>
                <button class="executive-switch__button<?= $heatmapDefaultMode === 'meta' ? ' is-active' : '' ?>" type="button" data-executive-heatmap-button="meta" aria-pressed="<?= $heatmapDefaultMode === 'meta' ? 'true' : 'false' ?>">Meta</button>
            </div>
        </header>

        <div class="executive-heatmap-pane" data-executive-heatmap-pane="secoes"<?= $heatmapDefaultMode === 'secoes' ? '' : ' hidden' ?>>
            <div class="executive-heatmap-pane__title"><?= e((string) ($sectionsHeatmap['title'] ?? 'Heatmap — Seções')) ?></div>
            <?php if ($sectionRows === [] || $sectionColumns === []): ?>
                <div class="executive-empty">Não há dados suficientes para montar o heatmap de seções neste recorte.</div>
            <?php else: ?>
                <div class="executive-heatmap-table-wrap">
                    <table class="executive-heatmap-table executive-heatmap-table--sections">
                        <thead>
                            <tr>
                                <th scope="col"><?= e((string) ($sectionsHeatmap['row_axis_label'] ?? 'GR')) ?> \ <?= e((string) ($sectionsHeatmap['row_axis_sublabel'] ?? 'Famílias')) ?></th>
                                <?php foreach ($sectionColumns as $column): ?>
                                    <th scope="col"><?= e((string) ($column['label'] ?? '')) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sectionRows as $row): ?>
                                <?php $rowKey = (string) ($row['key'] ?? ''); ?>
                                <tr>
                                    <th scope="row"><?= e((string) ($row['label'] ?? $rowKey)) ?></th>
                                    <?php foreach ($sectionColumns as $column): ?>
                                        <?php
                                        $columnKey = (string) ($column['key'] ?? '');
                                        $cell = $sectionData[$rowKey][$columnKey] ?? null;
                                        $percent = $cell !== null ? (float) ($cell['percent'] ?? 0.0) : null;
                                        ?>
                                        <td>
                                            <div class="executive-heatmap-cell <?= e($resolvePerformanceToneClass($percent)) ?>">
                                                <strong><?= e($percent === null ? '—' : $formatPercent($percent)) ?></strong>
                                                <span>
                                                    <?php if ($cell === null): ?>
                                                        —
                                                    <?php else: ?>
                                                        <?= e($formatPoints((float) ($cell['real'] ?? 0.0))) ?> / <?= e($formatPoints((float) ($cell['meta'] ?? 0.0))) ?>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="executive-heatmap-pane" data-executive-heatmap-pane="meta"<?= $heatmapDefaultMode === 'meta' ? '' : ' hidden' ?>>
            <div class="executive-heatmap-pane__title"><?= e((string) ($metaHeatmap['title'] ?? 'Heatmap — Variação da meta (mês a mês)')) ?></div>
            <?php if ($metaRows === [] || $metaMonths === []): ?>
                <div class="executive-empty">Não há dados suficientes para montar o heatmap de meta neste recorte.</div>
            <?php else: ?>
                <div class="executive-heatmap-table-wrap">
                    <table class="executive-heatmap-table executive-heatmap-table--meta">
                        <thead>
                            <tr>
                                <th scope="col"><?= e((string) ($metaHeatmap['row_axis_label'] ?? 'Hierarquia')) ?> \ <?= e((string) ($metaHeatmap['row_axis_sublabel'] ?? 'Mês')) ?></th>
                                <?php foreach ($metaMonths as $month): ?>
                                    <th scope="col"><?= e((string) ($month['label'] ?? '')) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($metaRows as $row): ?>
                                <?php $rowKey = (string) ($row['key'] ?? ''); ?>
                                <tr>
                                    <th scope="row"><?= e((string) ($row['label'] ?? $rowKey)) ?></th>
                                    <?php $previousMeta = null; ?>
                                    <?php foreach ($metaMonths as $month): ?>
                                        <?php
                                        $monthKey = (string) ($month['key'] ?? '');
                                        $currentMeta = isset($metaData[$rowKey][$monthKey]) ? (float) $metaData[$rowKey][$monthKey] : null;
                                        $variation = $resolveMetaTone($currentMeta, $previousMeta);
                                        ?>
                                        <td>
                                            <div class="executive-heatmap-cell executive-heatmap-cell--meta <?= e((string) ($variation['class'] ?? 'is-empty')) ?>">
                                                <strong><?= e((string) ($variation['text'] ?? '—')) ?></strong>
                                            </div>
                                        </td>
                                        <?php $previousMeta = $currentMeta; ?>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</section>