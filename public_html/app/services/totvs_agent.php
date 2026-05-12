<?php
declare(strict_types=1);

final class TotvsAgentService
{
    private const CACHE_TTL_SECONDS = 120;
    private const FILIAIS = [
        '010101' => 'TUFFLOG',
        '020101' => 'FESTA SHOW',
        '030101' => 'FESTA BRASIL',
        '040101' => 'DIST BASSINELLI MATRIZ',
        '040102' => 'DIST BASSINELLI FILIAL',
        '050101' => 'DIST ALEGRIA',
        '060101' => 'PARTY POPPER',
    ];

    public static function execute(string $action, array $params = []): array
    {
        switch ($action) {
            case 'catalogo':
                return self::catalogo();

            case 'top_clientes_inadimplentes':
                return self::topClientesInadimplentes($params);

            case 'cliente_com_mais_titulos_em_atraso':
                return self::clienteComMaisTitulosEmAtraso($params);

            case 'vendedor_com_maior_inadimplencia':
                return self::vendedorComMaiorInadimplencia($params);

            case 'clientes_inadimplentes_por_vendedor':
                return self::clientesInadimplentesPorVendedor($params);

            case 'total_inadimplente_por_supervisor':
                return self::totalInadimplentePorSupervisor($params);

            case 'buscar_cliente_documento':
                return self::buscarClienteDocumento($params);

            case 'buscar_cliente_nome':
                return self::buscarClienteNome($params);

            case 'vendedor_do_cliente':
                return self::vendedorDoCliente($params);

            case 'ultima_compra_cliente':
                return self::ultimaCompraCliente($params);

            case 'top_clientes_faturamento':
                return self::topClientesFaturamento($params);

            case 'faturamento_por_vendedor':
                return self::faturamentoPorVendedor($params);

            case 'faturamento_por_supervisor':
                return self::faturamentoPorSupervisor($params);

            case 'historico_compras_cliente':
                return self::historicoComprasCliente($params);

            case 'resumo_cliente':
                return self::resumoCliente($params);

            case 'comparativo_faturado_inadimplente':
                return self::comparativoFaturadoInadimplente($params);

            case 'executivo_resumo':
                return self::executivoResumo($params);

            case 'executivo_tops':
                return self::executivoTops($params);

            case 'faturamento_total_empresa':
                return self::faturamentoTotalEmpresa($params);

            case 'faturamento_hoje':
                return self::faturamentoHoje($params);

            case 'faturamento_mes_atual':
                return self::faturamentoMesAtual($params);

            case 'faturamento_ano_atual':
                return self::faturamentoAnoAtual($params);

            case 'realizado_hoje':
                return self::realizadoHoje($params);

            case 'realizado_mes_atual':
                return self::realizadoMesAtual($params);

            case 'realizado_ano_atual':
                return self::realizadoAnoAtual($params);

            case 'clientes_dashboard':
                return self::clientesDashboard($params);

            case 'insight_comercial':
                return self::insightComercial($params);

            case 'contas_pagar_resumo':
                return self::contasPagarResumo($params);

            case 'contas_pagar_proximos':
                return self::contasPagarProximos($params);

            case 'contas_pagar_rankings':
                return self::contasPagarRankings($params);

            case 'comex_importacoes_resumo':
                return self::comexImportacoesResumo($params);

            case 'comex_importacoes_lista':
                return self::comexImportacoesLista($params);

            case 'documento_entrada_resumo':
                return self::documentoEntradaResumo($params);

            case 'documento_entrada_proximos':
                return self::documentoEntradaProximos($params);

            case 'documento_entrada_rankings':
                return self::documentoEntradaRankings($params);
        }

        throw new InvalidArgumentException('Ação inválida para agente TOTVS.');
    }

    public static function catalogo(): array
    {
        return [
            'acoes' => [
                [
                    'action' => 'top_clientes_inadimplentes',
                    'descricao' => 'Retorna os clientes com maior valor inadimplente.',
                    'params' => ['limit', 'vendedor', 'supervisor', 'search', 'dias_min_atraso', 'valor_min', 'force'],
                ],
                [
                    'action' => 'cliente_com_mais_titulos_em_atraso',
                    'descricao' => 'Retorna o cliente com maior quantidade de titulos em atraso.',
                    'params' => ['vendedor', 'supervisor', 'search', 'dias_min_atraso', 'valor_min', 'force'],
                ],
                [
                    'action' => 'vendedor_com_maior_inadimplencia',
                    'descricao' => 'Retorna o vendedor com maior valor inadimplente somado.',
                    'params' => ['supervisor', 'dias_min_atraso', 'force'],
                ],
                [
                    'action' => 'clientes_inadimplentes_por_vendedor',
                    'descricao' => 'Lista clientes inadimplentes de um vendedor especifico.',
                    'params' => ['vendedor', 'limit', 'search', 'dias_min_atraso', 'valor_min', 'force'],
                ],
                [
                    'action' => 'total_inadimplente_por_supervisor',
                    'descricao' => 'Agrupa total inadimplente por supervisor.',
                    'params' => ['limit', 'dias_min_atraso', 'force'],
                ],
                [
                    'action' => 'buscar_cliente_documento',
                    'descricao' => 'Busca cadastro de cliente por CNPJ ou CPF.',
                    'params' => ['documento', 'limit', 'force'],
                ],
                [
                    'action' => 'buscar_cliente_nome',
                    'descricao' => 'Busca clientes pelo nome ou nome fantasia.',
                    'params' => ['nome', 'limit', 'force'],
                ],
                [
                    'action' => 'vendedor_do_cliente',
                    'descricao' => 'Retorna o vendedor atual vinculado ao cliente.',
                    'params' => ['cliente', 'loja', 'documento', 'nome', 'force'],
                ],
                [
                    'action' => 'ultima_compra_cliente',
                    'descricao' => 'Retorna a ultima compra do cliente no faturado.',
                    'params' => ['cliente', 'loja', 'documento', 'nome', 'force'],
                ],
                [
                    'action' => 'top_clientes_faturamento',
                    'descricao' => 'Retorna os clientes com maior faturamento no periodo.',
                    'params' => ['limit', 'date_from', 'date_to', 'months', 'vendedor', 'supervisor', 'search', 'valor_min', 'force'],
                ],
                [
                    'action' => 'faturamento_por_vendedor',
                    'descricao' => 'Agrupa faturamento por vendedor no periodo.',
                    'params' => ['limit', 'date_from', 'date_to', 'months', 'supervisor', 'search', 'valor_min', 'force'],
                ],
                [
                    'action' => 'faturamento_por_supervisor',
                    'descricao' => 'Agrupa faturamento por supervisor no periodo.',
                    'params' => ['limit', 'date_from', 'date_to', 'months', 'search', 'valor_min', 'force'],
                ],
                [
                    'action' => 'historico_compras_cliente',
                    'descricao' => 'Lista compras do cliente no faturado, com ultimas notas e totais.',
                    'params' => ['cliente', 'loja', 'documento', 'nome', 'limit', 'date_from', 'date_to', 'months', 'com_itens', 'force'],
                ],
                [
                    'action' => 'resumo_cliente',
                    'descricao' => 'Consolida cadastro, vendedor, inadimplencia e faturamento do cliente.',
                    'params' => ['cliente', 'loja', 'documento', 'nome', 'date_from', 'date_to', 'months', 'force'],
                ],
                [
                    'action' => 'comparativo_faturado_inadimplente',
                    'descricao' => 'Compara faturado x inadimplente por cliente no periodo.',
                    'params' => ['limit', 'date_from', 'date_to', 'months', 'vendedor', 'supervisor', 'search', 'valor_min', 'force'],
                ],
                [
                    'action' => 'executivo_resumo',
                    'descricao' => 'Retorna os KPIs do dashboard executivo/faturamento.',
                    'params' => ['dash', 'breakdown_filial', 'force'],
                ],
                [
                    'action' => 'executivo_tops',
                    'descricao' => 'Retorna diario do mes, top produtos, top vendedores e contagens de NF do executivo.',
                    'params' => ['ym', 'breakdown_filial', 'force'],
                ],
                [
                    'action' => 'faturamento_total_empresa',
                    'descricao' => 'Retorna total da empresa para uma data especifica, mes ou ano.',
                    'params' => ['periodo', 'date', 'force'],
                ],
                [
                    'action' => 'faturamento_hoje',
                    'descricao' => 'Retorna o faturado da empresa em uma data especifica. Sem date, usa o dia atual.',
                    'params' => ['date', 'breakdown_filial', 'force'],
                ],
                [
                    'action' => 'faturamento_mes_atual',
                    'descricao' => 'Retorna o faturado da empresa no mes atual.',
                    'params' => ['force'],
                ],
                [
                    'action' => 'faturamento_ano_atual',
                    'descricao' => 'Retorna o faturado da empresa no ano atual.',
                    'params' => ['force'],
                ],
                [
                    'action' => 'realizado_hoje',
                    'descricao' => 'Retorna o realizado hoje (faturado + imediato).',
                    'params' => ['force'],
                ],
                [
                    'action' => 'realizado_mes_atual',
                    'descricao' => 'Retorna o realizado do mes atual (faturado + imediato).',
                    'params' => ['force'],
                ],
                [
                    'action' => 'realizado_ano_atual',
                    'descricao' => 'Retorna o realizado do ano atual (faturado + imediato).',
                    'params' => ['force'],
                ],
                [
                    'action' => 'clientes_dashboard',
                    'descricao' => 'Retorna os numeros e rankings do dashboard de clientes.',
                    'params' => ['ym', 'force'],
                ],
                [
                    'action' => 'insight_comercial',
                    'descricao' => 'Retorna rankings e analise comercial de preco/desconto do insight.',
                    'params' => ['ym', 'dt_ini', 'dt_fim', 'limit', 'produto_filtro', 'breakdown_filial', 'force'],
                ],
                [
                    'action' => 'contas_pagar_resumo',
                    'descricao' => 'Retorna resumo de contas a pagar e vencimentos proximos.',
                    'params' => ['from', 'to', 'force'],
                ],
                [
                    'action' => 'contas_pagar_proximos',
                    'descricao' => 'Retorna listas de proximos vencimentos de contas a pagar.',
                    'params' => ['from', 'to', 'janela', 'force'],
                ],
                [
                    'action' => 'contas_pagar_rankings',
                    'descricao' => 'Retorna rankings por centro de custo e fornecedor.',
                    'params' => ['from', 'to', 'limit', 'force'],
                ],
                [
                    'action' => 'comex_importacoes_resumo',
                    'descricao' => 'Retorna o resumo do dashboard de importacoes.',
                    'params' => ['max', 'force'],
                ],
                [
                    'action' => 'comex_importacoes_lista',
                    'descricao' => 'Retorna a lista detalhada de processos de importacao.',
                    'params' => ['max', 'fase', 'atrasadas', 'force'],
                ],
                [
                    'action' => 'documento_entrada_resumo',
                    'descricao' => 'Retorna resumo de documentos de entrada (NF entrada): total, valor e proximos vencimentos.',
                    'params' => ['from', 'to', 'force'],
                ],
                [
                    'action' => 'documento_entrada_proximos',
                    'descricao' => 'Retorna lista de documentos de entrada com vencimento proximo (3, 7 ou 15 dias).',
                    'params' => ['from', 'to', 'janela', 'force'],
                ],
                [
                    'action' => 'documento_entrada_rankings',
                    'descricao' => 'Retorna rankings de gastos por centro de custo, natureza e fornecedor nos documentos de entrada. Use date_from/date_to para base por emissao e from/to para base por vencimento.',
                    'params' => ['date_from', 'date_to', 'from', 'to', 'limit', 'force'],
                ],
            ],
        ];
    }

    public static function topClientesInadimplentes(array $params): array
    {
        $limit = self::clampInt($params['limit'] ?? 10, 1, 100);
        $dataset = self::buildInadimplenciaDataset($params);
        $clientes = $dataset['clientes_filtrados'];
        usort($clientes, static fn(array $a, array $b): int => ($b['inad_total'] <=> $a['inad_total']));

        return [
            'filtros' => $dataset['filtros'],
            'total_clientes_filtrados' => count($clientes),
            'items' => array_slice(array_values($clientes), 0, $limit),
        ];
    }

    public static function clienteComMaisTitulosEmAtraso(array $params): array
    {
        $dataset = self::buildInadimplenciaDataset($params);
        $clientes = $dataset['clientes_filtrados'];

        usort($clientes, static function (array $a, array $b): int {
            $cmp = (($b['inad_qtd_titulos'] ?? 0) <=> ($a['inad_qtd_titulos'] ?? 0));
            if ($cmp !== 0) {
                return $cmp;
            }
            return (($b['inad_total'] ?? 0.0) <=> ($a['inad_total'] ?? 0.0));
        });

        return [
            'filtros' => $dataset['filtros'],
            'item' => $clientes[0] ?? null,
        ];
    }

    public static function vendedorComMaiorInadimplencia(array $params): array
    {
        $dataset = self::buildInadimplenciaDataset($params);
        $ranking = [];

        foreach ($dataset['clientes_filtrados'] as $cliente) {
            $codigo = trim((string) ($cliente['vendedor_codigo'] ?? ''));
            $nome = trim((string) ($cliente['vendedor_nome'] ?? ''));
            $key = $codigo !== '' ? $codigo : ($nome !== '' ? $nome : 'SEM_VENDEDOR');

            if (!isset($ranking[$key])) {
                $ranking[$key] = [
                    'vendedor_codigo' => $codigo,
                    'vendedor_nome' => $nome !== '' ? $nome : ($codigo !== '' ? $codigo : 'Sem vendedor'),
                    'inad_total' => 0.0,
                    'clientes' => 0,
                    'titulos' => 0,
                ];
            }

            $ranking[$key]['inad_total'] += (float) ($cliente['inad_total'] ?? 0.0);
            $ranking[$key]['clientes']++;
            $ranking[$key]['titulos'] += (int) ($cliente['inad_qtd_titulos'] ?? 0);
        }

        $items = array_values($ranking);
        usort($items, static fn(array $a, array $b): int => ($b['inad_total'] <=> $a['inad_total']));

        return [
            'filtros' => $dataset['filtros'],
            'item' => $items[0] ?? null,
            'ranking' => array_slice($items, 0, self::clampInt($params['limit'] ?? 10, 1, 100)),
        ];
    }

    public static function clientesInadimplentesPorVendedor(array $params): array
    {
        $vendedor = trim((string) ($params['vendedor'] ?? ''));
        if ($vendedor === '') {
            throw new InvalidArgumentException('Informe o vendedor para listar os clientes inadimplentes.');
        }

        $limit = self::clampInt($params['limit'] ?? 100, 1, 500);
        $params['vendedor'] = $vendedor;
        $dataset = self::buildInadimplenciaDataset($params);
        $clientes = $dataset['clientes_filtrados'];
        usort($clientes, static fn(array $a, array $b): int => ($b['inad_total'] <=> $a['inad_total']));

        return [
            'filtros' => $dataset['filtros'],
            'total_clientes' => count($clientes),
            'items' => array_slice(array_values($clientes), 0, $limit),
        ];
    }

    public static function totalInadimplentePorSupervisor(array $params): array
    {
        $dataset = self::buildInadimplenciaDataset($params);
        $ranking = [];

        foreach ($dataset['clientes_filtrados'] as $cliente) {
            $codigo = trim((string) ($cliente['supervisor_codigo'] ?? ''));
            $nome = trim((string) ($cliente['supervisor_nome'] ?? ''));
            $key = $codigo !== '' ? $codigo : ($nome !== '' ? $nome : 'SEM_SUPERVISOR');

            if (!isset($ranking[$key])) {
                $ranking[$key] = [
                    'supervisor_codigo' => $codigo,
                    'supervisor_nome' => $nome !== '' ? $nome : ($codigo !== '' ? $codigo : 'Sem supervisor'),
                    'inad_total' => 0.0,
                    'clientes' => 0,
                    'titulos' => 0,
                ];
            }

            $ranking[$key]['inad_total'] += (float) ($cliente['inad_total'] ?? 0.0);
            $ranking[$key]['clientes']++;
            $ranking[$key]['titulos'] += (int) ($cliente['inad_qtd_titulos'] ?? 0);
        }

        $items = array_values($ranking);
        usort($items, static fn(array $a, array $b): int => ($b['inad_total'] <=> $a['inad_total']));

        return [
            'filtros' => $dataset['filtros'],
            'items' => array_slice($items, 0, self::clampInt($params['limit'] ?? 50, 1, 100)),
        ];
    }

    public static function buscarClienteDocumento(array $params): array
    {
        $documento = self::onlyDigits((string) ($params['documento'] ?? ''));
        if ($documento === '') {
            throw new InvalidArgumentException('Informe o CNPJ ou CPF do cliente.');
        }

        $cadastro = self::buildCadastroDataset($params);
        $items = array_values(array_filter($cadastro['clientes'], static function (array $cliente) use ($documento): bool {
            return $documento === (string) ($cliente['documento'] ?? '');
        }));

        return [
            'documento' => $documento,
            'total_encontrados' => count($items),
            'items' => array_slice($items, 0, self::clampInt($params['limit'] ?? 20, 1, 100)),
        ];
    }

