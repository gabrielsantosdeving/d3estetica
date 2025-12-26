<?php
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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

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
    error_log('Erro PDO em agendamentos.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao conectar com o banco de dados. Verifique as configurações.',
        'error_code' => $e->getCode()
    ], JSON_UNESCAPED_UNICODE);
    exit();
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    error_log('Erro em agendamentos.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao conectar com o banco de dados: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    switch ($method) {
        case 'GET':
            $id = $_GET['id'] ?? null;
            $status = $_GET['status'] ?? null;
            $data = $_GET['data'] ?? null;
            $hora = $_GET['hora'] ?? null;
            $servico_id = $_GET['servico_id'] ?? null;
            
            if ($id) {
                // Buscar agendamento com TODAS as informações do serviço
                $stmt = $conn->prepare("SELECT a.*, s.nome as servico_nome, s.preco as servico_preco, s.imagem_path as servico_imagem, s.descricao as servico_descricao, s.categoria as servico_categoria
                                       FROM agendamentos a 
                                       LEFT JOIN servicos s ON a.servico_id = s.id 
                                       WHERE a.id = ?");
                $stmt->execute([$id]);
                $agendamento = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($agendamento) {
                    ob_clean();
                    echo json_encode(['success' => true, 'data' => $agendamento], JSON_UNESCAPED_UNICODE);
                } else {
                    ob_clean();
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Agendamento não encontrado'], JSON_UNESCAPED_UNICODE);
                }
            } else if ($data && $hora) {
                // Verificar disponibilidade de data/hora específica
                $stmt = $conn->prepare("SELECT id FROM agendamentos 
                                       WHERE data_agendamento = ? 
                                       AND hora_agendamento = ? 
                                       AND status IN ('pendente', 'confirmado')");
                $stmt->execute([$data, $hora]);
                $conflito = $stmt->fetch(PDO::FETCH_ASSOC);
                
                ob_clean();
                echo json_encode([
                    'success' => true, 
                    'disponivel' => !$conflito,
                    'message' => $conflito ? 'Horário já ocupado' : 'Horário disponível'
                ], JSON_UNESCAPED_UNICODE);
                break;
            } else if ($data && !$hora) {
                // Retornar todos os horários ocupados para uma data específica
                $stmt = $conn->prepare("SELECT hora_agendamento 
                                       FROM agendamentos 
                                       WHERE data_agendamento = ? 
                                       AND hora_agendamento IS NOT NULL
                                       AND status IN ('pendente', 'confirmado')
                                       ORDER BY hora_agendamento");
                $stmt->execute([$data]);
                $horariosOcupados = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Formatar horários para HH:MM (remover segundos se houver)
                $horariosFormatados = array_map(function($hora) {
                    return substr($hora, 0, 5); // Retorna apenas HH:MM
                }, $horariosOcupados);
                
                ob_clean();
                echo json_encode([
                    'success' => true, 
                    'horarios_ocupados' => $horariosFormatados,
                    'total' => count($horariosFormatados)
                ], JSON_UNESCAPED_UNICODE);
                break;
            } else {
                // Buscar todos os agendamentos com informações do serviço
                $query = "SELECT a.*, s.nome as servico_nome, s.preco as servico_preco, s.imagem_path as servico_imagem
                         FROM agendamentos a 
                         LEFT JOIN servicos s ON a.servico_id = s.id";
                $params = [];
                $conditions = [];
                
                if ($status) {
                    $conditions[] = "a.status = ?";
                    $params[] = $status;
                }
                
                if ($servico_id) {
                    $conditions[] = "a.servico_id = ?";
                    $params[] = $servico_id;
                }
                
                if (!empty($conditions)) {
                    $query .= " WHERE " . implode(" AND ", $conditions);
                }
                
                $query .= " ORDER BY a.created_at DESC";
                
                try {
                    $stmt = $conn->prepare($query);
                    $stmt->execute($params);
                    $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!is_array($agendamentos)) {
                        $agendamentos = [];
                    }
                    
                    // Fallback: se não há registros em agendamentos, tentar ler da tabela formularios
                    if (count($agendamentos) === 0) {
                        try {
                            $checkTable = $conn->query("SHOW TABLES LIKE 'formularios'");
                            if ($checkTable->rowCount() > 0) {
                                $formQuery = "SELECT 
                                    f.id,
                                    f.servico_id,
                                    f.nome,
                                    f.email,
                                    f.telefone,
                                    f.regiao,
                                    f.bairro,
                                    f.status,
                                    f.observacoes,
                                    f.data_agendamento,
                                    f.hora_agendamento,
                                    f.servico_nome,
                                    f.servico_preco,
                                    f.servico_imagem,
                                    f.servico_descricao,
                                    f.servico_categoria,
                                    f.created_at,
                                    f.updated_at
                                FROM formularios f";
                                
                                // Reaproveitar filtros se houver
                                $formConditions = [];
                                $formParams = [];
                                
                                if ($status) {
                                    $formConditions[] = "f.status = ?";
                                    $formParams[] = $status;
                                }
                                
                                if ($servico_id) {
                                    $formConditions[] = "f.servico_id = ?";
                                    $formParams[] = $servico_id;
                                }
                                
                                if (!empty($formConditions)) {
                                    $formQuery .= " WHERE " . implode(" AND ", $formConditions);
                                }
                                
                                $formQuery .= " ORDER BY f.created_at DESC";
                                
                                $formStmt = $conn->prepare($formQuery);
                                $formStmt->execute($formParams);
                                $agendamentos = $formStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                                
                                // Normalizar campos para o formato esperado em agendamentos
                                $agendamentos = array_map(function($f) {
                                    return [
                                        'id' => $f['id'],
                                        'servico_id' => $f['servico_id'],
                                        'nome' => $f['nome'],
                                        'email' => $f['email'],
                                        'telefone' => $f['telefone'],
                                        'regiao' => $f['regiao'],
                                        'bairro' => $f['bairro'],
                                        'status' => $f['status'] ?? 'pendente',
                                        'observacoes' => $f['observacoes'],
                                        'data_agendamento' => $f['data_agendamento'],
                                        'hora_agendamento' => $f['hora_agendamento'],
                                        'servico_nome' => $f['servico_nome'],
                                        'servico_preco' => $f['servico_preco'],
                                        'servico_imagem' => $f['servico_imagem'],
                                        'servico_descricao' => $f['servico_descricao'],
                                        'servico_categoria' => $f['servico_categoria'],
                                        'created_at' => $f['created_at'] ?? null,
                                        'updated_at' => $f['updated_at'] ?? null
                                    ];
                                }, $agendamentos);
                                
                                error_log('Fallback formularios -> agendamentos: ' . count($agendamentos) . ' registros');
                            }
                        } catch (PDOException $fallbackError) {
                            error_log('Erro no fallback de formularios: ' . $fallbackError->getMessage());
                        }
                    }
                    
                    // Log para debug (remover em produção se necessário)
                    error_log('Agendamentos retornados: ' . count($agendamentos));
                    
                    ob_clean();
                    echo json_encode(['success' => true, 'data' => $agendamentos], JSON_UNESCAPED_UNICODE);
                } catch (PDOException $e) {
                    error_log('Erro ao buscar agendamentos: ' . $e->getMessage());
                    ob_clean();
                    http_response_code(500);
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Erro ao buscar agendamentos: ' . $e->getMessage(),
                        'data' => []
                    ], JSON_UNESCAPED_UNICODE);
                }
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Dados não fornecidos']);
                break;
            }
            
            // Validar campos obrigatórios
            if (empty($data['nome']) || empty($data['telefone']) || empty($data['email']) || empty($data['regiao'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Campos obrigatórios: nome, telefone, email, regiao']);
                break;
            }
            
            // Validar email
            $email = trim($data['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Email inválido']);
                break;
            }
            
            // Validar tamanho dos campos
            if (strlen(trim($data['nome'])) > 255) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Nome muito longo (máximo 255 caracteres)']);
                break;
            }
            
            if (strlen(trim($data['telefone'])) > 20) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Telefone muito longo']);
                break;
            }
            
            // Validar status
            $status = $data['status'] ?? 'pendente';
            $allowedStatuses = ['pendente', 'confirmado', 'cancelado', 'concluido'];
            if (!in_array($status, $allowedStatuses)) {
                $status = 'pendente';
            }
            
            // Validar servico_id se fornecido
            $servico_id = isset($data['servico_id']) && !empty($data['servico_id']) ? intval($data['servico_id']) : null;
            if ($servico_id) {
                // Verificar se o serviço existe no banco
                $stmt = $conn->prepare("SELECT id, status FROM servicos WHERE id = ?");
                $stmt->execute([$servico_id]);
                $servico = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($servico) {
                    // Se o serviço existe no banco, verificar se está ativo
                    if ($servico['status'] !== 'ativo') {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Serviço inativo no sistema']);
                        break;
                    }
                } else {
                    // Se não existe no banco (serviço do JSON), definir como NULL
                    // para evitar violação de foreign key constraint
                    // As informações do serviço estarão nas observações
                    $servico_id = null;
                }
            }
            
            // Validar data e hora se fornecidas
            $data_agendamento = !empty($data['data_agendamento']) ? $data['data_agendamento'] : null;
            $hora_agendamento = !empty($data['hora_agendamento']) ? $data['hora_agendamento'] : null;
            
            // Validar formato do horário - deve ser exato (HH:00) - não permite 40 minutos antes ou depois
            if ($hora_agendamento) {
                // Validar formato HH:00 (horário exato)
                if (!preg_match('/^([0-1][0-9]|2[0-3]):00$/', $hora_agendamento)) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Horário inválido. Apenas horários exatos são permitidos (ex: 08:00, 09:00, 10:00). Horários com minutos não são permitidos.'
                    ]);
                    break;
                }
            }
            
            // Se data e hora foram fornecidas, verificar conflitos
            if ($data_agendamento && $hora_agendamento) {
                // Verificar se já existe agendamento na mesma data e hora (apenas confirmados ou pendentes)
                $stmt = $conn->prepare("SELECT id FROM agendamentos 
                                       WHERE data_agendamento = ? 
                                       AND hora_agendamento = ? 
                                       AND status IN ('pendente', 'confirmado')
                                       AND id != ?");
                $stmt->execute([$data_agendamento, $hora_agendamento, 0]);
                $conflito = $stmt->fetch();
                
                if ($conflito) {
                    http_response_code(409);
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Já existe um agendamento para esta data e hora. Por favor, escolha outro horário.',
                        'conflict' => true
                    ]);
                    break;
                }
            }
            
            try {
                // Iniciar transação
                $conn->beginTransaction();
                
                // Inserir em agendamentos
                $stmt = $conn->prepare("INSERT INTO agendamentos (servico_id, nome, telefone, email, regiao, bairro, status, observacoes, data_agendamento, hora_agendamento) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $servico_id,
                    trim($data['nome']),
                    trim($data['telefone']),
                    $email,
                    trim($data['regiao']),
                    isset($data['bairro']) ? trim($data['bairro']) : trim($data['regiao']),
                    $status,
                    isset($data['observacoes']) ? trim($data['observacoes']) : null,
                    $data_agendamento,
                    $hora_agendamento
                ]);
                
                $agendamentoId = $conn->lastInsertId();
                
                // Verificar se a tabela formularios existe e inserir também
                try {
                    $checkTable = $conn->query("SHOW TABLES LIKE 'formularios'");
                    $tableExists = $checkTable->rowCount() > 0;
                    
                    if ($tableExists) {
                        // Buscar informações do serviço
                        $servicoStmt = $conn->prepare("SELECT nome, preco, imagem_path, descricao, categoria FROM servicos WHERE id = ?");
                        $servicoStmt->execute([$servico_id]);
                        $servico = $servicoStmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Inserir em formularios
                        $formularioStmt = $conn->prepare("INSERT INTO formularios (
                            agendamento_id, servico_id, nome, email, telefone, regiao, bairro,
                            status, observacoes, data_agendamento, hora_agendamento,
                            servico_nome, servico_preco, servico_imagem, servico_descricao, servico_categoria,
                            created_at, sincronizado_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                        
                        $formularioStmt->execute([
                            $agendamentoId,
                            $servico_id,
                            trim($data['nome']),
                            $email,
                            trim($data['telefone']),
                            trim($data['regiao']),
                            isset($data['bairro']) ? trim($data['bairro']) : trim($data['regiao']),
                            $status,
                            isset($data['observacoes']) ? trim($data['observacoes']) : null,
                            $data_agendamento,
                            $hora_agendamento,
                            $servico ? $servico['nome'] : null,
                            $servico ? $servico['preco'] : null,
                            $servico ? $servico['imagem_path'] : null,
                            $servico ? $servico['descricao'] : null,
                            $servico ? $servico['categoria'] : null
                        ]);
                        
                        error_log("Agendamento {$agendamentoId} também inserido em formularios");
                    }
                } catch (PDOException $e) {
                    // Se falhar ao inserir em formularios, apenas logar o erro mas não falhar a transação
                    error_log('Erro ao inserir em formularios (não crítico): ' . $e->getMessage());
                }
                
                // Confirmar transação
                $conn->commit();
                
                ob_clean();
                echo json_encode(['success' => true, 'id' => $agendamentoId], JSON_UNESCAPED_UNICODE);
            } catch (PDOException $e) {
                // Reverter transação em caso de erro
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                ob_clean();
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erro ao criar agendamento: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
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
            
            // Validar conflitos se data/hora foram alteradas
            $data_agendamento = !empty($data['data_agendamento']) ? $data['data_agendamento'] : null;
            $hora_agendamento = !empty($data['hora_agendamento']) ? $data['hora_agendamento'] : null;
            
            // Validar formato do horário - deve ser exato (HH:00)
            if ($hora_agendamento) {
                if (!preg_match('/^([0-1][0-9]|2[0-3]):00$/', $hora_agendamento)) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Horário inválido. Apenas horários exatos são permitidos (ex: 08:00, 09:00, 10:00).'
                    ]);
                    break;
                }
            }
            
            if ($data_agendamento && $hora_agendamento) {
                // Verificar conflitos (excluindo o próprio agendamento)
                $stmt = $conn->prepare("SELECT id FROM agendamentos 
                                       WHERE data_agendamento = ? 
                                       AND hora_agendamento = ? 
                                       AND status IN ('pendente', 'confirmado')
                                       AND id != ?");
                $stmt->execute([$data_agendamento, $hora_agendamento, $id]);
                $conflito = $stmt->fetch();
                
                if ($conflito) {
                    http_response_code(409);
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Já existe um agendamento para esta data e hora. Por favor, escolha outro horário.',
                        'conflict' => true
                    ]);
                    break;
                }
            }
            
            // Validar servico_id se fornecido
            $servico_id = isset($data['servico_id']) && !empty($data['servico_id']) ? intval($data['servico_id']) : null;
            if ($servico_id) {
                $stmt = $conn->prepare("SELECT id FROM servicos WHERE id = ? AND status = 'ativo'");
                $stmt->execute([$servico_id]);
                if (!$stmt->fetch()) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Serviço não encontrado ou inativo']);
                    break;
                }
            }
            
            try {
                $stmt = $conn->prepare("UPDATE agendamentos SET servico_id = ?, nome = ?, telefone = ?, email = ?, regiao = ?, bairro = ?, status = ?, observacoes = ?, data_agendamento = ?, hora_agendamento = ? WHERE id = ?");
                $stmt->execute([
                    $servico_id,
                    trim($data['nome'] ?? ''),
                    trim($data['telefone'] ?? ''),
                    trim($data['email'] ?? ''),
                    trim($data['regiao'] ?? ''),
                    isset($data['bairro']) ? trim($data['bairro']) : trim($data['regiao'] ?? ''),
                    $data['status'] ?? 'pendente',
                    $data['observacoes'] ?? null,
                    $data_agendamento,
                    $hora_agendamento,
                    $id
                ]);
                
                if ($stmt->rowCount() > 0) {
                    // Atualizar também em formularios se existir
                    try {
                        $checkTable = $conn->query("SHOW TABLES LIKE 'formularios'");
                        if ($checkTable->rowCount() > 0) {
                            // Buscar informações do serviço
                            $servicoInfo = null;
                            if ($servico_id) {
                                $servicoStmt = $conn->prepare("SELECT nome, preco, imagem_path, descricao, categoria FROM servicos WHERE id = ?");
                                $servicoStmt->execute([$servico_id]);
                                $servicoInfo = $servicoStmt->fetch(PDO::FETCH_ASSOC);
                            }
                            
                            $updateFormularioStmt = $conn->prepare("UPDATE formularios SET
                                servico_id = ?,
                                nome = ?,
                                email = ?,
                                telefone = ?,
                                regiao = ?,
                                bairro = ?,
                                status = ?,
                                observacoes = ?,
                                data_agendamento = ?,
                                hora_agendamento = ?,
                                servico_nome = ?,
                                servico_preco = ?,
                                servico_imagem = ?,
                                servico_descricao = ?,
                                servico_categoria = ?,
                                sincronizado_at = NOW()
                                WHERE agendamento_id = ?");
                            
                            $updateFormularioStmt->execute([
                                $servico_id,
                                trim($data['nome'] ?? ''),
                                trim($data['email'] ?? ''),
                                trim($data['telefone'] ?? ''),
                                trim($data['regiao'] ?? ''),
                                isset($data['bairro']) ? trim($data['bairro']) : trim($data['regiao'] ?? ''),
                                $data['status'] ?? 'pendente',
                                $data['observacoes'] ?? null,
                                $data_agendamento,
                                $hora_agendamento,
                                $servicoInfo ? $servicoInfo['nome'] : null,
                                $servicoInfo ? $servicoInfo['preco'] : null,
                                $servicoInfo ? $servicoInfo['imagem_path'] : null,
                                $servicoInfo ? $servicoInfo['descricao'] : null,
                                $servicoInfo ? $servicoInfo['categoria'] : null,
                                $id
                            ]);
                            
                            error_log("Agendamento {$id} também atualizado em formularios");
                        }
                    } catch (PDOException $e) {
                        error_log('Erro ao atualizar em formularios (não crítico): ' . $e->getMessage());
                    }
                    
                    ob_clean();
                    echo json_encode(['success' => true, 'message' => 'Agendamento atualizado com sucesso'], JSON_UNESCAPED_UNICODE);
                } else {
                    ob_clean();
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Agendamento não encontrado'], JSON_UNESCAPED_UNICODE);
                }
            } catch (PDOException $e) {
                ob_clean();
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erro ao atualizar agendamento: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
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
            
            try {
                $stmt = $conn->prepare("DELETE FROM agendamentos WHERE id = ?");
                $stmt->execute([$id]);
                
                if ($stmt->rowCount() > 0) {
                    // Deletar também de formularios se existir
                    try {
                        $checkTable = $conn->query("SHOW TABLES LIKE 'formularios'");
                        if ($checkTable->rowCount() > 0) {
                            $deleteFormularioStmt = $conn->prepare("DELETE FROM formularios WHERE agendamento_id = ?");
                            $deleteFormularioStmt->execute([$id]);
                            error_log("Formulário relacionado ao agendamento {$id} também deletado");
                        }
                    } catch (PDOException $e) {
                        error_log('Erro ao deletar de formularios (não crítico): ' . $e->getMessage());
                    }
                    
                    ob_clean();
                    echo json_encode(['success' => true, 'message' => 'Agendamento deletado com sucesso'], JSON_UNESCAPED_UNICODE);
                } else {
                    ob_clean();
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Agendamento não encontrado'], JSON_UNESCAPED_UNICODE);
                }
            } catch (PDOException $e) {
                ob_clean();
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erro ao deletar agendamento: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    }
} catch (PDOException $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro no banco de dados: ' . $e->getMessage(),
        'code' => $e->getCode()
    ], JSON_UNESCAPED_UNICODE);
    error_log('Erro em agendamentos.php: ' . $e->getMessage());
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    error_log('Erro em agendamentos.php: ' . $e->getMessage());
}
?>

