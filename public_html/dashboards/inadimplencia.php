<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';
require_login();
require_dash_perm('dash.financeiro.inadimplencia');

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$u = current_user();
$activePage = 'inadimplentes';
$page_title = 'Dashboard - Inadimplência';

$extra_css = [
    '/assets/css/loader.css?v=' . filemtime(APP_ROOT . '/assets/css/loader.css'),
    '/assets/css/base.css?v=' . filemtime(APP_ROOT . '/assets/css/base.css'),
    '/assets/css/header.css?v=' . filemtime(APP_ROOT . '/assets/css/header.css'),
    '/assets/css/inadimplentes.css?v=' . filemtime(APP_ROOT . '/assets/css/inadimplentes.css'),
];
?>
<!doctype html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="/assets/js/view-detect.js?v=<?= filemtime(APP_ROOT . '/assets/js/view-detect.js') ?>" defer></script>
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>

    <?php foreach ($extra_css as $css): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($css, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach; ?>
</head>

<body class="page">
    <?php require APP_ROOT . '/app/layout/header.php'; ?>

    <main class="page-inad">
        <section class="inad-header">
            <div>
                <h1>Inadimplentes</h1>
                <p>Painel com cruzamento entre faturamento e títulos vencidos do TOTVS. (A partir de 01/08/2025)</p>
            </div>


        </section>

        <section class="inad-filters">
            <div class="filter-group">
                <label for="filterDateFrom">Data inicial</label>
                <input type="date" id="filterDateFrom">
            </div>

            <div class="filter-group">
                <label for="filterDateTo">Data final</label>
                <input type="date" id="filterDateTo">
            </div>

            <div class="filter-group">
                <label for="filterVendedor">Vendedor</label>
                <select id="filterVendedor">
                    <option value="">Todos</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="filterSupervisor">Supervisor</label>
                <select id="filterSupervisor">
                    <option value="">Todos</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="filterFaixa">Faixa atraso</label>
                <select id="filterFaixa">
                    <option value="">Todas</option>
                    <option value="1-30">1-30</option>
                    <option value="31-60">31-60</option>
                    <option value="61-90">61-90</option>
                    <option value="91-180">91-180</option>
                    <option value="180+">180+</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="filterValorMin">Valor mínimo</label>
                <input type="number" id="filterValorMin" min="0" step="0.01" placeholder="0,00">
            </div>

            <div class="filter-group filter-group-actions">
                <label>&nbsp;</label>
                <div class="filter-actions">
                    <button class="btn-filter" id="btnApplyFilters" type="button">Aplicar</button>
                    <button class="btn-clear" id="btnClearFilters" type="button">Limpar</button>
                </div>
            </div>
        </section>

        <section class="inad-kpis">
            <article class="kpi-card">
                <span class="kpi-label">Total inadimplente</span>
                <strong class="kpi-value" id="kpiTotalInad">R$ 0,00</strong>
            </article>

            <article class="kpi-card">
                <span class="kpi-label">Clientes inadimplentes</span>
                <strong class="kpi-value" id="kpiClientesInad">0</strong>
            </article>

            <article class="kpi-card">
                <span class="kpi-label">Títulos vencidos</span>
                <strong class="kpi-value" id="kpiTitulos">0</strong>
            </article>

            <article class="kpi-card">
                <span class="kpi-label">Ticket médio inadimplência</span>
                <strong class="kpi-value" id="kpiTicket">R$ 0,00</strong>
            </article>

            <article class="kpi-card">
                <span class="kpi-label">Média dias atraso</span>
                <strong class="kpi-value" id="kpiDias">0 dias</strong>
            </article>

            <article class="kpi-card">
                <span class="kpi-label">% inad. sobre faturado</span>
                <strong class="kpi-value" id="kpiPctSobreFat">0,00%</strong>
            </article>

            <article class="kpi-card">
                <span class="kpi-label">Concentração Top 10</span>
                <strong class="kpi-value" id="kpiTop10Pct">0,00%</strong>
            </article>

            <article class="kpi-card">
                <span class="kpi-label">Clientes risco alto</span>
                <strong class="kpi-value" id="kpiRiscoAlto">0</strong>
            </article>
        </section>

        <section class="card card-insights">
            <div class="card-head">
                <h2>Insights automáticos</h2>
            </div>
            <div id="insightsList" class="insights-list">
                <div class="empty">Carregando insights...</div>
            </div>
        </section>
        <section class="card card-chart">
            <div class="card-head card-head--chart">
                <div>
                    <h2>Tendência da inadimplência</h2>
                    <p id="chartSubtitle">Acompanhamento da subida e queda no período.</p>
                </div>

                <div class="chart-presets" id="chartPresets">
                    <button type="button" class="btn-preset is-active" data-preset="30d">30 dias</button>
                    <button type="button" class="btn-preset" data-preset="90d">90 dias</button>
                    <button type="button" class="btn-preset" data-preset="6m">6 meses</button>
                    <button type="button" class="btn-preset" data-preset="12m">12 meses</button>
                </div>
            </div>

            <div class="chart-summary" id="chartSummary">
                <div class="metric-chip">
                    <span class="metric-chip__label">Mês atual</span>
                    <strong class="metric-chip__value" id="chartCurrentValue">R$ 0,00</strong>
                </div>
                <div class="metric-chip">
                    <span class="metric-chip__label">Variação</span>
                    <strong class="metric-chip__value" id="chartVariation">0,00%</strong>
                </div>
                <div class="metric-chip">
                    <span class="metric-chip__label">Agrupamento</span>
                    <strong class="metric-chip__value" id="chartGrouping">-</strong>
                </div>
            </div>

            <div class="chart-container">
                <canvas id="inadTrendChart"></canvas>
            </div>
        </section>
        <section class="inad-grid inad-grid--3">
            <article class="card">
                <div class="card-head">
                    <h2>Top clientes inadimplentes</h2>
                </div>
                <div id="topInadList" class="ranking-list ranking-list--scroll-10"></div>
            </article>

            <article class="card">
                <div class="card-head">
                    <h2>Top 50 clientes faturados</h2>
                </div>
                <div id="topFatList" class="ranking-list ranking-list--fat ranking-list--scroll-10"></div>
            </article>

            <article class="card">
                <div class="card-head">
                    <h2>Concentração do risco</h2>
                </div>
                <div id="concentracaoList" class="metric-list"></div>
            </article>

            <article class="card">
                <div class="card-head">
                    <h2>Faixas de atraso</h2>
                </div>
                <div id="agingList" class="aging-list"></div>
            </article>

            <article class="card">
                <div class="card-head">
                    <h2>Ranking por vendedor</h2>
                </div>
                <div id="rankingVendedor" class="ranking-list ranking-list--compact"></div>
            </article>

            <article class="card">
                <div class="card-head">
                    <h2>Ranking por supervisor</h2>
                </div>
                <div id="rankingSupervisor" class="ranking-list ranking-list--compact"></div>
            </article>
        </section>

        <section class="card card--table-fixed">
            <div class="card-head">
                <h2>Todos os clientes inadimplentes</h2>
            </div>

            <div class="filters-grid filters-grid--table filters-inline">
                <div class="field field-inline field-search">
                    <label for="filtroBusca">Buscar</label>
                    <input type="text" id="filtroBusca" placeholder="Nome, código, CNPJ...">
                </div>

                <div class="field field-inline">
                    <label for="filtroVendedorTabela">Vendedor</label>
                    <input type="text" id="filtroVendedorTabela" placeholder="Código ou nome">
                </div>

                <div class="field field-inline">
                    <label for="filtroSupervisorTabela">Supervisor</label>
                    <input type="text" id="filtroSupervisorTabela" placeholder="Código ou nome">
                </div>

                <div class="field field-inline">
                    <label for="filtroFaixaTabela">Faixa atraso</label>
                    <select id="filtroFaixaTabela">
                        <option value="">Todas</option>
                        <option value="1-30">1-30</option>
                        <option value="31-60">31-60</option>
                        <option value="61-90">61-90</option>
                        <option value="91-180">91-180</option>
                        <option value="180+">180+</option>
                    </select>
                </div>

                <div class="field field-inline">
                    <label for="filtroValorMinTabela">Valor mínimo inadimplente</label>
                    <input type="number" id="filtroValorMinTabela" min="0" step="0.01" placeholder="0,00">
                </div>

                <div class="field field-inline">
                    <label for="filtroStatusTabela">Status risco</label>
                    <select id="filtroStatusTabela">
                        <option value="">Todos</option>
                        <option value="Baixo">Baixo</option>
                        <option value="Médio">Médio</option>
                        <option value="Alto">Alto</option>
                        <option value="Crítico">Crítico</option>
                    </select>
                </div>

                <div class="field field-inline field-actions">
                    <label>&nbsp;</label>
                    <div class="inline-actions">
                        <button class="btn-filter" id="btnApplyTable" type="button">Aplicar</button>
                        <button class="btn-clear" id="btnClearTable" type="button">Limpar</button>
                        <button class="btn-secondary" id="btnOpenEmailModal" type="button">Enviar aviso</button>
                    </div>
                </div>
            </div>

            <div class="table-wrap">
                <table class="inad-table">
                    <thead>
                        <tr>
                            <th class="th-sort" data-sort="nome">Cliente <span class="sort-indicator"></span></th>
                            <th class="th-sort" data-sort="vendedor_nome">Vendedor <span class="sort-indicator"></span>
                            </th>
                            <th class="th-sort" data-sort="supervisor_nome">Supervisor <span
                                    class="sort-indicator"></span></th>
                            <th class="th-sort" data-sort="inad_total">Inadimplente <span class="sort-indicator"></span>
                            </th>
                            <th class="th-sort" data-sort="faturado_periodo">Faturado período <span
                                    class="sort-indicator"></span></th>
                            <th class="th-sort" data-sort="indice_inadimplencia_pct">% Inad. <span
                                    class="sort-indicator"></span></th>
                            <th class="th-sort" data-sort="participacao_total_pct">Part. total <span
                                    class="sort-indicator"></span></th>
                            <th class="th-sort" data-sort="inad_qtd_titulos">Títulos <span
                                    class="sort-indicator"></span></th>
                            <th class="th-sort" data-sort="maior_atraso_dias">Maior atraso <span
                                    class="sort-indicator"></span></th>
                            <th class="th-sort" data-sort="risk_score">Risco <span class="sort-indicator"></span></th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody id="clientesTable"></tbody>
                </table>
            </div>
        </section>
        <div class="modal" id="modalAvisoEmail" aria-hidden="true">
            <div class="modal__backdrop" data-close-email-modal></div>

            <div class="modal__dialog modal__dialog--xl" role="dialog" aria-modal="true"
                aria-labelledby="modalAvisoTitulo">
                <div class="modal__header">
                    <div>
                        <h2 id="modalAvisoTitulo">Enviar aviso de inadimplência</h2>
                        <p id="modalAvisoResumo">Selecione o filtro e revise o e-mail antes do envio.</p>
                    </div>

                    <button type="button" class="modal__close" id="btnCloseEmailModal" aria-label="Fechar">×</button>
                </div>

                <div class="modal__body">
                    <div class="email-modal-grid">
                        <div class="email-form-side">

                            <div class="form-row">
                                <label for="emailDestinatario">Para</label>
                                <input type="text" id="emailDestinatario" placeholder="email@empresa.com.br">
                            </div>

                            <div class="form-row">
                                <label for="emailCc">CC</label>
                                <input type="text" id="emailCc" placeholder="cc1@empresa.com.br; cc2@empresa.com.br">
                            </div>

                            <div class="form-row">
                                <label for="emailAssunto">Assunto</label>
                                <input type="text" id="emailAssunto" name="inad_notice_topic_locked"
                                    placeholder="Assunto do e-mail" readonly tabindex="-1"
                                    style="background:#f3f4f6; cursor:not-allowed;">
                            </div>

                            <div class="form-row">
                                <label for="emailMensagemExtra">Observação adicional</label>
                                <textarea id="emailMensagemExtra" rows="5"
                                    placeholder="Digite uma mensagem complementar..."></textarea>
                            </div>

                            <div class="email-meta-cards">
                                <div class="mini-kpi">
                                    <span>Clientes</span>
                                    <strong id="emailQtdClientes">0</strong>
                                </div>
                                <div class="mini-kpi">
                                    <span>Total inadimplente</span>
                                    <strong id="emailTotalInad">R$ 0,00</strong>
                                </div>
                                <div class="mini-kpi">
                                    <span>Títulos</span>
                                    <strong id="emailQtdTitulos">0</strong>
                                </div>
                            </div>

                            <div class="modal__actions">
                                <button type="button" class="btn-clear" id="btnPreviewEmail">Atualizar prévia</button>
                                <button type="button" class="btn-filter" id="btnEnviarEmailAviso">Enviar e-mail</button>
                            </div>
                        </div>

                        <div class="email-preview-side">
                            <div class="preview-head">
                                <strong>Prévia do e-mail</strong>
                            </div>
                            <div id="emailPreviewHtml" class="email-preview-box"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div class="modal" id="modalCliente" aria-hidden="true">
        <div class="modal-backdrop" id="modalBackdrop"></div>

        <div class="modal-content">
            <div class="modal-head">
                <div>
                    <h3 id="modalClienteNome">Cliente</h3>
                    <p id="modalClienteResumo"></p>
                </div>
                <button class="modal-close" id="modalClose" type="button">×</button>
            </div>

            <div class="modal-summary" id="modalSummary"></div>

            <div class="table-wrap">
                <table class="inad-table inad-table--modal">
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Tipo</th>
                            <th>Emissão</th>
                            <th>Vencimento</th>
                            <th>Dias atraso</th>
                            <th>Faixa</th>
                            <th>Valor</th>
                            <th>Saldo</th>
                            <th>Forma</th>
                        </tr>
                    </thead>
                    <tbody id="modalTitulosBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <?php require_once APP_ROOT . '/app/layout/footer.php'; ?>

    <script src="/assets/js/loader.js?v=<?= filemtime(APP_ROOT . '/assets/js/loader.js') ?>"></script>
    <script
        src="/assets/js/dashboard-inadimplentes.js?v=<?= filemtime(APP_ROOT . '/assets/js/dashboard-inadimplentes.js') ?>"></script>
    <script src="/assets/js/header.js?v=<?= filemtime(APP_ROOT . '/assets/js/header.js') ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
</body>

</html>