    public static function buscarClienteNome(array $params): array
    {
        $nome = trim((string) ($params['nome'] ?? ''));
        if ($nome === '') {
            throw new InvalidArgumentException('Informe o nome do cliente.');
        }

        $needle = self::normalizeText($nome);
        $cadastro = self::buildCadastroDataset($params);
        $items = array_values(array_filter($cadastro['clientes'], static function (array $cliente) use ($needle): bool {
            $haystacks = [
                self::normalizeText((string) ($cliente['nome'] ?? '')),
                self::normalizeText((string) ($cliente['nome_fantasia'] ?? '')),
                self::normalizeText((string) ($cliente['cliente'] ?? '')),
            ];

            foreach ($haystacks as $haystack) {
                if ($haystack !== '' && str_contains($haystack, $needle)) {
                    return true;
                }
            }

            return false;
        }));

        usort($items, static fn(array $a, array $b): int => strcmp((string) ($a['nome'] ?? ''), (string) ($b['nome'] ?? '')));

        return [
            'nome' => $nome,
            'total_encontrados' => count($items),
            'items' => array_slice($items, 0, self::clampInt($params['limit'] ?? 20, 1, 100)),
        ];
    }

    public static function vendedorDoCliente(array $params): array
    {
        $cliente = self::resolveCliente($params);

        return [
            'cliente' => [
                'cliente' => $cliente['cliente'],
                'loja' => $cliente['loja'],
                'nome' => $cliente['nome'],
                'documento' => $cliente['documento'],
                'vendedor_codigo' => $cliente['vendedor_codigo'],
                'vendedor_nome' => $cliente['vendedor_nome'],
                'supervisor_codigo' => $cliente['supervisor_codigo'],
                'supervisor_nome' => $cliente['supervisor_nome'],
            ],
        ];
    }

    public static function ultimaCompraCliente(array $params): array
    {
        $cliente = self::resolveCliente($params);
        $historico = self::buildFaturamentoDataset($params);
        $key = self::clienteKey((string) $cliente['cliente'], (string) $cliente['loja']);
        $compra = $historico['ultimas_compras'][$key] ?? null;

        return [
            'cliente' => [
                'cliente' => $cliente['cliente'],
                'loja' => $cliente['loja'],
                'nome' => $cliente['nome'],
                'documento' => $cliente['documento'],
            ],
            'ultima_compra' => $compra,
        ];
    }

    public static function topClientesFaturamento(array $params): array
    {
        $limit = self::clampInt($params['limit'] ?? 10, 1, 100);
        $dataset = self::buildFaturamentoDataset($params);
        $clientes = $dataset['clientes_filtrados'];
        usort($clientes, static fn(array $a, array $b): int => ($b['faturamento_total'] <=> $a['faturamento_total']));

        return [
            'filtros' => $dataset['filtros'],
            'total_clientes_filtrados' => count($clientes),
            'items' => array_slice($clientes, 0, $limit),
        ];
    }

    public static function faturamentoPorVendedor(array $params): array
    {
        $limit = self::clampInt($params['limit'] ?? 50, 1, 100);
        $dataset = self::buildFaturamentoDataset($params);
        $items = array_values($dataset['ranking_vendedores']);
        usort($items, static fn(array $a, array $b): int => ($b['faturamento_total'] <=> $a['faturamento_total']));

        return [
            'filtros' => $dataset['filtros'],
            'items' => array_slice($items, 0, $limit),
        ];
    }

    public static function faturamentoPorSupervisor(array $params): array
    {
        $limit = self::clampInt($params['limit'] ?? 50, 1, 100);
        $dataset = self::buildFaturamentoDataset($params);
        $items = array_values($dataset['ranking_supervisores']);
        usort($items, static fn(array $a, array $b): int => ($b['faturamento_total'] <=> $a['faturamento_total']));

        return [
            'filtros' => $dataset['filtros'],
            'items' => array_slice($items, 0, $limit),
        ];
    }

    public static function historicoComprasCliente(array $params): array
    {
        $cliente = self::resolveCliente($params);
        $dataset = self::buildFaturamentoDataset($params);
        $historicoDataset = $dataset['historico_por_cliente'] ?? [];
        $clienteCodigo = (string) $cliente['cliente'];
        $clienteLoja = (string) $cliente['loja'];
        $key = self::clienteKey($clienteCodigo, $clienteLoja);
        $historico = $historicoDataset[$key] ?? null;
        if ($historico === null) {
            $historico = $historicoDataset[self::clienteKey($clienteCodigo, '')] ?? null;
        }
        if ($historico === null) {
            $historico = $historicoDataset[$clienteCodigo] ?? null;
        }
        if ($historico === null) {
            foreach ($historicoDataset as $entryKey => $entry) {
                $entryKey = (string) $entryKey;
                if (str_starts_with($entryKey, $clienteCodigo . '|') || $entryKey === $clienteCodigo) {
                    $historico = $entry;
                    break;
                }
            }
        }
        $historico = $historico ?? [
            'faturamento_total' => 0.0,
            'qtd_pedidos' => 0,
            'qtd_nfs' => 0,
            'ticket_medio' => 0.0,
            'primeira_compra' => null,
            'ultima_compra' => null,
            'compras' => [],
        ];
        $limit = self::resolveLimit($params['limit'] ?? 50, 50, 500);
        $comItens = self::boolParam($params['com_itens'] ?? false);

        $compras = self::buildHistoricoNotasComItens($historico['compras'], $limit);
        if (!$comItens) {
            foreach ($compras as &$compra) {
                unset($compra['itens']);
            }
            unset($compra);
        }
        $response = [
            'filtros' => $dataset['filtros'],
            'cliente' => [
                'cliente' => $cliente['cliente'],
                'loja' => $cliente['loja'],
                'nome' => $cliente['nome'],
                'documento' => $cliente['documento'],
            ],
            'resumo' => [
                'faturamento_total' => $historico['faturamento_total'],
                'qtd_pedidos' => $historico['qtd_pedidos'],
                'qtd_nfs' => $historico['qtd_nfs'],
                'ticket_medio' => $historico['ticket_medio'],
                'primeira_compra' => $historico['primeira_compra'],
                'ultima_compra' => $historico['ultima_compra'],
            ],
            'compras' => $compras,
        ];

        return $response;
    }

    public static function resumoCliente(array $params): array
    {
        $cliente = self::resolveCliente($params);
        $key = self::clienteKey((string) $cliente['cliente'], (string) $cliente['loja']);
        $fat = self::buildFaturamentoDataset($params);
        $inad = self::buildInadimplenciaDataset($params);

        $inadMap = [];
        foreach ($inad['clientes_filtrados'] as $item) {
            $inadMap[(string) ($item['cliente_key'] ?? '')] = $item;
        }

        $hist = $fat['historico_por_cliente'][$key] ?? null;
        $inadCliente = $inadMap[$key] ?? null;
        $indice = 0.0;
        if ($hist && (float) ($hist['faturamento_total'] ?? 0.0) > 0 && $inadCliente) {
            $indice = round((((float) ($inadCliente['inad_total'] ?? 0.0)) / (float) $hist['faturamento_total']) * 100, 2);
        }

        return [
            'filtros_faturamento' => $fat['filtros'],
            'cliente' => $cliente,
            'faturamento' => $hist,
            'inadimplencia' => $inadCliente,
            'comparativo' => [
                'faturamento_total' => (float) ($hist['faturamento_total'] ?? 0.0),
                'inad_total' => (float) ($inadCliente['inad_total'] ?? 0.0),
                'indice_inadimplencia_pct' => $indice,
            ],
        ];
    }

    public static function comparativoFaturadoInadimplente(array $params): array
    {
        $limit = self::clampInt($params['limit'] ?? 20, 1, 100);
        $fat = self::buildFaturamentoDataset($params);
        $inad = self::buildInadimplenciaDataset($params);
        $inadMap = [];

        foreach ($inad['clientes_filtrados'] as $item) {
            $inadMap[(string) ($item['cliente_key'] ?? '')] = $item;
        }

        $items = [];
        foreach ($fat['clientes_filtrados'] as $cliente) {
            $key = (string) ($cliente['cliente_key'] ?? '');
            $inadCliente = $inadMap[$key] ?? null;
            $faturamento = (float) ($cliente['faturamento_total'] ?? 0.0);
            $inadTotal = (float) ($inadCliente['inad_total'] ?? 0.0);

            if ($faturamento <= 0 && $inadTotal <= 0) {
                continue;
            }

            $items[] = [
                'cliente' => $cliente['cliente'],
                'loja' => $cliente['loja'],
                'cliente_key' => $cliente['cliente_key'],
                'nome' => $cliente['nome'],
                'documento' => $cliente['documento'],
                'vendedor_codigo' => $cliente['vendedor_codigo'],
                'vendedor_nome' => $cliente['vendedor_nome'],
                'supervisor_codigo' => $cliente['supervisor_codigo'],
                'supervisor_nome' => $cliente['supervisor_nome'],
                'faturamento_total' => $faturamento,
                'inad_total' => $inadTotal,
                'qtd_pedidos' => (int) ($cliente['qtd_pedidos'] ?? 0),
                'qtd_titulos_inad' => (int) ($inadCliente['inad_qtd_titulos'] ?? 0),
                'indice_inadimplencia_pct' => $faturamento > 0 ? round(($inadTotal / $faturamento) * 100, 2) : 0.0,
                'ultima_compra' => $cliente['ultima_compra'],
            ];
        }

        usort($items, static function (array $a, array $b): int {
            $cmp = ($b['indice_inadimplencia_pct'] <=> $a['indice_inadimplencia_pct']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return ($b['inad_total'] <=> $a['inad_total']);
        });

        return [
            'filtros_faturamento' => $fat['filtros'],
            'filtros_inadimplencia' => $inad['filtros'],
            'items' => array_slice($items, 0, $limit),
        ];
    }

    public static function executivoResumo(array $params): array
    {
        $data = self::buildExecutivoDataset($params);
        if (self::boolParam($params['breakdown_filial'] ?? false)) {
            $data['por_filial'] = self::buildFilialBreakdownResumo($params, 'mes');
        }

        return $data;
    }

    public static function executivoTops(array $params): array
    {
        $dataset = self::buildExecutivoTopsDataset($params);
        $response = [
            'ym' => $dataset['ym'],
            'updated_at' => $dataset['updated_at'],
            'values' => $dataset['values'],
            'qtd_nf_hoje' => $dataset['qtd_nf_hoje'],
            'qtd_nf_mes' => $dataset['qtd_nf_mes'],
            'clientes_mes' => $dataset['clientes_mes'],
            'diario_mes' => $dataset['diario_mes'],
            'top_produtos' => $dataset['top_produtos'],
            'top_vendedores' => $dataset['top_vendedores'],
            'debug' => $dataset['debug'],
        ];

        if (self::boolParam($params['breakdown_filial'] ?? false)) {
            $response['por_filial'] = $dataset['por_filial'] ?? [];
        }

        return $response;
    }

    public static function faturamentoTotalEmpresa(array $params): array
    {
        $periodo = strtolower(trim((string) ($params['periodo'] ?? 'mes')));
        if (in_array($periodo, ['dia', 'data'], true)) {
            $diario = self::buildFaturamentoDiarioEmpresa($params);
            return [
                'periodo' => 'dia',
                'date' => $diario['date'],
                'valor' => $diario['valor'],
                'updated_at' => $diario['updated_at'],
            ];
        }

        $executivo = self::buildExecutivoDataset($params);
        $map = [
            'hoje' => 'hoje_faturado',
            'mes' => 'mes_faturado',
            'ano' => 'ano_faturado',
        ];
        $key = $map[$periodo] ?? 'mes_faturado';

        return [
            'periodo' => array_search($key, $map, true) ?: 'mes',
            'valor' => (float) ($executivo['values'][$key] ?? 0.0),
            'values' => [
                'hoje' => (float) ($executivo['values']['hoje_faturado'] ?? 0.0),
                'mes' => (float) ($executivo['values']['mes_faturado'] ?? 0.0),
                'ano' => (float) ($executivo['values']['ano_faturado'] ?? 0.0),
            ],
            'updated_at' => $executivo['updated_at'] ?? null,
        ];
    }

    public static function faturamentoHoje(array $params): array
    {
        $data = self::buildFaturamentoDiarioEmpresa($params);
        if (self::boolParam($params['breakdown_filial'] ?? false)) {
            $data['por_filial'] = self::buildFilialBreakdownHoje($params, (string) ($data['date'] ?? date('Y-m-d')));
        }

        return $data;
    }

    public static function faturamentoMesAtual(array $params): array
    {
        $executivo = self::buildExecutivoDataset($params);
        return ['valor' => (float) ($executivo['values']['mes_faturado'] ?? 0.0), 'updated_at' => $executivo['updated_at'] ?? null];
    }

    public static function faturamentoAnoAtual(array $params): array
    {
        $executivo = self::buildExecutivoDataset($params);
        return ['valor' => (float) ($executivo['values']['ano_faturado'] ?? 0.0), 'updated_at' => $executivo['updated_at'] ?? null];
    }

    public static function realizadoHoje(array $params): array
    {
        $executivo = self::buildExecutivoDataset($params);
        return ['valor' => (float) ($executivo['values']['realizado_hoje'] ?? 0.0), 'updated_at' => $executivo['updated_at'] ?? null];
    }

    public static function realizadoMesAtual(array $params): array
    {
        $executivo = self::buildExecutivoDataset($params);
        return ['valor' => (float) ($executivo['values']['realizado_ate_hoje'] ?? 0.0), 'updated_at' => $executivo['updated_at'] ?? null];
    }

    public static function realizadoAnoAtual(array $params): array
    {
        $executivo = self::buildExecutivoDataset($params);
        return ['valor' => (float) ($executivo['values']['realizado_ano_acum'] ?? 0.0), 'updated_at' => $executivo['updated_at'] ?? null];
    }

    public static function clientesDashboard(array $params): array
    {
        return self::buildClientesDashboardDataset($params);
    }

    public static function insightComercial(array $params): array
    {
        return self::buildInsightComercialDataset($params);
    }

    public static function contasPagarResumo(array $params): array
    {
        $dataset = self::buildContasPagarDataset($params);
        return [
            'periodo' => $dataset['periodo'],
            'resumo' => $dataset['resumo'],
            'proximos' => $dataset['proximos'],
        ];
    }

    public static function contasPagarProximos(array $params): array
    {
        $dataset = self::buildContasPagarDataset($params);
        $janela = trim((string) ($params['janela'] ?? '15_dias'));
        if (!isset($dataset['proximos'][$janela])) {
            $janela = '15_dias';
        }

        return [
            'periodo' => $dataset['periodo'],
            'janela' => $janela,
            'data' => $dataset['proximos'][$janela],
        ];
    }

    public static function contasPagarRankings(array $params): array
    {
        $dataset = self::buildContasPagarDataset($params);
        $limit = self::clampInt($params['limit'] ?? 20, 1, 100);
        return [
            'periodo' => $dataset['periodo'],
            'rankings' => [
                'centro_custo' => array_slice($dataset['rankings']['centro_custo'], 0, $limit),
                'fornecedor' => array_slice($dataset['rankings']['fornecedor'], 0, $limit),
                'max_centro' => $dataset['rankings']['max_centro'],
                'max_fornecedor' => $dataset['rankings']['max_fornecedor'],
            ],
            'centro_fornecedores' => $dataset['centro_fornecedores'],
        ];
    }

    public static function documentoEntradaResumo(array $params): array
    {
        $dataset = self::buildDocumentoEntradaDataset($params);
        return [
            'periodo' => $dataset['periodo'],
            'resumo' => $dataset['resumo'],
            'proximos' => $dataset['proximos'],
        ];
    }

    public static function documentoEntradaProximos(array $params): array
    {
        $dataset = self::buildDocumentoEntradaDataset($params);
        $janela = trim((string) ($params['janela'] ?? '15_dias'));
        if (!isset($dataset['proximos'][$janela])) {
            $janela = '15_dias';
        }
        return [
            'periodo' => $dataset['periodo'],
            'janela' => $janela,
            'data' => $dataset['proximos'][$janela],
        ];
    }

    public static function documentoEntradaRankings(array $params): array
    {
        $dataset = self::buildDocumentoEntradaDataset($params);
        $limit = self::clampInt($params['limit'] ?? 20, 1, 100);
        return [
            'data_base' => $dataset['data_base'],
            'periodo' => $dataset['periodo'],
            'rankings' => [
                'centro_custo' => array_slice($dataset['rankings']['centro_custo'], 0, $limit),
                'natureza' => array_slice($dataset['rankings']['natureza'], 0, $limit),
                'fornecedor' => array_slice($dataset['rankings']['fornecedor'], 0, $limit),
                'max_centro' => $dataset['rankings']['max_centro'],
                'max_natureza' => $dataset['rankings']['max_natureza'],
                'max_fornecedor' => $dataset['rankings']['max_fornecedor'],
            ],
            'centro_fornecedores' => $dataset['centro_fornecedores'],
            'natureza_fornecedores' => $dataset['natureza_fornecedores'],
        ];
    }

    public static function comexImportacoesResumo(array $params): array
    {
        $dataset = self::buildComexImportacoesDataset($params);
        return [
            'ok' => true,
            'total' => $dataset['total'],
            'kpis' => $dataset['kpis'],
            'cached_at' => $dataset['cached_at'],
        ];
    }

    public static function comexImportacoesLista(array $params): array
    {
        $dataset = self::buildComexImportacoesDataset($params);
        $items = $dataset['items'];
        $fase = self::normalizeText((string) ($params['fase'] ?? ''));
        $somenteAtrasadas = !empty($params['atrasadas']) && (string) $params['atrasadas'] !== '0';

        $items = array_values(array_filter($items, static function (array $item) use ($fase, $somenteAtrasadas): bool {
            if ($fase !== '' && self::normalizeText((string) ($item['fase'] ?? '')) !== $fase) {
                return false;
            }
            if ($somenteAtrasadas && empty($item['atrasada'])) {
                return false;
            }
            return true;
        }));

        return [
            'ok' => true,
            'total' => count($items),
            'items' => $items,
            'cached_at' => $dataset['cached_at'],
        ];
    }

    private static function buildInadimplenciaDataset(array $params): array
    {
        $rows = self::fetchConsultaRows('000076', !empty($params['force']));
        $todayTs = strtotime(date('Y-m-d 00:00:00'));
        $diasMin = self::clampInt($params['dias_min_atraso'] ?? 3, 0, 365);
        $todayRefTs = strtotime("-{$diasMin} days", $todayTs);
        $search = self::normalizeText((string) ($params['search'] ?? ''));
        $filterVend = trim((string) ($params['vendedor'] ?? ''));
        $filterSuper = trim((string) ($params['supervisor'] ?? ''));
        $valorMin = max(0.0, self::toFloatBr($params['valor_min'] ?? 0));

        $clientes = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $codCliente = trim((string) self::pickFirst($row, ['E1_CLIENTE', 'A1_COD', 'COD_CLIENTE'], ''));
            $loja = trim((string) self::pickFirst($row, ['E1_LOJA', 'A1_LOJA', 'LOJA_CLIENTE', 'LOJA'], ''));
            if ($codCliente === '' || $loja === '') {
                continue;
            }

            $saldo = self::toFloatBr($row['E1_SALDO'] ?? 0);
            if ($saldo <= 0) {
                continue;
            }

            $vencto = self::onlyDigits((string) ($row['E1_VENCTO'] ?? ''));
            $tsVencto = self::parseYmd($vencto);
            if ($tsVencto === null || $tsVencto > $todayRefTs) {
                continue;
            }

            $diasAtraso = max(0, (int) floor(($todayTs - $tsVencto) / 86400));
            $key = self::clienteKey($codCliente, $loja);

            if (!isset($clientes[$key])) {
                $clientes[$key] = [
                    'cliente' => $codCliente,
                    'loja' => $loja,
                    'cliente_key' => $key,
                    'nome' => trim((string) self::pickFirst($row, ['A1_NOME', 'CLIENTE', 'NOME'], 'CLIENTE NAO IDENTIFICADO')),
                    'cnpj' => self::onlyDigits((string) self::pickFirst($row, ['A1_CGC', 'CGC', 'CNPJ'], '')),
                    'vendedor_codigo' => trim((string) self::pickFirst($row, ['E1_VEND1', 'A1_VEND', 'COD_VENDEDOR'], '')),
                    'vendedor_nome' => trim((string) self::pickFirst($row, ['A3_NOME', 'VENDEDOR', 'VENDEDOR_NOME'], '')),
                    'supervisor_codigo' => trim((string) self::pickFirst($row, ['A3_SUPER', 'SUPER', 'SUPERVISOR'], '')),
                    'supervisor_nome' => trim((string) self::pickFirst($row, ['SUPERVISOR_NOME', 'A3_SUPER_NOME'], '')),
                    'inad_total' => 0.0,
                    'inad_qtd_titulos' => 0,
                    'maior_atraso_dias' => 0,
                    'media_atraso_dias' => 0.0,
                    'titulos' => [],
                ];
            }

            $clientes[$key]['inad_total'] += $saldo;
            $clientes[$key]['inad_qtd_titulos']++;
            $clientes[$key]['maior_atraso_dias'] = max((int) $clientes[$key]['maior_atraso_dias'], $diasAtraso);
            $clientes[$key]['titulos'][] = [
                'titulo' => trim((string) ($row['E1_PREFIXO'] ?? '')) . '-' . trim((string) ($row['E1_NUM'] ?? '')) . '-' . trim((string) ($row['E1_PARCELA'] ?? '')),
                'saldo' => $saldo,
                'valor' => self::toFloatBr($row['E1_VALOR'] ?? 0),
                'vencto' => $vencto,
                'vencto_fmt' => self::formatDateBr($vencto),
                'dias_atraso' => $diasAtraso,
                'forma_pagamento' => trim((string) ($row['E1_XFRMPAG'] ?? '')),
            ];
        }

        foreach ($clientes as $key => $cliente) {
            $somaDias = 0;
            foreach ($cliente['titulos'] as $titulo) {
                $somaDias += (int) ($titulo['dias_atraso'] ?? 0);
            }
            $clientes[$key]['media_atraso_dias'] = $cliente['inad_qtd_titulos'] > 0
                ? round($somaDias / (int) $cliente['inad_qtd_titulos'], 1)
                : 0.0;

            usort($clientes[$key]['titulos'], static fn(array $a, array $b): int => ($b['saldo'] <=> $a['saldo']));
        }

        $clientesFiltrados = array_values(array_filter($clientes, static function (array $cliente) use ($search, $filterVend, $filterSuper, $valorMin): bool {
            if ((float) ($cliente['inad_total'] ?? 0.0) < $valorMin) {
                return false;
            }

            if ($filterVend !== '' && !self::textMatchesAny($filterVend, [
                (string) ($cliente['vendedor_codigo'] ?? ''),
                (string) ($cliente['vendedor_nome'] ?? ''),
            ])) {
                return false;
            }

            if ($filterSuper !== '' && !self::textMatchesAny($filterSuper, [
                (string) ($cliente['supervisor_codigo'] ?? ''),
                (string) ($cliente['supervisor_nome'] ?? ''),
            ])) {
                return false;
            }

            if ($search !== '' && !self::normalizedContainsAny($search, [
                (string) ($cliente['cliente'] ?? ''),
                (string) ($cliente['loja'] ?? ''),
                (string) ($cliente['nome'] ?? ''),
                (string) ($cliente['cnpj'] ?? ''),
                (string) ($cliente['vendedor_nome'] ?? ''),
                (string) ($cliente['supervisor_nome'] ?? ''),
            ])) {
                return false;
            }

            return true;
        }));

        return [
            'clientes_filtrados' => $clientesFiltrados,
            'filtros' => [
                'dias_min_atraso' => $diasMin,
                'search' => (string) ($params['search'] ?? ''),
                'vendedor' => $filterVend,
                'supervisor' => $filterSuper,
                'valor_min' => $valorMin,
            ],
        ];
    }

