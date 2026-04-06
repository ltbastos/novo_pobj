USE novo_pobj;

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
    (201, 2, 'PERCENTUAL', 'Giro de Carteira', 'Percentual de clientes com contato realizado.', FALSE, 2, TRUE, 'SEED'),
    (301, 3, 'QUANTIDADE', 'Seguro Vida', 'Quantidade de seguros de vida vendidos no periodo.', FALSE, 3, TRUE, 'SEED');

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
    ('2026T2', 101, 40.0000, 1, TRUE, 'SEED'),
    ('2026T2', 201, 35.0000, 2, TRUE, 'SEED'),
    ('2026T2', 301, 25.0000, 3, TRUE, 'SEED');

INSERT IGNORE INTO fIndicadorSegmentoPeriodo (
    id_periodo,
    segmento,
    id_indicador,
    ativo_sn,
    origem_carga
) VALUES
    ('2026T2', 'NEGOCIOS', 101, TRUE, 'SEED'),
    ('2026T2', 'NEGOCIOS', 201, TRUE, 'SEED'),
    ('2026T2', 'NEGOCIOS', 301, TRUE, 'SEED'),
    ('2026T2', 'DIGITAL', 101, TRUE, 'SEED'),
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
    ativo_sn,
    origem_carga
) VALUES
    ('2026T2', '4000001', '1101', '2101', '3101', 'NEGOCIOS', 101, 120000.0000, TRUE, 'SEED'),
    ('2026T2', '4000001', '1101', '2101', '3101', 'NEGOCIOS', 201, 90.0000, TRUE, 'SEED'),
    ('2026T2', '4000001', '1101', '2101', '3101', 'NEGOCIOS', 301, 15.0000, TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 101, 150000.0000, TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 201, 90.0000, TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 301, 10.0000, TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 101, 90000.0000, TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 201, 85.0000, TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 301, 12.0000, TRUE, 'SEED');

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
    ativo_sn,
    origem_carga
) VALUES
    ('2026T2', '4000001', '1101', '2101', '3101', 'NEGOCIOS', 101, 0, 135000.0000, TRUE, 'SEED'),
    ('2026T2', '4000001', '1101', '2101', '3101', 'NEGOCIOS', 201, 0, 92.0000, TRUE, 'SEED'),
    ('2026T2', '4000001', '1101', '2101', '3101', 'NEGOCIOS', 301, 0, 18.0000, TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 101, 0, 100000.0000, TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 201, 0, 80.0000, TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 301, 0, 8.0000, TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 101, 0, 95000.0000, TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 201, 0, 88.0000, TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 301, 0, 10.0000, TRUE, 'SEED');

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
    ('2026T2', '4000001', '1101', '2101', '3101', 'NEGOCIOS', 101, 40.0000, 45.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000001', '1101', '2101', '3101', 'NEGOCIOS', 201, 35.0000, 36.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000001', '1101', '2101', '3101', 'NEGOCIOS', 301, 25.0000, 30.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 101, 40.0000, 24.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 201, 35.0000, 31.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 301, 25.0000, 18.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 101, 40.0000, 41.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 201, 35.0000, 34.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 301, 25.0000, 22.0000, '2026-04-05', TRUE, 'SEED');

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
    ('2026T2', '4000001', '1101', '2101', '3101', 'NEGOCIOS', 201, 3000.0000, 3200.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000001', '1101', '2101', '3101', 'NEGOCIOS', 301, 1500.0000, 1800.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 101, 8000.0000, 5200.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 201, 3000.0000, 2700.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000002', '1102', '2101', '3101', 'NEGOCIOS', 301, 1500.0000, 900.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 101, 8000.0000, 7700.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 201, 3000.0000, 2900.0000, '2026-04-05', TRUE, 'SEED'),
    ('2026T2', '4000003', '1201', '2201', '3201', 'DIGITAL', 301, 1500.0000, 1300.0000, '2026-04-05', TRUE, 'SEED');

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
    ('2026T2', 201, 0, '4000001', '1101', '2101', '3101', 'NEGOCIOS', 'TX1002', 'CTR-0000002', '0001234', 'APP', '2026-04-04', 'CONCLUIDO', 'Contato de carteira registrado.', '012345678901234', 'Cliente Centro', 1.0000, TRUE, 'SEED'),
    ('2026T2', 101, 0, '4000002', '1102', '2101', '3101', 'NEGOCIOS', 'TX2001', 'CTR-0000003', '0002234', 'AGENCIA', '2026-04-03', 'PENDENTE', 'Cliente solicitou ajuste documental.', '023456789012345', 'Cliente Jardim', 30000.0000, TRUE, 'SEED'),
    ('2026T2', 301, 0, '4000003', '1201', '2201', '3201', 'DIGITAL', 'TX3001', 'CTR-0000004', '0003234', 'DIGITAL', '2026-04-05', 'CONCLUIDO', 'Venda concluida por canal digital.', '034567890123456', 'Cliente Litoral', 1.0000, TRUE, 'SEED');