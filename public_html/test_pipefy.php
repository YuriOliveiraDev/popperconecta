<?php
declare(strict_types=1);
require_once __DIR__ . '/app/pipefy-rh.php';

try {

    $card = pipefy_create_rh_redemption_card([
        'title' => 'TESTE POPPER COINS',
        'descricao' => 'Teste integração intranet',
        'informacoes_adicionais' =>
            "Solicitante real: Yuri\n" .
            "Item: Vale iFood\n" .
            "Coins: 300\n" .
            "Data: " . date('d/m/Y H:i')
    ]);

    echo "<pre>";
    print_r($card);

} catch (Throwable $e) {

    echo "Erro:\n";
    echo $e->getMessage();

}