    private static function buildExecutivoDataset(array $params): array
    {
        $dash = trim((string) ($params['dash'] ?? 'executivo'));
        $metrics = self::fetchDashboardMetrics($dash);
        $totvs = self::fetchExecutivoTotvs($params);

        $m = array_merge($metrics, $totvs['values']);
        $adj = self::fetchDashboardAjustes($dash);
        $hojeFaturadoTotvs = (float) ($m['hoje_faturado'] ?? 0.0);
        $mesFaturadoTotvs = (float) ($m['mes_faturado'] ?? 0.0);
        $anoFaturadoTotvs = (float) ($m['ano_faturado'] ?? 0.0);

        $m['hoje_faturado'] = $hojeFaturadoTotvs + $adj['hoje'];
        $m['mes_faturado'] = $mesFaturadoTotvs + $adj['mes'];
        $m['ano_faturado'] = $anoFaturadoTotvs + $adj['ano'];

        $m['realizado_hoje'] = (float) ($m['hoje_faturado'] ?? 0) + (float) ($m['hoje_im'] ?? 0);
        $m['realizado_ate_hoje'] = (float) ($m['mes_faturado'] ?? 0) + (float) ($m['mes_im'] ?? 0);
        $m['realizado_ano_acum'] = (float) ($m['ano_faturado'] ?? 0) + (float) ($m['ano_im'] ?? 0);

        $metaAno = 53000000.00;
        $realizadoAno = (float) ($m['realizado_ano_acum'] ?? 0);
        $faltaAno = max(0, $metaAno - $realizadoAno);

        $metaMes = (float) ($m['meta_mes'] ?? 0);
        $realizadoMes = (float) ($m['realizado_ate_hoje'] ?? 0);
        $faltaMes = max(0, $metaMes - $realizadoMes);
        $atingimentoMesPct = $metaMes > 0 ? ($realizadoMes / $metaMes) : 0.0;

        [$diasPassados, $diasTotais] = function_exists('dias_uteis_mes_ate_hoje')
            ? dias_uteis_mes_ate_hoje()
            : [0, 0];

        $deveriaTerHoje = ($metaMes / max(1, $diasTotais)) * $diasPassados;
        $realizadoDiaUtil = $diasPassados > 0 ? ($realizadoMes / $diasPassados) : 0.0;
        $metaDiaUtil = $diasTotais > 0 ? ($metaMes / $diasTotais) : 0.0;
        $produtividadePct = $metaDiaUtil > 0 ? ($realizadoDiaUtil / $metaDiaUtil) : 0.0;
        $diasRestantes = max(1, $diasTotais - $diasPassados);
        $aFaturarPorDia = $faltaMes / $diasRestantes;
        $projecaoFechamento = $realizadoDiaUtil * $diasTotais;
        $equivalePct = $metaMes > 0 ? ($projecaoFechamento / $metaMes) : 0.0;

        return [
            'dash' => $dash,
            'updated_at' => $totvs['updated_at'] ?? date('d/m/Y, H:i'),
            'values' => [
                'meta_ano' => $metaAno,
                'realizado_ano_acum' => $realizadoAno,
                'falta_meta_ano' => $faltaAno,
                'meta_mes' => $metaMes,
                'realizado_ate_hoje' => $realizadoMes,
                'falta_meta_mes' => $faltaMes,
                'atingimento_mes_pct' => $atingimentoMesPct,
                'deveria_ate_hoje' => $deveriaTerHoje,
                'meta_dia_util' => $metaDiaUtil,
                'realizado_dia_util' => $realizadoDiaUtil,
                'realizado_dia_util_pct' => $produtividadePct,
                'a_faturar_dia_util' => $aFaturarPorDia,
                'dias_uteis_trabalhar' => $diasTotais,
                'dias_uteis_trabalhados' => $diasPassados,
                'vai_bater_meta' => $projecaoFechamento >= $metaMes ? 'SIM' : 'NAO',
                'fechar_em' => $projecaoFechamento,
                'equivale_pct' => $equivalePct,
                'ajuste_hoje' => $adj['hoje'],
                'ajuste_mes' => $adj['mes'],
                'ajuste_ano' => $adj['ano'],
                'hoje_faturado_totvs' => $hojeFaturadoTotvs,
                'hoje_faturado_ajuste_manual' => $adj['hoje'],
                'hoje_faturado_total' => (float) ($m['hoje_faturado'] ?? 0),
                'hoje_total' => (float) ($m['realizado_hoje'] ?? 0),
                'hoje_faturado' => (float) ($m['hoje_faturado'] ?? 0),
                'hoje_im' => (float) ($m['hoje_im'] ?? 0),
                'hoje_imediato' => (float) ($m['hoje_im'] ?? 0),
                'hoje_ag' => (float) ($m['hoje_ag'] ?? 0),
                'hoje_agendado' => (float) ($m['hoje_ag'] ?? 0),
                'mes_faturado_totvs' => $mesFaturadoTotvs,
                'mes_faturado_ajuste_manual' => $adj['mes'],
                'mes_faturado_total' => (float) ($m['mes_faturado'] ?? 0),
                'mes_total' => (float) ($m['realizado_ate_hoje'] ?? 0),
                'mes_faturado' => (float) ($m['mes_faturado'] ?? 0),
                'mes_im' => (float) ($m['mes_im'] ?? 0),
                'mes_imediato' => (float) ($m['mes_im'] ?? 0),
                'mes_ag' => (float) ($m['mes_ag'] ?? 0),
                'mes_agendado' => (float) ($m['mes_ag'] ?? 0),
                'ano_faturado_totvs' => $anoFaturadoTotvs,
                'ano_faturado_ajuste_manual' => $adj['ano'],
                'ano_faturado_total' => (float) ($m['ano_faturado'] ?? 0),
                'ano_faturado' => (float) ($m['ano_faturado'] ?? 0),
                'ano_im' => (float) ($m['ano_im'] ?? 0),
                'ano_imediato' => (float) ($m['ano_im'] ?? 0),
                'ano_ag' => (float) ($m['ano_ag'] ?? 0),
                'ano_agendado' => (float) ($m['ano_ag'] ?? 0),
            ],
            'labels' => [
                'im' => 'Pedidos para faturar hoje',
                'ag' => 'Pedidos agendados futuros',
                'mes_im' => 'Pedidos para faturar hoje no acumulado do mes',
                'mes_ag' => 'Pedidos agendados futuros no acumulado do mes',
            ],
            'totvs_exec' => $totvs,
        ];
    }

