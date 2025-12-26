<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Iniciar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/auth/auth-functions.php';
require_once dirname(__DIR__) . '/lib/MercadoPagoHelper.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Planos VIP podem ser acessados sem autenticação para exibição pública
$method = $_SERVER['REQUEST_METHOD'];

// Obter conexão com tratamento de erro
try {
    $conn = getDB();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao conectar com o banco de dados: ' . $e->getMessage()
    ]);
    exit();
}

try {
    switch ($method) {
        case 'GET':
            $id = $_GET['id'] ?? null;
            
            if ($id) {
                $stmt = $conn->prepare("SELECT * FROM planos_vip WHERE id = ? AND status = 'ativo'");
                $stmt->execute([$id]);
                $plano = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($plano) {
                    // Buscar link de pagamento automaticamente
                    $mpHelper = new MercadoPagoHelper();
                    $linkPagamento = $mpHelper->buscarLinkPagamento('plano_vip', $plano['id']);
                    
                    if ($linkPagamento) {
                        $plano['link_pagamento'] = $linkPagamento['link_pagamento'];
                        $plano['preference_id'] = $linkPagamento['preference_id'];
                    } else {
                        // Gerar link automaticamente se não existir
                        $linkPagamento = $mpHelper->gerarLinkPagamento(
                            'plano_vip',
                            $plano['id'],
                            $plano['nome'],
                            $plano['preco']
                        );
                        if ($linkPagamento) {
                            $plano['link_pagamento'] = $linkPagamento['link_pagamento'];
                            $plano['preference_id'] = $linkPagamento['preference_id'];
                        }
                    }
                    
                    echo json_encode(['success' => true, 'data' => $plano]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Plano não encontrado']);
                }
            } else {
                // Admin pode ver todos os planos (ativos e inativos)
                $stmt = $conn->query("SELECT * FROM planos_vip ORDER BY preco ASC");
                $planos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!is_array($planos)) {
                    $planos = [];
                }
                
                // Adicionar links de pagamento automaticamente para cada plano
                $mpHelper = new MercadoPagoHelper();
                foreach ($planos as &$plano) {
                    $linkPagamento = $mpHelper->buscarLinkPagamento('plano_vip', $plano['id']);
                    
                    if ($linkPagamento) {
                        $plano['link_pagamento'] = $linkPagamento['link_pagamento'];
                        $plano['preference_id'] = $linkPagamento['preference_id'];
                    } else {
                        // Gerar link automaticamente se não existir
                        $linkPagamento = $mpHelper->gerarLinkPagamento(
                            'plano_vip',
                            $plano['id'],
                            $plano['nome'],
                            $plano['preco']
                        );
                        if ($linkPagamento) {
                            $plano['link_pagamento'] = $linkPagamento['link_pagamento'];
                            $plano['preference_id'] = $linkPagamento['preference_id'];
                        }
                    }
                }
                
                echo json_encode(['success' => true, 'data' => $planos]);
            }
            break;
            
        case 'POST':
            checkAuth();
            checkAdmin();
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data) || empty($data['nome']) || empty($data['tipo']) || !isset($data['preco'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Campos obrigatórios: nome, tipo, preco']);
                break;
            }
            
            // Validar preço
            $preco = floatval($data['preco']);
            if ($preco < 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Preço não pode ser negativo']);
                break;
            }
            
            // Validar desconto
            $desconto = floatval($data['desconto_percentual'] ?? 0);
            if ($desconto < 0 || $desconto > 100) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Desconto deve estar entre 0 e 100']);
                break;
            }
            
            // Validar tipo
            $tipo = $data['tipo'];
            $allowedTipos = ['mensal', 'trimestral', 'semestral', 'anual'];
            if (!in_array($tipo, $allowedTipos)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Tipo de plano inválido']);
                break;
            }
            
            $stmt = $conn->prepare("INSERT INTO planos_vip (nome, tipo, preco, desconto_percentual, beneficios, destaque, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                trim($data['nome']),
                $tipo,
                $preco,
                $desconto,
                isset($data['beneficios']) ? trim($data['beneficios']) : '',
                isset($data['destaque']) ? (bool)$data['destaque'] : false,
                $data['status'] ?? 'ativo'
            ]);
            
            $id = $conn->lastInsertId();
            
            // Gerar link de pagamento automaticamente
            $mpHelper = new MercadoPagoHelper();
            $linkPagamento = $mpHelper->gerarLinkPagamento(
                'plano_vip',
                $id,
                $data['nome'],
                $data['preco']
            );
            
            $response = ['success' => true, 'id' => $id];
            if ($linkPagamento) {
                $response['link_pagamento'] = $linkPagamento['link_pagamento'];
                $response['preference_id'] = $linkPagamento['preference_id'];
            }
            
            echo json_encode($response);
            break;
            
        case 'PUT':
            checkAuth();
            checkAdmin();
            
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
                break;
            }
            
            // Validar ID (deve ser numérico)
            if (!is_numeric($id) || $id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID inválido']);
                break;
            }
            
            $id = intval($id);
            
            // Validar preço
            $preco = floatval($data['preco'] ?? 0);
            if ($preco < 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Preço não pode ser negativo']);
                break;
            }
            
            // Validar desconto
            $desconto = floatval($data['desconto_percentual'] ?? 0);
            if ($desconto < 0 || $desconto > 100) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Desconto deve estar entre 0 e 100']);
                break;
            }
            
            // Validar tipo
            $tipo = $data['tipo'] ?? '';
            $allowedTipos = ['mensal', 'trimestral', 'semestral', 'anual'];
            if (!in_array($tipo, $allowedTipos)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Tipo de plano inválido']);
                break;
            }
            
            // Buscar preço antigo para verificar se mudou
            $stmt = $conn->prepare("SELECT preco, nome FROM planos_vip WHERE id = ?");
            $stmt->execute([$id]);
            $planoAntigo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $conn->prepare("UPDATE planos_vip SET nome = ?, tipo = ?, preco = ?, desconto_percentual = ?, beneficios = ?, destaque = ?, status = ? WHERE id = ?");
            $stmt->execute([
                trim($data['nome'] ?? ''),
                $tipo,
                $preco,
                $desconto,
                isset($data['beneficios']) ? trim($data['beneficios']) : '',
                isset($data['destaque']) ? (bool)$data['destaque'] : false,
                $data['status'] ?? 'ativo',
                $id
            ]);
            
            // Se o preço mudou, gerar novo link de pagamento automaticamente
            if ($planoAntigo && $planoAntigo['preco'] != $data['preco']) {
                $mpHelper = new MercadoPagoHelper();
                $linkPagamento = $mpHelper->gerarLinkPagamento(
                    'plano_vip',
                    $id,
                    $data['nome'],
                    $data['preco']
                );
                
                $response = ['success' => true];
                if ($linkPagamento) {
                    $response['link_pagamento'] = $linkPagamento['link_pagamento'];
                    $response['preference_id'] = $linkPagamento['preference_id'];
                }
                echo json_encode($response);
            } else {
                echo json_encode(['success' => true]);
            }
            break;
            
        case 'DELETE':
            checkAuth();
            checkAdmin();
            
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
                break;
            }
            
            // Validar ID (deve ser numérico)
            if (!is_numeric($id) || $id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID inválido']);
                break;
            }
            
            $id = intval($id);
            
            $stmt = $conn->prepare("DELETE FROM planos_vip WHERE id = ?");
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Plano deletado com sucesso']);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Plano não encontrado']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro no banco de dados: ' . $e->getMessage(),
        'code' => $e->getCode()
    ]);
    error_log('Erro em planos-vip.php: ' . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro: ' . $e->getMessage()
    ]);
    error_log('Erro em planos-vip.php: ' . $e->getMessage());
}
?>



