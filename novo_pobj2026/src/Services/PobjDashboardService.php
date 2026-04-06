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

    public function getDashboardData(array $context, string $periodId): array
    {
        $indicatorRows = $this->getIndicatorRows($context, $periodId);
        $indicatorCount = count($indicatorRows);
        $achievedCount = 0;

        foreach ($indicatorRows as $row) {
            if ($row['meta'] > 0 && $row['realizado'] >= $row['meta']) {
                $achievedCount++;
            }
        }

        $pontosMeta = $this->sumFact('fPontos', 'pontos_meta', $context, $periodId);
        $pontosRealizado = $this->sumFact('fPontos', 'pontos_realizado', $context, $periodId);
        $variavelMeta = $this->sumFact('fVariavel', 'variavel_meta', $context, $periodId);
        $variavelRealizada = $this->sumFact('fVariavel', 'variavel_realizada', $context, $periodId);
        $visibleFunctionals = $this->countVisibleFunctionals($context, $periodId);
        $contractsInScope = $this->countContracts($context, $periodId);

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

    private function getIndicatorRows(array $context, string $periodId): array
    {
        $sql = "SELECT
                    i.id_indicador,
                    i.nome_indicador,
                    f.nome_familia,
                    t.id_tipo_indicador,
                    t.nome_tipo_indicador
                FROM dIndicador i
                INNER JOIN dFamiliaProduto f ON f.id_familia = i.id_familia
                INNER JOIN dTipoIndicador t ON t.id_tipo_indicador = i.id_tipo_indicador
                WHERE i.ativo_sn = 1
                ORDER BY
                    COALESCE(f.ordem_exibicao, 999),
                    COALESCE(i.ordem_exibicao, 999),
                    i.nome_indicador";

        $rows = $this->connection->query($sql)->fetchAll();
        $metaMap = $this->sumByIndicator('fMeta', 'valor_meta', $context, $periodId);
        $realizadoMap = $this->sumByIndicator('fRealizado', 'valor_realizado', $context, $periodId);
        $pontosMap = $this->sumByIndicator('fPontos', 'pontos_realizado', $context, $periodId);
        $variavelMap = $this->sumByIndicator('fVariavel', 'variavel_realizada', $context, $periodId);

        foreach ($rows as &$row) {
            $indicatorId = (string) $row['id_indicador'];
            $row['meta'] = $metaMap[$indicatorId] ?? 0.0;
            $row['realizado'] = $realizadoMap[$indicatorId] ?? 0.0;
            $row['pontos'] = $pontosMap[$indicatorId] ?? 0.0;
            $row['variavel'] = $variavelMap[$indicatorId] ?? 0.0;
            $row['percentual_atingimento'] = $row['meta'] > 0 ? ($row['realizado'] / $row['meta']) * 100 : 0.0;
            $row['atingiu'] = $row['meta'] > 0 && $row['realizado'] >= $row['meta'];
        }
        unset($row);

        return $rows;
    }

    private function sumByIndicator(string $table, string $valueField, array $context, string $periodId): array
    {
        [$scopeSql, $scopeParams] = $this->buildScopeSql($context, 'f');
        $sql = "SELECT f.id_indicador, COALESCE(SUM(f.$valueField), 0) AS total
                FROM $table f
                WHERE f.id_periodo = :id_periodo
                  AND f.ativo_sn = 1
                  AND $scopeSql
                GROUP BY f.id_indicador";

        $statement = $this->connection->prepare($sql);
        $statement->execute(array_merge(['id_periodo' => $periodId], $scopeParams));

        $totals = [];

        foreach ($statement->fetchAll() as $row) {
            $totals[(string) $row['id_indicador']] = (float) $row['total'];
        }

        return $totals;
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

    private function countVisibleFunctionals(array $context, string $periodId): int
    {
        [$scopeSql, $scopeParams] = $this->buildScopeSql($context, 'f');
        $sql = "SELECT COUNT(DISTINCT f.funcional)
                FROM fRealizado f
                WHERE f.id_periodo = :id_periodo
                  AND f.ativo_sn = 1
                  AND $scopeSql";

        $statement = $this->connection->prepare($sql);
        $statement->execute(array_merge(['id_periodo' => $periodId], $scopeParams));

        return (int) $statement->fetchColumn();
    }

    private function countContracts(array $context, string $periodId): int
    {
        [$scopeSql, $scopeParams] = $this->buildScopeSql($context, 'f');
        $sql = "SELECT COUNT(DISTINCT f.numero_contrato)
                FROM fDetalhe f
                WHERE f.id_periodo = :id_periodo
                  AND f.ativo_sn = 1
                  AND $scopeSql";

        $statement = $this->connection->prepare($sql);
        $statement->execute(array_merge(['id_periodo' => $periodId], $scopeParams));

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
}