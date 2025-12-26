<?php
/**
 * API de Formulários - D3 Estética
 * Gerencia CRUD de formulários (dados sincronizados de agendamentos)
 */

// Iniciar buffer de saída para evitar qualquer output antes do JSON
ob_start();

// Iniciar sessão ANTES de qualquer header ou output
if (session_status() === PHP_SESSION_NONE) {
    if (session_name() !== 'PHPSESSID') {
        session_name('PHPSESSID');
    }
    session_start();
}

// Limpar buffer antes de enviar headers
ob_clean();

// Agora podemos enviar os headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Usar caminho absoluto baseado no document root para compatibilidade com Hostinger
$configPath = $_SERVER['DOCUMENT_ROOT'] . '/backend/config/database.php';
if (!file_exists($configPath)) {
    $configPath = __DIR__ . '/../config/database.php';
}
require_once $configPath;

$authPath = $_SERVER['DOCUMENT_ROOT'] . '/backend/auth/auth-functions.php';
if (!file_exists($authPath)) {
    $authPath = __DIR__ . '/../auth/auth-functions.php';
}
if (file_exists($authPath)) {
    require_once $authPath;
}

// Obter conexão com tratamento de erro
try {
    $conn = getDB();
    
    // Verificar se a conexão está ativa
    if (!$conn) {
        throw new Exception('Conexão com banco de dados não disponível');
    }
    
    // Testar conexão com query simples
    $conn->query("SELECT 1");
    
} catch (PDOException $e) {
    ob_clean();
    http_response_code(500);
    error_log('Erro PDO em formularios.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao conectar com o banco de dados. Verifique as configurações.',
        'error_code' => $e->getCode()
    ], JSON_UNESCAPED_UNICODE);
    exit();
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    error_log('Erro em formularios.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao conectar com o banco de dados: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            $id = $_GET['id'] ?? null;
            $status = $_GET['status'] ?? null;
            
            if ($id) {
                // Buscar formulário específico da tabela formularios
                // Se não encontrar, buscar de agendamentos como fallback
                try {
                    $stmt = $conn->prepare("SELECT * FROM formularios WHERE id = ?");
                    $stmt->execute([$id]);
                    $formulario = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Se não encontrou na tabela formularios, buscar de agendamentos
                    if (!$formulario) {
                        error_log("Formulário ID {$id} não encontrado em formularios, buscando em agendamentos...");
                        $fallbackStmt = $conn->prepare("
                            SELECT 
                                a.id,
                                a.servico_id,
                                a.nome,
                                a.email,
                                a.telefone,
                                a.regiao,
                                a.bairro,
                                a.status,
                                a.observacoes,
                                a.data_agendamento,
                                a.hora_agendamento,
                                s.nome as servico_nome,
                                s.preco as servico_preco,
                                s.imagem_path as servico_imagem,
                                s.descricao as servico_descricao,
                                s.categoria as servico_categoria,
                                a.created_at,
                                a.updated_at
                            FROM agendamentos a
                            LEFT JOIN servicos s ON a.servico_id = s.id
                            WHERE a.id = ?
                        ");
                        $fallbackStmt->execute([$id]);
                        $formulario = $fallbackStmt->fetch(PDO::FETCH_ASSOC);
                    }
                    
                    if ($formulario) {
                        ob_clean();
                        echo json_encode(['success' => true, 'data' => $formulario], JSON_UNESCAPED_UNICODE);
                    } else {
                        ob_clean();
                        http_response_code(404);
                        echo json_encode(['success' => false, 'message' => 'Formulário não encontrado'], JSON_UNESCAPED_UNICODE);
                    }
                } catch (PDOException $e) {
                    error_log('Erro ao buscar formulário: ' . $e->getMessage());
                    ob_clean();
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Erro ao buscar formulário: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
                }
            } else {
                // Verificar se a tabela formularios existe
                $tableExists = false;
                try {
                    $checkTable = $conn->query("SHOW TABLES LIKE 'formularios'");
                    $tableExists = $checkTable->rowCount() > 0;
                } catch (PDOException $e) {
                    error_log('Erro ao verificar tabela: ' . $e->getMessage());
                }
                
                if (!$tableExists) {
                    ob_clean();
                    http_response_code(500);
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Tabela "formularios" não encontrada. Execute o script SQL: backend/database/formularios.sql',
                        'data' => [],
                        'error_type' => 'table_not_found'
                    ], JSON_UNESCAPED_UNICODE);
                    break;
                }
                
                // Verificar se há formulários na tabela, se não houver, sincronizar de agendamentos
                try {
                    $countStmt = $conn->query("SELECT COUNT(*) as total FROM formularios");
                    $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
                    $totalFormularios = (int)$countResult['total'];
                    
                    // Se não houver formulários, tentar sincronizar de agendamentos
                    if ($totalFormularios === 0) {
                        error_log('Tabela formularios vazia, sincronizando de agendamentos...');
                        try {
                            // Verificar se a tabela agendamentos existe e tem dados
                            $checkAgendamentos = $conn->query("SELECT COUNT(*) as total FROM agendamentos");
                            $agendamentosCount = $checkAgendamentos->fetch(PDO::FETCH_ASSOC);
                            
                            if ((int)$agendamentosCount['total'] > 0) {
                                $syncStmt = $conn->prepare("
                                    INSERT INTO formularios (
                                        agendamento_id, servico_id, nome, email, telefone, regiao, bairro,
                                        status, observacoes, data_agendamento, hora_agendamento,
                                        servico_nome, servico_preco, servico_imagem, servico_descricao, servico_categoria,
                                        created_at, sincronizado_at
                                    )
                                    SELECT 
                                        a.id,
                                        a.servico_id,
                                        a.nome,
                                        a.email,
                                        a.telefone,
                                        a.regiao,
                                        a.bairro,
                                        a.status,
                                        a.observacoes,
                                        a.data_agendamento,
                                        a.hora_agendamento,
                                        s.nome as servico_nome,
                                        s.preco as servico_preco,
                                        s.imagem_path as servico_imagem,
                                        s.descricao as servico_descricao,
                                        s.categoria as servico_categoria,
                                        a.created_at,
                                        NOW()
                                    FROM agendamentos a
                                    LEFT JOIN servicos s ON a.servico_id = s.id
                                    WHERE a.id NOT IN (SELECT agendamento_id FROM formularios WHERE agendamento_id IS NOT NULL)
                                ");
                                $syncStmt->execute();
                                $sincronizados = $syncStmt->rowCount();
                                error_log("Sincronizados {$sincronizados} agendamentos para formularios");
                            }
                        } catch (PDOException $syncError) {
                            error_log('Erro ao sincronizar agendamentos: ' . $syncError->getMessage());
                            // Continuar mesmo se a sincronização falhar
                        }
                    }
                } catch (PDOException $e) {
                    error_log('Erro ao contar formulários: ' . $e->getMessage());
                }
                
                // Buscar todos os formulários da tabela formularios
                // Se não houver dados, buscar diretamente de agendamentos como fallback
                $query = "SELECT * FROM formularios";
                $params = [];
                $conditions = [];
                
                if ($status) {
                    $conditions[] = "status = ?";
                    $params[] = $status;
                }
                
                if (!empty($conditions)) {
                    $query .= " WHERE " . implode(" AND ", $conditions);
                }
                
                $query .= " ORDER BY created_at DESC";
                
                try {
                    $stmt = $conn->prepare($query);
                    $stmt->execute($params);
                    $formularios = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!is_array($formularios)) {
                        $formularios = [];
                    }
                    
                    // Se não houver formulários na tabela, buscar diretamente de agendamentos
                    if (count($formularios) === 0) {
                        error_log('Nenhum formulário encontrado, buscando diretamente de agendamentos...');
                        $fallbackQuery = "SELECT 
                            a.id,
                            a.servico_id,
                            a.nome,
                            a.email,
                            a.telefone,
                            a.regiao,
                            a.bairro,
                            a.status,
                            a.observacoes,
                            a.data_agendamento,
                            a.hora_agendamento,
                            s.nome as servico_nome,
                            s.preco as servico_preco,
                            s.imagem_path as servico_imagem,
                            s.descricao as servico_descricao,
                            s.categoria as servico_categoria,
                            a.created_at,
                            a.updated_at
                        FROM agendamentos a
                        LEFT JOIN servicos s ON a.servico_id = s.id";
                        
                        $fallbackParams = [];
                        $fallbackConditions = [];
                        
                        if ($status) {
                            $fallbackConditions[] = "a.status = ?";
                            $fallbackParams[] = $status;
                        }
                        
                        if (!empty($fallbackConditions)) {
                            $fallbackQuery .= " WHERE " . implode(" AND ", $fallbackConditions);
                        }
                        
                        $fallbackQuery .= " ORDER BY a.created_at DESC";
                        
                        $fallbackStmt = $conn->prepare($fallbackQuery);
                        $fallbackStmt->execute($fallbackParams);
                        $formularios = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (!is_array($formularios)) {
                            $formularios = [];
                        }
                        
                        error_log('Formulários buscados de agendamentos (fallback): ' . count($formularios));
                    }
                    
                    // Log para debug
                    error_log('Formulários encontrados: ' . count($formularios));
                    if (count($formularios) > 0) {
                        error_log('Primeiro formulário: ' . json_encode($formularios[0]));
                    }
                    
                    ob_clean();
                    echo json_encode(['success' => true, 'data' => $formularios], JSON_UNESCAPED_UNICODE);
                } catch (PDOException $e) {
                    error_log('Erro ao buscar formulários: ' . $e->getMessage());
                    error_log('Query: ' . $query);
                    error_log('Params: ' . json_encode($params));
                    ob_clean();
                    http_response_code(500);
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Erro ao buscar formulários: ' . $e->getMessage(),
                        'data' => [],
                        'error_type' => 'query_error'
                    ], JSON_UNESCAPED_UNICODE);
                }
            }
            break;
            
        case 'POST':
            // Sincronizar dados de agendamentos para formularios
            // Verificar se é uma requisição de sincronização
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (isset($data['action']) && $data['action'] === 'sync') {
                try {
                    // Sincronizar todos os agendamentos que ainda não estão em formularios
                    $stmt = $conn->prepare("
                        INSERT INTO formularios (
                            agendamento_id, servico_id, nome, email, telefone, regiao, bairro,
                            status, observacoes, data_agendamento, hora_agendamento,
                            servico_nome, servico_preco, servico_imagem, servico_descricao, servico_categoria,
                            created_at, sincronizado_at
                        )
                        SELECT 
                            a.id,
                            a.servico_id,
                            a.nome,
                            a.email,
                            a.telefone,
                            a.regiao,
                            a.bairro,
                            a.status,
                            a.observacoes,
                            a.data_agendamento,
                            a.hora_agendamento,
                            s.nome as servico_nome,
                            s.preco as servico_preco,
                            s.imagem_path as servico_imagem,
                            s.descricao as servico_descricao,
                            s.categoria as servico_categoria,
                            a.created_at,
                            NOW()
                        FROM agendamentos a
                        LEFT JOIN servicos s ON a.servico_id = s.id
                        WHERE a.id NOT IN (SELECT agendamento_id FROM formularios WHERE agendamento_id IS NOT NULL)
                        ON DUPLICATE KEY UPDATE
                            servico_id = a.servico_id,
                            nome = a.nome,
                            email = a.email,
                            telefone = a.telefone,
                            regiao = a.regiao,
                            bairro = a.bairro,
                            status = a.status,
                            observacoes = a.observacoes,
                            data_agendamento = a.data_agendamento,
                            hora_agendamento = a.hora_agendamento,
                            servico_nome = s.nome,
                            servico_preco = s.preco,
                            servico_imagem = s.imagem_path,
                            servico_descricao = s.descricao,
                            servico_categoria = s.categoria,
                            sincronizado_at = NOW()
                    ");
                    
                    $stmt->execute();
                    $sincronizados = $stmt->rowCount();
                    
                    ob_clean();
                    echo json_encode([
                        'success' => true, 
                        'message' => "Sincronização concluída. {$sincronizados} formulários sincronizados.",
                        'sincronizados' => $sincronizados
                    ], JSON_UNESCAPED_UNICODE);
                } catch (PDOException $e) {
                    ob_clean();
                    http_response_code(500);
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Erro ao sincronizar: ' . $e->getMessage()
                    ], JSON_UNESCAPED_UNICODE);
                }
            } else {
                ob_clean();
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ação não especificada'], JSON_UNESCAPED_UNICODE);
            }
            break;
            
        case 'PUT':
            // Atualizar status ou outros campos do formulário
            checkAuth();
            checkAdmin();
            
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            
            if (!$id) {
                ob_clean();
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID não fornecido'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            try {
                $updates = [];
                $params = [];
                
                if (isset($data['status'])) {
                    $updates[] = "status = ?";
                    $params[] = $data['status'];
                }
                
                if (isset($data['observacoes'])) {
                    $updates[] = "observacoes = ?";
                    $params[] = $data['observacoes'];
                }
                
                if (empty($updates)) {
                    ob_clean();
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Nenhum campo para atualizar'], JSON_UNESCAPED_UNICODE);
                    break;
                }
                
                $params[] = $id;
                $query = "UPDATE formularios SET " . implode(", ", $updates) . " WHERE id = ?";
                
                $stmt = $conn->prepare($query);
                $stmt->execute($params);
                
                if ($stmt->rowCount() > 0) {
                    ob_clean();
                    echo json_encode(['success' => true, 'message' => 'Formulário atualizado com sucesso'], JSON_UNESCAPED_UNICODE);
                } else {
                    ob_clean();
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Formulário não encontrado'], JSON_UNESCAPED_UNICODE);
                }
            } catch (PDOException $e) {
                ob_clean();
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erro ao atualizar formulário: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
            break;
            
        case 'DELETE':
            checkAuth();
            checkAdmin();
            
            $id = $_GET['id'] ?? null;
            
            if (!$id) {
                ob_clean();
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID não fornecido'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            try {
                $stmt = $conn->prepare("DELETE FROM formularios WHERE id = ?");
                $stmt->execute([$id]);
                
                if ($stmt->rowCount() > 0) {
                    ob_clean();
                    echo json_encode(['success' => true, 'message' => 'Formulário deletado com sucesso'], JSON_UNESCAPED_UNICODE);
                } else {
                    ob_clean();
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Formulário não encontrado'], JSON_UNESCAPED_UNICODE);
                }
            } catch (PDOException $e) {
                ob_clean();
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erro ao deletar formulário: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
            break;
            
        default:
            ob_clean();
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método não permitido'], JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro no banco de dados: ' . $e->getMessage(),
        'code' => $e->getCode()
    ], JSON_UNESCAPED_UNICODE);
    error_log('Erro em formularios.php: ' . $e->getMessage());
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    error_log('Erro em formularios.php: ' . $e->getMessage());
}

