<?php
declare(strict_types=1);

$url = 'https://localhost/api/webhook_solides_pesquisa.php';

$payload = [
    'acao' => 'nova_resposta_pesquisa',
    'data_criacao' => '13/07/2020 16:22:59',
    'dados' => [
        'pesquisa_id' => 403370,
        'descricao_Pesquisa' => 'Pesquisa de clima',
        'titulo_pesquisa' => 'Carreira 2020',
        'criado_em' => '13/07/2020 20:22:59',
        'respostas' => [
            [
                'pergunta_id' => 123456,
                'pergunta' => 'Pergunta teste',
                'pergunta_posicao' => 0,
                'resposta' => 'Resposta teste',
                'alternativa_id' => null,
                'numero_alternativa' => null,
            ]
        ],
        'respondente' => [
            'id' => 123456,
            'nome' => 'Yuri de oliveira yang',
            'vinculo_externos' => [
                [
                    'externo_id' => null,
                    'target_system' => null,
                ]
            ]
        ]
    ]
];

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        // 'X-Webhook-Token: popper-solides-2026',
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
]);

$response = curl_exec($ch);
$error = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

echo '<pre>';
echo "HTTP: {$httpCode}\n\n";

if ($error) {
    echo "ERRO CURL: {$error}\n";
} else {
    echo $response;
}
echo '</pre>';