    private static function buildExecutivoTopsDataset(array $params): array
    {
        $rows70 = self::fetchConsultaRows('000070', !empty($params['force']));
        $rows71 = self::fetchConsultaRows('000071', !empty($params['force']));
        $ym = (string) ($params['ym'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
            $ym = date('Y-m');
        }

        $fromMonth = strtotime($ym . '-01 00:00:00');
        $toMonth = strtotime(date('Y-m-t 23:59:59', $fromMonth));
        $todayStr = date('Ymd');
        $fromYear = strtotime(date('Y-01-01 00:00:00'));
        $toToday = strtotime(date('Y-m-d 23:59:59'));

        $diarioMes = [];
        $qtdNfHoje = 0;
        $qtdNfMes = 0;
        $fatToday = 0.0;
        $fatMonth = 0.0;
        $fatYear = 0.0;
        $agdToday = 0.0;
        $agdMonth = 0.0;
        $agdYear = 0.0;
        $topProdutos = [];
        $topVendedores = [];
        $clientesMes = [];
        $filiaisMes = [];

        foreach ($rows70 as $row) {
            if (!is_array($row)) {
                continue;
            }

            $emissao = (string) self::pickFirst($row, ['EMISAO', 'C5_EMISSAO'], '');
            $ts = self::parseYmd($emissao);
            if ($ts === null) {
                continue;
            }

            $valor = self::toFloatBr($row['VALOR'] ?? 0);
            if (self::inRange($ts, $fromYear, $toToday)) {
                $fatYear += $valor;
            }

            if (self::inRange($ts, $fromMonth, $toMonth)) {
                $fatMonth += $valor;
                $dia = substr($emissao, 6, 2);
                $diarioMes[$dia] = (($diarioMes[$dia] ?? 0.0) + $valor);
                $qtdNfMes++;

                $filialCodigo = self::resolveFilialCode($row);
                $filialNome = self::resolveFilialName($filialCodigo, $row);
                self::appendFilialBreakdown($filiaisMes, $filialCodigo, $filialNome, $valor);

                $produto = trim((string) self::pickFirst($row, ['PRODUTO', 'PROD_DESC', 'DESCRICAO', 'B1_DESC', 'ITEM_DESC', 'C6_DESCRI'], ''));
                if ($produto !== '') {
                    $topProdutos[$produto] = (($topProdutos[$produto] ?? 0.0) + $valor);
                }

                $vendedor = trim((string) self::pickFirst($row, ['VENDEDOR', 'VENDEDOR_NOME', 'A3_NOME', 'CLIENTE', 'A1_NOME'], ''));
                if ($vendedor !== '') {
                    $topVendedores[$vendedor] = (($topVendedores[$vendedor] ?? 0.0) + $valor);
                }

                $clienteMes = trim((string) self::pickFirst($row, ['COD_CLIENTE', 'C5_CLIENTE'], '')) . '|' . trim((string) self::pickFirst($row, ['LOJA_CLIENTE', 'C5_LOJACLI', 'C5_LOJA'], ''));
                if ($clienteMes !== '|') {
                    $clientesMes[$clienteMes] = true;
                }
            }

            if ($emissao === $todayStr) {
                $fatToday += $valor;
                $qtdNfHoje++;
            }
        }

        foreach ($rows71 as $row) {
            if (!is_array($row)) {
                continue;
            }

            $emissao = (string) self::pickFirst($row, ['C5_EMISSAO', 'EMISAO'], '');
            $ts = self::parseYmd($emissao);
            if ($ts === null) {
                continue;
            }

            $valor = self::toFloatBr($row['VALOR_PEDIDO'] ?? $row['VALOR'] ?? 0);
            if (self::inRange($ts, $fromYear, $toToday)) {
                $agdYear += $valor;
            }
            if (self::inRange($ts, $fromMonth, $toMonth)) {
                $agdMonth += $valor;
            }
            if ($emissao === $todayStr) {
                $agdToday += $valor;
            }
        }

        $ajustes = self::fetchDashboardAjustesByDateRange('executivo', date('Y-m-d', $fromYear), date('Y-m-d', $toToday));
        $ajusteHoje = 0.0;
        $ajusteMes = 0.0;
        $ajusteAno = 0.0;
        foreach ($ajustes['rows'] as $ajuste) {
            $refDate = (string) ($ajuste['ref_date'] ?? '');
            $valor = (float) ($ajuste['valor'] ?? 0.0);
            if ($refDate === '') {
                continue;
            }

            $adjYmd = str_replace('-', '', $refDate);
            if ($refDate >= date('Y-m-d', $fromMonth) && $refDate <= date('Y-m-d', $toMonth)) {
                $fatMonth += $valor;
                $ajusteMes += $valor;
                $dia = substr($adjYmd, 6, 2);
                if ($dia !== '') {
                    $diarioMes[$dia] = (($diarioMes[$dia] ?? 0.0) + $valor);
                }
            }
            if ($refDate >= date('Y-m-d', $fromYear) && $refDate <= date('Y-m-d', $toToday)) {
                $fatYear += $valor;
                $ajusteAno += $valor;
            }
            if ($adjYmd === $todayStr) {
                $fatToday += $valor;
                $ajusteHoje += $valor;
            }
        }

        arsort($topProdutos);
        arsort($topVendedores);
        ksort($diarioMes);

        return [
            'success' => true,
            'ym' => $ym,
            'updated_at' => date('Y-m-d H:i:s'),
            'values' => [
                'hoje_faturado' => round($fatToday, 2),
                'hoje_agendado' => round($agdToday, 2),
                'hoje_total' => round($fatToday + $agdToday, 2),
                'mes_faturado' => round($fatMonth, 2),
                'mes_agendado' => round($agdMonth, 2),
                'mes_total' => round($fatMonth + $agdMonth, 2),
                'ano_faturado' => round($fatYear, 2),
                'ano_agendado' => round($agdYear, 2),
                'ano_total' => round($fatYear + $agdYear, 2),
                'ajuste_hoje' => round($ajusteHoje, 2),
                'ajuste_mes' => round($ajusteMes, 2),
                'ajuste_ano' => round($ajusteAno, 2),
            ],
            'qtd_nf_hoje' => $qtdNfHoje,
            'qtd_nf_mes' => $qtdNfMes,
            'clientes_mes' => count($clientesMes),
            'diario_mes' => $diarioMes,
            'top_produtos' => array_slice($topProdutos, 0, 100, true),
            'top_vendedores' => array_slice($topVendedores, 0, 50, true),
            'por_filial' => self::finalizeFilialBreakdown($filiaisMes),
            'debug' => [
                'top_produtos_count' => count($topProdutos),
                'top_vendedores_count' => count($topVendedores),
            ],
        ];
    }

    private static function buildClientesDashboardDataset(array $params): array
    {
        $ym = (string) ($params['ym'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
            $ym = date('Y-m');
        }

        $rows = self::fetchConsultaRows('000070', !empty($params['force']));
        $fromMonth = strtotime($ym . '-01 00:00:00');
        $toMonthEnd = strtotime(date('Y-m-t 23:59:59', $fromMonth));
        $toToday = date('Y-m', time()) === $ym ? strtotime(date('Y-m-d 23:59:59')) : $toMonthEnd;

        $clients = [];
        $nfByClient = [];
        $daysByClient = [];
        $totalPedidosSet = [];
        $totalValorMes = 0.0;
        $totalCustoMes = 0.0;
        $descAll = [];
        $ufs = [];
        $regioes = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $ts = self::parseYmd((string) ($row['EMISAO'] ?? ''));
            if ($ts === null || $ts < $fromMonth || $ts > $toToday) {
                continue;
            }

            $cod = trim((string) ($row['COD_CLIENTE'] ?? ''));
            $loja = trim((string) ($row['LOJA_CLIENTE'] ?? ''));
            $nome = (string) ($row['CLIENTE'] ?? 'Cliente');
            if ($cod === '') {
                continue;
            }

            $key = self::clienteKey($cod, $loja);
            $nf = trim((string) ($row['NF'] ?? ''));
            $valor = self::toFloatBr($row['VALOR'] ?? 0);
            $custo = self::toFloatBr($row['CUSTO'] ?? 0);
            $tabela = self::toFloatBr($row['PRECO_TABELA'] ?? 0);
            $praticado = self::toFloatBr($row['PRECO_PRATICADO'] ?? 0);
            $desc = $tabela > 0 ? max(0.0, min(1.0, ($tabela - $praticado) / $tabela)) : 0.0;

            if (!isset($clients[$key])) {
                $clients[$key] = [
                    'key' => $key,
                    'cliente' => $nome,
                    'valor' => 0.0,
                    'custo' => 0.0,
                    'desc_sum' => 0.0,
                    'desc_cnt' => 0,
                    'first_ts' => $ts,
                    'last_ts' => $ts,
                ];
                $nfByClient[$key] = [];
            }

            $clients[$key]['valor'] += $valor;
            $clients[$key]['custo'] += $custo;
            if ($tabela > 0) {
                $clients[$key]['desc_sum'] += $desc;
                $clients[$key]['desc_cnt']++;
                $descAll[] = $desc;
            }
            $clients[$key]['first_ts'] = min((int) $clients[$key]['first_ts'], $ts);
            $clients[$key]['last_ts'] = max((int) $clients[$key]['last_ts'], $ts);
            if ($nf !== '') {
                $nfByClient[$key][$nf] = true;
                $totalPedidosSet[$nf] = true;
            }

            $ymd = date('Y-m-d', $ts);
            $daysByClient[$key][$ymd] = ($daysByClient[$key][$ymd] ?? 0.0) + $valor;

            $uf = strtoupper(trim((string) self::pickFirst($row, ['UF', 'ESTADO', 'EST', 'COD_ESTADO', 'UF_CLIENTE', 'ESTADO_CLIENTE'], '')));
            if (preg_match('/^[A-Z]{2}$/', $uf)) {
                $ufs[$uf] = ($ufs[$uf] ?? 0.0) + $valor;
                $regiao = self::ufToRegiao($uf);
                $regioes[$regiao] = ($regioes[$regiao] ?? 0.0) + $valor;
            }

            $totalValorMes += $valor;
            $totalCustoMes += $custo;
        }

        $list = array_values($clients);
        foreach ($list as &$client) {
            $valor = (float) $client['valor'];
            $custo = (float) $client['custo'];
            $margem = $valor - $custo;
            $margemPct = $valor > 0 ? ($margem / $valor) : 0.0;
            $pedidos = isset($nfByClient[$client['key']]) ? count($nfByClient[$client['key']]) : 0;
            $ticket = $pedidos > 0 ? ($valor / $pedidos) : $valor;
            $descMed = (int) $client['desc_cnt'] > 0 ? ((float) $client['desc_sum'] / (int) $client['desc_cnt']) : 0.0;
            $client['margem'] = $margem;
            $client['margem_pct'] = $margemPct;
            $client['pedidos'] = $pedidos;
            $client['ticket_medio'] = $ticket;
            $client['desconto_medio'] = $descMed;
        }
        unset($client);

        usort($list, static fn(array $a, array $b): int => ((float) $b['valor'] <=> (float) $a['valor']));
        $top50 = array_slice($list, 0, 50);

        $clientesAtivos = count($list);
        $pedidosMes = count($totalPedidosSet);
        $ticketMedio = $pedidosMes > 0 ? ($totalValorMes / $pedidosMes) : 0.0;
        $margemMes = $totalValorMes - $totalCustoMes;
        $margemPctMes = $totalValorMes > 0 ? ($margemMes / $totalValorMes) : 0.0;
        $margemMediaCliente = $clientesAtivos > 0 ? ($margemMes / $clientesAtivos) : 0.0;
        $descMedGeral = count($descAll) > 0 ? (array_sum($descAll) / count($descAll)) : 0.0;

        $top3Sum = 0.0;
        for ($i = 0; $i < min(3, count($top50)); $i++) {
            $top3Sum += (float) $top50[$i]['valor'];
        }
        $top3Pct = $totalValorMes > 0 ? ($top3Sum / $totalValorMes) : 0.0;

        $cum = 0.0;
        $abcItems = [];
        foreach ($list as $client) {
            $cum += (float) $client['valor'];
            $abcItems[] = [
                'key' => (string) $client['key'],
                'cliente' => (string) $client['cliente'],
                'valor' => round((float) $client['valor'], 2),
                'cum_pct' => round($totalValorMes > 0 ? ($cum / $totalValorMes) : 0.0, 6),
            ];
        }

        $margemTop10 = array_slice(array_map(static fn(array $c): array => [
            'key' => (string) $c['key'],
            'cliente' => (string) $c['cliente'],
            'margem_pct' => round((float) $c['margem_pct'], 6),
            'margem' => round((float) $c['margem'], 2),
            'valor' => round((float) $c['valor'], 2),
        ], $top50), 0, 10);

        $frequencia = [
            ['label' => '0-7', 'count' => 0],
            ['label' => '8-15', 'count' => 0],
            ['label' => '16-30', 'count' => 0],
            ['label' => '31+', 'count' => 0],
        ];

        foreach ($list as $client) {
            $ped = (int) ($client['pedidos'] ?? 0);
            if ($ped <= 1) {
                continue;
            }
            $spanDays = max(1, (int) floor((((int) $client['last_ts']) - ((int) $client['first_ts'])) / 86400));
            $avgGap = (float) $spanDays / max(1, ($ped - 1));
            if ($avgGap <= 7) {
                $frequencia[0]['count']++;
            } elseif ($avgGap <= 15) {
                $frequencia[1]['count']++;
            } elseif ($avgGap <= 30) {
                $frequencia[2]['count']++;
            } else {
                $frequencia[3]['count']++;
            }
        }

        return [
            'ym' => $ym,
            'updated_at' => date('c'),
            'kpis' => [
                'clientes_ativos' => $clientesAtivos,
                'ticket_medio_nf' => round($ticketMedio, 2),
                'margem_media_cliente' => round($margemMediaCliente, 2),
                'margem_pct' => round($margemPctMes * 100, 2),
                'desconto_medio' => round($descMedGeral * 100, 2),
                'top3_pct' => round($top3Pct * 100, 2),
                'faturamento_total' => round($totalValorMes, 2),
            ],
            'top_clientes' => $top50,
            'curva_abc' => $abcItems,
            'margem_top10' => $margemTop10,
            'geografia' => [
                'ufs' => $ufs,
                'regioes' => $regioes,
            ],
            'frequencia_compra' => $frequencia,
        ];
    }

    private static function buildInsightComercialDataset(array $params): array
    {
        $ym = (string) ($params['ym'] ?? date('Y-m'));
        $monthStart = $params['dt_ini'] ?? ($ym . '-01');
        $monthEnd = $params['dt_fim'] ?? date('Y-m-t', strtotime($monthStart));
        $monthStartTs = strtotime($monthStart . ' 00:00:00');
        $monthEndTs = strtotime($monthEnd . ' 23:59:59');
        $rows = self::fetchConsultaRows('000070', !empty($params['force']));

        $itemsMonth = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $emi = (string) self::pickFirst($row, ['EMISAO', 'C5_EMISSAO'], '');
            $ts = self::parseYmd($emi);
            if ($ts === null || $ts < $monthStartTs || $ts > $monthEndTs) {
                continue;
            }
            $itemsMonth[] = $row;
        }

        $bySeller = [];
        $byState = [];
        $nfTotals = [];
        $totalMes = 0.0;
        $priceAggVendor = [];
        $priceAggClient = [];
        $priceAggProd = [];
        $produtos = [];
        $filiais = [];
        $produtoFiltro = self::normalizeText((string) ($params['produto_filtro'] ?? ''));

        foreach ($itemsMonth as $row) {
            $seller = trim((string) self::pickFirst($row, ['VENDEDOR', 'A3_NOME'], 'Sem vendedor'));
            $uf = trim((string) self::pickFirst($row, ['ESTADO', 'UF'], 'N/D'));
            $nf = trim((string) self::pickFirst($row, ['NF', 'D2_DOC'], ''));
            $value = self::toFloatBr(self::pickFirst($row, ['VALOR', 'D2_TOTAL', 'VALOR_TOTAL'], 0));
            $totalMes += $value;
            if ($value > 0) {
                $bySeller[$seller] = ($bySeller[$seller] ?? 0.0) + $value;
                $byState[$uf] = ($byState[$uf] ?? 0.0) + $value;
                if ($nf !== '') {
                    $nfTotals[$nf] = ($nfTotals[$nf] ?? 0.0) + $value;
                }

                $filialCodigo = self::resolveFilialCode($row);
                $filialNome = self::resolveFilialName($filialCodigo, $row);
                self::appendFilialBreakdown($filiais, $filialCodigo, $filialNome, $value);
            }

            $sellerCode = self::pickFirst($row, ['COD_VENDEDOR', 'F2_VEND1', 'VENDEDOR_COD'], '');
            $sellerName = self::pickFirst($row, ['VENDEDOR', 'A3_NOME'], '');
            $clientCode = self::pickFirst($row, ['COD_CLIENTE', 'CLIENTE_COD', 'D2_CLIENTE', 'A1_COD'], '');
            $clientName = self::pickFirst($row, ['CLIENTE', 'A1_NOME'], '');
            $prodCode = self::pickFirst($row, ['CODIGO', 'B1_COD', 'PRODUTO_COD'], '');
            $prodName = self::pickFirst($row, ['PRODUTO', 'B1_DESC'], '');
            $prodKey = self::normKey((string) $prodCode, (string) $prodName, 'Sem produto');

            if (!isset($produtos[$prodKey])) {
                $produtos[$prodKey] = [
                    'codigo' => trim((string) $prodCode),
                    'nome' => trim((string) $prodName) !== '' ? trim((string) $prodName) : 'Sem produto',
                    'quantidade' => 0.0,
                    'valor_total' => 0.0,
                    'documentos' => [],
                ];
            }

            $qtde = self::toFloatBr(self::pickFirst($row, ['QTDE', 'D2_QUANT'], 0));
            $pt = self::toFloatBr(self::pickFirst($row, ['PRECO_TABELA', 'D2_PRUNIT'], 0));
            $pp = self::toFloatBr(self::pickFirst($row, ['PRECO_PRATICADO', 'D2_PRCVEN'], 0));

            if ($value > 0) {
                $produtos[$prodKey]['quantidade'] += $qtde;
                $produtos[$prodKey]['valor_total'] += $value;
                if ($nf !== '') {
                    $produtos[$prodKey]['documentos'][$nf] = true;
                }
            }

            if ($qtde <= 0 || $pt <= 0 || $pp <= 0) {
                continue;
            }

            $sellerKey = self::normKey((string) $sellerCode, (string) $sellerName, 'Sem vendedor');
            $clientKey = self::normKey((string) $clientCode, (string) $clientName, 'Sem cliente');
            $valTabela = $pt * $qtde;
            $valPrat = $pp * $qtde;
            $descR = $valTabela - $valPrat;
            if ($descR <= 0) {
                continue;
            }

            self::appendPriceAgg($priceAggVendor, $sellerKey, $valTabela, $valPrat, $descR, $qtde);
            self::appendPriceAgg($priceAggClient, $clientKey, $valTabela, $valPrat, $descR, $qtde);
            self::appendPriceAgg($priceAggProd, $prodKey, $valTabela, $valPrat, $descR, $qtde);
        }

        arsort($bySeller);
        arsort($byState);
        $ajuste = self::fetchDashboardAjustesByDateRange('executivo', $monthStart, $monthEnd);
        $faturadoTotvs = round($totalMes, 2);
        $faturadoAjuste = round((float) $ajuste['valor_periodo'], 2);
        $faturadoFinal = round($faturadoTotvs + $faturadoAjuste, 2);
        $totalDocumentosTotvs = count($nfTotals);
        $totalDocumentosAjuste = 0;
        $totalDocumentosFinal = $totalDocumentosTotvs + $totalDocumentosAjuste;
        $ticketMedio = $totalDocumentosFinal > 0 ? ($faturadoFinal / $totalDocumentosFinal) : 0.0;
        $produtosList = [];
        foreach ($produtos as $produto) {
            if ($produtoFiltro !== '' && !str_contains(self::normalizeText((string) ($produto['nome'] ?? '')), $produtoFiltro)) {
                continue;
            }

            $docs = count($produto['documentos']);
            $valorTotal = round((float) ($produto['valor_total'] ?? 0.0), 2);
            $produtosList[] = [
                'codigo' => (string) ($produto['codigo'] ?? ''),
                'nome' => (string) ($produto['nome'] ?? ''),
                'quantidade' => round((float) ($produto['quantidade'] ?? 0.0), 2),
                'valor_total' => $valorTotal,
                'ticket_medio' => $docs > 0 ? round($valorTotal / $docs, 2) : $valorTotal,
            ];
        }
        usort($produtosList, static fn(array $a, array $b): int => (($b['valor_total'] ?? 0.0) <=> ($a['valor_total'] ?? 0.0)));
        $produtosLimit = self::resolveLimit($params['limit'] ?? 20, 20, 5000);

        $response = [
            'periodo' => ['inicio' => $monthStart, 'fim' => $monthEnd],
            'resumo' => [
                'faturado_totvs' => $faturadoTotvs,
                'faturado_ajuste_manual' => $faturadoAjuste,
                'faturado_com_ajustes' => $faturadoFinal,
                'faturado_total' => $faturadoFinal,
                'faturado' => $faturadoFinal,
                'ticket_medio' => round($ticketMedio, 2),
                'total_documentos_totvs' => $totalDocumentosTotvs,
                'total_documentos_ajuste_manual' => $totalDocumentosAjuste,
                'total_documentos' => $totalDocumentosFinal,
            ],
            'top_vendedores' => array_slice($bySeller, 0, 10, true),
            'top_estados' => array_slice($byState, 0, 10, true),
            'produtos' => self::limitItems($produtosList, $produtosLimit),
            'descontos' => [
                'vendedor_pct' => self::rankAggByPct($priceAggVendor),
                'vendedor_desc' => self::rankAggByDesc($priceAggVendor),
                'cliente_pct' => self::rankAggByPct($priceAggClient),
                'cliente_desc' => self::rankAggByDesc($priceAggClient),
                'produto_pct' => self::rankAggByPct($priceAggProd),
                'produto_desc' => self::rankAggByDesc($priceAggProd),
            ],
        ];

        if (self::boolParam($params['breakdown_filial'] ?? false)) {
            $response['por_filial'] = self::finalizeFilialBreakdown($filiais);
        }

        return $response;
    }

