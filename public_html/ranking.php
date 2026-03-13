<?php
declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';

require_login();

$u = current_user();
$activePage = 'poppercoins';
?>
<!doctype html>
<html lang="pt-br">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Ranking — Popper Coins</title>

  <link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(__DIR__ . '/assets/css/base.css') ?>" />
  <link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(__DIR__ . '/assets/css/header.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/assets/css/dropdowns.css') ?>" />

  <style>
    :root {
      --pc-bg: #f6f8fc;
      --pc-card: #ffffff;
      --pc-border: #e8edf5;
      --pc-border-strong: #d8e0ec;
      --pc-text: #0f172a;
      --pc-muted: #64748b;
      --pc-primary: #6d5bd0;
      --pc-primary-2: #8a7ae6;
      --pc-green: #22c55e;
      --pc-green-2: #16a34a;
      --pc-gold: #f59e0b;
      --pc-silver: #94a3b8;
      --pc-bronze: #b45309;
      --pc-shadow: 0 12px 28px rgba(109, 91, 208, .10);
      --pc-shadow-soft: 0 8px 18px rgba(15, 23, 42, .05);
      --pc-radius: 18px;
    }

    body.page {
      background:
        radial-gradient(circle at top left, rgba(138, 122, 230, .08), transparent 30%),
        radial-gradient(circle at top right, rgba(34, 197, 94, .04), transparent 22%),
        linear-gradient(180deg, #fafbff 0%, #f6f8fc 100%);
    }

    .container.container--wide {
      max-width: 1440px
    }

    @media (min-width:1500px) {
      .container.container--wide {
        max-width: 1580px
      }
    }

    .page-title {
      margin-bottom: 18px;
      color: #fff;
    }

    .coins-shell {
      display: flex;
      flex-direction: column;
      gap: 16px;
      padding-bottom: 48px;
    }

    .hero {
      background:
        radial-gradient(circle at top right, rgba(255, 255, 255, .45), transparent 40%),
        linear-gradient(135deg, #efeafd 0%, #e5defa 52%, #ddd5f7 100%);
      color: #1f2540;
      border: 1px solid rgba(126, 98, 214, .14);
      border-radius: 24px;
      padding: 22px 22px 18px;
      box-shadow:
        0 12px 28px rgba(109, 91, 208, .10),
        inset 0 1px 0 rgba(255, 255, 255, .72);
      position: relative;
      overflow: hidden;
    }

    .hero::before {
      content: "";
      position: absolute;
      inset: auto -80px -90px auto;
      width: 240px;
      height: 240px;
      border-radius: 999px;
      background: rgba(255, 255, 255, .22);
      filter: blur(10px);
    }

    .hero::after {
      content: "";
      position: absolute;
      inset: -80px auto auto -60px;
      width: 180px;
      height: 180px;
      border-radius: 999px;
      background: rgba(255, 255, 255, .16);
      filter: blur(8px);
    }

    .hero-top {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 18px;
      flex-wrap: wrap;
      position: relative;
      z-index: 1;
    }

    .hero-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(255, 255, 255, .60);
      border: 1px solid rgba(109, 91, 208, .14);
      color: #5b46b2;
      border-radius: 999px;
      padding: 10px 14px;
      font-size: 13px;
      font-weight: 800;
      backdrop-filter: blur(6px);
      white-space: nowrap;
    }

    .kpis {
      margin-top: 18px;
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 14px;
      position: relative;
      z-index: 1;
    }

    @media (max-width:1100px) {
      .kpis {
        grid-template-columns: repeat(2, minmax(0, 1fr))
      }
    }

    @media (max-width:620px) {
      .kpis {
        grid-template-columns: 1fr
      }
    }

    .kpi {
      background: rgba(255, 255, 255, .58);
      border: 1px solid rgba(109, 91, 208, .10);
      border-radius: 18px;
      padding: 14px 16px;
      backdrop-filter: blur(8px);
      min-height: 86px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, .7);
    }

    .kpi-label {
      font-size: 12px;
      color: #6b7280;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: .04em;
      margin-bottom: 6px;
    }

    .kpi-value {
      font-size: 28px;
      line-height: 1;
      font-weight: 800;
      color: #4338ca;
      letter-spacing: -.02em;
    }

    .kpi-sub {
      margin-top: 6px;
      font-size: 12px;
      color: #6b7280;
      font-weight: 600;
    }

    .toolbar {
      background: var(--pc-card);
      border: 1px solid var(--pc-border);
      border-radius: 20px;
      box-shadow: var(--pc-shadow-soft);
      padding: 16px;
      display: flex;
      flex-direction: column;
      gap: 14px;
    }

    .toolbar-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 14px;
      flex-wrap: wrap;
    }

    .toolbar-left,
    .toolbar-right {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }

    .segmented {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 5px;
      background: #f8fafc;
      border: 1px solid var(--pc-border);
      border-radius: 14px;
    }

    .seg-btn {
      height: 38px;
      padding: 0 14px;
      border: none;
      border-radius: 10px;
      background: transparent;
      color: var(--pc-text);
      font-weight: 700;
      cursor: pointer;
      transition: .18s ease;
    }

    .seg-btn.is-active {
      background: #fff;
      color: var(--pc-primary);
      box-shadow: 0 4px 12px rgba(15, 23, 42, .08);
    }

    .search-wrap {
      position: relative;
      min-width: 320px;
      flex: 1 1 360px;
      max-width: 480px;
    }

    .search-icon {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
      display: flex;
      align-items: center;
      justify-content: center;
      pointer-events: none;
    }

    .search-input {
      width: 100%;
      height: 42px;
      border: 1px solid var(--pc-border);
      border-radius: 14px;
      padding: 0 40px 0 40px;
      background: #fff;
      outline: none;
      color: var(--pc-text);
      font-size: 14px;
      font-weight: 600;
      transition: border-color .18s ease, box-shadow .18s ease;
    }

    .search-input:focus {
      border-color: #c7d2fe;
      box-shadow: 0 0 0 4px rgba(99, 102, 241, .10);
    }

    .clear-search {
      position: absolute;
      right: 8px;
      top: 50%;
      transform: translateY(-50%);
      width: 28px;
      height: 28px;
      border: none;
      border-radius: 999px;
      background: #eef2ff;
      color: var(--pc-primary);
      font-weight: 900;
      cursor: pointer;
      display: none;
    }

    .clear-search.show {
      display: flex;
      align-items: center;
      justify-content: center
    }

    .control {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .control label {
      font-size: 11px;
      font-weight: 800;
      color: var(--pc-muted);
      text-transform: uppercase;
      letter-spacing: .05em;
      padding-left: 2px;
    }

    .select {
      min-width: 160px;
      height: 42px;
      border: 1px solid var(--pc-border);
      border-radius: 14px;
      padding: 0 12px;
      background: #fff;
      color: var(--pc-text);
      font-weight: 700;
      outline: none;
    }

    .select:focus {
      border-color: #c7d2fe;
      box-shadow: 0 0 0 4px rgba(99, 102, 241, .10);
    }

    .coins-page {
      display: grid;
      grid-template-columns: minmax(0, 1fr) 410px;
      gap: 16px;
      align-items: start;
    }

    @media (max-width:1180px) {
      .coins-page {
        grid-template-columns: 1fr
      }
    }

    .cards-panel,
    .ranking {
      background: var(--pc-card);
      border: 1px solid var(--pc-border);
      border-radius: 22px;
      box-shadow: var(--pc-shadow-soft);
      overflow: hidden;
    }

    .panel-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 16px 18px;
      border-bottom: 1px solid var(--pc-border);
      background: linear-gradient(180deg, #fff 0%, #fbfcfe 100%);
    }

    .panel-title-wrap {
      display: flex;
      flex-direction: column;
      gap: 4px;
      min-width: 0;
    }

    .panel-title {
      margin: 0;
      font-size: 18px;
      font-weight: 800;
      color: var(--pc-text);
      letter-spacing: -.01em;
    }

    .panel-subtitle {
      font-size: 13px;
      color: var(--pc-muted);
      font-weight: 600;
    }

    .panel-chip {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      height: 34px;
      padding: 0 12px;
      border-radius: 999px;
      background: #f8fafc;
      border: 1px solid var(--pc-border);
      color: var(--pc-text);
      font-size: 12px;
      font-weight: 800;
      white-space: nowrap;
    }

    .cards-carousel {
      padding: 0
    }

    .cards {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 16px;
      padding: 18px;
      overflow: hidden;
    }

    @media (min-width:1450px) {
      .cards {
        grid-template-columns: repeat(4, minmax(0, 1fr))
      }
    }

    @media (max-width:980px) {
      .cards {
        grid-template-columns: repeat(2, minmax(0, 1fr))
      }
    }

    @media (max-width:620px) {
      .cards {
        grid-template-columns: 1fr
      }
    }

    .card {
      background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
      border: 1px solid var(--pc-border);
      border-radius: 18px;
      padding: 14px;
      box-shadow: 0 8px 18px rgba(15, 23, 42, .05);
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 10px;
      min-height: 220px;
      transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
      position: relative;
      overflow: hidden;
    }

    .card::before {
      content: "";
      position: absolute;
      inset: 0 0 auto 0;
      height: 5px;
      background: linear-gradient(90deg, var(--pc-primary), #6366f1, #22c55e);
      opacity: .95;
    }

    .card:hover {
      transform: translateY(-3px);
      box-shadow: 0 14px 28px rgba(15, 23, 42, .10);
      border-color: var(--pc-border-strong);
    }

    .card-rank {
      position: absolute;
      top: 12px;
      left: 12px;
      min-width: 32px;
      height: 32px;
      padding: 0 10px;
      border-radius: 999px;
      background: #111827;
      color: #fff;
      font-size: 12px;
      font-weight: 800;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 8px 16px rgba(17, 24, 39, .18);
    }

    .card-rank.is-top1 {
      background: linear-gradient(135deg, #f59e0b, #facc15);
      color: #4a3300
    }

    .card-rank.is-top2 {
      background: linear-gradient(135deg, #cbd5e1, #94a3b8);
      color: #172554
    }

    .card-rank.is-top3 {
      background: linear-gradient(135deg, #d97706, #f59e0b);
      color: #fff7ed
    }

    .avatar {
      width: 104px;
      height: 104px;
      border-radius: 999px;
      margin-top: 8px;
      background: #f8fafc;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #111827;
      font-weight: 800;
      overflow: hidden;
      border: 3px solid #fff;
      box-shadow: 0 0 0 1px rgba(15, 23, 42, .08), 0 10px 18px rgba(15, 23, 42, .08);
      flex: 0 0 auto;
    }

    .avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 999px;
      display: block;
    }

    .card-name {
      text-align: center;
      font-weight: 800;
      color: var(--pc-text);
      font-size: 15px;
      line-height: 1.2;
      min-height: 36px;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 0 8px;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .card-sector {
      margin-top: -2px;
      min-height: 22px;
      font-size: 12px;
      color: var(--pc-muted);
      font-weight: 700;
      text-align: center;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 100%;
    }

    .coin-pill {
      margin-top: auto;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      min-height: 42px;
      width: 100%;
      background: linear-gradient(135deg, var(--pc-primary), var(--pc-primary-2));
      color: #fff;
      border-radius: 14px;
      font-weight: 800;
      font-size: 14px;
      padding: 0 12px;
      text-align: center;
      box-shadow: 0 10px 16px rgba(67, 56, 202, .18);
    }

    .cards.is-animating {
      pointer-events: none
    }

    .cards.page-enter {
      animation: pageEnter .22s ease both
    }

    .cards.page-leave {
      animation: pageLeave .16s ease both
    }

    @keyframes pageLeave {
      from {
        opacity: 1;
        transform: translateY(0)
      }

      to {
        opacity: 0;
        transform: translateY(8px)
      }
    }

    @keyframes pageEnter {
      from {
        opacity: 0;
        transform: translateY(-8px)
      }

      to {
        opacity: 1;
        transform: translateY(0)
      }
    }

    .cards-nav {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 10px;
      padding: 0 18px 18px;
      user-select: none;
    }

    .nav-btn {
      height: 40px;
      min-width: 44px;
      padding: 0 14px;
      border: 1px solid var(--pc-border);
      border-radius: 12px;
      background: #fff;
      color: var(--pc-text);
      font-weight: 900;
      cursor: pointer;
      transition: .18s ease;
    }

    .nav-btn:hover:not(:disabled) {
      border-color: #cbd5e1;
      background: #f8fafc;
    }

    .nav-btn:disabled {
      opacity: .45;
      cursor: not-allowed;
    }

    .page-ind {
      min-width: 110px;
      text-align: center;
      font-weight: 800;
      color: var(--pc-text);
      opacity: .8;
    }

    .ranking {
      position: sticky;
      top: 86px;
    }

    @media (max-width:1180px) {
      .ranking {
        position: relative;
        top: auto
      }
    }

    .ranking-head {
      background: linear-gradient(135deg, #9cc434 0%, #7fb321 100%);
      color: #fff;
      padding: 18px 18px 16px;
    }

    .ranking-title {
      font-size: 22px;
      font-weight: 800;
      margin: 0;
      letter-spacing: -.01em;
    }

    .ranking-meta {
      margin-top: 4px;
      font-size: 13px;
      font-weight: 700;
      opacity: .96;
    }

    .ranking-body {
      padding: 14px 18px 18px
    }

    .rank-tabs {
      display: flex;
      gap: 8px;
      justify-content: center;
      margin: 2px 0 14px;
      background: #f8fafc;
      border: 1px solid var(--pc-border);
      border-radius: 14px;
      padding: 5px;
    }

    .rank-tab {
      flex: 1 1 0;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 10px;
      font-weight: 800;
      color: var(--pc-text);
      opacity: .72;
      cursor: pointer;
      transition: .18s ease;
    }

    .rank-tab.is-active {
      background: #fff;
      opacity: 1;
      box-shadow: 0 4px 12px rgba(15, 23, 42, .08);
    }

    .rank-list {
      overflow-x: hidden
    }

    .rank-item {
      display: grid;
      grid-template-columns: 34px 42px minmax(0, 1fr) 96px;
      gap: 10px;
      align-items: center;
      padding: 11px 0;
      border-bottom: 1px solid #f1f5f9;
      min-width: 0;
    }

    .rank-item:last-child {
      border-bottom: none
    }

    .badge {
      width: 28px;
      height: 28px;
      border-radius: 999px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-weight: 800;
      font-size: 12px;
      background: #8b5cf6;
      flex: 0 0 auto;
    }

    .trophy-medal {
      font-size: 20px;
      line-height: 1;
      width: 28px;
      text-align: center;
    }

    .rank-avatar {
      width: 42px;
      height: 42px;
      border-radius: 999px;
      overflow: hidden;
      border: 1px solid rgba(15, 23, 42, .08);
      background: #f8fafc;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 6px 12px rgba(15, 23, 42, .06);
    }

    .rank-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .rank-name {
      font-weight: 800;
      color: var(--pc-text);
      font-size: 13px;
      line-height: 1.2;
      margin-bottom: 7px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .bar {
      height: 10px;
      border-radius: 999px;
      background: #e5e7eb;
      overflow: hidden;
      position: relative;
    }

    .bar>span {
      display: block;
      height: 100%;
      background: linear-gradient(90deg, #22c55e, #16a34a);
      width: 0%;
      border-radius: 999px;
    }

    .rank-coins {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      font-weight: 800;
      color: var(--pc-text);
      white-space: nowrap;
      flex: 0 0 auto;
      font-size: 13px;
    }

    .empty {
      padding: 22px 14px;
      color: var(--pc-muted);
      font-weight: 700;
      text-align: center;
    }

    .empty-box {
      padding: 28px 18px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 10px;
      color: var(--pc-muted);
      text-align: center;
    }

    .empty-box svg {
      opacity: .55;
    }

    .skeleton-card {
      height: 220px;
      border-radius: 18px;
      background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 37%, #f3f4f6 63%);
      background-size: 400% 100%;
      animation: shine 1.1s ease infinite;
      border: 1px solid var(--pc-border);
    }

    .skeleton-line {
      height: 14px;
      border-radius: 999px;
      background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 37%, #f3f4f6 63%);
      background-size: 400% 100%;
      animation: shine 1.1s ease infinite;
    }

    @keyframes shine {
      0% {
        background-position: 100% 0
      }

      100% {
        background-position: 0 0
      }
    }

    @media (max-width:760px) {
      .toolbar-row {
        flex-direction: column;
        align-items: stretch;
      }

      .toolbar-left,
      .toolbar-right {
        width: 100%;
      }

      .search-wrap {
        min-width: 0;
        max-width: none;
        width: 100%;
      }

      .control {
        flex: 1 1 0;
      }

      .select {
        min-width: 0;
        width: 100%;
      }

      .segmented {
        width: 100%;
      }

      .seg-btn {
        flex: 1 1 0;
      }
    }
  </style>
</head>

<body class="page">
  <?php require_once __DIR__ . '/app/header.php'; ?>

  <main class="container container--wide">
    <div class="coins-shell">
      <section class="hero">
        <div class="hero-top">
          <div>
            <h1 class="hero-title">Ranking — Popper Coins</h1>
            <p class="hero-sub">Acompanhe o desempenho dos colaboradores e gestores em tempo real.</p>
          </div>
          <div class="hero-badge" id="heroModeBadge">Modo: Acumulado</div>
        </div>

        <div class="kpis">
          <div class="kpi">
            <div class="kpi-label">Total de coins</div>
            <div class="kpi-value" id="totalCoins">0</div>
            <div class="kpi-sub">Saldo geral conforme filtros</div>
          </div>
          <div class="kpi">
            <div class="kpi-label">Participantes</div>
            <div class="kpi-value" id="kpiPeople">0</div>
            <div class="kpi-sub">Pessoas exibidas no ranking</div>
          </div>
          <div class="kpi">
            <div class="kpi-label">Maior saldo</div>
            <div class="kpi-value" id="kpiTopCoins">0</div>
            <div class="kpi-sub" id="kpiTopName">—</div>
          </div>
          <div class="kpi">
            <div class="kpi-label">Média por pessoa</div>
            <div class="kpi-value" id="kpiAverage">0</div>
            <div class="kpi-sub">Baseada no resultado atual</div>
          </div>
        </div>
      </section>

      <section class="toolbar">
        <div class="toolbar-row">
          <div class="toolbar-left">
            <div class="search-wrap">
              <span class="search-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="18" height="18">
                  <path fill="currentColor"
                    d="M10.5 3a7.5 7.5 0 1 1 0 15a7.5 7.5 0 0 1 0-15Zm0 2a5.5 5.5 0 1 0 0 11a5.5 5.5 0 0 0 0-11Zm8.85 12.44l2.86 2.85a1 1 0 0 1-1.42 1.42l-2.85-2.86a1 1 0 0 1 1.41-1.41Z" />
                </svg>
              </span>
              <input class="search-input" id="filterInput" placeholder="Buscar por nome..." />
              <button class="clear-search" id="clearSearchBtn" type="button" aria-label="Limpar busca">×</button>
            </div>

            <div class="segmented" aria-label="Modo de período">
              <button class="seg-btn is-active" id="tab-all" data-mode="all" type="button">Acumulado</button>
              <button class="seg-btn" id="tab-month" data-mode="month" type="button">Mês atual</button>
            </div>
          </div>

          <div class="toolbar-right">
            <div class="control">
              <label for="sector">Setor</label>
              <select class="select" id="sector">
                <option value="">Todos</option>
              </select>
            </div>

            <div class="control">
              <label for="sortBy">Ordenar por</label>
              <select class="select" id="sortBy">
                <option value="coins_desc">Maior saldo</option>
                <option value="coins_asc">Menor saldo</option>
                <option value="name_asc">Nome A-Z</option>
                <option value="name_desc">Nome Z-A</option>
              </select>
            </div>

            <div class="control">
              <label for="pageSize">Por página</label>
              <select class="select" id="pageSize">
                <option value="12">12</option>
                <option value="15">15</option>
                <option value="18">18</option>
              </select>
            </div>
          </div>
        </div>
      </section>

      <div class="coins-page">
        <!-- ESQUERDA -->
        <section class="cards-panel">
          <div class="panel-head">
            <div class="panel-title-wrap">
              <h2 class="panel-title">Colaboradores</h2>
              <div class="panel-subtitle" id="cardsSummary">Exibindo 0 pessoas</div>
            </div>
            <div class="panel-chip" id="cardsModeChip">Acumulado</div>
          </div>

          <div class="cards-carousel">
            <div class="cards" id="cardsContainer">
              <div class="skeleton-card"></div>
              <div class="skeleton-card"></div>
              <div class="skeleton-card"></div>
            </div>

            <div class="cards-nav" id="cardsNav" style="display:none;">
              <button class="nav-btn" id="prevPageBtn" type="button" aria-label="Página anterior">&larr;</button>
              <div class="page-ind" id="pageIndicator">1 / 1</div>
              <button class="nav-btn" id="nextPageBtn" type="button" aria-label="Próxima página">&rarr;</button>
            </div>
          </div>
        </section>
        <!-- DIREITA -->
        <aside class="ranking">
          <div class="ranking-head">
            <h3 class="ranking-title">Ranking do Mês</h3>
            <div class="ranking-meta">Top 10</div>
          </div>

          <div class="ranking-body">
            <div class="rank-list" id="rankingList">
              <div class="empty">Carregando…</div>
            </div>
          </div>
        </aside>

  </main>

  <?php require_once __DIR__ . '/app/footer.php'; ?>

  <script src="/assets/js/header.js?v=<?= filemtime(__DIR__ . '/assets/js/header.js') ?>"></script>
  <script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdowns.js') ?>"></script>

  <script>
    const USE_DEFAULT_AVATAR_ICON = true;

    function defaultAvatarIcon(size = 56) {
      return `
    <svg viewBox="0 0 24 24" width="${size}" height="${size}" aria-hidden="true" focusable="false">
      <path fill="#94a3b8" d="M12 12a4.25 4.25 0 1 0 0-8.5A4.25 4.25 0 0 0 12 12Zm0 2c-4.42 0-8 2.24-8 5v.75c0 .41.34.75.75.75h14.5c.41 0 .75-.34.75-.75V19c0-2.76-3.58-5-8-5Z"/>
    </svg>
  `;
    }

    (function () {
      const STORAGE_KEY = 'poppercoins-ranking-ui-v2';

      let mode = 'all';
      let sector = '';
      let q = '';
      let sortBy = 'coins_desc';
      let cardsPerPage = 12;

      let allItems = [];
      let page = 1;
      let totalPages = 1;

      const tabAll = document.getElementById('tab-all');
      const tabMonth = document.getElementById('tab-month');
      const sectorEl = document.getElementById('sector');
      const filterInput = document.getElementById('filterInput');
      const clearSearchBtn = document.getElementById('clearSearchBtn');
      const sortByEl = document.getElementById('sortBy');
      const pageSizeEl = document.getElementById('pageSize');

      const totalCoinsEl = document.getElementById('totalCoins');
      const kpiPeopleEl = document.getElementById('kpiPeople');
      const kpiTopCoinsEl = document.getElementById('kpiTopCoins');
      const kpiTopNameEl = document.getElementById('kpiTopName');
      const kpiAverageEl = document.getElementById('kpiAverage');

      const heroModeBadge = document.getElementById('heroModeBadge');
      const cardsModeChip = document.getElementById('cardsModeChip');
      const cardsSummary = document.getElementById('cardsSummary');

      const cardsContainer = document.getElementById('cardsContainer');
      const cardsNav = document.getElementById('cardsNav');
      const prevPageBtn = document.getElementById('prevPageBtn');
      const nextPageBtn = document.getElementById('nextPageBtn');
      const pageIndicator = document.getElementById('pageIndicator');

      const rankingList = document.getElementById('rankingList');

      function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, (c) => (
          { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]
        ));
      }

      function num(v) {
        const n = Number(v);
        return Number.isFinite(n) ? n : 0;
      }

      function formatInt(v) {
        return new Intl.NumberFormat('pt-BR').format(num(v));
      }

      function initials(name) {
        const n = String(name || '').trim();
        if (!n) return 'U';
        const parts = n.split(/\s+/);
        const a = parts[0]?.[0] || 'U';
        const b = (parts.length > 1 ? parts[parts.length - 1][0] : '') || '';
        return (a + b).toUpperCase();
      }

      function simpleName(full) {
        const n = String(full || '').trim().replace(/\s+/g, ' ');
        if (!n) return '';
        const parts = n.split(' ');
        if (parts.length === 1) return parts[0];
        return parts[0] + ' ' + parts[parts.length - 1];
      }

      function currentModeLabel() {
        return mode === 'month' ? 'Mês atual' : 'Acumulado';
      }

      function setActiveTabs() {
        tabAll.classList.toggle('is-active', mode === 'all');
        tabMonth.classList.toggle('is-active', mode === 'month');
        heroModeBadge.textContent = 'Modo: ' + currentModeLabel();
        cardsModeChip.textContent = currentModeLabel();
      }

      function updateSearchClearButton() {
        clearSearchBtn.classList.toggle('show', filterInput.value.trim().length > 0);
      }

      function saveState() {
        const state = {
          mode,
          sector,
          q,
          sortBy,
          cardsPerPage
        };
        localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
      }

      function loadState() {
        try {
          const raw = localStorage.getItem(STORAGE_KEY);
          if (!raw) return;
          const s = JSON.parse(raw);

          if (s.mode === 'all' || s.mode === 'month') mode = s.mode;
          if (typeof s.sector === 'string') sector = s.sector;
          if (typeof s.q === 'string') q = s.q;
          if (['coins_desc', 'coins_asc', 'name_asc', 'name_desc'].includes(s.sortBy)) sortBy = s.sortBy;
          if ([12, 15, 18].includes(Number(s.cardsPerPage))) cardsPerPage = Number(s.cardsPerPage);
        } catch (e) { }
      }

      async function loadSectors() {
        const res = await fetch('/api/sectors.php', { cache: 'no-store' });
        const data = await res.json();
        if (!data || !data.ok || !Array.isArray(data.sectors)) return;

        const seen = new Set();
        data.sectors.forEach((s) => {
          const name = String(s || '').trim();
          if (!name || seen.has(name)) return;
          seen.add(name);

          const opt = document.createElement('option');
          opt.value = name;
          opt.textContent = name;
          sectorEl.appendChild(opt);
        });

        if (sector) sectorEl.value = sector;
      }

      async function loadTotal() {
        const qs = new URLSearchParams({ mode, sector });
        const res = await fetch('/api/popper-coins-total.php?' + qs.toString(), { cache: 'no-store' });
        const data = await res.json();
        if (data && data.ok) {
          totalCoinsEl.textContent = formatInt(data.total ?? 0);
        } else {
          totalCoinsEl.textContent = '0';
        }
      }

      async function loadAllRanking() {
        const qs = new URLSearchParams({ mode, sector, q });
        const res = await fetch('/api/popper-coins-ranking.php?' + qs.toString(), { cache: 'no-store' });
        const data = await res.json();
        if (!data || !data.ok || !Array.isArray(data.items)) return { items: [] };
        return data;
      }

      function normalizeItem(it, idx) {
        return {
          position: num(it.position) || (idx + 1),
          name: String(it.name || '').trim(),
          coins: num(it.coins),
          avatar: String(it.avatar || '').trim(),
          sector: String(it.sector || it.department || '').trim()
        };
      }

      function sortItems(items) {
        const arr = [...items];
        arr.sort((a, b) => {
          if (sortBy === 'coins_asc') return a.coins - b.coins || a.name.localeCompare(b.name, 'pt-BR');
          if (sortBy === 'name_asc') return a.name.localeCompare(b.name, 'pt-BR') || (b.coins - a.coins);
          if (sortBy === 'name_desc') return b.name.localeCompare(a.name, 'pt-BR') || (b.coins - a.coins);
          return b.coins - a.coins || a.name.localeCompare(b.name, 'pt-BR');
        });

        return arr.map((item, index) => ({
          ...item,
          position: index + 1
        }));
      }

      function medalForIndex(idx) {
        if (idx === 0) return '🥇';
        if (idx === 1) return '🥈';
        if (idx === 2) return '🥉';
        return null;
      }

      function rankClass(idx) {
        if (idx === 0) return 'is-top1';
        if (idx === 1) return 'is-top2';
        if (idx === 2) return 'is-top3';
        return '';
      }

      function avatarHtml(avatar, name, size = 56) {
        const nm = simpleName(name);
        const icon = defaultAvatarIcon(size).replace(/'/g, "\\'");
        if (avatar) {
          return `<img src="${esc(avatar)}" alt="Foto de ${esc(nm)}"
        onerror="this.remove(); this.parentNode.innerHTML='${USE_DEFAULT_AVATAR_ICON ? icon : esc(initials(nm))}';" />`;
        }
        return USE_DEFAULT_AVATAR_ICON ? defaultAvatarIcon(size) : esc(initials(nm));
      }

      function showCardsLoading() {
        cardsContainer.innerHTML = `
      <div class="skeleton-card"></div>
      <div class="skeleton-card"></div>
      <div class="skeleton-card"></div>
      <div class="skeleton-card"></div>
      <div class="skeleton-card"></div>
      <div class="skeleton-card"></div>
    `;
        cardsNav.style.display = 'none';
      }

      function showRankingLoading() {
        rankingList.innerHTML = `
      <div style="display:flex;flex-direction:column;gap:14px;padding:8px 0;">
        <div class="skeleton-line" style="height:48px"></div>
        <div class="skeleton-line" style="height:48px"></div>
        <div class="skeleton-line" style="height:48px"></div>
        <div class="skeleton-line" style="height:48px"></div>
        <div class="skeleton-line" style="height:48px"></div>
      </div>
    `;
      }

      function renderKPIs(items) {
        const count = items.length;
        const total = items.reduce((acc, it) => acc + num(it.coins), 0);
        const top = items[0] || null;
        const avg = count > 0 ? Math.round(total / count) : 0;

        kpiPeopleEl.textContent = formatInt(count);
        kpiTopCoinsEl.textContent = formatInt(top ? top.coins : 0);
        kpiTopNameEl.textContent = top ? simpleName(top.name) : '—';
        kpiAverageEl.textContent = formatInt(avg);

        cardsSummary.textContent = `Exibindo ${formatInt(count)} pessoa${count === 1 ? '' : 's'}`;
      }

      function animateCardsSwap(doRender) {
        cardsContainer.classList.add('is-animating', 'page-leave');

        window.setTimeout(() => {
          cardsContainer.classList.remove('page-leave');
          doRender();
          cardsContainer.classList.add('page-enter');

          window.setTimeout(() => {
            cardsContainer.classList.remove('page-enter', 'is-animating');
          }, 230);
        }, 170);
      }

      function renderCardsPage(animate = true) {
        if (!allItems || allItems.length === 0) {
          cardsContainer.innerHTML = `
        <div class="empty-box" style="grid-column:1/-1;">
          <svg viewBox="0 0 24 24" width="52" height="52" aria-hidden="true">
            <path fill="#94a3b8" d="M10.5 3a7.5 7.5 0 1 1 0 15a7.5 7.5 0 0 1 0-15Zm0 2a5.5 5.5 0 1 0 0 11a5.5 5.5 0 0 0 0-11Zm8.85 12.44l2.86 2.85a1 1 0 0 1-1.42 1.42l-2.85-2.86a1 1 0 0 1 1.41-1.41Z"/>
          </svg>
          <div>Nenhum resultado encontrado.</div>
          <div style="font-size:13px;font-weight:600;">Tente alterar o setor, o modo ou o termo da busca.</div>
        </div>
      `;
          cardsNav.style.display = 'none';
          return;
        }

        totalPages = Math.max(1, Math.ceil(allItems.length / cardsPerPage));
        if (page > totalPages) page = totalPages;
        if (page < 1) page = 1;

        const start = (page - 1) * cardsPerPage;
        const chunk = allItems.slice(start, start + cardsPerPage);

        const doRender = () => {
          cardsContainer.innerHTML = '';

          chunk.forEach((it, idx) => {
            const nm = simpleName(it.name);
            const absoluteIndex = start + idx;
            const card = document.createElement('div');
            card.className = 'card';

            const posClass = rankClass(absoluteIndex);
            const badgeClass = posClass ? `card-rank ${posClass}` : 'card-rank';

            card.innerHTML = `
          <div class="${badgeClass}">#${absoluteIndex + 1}</div>
          <div class="avatar">${avatarHtml(it.avatar, it.name, 56)}</div>
          <div class="card-name" title="${esc(it.name)}">${esc(nm)}</div>
          <div class="card-sector" title="${esc(it.sector || '')}">${esc(it.sector || 'Sem setor')}</div>
          <div class="coin-pill">🪙 ${formatInt(it.coins)}</div>
        `;

            cardsContainer.appendChild(card);
          });

          cardsNav.style.display = totalPages > 1 ? 'flex' : 'none';
          prevPageBtn.disabled = (page <= 1);
          nextPageBtn.disabled = (page >= totalPages);
          pageIndicator.textContent = `${page} / ${totalPages}`;
        };

        if (!animate) doRender();
        else animateCardsSwap(doRender);
      }

      function renderRanking(items) {
        if (!items || items.length === 0) {
          rankingList.innerHTML = `
        <div class="empty-box">
          <svg viewBox="0 0 24 24" width="52" height="52" aria-hidden="true">
            <path fill="#94a3b8" d="M19 3H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h3.59L12 20.41L15.41 17H19a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2Z"/>
          </svg>
          <div>Sem dados para o ranking.</div>
        </div>
      `;
          return;
        }

        const topCoins = Math.max(...items.map(x => num(x.coins)), 0) || 1;
        rankingList.innerHTML = '';

        items.slice(0, 10).forEach((it, idx) => {
          const pct = Math.max(0, Math.min(100, (num(it.coins) / topCoins) * 100));
          const medal = medalForIndex(idx);
          const nm = simpleName(it.name);
          const row = document.createElement('div');
          row.className = 'rank-item';

          const leftHtml = medal
            ? `<div class="trophy-medal" title="${esc(it.position)}º">${medal}</div>`
            : `<div class="badge" title="${esc(it.position)}º">${esc(it.position)}</div>`;

          row.innerHTML = `
        ${leftHtml}
        <div class="rank-avatar">${avatarHtml(it.avatar, it.name, 28)}</div>
        <div style="min-width:0">
          <div class="rank-name" title="${esc(it.name)}">${esc(nm)}</div>
          <div class="bar"><span style="width:${pct.toFixed(0)}%"></span></div>
        </div>
        <div class="rank-coins">${formatInt(it.coins)}</div>
      `;

          rankingList.appendChild(row);
        });
      }

      async function refresh() {
        saveState();
        setActiveTabs();
        updateSearchClearButton();

        showCardsLoading();
        showRankingLoading();
        page = 1;

        await loadTotal();

        const data = await loadAllRanking();
        const normalized = (data.items || []).map(normalizeItem);
        allItems = sortItems(normalized);

        renderKPIs(allItems);
        renderCardsPage(false);
        renderRanking(allItems);
      }

      function applyStateToControls() {
        filterInput.value = q;
        sortByEl.value = sortBy;
        pageSizeEl.value = String(cardsPerPage);
        sectorEl.value = sector;
        setActiveTabs();
        updateSearchClearButton();
      }

      tabAll.addEventListener('click', () => {
        mode = 'all';
        refresh();
      });

      tabMonth.addEventListener('click', () => {
        mode = 'month';
        refresh();
      });

      sectorEl.addEventListener('change', () => {
        sector = sectorEl.value;
        refresh();
      });

      sortByEl.addEventListener('change', () => {
        sortBy = sortByEl.value;
        allItems = sortItems(allItems);
        renderKPIs(allItems);
        page = 1;
        renderCardsPage(false);
        renderRanking(allItems);
        saveState();
      });

      pageSizeEl.addEventListener('change', () => {
        cardsPerPage = Number(pageSizeEl.value) || 12;
        page = 1;
        renderCardsPage(false);
        saveState();
      });

      let debounce = null;
      filterInput.addEventListener('input', () => {
        q = filterInput.value.trim();
        updateSearchClearButton();
        clearTimeout(debounce);
        debounce = setTimeout(refresh, 250);
      });

      filterInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          q = filterInput.value.trim();
          clearTimeout(debounce);
          refresh();
        }
      });

      clearSearchBtn.addEventListener('click', () => {
        filterInput.value = '';
        q = '';
        updateSearchClearButton();
        refresh();
        filterInput.focus();
      });

      prevPageBtn.addEventListener('click', () => {
        if (page <= 1) return;
        page--;
        renderCardsPage(true);
      });

      nextPageBtn.addEventListener('click', () => {
        if (page >= totalPages) return;
        page++;
        renderCardsPage(true);
      });

      loadState();
      setActiveTabs();

      loadSectors().then(() => {
        applyStateToControls();
        refresh();
      });
    })();
  </script>
</body>

</html>