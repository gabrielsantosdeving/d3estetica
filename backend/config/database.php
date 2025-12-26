<?php
/**
 * ============================================
 * ARQUIVO DE CONEXÃO COM BANCO DE DADOS
 * ============================================
 * 
 * Este arquivo gerencia a conexão com o banco de dados MySQL usando o padrão Singleton.
 * Garante que apenas uma instância de conexão seja criada durante toda a execução.
 * 
 * IMPORTANTE: Para alterar as credenciais do banco, edite: backend/config/config.php
 * 
 * @package D3Estetica
 * @author Sistema D3 Estética
 * @version 1.0
 */

// Importar configurações do banco de dados
require_once dirname(__FILE__) . '/config.php';

/**
 * Classe Database - Singleton para gerenciar conexão com MySQL
 * 
 * Utiliza o padrão Singleton para garantir uma única instância de conexão,
 * melhorando performance e evitando múltiplas conexões desnecessárias.
 */
class Database {
    /**
     * Instância única da classe (Singleton)
     * @var Database|null
     */
    private static $instance = null;
    
    /**
     * Objeto PDO de conexão com o banco
     * @var PDO
     */
    private $conn;

    /**
     * Construtor privado para impedir instanciação direta (Singleton)
     * 
     * Cria a conexão PDO com as configurações definidas em config.php
     * Configurações aplicadas:
     * - ERRMODE_EXCEPTION: Lança exceções em caso de erro
     * - FETCH_ASSOC: Retorna arrays associativos por padrão
     * - EMULATE_PREPARES: Desabilita emulação de prepared statements (mais seguro)
     */
    private function __construct() {
        try {
            // Verificar se as constantes estão definidas
            if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
                throw new Exception('Configurações do banco de dados não encontradas. Verifique o arquivo config.php');
            }
            
            // String de conexão DSN (Data Source Name)
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            // Opções de configuração do PDO
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,        // Lança exceções em erros
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,   // Retorna arrays associativos
                PDO::ATTR_EMULATE_PREPARES => false,                // Usa prepared statements nativos
                PDO::ATTR_PERSISTENT => false,                      // Não usar conexão persistente
                PDO::ATTR_TIMEOUT => 10,                            // Timeout de 10 segundos (aumentado)
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci" // Garantir charset correto
            ];
            
            // Criar conexão PDO
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Testar conexão executando uma query simples
            $this->conn->query("SELECT 1");
            
        } catch(PDOException $e) {
            // Log do erro
            error_log("Erro na conexão PDO: " . $e->getMessage());
            
            // Em caso de erro, lançar exceção em vez de die para melhor tratamento
            throw new Exception("Erro ao conectar com o banco de dados: " . $e->getMessage() . " (Código: " . $e->getCode() . ")");
        } catch(Exception $e) {
            error_log("Erro na conexão: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtém a instância única da classe (Singleton)
     * 
     * Se não existir uma instância, cria uma nova.
     * Se já existir, retorna a existente.
     * 
     * @return Database Instância única da classe
     */
    public static function getInstance() {
        if (self::$instance === null) {
            try {
                self::$instance = new self();
            } catch (Exception $e) {
                // Resetar instância em caso de erro para permitir nova tentativa
                self::$instance = null;
                throw $e;
            }
        }
        return self::$instance;
    }

    /**
     * Retorna o objeto PDO de conexão
     * 
     * @return PDO Objeto de conexão com o banco de dados
     * @throws Exception Se a conexão não estiver disponível
     */
    public function getConnection() {
        if ($this->conn === null) {
            throw new Exception('Conexão com banco de dados não disponível');
        }
        
        // Verificar se a conexão ainda está ativa
        try {
            $this->conn->query("SELECT 1");
        } catch (PDOException $e) {
            // Se a conexão foi perdida, resetar e tentar reconectar
            self::$instance = null;
            $this->conn = null;
            throw new Exception('Conexão com banco de dados perdida. Tente novamente.');
        }
        
        return $this->conn;
    }
}

/**
 * Função helper para obter conexão com o banco de dados
 * 
 * Facilita o acesso à conexão sem precisar instanciar a classe manualmente.
 * 
 * @return PDO Objeto de conexão com o banco de dados
 */
function getDB() {
    return Database::getInstance()->getConnection();
}
