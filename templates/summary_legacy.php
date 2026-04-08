<?php

declare(strict_types=1);

if (($activeTab ?? 'resumo') !== 'resumo' || (($summaryFilterValues['visao_acumulada'] ?? 'MENSAL') === 'ANUAL')) {
    return;
}

$indicatorRows = $dashboardData['indicators'] ?? [];

if ($indicatorRows === []) {
    return;
}

$businessSnapshot = $legacyBusinessSnapshot ?? ['total' => 0, 'elapsed' => 0, 'remaining' => 0, 'start' => '', 'end' => '', 'today' => ''];
$businessSnapshotJson = e((string) json_encode($businessSnapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$calculateLegacyMetrics = static function (array $item, array $snapshot): array {
    $daysTotal = max(0, (int) ($snapshot['total'] ?? 0));
    $daysElapsed = max(0, (int) ($snapshot['elapsed'] ?? 0));
    $daysRemaining = max(0, (int) ($snapshot['remaining'] ?? 0));
    $meta = (float) ($item['meta'] ?? 0.0);
    $realizado = (float) ($item['realizado'] ?? 0.0);
    $peso = (float) ($item['pontos_meta'] ?? ($item['peso'] ?? 0.0));
    $referenciaHoje = $daysTotal > 0 ? ($meta / $daysTotal) * $daysElapsed : 0.0;
    $faltaParaMeta = max(0.0, $meta - $realizado);
    $metaDiariaNecessaria = $daysRemaining > 0 ? ($faltaParaMeta / $daysRemaining) : 0.0;
    $projecao = $daysElapsed > 0 ? $realizado + (($realizado / $daysElapsed) * $daysRemaining) : $realizado;
    $atingimento = $meta > 0 ? ($realizado / $meta) * 100 : 0.0;
    $pontos = $meta > 0 ? ($realizado / $meta) * $peso : 0.0;

    return [
        'referencia_hoje' => $referenciaHoje,
        'meta_diaria_necessaria' => $metaDiariaNecessaria,
        'projecao' => $projecao,
        'atingimento' => $atingimento,
        'pontos_simulados' => $pontos,
    ];
};

$familyGroups = [];

foreach ($indicatorRows as $row) {
    $familyLabel = trim((string) ($row['nome_familia'] ?? ''));
    $familyKey = $familyLabel !== '' ? $familyLabel : 'Sem familia';

    if (!isset($familyGroups[$familyKey])) {
        $familyGroups[$familyKey] = [
            'label' => $familyKey,
            'items' => [],
            'totals' => [
                'pontos_atingidos' => 0.0,
                'pontos_total' => 0.0,
                'atingimento_total' => 0.0,
                'atingimento_count' => 0,
                'atingimento_pct' => 0.0,
            ],
        ];
    }

    $row['legacy_item_id'] = 'indicator-' . trim((string) ($row['id_indicador'] ?? uniqid('', true)));
    $row['legacy_metrics'] = $calculateLegacyMetrics($row, $businessSnapshot);

    $familyGroups[$familyKey]['items'][] = $row;
    $familyGroups[$familyKey]['totals']['pontos_atingidos'] += (float) ($row['legacy_metrics']['pontos_simulados'] ?? 0.0);
    $familyGroups[$familyKey]['totals']['pontos_total'] += (float) ($row['pontos_meta'] ?? 0.0);
    $familyGroups[$familyKey]['totals']['atingimento_total'] += (float) ($row['legacy_metrics']['atingimento'] ?? 0.0);
    $familyGroups[$familyKey]['totals']['atingimento_count']++;
}

foreach ($familyGroups as &$familyGroup) {
    $familyGroup['totals']['atingimento_pct'] = (int) ($familyGroup['totals']['atingimento_count'] ?? 0) > 0
        ? (float) ($familyGroup['totals']['atingimento_total'] ?? 0.0) / (int) ($familyGroup['totals']['atingimento_count'] ?? 1)
        : 0.0;
}
unset($familyGroup);

$metricVariant = static function (string $metric): string {
    $metric = strtoupper(trim($metric));

    if ($metric === 'PERCENTUAL') {
        return 'perc';
    }

    if ($metric === 'QUANTIDADE') {
        return 'qtd';
    }

    return 'valor';
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
<section class="summary-legacy" data-resumo-pane="legacy" hidden aria-label="Resumo em visão clássica">
    <div class="summary-legacy__simulator" data-legacy-simulation-root data-legacy-business-days="<?= $businessSnapshotJson ?>">
        <div class="summary-legacy__simulator-main">
            <label class="summary-legacy__simulator-toggle">
                <input type="checkbox" data-legacy-simulation-toggle>
                <span class="summary-legacy__simulator-label">
                    Ativar simulação rápida
                    <small>Faça ajustes locais em meta e realizado; ao desativar ou recarregar, tudo volta ao original.</small>
                </span>
            </label>

            <button class="summary-legacy__simulator-reset" type="button" data-legacy-simulation-reset disabled>Limpar ajustes</button>
        </div>
    </div>

    <?php foreach ($familyGroups as $familyGroup): ?>
        <?php
        $averagePercent = (float) ($familyGroup['totals']['atingimento_pct'] ?? 0.0);
        $pointsTotal = (float) ($familyGroup['totals']['pontos_total'] ?? 0.0);
        $pointsHit = (float) ($familyGroup['totals']['pontos_atingidos'] ?? 0.0);
        $hasExpandableRows = false;

        foreach ($familyGroup['items'] as $groupItem) {
            if (($groupItem['subindicadores'] ?? []) !== []) {
                $hasExpandableRows = true;
                break;
            }
        }
        ?>
        <section class="summary-legacy__section" data-legacy-section data-legacy-sim-section>
            <header class="summary-legacy__head">
                <div class="summary-legacy__heading">
                    <div class="summary-legacy__title-row">
                        <span class="summary-legacy__name"><?= e($familyGroup['label']) ?></span>
                    </div>

                    <div class="summary-legacy__chips">
                        <span class="summary-legacy__chip"><?= e((string) count($familyGroup['items'])) ?> indicadores</span>
                        <span class="summary-legacy__chip">Pontos <strong data-legacy-section-points-hit><?= e(format_points_readable($pointsHit)) ?></strong> / <strong data-legacy-section-points-total><?= e(format_points_readable($pointsTotal)) ?></strong></span>
                        <?php if ($hasExpandableRows): ?>
                            <button class="summary-legacy__toggle-all" type="button" data-legacy-toggle-all aria-expanded="false">Abrir todos os filtros</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="summary-legacy__stats">
                    <div class="summary-legacy__stat">
                        <span class="summary-legacy__stat-label">Peso total</span>
                        <strong class="summary-legacy__stat-value"><?= e(format_points_readable($pointsTotal)) ?></strong>
                    </div>

                    <div class="summary-legacy__stat">
                        <span class="summary-legacy__stat-label">Atingimento</span>
                        <strong class="summary-legacy__stat-value" data-legacy-section-atingimento><?= e(number_format($averagePercent, 1, ',', '.')) ?>%</strong>
                    </div>
                </div>
            </header>

            <div class="summary-legacy__table-wrapper">
                <table class="summary-legacy__table">
                    <thead>
                        <tr>
                            <th scope="col">Indicador</th>
                            <th scope="col" class="summary-legacy__col--peso">Peso</th>
                            <th scope="col">Métrica</th>
                            <th scope="col" class="summary-legacy__col--meta">Meta</th>
                            <th scope="col" class="summary-legacy__col--real">Realizado</th>
                            <th scope="col" class="summary-legacy__col--ref">Ref. do dia</th>
                            <th scope="col" class="summary-legacy__col--forecast">Forecast</th>
                            <th scope="col" class="summary-legacy__col--meta-dia">Meta diária nec.</th>
                            <th scope="col" class="summary-legacy__col--pontos">Pontos</th>
                            <th scope="col" class="summary-legacy__col--ating">Ating.</th>
                            <th scope="col" class="summary-legacy__col--update">Atualização</th>
                        </tr>
                    </thead>

                    <?php foreach ($familyGroup['items'] as $item): ?>
                        <?php
                        $percent = (float) ($item['percentual_atingimento'] ?? 0.0);
                        $meterClass = $percent >= 100 ? 'is-ok' : ($percent >= 50 ? 'is-warn' : 'is-low');
                        $metricType = (string) ($item['id_tipo_indicador'] ?? 'VALOR');
                        $metaValue = format_metric((float) ($item['meta'] ?? 0.0), $metricType);
                        $metaValueReadable = format_metric_readable((float) ($item['meta'] ?? 0.0), $metricType);
                        $realizadoValue = format_metric((float) ($item['realizado'] ?? 0.0), $metricType);
                        $realizadoValueReadable = format_metric_readable((float) ($item['realizado'] ?? 0.0), $metricType);
                        $legacyMetrics = $item['legacy_metrics'] ?? [];
                        $referenceValue = format_metric_readable((float) ($legacyMetrics['referencia_hoje'] ?? 0.0), $metricType);
                        $forecastValue = format_metric_readable((float) ($legacyMetrics['projecao'] ?? 0.0), $metricType);
                        $dailyTargetValue = format_metric_readable((float) ($legacyMetrics['meta_diaria_necessaria'] ?? 0.0), $metricType);
                        $updateDate = format_date_br($item['ultima_atualizacao'] ?? null);
                        $subrows = array_values(array_filter($item['subindicadores'] ?? [], static function (array $subrow): bool {
                            return (int) ($subrow['id_subindicador'] ?? 0) > 0;
                        }));
                        $hasSubrows = $subrows !== [];
                        ?>
                        <tbody class="summary-legacy__group" data-legacy-group>
                            <tr
                                class="summary-legacy__row summary-legacy__row--parent"
                                data-legacy-item-row
                                data-legacy-section-label="<?= e($familyGroup['label']) ?>"
                                data-legacy-item-id="<?= e((string) ($item['legacy_item_id'] ?? '')) ?>"
                                data-legacy-metric-type="<?= e($metricType) ?>"
                                data-legacy-original-meta="<?= e(number_format((float) ($item['meta'] ?? 0.0), 4, '.', '')) ?>"
                                data-legacy-original-realizado="<?= e(number_format((float) ($item['realizado'] ?? 0.0), 4, '.', '')) ?>"
                                data-legacy-points-total="<?= e(number_format((float) ($item['pontos_meta'] ?? 0.0), 4, '.', '')) ?>"
                                data-legacy-variable-total="<?= e(number_format((float) ($item['variavel_meta'] ?? 0.0), 4, '.', '')) ?>"
                                data-legacy-variable-original="<?= e(number_format((float) ($item['variavel'] ?? 0.0), 4, '.', '')) ?>"
                            >
                                <td class="summary-legacy__col--prod">
                                    <div class="summary-legacy__prod-cell">
                                        <?php if ($hasSubrows): ?>
                                            <button class="summary-legacy__expand" type="button" data-legacy-toggle-row aria-expanded="false" aria-label="Expandir subindicadores de <?= e((string) ($item['nome_indicador'] ?? '')) ?>">
                                                <span class="summary-legacy__expand-icon" aria-hidden="true"></span>
                                            </button>
                                        <?php else: ?>
                                            <span class="summary-legacy__expand-placeholder" aria-hidden="true"></span>
                                        <?php endif; ?>

                                        <div class="summary-legacy__prod-stack">
                                            <div class="summary-legacy__prod-name" title="<?= e((string) ($item['nome_indicador'] ?? '')) ?>"><?= e((string) ($item['nome_indicador'] ?? '')) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="summary-legacy__col--peso"><?= e(format_points_readable((float) ($item['peso'] ?? 0.0))) ?></td>
                                <td>
                                    <span class="summary-legacy__metric summary-legacy__metric--<?= e($metricVariant($metricType)) ?>">
                                        <?= e((string) ($item['nome_tipo_indicador'] ?? $metricLabel($metricType))) ?>
                                    </span>
                                </td>
                                <td class="summary-legacy__col--meta" title="<?= e($metaValue) ?>">
                                    <span data-legacy-display="meta"><?= e($metaValueReadable) ?></span>
                                    <div class="summary-legacy__sim-field" hidden data-legacy-edit="meta">
                                        <input class="summary-legacy__sim-input" type="number" step="any" inputmode="decimal" value="<?= e(number_format((float) ($item['meta'] ?? 0.0), 4, '.', '')) ?>" data-legacy-input="meta">
                                        <span class="summary-legacy__sim-preview" data-legacy-preview="meta"><?= e($metaValueReadable) ?></span>
                                    </div>
                                </td>
                                <td class="summary-legacy__col--real" title="<?= e($realizadoValue) ?>">
                                    <span data-legacy-display="realizado"><?= e($realizadoValueReadable) ?></span>
                                    <div class="summary-legacy__sim-field" hidden data-legacy-edit="realizado">
                                        <input class="summary-legacy__sim-input" type="number" step="any" inputmode="decimal" value="<?= e(number_format((float) ($item['realizado'] ?? 0.0), 4, '.', '')) ?>" data-legacy-input="realizado">
                                        <span class="summary-legacy__sim-preview" data-legacy-preview="realizado"><?= e($realizadoValueReadable) ?></span>
                                    </div>
                                </td>
                                <td class="summary-legacy__col--ref"><span data-legacy-value="referencia"><?= e($referenceValue) ?></span></td>
                                <td class="summary-legacy__col--forecast"><span data-legacy-value="forecast"><?= e($forecastValue) ?></span></td>
                                <td class="summary-legacy__col--meta-dia"><span data-legacy-value="meta-dia"><?= e($dailyTargetValue) ?></span></td>
                                <td class="summary-legacy__col--pontos"><span data-legacy-value="pontos"><?= e(format_points_readable((float) ($legacyMetrics['pontos_simulados'] ?? 0.0))) ?> pts</span></td>
                                <td class="summary-legacy__col--ating">
                                    <div class="summary-legacy__ating-meter <?= e($meterClass) ?>" style="--fill: <?= e($formatCssPercent($percent)) ?>;" role="progressbar" aria-valuenow="<?= e(number_format(max(0, min(200, $percent)), 1, '.', '')) ?>" aria-valuemin="0" aria-valuemax="200" data-legacy-meter>
                                        <span class="summary-legacy__ating-fill"></span>
                                        <span class="summary-legacy__ating-value" data-legacy-value="atingimento"><?= e(number_format($percent, 1, ',', '.')) ?>%</span>
                                    </div>
                                </td>
                                <td class="summary-legacy__col--update"><?= e($updateDate !== '' ? $updateDate : '-') ?></td>
                            </tr>

                            <?php foreach ($subrows as $subrow): ?>
                                <?php
                                $subMetricType = strtoupper($metricType) === 'PERCENTUAL' ? 'QUANTIDADE' : $metricType;
                                $subValue = (float) ($subrow['total'] ?? 0.0);
                                $subValueExact = format_metric($subValue, $subMetricType);
                                $subValueReadable = format_metric_readable($subValue, $subMetricType);
                                ?>
                                <tr class="summary-legacy__row summary-legacy__row--sub" hidden data-legacy-child-row>
                                    <td class="summary-legacy__col--prod">
                                        <div class="summary-legacy__sub-name"><?= e((string) ($subrow['nome_subindicador'] ?? 'Subindicador')) ?></div>
                                    </td>
                                    <td class="summary-legacy__col--peso">-</td>
                                    <td>
                                        <span class="summary-legacy__metric summary-legacy__metric--<?= e($metricVariant($subMetricType)) ?>">
                                            <?= e($metricLabel($subMetricType)) ?>
                                        </span>
                                    </td>
                                    <td class="summary-legacy__col--meta">-</td>
                                    <td class="summary-legacy__col--real" title="<?= e($subValueExact) ?>">
                                        <div class="summary-legacy__cell-main"><?= e($subValueReadable) ?></div>
                                    </td>
                                    <td class="summary-legacy__col--ref">-</td>
                                    <td class="summary-legacy__col--forecast">-</td>
                                    <td class="summary-legacy__col--meta-dia">-</td>
                                    <td class="summary-legacy__col--pontos">-</td>
                                    <td class="summary-legacy__col--ating">-</td>
                                    <td class="summary-legacy__col--update"><?= e($updateDate !== '' ? $updateDate : '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    <?php endforeach; ?>
                </table>
            </div>
        </section>
    <?php endforeach; ?>
</section>
