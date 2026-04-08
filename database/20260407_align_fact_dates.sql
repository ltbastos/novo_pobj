USE novo_pobj;

SET @has_dt_meta := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'fMeta'
      AND COLUMN_NAME = 'dt_meta'
);
SET @sql := IF(@has_dt_meta = 0, 'ALTER TABLE fMeta ADD COLUMN dt_meta DATE NULL AFTER valor_meta', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_dt_realizado := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'fRealizado'
      AND COLUMN_NAME = 'dt_realizado'
);
SET @sql := IF(@has_dt_realizado = 0, 'ALTER TABLE fRealizado ADD COLUMN dt_realizado DATE NULL AFTER valor_realizado', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE fMeta
SET dt_meta = CASE
    WHEN id_periodo = '2026T1' THEN '2026-03-05'
    WHEN id_periodo = '2026T2' THEN '2026-04-05'
    ELSE COALESCE(DATE(dt_inclusao), CURRENT_DATE())
END
WHERE dt_meta IS NULL;

UPDATE fRealizado
SET dt_realizado = CASE
    WHEN id_periodo = '2026T1' THEN '2026-03-05'
    WHEN id_periodo = '2026T2' THEN '2026-04-05'
    ELSE COALESCE(DATE(dt_inclusao), CURRENT_DATE())
END
WHERE dt_realizado IS NULL;

ALTER TABLE fMeta MODIFY dt_meta DATE NOT NULL;
ALTER TABLE fRealizado MODIFY dt_realizado DATE NOT NULL;

SET @has_old_meta_uk := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'fMeta'
      AND INDEX_NAME = 'uk_fmeta_periodo_funcional_indicador'
);
SET @sql := IF(@has_old_meta_uk > 0, 'ALTER TABLE fMeta DROP INDEX uk_fmeta_periodo_funcional_indicador', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_new_meta_uk := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'fMeta'
      AND INDEX_NAME = 'uk_fmeta_periodo_funcional_indicador_data'
);
SET @sql := IF(@has_new_meta_uk = 0, 'ALTER TABLE fMeta ADD UNIQUE KEY uk_fmeta_periodo_funcional_indicador_data (id_periodo, funcional, id_indicador, dt_meta)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_meta_date_idx := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'fMeta'
      AND INDEX_NAME = 'idx_fmeta_dt_meta'
);
SET @sql := IF(@has_meta_date_idx = 0, 'ALTER TABLE fMeta ADD KEY idx_fmeta_dt_meta (dt_meta)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_old_realizado_uk := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'fRealizado'
      AND INDEX_NAME = 'uk_frealizado_periodo_funcional_indicador_sub'
);
SET @sql := IF(@has_old_realizado_uk > 0, 'ALTER TABLE fRealizado DROP INDEX uk_frealizado_periodo_funcional_indicador_sub', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_new_realizado_uk := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'fRealizado'
      AND INDEX_NAME = 'uk_frealizado_periodo_funcional_indicador_sub_data'
);
SET @sql := IF(@has_new_realizado_uk = 0, 'ALTER TABLE fRealizado ADD UNIQUE KEY uk_frealizado_periodo_funcional_indicador_sub_data (id_periodo, funcional, id_indicador, id_subindicador, dt_realizado)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_realizado_date_idx := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'fRealizado'
      AND INDEX_NAME = 'idx_frealizado_dt_realizado'
);
SET @sql := IF(@has_realizado_date_idx = 0, 'ALTER TABLE fRealizado ADD KEY idx_frealizado_dt_realizado (dt_realizado)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

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
    ('2026T1', 301, 22.0000, 4, TRUE, 'SEED');

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
    ('2026T1', 'DIGITAL', 301, TRUE, 'SEED');

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