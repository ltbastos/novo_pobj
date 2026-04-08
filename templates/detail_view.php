<?php

declare(strict_types=1);

if (($activeTab ?? 'resumo') !== 'detalhamento') {
    return;
}

$detailSummary = $detailData['summary'] ?? [];
$detailRows = $detailData['rows'] ?? [];
$tableViews = [
    ['id' => 'diretoria', 'label' => 'Diretoria'],
    ['id' => 'regional', 'label' => 'Regional'],
    ['id' => 'agencia', 'label' => 'Agência'],
    ['id' => 'gerente_gestao', 'label' => 'Gerente de gestão'],
    ['id' => 'gerente', 'label' => 'Gerente'],
    ['id' => 'familia', 'label' => 'Família'],
    ['id' => 'indicador', 'label' => 'Indicador'],
    ['id' => 'subindicador', 'label' => 'Subindicador'],
    ['id' => 'contrato', 'label' => 'Contratos'],
];

$detailColumns = [
    ['key' => 'realizado', 'label' => 'Realizado no período (R$)', 'default' => true],
    ['key' => 'meta', 'label' => 'Meta no período (R$)', 'default' => true],
    ['key' => 'atingimento_p', 'label' => 'Atingimento (%)', 'default' => true],
    ['key' => 'meta_diaria', 'label' => 'Meta diária total (R$)', 'default' => true],
    ['key' => 'referencia_hoje', 'label' => 'Referência para hoje (R$)', 'default' => true],
    ['key' => 'pontos', 'label' => 'Pontos no período (pts)', 'default' => true],
    ['key' => 'meta_diaria_necessaria', 'label' => 'Meta diária necessária (R$)', 'default' => true],
    ['key' => 'peso', 'label' => 'Peso (pts)', 'default' => true],
    ['key' => 'projecao', 'label' => 'Projeção (R$)', 'default' => true],
    ['key' => 'data', 'label' => 'Data', 'default' => true],
];

