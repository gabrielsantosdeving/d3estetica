<?php
/**
 * ============================================
 * HELPER MERCADO PAGO
 * ============================================
 * 
 * Classe para gerenciar integração automática com Mercado Pago
 * Gera links de pagamento automaticamente quando valores são inseridos/atualizados
 * 
 * @file MercadoPagoHelper.php
 * @package D3Estetica
 * @version 1.0
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

// Carregar SDK do Mercado Pago via Composer
$autoloadPath = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
} else {
    // Tentar caminho alternativo
    $autoloadPath = dirname(__DIR__) . '/../vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
    }
}

// Importar classes do MercadoPago (se disponíveis)
// Se o autoload não foi carregado, as classes não existirão e serão tratadas nos métodos
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;

class MercadoPagoHelper {
    
    private $accessToken;
    private $conn;
    
    public function __construct() {
        $this->accessToken = MERCADOPAGO_ACCESS_TOKEN;
        $this->conn = getDB();
        
        // Configurar SDK do Mercado Pago (se disponível)
        if (!empty($this->accessToken) && class_exists('MercadoPago\MercadoPagoConfig')) {
            try {
                MercadoPagoConfig::setAccessToken($this->accessToken);
            } catch (Exception $e) {
                error_log('Erro ao configurar MercadoPagoConfig: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Gera ou atualiza link de pagamento automaticamente
     * 
     * @param string $tipo Tipo do item: 'servico' ou 'plano_vip'
     * @param int $itemId ID do serviço ou plano
     * @param string $itemNome Nome do item
     * @param float $valor Valor do pagamento
     * @param array $clienteDados Dados opcionais do cliente (nome, email, id)
     * @return array|false Retorna array com link_pagamento e preference_id ou false em caso de erro
     */
    public function gerarLinkPagamento($tipo, $itemId, $itemNome, $valor, $clienteDados = []) {
        try {
            // Verificar se o SDK do MercadoPago está disponível
            if (!class_exists('MercadoPago\Client\Preference\PreferenceClient')) {
                error_log('SDK do MercadoPago não está disponível. Execute: composer install');
                return false;
            }
            
            // Verificar se já existe um pedido ativo para este item
            $pedidoExistente = $this->buscarPedidoAtivo($tipo, $itemId);
            
            // Se existe pedido e o valor não mudou, retornar link existente
            if ($pedidoExistente && $pedidoExistente['valor'] == $valor && !empty($pedidoExistente['link_pagamento'])) {
                return [
                    'link_pagamento' => $pedidoExistente['link_pagamento'],
                    'preference_id' => $pedidoExistente['mercado_pago_preference_id'],
                    'pedido_id' => $pedidoExistente['id']
                ];
            }
            
            // Criar preferência no Mercado Pago
            $preferenceClient = new PreferenceClient();
            
            $preferenceData = [
                'items' => [
                    [
                        'title' => $itemNome,
                        'quantity' => 1,
                        'unit_price' => (float)$valor,
                        'currency_id' => 'BRL'
                    ]
                ],
                'back_urls' => [
                    'success' => MERCADOPAGO_SUCCESS_URL,
                    'pending' => MERCADOPAGO_PENDING_URL,
                    'failure' => MERCADOPAGO_FAILURE_URL
                ],
                'auto_return' => 'approved',
                'notification_url' => MERCADOPAGO_WEBHOOK_URL,
                'external_reference' => "{$tipo}_{$itemId}_" . time(),
                'statement_descriptor' => 'D3 ESTETICA'
            ];
            
            // Adicionar dados do cliente se fornecidos
            if (!empty($clienteDados['email'])) {
                $preferenceData['payer'] = [
                    'email' => $clienteDados['email'],
                    'name' => $clienteDados['nome'] ?? null
                ];
            }
            
            $preference = $preferenceClient->create($preferenceData);
            
            if (!$preference || !isset($preference->id)) {
                error_log("Erro ao criar preferência Mercado Pago: " . json_encode($preference));
                return false;
            }
            
            $preferenceId = $preference->id;
            $linkPagamento = $preference->init_point ?? $preference->sandbox_init_point ?? null;
            
            if (!$linkPagamento) {
                error_log("Erro: Link de pagamento não retornado pelo Mercado Pago");
                return false;
            }
            
            // Salvar ou atualizar pedido no banco de dados
            if ($pedidoExistente) {
                // Atualizar pedido existente
                $stmt = $this->conn->prepare("
                    UPDATE pedidos 
                    SET valor = ?, 
                        mercado_pago_preference_id = ?, 
                        link_pagamento = ?,
                        status = 'pendente',
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $valor,
                    $preferenceId,
                    $linkPagamento,
                    $pedidoExistente['id']
                ]);
                $pedidoId = $pedidoExistente['id'];
            } else {
                // Criar novo pedido
                $stmt = $this->conn->prepare("
                    INSERT INTO pedidos 
                    (tipo, item_id, item_nome, valor, cliente_id, cliente_nome, cliente_email, 
                     mercado_pago_preference_id, link_pagamento, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente')
                ");
                $stmt->execute([
                    $tipo,
                    $itemId,
                    $itemNome,
                    $valor,
                    $clienteDados['id'] ?? null,
                    $clienteDados['nome'] ?? null,
                    $clienteDados['email'] ?? null,
                    $preferenceId,
                    $linkPagamento
                ]);
                $pedidoId = $this->conn->lastInsertId();
            }
            
            return [
                'link_pagamento' => $linkPagamento,
                'preference_id' => $preferenceId,
                'pedido_id' => $pedidoId
            ];
            
        } catch (Exception $e) {
            error_log("Erro ao gerar link Mercado Pago: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Busca pedido ativo para um item
     */
    private function buscarPedidoAtivo($tipo, $itemId) {
        $stmt = $this->conn->prepare("
            SELECT * FROM pedidos 
            WHERE tipo = ? AND item_id = ? AND status = 'pendente'
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$tipo, $itemId]);
        return $stmt->fetch();
    }
    
    /**
     * Processa notificação do webhook do Mercado Pago
     */
    public function processarWebhook($data) {
        try {
            if (!isset($data['type']) || !isset($data['data']['id'])) {
                return false;
            }
            
            $type = $data['type'];
            $paymentId = $data['data']['id'];
            
            if ($type !== 'payment') {
                return false;
            }
            
            // Verificar se o SDK do MercadoPago está disponível
            if (!class_exists('MercadoPago\Client\Payment\PaymentClient')) {
                error_log('SDK do MercadoPago não está disponível. Execute: composer install');
                return false;
            }
            
            // Buscar informações do pagamento no Mercado Pago
            $paymentClient = new PaymentClient();
            $payment = $paymentClient->get($paymentId);
            
            if (!$payment) {
                return false;
            }
            
            $preferenceId = $payment->preference_id ?? null;
            $status = $this->mapearStatusPagamento($payment->status ?? '');
            $externalReference = $payment->external_reference ?? '';
            
            // Buscar pedido pelo preference_id
            $stmt = $this->conn->prepare("
                SELECT * FROM pedidos 
                WHERE mercado_pago_preference_id = ? 
                LIMIT 1
            ");
            $stmt->execute([$preferenceId]);
            $pedido = $stmt->fetch();
            
            if ($pedido) {
                // Atualizar status do pedido
                $stmt = $this->conn->prepare("
                    UPDATE pedidos 
                    SET mercado_pago_payment_id = ?, 
                        status = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $paymentId,
                    $status,
                    $pedido['id']
                ]);
                
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Erro ao processar webhook Mercado Pago: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mapeia status do Mercado Pago para status interno
     */
    private function mapearStatusPagamento($statusMercadoPago) {
        $statusMap = [
            'approved' => 'aprovado',
            'pending' => 'pendente',
            'rejected' => 'rejeitado',
            'cancelled' => 'cancelado',
            'refunded' => 'reembolsado'
        ];
        
        return $statusMap[$statusMercadoPago] ?? 'pendente';
    }
    
    /**
     * Busca link de pagamento existente para um item
     */
    public function buscarLinkPagamento($tipo, $itemId) {
        $pedido = $this->buscarPedidoAtivo($tipo, $itemId);
        
        if ($pedido && !empty($pedido['link_pagamento'])) {
            return [
                'link_pagamento' => $pedido['link_pagamento'],
                'preference_id' => $pedido['mercado_pago_preference_id'],
                'pedido_id' => $pedido['id']
            ];
        }
        
        return false;
    }
}

