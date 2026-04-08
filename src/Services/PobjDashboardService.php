<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class PobjDashboardService
{
    /** @var PDO */
    private $connection;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    public function listPeriods(): array
    {
        $sql = "SELECT id_periodo, descricao_periodo, dt_inicio_periodo, dt_fim_periodo
                FROM dPeriodoApuracao
                WHERE ativo_sn = 1
                ORDER BY dt_inicio_periodo DESC";

        return $this->connection->query($sql)->fetchAll();
    }

    public function periodHasVisibleData(array $context, string $periodId): bool
    {
        [$scopeSql, $scopeParams] = $this->buildScopeSql($context, 'f');
        $sql = "SELECT 1
                FROM fMeta f
                WHERE f.id_periodo = :id_periodo
                  AND f.ativo_sn = 1
                  AND $scopeSql
                LIMIT 1";

        $statement = $this->connection->prepare($sql);
        $statement->execute(array_merge(['id_periodo' => $periodId], $scopeParams));

        return (bool) $statement->fetchColumn();
    }

    public function getDashboardData(array $context, string $periodId, array $filters = [], string $referenceStartDate = '', string $referenceEndDate = ''): array
    {
        $dateRange = $this->resolveFactDateRange($periodId, $referenceStartDate, $referenceEndDate);
        $indicatorRows = $this->getIndicatorRows($context, $periodId, $filters, $dateRange);
        $indicatorCount = count($indicatorRows);
        $achievedCount = 0;
        $pontosMeta = 0.0;
        $pontosRealizado = 0.0;
        $variavelMeta = 0.0;
        $variavelRealizada = 0.0;

        foreach ($indicatorRows as $row) {
            if ($row['meta'] > 0 && $row['realizado'] >= $row['meta']) {
                $achievedCount++;
            }

            $pontosMeta += (float) ($row['pontos_meta'] ?? 0.0);
            $pontosRealizado += (float) ($row['pontos'] ?? 0.0);
            $variavelMeta += (float) ($row['variavel_meta'] ?? 0.0);
            $variavelRealizada += (float) ($row['variavel'] ?? 0.0);
        }

        $visibleFunctionals = $this->countVisibleFunctionals($context, $periodId, $dateRange);
        $contractsInScope = $this->countContracts($context, $periodId, $dateRange);

        return [
            'summary' => [
                'indicadores_atingidos' => $achievedCount,
                'indicadores_total' => $indicatorCount,
                'taxa_atingimento' => $indicatorCount > 0 ? ($achievedCount / $indicatorCount) * 100 : 0.0,
                'pontos_meta' => $pontosMeta,
                'pontos_realizado' => $pontosRealizado,
                'variavel_meta' => $variavelMeta,
                'variavel_realizada' => $variavelRealizada,
                'funcionais_visiveis' => $visibleFunctionals,
                'contratos_visiveis' => $contractsInScope,
            ],
            'indicators' => $indicatorRows,
        ];
    }

    public function getDetailData(array $context, string $periodId, array $filters = [], string $referenceStartDate = '', string $referenceEndDate = ''): array
    {
        $dateRange = $this->resolveFactDateRange($periodId, $referenceStartDate, $referenceEndDate);
        $rows = $this->getDetailRows($context, $periodId, $filters, $dateRange);
        $contracts = [];
        $clients = [];
        $indicators = [];
        $subindicators = [];
        $agencies = [];
        $lastDate = '';

        foreach ($rows as $row) {
            $contract = trim((string) ($row['numero_contrato'] ?? ''));
            $client = trim((string) ($row['cnpj'] ?? ''));
            $indicatorId = trim((string) ($row['id_indicador'] ?? ''));
            $subindicatorId = (int) ($row['id_subindicador'] ?? 0);
            $agency = trim((string) ($row['juncao_ag'] ?? ''));
            $transactionDate = trim((string) ($row['data_transacao'] ?? ''));

            if ($contract !== '') {
                $contracts[$contract] = true;
            }

            if ($client !== '') {
                $clients[$client] = true;
            }

            if ($indicatorId !== '') {
                $indicators[$indicatorId] = true;
            }

            if ($subindicatorId > 0) {
                $subindicators[(string) $subindicatorId] = true;
            }

            if ($agency !== '') {
                $agencies[$agency] = true;
            }

            if ($transactionDate !== '' && ($lastDate === '' || strcmp($transactionDate, $lastDate) > 0)) {
                $lastDate = $transactionDate;
            }
        }

        return [
            'summary' => [
                'linhas_total' => count($rows),
                'contratos_total' => count($contracts),
                'clientes_total' => count($clients),
                'indicadores_total' => count($indicators),
                'subindicadores_total' => count($subindicators),
                'agencias_total' => count($agencies),
                'ultima_movimentacao' => $lastDate,
            ],
            'rows' => $rows,
        ];
    }

    public function getAnnualPerformanceData(array $context, array $filters, string $referenceEndDate): array
    {
        try {
            $referenceDate = new \DateTimeImmutable($referenceEndDate !== '' ? $referenceEndDate : 'today');
        } catch (\Throwable $exception) {
            $referenceDate = new \DateTimeImmutable('today');
        }

        $year = (int) $referenceDate->format('Y');
        $currentMonth = (int) $referenceDate->format('n');
        [$scopeSql, $scopeParams] = $this->buildFactFilterSql(
            $context,
            'p',
            $filters,
            ['segmento', 'diretoria', 'regional', 'agencia', 'gerente_gestao', 'gerente']
        );

        $sql = "SELECT
                    MONTH(p.data_realizado) AS month_number,
                    COALESCE(SUM(p.pontos_meta), 0) AS pontos_meta,
                    COALESCE(SUM(p.pontos_realizado), 0) AS pontos_realizado
                FROM fPontos p
                WHERE p.ativo_sn = 1
                  AND p.data_realizado IS NOT NULL
                  AND YEAR(p.data_realizado) = :report_year
                  AND $scopeSql";

        $params = array_merge(['report_year' => $year], $scopeParams);

        if (($filters['familia'] ?? '') !== '') {
            $sql .= " AND EXISTS (
                        SELECT 1
                        FROM dIndicador i
                        WHERE i.id_indicador = p.id_indicador
                          AND i.id_familia = :filter_familia_annual
                          AND i.ativo_sn = 1
                    )";
            $params['filter_familia_annual'] = $filters['familia'];
        }

        if (($filters['indicadores'] ?? '') !== '') {
            $sql .= ' AND p.id_indicador = :filter_indicador_annual';
            $params['filter_indicador_annual'] = $filters['indicadores'];
        }

        $sql .= ' GROUP BY MONTH(p.data_realizado) ORDER BY MONTH(p.data_realizado)';

        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        $totalsByMonth = [];

        foreach ($statement->fetchAll() as $row) {
            $monthNumber = (int) ($row['month_number'] ?? 0);

            if ($monthNumber < 1 || $monthNumber > 12) {
                continue;
            }

            $totalsByMonth[$monthNumber] = [
                'meta' => (float) ($row['pontos_meta'] ?? 0.0),
                'realizado' => (float) ($row['pontos_realizado'] ?? 0.0),
            ];
        }

        $rows = [];
        $attainedMonths = 0;
        $warningMonths = 0;
        $missedMonths = 0;

        for ($monthNumber = 1; $monthNumber <= $currentMonth; $monthNumber++) {
            $monthDate = $referenceDate->setDate($year, $monthNumber, 1);
            $meta = (float) ($totalsByMonth[$monthNumber]['meta'] ?? 0.0);
            $realizado = (float) ($totalsByMonth[$monthNumber]['realizado'] ?? 0.0);
            $percentual = $meta > 0 ? ($realizado / $meta) * 100 : 0.0;
            $status = 'distante';
            $statusLabel = 'Não atingiu';

            if ($percentual >= 100) {
                $status = 'atingiu';
                $statusLabel = 'Atingiu';
                $attainedMonths++;
            } elseif ($percentual >= 85) {
                $status = 'quase';
                $statusLabel = 'Quase';
                $warningMonths++;
            } else {
                $missedMonths++;
            }

            $rows[] = [
                'month_number' => $monthNumber,
                'month_label' => $this->formatMonthLabel($monthDate),
                'meta' => $meta,
                'realizado' => $realizado,
                'percentual' => $percentual,
                'status' => $status,
                'status_label' => $statusLabel,
            ];
        }

        return [
            'year' => $year,
            'rows' => $rows,
            'summary' => [
                'atingiu' => $attainedMonths,
                'quase' => $warningMonths,
                'distante' => $missedMonths,
            ],
        ];
    }

    public function getRankingData(array $context, string $periodId, array $filters = [], string $referenceStartDate = '', string $referenceEndDate = ''): array
    {
        $filters = $this->normalizeSummaryFilters($filters);
        $dateRange = $this->resolveFactDateRange($periodId, $referenceStartDate, $referenceEndDate);
        $rankingLevel = $this->resolveRankingLevel($filters);
        [$groupSql, $labelSql, $joinsSql] = $this->resolveRankingGroupingSql($rankingLevel);
        [$scopeSql, $scopeParams] = $this->buildFactFilterSql(
            $context,
            'f',
            $filters,
            ['segmento', 'diretoria', 'regional', 'agencia', 'gerente_gestao', 'gerente']
        );
        [$dateSql, $dateParams] = $this->buildCalendarDateRangeCondition('f.data_realizado', $dateRange, 'ranking_range');

        $sql = "SELECT
                    $groupSql AS group_value,
                    $labelSql AS display_label,
                    COALESCE(SUM(f.pontos_realizado), 0) AS pontos
                FROM fPontos f
                                INNER JOIN fGrupoFuncional gf
                                        ON gf.funcional = f.funcional
                                     AND gf.ativo_sn = 1
                $joinsSql
                WHERE f.ativo_sn = 1
                  AND $scopeSql
                  AND $dateSql";

        $params = array_merge($scopeParams, $dateParams);

        if (($filters['familia'] ?? '') !== '') {
            $sql .= " AND EXISTS (
                        SELECT 1
                        FROM dIndicador i
                        WHERE i.id_indicador = f.id_indicador
                          AND i.id_familia = :ranking_familia
                          AND i.ativo_sn = 1
                    )";
            $params['ranking_familia'] = $filters['familia'];
        }

        if (($filters['indicadores'] ?? '') !== '') {
            $sql .= ' AND f.id_indicador = :ranking_indicador';
            $params['ranking_indicador'] = $filters['indicadores'];
        }

        if (($filters['grupo'] ?? '') !== '') {
            $sql .= ' AND gf.id_grupo = :ranking_grupo';
            $params['ranking_grupo'] = $filters['grupo'];
        }

        $sql .= "
                GROUP BY group_value, display_label
                HAVING COALESCE(group_value, '') <> ''
                ORDER BY pontos DESC, display_label";

        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        $rows = [];
        $position = 0;

        foreach ($statement->fetchAll() as $row) {
            $groupValue = trim((string) ($row['group_value'] ?? ''));
            $displayLabel = trim((string) ($row['display_label'] ?? ''));

            if ($groupValue === '' || $displayLabel === '') {
                continue;
            }

            $position++;
            $rows[] = [
                'position' => $position,
                'level' => $rankingLevel,
                'label' => $groupValue,
                'display_label' => $displayLabel,
                'pontos' => (float) ($row['pontos'] ?? 0.0),
            ];
        }

        return [
            'level' => $rankingLevel,
            'level_label' => $this->resolveRankingLevelLabel($rankingLevel),
            'rows' => $rows,
        ];
    }

    public function listRankingGroupOptions(array $context, string $periodId, array $filters = [], string $referenceStartDate = '', string $referenceEndDate = ''): array
    {
        $filters = $this->normalizeSummaryFilters($filters);
        $dateRange = $this->resolveFactDateRange($periodId, $referenceStartDate, $referenceEndDate);
        [$scopeSql, $scopeParams] = $this->buildFactFilterSql(
            $context,
            'f',
            $filters,
            ['segmento', 'diretoria', 'regional', 'agencia', 'gerente_gestao', 'gerente']
        );
        [$dateSql, $dateParams] = $this->buildCalendarDateRangeCondition('f.data_realizado', $dateRange, 'ranking_group_range');

        $sql = "SELECT DISTINCT g.id_grupo AS value, g.nome_grupo AS label
                FROM fPontos f
                INNER JOIN fGrupoFuncional gf
                    ON gf.funcional = f.funcional
                   AND gf.ativo_sn = 1
                INNER JOIN dGrupo g
                    ON g.id_grupo = gf.id_grupo
                   AND g.ativo_sn = 1
                WHERE f.ativo_sn = 1
                  AND $scopeSql
                  AND $dateSql";

        $params = array_merge($scopeParams, $dateParams);

        if (($filters['familia'] ?? '') !== '') {
            $sql .= " AND EXISTS (
                        SELECT 1
                        FROM dIndicador i
                        WHERE i.id_indicador = f.id_indicador
                          AND i.id_familia = :ranking_group_familia
                          AND i.ativo_sn = 1
                    )";
            $params['ranking_group_familia'] = $filters['familia'];
        }

        if (($filters['indicadores'] ?? '') !== '') {
            $sql .= ' AND f.id_indicador = :ranking_group_indicador';
            $params['ranking_group_indicador'] = $filters['indicadores'];
        }

        $sql .= ' ORDER BY g.nome_grupo';

        return $this->fetchOptions($sql, $params);
    }

    public function getExecutiveViewData(array $context, string $periodId, array $filters = [], string $referenceStartDate = '', string $referenceEndDate = ''): array
    {
        $filters = $this->normalizeSummaryFilters($filters);
        $focus = $this->resolveExecutiveFocusMeta($filters);
        $dateContext = $this->resolveExecutiveDateContext($context, $periodId, $filters, $referenceStartDate, $referenceEndDate);
        $monthlyRows = $this->fetchExecutiveGroupedPerformance(
            $context,
            $periodId,
            $filters,
            $focus['level'],
            $dateContext['month_range']['start'],
            $dateContext['month_range']['end']
        );

        $topRows = $monthlyRows;
        usort($topRows, static function (array $left, array $right): int {
            $percentComparison = (float) ($right['p_mens'] ?? 0.0) <=> (float) ($left['p_mens'] ?? 0.0);

            if ($percentComparison !== 0) {
                return $percentComparison;
            }

            return strcmp((string) ($left['display_label'] ?? ''), (string) ($right['display_label'] ?? ''));
        });

        $bottomRows = $monthlyRows;
        usort($bottomRows, static function (array $left, array $right): int {
            $percentComparison = (float) ($left['p_mens'] ?? 0.0) <=> (float) ($right['p_mens'] ?? 0.0);

            if ($percentComparison !== 0) {
                return $percentComparison;
            }

            return strcmp((string) ($left['display_label'] ?? ''), (string) ($right['display_label'] ?? ''));
        });

        return [
            'focus' => $focus,
            'reference' => [
                'period_label' => (string) ($dateContext['period']['descricao_periodo'] ?? $periodId),
                'range_label' => format_date_br($dateContext['range']['start']) . ' a ' . format_date_br($dateContext['range']['end']),
                'month_label' => $dateContext['month_label'],
                'window_label' => 'Últimos 6 meses',
            ],
            'kpis' => $this->buildExecutiveKpis($context, $periodId, $filters, $dateContext),
            'chart' => $this->buildExecutiveChart($context, $periodId, $filters, $dateContext),
            'ranking' => [
                'top' => array_slice($topRows, 0, 5),
                'bottom' => array_slice($bottomRows, 0, 5),
            ],
            'status' => $this->buildExecutiveStatusBuckets($monthlyRows),
            'heatmap' => $this->buildExecutiveHeatmap($context, $periodId, $filters, $focus['level'], $dateContext),
        ];
    }

    public function prepareSummaryFilters(array $context, string $periodId, array $requestedFilters): array
    {
        $values = $this->normalizeSummaryFilters($requestedFilters);
        $values = $this->hydrateProfileHierarchySelections($context, $periodId, $values);
        $values = $this->hydrateHierarchySelections($context, $periodId, $values);
        $options = [];

        $options['segmento'] = $this->listSegmentOptions($context, $periodId);
        $values['segmento'] = $this->sanitizeFilterSelection($values['segmento'], $options['segmento']);

        $options['diretoria'] = $this->listHierarchyOptions(
            $context,
            $periodId,
            ['segmento' => $values['segmento']],
            'juncao_dr',
            'nome_diretoria'
        );
        $values['diretoria'] = $this->sanitizeFilterSelection($values['diretoria'], $options['diretoria']);

        $options['regional'] = $this->listHierarchyOptions(
            $context,
            $periodId,
            [
                'segmento' => $values['segmento'],
                'diretoria' => $values['diretoria'],
            ],
            'juncao_gr',
            'nome_regional'
        );
        $values['regional'] = $this->sanitizeFilterSelection($values['regional'], $options['regional']);

        $options['agencia'] = $this->listHierarchyOptions(
            $context,
            $periodId,
            [
                'segmento' => $values['segmento'],
                'diretoria' => $values['diretoria'],
                'regional' => $values['regional'],
            ],
            'juncao_ag',
            'nome_agencia'
        );
        $values['agencia'] = $this->sanitizeFilterSelection($values['agencia'], $options['agencia']);

        $options['gerente_gestao'] = $this->listManagementManagerOptions(
            $context,
            $periodId,
            [
                'segmento' => $values['segmento'],
                'diretoria' => $values['diretoria'],
                'regional' => $values['regional'],
                'agencia' => $values['agencia'],
            ]
        );
        $values['gerente_gestao'] = $this->sanitizeFilterSelection($values['gerente_gestao'], $options['gerente_gestao']);

        $options['gerente'] = $this->listCommercialManagerOptions(
            $context,
            $periodId,
            [
                'segmento' => $values['segmento'],
                'diretoria' => $values['diretoria'],
                'regional' => $values['regional'],
                'agencia' => $values['agencia'],
                'gerente_gestao' => $values['gerente_gestao'],
            ]
        );
        $values['gerente'] = $this->sanitizeFilterSelection($values['gerente'], $options['gerente']);

        $options['familia'] = $this->listFamilyOptions(
            $context,
            $periodId,
            [
                'segmento' => $values['segmento'],
                'diretoria' => $values['diretoria'],
                'regional' => $values['regional'],
                'agencia' => $values['agencia'],
                'gerente_gestao' => $values['gerente_gestao'],
                'gerente' => $values['gerente'],
            ]
        );
        $values['familia'] = $this->sanitizeFilterSelection($values['familia'], $options['familia']);

        $options['indicadores'] = $this->listIndicatorOptions(
            $context,
            $periodId,
            [
                'segmento' => $values['segmento'],
                'diretoria' => $values['diretoria'],
                'regional' => $values['regional'],
                'agencia' => $values['agencia'],
                'gerente_gestao' => $values['gerente_gestao'],
                'gerente' => $values['gerente'],
                'familia' => $values['familia'],
            ]
        );
        $values['indicadores'] = $this->sanitizeFilterSelection($values['indicadores'], $options['indicadores']);

        $options['subindicador'] = $this->listSubindicatorOptions(
            $context,
            $periodId,
            [
                'segmento' => $values['segmento'],
                'diretoria' => $values['diretoria'],
                'regional' => $values['regional'],
                'agencia' => $values['agencia'],
                'gerente_gestao' => $values['gerente_gestao'],
                'gerente' => $values['gerente'],
                'familia' => $values['familia'],
                'indicadores' => $values['indicadores'],
            ]
        );
        $values['subindicador'] = $this->sanitizeFilterSelection($values['subindicador'], $options['subindicador']);

        $options['status_indicadores'] = [
            ['value' => 'ATINGIDO', 'label' => 'Atingido'],
            ['value' => 'NAO_ATINGIDO', 'label' => 'Nao atingido'],
        ];
        $values['status_indicadores'] = $this->sanitizeFilterSelection($values['status_indicadores'], $options['status_indicadores']);

        $options['visao_acumulada'] = [
            ['value' => 'MENSAL', 'label' => 'Mensal'],
            ['value' => 'SEMESTRAL', 'label' => 'Semestral'],
            ['value' => 'ANUAL', 'label' => 'Anual'],
        ];
        $values['visao_acumulada'] = $this->sanitizeFilterSelection($values['visao_acumulada'], $options['visao_acumulada'], 'MENSAL');

        return [
            'options' => $options,
            'values' => $values,
        ];
    }

    private function hydrateProfileHierarchySelections(array $context, string $periodId, array $filters): array
    {
        $profile = $context['profile'] ?? [];
        $junctions = $context['junctions'] ?? [];
        $level = strtoupper(trim((string) ($profile['nivel'] ?? '')));
        $functional = trim((string) ($profile['funcional'] ?? ''));

        if ($functional === '') {
            return $filters;
        }

        if (in_array($level, ['GC', 'PA', 'AS'], true)) {
            return $this->mergeResolvedFilters(
                $filters,
                array_merge(
                    ['gerente' => $functional],
                    $this->resolveHierarchyPathForField($context, $periodId, 'f.funcional = :selected_value', $functional)
                )
            );
        }

        $primaryAgency = $this->resolvePrimaryJunction($junctions, 'AG');
        $primaryRegional = $this->resolvePrimaryJunction($junctions, 'GR');
        $primaryDirectorate = $this->resolvePrimaryJunction($junctions, 'DR');

        if (in_array($level, ['AG', 'GG', 'TL'], true) && $primaryAgency !== '') {
            return $this->mergeResolvedFilters(
                $filters,
                array_merge(
                    ['gerente_gestao' => $functional],
                    $this->resolveHierarchyPathForField($context, $periodId, 'f.juncao_ag = :selected_value', $primaryAgency)
                )
            );
        }

        if (in_array($level, ['GR', 'GE'], true) && $primaryRegional !== '') {
            return $this->mergeResolvedFilters(
                $filters,
                array_merge(
                    ['regional' => $primaryRegional],
                    $this->resolveHierarchyPathForField($context, $periodId, 'f.juncao_gr = :selected_value', $primaryRegional)
                )
            );
        }

        if ($level === 'DR' && $primaryDirectorate !== '') {
            return $this->mergeResolvedFilters(
                $filters,
                array_merge(
                    ['diretoria' => $primaryDirectorate],
                    $this->resolveHierarchyPathForField($context, $periodId, 'f.juncao_dr = :selected_value', $primaryDirectorate)
                )
            );
        }

        return $filters;
    }

    private function hydrateHierarchySelections(array $context, string $periodId, array $filters): array
    {
        if (($filters['gerente'] ?? '') !== '') {
            return $this->mergeResolvedFilters(
                $filters,
                $this->resolveHierarchyPathForField($context, $periodId, 'f.funcional = :selected_value', $filters['gerente'])
            );
        }

        if (($filters['gerente_gestao'] ?? '') !== '') {
            $resolved = ['gerente_gestao' => $filters['gerente_gestao']];
            $agencies = $this->resolveAgenciesByFunctional($filters['gerente_gestao']);

            if (count($agencies) === 1) {
                $resolved = array_merge(
                    $resolved,
                    $this->resolveHierarchyPathForField($context, $periodId, 'f.juncao_ag = :selected_value', (string) $agencies[0])
                );
            }

            return $this->mergeResolvedFilters($filters, $resolved);
        }

        if (($filters['agencia'] ?? '') !== '') {
            return $this->mergeResolvedFilters(
                $filters,
                $this->resolveHierarchyPathForField($context, $periodId, 'f.juncao_ag = :selected_value', $filters['agencia'])
            );
        }

        if (($filters['regional'] ?? '') !== '') {
            return $this->mergeResolvedFilters(
                $filters,
                $this->resolveHierarchyPathForField($context, $periodId, 'f.juncao_gr = :selected_value', $filters['regional'])
            );
        }

        if (($filters['diretoria'] ?? '') !== '') {
            return $this->mergeResolvedFilters(
                $filters,
                $this->resolveHierarchyPathForField($context, $periodId, 'f.juncao_dr = :selected_value', $filters['diretoria'])
            );
        }

        return $filters;
    }

    private function mergeResolvedFilters(array $filters, array $resolvedValues): array
    {
        foreach ($resolvedValues as $key => $value) {
            if (!array_key_exists($key, $filters) || $filters[$key] !== '' || $value === '') {
                continue;
            }

            $filters[$key] = $value;
        }

        return $filters;
    }

    private function resolveHierarchyPathForField(array $context, string $periodId, string $fieldSql, string $selectedValue): array
    {
        if ($selectedValue === '') {
            return [];
        }

        [$scopeSql, $scopeParams] = $this->buildScopeSql($context, 'f');
        $sql = "SELECT DISTINCT
                    f.segmento,
                    f.juncao_dr,
                    f.juncao_gr,
                    f.juncao_ag,
                    COALESCE(hgg.funcional, '') AS gerente_gestao
                FROM fMeta f
                LEFT JOIN fMultiJuncao m
                    ON m.juncao = f.juncao_ag
                   AND m.tipo_juncao = 'AG'
                   AND m.principal_sn = 1
                   AND m.ativo_sn = 1
                LEFT JOIN dHierarquia hgg
                    ON hgg.funcional = m.funcional
                   AND hgg.nivel = 'AG'
                   AND hgg.ativo_sn = 1
                WHERE f.id_periodo = :id_periodo
                  AND f.ativo_sn = 1
                  AND $scopeSql
                  AND $fieldSql";

        $statement = $this->connection->prepare($sql);
        $statement->execute(array_merge([
            'id_periodo' => $periodId,
            'selected_value' => $selectedValue,
        ], $scopeParams));

        $rows = $statement->fetchAll();

        if ($rows === []) {
            return [];
        }

        return [
            'segmento' => $this->resolveSingleValue($rows, 'segmento'),
            'diretoria' => $this->resolveSingleValue($rows, 'juncao_dr'),
            'regional' => $this->resolveSingleValue($rows, 'juncao_gr'),
            'agencia' => $this->resolveSingleValue($rows, 'juncao_ag'),
            'gerente_gestao' => $this->resolveSingleValue($rows, 'gerente_gestao'),
        ];
    }

    private function resolveSingleValue(array $rows, string $field): string
    {
        $values = [];

        foreach ($rows as $row) {
            $value = trim((string) ($row[$field] ?? ''));

            if ($value === '') {
                continue;
            }

            $values[$value] = true;
        }

        if (count($values) !== 1) {
            return '';
        }

        foreach ($values as $value => $_unused) {
            return (string) $value;
        }

        return '';
    }

    private function resolvePrimaryJunction(array $junctions, string $type): string
    {
        foreach ($junctions as $junction) {
            if (strtoupper((string) ($junction['tipo_juncao'] ?? '')) !== $type) {
                continue;
            }

            if ((int) ($junction['principal_sn'] ?? 0) !== 1) {
                continue;
            }

            return trim((string) ($junction['juncao'] ?? ''));
        }

        foreach ($junctions as $junction) {
            if (strtoupper((string) ($junction['tipo_juncao'] ?? '')) === $type) {
                return trim((string) ($junction['juncao'] ?? ''));
            }
        }

        return '';
    }

    private function getIndicatorRows(array $context, string $periodId, array $filters = [], array $dateRange = []): array
    {
        $filters = $this->normalizeSummaryFilters($filters);
        [$indicatorScopeSql, $indicatorScopeParams] = $this->buildFactFilterSql(
            $context,
            'fm',
            $filters,
            ['segmento', 'diretoria', 'regional', 'agencia', 'gerente_gestao', 'gerente']
        );
        [$metaDateSql, $metaDateParams] = $this->buildCalendarDateRangeCondition('fm.dt_meta', $dateRange, 'indicator_meta_range');

        $sql = "SELECT
                    DISTINCT i.id_indicador,
                    i.nome_indicador,
                    f.nome_familia,
                    t.id_tipo_indicador,
                    t.nome_tipo_indicador,
                    COALESCE(ip.peso, 0) AS peso
                FROM dIndicador i
                INNER JOIN dFamiliaProduto f ON f.id_familia = i.id_familia
                INNER JOIN dTipoIndicador t ON t.id_tipo_indicador = i.id_tipo_indicador
                INNER JOIN fMeta fm
                    ON fm.id_indicador = i.id_indicador
                   AND fm.ativo_sn = 1
                LEFT JOIN fIndicadorPeriodo ip
                    ON ip.id_indicador = i.id_indicador
                   AND ip.id_periodo = :id_periodo_peso
                   AND ip.ativo_sn = 1
                WHERE i.ativo_sn = 1
                  AND $indicatorScopeSql
                  AND $metaDateSql";

        $params = array_merge([
            'id_periodo_peso' => $periodId,
        ], $indicatorScopeParams, $metaDateParams);

        if (($filters['familia'] ?? '') !== '') {
            $sql .= " AND i.id_familia = :filter_familia";
            $params['filter_familia'] = $filters['familia'];
        }

        if (($filters['indicadores'] ?? '') !== '') {
            $sql .= " AND i.id_indicador = :filter_indicador";
            $params['filter_indicador'] = $filters['indicadores'];
        }

        if (($filters['subindicador'] ?? '') !== '') {
            $sql .= " AND EXISTS (
                        SELECT 1
                        FROM dSubindicador s
                        WHERE s.id_indicador = i.id_indicador
                          AND s.id_subindicador = :filter_subindicador
                          AND s.ativo_sn = 1
                    )";
            $params['filter_subindicador'] = $filters['subindicador'];
        }

        $sql .= "
                ORDER BY
                    COALESCE(f.ordem_exibicao, 999),
                    COALESCE(i.ordem_exibicao, 999),
                    i.nome_indicador";

        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        $rows = $statement->fetchAll();
        $metaMap = $this->sumByIndicator('fMeta', 'valor_meta', $context, $periodId, $filters, false, $dateRange);
        $percentualMetaMap = $this->averageByIndicator('fMeta', 'valor_meta', $context, $periodId, $filters, $dateRange);
        $realizadoMap = $this->sumByIndicator('fRealizado', 'valor_realizado', $context, $periodId, $filters, true, $dateRange);
        $variavelMap = $this->sumByIndicator('fVariavel', 'variavel_realizada', $context, $periodId, $filters, false, $dateRange);
        $variavelMetaMap = $this->sumByIndicator('fVariavel', 'variavel_meta', $context, $periodId, $filters, false, $dateRange);
        $metaDateMap = $this->maxDateByIndicator('fMeta', 'dt_meta', $context, $periodId, $filters, false, $dateRange);
        $realizadoDateMap = $this->maxDateByIndicator('fRealizado', 'dt_realizado', $context, $periodId, $filters, true, $dateRange);
        $pontosDateMap = $this->maxDateByIndicator('fPontos', 'data_realizado', $context, $periodId, $filters, false, $dateRange);
        $variavelDateMap = $this->maxDateByIndicator('fVariavel', 'data_realizado', $context, $periodId, $filters, false, $dateRange);
        $subindicatorTotalsMap = $this->getSubindicatorTotalsByIndicator($context, $periodId, $filters, $dateRange);

        foreach ($rows as &$row) {
            $indicatorId = (string) $row['id_indicador'];
            $peso = (float) ($row['peso'] ?? 0.0);
            $ultimaAtualizacao = $this->maxDateValue(
                $this->maxDateValue($metaDateMap[$indicatorId] ?? null, $realizadoDateMap[$indicatorId] ?? null),
                $this->maxDateValue($pontosDateMap[$indicatorId] ?? null, $variavelDateMap[$indicatorId] ?? null)
            );
            $metricType = strtoupper((string) ($row['id_tipo_indicador'] ?? 'VALOR'));
            $metaValue = $metricType === 'PERCENTUAL'
                ? (float) ($percentualMetaMap[$indicatorId] ?? ($metaMap[$indicatorId] ?? 0.0))
                : (float) ($metaMap[$indicatorId] ?? 0.0);
            $realizadoValue = (float) ($realizadoMap[$indicatorId] ?? 0.0);

            $row['meta'] = $metaValue;
            $row['realizado'] = $realizadoValue;
            $row['peso'] = $peso;
            $row['variavel'] = $variavelMap[$indicatorId] ?? 0.0;
            $row['variavel_meta'] = $variavelMetaMap[$indicatorId] ?? 0.0;
            $row['subindicadores'] = $subindicatorTotalsMap[$indicatorId] ?? [];

            if ($metricType !== 'PERCENTUAL' && $row['subindicadores'] !== []) {
                $row['realizado'] = array_reduce($row['subindicadores'], static function (float $carry, array $subindicator): float {
                    return $carry + (float) ($subindicator['total'] ?? 0.0);
                }, 0.0);
            }

            if ($metricType === 'PERCENTUAL') {
                $percentualMetrics = $this->resolvePercentualMetrics(
                    $metaValue,
                    $realizadoValue,
                    $row['subindicadores']
                );

                $row['meta'] = $percentualMetrics['meta'];
                $row['realizado'] = $percentualMetrics['realizado'];
                $row['percentual_atingimento'] = $percentualMetrics['atingimento'];
                $row['realizado_absoluto'] = $percentualMetrics['numerador'];
                $row['base_absoluta'] = $percentualMetrics['denominador'];
                $row['realizado_absoluto_label'] = $percentualMetrics['numerador_label'];
                $row['base_absoluta_label'] = $percentualMetrics['denominador_label'];
                $row['usa_base_absoluta'] = $percentualMetrics['por_base'];
            } else {
                $row['percentual_atingimento'] = $row['meta'] > 0 ? ($row['realizado'] / $row['meta']) * 100 : 0.0;
                $row['realizado_absoluto'] = null;
                $row['base_absoluta'] = null;
                $row['realizado_absoluto_label'] = '';
                $row['base_absoluta_label'] = '';
                $row['usa_base_absoluta'] = false;
            }

            $row['pontos_meta'] = $peso;
            $row['pontos'] = $this->calculateIndicatorPoints($row['percentual_atingimento'], $peso);
            $row['atingiu'] = $row['meta'] > 0 && $row['realizado'] >= $row['meta'];
            $row['ultima_atualizacao'] = $ultimaAtualizacao;
        }
        unset($row);

        if (($filters['status_indicadores'] ?? '') !== '') {
            $rows = array_values(array_filter($rows, static function (array $row) use ($filters): bool {
                if ($filters['status_indicadores'] === 'ATINGIDO') {
                    return (bool) ($row['atingiu'] ?? false);
                }

                if ($filters['status_indicadores'] === 'NAO_ATINGIDO') {
                    return !(bool) ($row['atingiu'] ?? false);
                }

                return true;
            }));
        }

        return $rows;
    }

    private function getDetailRows(array $context, string $periodId, array $filters = [], array $dateRange = []): array
    {
        $filters = $this->normalizeSummaryFilters($filters);
        [$scopeSql, $scopeParams] = $this->buildFactFilterSql(
            $context,
            'fd',
            $filters,
            ['segmento', 'diretoria', 'regional', 'agencia', 'gerente_gestao', 'gerente']
        );
        [$detailDateSql, $detailDateParams] = $this->buildCalendarDateRangeCondition('fd.data_transacao', $dateRange, 'detail_range');

        $sql = "SELECT
                    fd.id_detalhe,
                    fd.id_periodo,
                    fd.id_indicador,
                    fd.id_subindicador,
                    fd.funcional,
                    h.nome AS nome_gerente,
                    mgg.funcional AS funcional_gerente_gestao,
                    hgg.nome AS nome_gerente_gestao,
                    fd.juncao_ag,
                    mesu.nome_agencia,
                    fd.juncao_gr,
                    mesu.nome_regional,
                    fd.juncao_dr,
                    mesu.nome_diretoria,
                    fd.segmento,
                    fd.id_transacao,
                    fd.numero_contrato,
                    fd.conta,
                    fd.canal_venda,
                    fd.data_transacao,
                    fd.status_transacao,
                    fd.observacao,
                    fd.cnpj,
                    fd.nome_cliente,
                    fd.valor_realizado,
                    i.nome_indicador,
                    i.id_tipo_indicador,
                    f.nome_familia,
                    COALESCE(s.nome_subindicador, 'Sem subindicador') AS nome_subindicador
                FROM fDetalhe fd
                INNER JOIN dIndicador i
                    ON i.id_indicador = fd.id_indicador
                   AND i.ativo_sn = 1
                INNER JOIN dFamiliaProduto f
                    ON f.id_familia = i.id_familia
                   AND f.ativo_sn = 1
                LEFT JOIN dSubindicador s
                    ON s.id_subindicador = fd.id_subindicador
                   AND s.ativo_sn = 1
                LEFT JOIN dHierarquia h
                    ON h.funcional = fd.funcional
                   AND h.ativo_sn = 1
                LEFT JOIN fMultiJuncao mgg
                    ON mgg.juncao = fd.juncao_ag
                   AND mgg.tipo_juncao = 'AG'
                   AND mgg.principal_sn = 1
                   AND mgg.ativo_sn = 1
                LEFT JOIN dHierarquia hgg
                    ON hgg.funcional = mgg.funcional
                   AND hgg.nivel = 'AG'
                   AND hgg.ativo_sn = 1
                LEFT JOIN dMesu mesu
                    ON mesu.juncao_ag = fd.juncao_ag
                   AND mesu.ativo_sn = 1
                                WHERE fd.ativo_sn = 1
                                    AND $scopeSql
                                    AND $detailDateSql";

                $params = array_merge($scopeParams, $detailDateParams);

        if (($filters['familia'] ?? '') !== '') {
            $sql .= ' AND i.id_familia = :filter_familia';
            $params['filter_familia'] = $filters['familia'];
        }

        if (($filters['indicadores'] ?? '') !== '') {
            $sql .= ' AND fd.id_indicador = :filter_indicador';
            $params['filter_indicador'] = $filters['indicadores'];
        }

        if (($filters['subindicador'] ?? '') !== '') {
            $sql .= ' AND fd.id_subindicador = :filter_subindicador';
            $params['filter_subindicador'] = $filters['subindicador'];
        }

        $sql .= "
                ORDER BY
                    COALESCE(f.ordem_exibicao, 999),
                    COALESCE(i.ordem_exibicao, 999),
                    COALESCE(s.ordem_exibicao, 999),
                    fd.data_transacao DESC,
                    fd.numero_contrato";

        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function calculateIndicatorPoints(float $percentualAtingimento, float $peso): float
    {
        if ($peso <= 0) {
            return 0.0;
        }

        return ($percentualAtingimento / 100) * $peso;
    }

    private function getSubindicatorTotalsByIndicator(array $context, string $periodId, array $filters = [], array $dateRange = []): array
    {
        $filters = $this->normalizeSummaryFilters($filters);
        $filters['subindicador'] = '';

        [$scopeSql, $scopeParams] = $this->buildFactFilterSql(
            $context,
            'f',
            $filters,
            ['segmento', 'diretoria', 'regional', 'agencia', 'gerente_gestao', 'gerente']
        );
        [$realizadoDateSql, $realizadoDateParams] = $this->buildCalendarDateRangeCondition('f.dt_realizado', $dateRange, 'subindicator_range');

        $sql = "SELECT
                    f.id_indicador,
                    f.id_subindicador,
                    COALESCE(s.nome_subindicador, 'Sem subindicador') AS nome_subindicador,
                    COALESCE(s.ordem_exibicao, 999) AS ordem_exibicao,
                    COALESCE(SUM(f.valor_realizado), 0) AS total
                FROM fRealizado f
                LEFT JOIN dSubindicador s
                    ON s.id_subindicador = f.id_subindicador
                   AND s.ativo_sn = 1
                                WHERE f.ativo_sn = 1
                  AND COALESCE(f.id_subindicador, 0) > 0
                                    AND $scopeSql
                                    AND $realizadoDateSql";

                $params = array_merge($scopeParams, $realizadoDateParams);

        if (($filters['familia'] ?? '') !== '') {
            $sql .= ' AND EXISTS (
                        SELECT 1
                        FROM dIndicador i
                        WHERE i.id_indicador = f.id_indicador
                          AND i.id_familia = :filter_familia
                          AND i.ativo_sn = 1
                    )';
            $params['filter_familia'] = $filters['familia'];
        }

        if (($filters['indicadores'] ?? '') !== '') {
            $sql .= ' AND f.id_indicador = :filter_indicador';
            $params['filter_indicador'] = $filters['indicadores'];
        }

        $sql .= '
                GROUP BY f.id_indicador, f.id_subindicador, s.nome_subindicador, s.ordem_exibicao
                ORDER BY f.id_indicador, COALESCE(s.ordem_exibicao, 999), s.nome_subindicador';

        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        $grouped = [];

        foreach ($statement->fetchAll() as $row) {
            $indicatorId = (string) ($row['id_indicador'] ?? '');

            if ($indicatorId === '') {
                continue;
            }

            $grouped[$indicatorId][] = [
                'id_subindicador' => (int) ($row['id_subindicador'] ?? 0),
                'nome_subindicador' => (string) ($row['nome_subindicador'] ?? ''),
                'ordem_exibicao' => (int) ($row['ordem_exibicao'] ?? 999),
                'total' => (float) ($row['total'] ?? 0.0),
            ];
        }

        return $grouped;
    }

    private function resolvePercentualMetrics(float $meta, float $realizado, array $subindicatorRows): array
    {
        $meta = min(100.0, max(0.0, $meta));
        $resolvedRealizado = min(100.0, max(0.0, $realizado));
        $numerador = null;
        $denominador = null;
        $numeradorLabel = '';
        $denominadorLabel = '';
        $usedAbsoluteBase = false;

        if ($subindicatorRows !== []) {
            $denominatorRow = $this->findSubindicatorRowByKeywords($subindicatorRows, ['carteira', 'base', 'universo', 'elegiv', 'total']);

            if ($denominatorRow === null) {
                $denominatorRow = $this->pickLargestSubindicatorRow($subindicatorRows);
            }

            if ($denominatorRow !== null) {
                $numeratorRow = $this->findSubindicatorRowByKeywords($subindicatorRows, ['contato', 'contat', 'acion', 'abord', 'atingid', 'realizado', 'falad']);

                if ($numeratorRow === null) {
                    $remainingTotal = 0.0;
                    $remainingLabel = 'Realizado';

                    foreach ($subindicatorRows as $subindicatorRow) {
                        if ((int) ($subindicatorRow['id_subindicador'] ?? 0) === (int) ($denominatorRow['id_subindicador'] ?? 0)) {
                            continue;
                        }

                        $remainingTotal += (float) ($subindicatorRow['total'] ?? 0.0);
                    }

                    if ($remainingTotal > 0) {
                        $numeratorRow = [
                            'total' => $remainingTotal,
                            'nome_subindicador' => $remainingLabel,
                        ];
                    }
                }

                $denominatorTotal = (float) ($denominatorRow['total'] ?? 0.0);
                $numeratorTotal = (float) ($numeratorRow['total'] ?? 0.0);

                if ($denominatorTotal > 0 && $numeratorTotal >= 0) {
                    $resolvedRealizado = min(100.0, max(0.0, ($numeratorTotal / $denominatorTotal) * 100));
                    $numerador = $numeratorTotal;
                    $denominador = $denominatorTotal;
                    $numeradorLabel = (string) ($numeratorRow['nome_subindicador'] ?? 'Realizado');
                    $denominadorLabel = (string) ($denominatorRow['nome_subindicador'] ?? 'Base');
                    $usedAbsoluteBase = true;
                }
            }
        }

        $atingimento = $meta > 0 ? min(100.0, max(0.0, ($resolvedRealizado / $meta) * 100)) : 0.0;

        return [
            'meta' => $meta,
            'realizado' => $resolvedRealizado,
            'atingimento' => $atingimento,
            'numerador' => $numerador,
            'denominador' => $denominador,
            'numerador_label' => $numeradorLabel,
            'denominador_label' => $denominadorLabel,
            'por_base' => $usedAbsoluteBase,
        ];
    }

    private function findSubindicatorRowByKeywords(array $subindicatorRows, array $keywords): ?array
    {
        foreach ($subindicatorRows as $subindicatorRow) {
            $name = strtolower((string) ($subindicatorRow['nome_subindicador'] ?? ''));

            foreach ($keywords as $keyword) {
                if ($keyword !== '' && strpos($name, $keyword) !== false) {
                    return $subindicatorRow;
                }
            }
        }

        return null;
    }

    private function pickLargestSubindicatorRow(array $subindicatorRows): ?array
    {
        $selected = null;

        foreach ($subindicatorRows as $subindicatorRow) {
            if ($selected === null || (float) ($subindicatorRow['total'] ?? 0.0) > (float) ($selected['total'] ?? 0.0)) {
                $selected = $subindicatorRow;
            }
        }

        return $selected;
    }

    private function maxDateByIndicator(string $table, string $dateField, array $context, string $periodId, array $filters = [], bool $supportsSubindicator = false, array $dateRange = []): array
    {
        [$scopeSql, $scopeParams] = $this->buildFactFilterSql(
            $context,
            'f',
            $filters,
            ['segmento', 'diretoria', 'regional', 'agencia', 'gerente_gestao', 'gerente']
        );
        [$dateRangeSql, $dateRangeParams] = $this->buildCalendarDateRangeCondition('f.' . $dateField, $dateRange, 'max_date_range');

        $conditions = [$scopeSql, $dateRangeSql];
        $params = array_merge($scopeParams, $dateRangeParams);

        if (($filters['indicadores'] ?? '') !== '') {
            $conditions[] = 'f.id_indicador = :filter_indicador';
            $params['filter_indicador'] = $filters['indicadores'];
        }

        if ($supportsSubindicator && ($filters['subindicador'] ?? '') !== '') {
            $conditions[] = 'f.id_subindicador = :filter_subindicador';
            $params['filter_subindicador'] = $filters['subindicador'];
        }

        $sql = "SELECT f.id_indicador, MAX(f.$dateField) AS last_date
                FROM $table f
                WHERE f.ativo_sn = 1
                  AND COALESCE(f.$dateField, '') <> ''
                  AND " . implode(' AND ', $conditions) . "
                GROUP BY f.id_indicador";

        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        $dates = [];

        foreach ($statement->fetchAll() as $row) {
            $dates[(string) $row['id_indicador']] = (string) ($row['last_date'] ?? '');
        }

        return $dates;
    }

    private function maxDateValue(?string $left, ?string $right): string
    {
        $left = trim((string) $left);
        $right = trim((string) $right);

        if ($left === '') {
            return $right;
        }

        if ($right === '') {
            return $left;
        }

        return strcmp($left, $right) >= 0 ? $left : $right;
    }

    private function sumByIndicator(string $table, string $valueField, array $context, string $periodId, array $filters = [], bool $supportsSubindicator = false, array $dateRange = []): array
    {
        [$scopeSql, $scopeParams] = $this->buildFactFilterSql(
            $context,
            'f',
            $filters,
            ['segmento', 'diretoria', 'regional', 'agencia', 'gerente_gestao', 'gerente']
        );
        $dateField = $this->resolveFactDateField($table);
        [$dateRangeSql, $dateRangeParams] = $this->buildCalendarDateRangeCondition('f.' . $dateField, $dateRange, 'sum_range');

        $conditions = [$scopeSql, $dateRangeSql];
        $params = array_merge($scopeParams, $dateRangeParams);

        if (($filters['indicadores'] ?? '') !== '') {
            $conditions[] = 'f.id_indicador = :filter_indicador';
            $params['filter_indicador'] = $filters['indicadores'];
        }

        if ($supportsSubindicator && ($filters['subindicador'] ?? '') !== '') {
            $conditions[] = 'f.id_subindicador = :filter_subindicador';
            $params['filter_subindicador'] = $filters['subindicador'];
        }

        $sql = "SELECT f.id_indicador, COALESCE(SUM(f.$valueField), 0) AS total
                FROM $table f
                                WHERE f.ativo_sn = 1
                  AND " . implode(' AND ', $conditions) . "
                GROUP BY f.id_indicador";

        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        $totals = [];

        foreach ($statement->fetchAll() as $row) {
            $totals[(string) $row['id_indicador']] = (float) $row['total'];
        }

        return $totals;
    }

    private function averageByIndicator(string $table, string $valueField, array $context, string $periodId, array $filters = [], array $dateRange = []): array
    {
        [$scopeSql, $scopeParams] = $this->buildFactFilterSql(
            $context,
            'f',
            $filters,
            ['segmento', 'diretoria', 'regional', 'agencia', 'gerente_gestao', 'gerente']
        );
        $dateField = $this->resolveFactDateField($table);
        [$dateRangeSql, $dateRangeParams] = $this->buildCalendarDateRangeCondition('f.' . $dateField, $dateRange, 'avg_range');

        $conditions = [$scopeSql, $dateRangeSql];
        $params = array_merge($scopeParams, $dateRangeParams);

        if (($filters['indicadores'] ?? '') !== '') {
            $conditions[] = 'f.id_indicador = :filter_indicador';
            $params['filter_indicador'] = $filters['indicadores'];
        }

        $sql = "SELECT f.id_indicador, COALESCE(AVG(f.$valueField), 0) AS average_value
                FROM $table f
                                WHERE f.ativo_sn = 1
                  AND " . implode(' AND ', $conditions) . "
                GROUP BY f.id_indicador";

        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        $averages = [];

        foreach ($statement->fetchAll() as $row) {
            $averages[(string) $row['id_indicador']] = (float) $row['average_value'];
        }

        return $averages;
    }

    private function sumFact(string $table, string $valueField, array $context, string $periodId): float
    {
        [$scopeSql, $scopeParams] = $this->buildScopeSql($context, 'f');
        $sql = "SELECT COALESCE(SUM(f.$valueField), 0) AS total
                FROM $table f
                WHERE f.id_periodo = :id_periodo
                  AND f.ativo_sn = 1
                  AND $scopeSql";

        $statement = $this->connection->prepare($sql);
        $statement->execute(array_merge(['id_periodo' => $periodId], $scopeParams));

        return (float) $statement->fetchColumn();
    }

    private function countVisibleFunctionals(array $context, string $periodId, array $dateRange = []): int
    {
        [$scopeSql, $scopeParams] = $this->buildScopeSql($context, 'f');
        [$dateRangeSql, $dateRangeParams] = $this->buildCalendarDateRangeCondition('f.dt_realizado', $dateRange, 'visible_functionals_range');
        $sql = "SELECT COUNT(DISTINCT f.funcional)
                FROM fRealizado f
                WHERE f.ativo_sn = 1
                  AND $scopeSql
                  AND $dateRangeSql";

        $statement = $this->connection->prepare($sql);
        $statement->execute(array_merge($scopeParams, $dateRangeParams));

        return (int) $statement->fetchColumn();
    }

    private function countContracts(array $context, string $periodId, array $dateRange = []): int
    {
        [$scopeSql, $scopeParams] = $this->buildScopeSql($context, 'f');
        [$dateRangeSql, $dateRangeParams] = $this->buildCalendarDateRangeCondition('f.data_transacao', $dateRange, 'contracts_range');
        $sql = "SELECT COUNT(DISTINCT f.numero_contrato)
                FROM fDetalhe f
                WHERE f.ativo_sn = 1
                  AND $scopeSql
                  AND $dateRangeSql";

        $statement = $this->connection->prepare($sql);
        $statement->execute(array_merge($scopeParams, $dateRangeParams));

        return (int) $statement->fetchColumn();
    }

    private function buildScopeSql(array $context, string $alias): array
    {
        $scope = $context['scope'];

        if ($scope['code'] === 'GLOBAL') {
            return ['1 = 1', []];
        }

        if ($scope['code'] === 'SELF') {
            return [sprintf('%s.funcional = :scope_funcional', $alias), ['scope_funcional' => $scope['functional']]];
        }

        if ($scope['code'] === 'DR') {
            $field = $alias . '.juncao_dr';
        } elseif ($scope['code'] === 'GR') {
            $field = $alias . '.juncao_gr';
        } else {
            $field = $alias . '.juncao_ag';
        }

        return $this->buildInCondition($field, $scope['allowed'], 'scope_');
    }

    private function buildInCondition(string $field, array $values, string $prefix): array
    {
        $values = array_values(array_unique(array_filter($values)));

        if ($values === []) {
            return ['1 = 0', []];
        }

        $params = [];
        $placeholders = [];

        foreach ($values as $index => $value) {
            $param = $prefix . $index;
            $placeholders[] = ':' . $param;
            $params[$param] = $value;
        }

        return [sprintf('%s IN (%s)', $field, implode(', ', $placeholders)), $params];
    }

    private function listSegmentOptions(array $context, string $periodId): array
    {
        [$scopeSql, $scopeParams] = $this->buildScopeSql($context, 'f');
        $sql = "SELECT DISTINCT
                    f.segmento AS value,
                    CONCAT(
                        COALESCE(s.juncao_segmento, f.segmento),
                        ' - ',
                        COALESCE(s.nome_segmento, f.segmento)
                    ) AS label
                FROM fMeta f
                LEFT JOIN dSegmento s
                    ON s.segmento = f.segmento
                   AND s.ativo_sn = 1
                WHERE f.id_periodo = :id_periodo
                  AND f.ativo_sn = 1
                  AND COALESCE(f.segmento, '') <> ''
                  AND $scopeSql
                ORDER BY label";

        return $this->fetchOptions($sql, array_merge(['id_periodo' => $periodId], $scopeParams));
    }

    private function listHierarchyOptions(array $context, string $periodId, array $filters, string $junctionField, string $nameField): array
    {
        [$filterSql, $filterParams] = $this->buildFactFilterSql($context, 'f', $filters, ['segmento', 'diretoria', 'regional']);
        $sql = "SELECT DISTINCT f.$junctionField AS value,
                       CONCAT(f.$junctionField, ' - ', COALESCE(m.$nameField, f.$junctionField)) AS label
                FROM fMeta f
                LEFT JOIN dMesu m
                    ON m.$junctionField = f.$junctionField
                   AND m.ativo_sn = 1
                WHERE f.id_periodo = :id_periodo
                  AND f.ativo_sn = 1
                  AND COALESCE(f.$junctionField, '') <> ''
                  AND $filterSql
                ORDER BY label";

        return $this->fetchOptions($sql, array_merge(['id_periodo' => $periodId], $filterParams));
    }

    private function listManagementManagerOptions(array $context, string $periodId, array $filters): array
    {
        [$filterSql, $filterParams] = $this->buildFactFilterSql($context, 'f', $filters, ['segmento', 'diretoria', 'regional', 'agencia']);
        $sql = "SELECT DISTINCT h.funcional AS value, CONCAT(h.funcional, ' - ', h.nome) AS label
                FROM dHierarquia h
                INNER JOIN fMultiJuncao m
                    ON m.funcional = h.funcional
                   AND m.ativo_sn = 1
                   AND m.principal_sn = 1
                   AND m.tipo_juncao = 'AG'
                INNER JOIN fMeta f
                    ON f.juncao_ag = m.juncao
                   AND f.id_periodo = :id_periodo
                   AND f.ativo_sn = 1
                WHERE h.ativo_sn = 1
                  AND h.nivel = 'AG'
                  AND $filterSql
                ORDER BY h.nome";

        return $this->fetchOptions($sql, array_merge(['id_periodo' => $periodId], $filterParams));
    }

    private function listCommercialManagerOptions(array $context, string $periodId, array $filters): array
    {
        if (($context['scope']['code'] ?? '') === 'SELF') {
            $profile = $context['profile'] ?? [];

            if (($profile['nivel'] ?? '') === 'GC') {
                return [[
                    'value' => (string) $profile['funcional'],
                    'label' => sprintf('%s - %s', (string) $profile['funcional'], (string) $profile['nome']),
                ]];
            }
        }

        [$filterSql, $filterParams] = $this->buildFactFilterSql($context, 'f', $filters, ['segmento', 'diretoria', 'regional', 'agencia', 'gerente_gestao']);
        $sql = "SELECT DISTINCT h.funcional AS value, CONCAT(h.funcional, ' - ', h.nome) AS label
                FROM dHierarquia h
                INNER JOIN fMeta f
                    ON f.funcional = h.funcional
                   AND f.id_periodo = :id_periodo
                   AND f.ativo_sn = 1
                WHERE h.ativo_sn = 1
                  AND h.nivel IN ('GC', 'PA', 'AS')
                  AND $filterSql
                ORDER BY h.nome";

        return $this->fetchOptions($sql, array_merge(['id_periodo' => $periodId], $filterParams));
    }

    private function listFamilyOptions(array $context, string $periodId, array $filters): array
    {
        [$filterSql, $filterParams] = $this->buildFactFilterSql($context, 'f', $filters, ['segmento', 'diretoria', 'regional', 'agencia', 'gerente_gestao', 'gerente']);
        $sql = "SELECT DISTINCT CAST(df.id_familia AS CHAR) AS value, df.nome_familia AS label
                FROM dFamiliaProduto df
                INNER JOIN dIndicador i
                    ON i.id_familia = df.id_familia
                   AND i.ativo_sn = 1
                INNER JOIN fMeta f
                    ON f.id_indicador = i.id_indicador
                   AND f.id_periodo = :id_periodo
                   AND f.ativo_sn = 1
                WHERE df.ativo_sn = 1
                  AND $filterSql
                ORDER BY COALESCE(df.ordem_exibicao, 999), df.nome_familia";

        return $this->fetchOptions($sql, array_merge(['id_periodo' => $periodId], $filterParams));
    }

    private function listIndicatorOptions(array $context, string $periodId, array $filters): array
    {
        [$filterSql, $filterParams] = $this->buildFactFilterSql($context, 'f', $filters, ['segmento', 'diretoria', 'regional', 'agencia', 'gerente_gestao', 'gerente']);
        $sql = "SELECT DISTINCT CAST(i.id_indicador AS CHAR) AS value, i.nome_indicador AS label
                FROM dIndicador i
                INNER JOIN fMeta f
                    ON f.id_indicador = i.id_indicador
                   AND f.id_periodo = :id_periodo
                   AND f.ativo_sn = 1
                WHERE i.ativo_sn = 1
                  AND $filterSql";

        $params = array_merge(['id_periodo' => $periodId], $filterParams);

        if (($filters['familia'] ?? '') !== '') {
            $sql .= " AND i.id_familia = :filter_familia";
            $params['filter_familia'] = $filters['familia'];
        }

        $sql .= " ORDER BY COALESCE(i.ordem_exibicao, 999), i.nome_indicador";

        return $this->fetchOptions($sql, $params);
    }

    private function listSubindicatorOptions(array $context, string $periodId, array $filters): array
    {
        [$filterSql, $filterParams] = $this->buildFactFilterSql($context, 'f', $filters, ['segmento', 'diretoria', 'regional', 'agencia', 'gerente_gestao', 'gerente']);
        $sql = "SELECT DISTINCT CAST(s.id_subindicador AS CHAR) AS value, s.nome_subindicador AS label
                FROM dSubindicador s
                INNER JOIN dIndicador i
                    ON i.id_indicador = s.id_indicador
                   AND i.ativo_sn = 1
                INNER JOIN fRealizado f
                    ON f.id_subindicador = s.id_subindicador
                   AND f.id_periodo = :id_periodo
                   AND f.ativo_sn = 1
                WHERE s.ativo_sn = 1
                  AND $filterSql";

        $params = array_merge(['id_periodo' => $periodId], $filterParams);

        if (($filters['familia'] ?? '') !== '') {
            $sql .= " AND i.id_familia = :filter_familia";
            $params['filter_familia'] = $filters['familia'];
        }

        if (($filters['indicadores'] ?? '') !== '') {
            $sql .= " AND s.id_indicador = :filter_indicador";
            $params['filter_indicador'] = $filters['indicadores'];
        }

        $sql .= " ORDER BY COALESCE(s.ordem_exibicao, 999), s.nome_subindicador";

        return $this->fetchOptions($sql, $params);
    }

    private function buildFactFilterSql(array $context, string $alias, array $filters, array $allowedFilters): array
    {
        [$scopeSql, $scopeParams] = $this->buildScopeSql($context, $alias);
        $conditions = [$scopeSql];
        $params = $scopeParams;

        if (in_array('segmento', $allowedFilters, true) && ($filters['segmento'] ?? '') !== '') {
            $conditions[] = $alias . '.segmento = :filter_segmento';
            $params['filter_segmento'] = $filters['segmento'];
        }

        if (in_array('diretoria', $allowedFilters, true) && ($filters['diretoria'] ?? '') !== '') {
            $conditions[] = $alias . '.juncao_dr = :filter_diretoria';
            $params['filter_diretoria'] = $filters['diretoria'];
        }

        if (in_array('regional', $allowedFilters, true) && ($filters['regional'] ?? '') !== '') {
            $conditions[] = $alias . '.juncao_gr = :filter_regional';
            $params['filter_regional'] = $filters['regional'];
        }

        if (in_array('agencia', $allowedFilters, true) && ($filters['agencia'] ?? '') !== '') {
            $conditions[] = $alias . '.juncao_ag = :filter_agencia';
            $params['filter_agencia'] = $filters['agencia'];
        }

        if (in_array('gerente_gestao', $allowedFilters, true) && ($filters['gerente_gestao'] ?? '') !== '') {
            [$agencySql, $agencyParams] = $this->buildInCondition(
                $alias . '.juncao_ag',
                $this->resolveAgenciesByFunctional($filters['gerente_gestao']),
                'filter_gerente_gestao_ag_'
            );
            $conditions[] = $agencySql;
            $params = array_merge($params, $agencyParams);
        }

        if (in_array('gerente', $allowedFilters, true) && ($filters['gerente'] ?? '') !== '') {
            $conditions[] = $alias . '.funcional = :filter_gerente';
            $params['filter_gerente'] = $filters['gerente'];
        }

        return [implode(' AND ', $conditions), $params];
    }

    private function normalizeSummaryFilters(array $requestedFilters): array
    {
        $normalized = [];
        $allowedKeys = [
            'segmento',
            'diretoria',
            'regional',
            'agencia',
            'gerente_gestao',
            'gerente',
            'familia',
            'indicadores',
            'subindicador',
            'status_indicadores',
            'grupo',
            'visao_acumulada',
        ];

        foreach ($allowedKeys as $key) {
            $normalized[$key] = trim((string) ($requestedFilters[$key] ?? ''));
        }

        return $normalized;
    }

    private function resolveExecutiveFocusMeta(array $filters): array
    {
        if (($filters['gerente'] ?? '') !== '') {
            return [
                'level' => 'familia',
                'label' => 'Família',
                'ranking_title' => 'Desempenho por Família',
                'status_title' => 'Status por Família',
            ];
        }

        if (($filters['gerente_gestao'] ?? '') !== '') {
            return [
                'level' => 'gerente',
                'label' => 'Gerente',
                'ranking_title' => 'Desempenho por Gerente',
                'status_title' => 'Status por Gerente',
            ];
        }

        if (($filters['agencia'] ?? '') !== '') {
            return [
                'level' => 'gerente_gestao',
                'label' => 'Gerente de gestão',
                'ranking_title' => 'Desempenho por Gerente de Gestão',
                'status_title' => 'Status por Gerente de Gestão',
            ];
        }

        if (($filters['regional'] ?? '') !== '') {
            return [
                'level' => 'agencia',
                'label' => 'Agência',
                'ranking_title' => 'Desempenho por Agência',
                'status_title' => 'Status por Agência',
            ];
        }

        if (($filters['diretoria'] ?? '') !== '') {
            return [
                'level' => 'regional',
                'label' => 'Regional',
                'ranking_title' => 'Desempenho por Regional',
                'status_title' => 'Status por Regional',
            ];
        }

        if (($filters['segmento'] ?? '') !== '') {
            return [
                'level' => 'diretoria',
                'label' => 'Diretoria',
                'ranking_title' => 'Desempenho por Diretoria',
                'status_title' => 'Status por Diretoria',
            ];
        }

        return [
            'level' => 'regional',
            'label' => 'Regional',
            'ranking_title' => 'Desempenho por Regional',
            'status_title' => 'Status por Regional',
        ];
    }

    private function resolveExecutiveDateContext(array $context, string $periodId, array $filters = [], string $referenceStartDate = '', string $referenceEndDate = ''): array
    {
        $period = $this->getPeriodDefinition($periodId);
        $periodStartRaw = clamp_date_to_today($period['dt_inicio_periodo'] ?? null);
        $periodEndRaw = clamp_date_to_today($period['dt_fim_periodo'] ?? null);
        $rangeStartRaw = clamp_date_to_today($referenceStartDate !== '' ? $referenceStartDate : $periodStartRaw);
        $rangeEndRaw = clamp_date_to_today($referenceEndDate !== '' ? $referenceEndDate : $periodEndRaw);

        if ($rangeStartRaw === '') {
            $rangeStartRaw = $rangeEndRaw !== '' ? $rangeEndRaw : (new \DateTimeImmutable('today'))->format('Y-m-d');
        }

        if ($rangeEndRaw === '') {
            $rangeEndRaw = $rangeStartRaw;
        }

        try {
            $rangeStart = new \DateTimeImmutable($rangeStartRaw);
            $rangeEnd = new \DateTimeImmutable($rangeEndRaw);
        } catch (\Throwable $exception) {
            $rangeStart = new \DateTimeImmutable('today');
            $rangeEnd = new \DateTimeImmutable('today');
        }

        if ($rangeStart > $rangeEnd) {
            [$rangeStart, $rangeEnd] = [$rangeEnd, $rangeStart];
        }

        $monthAnchor = $rangeEnd->modify('first day of this month');
        $forecastEnd = $monthAnchor->modify('last day of this month');
        $historyAnchor = $this->resolveExecutiveHistoryAnchor($context, $periodId, $filters, $rangeEnd->format('Y-m-d'));
        $historyMonthAnchor = $historyAnchor->modify('first day of this month');
        $chartStart = $historyMonthAnchor
            ->sub(new \DateInterval('P5M'))
            ->modify('first day of this month');
        $metaStart = $historyMonthAnchor
            ->sub(new \DateInterval('P5M'))
            ->modify('first day of this month');
        $chartMonths = [];
        $metaMonths = [];
        $cursor = $chartStart;

        while ($cursor <= $historyMonthAnchor) {
            $chartMonths[] = [
                'key' => $cursor->format('Y-m'),
                'label' => $this->formatExecutiveMonthShortLabel($cursor),
            ];
            $cursor = $cursor->add(new \DateInterval('P1M'));
        }

        $cursor = $metaStart;

        while ($cursor <= $historyMonthAnchor) {
            $metaMonths[] = [
                'key' => $cursor->format('Y-m'),
                'label' => $this->formatExecutiveMonthYearLabel($cursor),
            ];
            $cursor = $cursor->add(new \DateInterval('P1M'));
        }

        return [
            'period' => $period,
            'range' => [
                'start' => $rangeStart->format('Y-m-d'),
                'end' => $rangeEnd->format('Y-m-d'),
            ],
            'month_range' => [
                'start' => $monthAnchor->format('Y-m-d'),
                'end' => $rangeEnd->format('Y-m-d'),
            ],
            'forecast_range' => [
                'start' => $monthAnchor->format('Y-m-d'),
                'end' => $forecastEnd->format('Y-m-d'),
            ],
            'chart_range' => [
                'start' => $chartStart->format('Y-m-d'),
                'end' => $historyAnchor->format('Y-m-d'),
                'months' => $chartMonths,
            ],
            'meta_range' => [
                'start' => $metaStart->format('Y-m-d'),
                'end' => $historyAnchor->format('Y-m-d'),
                'months' => $metaMonths,
            ],
            'month_label' => $this->formatMonthLabel($monthAnchor) . ' ' . $monthAnchor->format('Y'),
            'business_days' => resolve_business_day_snapshot(
                $monthAnchor->format('Y-m-d'),
                $forecastEnd->format('Y-m-d'),
                $rangeEnd->format('Y-m-d')
            ),
        ];
    }

    private function resolveExecutiveHistoryAnchor(array $context, string $periodId, array $filters = [], string $fallbackDate = ''): \DateTimeImmutable
    {
        [$scopeSql, $scopeParams] = $this->buildFactFilterSql(
            $context,
            'f',
            $filters,
            ['segmento', 'diretoria', 'regional', 'agencia', 'gerente_gestao', 'gerente']
        );

        $pointSql = "SELECT MAX(f.data_realizado) AS last_date
                     FROM fPontos f
                     INNER JOIN dIndicador i
                         ON i.id_indicador = f.id_indicador
                        AND i.ativo_sn = 1
                     WHERE f.ativo_sn = 1
                       AND COALESCE(f.data_realizado, '') <> ''
                       AND $scopeSql";
        $pointParams = $scopeParams;
        $this->appendExecutiveIndicatorFilters($pointSql, $pointParams, $filters, 'f', 'i');
        $pointStatement = $this->connection->prepare($pointSql);
        $pointStatement->execute($pointParams);
        $pointDate = trim((string) ($pointStatement->fetchColumn() ?: ''));

        $metaSql = "SELECT MAX(f.dt_meta) AS last_date
                    FROM fMeta f
                    INNER JOIN dIndicador i
                        ON i.id_indicador = f.id_indicador
                       AND i.ativo_sn = 1
                    WHERE f.ativo_sn = 1
                      AND COALESCE(f.dt_meta, '') <> ''
                      AND $scopeSql";
        $metaParams = $scopeParams;
        $this->appendExecutiveIndicatorFilters($metaSql, $metaParams, $filters, 'f', 'i');
        $metaStatement = $this->connection->prepare($metaSql);
        $metaStatement->execute($metaParams);
        $metaDate = trim((string) ($metaStatement->fetchColumn() ?: ''));

        $resolvedDate = $this->maxDateValue($pointDate, $metaDate);

        if ($resolvedDate === '') {
            $resolvedDate = clamp_date_to_today($fallbackDate !== '' ? $fallbackDate : null);
        }

        try {
            return new \DateTimeImmutable($resolvedDate !== '' ? $resolvedDate : 'today');
        } catch (\Throwable $exception) {
            return new \DateTimeImmutable('today');
        }
    }

    private function getPeriodDefinition(string $periodId): array
    {
        $statement = $this->connection->prepare(
            'SELECT id_periodo, descricao_periodo, dt_inicio_periodo, dt_fim_periodo
             FROM dPeriodoApuracao
             WHERE id_periodo = :id_periodo
             LIMIT 1'
        );
        $statement->execute(['id_periodo' => $periodId]);

        return $statement->fetch() ?: [
            'id_periodo' => $periodId,
            'descricao_periodo' => $periodId,
            'dt_inicio_periodo' => '',
            'dt_fim_periodo' => '',
        ];
    }

    private function resolveFactDateRange(string $periodId, string $referenceStartDate = '', string $referenceEndDate = ''): array
    {
        $period = $this->getPeriodDefinition($periodId);
        $periodStartRaw = clamp_date_to_today($period['dt_inicio_periodo'] ?? null);
        $periodEndRaw = clamp_date_to_today($period['dt_fim_periodo'] ?? null);
        $rangeStartRaw = clamp_date_to_today($referenceStartDate !== '' ? $referenceStartDate : $periodStartRaw);
        $rangeEndRaw = clamp_date_to_today($referenceEndDate !== '' ? $referenceEndDate : $periodEndRaw);

        if ($rangeStartRaw === '') {
            $rangeStartRaw = $rangeEndRaw !== '' ? $rangeEndRaw : (new \DateTimeImmutable('today'))->format('Y-m-d');
        }

        if ($rangeEndRaw === '') {
            $rangeEndRaw = $rangeStartRaw;
        }

        try {
            $rangeStart = new \DateTimeImmutable($rangeStartRaw);
            $rangeEnd = new \DateTimeImmutable($rangeEndRaw);
        } catch (\Throwable $exception) {
            $rangeStart = new \DateTimeImmutable('today');
            $rangeEnd = new \DateTimeImmutable('today');
        }

        if ($rangeStart > $rangeEnd) {
            [$rangeStart, $rangeEnd] = [$rangeEnd, $rangeStart];
        }

        return [
            'start' => $rangeStart->format('Y-m-d'),
            'end' => $rangeEnd->format('Y-m-d'),
        ];
    }

    private function resolveFactDateField(string $table): string
    {
        $map = [
            'fMeta' => 'dt_meta',
            'fRealizado' => 'dt_realizado',
            'fPontos' => 'data_realizado',
            'fVariavel' => 'data_realizado',
            'fDetalhe' => 'data_transacao',
        ];

        return $map[$table] ?? '';
    }

    private function buildCalendarDateRangeCondition(string $dateExpression, array $dateRange, string $paramPrefix): array
    {
        $start = trim((string) ($dateRange['start'] ?? ''));
        $end = trim((string) ($dateRange['end'] ?? ''));

        if ($start === '' || $end === '') {
            return ['1 = 1', []];
        }

        return [
            "COALESCE($dateExpression, '') <> '' AND EXISTS (
                SELECT 1
                FROM dcalendario dc
                WHERE dc.data_calendario = $dateExpression
                  AND dc.ativo_sn = 1
                  AND dc.data_calendario BETWEEN :{$paramPrefix}_start AND :{$paramPrefix}_end
            )",
            [
                $paramPrefix . '_start' => $start,
                $paramPrefix . '_end' => $end,
            ],
        ];
    }

    private function buildExecutiveKpis(array $context, string $periodId, array $filters, array $dateContext): array
    {
        $monthlyTotals = $this->fetchExecutivePointSummary(
            $context,
            $periodId,
            $filters,
            $dateContext['month_range']['start'],
            $dateContext['month_range']['end']
        );
        $accumulatedTotals = $this->fetchExecutivePointSummary(
            $context,
            $periodId,
            $filters,
            $dateContext['range']['start'],
            $dateContext['range']['end']
        );
        $monthlyMeta = (float) ($monthlyTotals['meta'] ?? 0.0);
        $monthlyReal = (float) ($monthlyTotals['real'] ?? 0.0);
        $forecast = 0.0;
        $businessDays = $dateContext['business_days'];

        if ((int) ($businessDays['elapsed'] ?? 0) > 0) {
            $forecast = ($monthlyReal / (int) $businessDays['elapsed']) * (int) ($businessDays['total'] ?? 0);
        }

        return [
            'real_mens' => $monthlyReal,
            'meta_mens' => $monthlyMeta,
            'real_acum' => (float) ($accumulatedTotals['real'] ?? 0.0),
            'meta_acum' => (float) ($accumulatedTotals['meta'] ?? 0.0),
            'forecast' => $forecast,
            'gap' => $monthlyMeta - $monthlyReal,
            'percent_mens' => $monthlyMeta > 0 ? ($monthlyReal / $monthlyMeta) * 100 : 0.0,
            'percent_forecast' => $monthlyMeta > 0 ? ($forecast / $monthlyMeta) * 100 : 0.0,
            'business_days' => $businessDays,
        ];
    }

    private function fetchExecutivePointSummary(array $context, string $periodId, array $filters, string $startDate, string $endDate): array
    {
        if ($startDate === '' || $endDate === '') {
            return ['real' => 0.0, 'meta' => 0.0];
        }

        [$scopeSql, $scopeParams] = $this->buildFactFilterSql(
            $context,
            'f',
            $filters,
            ['segmento', 'diretoria', 'regional', 'agencia', 'gerente_gestao', 'gerente']
        );
        [$dateSql, $dateParams] = $this->buildCalendarDateRangeCondition('f.data_realizado', ['start' => $startDate, 'end' => $endDate], 'exec_summary_range');

        $sql = "SELECT
                    COALESCE(SUM(f.pontos_realizado), 0) AS real_total,
                    COALESCE(SUM(f.pontos_meta), 0) AS meta_total
                FROM fPontos f
                INNER JOIN dIndicador i
                    ON i.id_indicador = f.id_indicador
                   AND i.ativo_sn = 1
                                WHERE f.ativo_sn = 1
                  AND $scopeSql
                  AND $dateSql";

        $params = array_merge($scopeParams, $dateParams);

        $this->appendExecutiveIndicatorFilters($sql, $params, $filters, 'f', 'i');

        $statement = $this->connection->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch() ?: [];

        return [
            'real' => (float) ($row['real_total'] ?? 0.0),
            'meta' => (float) ($row['meta_total'] ?? 0.0),
        ];
    }

    private function buildExecutiveChart(array $context, string $periodId, array $filters, array $dateContext): array
    {
        [$scopeSql, $scopeParams] = $this->buildFactFilterSql(
            $context,
            'f',
            $filters,
            ['segmento', 'diretoria', 'regional', 'agencia', 'gerente_gestao', 'gerente']
        );
        [$dateSql, $dateParams] = $this->buildCalendarDateRangeCondition(
            'f.data_realizado',
            ['start' => $dateContext['chart_range']['start'], 'end' => $dateContext['chart_range']['end']],
            'exec_chart_range'
        );

        $sql = "SELECT
                    DATE_FORMAT(f.data_realizado, '%Y-%m') AS month_key,
                    fam.id_familia AS section_key,
                    fam.nome_familia AS section_label,
                    COALESCE(fam.ordem_exibicao, 999) AS section_order,
                    COALESCE(SUM(f.pontos_realizado), 0) AS real_total,
                    COALESCE(SUM(f.pontos_meta), 0) AS meta_total
                FROM fPontos f
                INNER JOIN dIndicador i
                    ON i.id_indicador = f.id_indicador
                   AND i.ativo_sn = 1
                INNER JOIN dFamiliaProduto fam
                    ON fam.id_familia = i.id_familia
                   AND fam.ativo_sn = 1
                                WHERE f.ativo_sn = 1
                  AND $scopeSql
                  AND $dateSql";

        $params = array_merge($scopeParams, $dateParams);

        $this->appendExecutiveIndicatorFilters($sql, $params, $filters, 'f', 'i');

        $sql .= ' GROUP BY month_key, fam.id_familia, fam.nome_familia, fam.ordem_exibicao';

        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        $monthKeys = [];
        $labels = [];

        foreach ($dateContext['chart_range']['months'] as $month) {
            $monthKey = (string) ($month['key'] ?? '');

            if ($monthKey === '') {
                continue;
            }

            $monthKeys[] = $monthKey;
            $labels[] = (string) ($month['label'] ?? $monthKey);
        }

        $palette = ['#cc092f', '#e03d4b', '#ff7849', '#f2a23f', '#8f1d3f', '#d85c8f'];
        $seriesMeta = [];
        $seriesValues = [];

        foreach ($statement->fetchAll() as $row) {
            $sectionKey = trim((string) ($row['section_key'] ?? ''));
            $monthKey = trim((string) ($row['month_key'] ?? ''));

            if ($sectionKey === '' || $monthKey === '') {
                continue;
            }

            if (!isset($seriesMeta[$sectionKey])) {
                $seriesMeta[$sectionKey] = [
                    'label' => (string) ($row['section_label'] ?? $sectionKey),
                    'order' => (int) ($row['section_order'] ?? 999),
                ];
            }

            $metaTotal = (float) ($row['meta_total'] ?? 0.0);
            $realTotal = (float) ($row['real_total'] ?? 0.0);
            $seriesValues[$sectionKey][$monthKey] = $metaTotal > 0 ? ($realTotal / $metaTotal) * 100 : 0.0;
        }

        uasort($seriesMeta, static function (array $left, array $right): int {
            $orderComparison = (int) ($left['order'] ?? 999) <=> (int) ($right['order'] ?? 999);

            if ($orderComparison !== 0) {
                return $orderComparison;
            }

            return strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        $series = [];
        $maxValue = 100.0;
        $seriesIndex = 0;

        foreach ($seriesMeta as $sectionKey => $meta) {
            $values = [];

            foreach ($monthKeys as $monthKey) {
                $value = (float) ($seriesValues[$sectionKey][$monthKey] ?? 0.0);
                $values[] = $value;
                $maxValue = max($maxValue, $value);
            }

            $series[] = [
                'id' => (string) $sectionKey,
                'label' => (string) ($meta['label'] ?? $sectionKey),
                'color' => $palette[$seriesIndex % count($palette)],
                'values' => $values,
            ];
            $seriesIndex++;
        }

        $maxValue = ceil($maxValue / 25) * 25;

        return [
            'labels' => $labels,
            'series' => $series,
            'max_value' => max(100.0, $maxValue),
        ];
    }

    private function fetchExecutiveGroupedPerformance(array $context, string $periodId, array $filters, string $level, string $startDate, string $endDate): array
    {
        [$groupSql, $labelSql, $joinsSql] = $this->resolveExecutiveGroupingSql($level);
        [$scopeSql, $scopeParams] = $this->buildFactFilterSql(
            $context,
            'f',
            $filters,
            ['segmento', 'diretoria', 'regional', 'agencia', 'gerente_gestao', 'gerente']
        );
        [$dateSql, $dateParams] = $this->buildCalendarDateRangeCondition('f.data_realizado', ['start' => $startDate, 'end' => $endDate], 'exec_group_range');

        $sql = "SELECT
                    $groupSql AS group_value,
                    $labelSql AS display_label,
                    COALESCE(SUM(f.pontos_realizado), 0) AS real_mens,
                    COALESCE(SUM(f.pontos_meta), 0) AS meta_mens
                FROM fPontos f
                INNER JOIN dIndicador i
                    ON i.id_indicador = f.id_indicador
                   AND i.ativo_sn = 1
                LEFT JOIN dFamiliaProduto fam
                    ON fam.id_familia = i.id_familia
                   AND fam.ativo_sn = 1
                $joinsSql
                                WHERE f.ativo_sn = 1
                  AND $scopeSql
                  AND $dateSql";

        $params = array_merge($scopeParams, $dateParams);

        $this->appendExecutiveIndicatorFilters($sql, $params, $filters, 'f', 'i');

        $sql .= ' GROUP BY group_value, display_label HAVING COALESCE(group_value, "") <> ""';

        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        $rows = [];

        foreach ($statement->fetchAll() as $row) {
            $groupValue = trim((string) ($row['group_value'] ?? ''));
            $displayLabel = trim((string) ($row['display_label'] ?? ''));

            if ($groupValue === '' || $displayLabel === '') {
                continue;
            }

            $realMens = (float) ($row['real_mens'] ?? 0.0);
            $metaMens = (float) ($row['meta_mens'] ?? 0.0);

            $rows[] = [
                'key' => $groupValue,
                'display_label' => $displayLabel,
                'real_mens' => $realMens,
                'meta_mens' => $metaMens,
                'p_mens' => $metaMens > 0 ? ($realMens / $metaMens) * 100 : 0.0,
                'gap' => $realMens - $metaMens,
                'deficit' => max(0.0, $metaMens - $realMens),
            ];
        }

        return $rows;
    }

    private function buildExecutiveStatusBuckets(array $rows): array
    {
        $hit = array_values(array_filter($rows, static function (array $row): bool {
            return (float) ($row['p_mens'] ?? 0.0) >= 100.0;
        }));
        $quase = array_values(array_filter($rows, static function (array $row): bool {
            $percent = (float) ($row['p_mens'] ?? 0.0);

            return $percent >= 90.0 && $percent < 100.0;
        }));
        $longe = array_values(array_filter($rows, static function (array $row): bool {
            return (float) ($row['p_mens'] ?? 0.0) < 90.0;
        }));

        usort($hit, static function (array $left, array $right): int {
            return ((float) ($right['p_mens'] ?? 0.0) <=> (float) ($left['p_mens'] ?? 0.0))
                ?: strcmp((string) ($left['display_label'] ?? ''), (string) ($right['display_label'] ?? ''));
        });
        usort($quase, static function (array $left, array $right): int {
            return ((float) ($right['p_mens'] ?? 0.0) <=> (float) ($left['p_mens'] ?? 0.0))
                ?: strcmp((string) ($left['display_label'] ?? ''), (string) ($right['display_label'] ?? ''));
        });
        usort($longe, static function (array $left, array $right): int {
            return ((float) ($left['gap'] ?? 0.0) <=> (float) ($right['gap'] ?? 0.0))
                ?: strcmp((string) ($left['display_label'] ?? ''), (string) ($right['display_label'] ?? ''));
        });

        return [
            'hit' => array_slice($hit, 0, 5),
            'quase' => array_slice($quase, 0, 5),
            'longe' => array_slice($longe, 0, 5),
            'summary' => [
                'hit' => count($hit),
                'quase' => count($quase),
                'longe' => count($longe),
            ],
        ];
    }

    private function buildExecutiveHeatmap(array $context, string $periodId, array $filters, string $focusLevel, array $dateContext): array
    {
        $unitLevel = $this->resolveExecutiveHeatmapUnitLevel($focusLevel);
        $axisMeta = $this->resolveExecutiveHeatmapAxisMeta($unitLevel, $filters);
        $unitRows = $this->fetchExecutiveGroupedPerformance(
            $context,
            $periodId,
            $filters,
            $unitLevel,
            $dateContext['month_range']['start'],
            $dateContext['month_range']['end']
        );

        usort($unitRows, static function (array $left, array $right): int {
            return ((float) ($right['p_mens'] ?? 0.0) <=> (float) ($left['p_mens'] ?? 0.0))
                ?: strcmp((string) ($left['display_label'] ?? ''), (string) ($right['display_label'] ?? ''));
        });

        $sectionsMatrix = $this->fetchExecutiveHeatmapMatrix(
            $context,
            $periodId,
            $filters,
            $unitLevel,
            'familia',
            $dateContext['month_range']['start'],
            $dateContext['month_range']['end']
        );
        $indicatorsMatrix = $this->fetchExecutiveHeatmapMatrix(
            $context,
            $periodId,
            $filters,
            $unitLevel,
            'indicador',
            $dateContext['month_range']['start'],
            $dateContext['month_range']['end']
        );

        $activeColumns = ($filters['familia'] ?? '') !== ''
            ? $indicatorsMatrix['sections']
            : $sectionsMatrix['sections'];
        $activeData = ($filters['familia'] ?? '') !== ''
            ? $indicatorsMatrix['data']
            : $sectionsMatrix['data'];

        $units = [];

        foreach ($unitRows as $row) {
            $unitKey = (string) ($row['key'] ?? '');

            if ($unitKey === '') {
                continue;
            }

            $units[] = [
                'key' => $unitKey,
                'label' => (string) ($row['display_label'] ?? $unitKey),
                'percent' => (float) ($row['p_mens'] ?? 0.0),
            ];
        }

        return [
            'default_mode' => 'secoes',
            'secoes' => [
                'title' => 'Heatmap — ' . $axisMeta['short_label'] . ' × Seções',
                'row_axis_label' => $axisMeta['short_label'],
                'row_axis_sublabel' => ($filters['familia'] ?? '') !== '' ? 'Indicadores' : 'Famílias',
                'rows' => $units,
                'columns' => $activeColumns,
                'data' => $activeData,
            ],
            'meta' => $this->buildExecutiveMetaHeatmap(
                $context,
                $periodId,
                $filters,
                $dateContext['meta_range']['start'],
                $dateContext['meta_range']['end'],
                $dateContext['meta_range']['months']
            ),
        ];
    }

    private function resolveExecutiveHeatmapUnitLevel(string $focusLevel): string
    {
        return $focusLevel === 'familia' ? 'gerente' : $focusLevel;
    }

    private function resolveExecutiveHeatmapAxisMeta(string $unitLevel, array $filters): array
    {
        $map = [
            'diretoria' => ['short_label' => 'DR', 'full_label' => 'Diretoria'],
            'regional' => ['short_label' => 'GR', 'full_label' => 'Regional'],
            'agencia' => ['short_label' => 'AG', 'full_label' => 'Agência'],
            'gerente_gestao' => ['short_label' => 'GG', 'full_label' => 'Gerente de gestão'],
            'gerente' => ['short_label' => 'GC', 'full_label' => 'Gerente'],
        ];

        $resolved = $map[$unitLevel] ?? ['short_label' => 'GR', 'full_label' => 'Regional'];

        if (($filters['familia'] ?? '') !== '') {
            $resolved['column_label'] = 'Indicadores';
        } else {
            $resolved['column_label'] = 'Famílias';
        }

        return $resolved;
    }

    private function buildExecutiveMetaHeatmap(array $context, string $periodId, array $filters, string $startDate, string $endDate, array $months): array
    {
        $rows = [
            ['key' => 'diretoria', 'label' => 'Todas Diretorias'],
            ['key' => 'regional', 'label' => 'Todas Regionais'],
            ['key' => 'agencia', 'label' => 'Todas Agências'],
            ['key' => 'gerente_gestao', 'label' => 'Todos Ger. de Gestão'],
            ['key' => 'gerente', 'label' => 'Todos GCs'],
        ];
        $data = [];

        foreach ($rows as $row) {
            $monthlyTotals = $this->fetchExecutiveMetaMonthlyTotals(
                $context,
                $periodId,
                $filters,
                $row['key'],
                $startDate,
                $endDate,
                $months
            );

            foreach ($months as $month) {
                $monthKey = trim((string) ($month['key'] ?? ''));

                if ($monthKey === '') {
                    continue;
                }

                $data[$row['key']][$monthKey] = (float) ($monthlyTotals[$monthKey] ?? 0.0);
            }
        }

        return [
            'title' => 'Heatmap — Variação da meta (mês a mês)',
            'row_axis_label' => 'Hierarquia',
            'row_axis_sublabel' => 'Mês',
            'rows' => $rows,
            'months' => $months,
            'data' => $data,
        ];
    }

    private function fetchExecutiveMetaMonthlyTotals(array $context, string $periodId, array $filters, string $level, string $startDate, string $endDate, array $months): array
    {
        [$groupSql, $_groupLabelSql, $joinsSql] = $this->resolveExecutiveGroupingSql($level);
        [$scopeSql, $scopeParams] = $this->buildFactFilterSql(
            $context,
            'f',
            $filters,
            ['segmento', 'diretoria', 'regional', 'agencia', 'gerente_gestao', 'gerente']
        );
        [$dateSql, $dateParams] = $this->buildCalendarDateRangeCondition('f.dt_meta', ['start' => $startDate, 'end' => $endDate], 'exec_meta_range');

        $sql = "SELECT
                    DATE_FORMAT(f.dt_meta, '%Y-%m') AS month_key,
                    $groupSql AS group_value,
                    COALESCE(SUM(f.valor_meta), 0) AS meta_total
                FROM fMeta f
                INNER JOIN dIndicador i
                    ON i.id_indicador = f.id_indicador
                   AND i.ativo_sn = 1
                LEFT JOIN dFamiliaProduto fam
                    ON fam.id_familia = i.id_familia
                   AND fam.ativo_sn = 1
                $joinsSql
                WHERE f.ativo_sn = 1
                  AND $scopeSql
                  AND $dateSql";

        $params = array_merge($scopeParams, $dateParams);

        $this->appendExecutiveIndicatorFilters($sql, $params, $filters, 'f', 'i');

        $sql .= ' GROUP BY month_key, group_value HAVING COALESCE(group_value, "") <> ""';

        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        $totals = [];

        foreach ($statement->fetchAll() as $row) {
            $monthKey = trim((string) ($row['month_key'] ?? ''));

            if ($monthKey === '') {
                continue;
            }

            $totals[$monthKey] = ($totals[$monthKey] ?? 0.0) + (float) ($row['meta_total'] ?? 0.0);
        }

        foreach ($months as $month) {
            $monthKey = trim((string) ($month['key'] ?? ''));

            if ($monthKey !== '' && !isset($totals[$monthKey])) {
                $totals[$monthKey] = 0.0;
            }
        }

        return $totals;
    }

    private function fetchExecutiveHeatmapMatrix(array $context, string $periodId, array $filters, string $unitLevel, string $sectionMode, string $startDate, string $endDate): array
    {
        [$unitSql, $unitLabelSql, $unitJoinsSql] = $this->resolveExecutiveGroupingSql($unitLevel);
        [$scopeSql, $scopeParams] = $this->buildFactFilterSql(
            $context,
            'f',
            $filters,
            ['segmento', 'diretoria', 'regional', 'agencia', 'gerente_gestao', 'gerente']
        );
        [$dateSql, $dateParams] = $this->buildCalendarDateRangeCondition('f.data_realizado', ['start' => $startDate, 'end' => $endDate], 'exec_heatmap_range');

        if ($sectionMode === 'indicador') {
            $sectionSql = 'i.id_indicador';
            $sectionLabelSql = 'i.nome_indicador';
            $sectionOrderSql = 'COALESCE(i.ordem_exibicao, 999)';
        } else {
            $sectionSql = 'fam.id_familia';
            $sectionLabelSql = 'fam.nome_familia';
            $sectionOrderSql = 'COALESCE(fam.ordem_exibicao, 999)';
        }

        $sql = "SELECT
                    $unitSql AS unit_key,
                    $unitLabelSql AS unit_label,
                    $sectionSql AS section_key,
                    $sectionLabelSql AS section_label,
                    $sectionOrderSql AS section_order,
                    COALESCE(SUM(f.pontos_realizado), 0) AS real_total,
                    COALESCE(SUM(f.pontos_meta), 0) AS meta_total
                FROM fPontos f
                INNER JOIN dIndicador i
                    ON i.id_indicador = f.id_indicador
                   AND i.ativo_sn = 1
                LEFT JOIN dFamiliaProduto fam
                    ON fam.id_familia = i.id_familia
                   AND fam.ativo_sn = 1
                $unitJoinsSql
                                WHERE f.ativo_sn = 1
                  AND $scopeSql
                  AND $dateSql";

        $params = array_merge($scopeParams, $dateParams);

        $this->appendExecutiveIndicatorFilters($sql, $params, $filters, 'f', 'i');

        $sql .= ' GROUP BY unit_key, unit_label, section_key, section_label, section_order';

        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        $sections = [];
        $units = [];
        $data = [];

        foreach ($statement->fetchAll() as $row) {
            $unitKey = trim((string) ($row['unit_key'] ?? ''));
            $sectionKey = trim((string) ($row['section_key'] ?? ''));

            if ($unitKey === '' || $sectionKey === '') {
                continue;
            }

            $units[$unitKey] = [
                'key' => $unitKey,
                'label' => trim((string) ($row['unit_label'] ?? $unitKey)),
            ];
            $sections[$sectionKey] = [
                'key' => $sectionKey,
                'label' => trim((string) ($row['section_label'] ?? $sectionKey)),
                'order' => (int) ($row['section_order'] ?? 999),
            ];

            $realTotal = (float) ($row['real_total'] ?? 0.0);
            $metaTotal = (float) ($row['meta_total'] ?? 0.0);
            $data[$unitKey][$sectionKey] = [
                'real' => $realTotal,
                'meta' => $metaTotal,
                'percent' => $metaTotal > 0 ? ($realTotal / $metaTotal) * 100 : 0.0,
            ];
        }

        uasort($sections, static function (array $left, array $right): int {
            $orderComparison = (int) ($left['order'] ?? 999) <=> (int) ($right['order'] ?? 999);

            if ($orderComparison !== 0) {
                return $orderComparison;
            }

            return strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        return [
            'units' => array_values($units),
            'sections' => array_values($sections),
            'data' => $data,
        ];
    }

    private function fetchExecutiveUnitTrend(array $context, string $periodId, array $filters, string $unitLevel, string $startDate, string $endDate, array $months): array
    {
        [$unitSql, $_unitLabelSql, $unitJoinsSql] = $this->resolveExecutiveGroupingSql($unitLevel);
        [$scopeSql, $scopeParams] = $this->buildFactFilterSql(
            $context,
            'f',
            $filters,
            ['segmento', 'diretoria', 'regional', 'agencia', 'gerente_gestao', 'gerente']
        );

        $sql = "SELECT
                    $unitSql AS unit_key,
                    DATE_FORMAT(f.data_realizado, '%Y-%m') AS month_key,
                    COALESCE(SUM(f.pontos_realizado), 0) AS real_total,
                    COALESCE(SUM(f.pontos_meta), 0) AS meta_total
                FROM fPontos f
                INNER JOIN dIndicador i
                    ON i.id_indicador = f.id_indicador
                   AND i.ativo_sn = 1
                LEFT JOIN dFamiliaProduto fam
                    ON fam.id_familia = i.id_familia
                   AND fam.ativo_sn = 1
                $unitJoinsSql
                                WHERE f.ativo_sn = 1
                  AND COALESCE(f.data_realizado, '') <> ''
                  AND f.data_realizado BETWEEN :trend_start_date AND :trend_end_date
                  AND $scopeSql";

        $params = array_merge([
            'trend_start_date' => $startDate,
            'trend_end_date' => $endDate,
        ], $scopeParams);

        $this->appendExecutiveIndicatorFilters($sql, $params, $filters, 'f', 'i');

        $sql .= ' GROUP BY unit_key, month_key';

        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        $monthKeys = [];

        foreach ($months as $month) {
            $monthKey = trim((string) ($month['key'] ?? ''));

            if ($monthKey !== '') {
                $monthKeys[] = $monthKey;
            }
        }

        $trend = [];

        foreach ($statement->fetchAll() as $row) {
            $unitKey = trim((string) ($row['unit_key'] ?? ''));
            $monthKey = trim((string) ($row['month_key'] ?? ''));

            if ($unitKey === '' || $monthKey === '') {
                continue;
            }

            $metaTotal = (float) ($row['meta_total'] ?? 0.0);
            $realTotal = (float) ($row['real_total'] ?? 0.0);
            $trend[$unitKey][$monthKey] = $metaTotal > 0 ? ($realTotal / $metaTotal) * 100 : 0.0;
        }

        foreach ($trend as $unitKey => $values) {
            $ordered = [];

            foreach ($monthKeys as $monthKey) {
                $ordered[] = [
                    'key' => $monthKey,
                    'percent' => (float) ($values[$monthKey] ?? 0.0),
                ];
            }

            $trend[$unitKey] = $ordered;
        }

        return $trend;
    }

    private function appendExecutiveIndicatorFilters(string &$sql, array &$params, array $filters, string $factAlias = 'f', string $indicatorAlias = 'i'): void
    {
        if (($filters['familia'] ?? '') !== '') {
            $sql .= " AND $indicatorAlias.id_familia = :exec_filter_familia";
            $params['exec_filter_familia'] = $filters['familia'];
        }

        if (($filters['indicadores'] ?? '') !== '') {
            $sql .= " AND $factAlias.id_indicador = :exec_filter_indicador";
            $params['exec_filter_indicador'] = $filters['indicadores'];
        }
    }

    private function resolveExecutiveGroupingSql(string $level): array
    {
        if ($level === 'familia') {
            return [
                'fam.id_familia',
                'COALESCE(fam.nome_familia, "Sem família")',
                '',
            ];
        }

        return $this->resolveRankingGroupingSql($level);
    }

    private function formatExecutiveMonthShortLabel(\DateTimeImmutable $date): string
    {
        $map = [
            1 => 'Jan',
            2 => 'Fev',
            3 => 'Mar',
            4 => 'Abr',
            5 => 'Mai',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Ago',
            9 => 'Set',
            10 => 'Out',
            11 => 'Nov',
            12 => 'Dez',
        ];

        return $map[(int) $date->format('n')] ?? $date->format('m');
    }

    private function formatExecutiveMonthYearLabel(\DateTimeImmutable $date): string
    {
        $map = [
            1 => 'jan',
            2 => 'fev',
            3 => 'mar',
            4 => 'abr',
            5 => 'mai',
            6 => 'jun',
            7 => 'jul',
            8 => 'ago',
            9 => 'set',
            10 => 'out',
            11 => 'nov',
            12 => 'dez',
        ];

        return ($map[(int) $date->format('n')] ?? $date->format('m')) . '/' . $date->format('y');
    }

    private function resolveRankingLevel(array $filters): string
    {
        if (($filters['gerente'] ?? '') !== '') {
            return 'gerente';
        }

        if (($filters['gerente_gestao'] ?? '') !== '') {
            return 'gerente_gestao';
        }

        if (($filters['agencia'] ?? '') !== '') {
            return 'agencia';
        }

        if (($filters['regional'] ?? '') !== '') {
            return 'regional';
        }

        if (($filters['diretoria'] ?? '') !== '') {
            return 'diretoria';
        }

        if (($filters['segmento'] ?? '') !== '') {
            return 'segmento';
        }

        return 'gerente_gestao';
    }

    private function resolveRankingLevelLabel(string $level): string
    {
        $map = [
            'segmento' => 'Segmento',
            'diretoria' => 'Diretoria',
            'regional' => 'Regional',
            'agencia' => 'Agência',
            'gerente_gestao' => 'Gerente de gestão',
            'gerente' => 'Gerente',
        ];

        return $map[$level] ?? 'Gerente de gestão';
    }

    private function resolveRankingGroupingSql(string $level): array
    {
        switch ($level) {
            case 'segmento':
                return [
                    'f.segmento',
                    "CONCAT(COALESCE(s.juncao_segmento, f.segmento), ' - ', COALESCE(s.nome_segmento, f.segmento))",
                    "LEFT JOIN dSegmento s
                        ON s.segmento = f.segmento
                       AND s.ativo_sn = 1",
                ];

            case 'diretoria':
                return [
                    'f.juncao_dr',
                    "CONCAT(f.juncao_dr, ' - ', COALESCE(dr.nome_diretoria, f.juncao_dr))",
                    "LEFT JOIN (
                        SELECT juncao_dr, MAX(nome_diretoria) AS nome_diretoria
                        FROM dMesu
                        WHERE ativo_sn = 1
                        GROUP BY juncao_dr
                    ) dr ON dr.juncao_dr = f.juncao_dr",
                ];

            case 'regional':
                return [
                    'f.juncao_gr',
                    "CONCAT(f.juncao_gr, ' - ', COALESCE(gr.nome_regional, f.juncao_gr))",
                    "LEFT JOIN (
                        SELECT juncao_gr, MAX(nome_regional) AS nome_regional
                        FROM dMesu
                        WHERE ativo_sn = 1
                        GROUP BY juncao_gr
                    ) gr ON gr.juncao_gr = f.juncao_gr",
                ];

            case 'agencia':
                return [
                    'f.juncao_ag',
                    "CONCAT(f.juncao_ag, ' - ', COALESCE(ag.nome_agencia, f.juncao_ag))",
                    "LEFT JOIN (
                        SELECT juncao_ag, MAX(nome_agencia) AS nome_agencia
                        FROM dMesu
                        WHERE ativo_sn = 1
                        GROUP BY juncao_ag
                    ) ag ON ag.juncao_ag = f.juncao_ag",
                ];

            case 'gerente':
                return [
                    'f.funcional',
                    "CONCAT(f.funcional, ' - ', COALESCE(hg.nome, f.funcional))",
                    "LEFT JOIN dHierarquia hg
                        ON hg.funcional = f.funcional
                       AND hg.ativo_sn = 1",
                ];

            case 'gerente_gestao':
            default:
                return [
                    'hgg.funcional',
                    "CONCAT(hgg.funcional, ' - ', COALESCE(hgg.nome, hgg.funcional))",
                    "LEFT JOIN fMultiJuncao mgg
                        ON mgg.juncao = f.juncao_ag
                       AND mgg.tipo_juncao = 'AG'
                       AND mgg.principal_sn = 1
                       AND mgg.ativo_sn = 1
                    LEFT JOIN dHierarquia hgg
                        ON hgg.funcional = mgg.funcional
                       AND hgg.nivel = 'AG'
                       AND hgg.ativo_sn = 1",
                ];
        }
    }

    public function sanitizeFilterSelection(string $selectedValue, array $options, string $defaultValue = ''): string
    {
        if ($selectedValue === '') {
            return $defaultValue;
        }

        foreach ($options as $option) {
            if ((string) ($option['value'] ?? '') === $selectedValue) {
                return $selectedValue;
            }
        }

        return $defaultValue;
    }

    private function prettifySegmentName(string $value): string
    {
        $map = [
            'NEGOCIOS' => 'Negócios',
            'DIGITAL' => 'Digital',
        ];

        return $map[$value] ?? ucwords(strtolower(str_replace('_', ' ', $value)));
    }

    private function formatMonthLabel(\DateTimeImmutable $date): string
    {
        $months = [
            1 => 'Janeiro',
            2 => 'Fevereiro',
            3 => 'Março',
            4 => 'Abril',
            5 => 'Maio',
            6 => 'Junho',
            7 => 'Julho',
            8 => 'Agosto',
            9 => 'Setembro',
            10 => 'Outubro',
            11 => 'Novembro',
            12 => 'Dezembro',
        ];

        $monthNumber = (int) $date->format('n');

        return $months[$monthNumber] ?? $date->format('m/Y');
    }

    private function resolveAgenciesByFunctional(string $functional): array
    {
        $statement = $this->connection->prepare(
            "SELECT DISTINCT juncao
             FROM fMultiJuncao
             WHERE funcional = :funcional
               AND tipo_juncao = 'AG'
               AND ativo_sn = 1"
        );
        $statement->execute(['funcional' => $functional]);

        return array_column($statement->fetchAll(), 'juncao');
    }

    private function fetchOptions(string $sql, array $params = []): array
    {
        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        $options = [];

        foreach ($statement->fetchAll() as $row) {
            $value = trim((string) ($row['value'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));

            if ($value === '' || $label === '') {
                continue;
            }

            $options[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $options;
    }
}