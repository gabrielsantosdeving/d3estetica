<?php
/**
 * ============================================
 * GERADOR DE PDF - DOM PDF
 * ============================================
 * 
 * Converte HTML em PDF usando a biblioteca DomPDF
 * 
 * @package D3Estetica
 * @file pdf_generator.php
 * @version 2.0
 */

// Verificar se DomPDF está instalado
if (!class_exists('Dompdf\Dompdf')) {
    // Tentar carregar via autoload do Composer
    $autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
    } else {
        // Se DomPDF não estiver instalado, lançar exceção para o código chamador tratar
        throw new Exception('DomPDF não está instalado. Execute: composer install');
    }
}

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Gera PDF a partir de HTML
 * 
 * @param string $html Conteúdo HTML a ser convertido
 * @param string $filename Nome do arquivo PDF (sem extensão)
 * @param bool $download Se true, força download. Se false, retorna o PDF como string
 * @return void|string
 */
function gerarPDF($html, $filename = 'documento', $download = true) {
    // Configurar opções do DomPDF
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true); // Permitir imagens remotas
    $options->set('isFontSubsettingEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('chroot', dirname(__DIR__)); // Diretório raiz para segurança
    
    // Criar instância do DomPDF
    $dompdf = new Dompdf($options);
    
    // Carregar HTML
    $dompdf->loadHtml($html, 'UTF-8');
    
    // Configurar papel (A4)
    $dompdf->setPaper('A4', 'portrait');
    
    // Renderizar PDF
    $dompdf->render();
    
    // Adicionar metadados
    $dompdf->addInfo('Title', $filename);
    $dompdf->addInfo('Creator', 'D3 Estética');
    $dompdf->addInfo('Subject', 'Ficha de Anamnese');
    
    if ($download) {
        // Forçar download
        $dompdf->stream($filename . '.pdf', [
            'Attachment' => 1
        ]);
    } else {
        // Retornar como string
        return $dompdf->output();
    }
}

/**
 * Gera PDF de uma ficha de anamnese completa
 * 
 * @param array $anamnese Dados da anamnese
 * @param bool $download Se true, força download. Se false, retorna o PDF como string
 * @return void|string
 */
function gerarPDFAnamnese($anamnese, $download = true) {
    // Gerar HTML da anamnese
    require_once __DIR__ . '/pdf_generator_completo.php';
    $html = gerarPDFAnamneseCompleto($anamnese);
    
    // Nome do arquivo
    $nomePaciente = str_replace(' ', '_', $anamnese['paciente_nome'] ?? 'anamnese');
    $nomePaciente = preg_replace('/[^a-zA-Z0-9_]/', '', $nomePaciente);
    $filename = 'anamnese_' . ($anamnese['id'] ?? '') . '_' . $nomePaciente;
    
    // Gerar e retornar PDF
    return gerarPDF($html, $filename, $download);
}
