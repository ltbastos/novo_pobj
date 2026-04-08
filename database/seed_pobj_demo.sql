USE novo_pobj;

CREATE TABLE IF NOT EXISTS dSegmento (
    id_segmento BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    segmento VARCHAR(20) NOT NULL,
    juncao_segmento CHAR(4) NOT NULL,
    nome_segmento VARCHAR(150) NOT NULL,
    ativo_sn TINYINT(1) NOT NULL DEFAULT 1,
    origem_carga VARCHAR(50) NOT NULL DEFAULT 'SEED',
    dt_inclusao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    dt_atualizacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_segmento),
    UNIQUE KEY uk_dsegmento_segmento (segmento),
    UNIQUE KEY uk_dsegmento_juncao (juncao_segmento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS dGrupo (
    id_grupo CHAR(4) NOT NULL,
    nome_grupo VARCHAR(150) NOT NULL,
    ativo_sn TINYINT(1) NOT NULL DEFAULT 1,
    origem_carga VARCHAR(50) NOT NULL DEFAULT 'SEED',
    dt_inclusao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    dt_atualizacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_grupo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS fGrupoFuncional (
    id_grupo_funcional BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    funcional CHAR(7) NOT NULL,
    id_grupo CHAR(4) NOT NULL,
    ativo_sn TINYINT(1) NOT NULL DEFAULT 1,
    origem_carga VARCHAR(50) NOT NULL DEFAULT 'SEED',
    dt_inclusao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    dt_atualizacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_grupo_funcional),
    UNIQUE KEY uk_fgrupofuncional_funcional (funcional),
    KEY idx_fgrupofuncional_grupo (id_grupo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO dSegmento (
    segmento,
    juncao_segmento,
    nome_segmento,
    ativo_sn,
    origem_carga
) VALUES
    ('NEGOCIOS', '8607', 'Empresas', TRUE, 'SEED'),
    ('DIGITAL', '8608', 'Digital', TRUE, 'SEED')
ON DUPLICATE KEY UPDATE
    nome_segmento = VALUES(nome_segmento),
    ativo_sn = VALUES(ativo_sn),
    origem_carga = VALUES(origem_carga);

INSERT INTO dGrupo (
    id_grupo,
    nome_grupo,
    ativo_sn,
    origem_carga
) VALUES
    ('G001', 'Grupo Norte', TRUE, 'SEED'),
    ('G002', 'Grupo Sul', TRUE, 'SEED')
ON DUPLICATE KEY UPDATE
    nome_grupo = VALUES(nome_grupo),
    ativo_sn = VALUES(ativo_sn),
    origem_carga = VALUES(origem_carga);

INSERT IGNORE INTO dPeriodoApuracao (
    id_periodo,
    descricao_periodo,
    dt_inicio_periodo,
    dt_fim_periodo,
    ativo_sn,
    origem_carga
) VALUES
    ('2026T1', '1o Trimestre 2026', '2026-01-01', '2026-03-31', TRUE, 'SEED'),
    ('2026T2', '2o Trimestre 2026', '2026-04-01', '2026-06-30', TRUE, 'SEED');

INSERT IGNORE INTO dFamiliaProduto (
    id_familia,
    nome_familia,
    ordem_exibicao,
    ativo_sn,
    origem_carga
) VALUES
    (1, 'Credito', 1, TRUE, 'SEED'),
    (2, 'Relacionamento', 2, TRUE, 'SEED'),
    (3, 'Seguros', 3, TRUE, 'SEED');

INSERT IGNORE INTO dTipoIndicador (
    id_tipo_indicador,
    nome_tipo_indicador,
    formato_exibicao,
    casas_decimais,
    prefixo,
    sufixo,
    ativo_sn,
    origem_carga
) VALUES
    ('VALOR', 'Valor', 'currency', 2, 'R$', NULL, TRUE, 'SEED'),
    ('PERCENTUAL', 'Percentual', 'percent', 2, NULL, '%', TRUE, 'SEED'),
    ('QUANTIDADE', 'Quantidade', 'number', 0, NULL, NULL, TRUE, 'SEED');

INSERT IGNORE INTO dIndicador (
    id_indicador,
    id_familia,
    id_tipo_indicador,
    nome_indicador,
    descricao,
    possui_subindicador_sn,
    ordem_exibicao,
    ativo_sn,
    origem_carga
) VALUES
    (101, 1, 'VALOR', 'Credito Total', 'Volume de credito produzido no periodo.', FALSE, 1, TRUE, 'SEED'),
    (102, 1, 'VALOR', 'Capital de Giro', 'Volume de capital de giro desembolsado no periodo.', FALSE, 2, TRUE, 'SEED'),
    (201, 2, 'PERCENTUAL', 'Giro de Carteira', 'Percentual de clientes com contato realizado sobre a base da carteira.', TRUE, 3, TRUE, 'SEED'),
    (301, 3, 'QUANTIDADE', 'Seguro Vida', 'Quantidade de seguros de vida vendidos no periodo.', FALSE, 4, TRUE, 'SEED');

INSERT IGNORE INTO dSubindicador (
    id_subindicador,
    id_indicador,
    nome_subindicador,
    descricao,
    ordem_exibicao,
    ativo_sn,
    origem_carga
) VALUES
    (2101, 201, 'Base da carteira', 'Quantidade de clientes elegiveis na carteira do gerente.', 1, TRUE, 'SEED'),
    (2102, 201, 'Clientes com contato', 'Quantidade de clientes com contato registrado no periodo.', 2, TRUE, 'SEED');

INSERT IGNORE INTO dMesu (
    juncao_ag,
    juncao_gr,
    juncao_dr,
    nome_agencia,
    nome_regional,
    nome_diretoria,
    ativo_sn,
    origem_carga
) VALUES
    ('1101', '2101', '3101', 'Agencia Centro', 'Regional Capital', 'Diretoria Norte', TRUE, 'SEED'),
    ('1102', '2101', '3101', 'Agencia Jardim', 'Regional Capital', 'Diretoria Norte', TRUE, 'SEED'),
    ('1201', '2201', '3201', 'Agencia Litoral', 'Regional Costa', 'Diretoria Sul', TRUE, 'SEED');

INSERT IGNORE INTO dHierarquia (
    funcional,
    nome,
    email,
    cargo,
    nivel,
    ativo_sn,
    origem_carga
) VALUES
    ('9000001', 'Elaine Departamento', 'elaine.departamento@teste.local', 'Diretoria Departamental', 'DP', TRUE, 'SEED'),
    ('8000001', 'Renato Diretor Norte', 'renato.diretor@teste.local', 'Diretor Regional', 'DR', TRUE, 'SEED'),
    ('8000002', 'Sonia Diretora Sul', 'sonia.diretora@teste.local', 'Diretor Regional', 'DR', TRUE, 'SEED'),
    ('7000001', 'Marta Regional Capital', 'marta.regional@teste.local', 'Gerente Regional', 'GR', TRUE, 'SEED'),
    ('7000002', 'Paulo Regional Costa', 'paulo.regional@teste.local', 'Gerente Regional', 'GR', TRUE, 'SEED'),
    ('6000001', 'Lara Agencia Centro', 'lara.agencia@teste.local', 'Gerente de Agencia', 'AG', TRUE, 'SEED'),
    ('6000002', 'Davi Agencia Jardim', 'davi.agencia@teste.local', 'Gerente de Agencia', 'AG', TRUE, 'SEED'),
    ('6000003', 'Nina Agencia Litoral', 'nina.agencia@teste.local', 'Gerente de Agencia', 'AG', TRUE, 'SEED'),
    ('4000001', 'Bruno GC Centro', 'bruno.gc@teste.local', 'Gerente de Contas', 'GC', TRUE, 'SEED'),
    ('4000002', 'Carla GC Jardim', 'carla.gc@teste.local', 'Gerente de Contas', 'GC', TRUE, 'SEED'),
    ('4000003', 'Diego GC Litoral', 'diego.gc@teste.local', 'Gerente de Contas', 'GC', TRUE, 'SEED');

INSERT IGNORE INTO fMultiJuncao (
    funcional,
    juncao,
    tipo_juncao,
    principal_sn,
    ativo_sn,
    origem_carga
) VALUES
    ('9000001', '4000', 'DP', TRUE, TRUE, 'SEED'),
    ('8000001', '3101', 'DR', TRUE, TRUE, 'SEED'),
    ('8000002', '3201', 'DR', TRUE, TRUE, 'SEED'),
    ('7000001', '2101', 'GR', TRUE, TRUE, 'SEED'),
    ('7000002', '2201', 'GR', TRUE, TRUE, 'SEED'),
    ('6000001', '1101', 'AG', TRUE, TRUE, 'SEED'),
    ('6000002', '1102', 'AG', TRUE, TRUE, 'SEED'),
    ('6000003', '1201', 'AG', TRUE, TRUE, 'SEED'),
    ('4000001', '1101', 'AG', TRUE, TRUE, 'SEED'),
    ('4000002', '1102', 'AG', TRUE, TRUE, 'SEED'),
    ('4000003', '1201', 'AG', TRUE, TRUE, 'SEED');

INSERT INTO fGrupoFuncional (
    funcional,
    id_grupo,
    ativo_sn,
    origem_carga
) VALUES
    ('9000001', 'G001', TRUE, 'SEED'),
    ('8000001', 'G001', TRUE, 'SEED'),
    ('8000002', 'G002', TRUE, 'SEED'),
    ('7000001', 'G001', TRUE, 'SEED'),
    ('7000002', 'G002', TRUE, 'SEED'),
    ('6000001', 'G001', TRUE, 'SEED'),
    ('6000002', 'G001', TRUE, 'SEED'),
    ('6000003', 'G002', TRUE, 'SEED'),
    ('4000001', 'G001', TRUE, 'SEED'),
    ('4000002', 'G001', TRUE, 'SEED'),
    ('4000003', 'G002', TRUE, 'SEED')
ON DUPLICATE KEY UPDATE
    id_grupo = VALUES(id_grupo),
    ativo_sn = VALUES(ativo_sn),
    origem_carga = VALUES(origem_carga);

INSERT IGNORE INTO fEncarteiramento (
    juncao_ag,
    conta,
    cnpj,
    funcional,
    ativo_sn,
    origem_carga,
    tipo_origem,
    dt_referencia_carga
) VALUES
    ('1101', '0001234', '012345678901234', '4000001', TRUE, 'SEED', 'REDE', '2026-04-05'),
    ('1102', '0002234', '023456789012345', '4000002', TRUE, 'SEED', 'REDE', '2026-04-05'),
    ('1201', '0003234', '034567890123456', '4000003', TRUE, 'SEED', 'REDE', '2026-04-05');

INSERT IGNORE INTO fIndicadorPeriodo (
    id_periodo,
    id_indicador,
    peso,
    ordem_exibicao,
    ativo_sn,
    origem_carga
) VALUES
    ('2026T1', 101, 32.0000, 1, TRUE, 'SEED'),
    ('2026T1', 102, 18.0000, 2, TRUE, 'SEED'),
    ('2026T1', 201, 28.0000, 3, TRUE, 'SEED'),
    ('2026T1', 301, 22.0000, 4, TRUE, 'SEED'),
    ('2026T2', 101, 32.0000, 1, TRUE, 'SEED'),
    ('2026T2', 102, 18.0000, 2, TRUE, 'SEED'),
    ('2026T2', 201, 28.0000, 3, TRUE, 'SEED'),
    ('2026T2', 301, 22.0000, 4, TRUE, 'SEED');

INSERT IGNORE INTO fIndicadorSegmentoPeriodo (
    id_periodo,
    segmento,
    id_indicador,
    ativo_sn,
    origem_carga
) VALUES
    ('2026T1', 'NEGOCIOS', 101, TRUE, 'SEED'),
    ('2026T1', 'NEGOCIOS', 102, TRUE, 'SEED'),
    ('2026T1', 'NEGOCIOS', 201, TRUE, 'SEED'),
    ('2026T1', 'NEGOCIOS', 301, TRUE, 'SEED'),
    ('2026T1', 'DIGITAL', 101, TRUE, 'SEED'),
    ('2026T1', 'DIGITAL', 102, TRUE, 'SEED'),
    ('2026T1', 'DIGITAL', 201, TRUE, 'SEED'),
    ('2026T1', 'DIGITAL', 301, TRUE, 'SEED'),
    ('2026T2', 'NEGOCIOS', 101, TRUE, 'SEED'),
    ('2026T2', 'NEGOCIOS', 102, TRUE, 'SEED'),
    ('2026T2', 'NEGOCIOS', 201, TRUE, 'SEED'),
    ('2026T2', 'NEGOCIOS', 301, TRUE, 'SEED'),
    ('2026T2', 'DIGITAL', 101, TRUE, 'SEED'),
    ('2026T2', 'DIGITAL', 102, TRUE, 'SEED'),
    ('2026T2', 'DIGITAL', 201, TRUE, 'SEED'),
    ('2026T2', 'DIGITAL', 301, TRUE, 'SEED');

INSERT IGNORE INTO fMeta (
    id_periodo,
    funcional,
    juncao_ag,
    juncao_gr,
    juncao_dr,
    segmento,
    id_indicador,
    valor_meta,
    dt_meta,
    ativo_sn,
    origem_carga
) VALUES
    ('2026T2', '4000001', '1101', '2101', '3101', 'NEGOCIOS', 101, 120000.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000001', '1101', '2101', '3101', 'NEGOCIOS', 102, 60000.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000001', '1101', '2101', '3101', 'NEGOCIOS', 201, 90.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000001', '1101', '2101', '3101', 'NEGOCIOS', 301, 15.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 101, 150000.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 102, 55000.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 201, 90.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 301, 10.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 101, 90000.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 102, 50000.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 201, 85.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 301, 12.0000, '2026-04-05', TRUE, 'SEED');

INSERT IGNORE INTO fMeta (
    id_periodo,
    funcional,
    juncao_ag,
    juncao_gr,
    juncao_dr,
    segmento,
    id_indicador,
    valor_meta,
    dt_meta,
    ativo_sn,
    origem_carga
)
SELECT
    '2026T1',
    funcional,
    juncao_ag,
    juncao_gr,
    juncao_dr,
    segmento,
    id_indicador,
    ROUND(
        CASE
            WHEN id_indicador = 201 THEN GREATEST(75, valor_meta - CASE funcional WHEN '4000001' THEN 2 WHEN '4000002' THEN 4 ELSE 1 END)
            WHEN funcional = '4000001' THEN valor_meta * 0.92
            WHEN funcional = '4000002' THEN valor_meta * 0.90
            ELSE valor_meta * 0.95
        END,
        4
    ),
    '2026-03-05',
    ativo_sn,
    origem_carga
FROM fMeta
WHERE id_periodo = '2026T2'
  AND dt_meta = '2026-04-05';

INSERT IGNORE INTO fRealizado (
    id_periodo,
    funcional,
    juncao_ag,
    juncao_gr,
    juncao_dr,
    segmento,
    id_indicador,
    id_subindicador,
    valor_realizado,
    dt_realizado,
    ativo_sn,
    origem_carga
) VALUES
    ('2026T2', '4000001', '1101', '2101', '3101', 'NEGOCIOS', 101, 0, 135000.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000001', '1101', '2101', '3101', 'NEGOCIOS', 102, 0, 65000.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000001', '1101', '2101', '3101', 'NEGOCIOS', 201, 2101, 100.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000001', '1101', '2101', '3101', 'NEGOCIOS', 201, 2102, 92.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000001', '1101', '2101', '3101', 'NEGOCIOS', 301, 0, 18.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 101, 0, 100000.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 102, 0, 50000.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 201, 2101, 120.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 201, 2102, 80.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 301, 0, 8.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 101, 0, 95000.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 102, 0, 45000.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 201, 2101, 90.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 201, 2102, 88.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 301, 0, 10.0000, '2026-04-05', TRUE, 'SEED');

INSERT IGNORE INTO fRealizado (
    id_periodo,
    funcional,
    juncao_ag,
    juncao_gr,
    juncao_dr,
    segmento,
    id_indicador,
    id_subindicador,
    valor_realizado,
    dt_realizado,
    ativo_sn,
    origem_carga
)
SELECT
    '2026T1',
    funcional,
    juncao_ag,
    juncao_gr,
    juncao_dr,
    segmento,
    id_indicador,
    id_subindicador,
    ROUND(
        CASE
            WHEN id_indicador = 201 AND id_subindicador = 2101 THEN valor_realizado * 0.95
            WHEN id_indicador = 201 AND id_subindicador = 2102 THEN valor_realizado * 0.89
            WHEN funcional = '4000001' THEN valor_realizado * 0.90
            WHEN funcional = '4000002' THEN valor_realizado * 0.87
            ELSE valor_realizado * 0.93
        END,
        4
    ),
    '2026-03-05',
    ativo_sn,
    origem_carga
FROM fRealizado
WHERE id_periodo = '2026T2'
  AND dt_realizado = '2026-04-05';

INSERT IGNORE INTO fPontos (
    id_periodo,
    funcional,
    juncao_ag,
    juncao_gr,
    juncao_dr,
    segmento,
    id_indicador,
    pontos_meta,
    pontos_realizado,
    data_realizado,
    ativo_sn,
    origem_carga
) VALUES
    ('2026T2', '4000001', '1101', '2101', '3101', 'NEGOCIOS', 101, 32.0000, 36.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000001', '1101', '2101', '3101', 'NEGOCIOS', 102, 18.0000, 19.5000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000001', '1101', '2101', '3101', 'NEGOCIOS', 201, 28.0000, 28.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000001', '1101', '2101', '3101', 'NEGOCIOS', 301, 22.0000, 26.4000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 101, 32.0000, 21.3333, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 102, 18.0000, 16.3636, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 201, 28.0000, 24.8889, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 301, 22.0000, 17.6000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 101, 32.0000, 33.7778, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 102, 18.0000, 16.2000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 201, 28.0000, 28.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 301, 22.0000, 18.3333, '2026-04-05', TRUE, 'SEED');

INSERT IGNORE INTO fPontos (
    id_periodo,
    funcional,
    juncao_ag,
    juncao_gr,
    juncao_dr,
    segmento,
    id_indicador,
    pontos_meta,
    pontos_realizado,
    data_realizado,
    ativo_sn,
    origem_carga
)
SELECT
    '2026T1',
    funcional,
    juncao_ag,
    juncao_gr,
    juncao_dr,
    segmento,
    id_indicador,
    pontos_meta,
    ROUND(
        CASE
            WHEN funcional = '4000001' THEN pontos_realizado * 0.91
            WHEN funcional = '4000002' THEN pontos_realizado * 0.88
            ELSE pontos_realizado * 0.94
        END,
        4
    ),
    DATE_SUB(data_realizado, INTERVAL 1 MONTH),
    ativo_sn,
    origem_carga
FROM fPontos
WHERE id_periodo = '2026T2'
  AND data_realizado = '2026-04-05';

INSERT IGNORE INTO fVariavel (
    id_periodo,
    funcional,
    juncao_ag,
    juncao_gr,
    juncao_dr,
    segmento,
    id_indicador,
    variavel_meta,
    variavel_realizada,
    data_realizado,
    ativo_sn,
    origem_carga
) VALUES
    ('2026T2', '4000001', '1101', '2101', '3101', 'NEGOCIOS', 101, 8000.0000, 8600.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000001', '1101', '2101', '3101', 'NEGOCIOS', 102, 2500.0000, 2700.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000001', '1101', '2101', '3101', 'NEGOCIOS', 201, 3000.0000, 3200.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000001', '1101', '2101', '3101', 'NEGOCIOS', 301, 1500.0000, 1800.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 101, 8000.0000, 5200.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 102, 2500.0000, 2300.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 201, 3000.0000, 2700.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 301, 1500.0000, 900.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 101, 8000.0000, 7700.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 102, 2500.0000, 2400.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 201, 3000.0000, 2900.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 301, 1500.0000, 1300.0000, '2026-04-05', TRUE, 'SEED');

INSERT IGNORE INTO fVariavel (
    id_periodo,
    funcional,
    juncao_ag,
    juncao_gr,
    juncao_dr,
    segmento,
    id_indicador,
    variavel_meta,
    variavel_realizada,
    data_realizado,
    ativo_sn,
    origem_carga
)
SELECT
    '2026T1',
    funcional,
    juncao_ag,
    juncao_gr,
    juncao_dr,
    segmento,
    id_indicador,
    ROUND(variavel_meta * 0.95, 4),
    ROUND(
        CASE
            WHEN funcional = '4000001' THEN variavel_realizada * 0.90
            WHEN funcional = '4000002' THEN variavel_realizada * 0.86
            ELSE variavel_realizada * 0.92
        END,
        4
    ),
    DATE_SUB(data_realizado, INTERVAL 1 MONTH),
    ativo_sn,
    origem_carga
FROM fVariavel
WHERE id_periodo = '2026T2'
  AND data_realizado = '2026-04-05';

INSERT IGNORE INTO fDetalhe (
    id_periodo,
    id_indicador,
    id_subindicador,
    funcional,
    juncao_ag,
    juncao_gr,
    juncao_dr,
    segmento,
    id_transacao,
    numero_contrato,
    conta,
    canal_venda,
    data_transacao,
    status_transacao,
    observacao,
    cnpj,
    nome_cliente,
    valor_realizado,
    ativo_sn,
    origem_carga
) VALUES
    ('2026T2', 101, 0, '4000001', '1101', '2101', '3101', 'NEGOCIOS', 'TX1001', 'CTR-0000001', '0001234', 'AGENCIA', '2026-04-03', 'APROVADO', 'Credito aprovado na primeira analise.', '012345678901234', 'Cliente Centro', 45000.0000, TRUE, 'SEED'),
    ('2026T2', 102, 0, '4000001', '1101', '2101', '3101', 'NEGOCIOS', 'TX1002', 'CTR-0000002', '0001234', 'AGENCIA', '2026-04-04', 'APROVADO', 'Operacao de capital de giro liberada.', '012345678901234', 'Cliente Centro', 65000.0000, TRUE, 'SEED'),
    ('2026T2', 201, 2101, '4000001', '1101', '2101', '3101', 'NEGOCIOS', 'TX1003', 'CTR-0000012', '0001234', 'CRM', '2026-04-04', 'BASE', 'Base da carteira considerada para o giro.', '012345678901234', 'Carteira Centro', 100.0000, TRUE, 'SEED'),
    ('2026T2', 201, 2102, '4000001', '1101', '2101', '3101', 'NEGOCIOS', 'TX1004', 'CTR-0000013', '0001234', 'APP', '2026-04-04', 'CONCLUIDO', 'Contato de carteira registrado.', '012345678901234', 'Carteira Centro', 92.0000, TRUE, 'SEED'),
    ('2026T2', 101, 0, '4000002', '1102', '2101', '3101', 'NEGOCIOS', 'TX2001', 'CTR-0000003', '0002234', 'AGENCIA', '2026-04-03', 'PENDENTE', 'Cliente solicitou ajuste documental.', '023456789012345', 'Cliente Jardim', 30000.0000, TRUE, 'SEED'),
    ('2026T2', 102, 0, '4000002', '1102', '2101', '3101', 'NEGOCIOS', 'TX2002', 'CTR-0000005', '0002234', 'AGENCIA', '2026-04-04', 'APROVADO', 'Capital de giro contratado com garantia de recebiveis.', '023456789012345', 'Cliente Jardim', 50000.0000, TRUE, 'SEED'),
    ('2026T2', 201, 2101, '4000002', '1102', '2101', '3101', 'NEGOCIOS', 'TX2003', 'CTR-0000014', '0002234', 'CRM', '2026-04-04', 'BASE', 'Base da carteira considerada para o giro.', '023456789012345', 'Carteira Jardim', 120.0000, TRUE, 'SEED'),
    ('2026T2', 201, 2102, '4000002', '1102', '2101', '3101', 'NEGOCIOS', 'TX2004', 'CTR-0000015', '0002234', 'APP', '2026-04-04', 'CONCLUIDO', 'Contato de carteira registrado.', '023456789012345', 'Carteira Jardim', 80.0000, TRUE, 'SEED'),
    ('2026T2', 301, 0, '4000003', '1201', '2201', '3201', 'DIGITAL', 'TX3001', 'CTR-0000008', '0003234', 'DIGITAL', '2026-04-05', 'CONCLUIDO', 'Venda concluida por canal digital.', '034567890123456', 'Cliente Litoral', 1.0000, TRUE, 'SEED'),
    ('2026T2', 102, 0, '4000003', '1201', '2201', '3201', 'DIGITAL', 'TX3002', 'CTR-0000009', '0003234', 'DIGITAL', '2026-04-05', 'APROVADO', 'Capital de giro contratado em jornada digital.', '034567890123456', 'Cliente Litoral', 45000.0000, TRUE, 'SEED'),
    ('2026T2', 201, 2101, '4000003', '1201', '2201', '3201', 'DIGITAL', 'TX3003', 'CTR-0000016', '0003234', 'CRM', '2026-04-05', 'BASE', 'Base da carteira considerada para o giro.', '034567890123456', 'Carteira Litoral', 90.0000, TRUE, 'SEED'),
    ('2026T2', 201, 2102, '4000003', '1201', '2201', '3201', 'DIGITAL', 'TX3004', 'CTR-0000017', '0003234', 'DIGITAL', '2026-04-05', 'CONCLUIDO', 'Contato de carteira registrado.', '034567890123456', 'Carteira Litoral', 88.0000, TRUE, 'SEED');

INSERT INTO fDetalhe (
    id_periodo,
    id_indicador,
    id_subindicador,
    funcional,
    juncao_ag,
    juncao_gr,
    juncao_dr,
    segmento,
    id_transacao,
    numero_contrato,
    conta,
    canal_venda,
    data_transacao,
    status_transacao,
    observacao,
    cnpj,
    nome_cliente,
    valor_realizado,
    ativo_sn,
    origem_carga
)
SELECT
    '2026T1',
    id_indicador,
    id_subindicador,
    funcional,
    juncao_ag,
    juncao_gr,
    juncao_dr,
    segmento,
    CONCAT('TM', SUBSTRING(id_transacao, 3)),
    CONCAT('MAR-', numero_contrato),
    conta,
    canal_venda,
    DATE_SUB(data_transacao, INTERVAL 1 MONTH),
    status_transacao,
    CONCAT('Fechamento de março. ', observacao),
    cnpj,
    nome_cliente,
    ROUND(
        CASE
            WHEN funcional = '4000001' THEN valor_realizado * 0.88
            WHEN funcional = '4000002' THEN valor_realizado * 0.85
            ELSE valor_realizado * 0.92
        END,
        4
    ),
    ativo_sn,
    origem_carga
FROM fDetalhe base
WHERE base.id_periodo = '2026T2'
  AND NOT EXISTS (
      SELECT 1
      FROM fDetalhe existing
      WHERE existing.id_periodo = '2026T1'
        AND existing.id_transacao = CONCAT('TM', SUBSTRING(base.id_transacao, 3))
  );