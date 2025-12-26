<?php
/**
 * ============================================
 * WEBHOOK MERCADO PAGO
 * ============================================
 * 
 * Recebe notificações do Mercado Pago sobre status de pagamento
 * Atualiza automaticamente o status dos pedidos no banco de dados
 * 
 * @file mercadopago-webhook.php
 * @package D3Estetica
 * @version 1.0
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once dirname(__DIR__) . '/lib/MercadoPagoHelper.php';

try {
    // Receber dados do webhook
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Se não for JSON, tentar receber via GET (modo de teste do Mercado Pago)
    if (!$data && isset($_GET['data_id']) && isset($_GET['type'])) {
        $data = [
            'type' => $_GET['type'],
            'data' => [
                'id' => $_GET['data_id']
            ]
        ];
    }
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        exit();
    }
    
    // Processar webhook
    $mpHelper = new MercadoPagoHelper();
    $resultado = $mpHelper->processarWebhook($data);
    
    if ($resultado) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Webhook processado com sucesso']);
    } else {
        http_response_code(200); // Sempre retornar 200 para o Mercado Pago
        echo json_encode(['success' => false, 'message' => 'Webhook não processado']);
    }
    
} catch (Exception $e) {
    error_log("Erro no webhook Mercado Pago: " . $e->getMessage());
    http_response_code(200); // Sempre retornar 200 para o Mercado Pago
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}


