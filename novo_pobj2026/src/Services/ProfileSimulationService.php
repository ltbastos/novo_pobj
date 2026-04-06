<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class ProfileSimulationService
{
    private const LEVEL_SCOPE_RANK = [
        'AS' => 0,
        'GC' => 0,
        'PA' => 0,
        'GG' => 1,
        'TL' => 1,
        'AG' => 1,
        'GE' => 2,
        'GR' => 2,
        'DR' => 3,
        'DP' => 4,
    ];

    private const JUNCTION_SCOPE_RANK = [
        'AG' => 1,
        'GR' => 2,
        'DR' => 3,
        'DP' => 4,
    ];

    /** @var PDO */
    private $connection;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    public function listProfiles(): array
    {
        $sql = "SELECT
                    h.funcional,
                    h.nome,
                    h.email,
                    h.nivel,
                    COALESCE(m.juncao, '') AS juncao_principal,
                    COALESCE(m.tipo_juncao, '') AS tipo_juncao
                FROM dHierarquia h
                LEFT JOIN fMultiJuncao m
                    ON m.funcional = h.funcional
                   AND m.ativo_sn = 1
                   AND m.principal_sn = 1
                WHERE h.ativo_sn = 1
                ORDER BY
                    CASE h.nivel
                        WHEN 'DP' THEN 1
                        WHEN 'DR' THEN 2
                        WHEN 'GE' THEN 3
                        WHEN 'GR' THEN 4
                        WHEN 'AG' THEN 5
                        WHEN 'GG' THEN 6
                        WHEN 'TL' THEN 7
                        WHEN 'GC' THEN 8
                        WHEN 'PA' THEN 9
                        WHEN 'AS' THEN 10
                        ELSE 99
                    END,
                    h.nome";

        return $this->connection->query($sql)->fetchAll();
    }

    public function profileExists(string $functional): bool
    {
        $statement = $this->connection->prepare('SELECT 1 FROM dHierarquia WHERE funcional = :funcional AND ativo_sn = 1 LIMIT 1');
        $statement->execute(['funcional' => $functional]);

        return (bool) $statement->fetchColumn();
    }

    public function getContext(string $functional): ?array
    {
        $profileStatement = $this->connection->prepare(
            'SELECT funcional, nome, email, cargo, nivel FROM dHierarquia WHERE funcional = :funcional AND ativo_sn = 1 LIMIT 1'
        );
        $profileStatement->execute(['funcional' => $functional]);
        $profile = $profileStatement->fetch();

        if (!$profile) {
            return null;
        }

        $junctionStatement = $this->connection->prepare(
            'SELECT funcional, juncao, tipo_juncao, principal_sn, ativo_sn FROM fMultiJuncao WHERE funcional = :funcional AND ativo_sn = 1 ORDER BY principal_sn DESC, juncao'
        );
        $junctionStatement->execute(['funcional' => $functional]);
        $junctions = $junctionStatement->fetchAll();

        $scope = $this->resolveScope($profile['nivel'], $junctions, $profile['funcional']);

        return [
            'profile' => $profile,
            'junctions' => $junctions,
            'scope' => $scope,
        ];
    }

    private function resolveScope(string $level, array $junctions, string $functional): array
    {
        foreach ($junctions as $junction) {
            if ($junction['juncao'] === '4000') {
                return [
                    'code' => 'GLOBAL',
                    'label' => 'Visao total do departamento',
                    'allowed' => [],
                    'functional' => $functional,
                ];
            }
        }

        if (in_array($level, ['GC', 'PA', 'AS'], true)) {
            return [
                'code' => 'SELF',
                'label' => 'Carteira propria do usuario selecionado',
                'allowed' => [],
                'functional' => $functional,
            ];
        }

        $baseRank = self::LEVEL_SCOPE_RANK[$level] ?? 0;
        $junctionRank = 0;

        foreach ($junctions as $junction) {
            $junctionRank = max($junctionRank, self::JUNCTION_SCOPE_RANK[$junction['tipo_juncao']] ?? 0);
        }

        $effectiveRank = max($baseRank, $junctionRank);

        if ($effectiveRank === 3) {
            return [
                'code' => 'DR',
                'label' => 'Visao de diretoria',
                'allowed' => $this->resolveDrJunctions($junctions),
                'functional' => $functional,
            ];
        }

        if ($effectiveRank === 2) {
            return [
                'code' => 'GR',
                'label' => 'Visao regional',
                'allowed' => $this->resolveGrJunctions($junctions),
                'functional' => $functional,
            ];
        }

        if ($effectiveRank === 1) {
            return [
                'code' => 'AG',
                'label' => 'Visao de agencia',
                'allowed' => $this->resolveAgJunctions($junctions),
                'functional' => $functional,
            ];
        }

        return [
            'code' => 'SELF',
            'label' => 'Carteira propria do usuario selecionado',
            'allowed' => [],
            'functional' => $functional,
        ];
    }

    private function resolveAgJunctions(array $junctions): array
    {
        $agencies = [];

        foreach ($junctions as $junction) {
            if ($junction['tipo_juncao'] === 'AG') {
                $agencies[] = $junction['juncao'];
            }
        }

        $agencies = array_values(array_unique($agencies));
        sort($agencies);

        return $agencies;
    }

    private function resolveGrJunctions(array $junctions): array
    {
        $regionals = [];
        $agencyJunctions = [];

        foreach ($junctions as $junction) {
            if ($junction['tipo_juncao'] === 'GR') {
                $regionals[] = $junction['juncao'];
            }

            if ($junction['tipo_juncao'] === 'AG') {
                $agencyJunctions[] = $junction['juncao'];
            }
        }

        if ($agencyJunctions !== []) {
            $placeholders = implode(', ', array_fill(0, count($agencyJunctions), '?'));
            $statement = $this->connection->prepare("SELECT DISTINCT juncao_gr FROM dMesu WHERE ativo_sn = 1 AND juncao_ag IN ($placeholders)");
            $statement->execute($agencyJunctions);
            $regionals = array_merge($regionals, array_column($statement->fetchAll(), 'juncao_gr'));
        }

        $regionals = array_values(array_unique(array_filter($regionals)));
        sort($regionals);

        return $regionals;
    }

    private function resolveDrJunctions(array $junctions): array
    {
        $directorates = [];
        $regionalJunctions = [];
        $agencyJunctions = [];

        foreach ($junctions as $junction) {
            if ($junction['tipo_juncao'] === 'DR') {
                $directorates[] = $junction['juncao'];
            }

            if ($junction['tipo_juncao'] === 'GR') {
                $regionalJunctions[] = $junction['juncao'];
            }

            if ($junction['tipo_juncao'] === 'AG') {
                $agencyJunctions[] = $junction['juncao'];
            }
        }

        if ($regionalJunctions !== []) {
            $placeholders = implode(', ', array_fill(0, count($regionalJunctions), '?'));
            $statement = $this->connection->prepare("SELECT DISTINCT juncao_dr FROM dMesu WHERE ativo_sn = 1 AND juncao_gr IN ($placeholders)");
            $statement->execute($regionalJunctions);
            $directorates = array_merge($directorates, array_column($statement->fetchAll(), 'juncao_dr'));
        }

        if ($agencyJunctions !== []) {
            $placeholders = implode(', ', array_fill(0, count($agencyJunctions), '?'));
            $statement = $this->connection->prepare("SELECT DISTINCT juncao_dr FROM dMesu WHERE ativo_sn = 1 AND juncao_ag IN ($placeholders)");
            $statement->execute($agencyJunctions);
            $directorates = array_merge($directorates, array_column($statement->fetchAll(), 'juncao_dr'));
        }

        $directorates = array_values(array_unique(array_filter($directorates)));
        sort($directorates);

        return $directorates;
    }
}