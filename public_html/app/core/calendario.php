<?php
/**
 * app/calendario.php
 * Dias úteis: Seg-Sex, exceto feriados/recessos/férias coletivas.
 */

date_default_timezone_set('America/Sao_Paulo');

// =========================
// DIAS NÃO TRABALHADOS (CALENDÁRIO POPPER 2026)
// Formato: 'YYYY-MM-DD' => 'Motivo'
// =========================
$DIAS_NAO_UTEIS = [
  // Janeiro 2026 - Férias coletivas (1 a 4 jan)
  '2026-01-01' => 'Férias coletivas',
  '2026-01-02' => 'Férias coletivas',
  '2026-01-03' => 'Férias coletivas',
  '2026-01-04' => 'Férias coletivas',

  // Fevereiro 2026 - Recesso de Carnaval (14 a 17 fev)
  '2026-02-14' => 'Recesso de Carnaval',
  '2026-02-15' => 'Recesso de Carnaval',
  '2026-02-16' => 'Recesso de Carnaval',
  '2026-02-17' => 'Recesso de Carnaval',

  // Abril 2026
  '2026-04-03' => 'Feriado',
  '2026-04-21' => 'Feriado',

  // Maio 2026
  '2026-05-01' => 'Feriado',

  // Junho 2026
  '2026-06-04' => 'Feriado',

  // Setembro 2026 - Recesso (7 a 8 set)
  '2026-09-07' => 'Recesso',
  '2026-09-08' => 'Recesso',

  // Outubro 2026
  '2026-10-12' => 'Feriado',

  // Novembro 2026
  '2026-11-02' => 'Feriado',
  '2026-11-15' => 'Feriado',
  '2026-11-20' => 'Feriado',

  // Dezembro 2026 - Férias coletivas (21 dez a 04 jan 2027)
  '2026-12-21' => 'Férias coletivas',
  '2026-12-22' => 'Férias coletivas',
  '2026-12-23' => 'Férias coletivas',
  '2026-12-24' => 'Férias coletivas',
  '2026-12-25' => 'Férias coletivas',
  '2026-12-26' => 'Férias coletivas',
  '2026-12-27' => 'Férias coletivas',
  '2026-12-28' => 'Férias coletivas',
  '2026-12-29' => 'Férias coletivas',
  '2026-12-30' => 'Férias coletivas',
  '2026-12-31' => 'Férias coletivas',

  // Janeiro 2027 - continuação férias coletivas
  '2027-01-01' => 'Férias coletivas',
  '2027-01-02' => 'Férias coletivas',
  '2027-01-03' => 'Férias coletivas',
  '2027-01-04' => 'Férias coletivas',
];

// =========================
// EXCEÇÕES (OPCIONAL)
// Se em algum sábado trabalhar, coloque aqui como true.
// Se em algum dia útil normal tiver folga extra, coloque em $DIAS_NAO_UTEIS.
// =========================
$EXCECOES_TRABALHA = [
  // '2026-12-19' => true, // exemplo: sábado trabalhado
];

// =========================
// FUNÇÕES
// =========================
function is_weekend(string $ymd): bool {
  $ts = strtotime($ymd . ' 00:00:00');
  if (!$ts) return false;
  $dow = (int)date('N', $ts); // 6=sab,7=dom
  return ($dow >= 6);
}

function is_dia_util(string $ymd): bool {
  global $DIAS_NAO_UTEIS, $EXCECOES_TRABALHA;

  // valida formato básico
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) return false;

  // exceção: trabalha mesmo sendo sábado/domingo/feriado
  if (!empty($EXCECOES_TRABALHA[$ymd])) return true;

  // fim de semana
  if (is_weekend($ymd)) return false;

  // feriado/recesso/férias
  if (isset($DIAS_NAO_UTEIS[$ymd])) return false;

  return true;
}

/**
 * Conta dias úteis de um período [start, end] (inclusive)
 */
function contar_dias_uteis(string $startYmd, string $endYmd): int {
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startYmd)) return 0;
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endYmd)) return 0;

  if ($endYmd < $startYmd) return 0;

  $count = 0;
  $d = $startYmd;
  while ($d <= $endYmd) {
    if (is_dia_util($d)) $count++;
    $d = date('Y-m-d', strtotime($d . ' +1 day'));
  }
  return $count;
}

/**
 * Retorna dias úteis totais do mês
 */
function dias_uteis_no_mes(int $ano, int $mes): int {
  $from = sprintf('%04d-%02d-01', $ano, $mes);
  $to = date('Y-m-t', strtotime($from));
  return contar_dias_uteis($from, $to);
}

/**
 * Retorna dias úteis do mês até uma data (inclusive)
 * Se $ymd estiver fora do mês, ele limita automaticamente ao último dia do mês.
 */
function dias_uteis_no_mes_ate(string $ymd): int {
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) return 0;

  $ano = (int)substr($ymd, 0, 4);
  $mes = (int)substr($ymd, 5, 2);

  $from = sprintf('%04d-%02d-01', $ano, $mes);
  $last = date('Y-m-t', strtotime($from));
  $end = ($ymd > $last) ? $last : $ymd;

  return contar_dias_uteis($from, $end);
}

/**
 * Atalho: retorna [dias_uteis_trabalhados, dias_uteis_trabalhar] do mês atual (até hoje)
 */
function dias_uteis_mes_ate_hoje(): array {
  $today = date('Y-m-d');
  $ano = (int)date('Y');
  $mes = (int)date('m');

  $trabalhados = dias_uteis_no_mes_ate($today);
  $total = dias_uteis_no_mes($ano, $mes);

  return [$trabalhados, $total];
}