<?php
// IMPORTANTE: Iniciar sessão ANTES de qualquer header ou output
// Isso é crítico para manter a sessão entre requisições
if (session_status() === PHP_SESSION_NONE) {
    // Configurar nome da sessão para garantir consistência
    if (session_name() !== 'PHPSESSID') {
        session_name('PHPSESSID');
    }
    session_start();
}

// Agora podemos enviar os headers
// NÃO definir Content-Type aqui - será definido depois conforme o tipo de requisição
// Permitir credenciais (cookies) nas requisições
// Quando usando Access-Control-Allow-Credentials: true, não podemos usar '*'
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : null;
if ($origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
} else {
    // Se não houver origem (mesmo domínio), não precisa de CORS
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/auth/auth-functions.php';
require_once dirname(__DIR__) . '/lib/MercadoPagoHelper.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Serviços podem ser acessados sem autenticação para exibição pública
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
    http_response_code(500);
    error_log('Erro PDO em servicos.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao conectar com o banco de dados. Verifique as configurações.',
        'error_code' => $e->getCode()
    ]);
    exit();
} catch (Exception $e) {
    http_response_code(500);
    error_log('Erro em servicos.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao conectar com o banco de dados: ' . $e->getMessage()
    ]);
    exit();
}

try {
    // Definir Content-Type como JSON (será usado para todas as respostas JSON)
    // Mas não interferirá com FormData que define seu próprio Content-Type
    header('Content-Type: application/json');
    
    switch ($method) {
        case 'GET':
            $id = $_GET['id'] ?? null;
            $categoria = $_GET['categoria'] ?? null;
            
            if ($id) {
                // Buscar serviço com informações do criador (pode ser de administradores ou usuarios)
                $stmt = $conn->prepare("
                    SELECT s.*, 
                           COALESCE(a.nome, u.nome) as criado_por_nome,
                           COALESCE(a.email, u.email) as criado_por_email,
                           DATE_FORMAT(s.created_at, '%d/%m/%Y') as data_criacao_formatada
                    FROM servicos s
                    LEFT JOIN administradores a ON s.criado_por = a.id
                    LEFT JOIN usuarios u ON s.criado_por = u.id
                    WHERE s.id = ?
                ");
                $stmt->execute([$id]);
                $servico = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($servico) {
                    // Adicionar informações padrão se não houver criador
                    if (empty($servico['criado_por_nome'])) {
                        $servico['criado_por_nome'] = 'Admin';
                    }
                    
                    // Buscar link de pagamento automaticamente
                    // Usar try-catch para não quebrar a resposta se houver erro no MercadoPago
                    try {
                        $mpHelper = new MercadoPagoHelper();
                        $linkPagamento = $mpHelper->buscarLinkPagamento('servico', $servico['id']);
                        
                        if ($linkPagamento) {
                            $servico['link_pagamento'] = $linkPagamento['link_pagamento'];
                            $servico['preference_id'] = $linkPagamento['preference_id'];
                        } else {
                            // Gerar link automaticamente se não existir
                            $linkPagamento = $mpHelper->gerarLinkPagamento(
                                'servico',
                                $servico['id'],
                                $servico['nome'],
                                $servico['preco']
                            );
                            if ($linkPagamento) {
                                $servico['link_pagamento'] = $linkPagamento['link_pagamento'];
                                $servico['preference_id'] = $linkPagamento['preference_id'];
                            }
                        }
                    } catch (Exception $e) {
                        // Se houver erro ao gerar link de pagamento, continuar sem o link
                        error_log('Erro ao gerar link de pagamento para serviço ' . $servico['id'] . ': ' . $e->getMessage());
                        // Não adicionar link_pagamento se houver erro
                    }
                    
                    echo json_encode(['success' => true, 'data' => $servico]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Serviço não encontrado']);
                }
            } else {
                // Verificar se é admin autenticado
                $isAdmin = false;
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $isAdmin = isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
                
                // Sempre usar JOINs para retornar informações do criador
                // Para acesso público, mostrar apenas serviços ativos
                // Admin pode ver todos através de autenticação
                if ($isAdmin) {
                    $query = "SELECT s.*, 
                                     COALESCE(a.nome, u.nome) as criado_por_nome,
                                     COALESCE(a.email, u.email) as criado_por_email,
                                     DATE_FORMAT(s.created_at, '%d/%m/%Y') as data_criacao_formatada
                              FROM servicos s
                              LEFT JOIN administradores a ON s.criado_por = a.id
                              LEFT JOIN usuarios u ON s.criado_por = u.id";
                    $params = [];
                    
                    if ($categoria) {
                        $query .= " WHERE s.categoria = ?";
                        $params[] = $categoria;
                    }
                } else {
                    $query = "SELECT s.*, 
                                     COALESCE(a.nome, u.nome) as criado_por_nome,
                                     COALESCE(a.email, u.email) as criado_por_email,
                                     DATE_FORMAT(s.created_at, '%d/%m/%Y') as data_criacao_formatada
                              FROM servicos s
                              LEFT JOIN administradores a ON s.criado_por = a.id
                              LEFT JOIN usuarios u ON s.criado_por = u.id
                              WHERE s.status = 'ativo'";
                    $params = [];
                    
                    if ($categoria) {
                        $query .= " AND s.categoria = ?";
                        $params[] = $categoria;
                    }
                }
                
                $query .= " ORDER BY s.vendidos DESC, s.nome ASC";
                
                try {
                    $stmt = $conn->prepare($query);
                    $stmt->execute($params);
                    $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    error_log('Erro ao buscar serviços: ' . $e->getMessage());
                    error_log('Query: ' . $query);
                    error_log('Params: ' . print_r($params, true));
                    $servicos = [];
                }
                
                if (!is_array($servicos)) {
                    $servicos = [];
                }
                
                // Adicionar informações padrão se não houver criador
                foreach ($servicos as &$servico) {
                    if (empty($servico['criado_por_nome'])) {
                        $servico['criado_por_nome'] = 'Admin';
                    }
                    // Garantir que data_criacao_formatada existe
                    if (empty($servico['data_criacao_formatada']) && !empty($servico['created_at'])) {
                        $date = new DateTime($servico['created_at']);
                        $servico['data_criacao_formatada'] = $date->format('d/m/Y');
                    }
                }
                unset($servico);
                
                // Adicionar links de pagamento automaticamente para cada serviço
                // Usar try-catch para não quebrar a resposta se houver erro no MercadoPago
                try {
                    $mpHelper = new MercadoPagoHelper();
                    foreach ($servicos as &$servico) {
                        try {
                            $linkPagamento = $mpHelper->buscarLinkPagamento('servico', $servico['id']);
                            
                            if ($linkPagamento) {
                                $servico['link_pagamento'] = $linkPagamento['link_pagamento'];
                                $servico['preference_id'] = $linkPagamento['preference_id'];
                            } else {
                                // Gerar link automaticamente se não existir
                                $linkPagamento = $mpHelper->gerarLinkPagamento(
                                    'servico',
                                    $servico['id'],
                                    $servico['nome'],
                                    $servico['preco']
                                );
                                if ($linkPagamento) {
                                    $servico['link_pagamento'] = $linkPagamento['link_pagamento'];
                                    $servico['preference_id'] = $linkPagamento['preference_id'];
                                }
                            }
                        } catch (Exception $e) {
                            // Se houver erro ao gerar link de pagamento, continuar sem o link
                            error_log('Erro ao gerar link de pagamento para serviço ' . $servico['id'] . ': ' . $e->getMessage());
                            // Não adicionar link_pagamento se houver erro
                        }
                    }
                } catch (Exception $e) {
                    // Se houver erro ao instanciar MercadoPagoHelper, continuar sem links de pagamento
                    error_log('Erro ao instanciar MercadoPagoHelper: ' . $e->getMessage());
                    // Continuar sem adicionar links de pagamento
                }
                
                echo json_encode(['success' => true, 'data' => $servicos]);
            }
            break;
            
        case 'POST':
            // Garantir que a sessão está iniciada antes de verificar autenticação
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            error_log('POST servicos.php - Sessão iniciada. user_id: ' . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'não definido'));
            error_log('POST servicos.php - user_type: ' . (isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'não definido'));
            
            // Verificar se a conexão ainda está disponível
            if (!isset($conn) || $conn === null) {
                error_log('POST servicos.php - Conexão não disponível, tentando reconectar...');
                try {
                    $conn = getDB();
                    error_log('POST servicos.php - Conexão reestabelecida com sucesso');
                } catch (Exception $e) {
                    error_log('POST servicos.php - Erro ao reconectar: ' . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Erro ao conectar com o banco de dados: ' . $e->getMessage()]);
                    exit();
                }
            }
            
            // Verificar autenticação e permissões de admin
            // (checkAuth e checkAdmin fazem exit() se falharem, então não precisa try-catch)
            try {
                checkAuth();
                checkAdmin();
                error_log('POST servicos.php - Autenticação verificada com sucesso');
            } catch (Exception $e) {
                error_log('POST servicos.php - Erro na autenticação: ' . $e->getMessage());
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Erro de autenticação: ' . $e->getMessage()]);
                exit();
            }
            
            // Verificar se é upload de arquivo (FormData) ou JSON
            $isFormData = isset($_FILES['imagem']);
            
            error_log('POST servicos.php - isFormData: ' . ($isFormData ? 'true' : 'false'));
            
            if ($isFormData) {
                $nome = $_POST['nome'] ?? '';
                $categoria = $_POST['categoria'] ?? '';
                $descricao = $_POST['descricao'] ?? '';
                $preco = $_POST['preco'] ?? 0;
                $preco_original = $_POST['preco_original'] ?? null;
                $status = $_POST['status'] ?? 'ativo';
                
                // Validar campos obrigatórios
                if (empty(trim($nome))) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Nome do serviço é obrigatório']);
                    exit();
                }
                
                if (empty(trim($categoria))) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Categoria é obrigatória']);
                    exit();
                }
                
                if (empty(trim($descricao))) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Descrição é obrigatória']);
                    exit();
                }
                
                // Processar upload de imagem
                $imagem_path = null;
                if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
                    // Validar tamanho do arquivo (5MB máximo)
                    if ($_FILES['imagem']['size'] > MAX_UPLOAD_SIZE) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Arquivo muito grande. Tamanho máximo: 5MB']);
                        exit();
                    }
                    
                    // Validar tipo MIME
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $_FILES['imagem']['tmp_name']);
                    finfo_close($finfo);
                    
                    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Tipo de arquivo não permitido. Use apenas imagens (JPG, PNG, GIF, WEBP)']);
                        exit();
                    }
                    
                    // Validar extensão
                    $ext = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
                    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    if (!in_array($ext, $allowedExts)) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Extensão de arquivo não permitida']);
                        exit();
                    }
                    
                    $uploadDir = dirname(__DIR__) . '/uploads/servicos/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    // Gerar nome seguro do arquivo
                    $fileName = 'servico_' . time() . '_' . uniqid() . '.' . $ext;
                    $filePath = $uploadDir . $fileName;
                    
                    // Validar que não há path traversal
                    $realPath = realpath($uploadDir);
                    $realFilePath = realpath(dirname($filePath));
                    if ($realFilePath === false || strpos($realFilePath, $realPath) !== 0) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Caminho de arquivo inválido']);
                        exit();
                    }
                    
                    if (move_uploaded_file($_FILES['imagem']['tmp_name'], $filePath)) {
                        $imagem_path = '/backend/uploads/servicos/' . $fileName;
                    } else {
                        http_response_code(500);
                        echo json_encode(['success' => false, 'message' => 'Erro ao fazer upload do arquivo']);
                        exit();
                    }
                }
                
                // Validar preço
                $preco = floatval($preco);
                if ($preco <= 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Preço deve ser maior que zero']);
                    exit();
                }
                
                $preco_original = isset($preco_original) && $preco_original !== '' ? floatval($preco_original) : null;
                if ($preco_original !== null && $preco_original < 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Preço original não pode ser negativo']);
                    exit();
                }
                
                // Validar status
                $status = $status ?? 'ativo';
                $allowedStatuses = ['ativo', 'inativo'];
                if (!in_array($status, $allowedStatuses)) {
                    $status = 'ativo';
                }
                
                // Validar tamanho dos campos
                if (strlen(trim($nome)) > 255) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Nome muito longo (máximo 255 caracteres)']);
                    exit();
                }
                
                if (strlen(trim($categoria)) > 100) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Categoria muito longa (máximo 100 caracteres)']);
                    exit();
                }
                
                // Obter ID do usuário logado (admin)
                $criado_por = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                
                $o_que_esta_incluso = $_POST['o_que_esta_incluso'] ?? null;
                
                try {
                    // Verificar conexão novamente antes de inserir
                    if (!isset($conn) || $conn === null) {
                        error_log('POST servicos.php (FormData) - Conexão perdida, reconectando...');
                        $conn = getDB();
                    }
                    
                    error_log('POST servicos.php (FormData) - Preparando INSERT');
                    
                    $stmt = $conn->prepare("INSERT INTO servicos (nome, categoria, descricao, o_que_esta_incluso, preco, preco_original, status, imagem_path, criado_por) 
                                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $params = [
                        trim($nome),
                        trim($categoria),
                        isset($descricao) ? trim($descricao) : '',
                        $o_que_esta_incluso ? trim($o_que_esta_incluso) : null,
                        $preco,
                        $preco_original,
                        $status,
                        $imagem_path,
                        $criado_por
                    ];
                    
                    error_log('POST servicos.php (FormData) - Executando INSERT com params: ' . print_r($params, true));
                    
                    $result = $stmt->execute($params);
                    
                    if (!$result) {
                        $errorInfo = $stmt->errorInfo();
                        error_log('Erro ao inserir serviço (FormData): ' . print_r($errorInfo, true));
                        error_log('SQL State: ' . ($errorInfo[0] ?? 'N/A'));
                        error_log('Error Code: ' . ($errorInfo[1] ?? 'N/A'));
                        error_log('Error Message: ' . ($errorInfo[2] ?? 'N/A'));
                        http_response_code(500);
                        echo json_encode(['success' => false, 'message' => 'Erro ao inserir serviço no banco de dados: ' . ($errorInfo[2] ?? 'Erro desconhecido')]);
                        exit();
                    }
                    
                    $id = $conn->lastInsertId();
                    
                    if (!$id) {
                        error_log('Erro ao obter ID do serviço inserido (FormData)');
                        error_log('Row count: ' . $stmt->rowCount());
                        http_response_code(500);
                        echo json_encode(['success' => false, 'message' => 'Erro ao obter ID do serviço inserido']);
                        exit();
                    }
                    
                    error_log('Serviço inserido com sucesso (FormData). ID: ' . $id);
                } catch (PDOException $e) {
                    error_log('Erro PDO ao inserir serviço (FormData): ' . $e->getMessage());
                    error_log('PDO Error Code: ' . $e->getCode());
                    error_log('PDO Error Info: ' . print_r($e->errorInfo ?? [], true));
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Erro ao inserir serviço: ' . $e->getMessage()]);
                    exit();
                }
            } else {
                $rawInput = file_get_contents('php://input');
                error_log('POST servicos.php - Raw input: ' . $rawInput);
                $data = json_decode($rawInput, true);
                
                // Validar se os dados foram decodificados corretamente
                if ($data === null || !is_array($data)) {
                    $jsonError = json_last_error_msg();
                    error_log('POST servicos.php - Erro ao decodificar JSON: ' . $jsonError);
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Dados inválidos no JSON: ' . $jsonError]);
                    exit();
                }
                
                error_log('POST servicos.php - Dados decodificados: ' . print_r($data, true));
                
                // Validar campos obrigatórios
                if (empty(trim($data['nome'] ?? ''))) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Nome do serviço é obrigatório']);
                    exit();
                }
                
                if (empty(trim($data['categoria'] ?? ''))) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Categoria é obrigatória']);
                    exit();
                }
                
                if (empty(trim($data['descricao'] ?? ''))) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Descrição é obrigatória']);
                    exit();
                }
                
                // Validar preço
                $preco = floatval($data['preco'] ?? 0);
                if ($preco <= 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Preço deve ser maior que zero']);
                    exit();
                }
                
                $preco_original = isset($data['preco_original']) && $data['preco_original'] !== '' ? floatval($data['preco_original']) : null;
                if ($preco_original !== null && $preco_original < 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Preço original não pode ser negativo']);
                    exit();
                }
                
                // Validar status
                $status = $data['status'] ?? 'ativo';
                $allowedStatuses = ['ativo', 'inativo'];
                if (!in_array($status, $allowedStatuses)) {
                    $status = 'ativo';
                }
                
                // Validar tamanho dos campos
                if (strlen(trim($data['nome'] ?? '')) > 255) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Nome muito longo (máximo 255 caracteres)']);
                    exit();
                }
                
                if (strlen(trim($data['categoria'] ?? '')) > 100) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Categoria muito longa (máximo 100 caracteres)']);
                    exit();
                }
                
                // Obter ID do usuário logado (admin)
                $criado_por = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                $o_que_esta_incluso = isset($data['o_que_esta_incluso']) && !empty($data['o_que_esta_incluso']) ? trim($data['o_que_esta_incluso']) : null;
                
                try {
                    // Verificar conexão novamente antes de inserir
                    if (!isset($conn) || $conn === null) {
                        error_log('POST servicos.php (JSON) - Conexão perdida, reconectando...');
                        $conn = getDB();
                    }
                    
                    error_log('POST servicos.php (JSON) - Preparando INSERT com dados: ' . print_r([
                        'nome' => trim($data['nome'] ?? ''),
                        'categoria' => trim($data['categoria'] ?? ''),
                        'descricao' => isset($data['descricao']) ? substr(trim($data['descricao']), 0, 100) : '',
                        'preco' => $preco,
                        'preco_original' => $preco_original,
                        'status' => $status,
                        'criado_por' => $criado_por
                    ], true));
                    
                    $stmt = $conn->prepare("INSERT INTO servicos (nome, categoria, descricao, o_que_esta_incluso, preco, preco_original, status, criado_por) 
                                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $params = [
                        trim($data['nome'] ?? ''),
                        trim($data['categoria'] ?? ''),
                        isset($data['descricao']) ? trim($data['descricao']) : '',
                        $o_que_esta_incluso,
                        $preco,
                        $preco_original,
                        $status,
                        $criado_por
                    ];
                    
                    error_log('POST servicos.php (JSON) - Executando INSERT com params: ' . print_r($params, true));
                    
                    $result = $stmt->execute($params);
                    
                    if (!$result) {
                        $errorInfo = $stmt->errorInfo();
                        error_log('Erro ao inserir serviço: ' . print_r($errorInfo, true));
                        error_log('SQL State: ' . ($errorInfo[0] ?? 'N/A'));
                        error_log('Error Code: ' . ($errorInfo[1] ?? 'N/A'));
                        error_log('Error Message: ' . ($errorInfo[2] ?? 'N/A'));
                        http_response_code(500);
                        echo json_encode(['success' => false, 'message' => 'Erro ao inserir serviço no banco de dados: ' . ($errorInfo[2] ?? 'Erro desconhecido')]);
                        exit();
                    }
                    
                    $id = $conn->lastInsertId();
                    
                    if (!$id) {
                        error_log('Erro ao obter ID do serviço inserido');
                        error_log('Row count: ' . $stmt->rowCount());
                        http_response_code(500);
                        echo json_encode(['success' => false, 'message' => 'Erro ao obter ID do serviço inserido']);
                        exit();
                    }
                    
                    error_log('Serviço inserido com sucesso. ID: ' . $id);
                } catch (PDOException $e) {
                    error_log('Erro PDO ao inserir serviço: ' . $e->getMessage());
                    error_log('PDO Error Code: ' . $e->getCode());
                    error_log('PDO Error Info: ' . print_r($e->errorInfo ?? [], true));
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Erro ao inserir serviço: ' . $e->getMessage()]);
                    exit();
                }
            }
            
            // Gerar link de pagamento automaticamente (não crítico se falhar)
            $linkPagamento = null;
            try {
                $mpHelper = new MercadoPagoHelper();
                $linkPagamento = $mpHelper->gerarLinkPagamento(
                    'servico',
                    $id,
                    $isFormData ? $nome : $data['nome'],
                    $isFormData ? $preco : $data['preco']
                );
            } catch (Exception $e) {
                // Log do erro mas não falha o salvamento do serviço
                error_log('Erro ao gerar link de pagamento (não crítico): ' . $e->getMessage());
            }
            
            $response = ['success' => true, 'id' => $id];
            if ($linkPagamento) {
                $response['link_pagamento'] = $linkPagamento['link_pagamento'];
                $response['preference_id'] = $linkPagamento['preference_id'];
            }
            
            error_log('POST servicos.php - Resposta de sucesso: ' . json_encode($response));
            echo json_encode($response);
            exit();
            
        case 'PUT':
            // Garantir que a sessão está iniciada antes de verificar autenticação
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Verificar autenticação e permissões de admin
            checkAuth();
            checkAdmin();
            
            $id = $_GET['id'] ?? null;
            if (!$id) {
                $id = $_POST['id'] ?? null;
            }
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
                break;
            }
            
            // Verificar se é upload de arquivo (FormData) ou JSON
            $isFormData = isset($_FILES['imagem']);
            
            // Buscar preço antigo para verificar se mudou
            $stmt = $conn->prepare("SELECT preco, nome, imagem_path FROM servicos WHERE id = ?");
            $stmt->execute([$id]);
            $servicoAntigo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($isFormData) {
                $nome = $_POST['nome'] ?? '';
                $categoria = $_POST['categoria'] ?? '';
                $descricao = $_POST['descricao'] ?? '';
                $o_que_esta_incluso = $_POST['o_que_esta_incluso'] ?? null;
                $preco = $_POST['preco'] ?? 0;
                $preco_original = $_POST['preco_original'] ?? null;
                $status = $_POST['status'] ?? 'ativo';
                
                // Processar upload de imagem
                $imagem_path = $servicoAntigo['imagem_path'] ?? null;
                if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
                    // Validar tamanho do arquivo (5MB máximo)
                    if ($_FILES['imagem']['size'] > MAX_UPLOAD_SIZE) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Arquivo muito grande. Tamanho máximo: 5MB']);
                        exit();
                    }
                    
                    // Validar tipo MIME
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $_FILES['imagem']['tmp_name']);
                    finfo_close($finfo);
                    
                    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Tipo de arquivo não permitido. Use apenas imagens (JPG, PNG, GIF, WEBP)']);
                        exit();
                    }
                    
                    // Validar extensão
                    $ext = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
                    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    if (!in_array($ext, $allowedExts)) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Extensão de arquivo não permitida']);
                        exit();
                    }
                    
                    // Remover imagem antiga se existir
                    if ($imagem_path && file_exists(dirname(__DIR__) . $imagem_path)) {
                        @unlink(dirname(__DIR__) . $imagem_path);
                    }
                    
                    $uploadDir = dirname(__DIR__) . '/uploads/servicos/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    // Gerar nome seguro do arquivo
                    $fileName = 'servico_' . time() . '_' . uniqid() . '.' . $ext;
                    $filePath = $uploadDir . $fileName;
                    
                    // Validar que não há path traversal
                    $realPath = realpath($uploadDir);
                    $realFilePath = realpath(dirname($filePath));
                    if ($realFilePath === false || strpos($realFilePath, $realPath) !== 0) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Caminho de arquivo inválido']);
                        exit();
                    }
                    
                    if (move_uploaded_file($_FILES['imagem']['tmp_name'], $filePath)) {
                        $imagem_path = '/backend/uploads/servicos/' . $fileName;
                    } else {
                        http_response_code(500);
                        echo json_encode(['success' => false, 'message' => 'Erro ao fazer upload do arquivo']);
                        exit();
                    }
                }
                
                $stmt = $conn->prepare("UPDATE servicos SET nome = ?, categoria = ?, descricao = ?, o_que_esta_incluso = ?, preco = ?, preco_original = ?, status = ?, imagem_path = ? WHERE id = ?");
                $stmt->execute([
                    $nome,
                    $categoria,
                    $descricao,
                    $o_que_esta_incluso ? trim($o_que_esta_incluso) : null,
                    $preco,
                    $preco_original,
                    $status,
                    $imagem_path,
                    $id
                ]);
                
                $precoMudou = $servicoAntigo && $servicoAntigo['preco'] != $preco;
            } else {
                $data = json_decode(file_get_contents('php://input'), true);
                
                if (empty($data)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Dados não fornecidos']);
                    break;
                }
                
                // Validar preço
                $preco = floatval($data['preco'] ?? 0);
                if ($preco < 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Preço não pode ser negativo']);
                    break;
                }
                
                $preco_original = isset($data['preco_original']) && $data['preco_original'] !== '' ? floatval($data['preco_original']) : null;
                if ($preco_original !== null && $preco_original < 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Preço original não pode ser negativo']);
                    break;
                }
                
                // Validar status
                $status = $data['status'] ?? 'ativo';
                $allowedStatuses = ['ativo', 'inativo'];
                if (!in_array($status, $allowedStatuses)) {
                    $status = 'ativo';
                }
                
                // Validar tamanho dos campos
                if (strlen(trim($data['nome'] ?? '')) > 255) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Nome muito longo (máximo 255 caracteres)']);
                    break;
                }
                
                if (strlen(trim($data['categoria'] ?? '')) > 100) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Categoria muito longa (máximo 100 caracteres)']);
                    break;
                }
                
                $o_que_esta_incluso = isset($data['o_que_esta_incluso']) && !empty($data['o_que_esta_incluso']) ? trim($data['o_que_esta_incluso']) : null;
                
                $stmt = $conn->prepare("UPDATE servicos SET nome = ?, categoria = ?, descricao = ?, o_que_esta_incluso = ?, preco = ?, preco_original = ?, status = ? WHERE id = ?");
                $stmt->execute([
                    trim($data['nome'] ?? ''),
                    trim($data['categoria'] ?? ''),
                    isset($data['descricao']) ? trim($data['descricao']) : '',
                    $o_que_esta_incluso,
                    $preco,
                    $preco_original,
                    $status,
                    $id
                ]);
                
                $precoMudou = $servicoAntigo && $servicoAntigo['preco'] != $data['preco'];
            }
            
            // Se o preço mudou, gerar novo link de pagamento automaticamente
            if ($precoMudou) {
                $mpHelper = new MercadoPagoHelper();
                $linkPagamento = $mpHelper->gerarLinkPagamento(
                    'servico',
                    $id,
                    $isFormData ? $nome : $data['nome'],
                    $isFormData ? $preco : $data['preco']
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
            // Verificar autenticação e permissões de admin
            // (checkAuth e checkAdmin fazem exit() se falharem, então não precisa try-catch)
            checkAuth();
            checkAdmin();
            
            $id = $_GET['id'] ?? null;
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
                break;
            }
            
            try {
                // Verificar se o serviço existe antes de excluir
                $stmt = $conn->prepare("SELECT id FROM servicos WHERE id = ?");
                $stmt->execute([$id]);
                $servico = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$servico) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Serviço não encontrado']);
                    break;
                }
                
                // Excluir o serviço
                $stmt = $conn->prepare("DELETE FROM servicos WHERE id = ?");
                $result = $stmt->execute([$id]);
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Serviço excluído com sucesso']);
                } else {
                    $errorInfo = $stmt->errorInfo();
                    error_log('Erro ao excluir serviço: ' . print_r($errorInfo, true));
                    http_response_code(500);
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Erro ao excluir serviço: ' . ($errorInfo[2] ?? 'Erro desconhecido')
                    ]);
                }
            } catch (PDOException $e) {
                error_log('Erro PDO ao excluir serviço: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode([
                    'success' => false, 
                    'message' => 'Erro ao excluir serviço: ' . $e->getMessage()
                ]);
            } catch (Exception $e) {
                error_log('Erro ao excluir serviço: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode([
                    'success' => false, 
                    'message' => 'Erro ao excluir serviço: ' . $e->getMessage()
                ]);
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
    error_log('Erro em servicos.php: ' . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro: ' . $e->getMessage()
    ]);
    error_log('Erro em servicos.php: ' . $e->getMessage());
}
?>