    private static function buildContasPagarDataset(array $params): array
    {
        $from = (string) ($params['from'] ?? date('Y-m-01'));
        $to = (string) ($params['to'] ?? date('Y-m-t'));
        $fromTs = strtotime($from . ' 00:00:00') ?: strtotime(date('Y-m-01 00:00:00'));
        $toTs = strtotime($to . ' 23:59:59') ?: strtotime(date('Y-m-t 23:59:59'));
        if ($fromTs > $toTs) {
            [$fromTs, $toTs] = [$toTs, $fromTs];
            [$from, $to] = [$to, $from];
        }

        $rows = self::fetchConsultaRows(TOTVS_CONSULTAS['kpi_contasapagar'] ?? '000072', !empty($params['force']));
        $itemsFiltered = array_values(array_filter($rows, static function ($row) use ($fromTs, $toTs): bool {
            if (!is_array($row)) {
                return false;
            }
            $vencTs = toDateTs($row['E2_VENCREA'] ?? '');
            return $vencTs !== null && $vencTs >= $fromTs && $vencTs <= $toTs;
        }));

        $todayTs = strtotime('today');
        $next3Ts = strtotime('+3 days', $todayTs);
        $next7Ts = strtotime('+7 days', $todayTs);
        $next15Ts = strtotime('+15 days', $todayTs);
        $titulosPorFornecedor = [];
        $totalValor = 0.0;
        $totalQtd = 0;
        $topCentro = [];
        $topFornecedor = [];
        $centroFornecedores = [];
        $proximos3 = [];
        $proximos7 = [];
        $proximos15 = [];

        foreach ($itemsFiltered as $row) {
            $forn = trim((string) ($row['E2_FORNECE'] ?? ''));
            $valor = (float) ($row['E2_VALOR'] ?? 0);
            $ccdNomeado = nomeSetorCCD($row['E2_CCD'] ?? '');
            $fornNomeado = nomeFornecedor($forn);

            if (!isset($titulosPorFornecedor[$fornNomeado])) {
                $titulosPorFornecedor[$fornNomeado] = ['fornecedor' => $fornNomeado, 'total' => 0.0, 'qtd' => 0, 'titulos' => []];
            }
            $titulosPorFornecedor[$fornNomeado]['total'] += $valor;
            $titulosPorFornecedor[$fornNomeado]['qtd']++;

            $totalValor += $valor;
            $totalQtd++;
            $topCentro[$ccdNomeado]['key'] = $ccdNomeado;
            $topCentro[$ccdNomeado]['total'] = ($topCentro[$ccdNomeado]['total'] ?? 0.0) + $valor;
            $topCentro[$ccdNomeado]['qtd'] = ($topCentro[$ccdNomeado]['qtd'] ?? 0) + 1;

            $topFornecedor[$fornNomeado]['key'] = $fornNomeado;
            $topFornecedor[$fornNomeado]['total'] = ($topFornecedor[$fornNomeado]['total'] ?? 0.0) + $valor;
            $topFornecedor[$fornNomeado]['qtd'] = ($topFornecedor[$fornNomeado]['qtd'] ?? 0) + 1;

            $centroFornecedores[$ccdNomeado][$fornNomeado]['nome'] = $fornNomeado;
            $centroFornecedores[$ccdNomeado][$fornNomeado]['qtd'] = ($centroFornecedores[$ccdNomeado][$fornNomeado]['qtd'] ?? 0) + 1;
            $centroFornecedores[$ccdNomeado][$fornNomeado]['total'] = ($centroFornecedores[$ccdNomeado][$fornNomeado]['total'] ?? 0.0) + $valor;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $vencTs = toDateTs($row['E2_VENCREA'] ?? '');
            if ($vencTs === null) {
                continue;
            }
            $row['fornecedor_nome'] = nomeFornecedor($row['E2_FORNECE'] ?? '');
            $row['centro_nome'] = nomeSetorCCD($row['E2_CCD'] ?? '');
            $row['vencimento_fmt'] = ddmmyyyy($row['E2_VENCREA'] ?? '');
            $row['emissao_fmt'] = ddmmyyyy($row['E2_EMISSAO'] ?? '');
            if ($vencTs >= $todayTs && $vencTs <= $next3Ts) {
                $proximos3[] = $row;
            }
            if ($vencTs >= $todayTs && $vencTs <= $next7Ts) {
                $proximos7[] = $row;
            }
            if ($vencTs >= $todayTs && $vencTs <= $next15Ts) {
                $proximos15[] = $row;
            }
        }

        $topCentroList = array_values($topCentro);
        usort($topCentroList, static fn(array $a, array $b): int => ($b['total'] <=> $a['total']));
        $topFornecedorList = array_values($topFornecedor);
        usort($topFornecedorList, static fn(array $a, array $b): int => ($b['total'] <=> $a['total']));
        $maxCentro = empty($topCentroList) ? 0 : max(array_column($topCentroList, 'total'));
        $maxFornecedor = empty($topFornecedorList) ? 0 : max(array_column($topFornecedorList, 'total'));

        $centroFornecedoresResponse = [];
        foreach ($centroFornecedores as $centro => $fornecedores) {
            $lista = array_values($fornecedores);
            usort($lista, static fn(array $a, array $b): int => ($b['total'] <=> $a['total']));
            $totalCentro = $topCentro[$centro]['total'] ?? 0.0;
            foreach ($lista as &$fornecedor) {
                $fornecedor['percent'] = $totalCentro > 0 ? round(($fornecedor['total'] / $totalCentro) * 100, 1) : 0.0;
            }
            unset($fornecedor);
            $centroFornecedoresResponse[$centro] = [
                'centro' => $centro,
                'total' => $totalCentro,
                'fornecedores' => $lista,
            ];
        }

        return [
            'periodo' => ['from' => $from, 'to' => $to],
            'resumo' => [
                'total_qtd' => $totalQtd,
                'total_valor' => $totalValor,
                'proximos_3_dias' => array_sum(array_column($proximos3, 'E2_VALOR')),
                'proximos_7_dias' => array_sum(array_column($proximos7, 'E2_VALOR')),
                'proximos_15_dias' => array_sum(array_column($proximos15, 'E2_VALOR')),
            ],
            'rankings' => [
                'centro_custo' => $topCentroList,
                'fornecedor' => $topFornecedorList,
                'max_centro' => $maxCentro,
                'max_fornecedor' => $maxFornecedor,
            ],
            'centro_fornecedores' => $centroFornecedoresResponse,
            'proximos' => [
                '3_dias' => ['items' => $proximos3, 'total' => array_sum(array_column($proximos3, 'E2_VALOR'))],
                '7_dias' => ['items' => $proximos7, 'total' => array_sum(array_column($proximos7, 'E2_VALOR'))],
                '15_dias' => ['items' => $proximos15, 'total' => array_sum(array_column($proximos15, 'E2_VALOR'))],
            ],
        ];
    }

    private static function buildDocumentoEntradaDataset(array $params): array
    {
        $useEmissaoBase = !empty($params['date_from']) || !empty($params['date_to']);
        $from = (string) ($params[$useEmissaoBase ? 'date_from' : 'from'] ?? date('Y-m-01'));
        $to = (string) ($params[$useEmissaoBase ? 'date_to' : 'to'] ?? date('Y-m-t'));
        $fromTs = strtotime($from . ' 00:00:00') ?: strtotime(date('Y-m-01 00:00:00'));
        $toTs = strtotime($to . ' 23:59:59') ?: strtotime(date('Y-m-t 23:59:59'));
        if ($fromTs > $toTs) {
            [$fromTs, $toTs] = [$toTs, $fromTs];
            [$from, $to] = [$to, $from];
        }
        $dataBase = $useEmissaoBase ? 'emissao' : 'vencimento';

        $rows = self::fetchConsultaRows(TOTVS_CONSULTAS['kpi_docsentrada'] ?? '000083', !empty($params['force']));

        $itemsFiltered = array_values(array_filter($rows, static function ($row) use ($fromTs, $toTs, $useEmissaoBase): bool {
            if (!is_array($row)) {
                return false;
            }
            $dateField = $useEmissaoBase ? 'DT_EMISSAO' : 'DT_VENCIMENTO';
            $baseDate = (string) ($row[$dateField] ?? $row['DATA'] ?? '');
            $baseTs = toDateTs($baseDate);
            return $baseTs !== null && $baseTs >= $fromTs && $baseTs <= $toTs;
        }));

        if ($dataBase === 'vencimento') {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                if (!empty($row['DT_VENCIMENTO'])) {
                    break;
                }
                if (!empty($row['DATA'])) {
                    $dataBase = 'data';
                }
            }
        }

        $todayTs = strtotime('today');
        $next3Ts = strtotime('+3 days', $todayTs);
        $next7Ts = strtotime('+7 days', $todayTs);
        $next15Ts = strtotime('+15 days', $todayTs);

        $titulosPorFornecedor = [];
        $totalValor = 0.0;
        $totalQtd = 0;
        $topNatureza = [];
        $topCentro = [];
        $topFornecedor = [];
        $naturezaFornecedores = [];
        $centroFornecedores = [];
        $proximos3 = [];
        $proximos7 = [];
        $proximos15 = [];

        foreach ($itemsFiltered as $row) {
            $fornNome = trim((string) ($row['NOME_FORNECEDOR'] ?? $row['FORNECEDOR'] ?? $row['COD_FORNECEDOR'] ?? $row['CODIGO'] ?? ''));
            $valor = (float) ($row['VL_PARCELA'] ?? $row['VALOR'] ?? 0);
            $natureza = trim((string) ($row['NATUREZA'] ?? $row['DESCRICAO'] ?? 'N/A'));
            $ccdRaw = trim((string) ($row['CENTRO_CUSTO_TITULO'] ?? $row['CENTRO_CUSTO'] ?? ''));
            $ccdNomeado = $ccdRaw !== '' ? nomeSetorCCD($ccdRaw) : 'N/A';
            $dataDocumento = (string) ($row['DT_EMISSAO'] ?? $row['DT_VENCIMENTO'] ?? $row['DATA'] ?? '');
            $dataVencimento = (string) ($row['DT_VENCIMENTO'] ?? $row['DATA'] ?? '');

            if (!isset($titulosPorFornecedor[$fornNome])) {
                $titulosPorFornecedor[$fornNome] = ['fornecedor' => $fornNome, 'total' => 0.0, 'qtd' => 0, 'titulos' => []];
            }
            $titulosPorFornecedor[$fornNome]['total'] += $valor;
            $titulosPorFornecedor[$fornNome]['qtd']++;
            $titulosPorFornecedor[$fornNome]['titulos'][] = [
                'filial'             => (string) ($row['FILIAL'] ?? ''),
                'nf_numero'          => (string) ($row['NF_NUMERO'] ?? ''),
                'serie'              => (string) ($row['SERIE'] ?? ''),
                'emissao'            => ddmmyyyy($dataDocumento),
                'vencimento'         => ddmmyyyy($dataVencimento),
                'natureza'           => $natureza,
                'centro_custo'       => $ccdNomeado,
                'centro_custo_raw'   => $ccdRaw,
                'centro_custo_rateio'=> (string) ($row['CENTRO_CUSTO_RATEIO'] ?? ''),
                'perc_rateio'        => (float) ($row['PERC_RATEIO'] ?? 0),
                'conta_contabil'     => (string) ($row['CONTA_CONTABIL'] ?? ''),
                'parcela'            => (string) ($row['PARCELA'] ?? ''),
                'vl_parcela'         => $valor,
                'vl_saldo'           => (float) ($row['VL_SALDO'] ?? $row['VALOR'] ?? 0),
                'vl_total_nf'        => (float) ($row['VL_TOTAL_TITULO'] ?? $row['VALOR'] ?? 0),
                'codigo'             => (string) ($row['CODIGO'] ?? ''),
                'loja'               => (string) ($row['LOJA'] ?? ''),
                'fornecedor'         => $fornNome,
            ];

            $totalValor += $valor;
            $totalQtd++;

            $topNatureza[$natureza]['key'] = $natureza;
            $topNatureza[$natureza]['total'] = ($topNatureza[$natureza]['total'] ?? 0.0) + $valor;
            $topNatureza[$natureza]['qtd'] = ($topNatureza[$natureza]['qtd'] ?? 0) + 1;

            $topCentro[$ccdNomeado]['key'] = $ccdNomeado;
            $topCentro[$ccdNomeado]['total'] = ($topCentro[$ccdNomeado]['total'] ?? 0.0) + $valor;
            $topCentro[$ccdNomeado]['qtd'] = ($topCentro[$ccdNomeado]['qtd'] ?? 0) + 1;

            $topFornecedor[$fornNome]['key'] = $fornNome;
            $topFornecedor[$fornNome]['total'] = ($topFornecedor[$fornNome]['total'] ?? 0.0) + $valor;
            $topFornecedor[$fornNome]['qtd'] = ($topFornecedor[$fornNome]['qtd'] ?? 0) + 1;

            $naturezaFornecedores[$natureza][$fornNome]['nome'] = $fornNome;
            $naturezaFornecedores[$natureza][$fornNome]['qtd'] = ($naturezaFornecedores[$natureza][$fornNome]['qtd'] ?? 0) + 1;
            $naturezaFornecedores[$natureza][$fornNome]['total'] = ($naturezaFornecedores[$natureza][$fornNome]['total'] ?? 0.0) + $valor;

            $centroFornecedores[$ccdNomeado][$fornNome]['nome'] = $fornNome;
            $centroFornecedores[$ccdNomeado][$fornNome]['qtd'] = ($centroFornecedores[$ccdNomeado][$fornNome]['qtd'] ?? 0) + 1;
            $centroFornecedores[$ccdNomeado][$fornNome]['total'] = ($centroFornecedores[$ccdNomeado][$fornNome]['total'] ?? 0.0) + $valor;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $vencBase = (string) ($row['DT_VENCIMENTO'] ?? $row['DATA'] ?? '');
            $vencTs = toDateTs($vencBase);
            if ($vencTs === null) {
                continue;
            }
            $row['fornecedor_nome'] = trim((string) ($row['NOME_FORNECEDOR'] ?? $row['FORNECEDOR'] ?? $row['COD_FORNECEDOR'] ?? $row['CODIGO'] ?? ''));
            $row['centro_custo_nome'] = nomeSetorCCD(trim((string) ($row['CENTRO_CUSTO_TITULO'] ?? $row['CENTRO_CUSTO'] ?? '')));
            $row['vencimento_fmt'] = ddmmyyyy($vencBase);
            $row['emissao_fmt'] = ddmmyyyy((string) ($row['DT_EMISSAO'] ?? $row['DATA'] ?? ''));
            if ($vencTs >= $todayTs && $vencTs <= $next3Ts) {
                $proximos3[] = $row;
            }
            if ($vencTs >= $todayTs && $vencTs <= $next7Ts) {
                $proximos7[] = $row;
            }
            if ($vencTs >= $todayTs && $vencTs <= $next15Ts) {
                $proximos15[] = $row;
            }
        }

        $topNaturezaList = array_values($topNatureza);
        usort($topNaturezaList, static fn(array $a, array $b): int => ($b['total'] <=> $a['total']));

        $topCentroList = array_values($topCentro);
        usort($topCentroList, static fn(array $a, array $b): int => ($b['total'] <=> $a['total']));

        $topFornecedorList = array_values($topFornecedor);
        usort($topFornecedorList, static fn(array $a, array $b): int => ($b['total'] <=> $a['total']));

        $maxNatureza = empty($topNaturezaList) ? 0 : max(array_column($topNaturezaList, 'total'));
        $maxCentro = empty($topCentroList) ? 0 : max(array_column($topCentroList, 'total'));
        $maxFornecedor = empty($topFornecedorList) ? 0 : max(array_column($topFornecedorList, 'total'));

        $naturezaFornecedoresResponse = [];
        foreach ($naturezaFornecedores as $natureza => $fornecedores) {
            $lista = array_values($fornecedores);
            usort($lista, static fn(array $a, array $b): int => ($b['total'] <=> $a['total']));
            $totalNatureza = $topNatureza[$natureza]['total'] ?? 0.0;
            foreach ($lista as &$f) {
                $f['percent'] = $totalNatureza > 0 ? round(($f['total'] / $totalNatureza) * 100, 1) : 0.0;
            }
            unset($f);
            $naturezaFornecedoresResponse[$natureza] = [
                'natureza' => $natureza,
                'total' => $totalNatureza,
                'fornecedores' => $lista,
            ];
        }

        $centroFornecedoresResponse = [];
        foreach ($centroFornecedores as $centro => $fornecedores) {
            $lista = array_values($fornecedores);
            usort($lista, static fn(array $a, array $b): int => ($b['total'] <=> $a['total']));
            $totalCentro = $topCentro[$centro]['total'] ?? 0.0;
            foreach ($lista as &$f) {
                $f['percent'] = $totalCentro > 0 ? round(($f['total'] / $totalCentro) * 100, 1) : 0.0;
            }
            unset($f);
            $centroFornecedoresResponse[$centro] = [
                'centro' => $centro,
                'total' => $totalCentro,
                'fornecedores' => $lista,
            ];
        }

        $sumProx3 = array_sum(array_map(static fn($r) => (float) ($r['VL_PARCELA'] ?? $r['VALOR'] ?? 0), $proximos3));
        $sumProx7 = array_sum(array_map(static fn($r) => (float) ($r['VL_PARCELA'] ?? $r['VALOR'] ?? 0), $proximos7));
        $sumProx15 = array_sum(array_map(static fn($r) => (float) ($r['VL_PARCELA'] ?? $r['VALOR'] ?? 0), $proximos15));

