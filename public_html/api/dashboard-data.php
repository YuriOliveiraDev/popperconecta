<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$dashboard_slug = 'executivo';
$stmt = db()->prepare('SELECT metric_key, metric_value_num, metric_value_text FROM metrics WHERE dashboard_slug = ?');
$stmt->execute([$dashboard_slug]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$m = [];
foreach ($rows as $r) {
    $m[$r['metric_key']] = $r['metric_value_text'] ?? (float)$r['metric_value_num'];
}

// --- LÓGICA DE CÁLCULOS AUTOMÁTICOS ---

// 1. Ano
$meta_ano = (float)($m['meta_ano'] ?? 0);
$realizado_ano = (float)($m['realizado_ano_acum'] ?? 0);
$falta_ano = max(0, $meta_ano - $realizado_ano);

// 2. Mês
$meta_mes = (float)($m['meta_mes'] ?? 0);
$realizado_mes = (float)($m['realizado_ate_hoje'] ?? 0);
$falta_mes = max(0, $meta_mes - $realizado_mes);
$atingimento_mes_pct = ($meta_mes > 0) ? ($realizado_mes / $meta_mes) : 0;

// 3. Dias e Ritmo
$dias_totais = (int)($m['dias_uteis_trabalhar'] ?? 1);
$dias_passados = (int)($m['dias_uteis_trabalhados'] ?? 0);

// Quanto deveria ter hoje (proporcional aos dias trabalhados)
$deveria_ter_hoje = ($meta_mes / max(1, $dias_totais)) * $dias_passados;

// Ritmo Realizado por dia útil
$realizado_dia_util = ($dias_passados > 0) ? ($realizado_mes / $dias_passados) : 0;
$meta_dia_util = ($dias_totais > 0) ? ($meta_mes / $dias_totais) : 0;

// Produtividade (Ritmo Real vs Meta Diária)
$produtividade_pct = ($meta_dia_util > 0) ? ($realizado_dia_util / $meta_dia_util) : 0;

// A faturar por dia (o que resta dividido pelos dias que faltam)
$dias_restantes = max(1, $dias_totais - $dias_passados);
$a_faturar_por_dia = $falta_mes / $dias_restantes;

// Projeção de Fechamento
$projecao_fechamento = $realizado_dia_util * $dias_totais;
$equivale_pct = ($meta_mes > 0) ? ($projecao_fechamento / $meta_mes) : 0;
$vai_bater = ($projecao_fechamento >= $meta_mes) ? "SIM" : "NÃO";

// --- MONTAGEM DO JSON FINAL ---
$data = [
    'updated_at' => date('d/m/Y, H:i'),
    'values' => [
        // Ano
        'meta_ano' => $meta_ano,
        'realizado_ano_acum' => $realizado_ano,
        'falta_meta_ano' => $falta_ano,
        
        // Mês
        'meta_mes' => $meta_mes,
        'realizado_ate_hoje' => $realizado_mes,
        'falta_meta_mes' => $falta_mes,
        'atingimento_mes_pct' => $atingimento_mes_pct,
        'deveria_ate_hoje' => $deveria_ter_hoje,
        
        // Ritmo
        'meta_dia_util' => $meta_dia_util,
        'realizado_dia_util' => $realizado_dia_util,
        'realizado_dia_util_pct' => $produtividade_pct,
        'a_faturar_dia_util' => $a_faturar_por_dia,
        
        // Dias
        'dias_uteis_trabalhar' => $dias_totais,
        'dias_uteis_trabalhados' => $dias_passados,
        
        // Projeções
        'vai_bater_meta' => $vai_bater,
        'fechar_em' => $projecao_fechamento,
        'equivale_pct' => $equivale_pct
    ]
];

echo json_encode($data);