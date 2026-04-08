<?php

declare(strict_types=1);

$profile = $context['profile'] ?? null;
$scope = $context['scope'] ?? null;
$summary = $dashboardData['summary'] ?? [];
$indicatorRows = $dashboardData['indicators'] ?? [];
?>
<header class="page-header">
    <div>
        <p class="page-eyebrow">POBJ</p>
        <h2 class="page-title">Dashboard Executivo de Teste</h2>
        <p class="page-copy">Selecione um perfil para simular o login corporativo e validar a hierarquia aplicada sobre o programa.</p>
    </div>
    <div class="test-badge">Sem autenticação real</div>
</header>

<section class="toolbar-grid">
    <form method="post" class="toolbar-card toolbar-card-accent">
        <input type="hidden" name="action" value="select_profile">
        <?= csrf_field() ?>
        <label class="field-label" for="profile-functional">Perfil de teste</label>
        <select id="profile-functional" name="selected_functional" class="field-control" data-autosubmit>
            <?php foreach ($profiles as $item): ?>
                <?php $optionLabel = sprintf('%s • %s • %s', $item['nome'], $item['nivel'], $item['juncao_principal'] !== '' ? $item['juncao_principal'] : 'SEM JUNCAO'); ?>
                <option value="<?= e($item['funcional']) ?>"<?= selected_attr($item['funcional'], $selectedProfileFunctional) ?>><?= e($optionLabel) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="field-help">A seleção simula o login e dispara o mesmo fluxo de visibilidade por funcional, nível e junção.</p>
    </form>

    <form method="get" class="toolbar-card">
        <input type="hidden" name="page" value="pobj-dashboard">
        <label class="field-label" for="period-filter">Período</label>
        <select id="period-filter" name="periodo" class="field-control" data-autosubmit>
            <?php foreach ($periods as $period): ?>
                <option value="<?= e($period['id_periodo']) ?>"<?= selected_attr($period['id_periodo'], $selectedPeriodId) ?>><?= e($period['descricao_periodo']) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="field-help">Os indicadores e somatórios respeitam o período ativo escolhido para teste.</p>
    </form>

    <div class="toolbar-card">
        <p class="field-label">Escopo aplicado</p>
        <?php if ($profile !== null && $scope !== null): ?>
            <div class="scope-block">
                <h3><?= e($profile['nome']) ?></h3>
                <p><?= e($profile['nivel']) ?> • Funcional <?= e($profile['funcional']) ?></p>
                <div class="pill-row">
                    <span class="pill pill-highlight"><?= e($scope['label']) ?></span>
                    <?php if ($scope['code'] === 'GLOBAL'): ?>
                        <span class="pill">4000</span>
                    <?php elseif ($scope['allowed'] !== []): ?>
                        <?php foreach ($scope['allowed'] as $allowedJunction): ?>
                            <span class="pill"><?= e($allowedJunction) ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="pill">Carteira própria</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <p class="field-help">Nenhum perfil carregado.</p>
        <?php endif; ?>
    </div>
</section>

<section class="cards-grid">
    <article class="metric-card">
        <p class="metric-label">Indicadores Atingidos</p>
        <div class="metric-value"><?= e((string) ($summary['indicadores_atingidos'] ?? 0)) ?><span>/<?= e((string) ($summary['indicadores_total'] ?? 0)) ?></span></div>
        <p class="metric-footnote">Indicadores dentro do escopo que já bateram a meta no período selecionado.</p>
    </article>

    <article class="metric-card metric-card-strong">
        <p class="metric-label">Taxa de Atingimento</p>
        <div class="metric-value"><?= e(format_decimal((float) ($summary['taxa_atingimento'] ?? 0), 1, '%')) ?></div>
        <p class="metric-footnote">Percentual de indicadores atingidos sobre o total visível ao perfil atual.</p>
    </article>

    <article class="metric-card">
        <p class="metric-label">Pontos no Período</p>
        <div class="metric-value"><?= e(format_decimal((float) ($summary['pontos_realizado'] ?? 0), 2)) ?><span>/<?= e(format_decimal((float) ($summary['pontos_meta'] ?? 0), 2)) ?></span></div>
        <p class="metric-footnote">Somatório de pontos realizados versus potencial total do escopo visível.</p>
    </article>

    <article class="metric-card">
        <p class="metric-label">Variável Estimada</p>
        <div class="metric-value"><?= e(format_currency((float) ($summary['variavel_realizada'] ?? 0))) ?><span>/<?= e(format_currency((float) ($summary['variavel_meta'] ?? 0))) ?></span></div>
        <p class="metric-footnote">Valor estimado da comissão do POBJ para o conjunto de usuários visíveis.</p>
    </article>

    <article class="metric-card metric-card-soft">
        <p class="metric-label">Funcionais Visíveis</p>
        <div class="metric-value"><?= e((string) ($summary['funcionais_visiveis'] ?? 0)) ?></div>
        <p class="metric-footnote">Quantidade de pessoas com produção dentro do recorte do perfil simulado.</p>
    </article>

    <article class="metric-card metric-card-soft">
        <p class="metric-label">Contratos Visíveis</p>
        <div class="metric-value"><?= e((string) ($summary['contratos_visiveis'] ?? 0)) ?></div>
        <p class="metric-footnote">Volume de contratos encontrados na fato detalhada sob o escopo aplicado.</p>
    </article>
</section>

<section class="table-card">
    <div class="table-header">
        <div>
            <p class="table-eyebrow">Indicadores</p>
            <h3 class="table-title">Desempenho por Família e Indicador</h3>
        </div>
        <p class="table-copy">A listagem abaixo já respeita a hierarquia do perfil escolhido e o período atual.</p>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Família</th>
                    <th>Indicador</th>
                    <th>Tipo</th>
                    <th>Meta</th>
                    <th>Realizado</th>
                    <th>Atingimento</th>
                    <th>Pontos</th>
                    <th>Variável</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($indicatorRows as $row): ?>
                    <tr>
                        <td><?= e($row['nome_familia']) ?></td>
                        <td>
                            <div class="cell-title"><?= e($row['nome_indicador']) ?></div>
                            <div class="cell-subtitle">ID <?= e((string) $row['id_indicador']) ?></div>
                        </td>
                        <td><?= e($row['nome_tipo_indicador']) ?></td>
                        <td><?= e(format_metric((float) $row['meta'], $row['id_tipo_indicador'])) ?></td>
                        <td><?= e(format_metric((float) $row['realizado'], $row['id_tipo_indicador'])) ?></td>
                        <td><?= e(format_decimal((float) $row['percentual_atingimento'], 1, '%')) ?></td>
                        <td><?= e(format_decimal((float) $row['pontos'], 2)) ?></td>
                        <td><?= e(format_currency((float) $row['variavel'])) ?></td>
                        <td><span class="badge <?= e(badge_class((bool) $row['atingiu'])) ?>"><?= e($row['atingiu'] ? 'Atingido' : 'Nao atingido') ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>