        return [
            'data_base' => $dataBase,
            'periodo' => [
                'from' => $from,
                'to' => $to,
                'tipo_data' => $dataBase,
            ],
            'resumo' => [
                'total_qtd' => $totalQtd,
                'total_valor' => $totalValor,
                'proximos_3_dias' => $sumProx3,
                'proximos_7_dias' => $sumProx7,
                'proximos_15_dias' => $sumProx15,
            ],
            'rankings' => [
                'centro_custo' => $topCentroList,
                'natureza' => $topNaturezaList,
                'fornecedor' => $topFornecedorList,
                'max_centro' => $maxCentro,
                'max_natureza' => $maxNatureza,
                'max_fornecedor' => $maxFornecedor,
            ],
            'centro_fornecedores' => $centroFornecedoresResponse,
            'natureza_fornecedores' => $naturezaFornecedoresResponse,
            'titulos_por_fornecedor' => $titulosPorFornecedor,
            'proximos' => [
                '3_dias' => ['items' => $proximos3, 'total' => $sumProx3],
                '7_dias' => ['items' => $proximos7, 'total' => $sumProx7],
                '15_dias' => ['items' => $proximos15, 'total' => $sumProx15],
            ],
        ];
    }

    private static function buildComexImportacoesDataset(array $params): array
    {
        if (!defined('PIPE_ID_COMEX')) {
            require_once APP_ROOT . '/app/config/config-pipefy.php';
        }

        $force = !empty($params['force']);
        $max = self::clampInt($params['max'] ?? 500, 50, 2000);
        $cacheDir = APP_ROOT . '/api/cache';
        $cacheFile = $cacheDir . '/comex-importacoes-agent.json';
        if (!$force && is_file($cacheFile) && (time() - (int) filemtime($cacheFile) < 300)) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $query = <<<'GQL'
query($pipeId: ID!, $first: Int!, $after: String) {
  cards(pipe_id: $pipeId, first: $first, after: $after) {
    pageInfo { hasNextPage endCursor }
    edges {
      node {
        id
        title
        current_phase { name }
        fields { name value }
      }
    }
  }
}
GQL;

        $items = [];
        $after = null;
        $pageInfo = ['hasNextPage' => false, 'endCursor' => null];

        while (count($items) < $max) {
            $data = self::pipefyGraphql($query, [
                'pipeId' => (string) PIPE_ID_COMEX,
                'first' => 50,
                'after' => $after,
            ]);
            $edges = $data['cards']['edges'] ?? [];
            $pageInfo = $data['cards']['pageInfo'] ?? $pageInfo;

            foreach ($edges as $edge) {
                $node = $edge['node'] ?? [];
                $map = self::pipefyFieldMap($node['fields'] ?? []);
                $etd = self::pipefyGetField($map, 'ETD (Previsão de saída)');
                $eta = self::pipefyGetField($map, 'ETA  (Previsão de chegada no porto)');
                $etaTs = $eta ? strtotime($eta . ' 23:59:59') : false;
                $items[] = [
                    'card_id' => $node['id'] ?? null,
                    'container' => $node['title'] ?? null,
                    'fase' => $node['current_phase']['name'] ?? null,
                    'previsao_embarque_etd' => $etd,
                    'previsao_entrega_eta' => $eta,
                    'atrasada' => $etaTs ? ($etaTs < strtotime('today')) : false,
                    'proximos_30_dias' => $etaTs ? ($etaTs >= strtotime('today') && $etaTs <= strtotime('+30 days')) : false,
                ];
                if (count($items) >= $max) {
                    break;
                }
            }

            if (empty($pageInfo['hasNextPage']) || empty($pageInfo['endCursor'])) {
                break;
            }
            $after = $pageInfo['endCursor'];
        }

        $atrasadas = count(array_filter($items, static fn(array $item): bool => !empty($item['atrasada'])));
        $next30 = count(array_filter($items, static fn(array $item): bool => !empty($item['proximos_30_dias'])));
        $payload = [
            'ok' => true,
            'pipe_id' => PIPE_ID_COMEX,
            'total' => count($items),
            'pageInfo' => $pageInfo,
            'items' => $items,
            'kpis' => [
                'total' => count($items),
                'proximos_30_dias' => $next30,
                'atrasadas' => $atrasadas,
            ],
            'cached_at' => date('c'),
        ];

        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        @file_put_contents($cacheFile, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $payload;
    }

    private static function buildCadastroDataset(array $params): array
    {
        $cadRows = self::fetchConsultaRows('000073', !empty($params['force']));
        $fat = self::buildFaturamentoDataset($params);
        $inad = self::buildInadimplenciaDataset($params);

        $inadPorKey = [];
        foreach ($inad['clientes_filtrados'] as $cliente) {
            $inadPorKey[(string) $cliente['cliente_key']] = $cliente;
        }

        $clientes = [];
        foreach ($cadRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $codigo = trim((string) self::pickFirst($row, ['A1_COD', 'COD_CLIENTE', 'CLIENTE'], ''));
            $loja = trim((string) self::pickFirst($row, ['A1_LOJA', 'LOJA_CLIENTE', 'LOJA'], ''));
            if ($codigo === '' || $loja === '') {
                continue;
            }

            $key = self::clienteKey($codigo, $loja);
            $clientes[$key] = [
                'cliente' => $codigo,
                'loja' => $loja,
                'cliente_key' => $key,
                'nome' => trim((string) self::pickFirst($row, ['A1_NOME', 'CLIENTE', 'NOME'], '')),
                'nome_fantasia' => trim((string) self::pickFirst($row, ['A1_NREDUZ', 'FANTASIA'], '')),
                'documento' => self::onlyDigits((string) self::pickFirst($row, ['A1_CGC', 'CGC', 'CNPJ', 'CPF'], '')),
                'cidade' => trim((string) self::pickFirst($row, ['A1_MUN', 'MUNICIPIO', 'CIDADE'], '')),
                'uf' => strtoupper(trim((string) self::pickFirst($row, ['A1_EST', 'UF', 'ESTADO'], ''))),
                'vendedor_codigo' => trim((string) self::pickFirst($row, ['A1_VEND', 'COD_VENDEDOR', 'VENDEDOR_CODIGO'], '')),
                'vendedor_nome' => trim((string) self::pickFirst($row, ['A3_NOME', 'VENDEDOR', 'VENDEDOR_NOME'], '')),
                'supervisor_codigo' => trim((string) self::pickFirst($row, ['A3_SUPER', 'SUPER', 'SUPERVISOR'], '')),
                'supervisor_nome' => trim((string) self::pickFirst($row, ['SUPERVISOR_NOME', 'A3_SUPER_NOME'], '')),
                'inad_total' => 0.0,
                'inad_qtd_titulos' => 0,
                'ultima_compra' => $fat['ultimas_compras'][$key] ?? null,
            ];

            if (isset($inadPorKey[$key])) {
                $clientes[$key]['inad_total'] = (float) ($inadPorKey[$key]['inad_total'] ?? 0.0);
                $clientes[$key]['inad_qtd_titulos'] = (int) ($inadPorKey[$key]['inad_qtd_titulos'] ?? 0);

                if ($clientes[$key]['vendedor_codigo'] === '') {
                    $clientes[$key]['vendedor_codigo'] = (string) ($inadPorKey[$key]['vendedor_codigo'] ?? '');
                }
                if ($clientes[$key]['vendedor_nome'] === '') {
                    $clientes[$key]['vendedor_nome'] = (string) ($inadPorKey[$key]['vendedor_nome'] ?? '');
                }
                if ($clientes[$key]['supervisor_codigo'] === '') {
                    $clientes[$key]['supervisor_codigo'] = (string) ($inadPorKey[$key]['supervisor_codigo'] ?? '');
                }
                if ($clientes[$key]['supervisor_nome'] === '') {
                    $clientes[$key]['supervisor_nome'] = (string) ($inadPorKey[$key]['supervisor_nome'] ?? '');
                }
            }

            if (($clientes[$key]['vendedor_codigo'] === '' || $clientes[$key]['vendedor_nome'] === '') && isset($fat['vendedores_por_cliente'][$key])) {
                $vendedorFat = $fat['vendedores_por_cliente'][$key];
                if ($clientes[$key]['vendedor_codigo'] === '') {
                    $clientes[$key]['vendedor_codigo'] = (string) ($vendedorFat['vendedor_codigo'] ?? '');
                }
                if ($clientes[$key]['vendedor_nome'] === '') {
                    $clientes[$key]['vendedor_nome'] = (string) ($vendedorFat['vendedor_nome'] ?? '');
                }
                if ($clientes[$key]['supervisor_codigo'] === '') {
                    $clientes[$key]['supervisor_codigo'] = (string) ($vendedorFat['supervisor_codigo'] ?? '');
                }
                if ($clientes[$key]['supervisor_nome'] === '') {
                    $clientes[$key]['supervisor_nome'] = (string) ($vendedorFat['supervisor_nome'] ?? '');
                }
            }
        }

        return [
            'clientes' => array_values($clientes),
        ];
    }

    private static function buildFaturamentoDataset(array $params): array
    {
        $rows = self::fetchConsultaRows('000070', !empty($params['force']));
        $ultimasCompras = [];
        $vendedoresPorCliente = [];
        $historicoPorCliente = [];
        $rankingVendedores = [];
        $rankingSupervisores = [];
        [$fromTs, $toTs] = self::resolvePeriodoFaturamento($params);
        $dateFrom = $fromTs ? date('Y-m-d', $fromTs) : '';
        $dateTo = $toTs ? date('Y-m-d', $toTs) : '';
        $dateFromCmp = str_replace('-', '', $dateFrom);
        $dateToCmp = str_replace('-', '', $dateTo);
        $search = self::normalizeText((string) ($params['search'] ?? ''));
        $filterVend = trim((string) ($params['vendedor'] ?? ''));
        $filterSuper = trim((string) ($params['supervisor'] ?? ''));
        $valorMin = max(0.0, self::toFloatBr($params['valor_min'] ?? 0));

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $codigo = trim((string) self::pickFirst($row, ['C5_CLIENTE', 'COD_CLIENTE', 'A1_COD'], ''));
            $loja = trim((string) self::pickFirst($row, ['C5_LOJACLI', 'C5_LOJA', 'LOJA_CLIENTE', 'LOJA'], ''));
            if ($codigo === '') {
                continue;
            }

            $key = self::clienteKey($codigo, $loja);
            $dataEmissao = self::onlyDigits((string) ($row['EMISAO'] ?? ''));
            if ($dataEmissao === '' && isset($row['C5_EMISSAO'])) {
                $dataEmissao = self::onlyDigits((string) $row['C5_EMISSAO']);
            }
            if ($dataEmissao === '' && isset($row['D2_EMISSAO'])) {
                $dataEmissao = self::onlyDigits((string) $row['D2_EMISSAO']);
            }
            $tsEmissao = self::parseYmd($dataEmissao);
            if ($dataEmissao === '' || ($dateFromCmp !== '' && $dataEmissao < $dateFromCmp) || ($dateToCmp !== '' && $dataEmissao > $dateToCmp)) {
                continue;
            }
            $valor = self::toFloatBr(self::pickFirst($row, ['VALOR', 'TOTAL', 'D2_TOTAL', 'VALOR_TOTAL', 'VALOR_PEDIDO'], 0));
            $custo = self::toFloatBr(self::pickFirst($row, ['CUSTO', 'D2_CUSTO', 'CUSTO_TOTAL'], 0));
            if ($valor <= 0) {
                continue;
            }

            $filialCodigo = self::resolveFilialCode($row);
            $filialNome = self::resolveFilialName($filialCodigo, $row);
            $produtoCodigo = trim((string) self::pickFirst($row, ['CODIGO', 'B1_COD', 'PRODUTO_COD', 'D2_COD'], ''));
            $produtoNome = trim((string) self::pickFirst($row, ['PRODUTO', 'PROD_DESC', 'DESCRICAO', 'B1_DESC', 'ITEM_DESC', 'C6_DESCRI'], ''));
            $quantidade = self::toFloatBr(self::pickFirst($row, ['QTDE', 'D2_QUANT'], 0));
            $valorUnitario = self::toFloatBr(self::pickFirst($row, ['PRECO_PRATICADO', 'D2_PRCVEN', 'PRECO_TABELA', 'D2_PRUNIT'], 0));

            $item = [
                'data' => $dataEmissao,
                'data_fmt' => self::formatDateBr($dataEmissao),
                'nf' => trim((string) ($row['NF'] ?? '')),
                'pedido' => trim((string) self::pickFirst($row, ['PEDIDO', 'C5_NUM', 'NUM_PEDIDO'], '')),
                'valor' => $valor,
                'custo' => $custo,
                'filial_codigo' => $filialCodigo,
                'filial_nome' => $filialNome,
                'cliente' => trim((string) self::pickFirst($row, ['CLIENTE', 'A1_NOME', 'NOME'], '')),
                'documento' => self::onlyDigits((string) self::pickFirst($row, ['A1_CGC', 'CGC', 'CNPJ', 'CPF'], '')),
                'vendedor_codigo' => trim((string) self::pickFirst($row, ['E1_VEND1', 'A1_VEND', 'COD_VENDEDOR', 'VENDEDOR_CODIGO'], '')),
                'vendedor_nome' => trim((string) self::pickFirst($row, ['A3_NOME', 'VENDEDOR', 'VENDEDOR_NOME'], '')),
                'supervisor_codigo' => trim((string) self::pickFirst($row, ['A3_SUPER', 'SUPER', 'SUPERVISOR'], '')),
                'supervisor_nome' => trim((string) self::pickFirst($row, ['SUPERVISOR_NOME', 'A3_SUPER_NOME'], '')),
                'produto_codigo' => $produtoCodigo,
                'produto_nome' => $produtoNome,
                'quantidade' => round($quantidade, 2),
                'valor_unitario' => round($valorUnitario, 2),
            ];

            if (!isset($ultimasCompras[$key]) || ($tsEmissao !== null && $tsEmissao > (int) ($ultimasCompras[$key]['ts'] ?? 0))) {
                $item['ts'] = $tsEmissao ?? 0;
                $ultimasCompras[$key] = $item;
            }

            if (!isset($vendedoresPorCliente[$key])) {
                $vendedoresPorCliente[$key] = [
                    'vendedor_codigo' => $item['vendedor_codigo'],
                    'vendedor_nome' => $item['vendedor_nome'],
                    'supervisor_codigo' => $item['supervisor_codigo'],
                    'supervisor_nome' => $item['supervisor_nome'],
                ];
            }

            if (!isset($historicoPorCliente[$key])) {
                $historicoPorCliente[$key] = [
                    'cliente' => $codigo,
                    'loja' => $loja,
                    'cliente_key' => $key,
                    'nome' => $item['cliente'],
                    'documento' => $item['documento'],
                    'vendedor_codigo' => $item['vendedor_codigo'],
                    'vendedor_nome' => $item['vendedor_nome'],
                    'supervisor_codigo' => $item['supervisor_codigo'],
                    'supervisor_nome' => $item['supervisor_nome'],
                    'filial_codigo' => $filialCodigo,
                    'filial_nome' => $filialNome,
                    'faturamento_total' => 0.0,
                    'qtd_pedidos' => 0,
                    'nfs' => [],
                    'pedidos' => [],
                    'filiais' => [],
                    'primeira_compra' => null,
                    'ultima_compra' => null,
                    'compras' => [],
                ];
            }

            $historicoPorCliente[$key]['faturamento_total'] += $valor;
            if ($filialCodigo !== '' || $filialNome !== '') {
                $filialKey = $filialCodigo !== '' ? $filialCodigo : $filialNome;
                if (!isset($historicoPorCliente[$key]['filiais'][$filialKey])) {
                    $historicoPorCliente[$key]['filiais'][$filialKey] = [
                        'codigo' => $filialCodigo,
                        'nome' => $filialNome !== '' ? $filialNome : $filialCodigo,
                        'valor' => 0.0,
                    ];
                }
                $historicoPorCliente[$key]['filiais'][$filialKey]['valor'] += $valor;
            }
            if ($item['pedido'] !== '') {
                $historicoPorCliente[$key]['pedidos'][$item['pedido']] = true;
            }
            if ($item['nf'] !== '') {
                $historicoPorCliente[$key]['nfs'][$item['nf']] = true;
            }
            $historicoPorCliente[$key]['compras'][] = $item;

            if ($historicoPorCliente[$key]['primeira_compra'] === null || (($tsEmissao ?? PHP_INT_MAX) < (int) ($historicoPorCliente[$key]['primeira_compra']['ts'] ?? PHP_INT_MAX))) {
                $historicoPorCliente[$key]['primeira_compra'] = $item + ['ts' => $tsEmissao ?? 0];
            }
            if ($historicoPorCliente[$key]['ultima_compra'] === null || (($tsEmissao ?? 0) > (int) ($historicoPorCliente[$key]['ultima_compra']['ts'] ?? 0))) {
                $historicoPorCliente[$key]['ultima_compra'] = $item + ['ts' => $tsEmissao ?? 0];
            }

            $vendedorKey = $item['vendedor_codigo'] !== '' ? $item['vendedor_codigo'] : ($item['vendedor_nome'] !== '' ? $item['vendedor_nome'] : 'SEM_VENDEDOR');
            if (!isset($rankingVendedores[$vendedorKey])) {
                $rankingVendedores[$vendedorKey] = [
                    'vendedor_codigo' => $item['vendedor_codigo'],
                    'vendedor_nome' => $item['vendedor_nome'] !== '' ? $item['vendedor_nome'] : 'Sem vendedor',
                    'supervisor_codigo' => $item['supervisor_codigo'],
                    'supervisor_nome' => $item['supervisor_nome'],
                    'faturamento_total' => 0.0,
                    'clientes' => [],
                    'nfs' => [],
                ];
            }
            $rankingVendedores[$vendedorKey]['faturamento_total'] += $valor;
            $rankingVendedores[$vendedorKey]['clientes'][$key] = true;
            if ($item['nf'] !== '') {
                $rankingVendedores[$vendedorKey]['nfs'][$item['nf']] = true;
            }

            $supervisorKey = $item['supervisor_codigo'] !== '' ? $item['supervisor_codigo'] : ($item['supervisor_nome'] !== '' ? $item['supervisor_nome'] : 'SEM_SUPERVISOR');
            if (!isset($rankingSupervisores[$supervisorKey])) {
                $rankingSupervisores[$supervisorKey] = [
                    'supervisor_codigo' => $item['supervisor_codigo'],
                    'supervisor_nome' => $item['supervisor_nome'] !== '' ? $item['supervisor_nome'] : 'Sem supervisor',
                    'faturamento_total' => 0.0,
                    'clientes' => [],
                    'nfs' => [],
                ];
            }
            $rankingSupervisores[$supervisorKey]['faturamento_total'] += $valor;
            $rankingSupervisores[$supervisorKey]['clientes'][$key] = true;
            if ($item['nf'] !== '') {
                $rankingSupervisores[$supervisorKey]['nfs'][$item['nf']] = true;
            }
        }

        foreach ($ultimasCompras as $key => $item) {
            unset($ultimasCompras[$key]['ts']);
        }

        foreach ($historicoPorCliente as $key => $cliente) {
            usort($historicoPorCliente[$key]['compras'], static function (array $a, array $b): int {
                return strcmp((string) ($b['data'] ?? ''), (string) ($a['data'] ?? ''));
            });
            $historicoPorCliente[$key]['qtd_pedidos'] = count($historicoPorCliente[$key]['pedidos']);
            $historicoPorCliente[$key]['qtd_nfs'] = count($historicoPorCliente[$key]['nfs']);
            $historicoPorCliente[$key]['ticket_medio'] = $historicoPorCliente[$key]['qtd_pedidos'] > 0
                ? round($historicoPorCliente[$key]['faturamento_total'] / $historicoPorCliente[$key]['qtd_pedidos'], 2)
                : 0.0;

            $filiaisCliente = array_values($historicoPorCliente[$key]['filiais']);
            usort($filiaisCliente, static fn(array $a, array $b): int => (($b['valor'] ?? 0.0) <=> ($a['valor'] ?? 0.0)));
            $filialPrincipal = $filiaisCliente[0] ?? ['codigo' => '', 'nome' => '', 'valor' => 0.0];
            $historicoPorCliente[$key]['filial_codigo'] = (string) ($filialPrincipal['codigo'] ?? '');
            $historicoPorCliente[$key]['filial_nome'] = (string) ($filialPrincipal['nome'] ?? '');

            unset($historicoPorCliente[$key]['pedidos'], $historicoPorCliente[$key]['nfs'], $historicoPorCliente[$key]['filiais']);
            if (isset($historicoPorCliente[$key]['primeira_compra']['ts'])) {
                unset($historicoPorCliente[$key]['primeira_compra']['ts']);
            }
            if (isset($historicoPorCliente[$key]['ultima_compra']['ts'])) {
                unset($historicoPorCliente[$key]['ultima_compra']['ts']);
            }
        }

        foreach ($rankingVendedores as $key => $item) {
            $rankingVendedores[$key]['clientes'] = count($item['clientes']);
            $rankingVendedores[$key]['qtd_nfs'] = count($item['nfs']);
            unset($rankingVendedores[$key]['nfs']);
        }

        foreach ($rankingSupervisores as $key => $item) {
            $rankingSupervisores[$key]['clientes'] = count($item['clientes']);
            $rankingSupervisores[$key]['qtd_nfs'] = count($item['nfs']);
            unset($rankingSupervisores[$key]['nfs']);
        }

        $clientesFiltrados = array_values(array_filter($historicoPorCliente, static function (array $cliente) use ($search, $filterVend, $filterSuper, $valorMin): bool {
            if ((float) ($cliente['faturamento_total'] ?? 0.0) < $valorMin) {
                return false;
            }

            if ($filterVend !== '' && !self::textMatchesAny($filterVend, [
                (string) ($cliente['vendedor_codigo'] ?? ''),
                (string) ($cliente['vendedor_nome'] ?? ''),
            ])) {
                return false;
            }

            if ($filterSuper !== '' && !self::textMatchesAny($filterSuper, [
                (string) ($cliente['supervisor_codigo'] ?? ''),
                (string) ($cliente['supervisor_nome'] ?? ''),
            ])) {
                return false;
            }

            if ($search !== '' && !self::normalizedContainsAny($search, [
                (string) ($cliente['cliente'] ?? ''),
                (string) ($cliente['loja'] ?? ''),
                (string) ($cliente['nome'] ?? ''),
                (string) ($cliente['documento'] ?? ''),
                (string) ($cliente['vendedor_nome'] ?? ''),
                (string) ($cliente['supervisor_nome'] ?? ''),
            ])) {
                return false;
            }

            return true;
        }));

        $clienteKeysPermitidos = [];
        foreach ($clientesFiltrados as $cliente) {
            $clienteKeysPermitidos[(string) ($cliente['cliente_key'] ?? '')] = true;
        }

        $rankingVendedores = array_filter($rankingVendedores, static function (array $item) use ($filterSuper, $search): bool {
            if ($filterSuper !== '' && !self::textMatchesAny($filterSuper, [
                (string) ($item['supervisor_codigo'] ?? ''),
                (string) ($item['supervisor_nome'] ?? ''),
            ])) {
                return false;
            }

            if ($search !== '' && !self::normalizedContainsAny($search, [
                (string) ($item['vendedor_codigo'] ?? ''),
                (string) ($item['vendedor_nome'] ?? ''),
                (string) ($item['supervisor_nome'] ?? ''),
            ])) {
                return false;
            }

            return true;
        });

        $rankingSupervisores = array_filter($rankingSupervisores, static function (array $item) use ($search): bool {
            if ($search !== '' && !self::normalizedContainsAny($search, [
                (string) ($item['supervisor_codigo'] ?? ''),
                (string) ($item['supervisor_nome'] ?? ''),
            ])) {
                return false;
            }

            return true;
        });

        return [
            'ultimas_compras' => $ultimasCompras,
            'vendedores_por_cliente' => $vendedoresPorCliente,
            'historico_por_cliente' => $historicoPorCliente,
            'clientes_filtrados' => $clientesFiltrados,
            'ranking_vendedores' => $rankingVendedores,
            'ranking_supervisores' => $rankingSupervisores,
            'filtros' => [
                'date_from' => $fromTs ? date('Y-m-d', $fromTs) : null,
                'date_to' => $toTs ? date('Y-m-d', $toTs) : null,
                'months' => $params['months'] ?? null,
                'search' => (string) ($params['search'] ?? ''),
                'vendedor' => $filterVend,
                'supervisor' => $filterSuper,
                'valor_min' => $valorMin,
            ],
        ];
    }

    private static function resolveCliente(array $params): array
    {
        $cadastro = self::buildCadastroDataset($params);
        $clientes = $cadastro['clientes'];

        $cliente = trim((string) ($params['cliente'] ?? ''));
        $loja = trim((string) ($params['loja'] ?? ''));
        $documento = self::onlyDigits((string) ($params['documento'] ?? ''));
        $nome = trim((string) ($params['nome'] ?? ''));

        $matches = array_values(array_filter($clientes, static function (array $item) use ($cliente, $loja, $documento, $nome): bool {
            if ($cliente !== '' && $loja !== '') {
                return (string) ($item['cliente'] ?? '') === $cliente && (string) ($item['loja'] ?? '') === $loja;
            }

            if ($documento !== '' && (string) ($item['documento'] ?? '') === $documento) {
                return true;
            }

            if ($nome !== '') {
                return self::normalizedContainsAny(self::normalizeText($nome), [
                    (string) ($item['nome'] ?? ''),
                    (string) ($item['nome_fantasia'] ?? ''),
                ]);
            }

            if ($cliente !== '') {
                return (string) ($item['cliente'] ?? '') === $cliente;
            }

            return false;
        }));

        if ($matches === []) {
            throw new RuntimeException('Cliente nao encontrado.');
        }

        if (count($matches) > 1 && ($cliente === '' || $loja === '') && $documento === '') {
            throw new RuntimeException('Mais de um cliente encontrado. Informe codigo e loja ou documento.');
        }

        return $matches[0];
    }

    private static function fetchConsultaRows(string $consulta, bool $force = false): array
    {
        $cacheDir = APP_ROOT . '/app/cache/totvs-agent';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        $cacheFile = $cacheDir . '/consulta-' . preg_replace('/\D+/', '', $consulta) . '.json';
        if (!$force && is_file($cacheFile) && (time() - (int) @filemtime($cacheFile) < self::CACHE_TTL_SECONDS)) {
            $cached = json_decode((string) @file_get_contents($cacheFile), true);
            if (is_array($cached) && isset($cached['items']) && is_array($cached['items'])) {
                return $cached['items'];
            }
        }

        $resp = callTotvsApi($consulta);
        if (!($resp['success'] ?? false) || !is_array($resp['data'] ?? null)) {
            if (is_file($cacheFile)) {
                $cached = json_decode((string) @file_get_contents($cacheFile), true);
                if (is_array($cached) && isset($cached['items']) && is_array($cached['items'])) {
                    return $cached['items'];
                }
            }

            $erro = (string) ($resp['json_error'] ?? ($resp['info']['error'] ?? 'Falha ao consultar TOTVS.'));
            throw new RuntimeException('Falha ao consultar TOTVS ' . $consulta . ': ' . $erro);
        }

        $items = self::extractItems($resp['data']);
        @file_put_contents($cacheFile, json_encode([
            'consulta' => $consulta,
            'updated_at' => date('c'),
            'items' => $items,
        ], JSON_UNESCAPED_UNICODE));

        return $items;
    }

    private static function extractItems($data): array
    {
        if (!is_array($data)) {
            return [];
        }

        if (array_is_list($data)) {
            return $data;
        }

        if (isset($data['items']) && is_array($data['items'])) {
            return $data['items'];
        }

        if (isset($data['value']) && is_array($data['value'])) {
            return $data['value'];
        }

        foreach ($data as $value) {
            if (is_array($value) && array_is_list($value)) {
                return $value;
            }
        }

        return [];
    }

    private static function pickFirst(array $row, array $keys, $default = '')
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
        }

        return $default;
    }

    private static function clienteKey(string $cliente, string $loja): string
    {
        return trim($cliente) . '|' . trim($loja);
    }

    private static function resolveFilialCode(array $row): string
    {
        $code = trim((string) self::pickFirst($row, ['FILIAL', 'FILIAL_CODIGO', 'COD_FILIAL', 'D2_FILIAL', 'F2_FILIAL', 'C5_FILIAL', 'EMPRESA_FILIAL'], ''));
        if ($code === '') {
            return '';
        }

        $digits = self::onlyDigits($code);
        if (strlen($digits) >= 6) {
            return substr($digits, 0, 6);
        }

        return $code;
    }

    private static function resolveFilialName(string $code, array $row = []): string
    {
        $code = trim($code);
        if ($code !== '' && isset(self::FILIAIS[$code])) {
            return self::FILIAIS[$code];
        }

        return trim((string) self::pickFirst($row, ['FILIAL_NOME', 'NOME_FILIAL', 'EMPRESA_NOME'], ''));
    }

    private static function appendFilialBreakdown(array &$agg, string $code, string $name, float $valor): void
    {
        $code = trim($code);
        $name = trim($name);
        $key = $code !== '' ? $code : ($name !== '' ? $name : 'SEM_FILIAL');
        if (!isset($agg[$key])) {
            $agg[$key] = [
                'codigo' => $code,
                'nome' => $name !== '' ? $name : ($code !== '' ? $code : 'Sem filial'),
                'valor' => 0.0,
            ];
        }

        $agg[$key]['valor'] += $valor;
    }

    private static function finalizeFilialBreakdown(array $agg): array
    {
        $items = array_values($agg);
        usort($items, static fn(array $a, array $b): int => (($b['valor'] ?? 0.0) <=> ($a['valor'] ?? 0.0)));
        $total = 0.0;
        foreach ($items as $item) {
            $total += (float) ($item['valor'] ?? 0.0);
        }

        foreach ($items as &$item) {
            $valor = round((float) ($item['valor'] ?? 0.0), 2);
            $item['valor'] = $valor;
            $item['percentual'] = $total > 0 ? round(($valor / $total) * 100, 2) : 0.0;
        }
        unset($item);

        return $items;
    }

    private static function clampInt($value, int $min, int $max): int
    {
        $int = (int) $value;
        if ($int < $min) {
            return $min;
        }
        if ($int > $max) {
            return $max;
        }
        return $int;
    }

    private static function resolveLimit($value, int $default, int $max): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $int = (int) $value;
        if ($int <= 0) {
            return 0;
        }

        return min($int, $max);
    }

    private static function limitItems(array $items, int $limit): array
    {
        if ($limit <= 0) {
            return array_values($items);
        }

        return array_slice(array_values($items), 0, $limit);
    }

    private static function boolParam($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = self::normalizeText((string) $value);
        return in_array($normalized, ['1', 'true', 'sim', 'yes', 'y', 'on'], true);
    }

    private static function onlyDigits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private static function parseYmd(?string $ymd): ?int
    {
        $ymd = self::onlyDigits((string) $ymd);
        if (strlen($ymd) !== 8) {
            return null;
        }

        $y = (int) substr($ymd, 0, 4);
        $m = (int) substr($ymd, 4, 2);
        $d = (int) substr($ymd, 6, 2);

        if (!checkdate($m, $d, $y)) {
            return null;
        }

        return strtotime(sprintf('%04d-%02d-%02d 00:00:00', $y, $m, $d)) ?: null;
    }

    private static function resolvePeriodoFaturamento(array $params): array
    {
        $today = strtotime(date('Y-m-d 23:59:59'));
        $months = self::clampInt($params['months'] ?? 6, 1, 36);
        $from = null;
        $to = null;

        if (!empty($params['date_from'])) {
            $from = strtotime((string) $params['date_from'] . ' 00:00:00') ?: null;
        }
        if (!empty($params['date_to'])) {
            $to = strtotime((string) $params['date_to'] . ' 23:59:59') ?: null;
        }

        if ($from === null && $to === null) {
            $from = strtotime(date('Y-m-01 00:00:00', strtotime('-' . ($months - 1) . ' months')));
            $to = $today;
        } elseif ($from === null && $to !== null) {
            $from = strtotime(date('Y-m-01 00:00:00', strtotime('-' . ($months - 1) . ' months', $to)));
        } elseif ($from !== null && $to === null) {
            $to = $today;
        }

        if ($from !== null && $to !== null && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    private static function buildFaturamentoDiarioEmpresa(array $params): array
    {
        $rawDate = trim((string) ($params['date'] ?? ''));
        $date = self::normalizeAgentDate($rawDate);
        if ($rawDate !== '' && $date === null) {
            throw new InvalidArgumentException('Informe a data no formato YYYY-MM-DD.');
        }
        if ($date === null) {
            $date = date('Y-m-d');
        }

        $targetYmd = str_replace('-', '', $date);
        $rows = self::fetchConsultaRows('000070', !empty($params['force']));
        $valorTotvs = 0.0;
        $qtdNfsTotvs = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $emissao = self::onlyDigits((string) self::pickFirst($row, ['EMISAO', 'C5_EMISSAO', 'D2_EMISSAO'], ''));
            if ($emissao !== $targetYmd) {
                continue;
            }

            $valorItem = self::toFloatBr(self::pickFirst($row, ['VALOR', 'VALOR_PEDIDO', 'VALOR_TOTAL', 'TOTAL'], 0));
            if ($valorItem <= 0) {
                continue;
            }

            $valorTotvs += $valorItem;
            $qtdNfsTotvs++;
        }

        $ajuste = self::fetchDashboardAjustesByDateRange('executivo', $date, $date);
        $valorAjuste = (float) ($ajuste['valor_periodo'] ?? 0.0);
        $qtdNfsAjuste = 0;
        $valorFinal = $valorTotvs + $valorAjuste;
        $qtdNfsFinal = $qtdNfsTotvs + $qtdNfsAjuste;

        return [
            'date' => $date,
            'valor_totvs' => round($valorTotvs, 2),
            'valor_ajuste_manual' => round($valorAjuste, 2),
            'valor_total' => round($valorFinal, 2),
            'valor' => round($valorFinal, 2),
            'qtd_nfs_totvs' => $qtdNfsTotvs,
            'qtd_nfs_ajuste_manual' => $qtdNfsAjuste,
            'qtd_nfs' => $qtdNfsFinal,
            'updated_at' => date('d/m/Y, H:i'),
        ];
    }

    private static function buildFilialBreakdownHoje(array $params, string $date): array
    {
        $targetYmd = str_replace('-', '', $date);
        $rows = self::fetchConsultaRows('000070', !empty($params['force']));
        $agg = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $emissao = self::onlyDigits((string) self::pickFirst($row, ['EMISAO', 'C5_EMISSAO', 'D2_EMISSAO'], ''));
            if ($emissao !== $targetYmd) {
                continue;
            }

            $valor = self::toFloatBr(self::pickFirst($row, ['VALOR', 'VALOR_PEDIDO', 'VALOR_TOTAL', 'TOTAL'], 0));
            if ($valor <= 0) {
                continue;
            }

            $codigo = self::resolveFilialCode($row);
            $nome = self::resolveFilialName($codigo, $row);
            self::appendFilialBreakdown($agg, $codigo, $nome, $valor);
        }

        return self::finalizeFilialBreakdown($agg);
    }

    private static function buildFilialBreakdownResumo(array $params, string $periodo = 'mes'): array
    {
        $rows = self::fetchConsultaRows('000070', !empty($params['force']));
        $now = time();
        $from = $periodo === 'ano'
            ? strtotime(date('Y-01-01 00:00:00', $now))
            : strtotime(date('Y-m-01 00:00:00', $now));
        $to = strtotime(date('Y-m-d 23:59:59', $now));
        $agg = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $emissao = trim((string) self::pickFirst($row, ['EMISAO', 'C5_EMISSAO', 'D2_EMISSAO'], ''));
            $ts = self::parseYmd($emissao);
            if (!self::inRange($ts, $from, $to)) {
                continue;
            }

            $valor = self::toFloatBr(self::pickFirst($row, ['VALOR', 'VALOR_PEDIDO', 'VALOR_TOTAL', 'TOTAL'], 0));
            if ($valor <= 0) {
                continue;
            }

            $codigo = self::resolveFilialCode($row);
            $nome = self::resolveFilialName($codigo, $row);
            self::appendFilialBreakdown($agg, $codigo, $nome, $valor);
        }

        return self::finalizeFilialBreakdown($agg);
    }

    private static function buildHistoricoNotasComItens(array $compras, int $limit): array
    {
        $notas = [];
        foreach ($compras as $compra) {
            if (!is_array($compra)) {
                continue;
            }

            $notaKey = trim((string) ($compra['nf'] ?? ''));
            if ($notaKey === '') {
                $notaKey = trim((string) ($compra['pedido'] ?? ''));
            }
            if ($notaKey === '') {
                $notaKey = (string) (($compra['data'] ?? '') . '|' . ($compra['valor'] ?? '0'));
            }

            if (!isset($notas[$notaKey])) {
                $notas[$notaKey] = [
                    'nf' => (string) ($compra['nf'] ?? ''),
                    'filial' => (string) ($compra['filial_nome'] ?? ''),
                    'data' => (string) self::formatDateBr((string) ($compra['data'] ?? '')),
                    'data_ordem' => (string) ($compra['data'] ?? ''),
                    'valor_total' => 0.0,
                    'itens' => [],
                ];
            }

            $notas[$notaKey]['valor_total'] += round((float) ($compra['valor'] ?? 0.0), 2);

            if (($compra['produto_codigo'] ?? '') === '' && ($compra['produto_nome'] ?? '') === '') {
                continue;
            }

            $notas[$notaKey]['itens'][] = [
                'codigo' => (string) ($compra['produto_codigo'] ?? ''),
                'produto' => (string) ($compra['produto_nome'] ?? ''),
                'valor' => round((float) ($compra['valor'] ?? 0.0), 2),
                'custo' => round((float) ($compra['custo'] ?? 0.0), 2),
            ];
        }

        $notas = array_values($notas);
        usort($notas, static fn(array $a, array $b): int => strcmp(
            (string) ($b['data_ordem'] ?? ''),
            (string) ($a['data_ordem'] ?? '')
        ));
        foreach ($notas as &$nota) {
            $nota['valor_total'] = round((float) ($nota['valor_total'] ?? 0.0), 2);
            unset($nota['data_ordem']);
        }
        unset($nota);

        return self::limitItems($notas, $limit);
    }

    private static function normalizeAgentDate(string $date): ?string
    {
        $date = trim($date);
        if ($date === '') {
            return null;
        }

        if (preg_match('/^\d{8}$/', $date)) {
            $y = (int) substr($date, 0, 4);
            $m = (int) substr($date, 4, 2);
            $d = (int) substr($date, 6, 2);
            return checkdate($m, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $m, $d) : null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            [$y, $m, $d] = array_map('intval', explode('-', $date));
            return checkdate($m, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $m, $d) : null;
        }

        return null;
    }

    private static function fetchDashboardMetrics(string $dashboardSlug): array
    {
        $stmt = db()->prepare('
            SELECT metric_key, metric_value_num, metric_value_text
            FROM metrics
            WHERE dashboard_slug = ?
        ');
        $stmt->execute([$dashboardSlug]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $metrics = [];

        foreach ($rows as $row) {
            $key = (string) ($row['metric_key'] ?? '');
            if ($key === '') {
                continue;
            }
            if (($row['metric_value_text'] ?? null) !== null && (string) $row['metric_value_text'] !== '') {
                $metrics[$key] = $row['metric_value_text'];
            } else {
                $metrics[$key] = (float) ($row['metric_value_num'] ?? 0);
            }
        }

        try {
            $stmtMetaMes = db()->prepare('
                SELECT valor
                FROM dashboard_faturamento_metas_mensais
                WHERE dash_slug = ? AND ref_month = ? AND is_active = 1
                ORDER BY id DESC
                LIMIT 1
            ');
            $stmtMetaMes->execute([$dashboardSlug, date('Y-m-01')]);
            $metaMesAtual = $stmtMetaMes->fetchColumn();
            if ($metaMesAtual !== false && $metaMesAtual !== null) {
                $metrics['meta_mes'] = (float) $metaMesAtual;
            }
        } catch (Throwable $e) {
            // fallback para metrics.meta_mes
        }

        return $metrics;
    }

    private static function fetchExecutivoTotvs(array $params): array
    {
        $rows70 = self::fetchConsultaRows('000070', !empty($params['force']));
        $rows71 = self::fetchConsultaRows('000071', !empty($params['force']));
        $now = time();
        $fromMonth = strtotime(date('Y-m-01 00:00:00', $now));
        $fromYear = strtotime(date('Y-01-01 00:00:00', $now));
        $toToday = strtotime(date('Y-m-d 23:59:59', $now));
        $toMonthEnd = strtotime(date('Y-m-t 23:59:59', $now));
        $toYearEnd = strtotime(date('Y-12-31 23:59:59', $now));
        $todayStr = date('Ymd');

        $fatToday = 0.0;
        $fatMonth = 0.0;
        $fatYear = 0.0;
        foreach ($rows70 as $row) {
            if (!is_array($row)) {
                continue;
            }
            $emissao = trim((string) self::pickFirst($row, ['EMISAO', 'C5_EMISSAO'], ''));
            $ts = self::parseYmd($emissao);
            if ($ts === null) {
                continue;
            }
            $valor = self::toFloatBr(self::pickFirst($row, ['VALOR', 'VALOR_PEDIDO', 'VALOR_TOTAL'], 0));
            if (self::inRange($ts, $fromYear, $toToday)) {
                $fatYear += $valor;
            }
            if (self::inRange($ts, $fromMonth, $toToday)) {
                $fatMonth += $valor;
            }
            if ($emissao === $todayStr) {
                $fatToday += $valor;
            }
        }

        $imToday = 0.0;
        $imMonth = 0.0;
        $imYear = 0.0;
        $agToday = 0.0;
        $agMonth = 0.0;
        $agYear = 0.0;

        foreach ($rows71 as $row) {
            if (!is_array($row)) {
                continue;
            }
            $status = strtoupper(trim((string) ($row['C5_XSTATUS'] ?? '')));
            $isIM = $status === 'IM';
            $isAG = $status === 'AG';
            if (!$isIM && !$isAG) {
                continue;
            }

            $fecent = trim((string) ($row['C5_FECENT'] ?? ''));
            $tsFecent = self::parseYmd($fecent);
            if ($tsFecent === null) {
                continue;
            }

            $valor = self::toFloatBr(self::pickFirst($row, ['VALOR_PEDIDO', 'VALOR', 'VALOR_TOTAL'], 0));
            if ($isIM) {
                if (self::inRange($tsFecent, $fromMonth, $toMonthEnd)) {
                    $imMonth += $valor;
                }
                if ($fecent === $todayStr) {
                    $imToday += $valor;
                }
                if (self::inRange($tsFecent, $fromYear, $toYearEnd)) {
                    $imYear += $valor;
                }
            }
            if ($isAG) {
                if (self::inRange($tsFecent, $fromMonth, $toMonthEnd)) {
                    $agMonth += $valor;
                }
                if ($fecent === $todayStr) {
                    $agToday += $valor;
                }
                if (self::inRange($tsFecent, $fromYear, $toYearEnd)) {
                    $agYear += $valor;
                }
            }
        }

        return [
            'updated_at' => date('d/m/Y, H:i'),
            'values' => [
                'realizado_hoje' => round($fatToday + $imToday, 2),
                'realizado_ate_hoje' => round($fatMonth + $imMonth, 2),
                'realizado_ano_acum' => round($fatYear + $imYear, 2),
                'hoje_faturado' => round($fatToday, 2),
                'mes_faturado' => round($fatMonth, 2),
                'ano_faturado' => round($fatYear, 2),
                'hoje_im' => round($imToday, 2),
                'mes_im' => round($imMonth, 2),
                'ano_im' => round($imYear, 2),
                'hoje_ag' => round($agToday, 2),
                'mes_ag' => round($agMonth, 2),
                'ano_ag' => round($agYear, 2),
            ],
        ];
    }

    private static function fetchDashboardAjustes(string $dashboardSlug): array
    {
        $now = time();
        $todayYmd = date('Y-m-d', $now);
        $yearStart = date('Y-01-01', $now);
        $ajustes = self::fetchDashboardAjustesByDateRange($dashboardSlug, $yearStart, $todayYmd);

        return [
            'hoje' => (float) ($ajustes['valor_hoje'] ?? 0.0),
            'mes' => (float) ($ajustes['valor_mes'] ?? 0.0),
            'ano' => (float) ($ajustes['valor_periodo'] ?? 0.0),
        ];
    }

    private static function fetchDashboardAjustesByDateRange(string $dashboardSlug, string $dateFrom, string $dateTo): array
    {
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $monthStart = substr($dateTo, 0, 7) . '-01';
        $monthEnd = date('Y-m-t', strtotime($dateTo));
        $todayYmd = date('Y-m-d');

        $stmt = db()->prepare('
            SELECT ref_date, valor
            FROM dashboard_faturamento_ajustes
            WHERE dash_slug = ?
              AND is_active = 1
              AND ref_date BETWEEN ? AND ?
            ORDER BY ref_date ASC, id ASC
        ');
        $stmt->execute([$dashboardSlug, $dateFrom, $dateTo]);

        $rows = [];
        $valorPeriodo = 0.0;
        $valorMes = 0.0;
        $valorHoje = 0.0;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $refDate = (string) ($row['ref_date'] ?? '');
            $valor = (float) ($row['valor'] ?? 0.0);
            $rows[] = [
                'ref_date' => $refDate,
                'valor' => $valor,
            ];
            $valorPeriodo += $valor;
            if ($refDate >= $monthStart && $refDate <= $monthEnd) {
                $valorMes += $valor;
            }
            if ($refDate === $todayYmd) {
                $valorHoje += $valor;
            }
        }

        return [
            'rows' => $rows,
            'valor_periodo' => $valorPeriodo,
            'valor_periodo_total' => $valorPeriodo,
            'valor_mes' => $valorMes,
            'valor_hoje' => $valorHoje,
        ];
    }

    private static function ufToRegiao(string $uf): string
    {
        static $map = [
            'AC' => 'Norte', 'AP' => 'Norte', 'AM' => 'Norte', 'PA' => 'Norte', 'RO' => 'Norte', 'RR' => 'Norte', 'TO' => 'Norte',
            'AL' => 'Nordeste', 'BA' => 'Nordeste', 'CE' => 'Nordeste', 'MA' => 'Nordeste', 'PB' => 'Nordeste', 'PE' => 'Nordeste', 'PI' => 'Nordeste', 'RN' => 'Nordeste', 'SE' => 'Nordeste',
            'DF' => 'Centro-Oeste', 'GO' => 'Centro-Oeste', 'MT' => 'Centro-Oeste', 'MS' => 'Centro-Oeste',
            'ES' => 'Sudeste', 'MG' => 'Sudeste', 'RJ' => 'Sudeste', 'SP' => 'Sudeste',
            'PR' => 'Sul', 'RS' => 'Sul', 'SC' => 'Sul',
        ];
        return $map[$uf] ?? 'Nao informado';
    }

    private static function normKey(string $code, string $label, string $fallback): string
    {
        $code = trim($code);
        $label = trim($label);
        if ($code !== '' && $label !== '') {
            return $code . ' - ' . $label;
        }
        if ($code !== '') {
            return $code;
        }
        if ($label !== '') {
            return $label;
        }
        return $fallback;
    }

    private static function appendPriceAgg(array &$agg, string $key, float $tabela, float $praticado, float $desc, float $qtd): void
    {
        if (!isset($agg[$key])) {
            $agg[$key] = ['tabela' => 0.0, 'praticado' => 0.0, 'desc' => 0.0, 'itens' => 0, 'qtd' => 0.0];
        }
        $agg[$key]['tabela'] += $tabela;
        $agg[$key]['praticado'] += $praticado;
        $agg[$key]['desc'] += $desc;
        $agg[$key]['itens']++;
        $agg[$key]['qtd'] += $qtd;
    }

    private static function rankAggByPct(array $agg): array
    {
        $out = [];
        foreach ($agg as $key => $value) {
            $t = (float) ($value['tabela'] ?? 0);
            $d = (float) ($value['desc'] ?? 0);
            if ($t < 1500.0 || $d <= 0) {
                continue;
            }
            $out[$key] = [
                'pct' => $t > 0 ? ($d / $t) : 0.0,
                'tabela' => $t,
                'praticado' => (float) ($value['praticado'] ?? 0),
                'desc' => $d,
                'itens' => (int) ($value['itens'] ?? 0),
                'qtd' => (float) ($value['qtd'] ?? 0),
            ];
        }
        uasort($out, static fn(array $a, array $b): int => ($b['pct'] <=> $a['pct']));
        return array_slice($out, 0, 10, true);
    }

    private static function rankAggByDesc(array $agg): array
    {
        $out = [];
        foreach ($agg as $key => $value) {
            $t = (float) ($value['tabela'] ?? 0);
            $d = (float) ($value['desc'] ?? 0);
            if ($t < 1500.0 || $d <= 0) {
                continue;
            }
            $out[$key] = [
                'tabela' => $t,
                'praticado' => (float) ($value['praticado'] ?? 0),
                'desc' => $d,
                'itens' => (int) ($value['itens'] ?? 0),
                'qtd' => (float) ($value['qtd'] ?? 0),
            ];
        }
        uasort($out, static fn(array $a, array $b): int => ($b['desc'] <=> $a['desc']));
        return array_slice($out, 0, 10, true);
    }

    private static function pipefyGraphql(string $query, array $variables): array
    {
        $url = 'https://api.pipefy.com/graphql';
        $payload = json_encode(['query' => $query, 'variables' => $variables], JSON_UNESCAPED_UNICODE);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . PIPEFY_TOKEN_COMEX,
            ],
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException('Erro cURL Pipefy: ' . $err);
        }
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            throw new RuntimeException('Resposta invalida do Pipefy.');
        }
        if ($http >= 400 || !empty($json['errors'])) {
            throw new RuntimeException('Falha Pipefy GraphQL.');
        }
        return $json['data'] ?? [];
    }

    private static function pipefyFieldMap(array $fields): array
    {
        $map = [];
        foreach ($fields as $field) {
            $name = trim((string) ($field['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $map[mb_strtolower($name, 'UTF-8')] = $field['value'] ?? null;
        }
        return $map;
    }

    private static function pipefyGetField(array $map, string $name): ?string
    {
        $key = mb_strtolower($name, 'UTF-8');
        if (!array_key_exists($key, $map)) {
            return null;
        }
        $value = $map[$key];
        return $value !== null ? (string) $value : null;
    }

    private static function inRange(?int $ts, ?int $from, ?int $to): bool
    {
        if ($ts === null) {
            return false;
        }
        if ($from !== null && $ts < $from) {
            return false;
        }
        if ($to !== null && $ts > $to) {
            return false;
        }
        return true;
    }

    private static function formatDateBr(?string $ymd): string
    {
        $ymd = self::onlyDigits((string) $ymd);
        if (strlen($ymd) !== 8) {
            return '';
        }

        return substr($ymd, 6, 2) . '/' . substr($ymd, 4, 2) . '/' . substr($ymd, 0, 4);
    }

    private static function toFloatBr($value): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return 0.0;
        }

        $text = str_replace(['R$', ' '], '', $text);
        if (str_contains($text, ',') && str_contains($text, '.')) {
            $text = str_replace('.', '', $text);
            $text = str_replace(',', '.', $text);
        } else {
            $text = str_replace(',', '.', $text);
        }

        return (float) $text;
    }

    private static function normalizeText(string $value): string
    {
        $value = trim(mb_strtolower($value, 'UTF-8'));
        if ($value === '') {
            return '';
        }

        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($normalized !== false) {
            $value = $normalized;
        }

        $value = preg_replace('/[^a-z0-9]+/i', ' ', $value) ?? '';
        return trim($value);
    }

    private static function textMatchesAny(string $needle, array $haystacks): bool
    {
        $needle = self::normalizeText($needle);
        if ($needle === '') {
            return true;
        }

        foreach ($haystacks as $haystack) {
            if (self::normalizeText((string) $haystack) === $needle) {
                return true;
            }
        }

        return false;
    }

    private static function normalizedContainsAny(string $needle, array $haystacks): bool
    {
        if ($needle === '') {
            return true;
        }

        foreach ($haystacks as $haystack) {
            $normalized = self::normalizeText((string) $haystack);
            if ($normalized !== '' && str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }
}