$detailColumnsJson = e((string) json_encode($detailColumns, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$defaultDetailColumns = array_values(array_map(
    static function (array $column): string {
        return (string) $column['key'];
    },
    array_filter($detailColumns, static function (array $column): bool {
        return (bool) ($column['default'] ?? false);
    })
));

$lowercase = static function (string $value): string {
    return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
};

$findOptionLabel = static function (array $options, string $value): string {
    foreach ($options as $option) {
        if ((string) ($option['value'] ?? '') === $value) {
            return (string) ($option['label'] ?? $value);
        }
    }

    return $value;
};

$appliedFilterDefinitions = [
    'segmento' => 'Segmento',
    'diretoria' => 'Diretoria',
    'regional' => 'Regional',
    'agencia' => 'Agência',
    'gerente_gestao' => 'Gerente de gestão',
    'gerente' => 'Gerente',
    'familia' => 'Família',
    'indicadores' => 'Indicador',
    'subindicador' => 'Subindicador',
];

$appliedFilters = [];

foreach ($appliedFilterDefinitions as $filterKey => $filterLabel) {
    $value = trim((string) ($summaryFilterValues[$filterKey] ?? ''));

    if ($value === '') {
        continue;
    }

    $appliedFilters[] = [
        'label' => $filterLabel,
        'value' => $findOptionLabel($summaryFilterOptions[$filterKey] ?? [], $value),
    ];
}

$indicatorMetricsMap = [];

foreach (($dashboardData['indicators'] ?? []) as $indicatorRow) {
    $indicatorId = (string) ($indicatorRow['id_indicador'] ?? '');

    if ($indicatorId === '') {
        continue;
    }

    $indicatorMetricsMap[$indicatorId] = [
        'meta' => (float) ($indicatorRow['meta'] ?? 0.0),
        'peso' => (float) ($indicatorRow['peso'] ?? 0.0),
        'tipo' => strtoupper((string) ($indicatorRow['id_tipo_indicador'] ?? 'VALOR')),
    ];
}

$detailRowsPerIndicator = [];

foreach ($detailRows as $row) {
    $indicatorId = (string) ($row['id_indicador'] ?? '');

    if ($indicatorId === '') {
        continue;
    }

    $detailRowsPerIndicator[$indicatorId] = ($detailRowsPerIndicator[$indicatorId] ?? 0) + 1;
}

$detailRows = array_values(array_map(
    static function (array $row) use ($indicatorMetricsMap, $detailRowsPerIndicator): array {
        $indicatorId = (string) ($row['id_indicador'] ?? '');
        $metrics = $indicatorMetricsMap[$indicatorId] ?? ['meta' => 0.0, 'peso' => 0.0, 'tipo' => 'VALOR'];
        $rowCount = max(1, (int) ($detailRowsPerIndicator[$indicatorId] ?? 1));

        $row['valor_meta_detail'] = (float) ($metrics['meta'] ?? 0.0) / $rowCount;
        $row['peso_detail'] = (float) ($metrics['peso'] ?? 0.0) / $rowCount;
        $row['tipo_detail'] = (string) ($metrics['tipo'] ?? 'VALOR');

        return $row;
    },
    $detailRows
));

$buildSearchPayload = static function (array $items): string {
    $parts = [];

    foreach ($items as $item) {
        $parts[] = implode(' ', [
            (string) ($item['nome_diretoria'] ?? ''),
            (string) ($item['nome_regional'] ?? ''),
            (string) ($item['nome_agencia'] ?? ''),
            (string) ($item['nome_gerente_gestao'] ?? ''),
            (string) ($item['nome_gerente'] ?? ''),
            (string) ($item['nome_familia'] ?? ''),
            (string) ($item['nome_indicador'] ?? ''),
            (string) ($item['nome_subindicador'] ?? ''),
            (string) ($item['numero_contrato'] ?? ''),
            (string) ($item['nome_cliente'] ?? ''),
            (string) ($item['cnpj'] ?? ''),
            (string) ($item['canal_venda'] ?? ''),
            (string) ($item['status_transacao'] ?? ''),
            (string) ($item['observacao'] ?? ''),
        ]);
    }

    return trim(implode(' ', array_filter($parts, static function (string $value): bool {
        return trim($value) !== '';
    })));
};

$calculateSummary = static function (array $items): array {
    $valorRealizado = 0.0;
    $valorMeta = 0.0;
    $peso = 0.0;
    $lastDate = '';

    foreach ($items as $item) {
        $valorRealizado += (float) ($item['valor_realizado'] ?? 0.0);
        $valorMeta += (float) ($item['valor_meta_detail'] ?? 0.0);
        $peso += (float) ($item['peso_detail'] ?? 0.0);

        $transactionDate = trim((string) ($item['data_transacao'] ?? ''));

        if ($transactionDate !== '' && ($lastDate === '' || strcmp($transactionDate, $lastDate) > 0)) {
            $lastDate = $transactionDate;
        }
    }

    $daysTotal = 30;
    $daysElapsed = min((int) date('j'), $daysTotal);
    $daysRemaining = max(1, $daysTotal - $daysElapsed);
    $metaDiaria = $daysTotal > 0 ? $valorMeta / $daysTotal : 0.0;
    $referenciaHoje = $daysElapsed > 0 ? min($valorMeta, $metaDiaria * $daysElapsed) : 0.0;
    $metaDiariaNecessaria = $daysRemaining > 0 ? max(0.0, ($valorMeta - $valorRealizado) / $daysRemaining) : 0.0;
    $projecao = $daysElapsed > 0 ? ($valorRealizado / max($daysElapsed, 1)) * $daysTotal : $valorRealizado;
    $atingimentoValor = $valorRealizado - $valorMeta;
    $atingimentoPercentual = $valorMeta > 0 ? ($valorRealizado / $valorMeta) * 100 : 0.0;

    return [
        'valor_realizado' => $valorRealizado,
        'valor_meta' => $valorMeta,
        'atingimento_v' => $atingimentoValor,
        'atingimento_p' => $atingimentoPercentual,
        'meta_diaria' => $metaDiaria,
        'referencia_hoje' => $referenciaHoje,
        'pontos' => $peso,
        'meta_diaria_necessaria' => $metaDiariaNecessaria,
        'peso' => $peso,
        'projecao' => $projecao,
        'data' => $lastDate,
    ];
};

$extractNodeIdentity = static function (array $row, string $level): array {
    switch ($level) {
        case 'diretoria':
            return [
                'key' => (string) ($row['juncao_dr'] ?? 'sem-diretoria'),
                'label' => (string) ($row['nome_diretoria'] ?? 'Sem diretoria'),
            ];

        case 'regional':
            return [
                'key' => (string) ($row['juncao_gr'] ?? 'sem-regional'),
                'label' => (string) ($row['nome_regional'] ?? 'Sem regional'),
            ];

        case 'agencia':
            return [
                'key' => (string) ($row['juncao_ag'] ?? 'sem-agencia'),
                'label' => (string) ($row['nome_agencia'] ?? 'Sem agência'),
            ];

        case 'gerente_gestao':
            return [
                'key' => (string) ($row['funcional_gerente_gestao'] ?? 'sem-gerente-gestao'),
                'label' => (string) ($row['nome_gerente_gestao'] ?? 'Sem gerente de gestão'),
            ];

        case 'gerente':
            return [
                'key' => (string) ($row['funcional'] ?? 'sem-gerente'),
                'label' => (string) ($row['nome_gerente'] ?? 'Sem gerente'),
            ];

        case 'familia':
            return [
                'key' => (string) ($row['nome_familia'] ?? 'sem-familia'),
                'label' => (string) ($row['nome_familia'] ?? 'Sem família'),
            ];

        case 'indicador':
            return [
                'key' => (string) ($row['id_indicador'] ?? 'sem-indicador'),
                'label' => (string) ($row['nome_indicador'] ?? 'Sem indicador'),
            ];

        case 'subindicador':
            return [
                'key' => (string) ($row['id_subindicador'] ?? 'sem-subindicador'),
                'label' => (string) ($row['nome_subindicador'] ?? 'Sem subindicador'),
            ];

        case 'contrato':
            $contract = trim((string) ($row['numero_contrato'] ?? ''));

            return [
                'key' => $contract !== '' ? $contract : (string) ($row['id_transacao'] ?? 'sem-contrato'),
                'label' => $contract !== '' ? $contract : 'Sem contrato',
            ];

        default:
            return [
                'key' => 'desconhecido',
                'label' => 'Sem classificação',
            ];
    }
};

$buildTreeHierarchy = null;
$buildTreeHierarchy = static function (array $items, array $hierarchy, int $level = 0) use (&$buildTreeHierarchy, $extractNodeIdentity, $buildSearchPayload, $calculateSummary, $lowercase): array {
    if ($items === [] || !isset($hierarchy[$level])) {
        return [];
    }

    $currentLevel = $hierarchy[$level];
    $groups = [];

    foreach ($items as $item) {
        $identity = $extractNodeIdentity($item, $currentLevel);
        $bucketId = $currentLevel . '::' . $identity['key'];

        if (!isset($groups[$bucketId])) {
            $groups[$bucketId] = [
                'identity' => $identity,
                'items' => [],
            ];
        }

        $groups[$bucketId]['items'][] = $item;
    }

    $nodes = [];

    foreach ($groups as $bucketId => $group) {
        $groupItems = $group['items'];
        $firstItem = $groupItems[0] ?? [];
        $children = $buildTreeHierarchy($groupItems, $hierarchy, $level + 1);

        $nodes[] = [
            'id' => $currentLevel . '-' . substr(md5($bucketId . '|' . $level), 0, 16),
            'label' => (string) ($group['identity']['label'] ?? 'Sem classificação'),
            'level' => $currentLevel,
            'children' => $children,
            'summary' => $calculateSummary($groupItems),
            'search_text' => $lowercase($buildSearchPayload($groupItems)),
            'detail' => $currentLevel === 'contrato'
                ? [
                    'canal_venda' => (string) ($firstItem['canal_venda'] ?? ''),
                    'cliente' => (string) ($firstItem['nome_cliente'] ?? ''),
                    'cnpj' => (string) ($firstItem['cnpj'] ?? ''),
                    'gerente' => (string) ($firstItem['nome_gerente'] ?? ''),
                    'gerente_gestao' => (string) ($firstItem['nome_gerente_gestao'] ?? ''),
                    'conta' => (string) ($firstItem['conta'] ?? ''),
                    'status' => (string) ($firstItem['status_transacao'] ?? ''),
                    'data' => (string) ($firstItem['data_transacao'] ?? ''),
                    'observacao' => (string) ($firstItem['observacao'] ?? ''),
                ]
                : null,
        ];
    }

    usort($nodes, static function (array $left, array $right): int {
        return strcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
    });

    return $nodes;
};

$levelHierarchy = [
    'diretoria' => ['diretoria', 'regional', 'agencia', 'gerente_gestao', 'gerente', 'familia', 'indicador', 'subindicador', 'contrato'],
    'regional' => ['regional', 'agencia', 'gerente_gestao', 'gerente', 'familia', 'indicador', 'subindicador', 'contrato'],
    'agencia' => ['agencia', 'gerente_gestao', 'gerente', 'familia', 'indicador', 'subindicador', 'contrato'],
    'gerente_gestao' => ['gerente_gestao', 'gerente', 'familia', 'indicador', 'subindicador', 'contrato'],
    'gerente' => ['gerente', 'familia', 'indicador', 'subindicador', 'contrato'],
    'familia' => ['familia', 'indicador', 'subindicador', 'contrato'],
    'indicador' => ['indicador', 'subindicador', 'contrato'],
    'subindicador' => ['subindicador', 'contrato'],
    'contrato' => ['contrato'],
];

$treeViewsData = [];

foreach ($tableViews as $view) {
    $viewId = (string) ($view['id'] ?? 'diretoria');
    $treeViewsData[$viewId] = $detailRows === [] ? [] : $buildTreeHierarchy($detailRows, $levelHierarchy[$viewId] ?? $levelHierarchy['diretoria']);
}

$getAtingimentoClass = static function (float $value): string {
    if ($value >= 100.0) {
        return 'text-success';
    }

    if ($value >= 80.0) {
        return 'text-warning';
    }

    return 'text-danger';
};

$formatColumnValue = static function (array $summary, string $columnKey): string {
    switch ($columnKey) {
        case 'realizado':
            return format_currency_readable((float) ($summary['valor_realizado'] ?? 0.0));

        case 'meta':
            return format_currency_readable((float) ($summary['valor_meta'] ?? 0.0));

        case 'atingimento_p':
            return format_number_readable((float) ($summary['atingimento_p'] ?? 0.0)) . '%';

        case 'meta_diaria':
            return format_currency_readable((float) ($summary['meta_diaria'] ?? 0.0));

        case 'referencia_hoje':
            return format_currency_readable((float) ($summary['referencia_hoje'] ?? 0.0));

        case 'pontos':
            return format_points_readable((float) ($summary['pontos'] ?? 0.0)) . ' pts';

        case 'meta_diaria_necessaria':
            return format_currency_readable((float) ($summary['meta_diaria_necessaria'] ?? 0.0));

        case 'peso':
            return format_points_readable((float) ($summary['peso'] ?? 0.0)) . ' pts';

        case 'projecao':
            return format_currency_readable((float) ($summary['projecao'] ?? 0.0));

        case 'data':
            $formatted = format_date_br($summary['data'] ?? null);

            return $formatted !== '' ? $formatted : '—';

        default:
            return '—';
    }
};

$formatColumnTooltip = static function (array $summary, string $columnKey): string {
    switch ($columnKey) {
        case 'realizado':
            return format_currency((float) ($summary['valor_realizado'] ?? 0.0));

        case 'meta':
            return format_currency((float) ($summary['valor_meta'] ?? 0.0));

        case 'atingimento_p':
            return format_decimal((float) ($summary['atingimento_p'] ?? 0.0), 2, '%');

        case 'meta_diaria':
            return format_currency((float) ($summary['meta_diaria'] ?? 0.0));

        case 'referencia_hoje':
            return format_currency((float) ($summary['referencia_hoje'] ?? 0.0));

        case 'pontos':
            return format_decimal((float) ($summary['pontos'] ?? 0.0), 2, ' pts');

        case 'meta_diaria_necessaria':
            return format_currency((float) ($summary['meta_diaria_necessaria'] ?? 0.0));

        case 'peso':
            return format_decimal((float) ($summary['peso'] ?? 0.0), 2, ' pts');

        case 'projecao':
            return format_currency((float) ($summary['projecao'] ?? 0.0));

        default:
            return '';
    }
};

$renderTreeRows = null;
$renderTreeRows = static function (array $nodes, int $level = 0, string $parentId = '') use (&$renderTreeRows, $detailColumns, $formatColumnValue, $formatColumnTooltip, $getAtingimentoClass): void {
    foreach ($nodes as $node) {
        $nodeId = (string) ($node['id'] ?? '');
        $summary = $node['summary'] ?? [];
        $children = $node['children'] ?? [];
        $hasChildren = $children !== [];
        $isContract = (string) ($node['level'] ?? '') === 'contrato';
        $detail = $node['detail'] ?? [];
        ?>
        <tr class="tree-row lvl-<?= $level ?>" data-tree-row data-tree-id="<?= e($nodeId) ?>" data-tree-parent-id="<?= e($parentId) ?>" data-tree-search-text="<?= e((string) ($node['search_text'] ?? '')) ?>"<?= $parentId !== '' ? ' hidden' : '' ?>>
            <td>
                <div class="tree-cell">
                    <?php if ($hasChildren || $isContract): ?>
                        <button type="button" class="toggle" data-tree-toggle aria-expanded="false" data-tree-target="<?= e($nodeId) ?>">
                            <span class="toggle__chevron" aria-hidden="true"></span>
                        </button>
                    <?php else: ?>
                        <span class="toggle toggle--placeholder" aria-hidden="true"></span>
                    <?php endif; ?>

                    <span class="label-strong"><?= e((string) ($node['label'] ?? '—')) ?></span>
                </div>
            </td>

            <?php foreach ($detailColumns as $column): ?>
                <?php
                $columnKey = (string) ($column['key'] ?? '');
                $displayValue = $formatColumnValue($summary, $columnKey);
                $tooltipValue = $formatColumnTooltip($summary, $columnKey);
                $classes = $columnKey === 'atingimento_p'
                    ? 'cell-content ' . $getAtingimentoClass((float) ($summary['atingimento_p'] ?? 0.0))
                    : 'cell-content';
                ?>
                <td class="<?= $columnKey === 'data' ? 'col-number col-date' : 'col-number' ?>" data-detail-column="<?= e($columnKey) ?>">
                    <span class="<?= e(trim($classes)) ?>"<?= $tooltipValue !== '' ? ' title="' . e($tooltipValue) . '"' : '' ?>><?= e($displayValue) ?></span>
                </td>
            <?php endforeach; ?>
        </tr>

        <?php if ($isContract): ?>
            <tr class="tree-row tree-detail-row" data-tree-detail-row data-parent-id="<?= e($nodeId) ?>" hidden>
                <td class="tree-detail-cell" data-tree-detail-colspan colspan="<?= count($detailColumns) + 1 ?>">
                    <div class="contract-detail-card">
                        <div class="contract-detail-card__table-wrapper">
                            <table class="contract-detail-card__table">
                                <thead>
                                    <tr>
                                        <th>Canal da venda</th>
                                        <th>Cliente</th>
                                        <th>Gerente</th>
                                        <th>Gerente de gestão</th>
                                        <th>Conta</th>
                                        <th>Data</th>
                                        <th>Status</th>
                                        <th>Observação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><?= e((string) ($detail['canal_venda'] ?? '—')) ?></td>
                                        <td>
                                            <strong><?= e((string) ($detail['cliente'] ?? '—')) ?></strong>
                                            <div class="contract-detail-card__meta"><?= e((string) ($detail['cnpj'] ?? '—')) ?></div>
                                        </td>
                                        <td><?= e((string) ($detail['gerente'] ?? '—')) ?></td>
                                        <td><?= e((string) ($detail['gerente_gestao'] ?? '—')) ?></td>
                                        <td><?= e((string) ($detail['conta'] ?? '—')) ?></td>
                                        <td><?= e(format_date_br($detail['data'] ?? null) ?: '—') ?></td>
                                        <td><?= e((string) ($detail['status'] ?? '—')) ?></td>
                                        <td><?= e((string) ($detail['observacao'] ?? '—')) ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </td>
            </tr>
        <?php endif; ?>

        <?php if ($hasChildren): ?>
            <?php $renderTreeRows($children, $level + 1, $nodeId); ?>
        <?php endif; ?>
        <?php
    }
};
?>
<section
    class="detalhes-wrapper"
    aria-label="Detalhamento do POBJ"
    data-detail-root
    data-detail-columns-config='<?= $detailColumnsJson ?>'
    data-detail-default-columns='<?= e((string) json_encode($defaultDetailColumns, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
>
    <div class="detalhes-view">
        <div class="card card--detalhes">
            <header class="card__header">
                <div class="title-subtitle">
                    <h3>Detalhamento</h3>
                    <p class="muted">Visualize os contratos em uma estrutura hierárquica</p>
                </div>

                <div class="card__actions">
                    <div class="search-box">
                        <input class="input input--search" type="search" placeholder="Contrato (Ex.: 999999)" data-detail-search>
                    </div>
                </div>
            </header>

            <?php if ($appliedFilters !== []): ?>
                <div class="applied-bar">
                    <?php foreach ($appliedFilters as $filter): ?>
                        <div class="applied-chip">
                            <span class="k"><?= e((string) ($filter['label'] ?? '')) ?></span>
                            <span class="v"><?= e((string) ($filter['value'] ?? '')) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="table-controls">
                <div class="table-controls__main">
                    <div class="table-controls__chips">
                        <div class="chipbar" aria-label="Níveis de detalhamento">
                            <?php foreach ($tableViews as $index => $view): ?>
                                <button
                                    type="button"
                                    class="chip<?= $index === 0 ? ' is-active' : '' ?>"
                                    data-detail-table-view="<?= e((string) ($view['id'] ?? '')) ?>"
                                    aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>"
                                >
                                    <?= e((string) ($view['label'] ?? '')) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="table-controls__search"></div>
                </div>
            </div>

            <div class="detail-view-bar">
                <div class="detail-view-bar__left">
                    <span class="detail-view-bar__label">Visões da tabela</span>
                    <div class="detail-view-chips" data-detail-views-host="inline">
                        <button class="detail-chip is-active" type="button" data-detail-default-view-trigger>Visão padrão</button>
                    </div>
                </div>
            </div>

            <div class="table-toolbar-wrapper">
                <div class="table-toolbar">
                    <button type="button" class="table-toolbar__btn" data-detail-expand-all>
                        <span class="table-toolbar__icon" aria-hidden="true"><i data-lucide="chevrons-down"></i></span>
                        <span class="table-toolbar__text">Expandir tudo</span>
                    </button>

                    <button type="button" class="table-toolbar__btn" data-detail-collapse-all>
                        <span class="table-toolbar__icon" aria-hidden="true"><i data-lucide="chevrons-up"></i></span>
                        <span class="table-toolbar__text">Recolher tudo</span>
                    </button>

                    <button type="button" class="table-toolbar__btn detail-view-manage" data-detail-open-modal aria-expanded="false" aria-controls="detail-columns-modal">
                        <span class="table-toolbar__icon" aria-hidden="true"><i data-lucide="columns-3"></i></span>
                        <span class="table-toolbar__text">Personalizar colunas</span>
                    </button>
                </div>
            </div>

            <?php if ($detailRows === []): ?>
                <div class="detail-empty-state">
                    <strong>Nenhum dado encontrado</strong>
                    <span>Nenhum dado encontrado para os filtros selecionados.</span>
                </div>
            <?php else: ?>
                <?php foreach ($tableViews as $index => $view): ?>
                    <?php $nodes = $treeViewsData[(string) ($view['id'] ?? '')] ?? []; ?>
                    <div class="table-wrapper" data-detail-table-pane="<?= e((string) ($view['id'] ?? '')) ?>"<?= $index > 0 ? ' hidden' : '' ?>>
                        <table class="tree-table" data-detail-table>
                            <thead>
                                <tr>
                                    <th><?= e((string) ($view['label'] ?? 'Item')) ?></th>

                                    <?php foreach ($detailColumns as $column): ?>
                                        <th class="col-number" data-detail-column="<?= e((string) ($column['key'] ?? '')) ?>"><?= e((string) ($column['label'] ?? '')) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $renderTreeRows($nodes); ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <section class="detail-designer" id="detail-columns-modal" hidden data-detail-modal aria-hidden="true">
        <button class="detail-designer__overlay" type="button" aria-label="Fechar personalização" data-detail-close-modal></button>

        <div class="detail-designer__panel" role="dialog" aria-modal="true" aria-labelledby="detail-designer-title">
            <header class="detail-designer__head">
                <div>
                    <h4 id="detail-designer-title">Personalizar colunas</h4>
                    <p class="detail-designer__subtitle">Arraste as colunas para montar a visão da tabela e salve até 5 configurações preferidas. A coluna de ações continua fixa no final da grade.</p>
                </div>

                <button type="button" class="icon-btn detail-designer__close" aria-label="Fechar personalização" data-detail-close-modal>×</button>
            </header>

            <section class="detail-designer__views">
                <div class="detail-designer__views-head">
                    <span>Visões salvas</span>
                    <span class="detail-designer__views-hint">Carregue para ajustar ou excluir.</span>
                </div>

                <div class="detail-designer__views-list" data-detail-views-host="modal">
                    <button class="detail-view-chip is-active" type="button" data-detail-default-view-trigger>Visão padrão</button>
                </div>
            </section>

            <section class="detail-designer__lists">
                <div class="detail-designer__list detail-designer__list--available">
                    <div class="detail-designer__list-head">
                        <h5>Colunas disponíveis</h5>
                        <p>Arraste para incluir ou clique para adicionar.</p>
                    </div>

                    <div class="detail-designer__items" data-detail-available-columns></div>
                </div>

                <div class="detail-designer__list detail-designer__list--selected">
                    <div class="detail-designer__list-head">
                        <h5>Colunas na tabela</h5>
                        <p>Arraste para reorganizar ou clique para remover.</p>
                    </div>

                    <div class="detail-designer__items" data-detail-selected-columns></div>
                </div>
            </section>

            <footer class="detail-designer__foot">
                <div class="detail-designer__save">
                    <label class="detail-designer__save-label" for="detail-view-name">Salvar nova visão</label>

                    <div class="detail-designer__input-wrapper">
                        <input id="detail-view-name" class="detail-designer__input" type="text" maxlength="48" placeholder="Ex.: Indicadores priorizados" data-detail-view-name>
                        <button type="button" class="btn btn--primary detail-designer__save-btn" data-detail-save-view-modal>Salvar visão</button>
                    </div>

                    <small class="detail-designer__save-hint">Você pode guardar até 5 visões personalizadas.</small>
                </div>

                <div class="detail-designer__actions">
                    <button type="button" class="btn btn--secondary detail-designer__action-btn" data-detail-cancel-modal>Cancelar</button>
                    <button type="button" class="btn btn--primary detail-designer__action-btn" data-detail-apply-columns>Aplicar</button>
                </div>
            </footer>
        </div>
    </section>
</section>