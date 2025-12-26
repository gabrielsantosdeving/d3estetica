<?php
/**
 * ============================================
 * API - DASHBOARD ACTIVITY
 * ============================================
 * 
 * Fornece atividades recentes para o dashboard
 * 
 * @package D3Estetica
 * @file dashboard-activity.php
 * @version 1.0
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/auth/auth-functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

try {
    // Verificar autenticação
    checkAuth();
    checkAdmin();
    
    // Obter conexão com tratamento de erro
    try {
        $conn = getDB();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Erro ao conectar com o banco de dados: ' . $e->getMessage(),
            'data' => []
        ]);
        exit();
    }
    $activities = [];
    
    // Buscar novos usuários recentes
    try {
        $stmt = $conn->prepare("
            SELECT nome, email, created_at 
            FROM usuarios 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute();
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($usuarios as $usuario) {
            $timeAgo = getTimeAgo($usuario['created_at']);
            $activities[] = [
                'type' => 'new-user',
                'icon' => 'bi-person-plus-fill',
                'text' => 'Novo usuário registrado',
                'details' => $usuario['nome'],
                'time' => $timeAgo,
                'timestamp' => $usuario['created_at']
            ];
        }
    } catch (Exception $e) {
        error_log('Erro ao buscar usuários: ' . $e->getMessage());
    }
    
    // Buscar novos agendamentos
    try {
        $stmt = $conn->prepare("
            SELECT id, nome as cliente_nome, created_at as data_agendamento 
            FROM agendamentos 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute();
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($pedidos as $pedido) {
            $timeAgo = getTimeAgo($pedido['data_agendamento']);
            $activities[] = [
                'type' => 'new-order',
                'icon' => 'bi-calendar-check-fill',
                'text' => 'Novo agendamento',
                'details' => $pedido['cliente_nome'] . ' - #' . $pedido['id'],
                'time' => $timeAgo,
                'timestamp' => $pedido['data_agendamento']
            ];
        }
    } catch (Exception $e) {
        error_log('Erro ao buscar agendamentos: ' . $e->getMessage());
    }
    
    // Buscar novos tickets de chat
    try {
        $stmt = $conn->prepare("
            SELECT id, cliente_nome, cliente_email, created_at 
            FROM tickets 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute();
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tickets as $ticket) {
            $timeAgo = getTimeAgo($ticket['created_at']);
            $activities[] = [
                'type' => 'comment',
                'icon' => 'bi-chat-dots-fill',
                'text' => 'Novo ticket de suporte',
                'details' => ($ticket['cliente_nome'] ?: $ticket['cliente_email']) . ' - #' . $ticket['id'],
                'time' => $timeAgo,
                'timestamp' => $ticket['created_at']
            ];
        }
    } catch (Exception $e) {
        error_log('Erro ao buscar tickets: ' . $e->getMessage());
    }
    
    // Buscar novos serviços criados
    try {
        $stmt = $conn->prepare("
            SELECT id, nome, created_at 
            FROM servicos 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute();
        $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($servicos as $servico) {
            $timeAgo = getTimeAgo($servico['created_at']);
            $activities[] = [
                'type' => 'new-order',
                'icon' => 'bi-plus-circle-fill',
                'text' => 'Novo serviço criado',
                'details' => $servico['nome'],
                'time' => $timeAgo,
                'timestamp' => $servico['created_at']
            ];
        }
    } catch (Exception $e) {
        error_log('Erro ao buscar serviços: ' . $e->getMessage());
    }
    
    // Buscar novas candidaturas
    try {
        $stmt = $conn->prepare("
            SELECT id, nome, vaga, created_at 
            FROM candidaturas 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute();
        $candidaturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($candidaturas as $candidatura) {
            $timeAgo = getTimeAgo($candidatura['created_at']);
            $activities[] = [
                'type' => 'new-user',
                'icon' => 'bi-file-earmark-person-fill',
                'text' => 'Nova candidatura',
                'details' => $candidatura['nome'] . ' - ' . $candidatura['vaga'],
                'time' => $timeAgo,
                'timestamp' => $candidatura['created_at']
            ];
        }
    } catch (Exception $e) {
        error_log('Erro ao buscar candidaturas: ' . $e->getMessage());
    }
    
    // Ordenar por timestamp (mais recente primeiro)
    usort($activities, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    // Limitar a 10 atividades mais recentes
    $activities = array_slice($activities, 0, 10);
    
    echo json_encode([
        'success' => true,
        'data' => $activities
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar atividades: ' . $e->getMessage(),
        'data' => []
    ]);
    error_log('Erro em dashboard-activity.php: ' . $e->getMessage());
}

/**
 * Calcula o tempo relativo (ex: "Há 5 min")
 */
function getTimeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'Há ' . $diff . ' seg';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return 'Há ' . $mins . ' min';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return 'Há ' . $hours . ' hora' . ($hours > 1 ? 's' : '');
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return 'Há ' . $days . ' dia' . ($days > 1 ? 's' : '');
    } else {
        $weeks = floor($diff / 604800);
        return 'Há ' . $weeks . ' semana' . ($weeks > 1 ? 's' : '');
    }
}
?>


