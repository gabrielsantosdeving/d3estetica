<?php
/**
 * ============================================
 * GERADOR DE PDF COMPLETO - 6 PÁGINAS
 * ============================================
 * 
 * Gera PDF completo com todas as 6 páginas da ficha de anamnese
 * Estilo: Fundo branco, acentos laranja (#FF8A3D), tipografia Poppins
 */

require_once dirname(__DIR__) . '/config/database.php';

/**
 * Gera PDF completo de uma ficha de anamnese com 6 páginas
 */
function gerarPDFAnamneseCompleto($anamnese) {
    $html = '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        @page {
            size: A4;
            margin: 20mm;
        }
        
        body {
            font-family: "Poppins", sans-serif;
            background: #ffffff;
            color: #2c3e50;
            line-height: 1.6;
            font-size: 13px;
        }
        
        .page {
            page-break-after: always;
            padding: 30px;
            background: white;
            min-height: 100vh;
        }
        
        .page:last-child {
            page-break-after: auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 3px solid #FF8A3D;
        }
        
        .header h1 {
            font-size: 28px;
            font-weight: 600;
            color: #2c3e50;
            margin: 0 0 10px 0;
            letter-spacing: -0.5px;
        }
        
        .header h2 {
            font-size: 18px;
            font-weight: 400;
            color: #FF8A3D;
            margin: 0;
            font-style: italic;
        }
        
        .section {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #FF8A3D;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .field-group {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .field {
            margin-bottom: 12px;
        }
        
        .field label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .field .value {
            border-bottom: 2px solid #e0e0e0;
            padding: 8px 0;
            min-height: 25px;
            font-size: 13px;
        }
        
        .radio-group {
            display: flex;
            gap: 25px;
            margin-top: 8px;
        }
        
        .radio-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .radio-item input[type="checkbox"] {
            width: 15px;
            height: 15px;
            accent-color: #FF8A3D;
        }
        
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 10px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: 15px;
            height: 15px;
            accent-color: #FF8A3D;
        }
        
        .term {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin-top: 30px;
            border-left: 4px solid #FF8A3D;
        }
        
        .term-text {
            font-size: 12px;
            line-height: 1.8;
            color: #2c3e50;
            margin-bottom: 20px;
        }
        
        .signature-area {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
        }
        
        .signature-line {
            width: 100%;
            border: none;
            border-bottom: 2px solid #2c3e50;
            padding: 8px 0;
            margin-bottom: 5px;
            background: transparent;
        }
        
        .signature-label {
            font-size: 11px;
            color: #6c757d;
            text-align: center;
            margin-top: 5px;
        }
        
        .authorization {
            margin-top: 30px;
            padding: 25px;
            background: white;
            border: 2px solid #FF8A3D;
            border-radius: 8px;
        }
        
        .authorization-title {
            font-size: 14px;
            font-weight: 600;
            color: #FF8A3D;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .authorization-option {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 12px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .authorization-option input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-top: 2px;
            accent-color: #FF8A3D;
        }
        
        .authorization-option span {
            font-size: 12px;
            line-height: 1.6;
            color: #2c3e50;
        }
        
        .measurements {
            position: relative;
            padding: 30px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .silhouette {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 150px;
            height: 400px;
            opacity: 0.08;
            background-image: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 300\'%3E%3Cpath d=\'M50 20 Q45 25 43 35 Q40 45 43 55 Q45 65 50 70 Q55 65 57 55 Q60 45 57 35 Q55 25 50 20 M50 70 L50 90 Q48 95 46 100 Q44 105 46 110 Q48 115 50 120 Q52 115 54 110 Q56 105 54 100 Q52 95 50 90 M50 120 Q48 130 46 140 Q44 150 46 160 Q48 170 50 180 Q52 170 54 160 Q56 150 54 140 Q52 130 50 120 M50 180 Q48 190 46 200 Q44 210 46 220 Q48 230 50 240 Q52 230 54 220 Q56 210 54 200 Q52 190 50 180 M50 240 Q48 250 46 260 Q44 270 46 280 Q48 290 50 300 Q52 290 54 280 Q56 270 54 260 Q52 250 50 240\' fill=\'%23666\' stroke=\'none\'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
            pointer-events: none;
            z-index: 0;
        }
        
        .measurement-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            position: relative;
            z-index: 1;
        }
        
        .measurement-item {
            background: white;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
        }
        
        .measurement-item label {
            display: block;
            font-weight: 600;
            color: #FF8A3D;
            margin-bottom: 8px;
            font-size: 12px;
        }
        
        .measurement-line {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }
        
        .measurement-line span {
            font-size: 11px;
            color: #6c757d;
            min-width: 15px;
        }
        
        .measurement-line .value {
            flex: 1;
            border: none;
            border-bottom: 1px solid #e0e0e0;
            padding: 3px 0;
            font-size: 12px;
        }
        
        @media print {
            .page {
                page-break-after: always;
            }
            .page:last-child {
                page-break-after: auto;
            }
        }
    </style>
</head>
<body>';
    
    // PÁGINA 1: Anamnese Designer de Unhas
    $html .= gerarPagina1($anamnese);
    
    // PÁGINA 2: Spa dos Pés
    $html .= gerarPagina2($anamnese);
    
    // PÁGINA 3: Estética Facial
    $html .= gerarPagina3($anamnese);
    
    // PÁGINA 4: Avaliação da Pele
    $html .= gerarPagina4($anamnese);
    
    // PÁGINA 5: Ficha de Medidas
    $html .= gerarPagina5($anamnese);
    
    // PÁGINA 6: Termo + Autorização
    $html .= gerarPagina6($anamnese);
    
    $html .= '</body></html>';
    
    return $html;
}

function gerarPagina1($a) {
    return '
    <div class="page">
        <div class="header">
            <h1>FICHA DE ANAMNESE</h1>
            <h2>Designer de Unhas / Mãos e Pés</h2>
        </div>
        
        <div class="section">
            <div class="field-group">
                <div class="field">
                    <label>Nome</label>
                    <div class="value">' . htmlspecialchars($a['nome'] ?? '') . '</div>
                </div>
                <div class="field">
                    <label>Idade</label>
                    <div class="value">' . htmlspecialchars($a['idade'] ?? '') . '</div>
                </div>
            </div>
            
            <div class="field-group">
                <div class="field">
                    <label>Ocupação</label>
                    <div class="value">' . htmlspecialchars($a['ocupacao'] ?? '') . '</div>
                </div>
                <div class="field">
                    <label>Indicação</label>
                    <div class="value">' . htmlspecialchars($a['indicacao'] ?? '') . '</div>
                </div>
            </div>
            
            <div class="field-group">
                <div class="field">
                    <label>Endereço</label>
                    <div class="value">' . htmlspecialchars($a['endereco'] ?? '') . '</div>
                </div>
                <div class="field">
                    <label>CEP</label>
                    <div class="value">' . htmlspecialchars($a['cep'] ?? '') . '</div>
                </div>
            </div>
            
            <div class="field-group">
                <div class="field">
                    <label>CPF</label>
                    <div class="value">' . htmlspecialchars($a['cpf'] ?? '') . '</div>
                </div>
                <div class="field">
                    <label>Contato</label>
                    <div class="value">' . htmlspecialchars($a['telefone'] ?? '') . '</div>
                </div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Questionário</div>
            ' . gerarQuestaoSimNao($a['gestante'] ?? '', 'Está em gestação?') . '
            ' . gerarQuestaoSimNao($a['diabetes'] ?? '', 'Tem diabetes?') . '
            ' . gerarQuestaoSimNao($a['alergia_esmaltes'] ?? '', 'Alergia a esmaltes ou cosméticos?') . '
            ' . gerarQuestaoSimNao($a['retira_cuticula'] ?? '', 'Costuma retirar a cutícula?') . '
            ' . gerarQuestaoSimNao($a['micose_fungo'] ?? '', 'Problema com micose ou fungo?') . '
            ' . gerarQuestaoSimNao($a['medicamentos'] ?? '', 'Faz uso de medicamentos?') . '
            ' . gerarQuestaoSimNao($a['roe_unhas'] ?? '', 'Hábito de roer unhas?') . '
            ' . gerarQuestaoSimNao($a['unha_encravada'] ?? '', 'Unha encravada?') . '
            ' . gerarQuestaoSimNao($a['atividade_fisica'] ?? '', 'Pratica atividade física?') . '
            ' . gerarQuestaoSimNao($a['piscina_praia'] ?? '', 'Frequenta piscina ou praia?') . '
        </div>
        
        <div class="section">
            <div class="section-title">A lâmina ungueal apresenta:</div>
            <div class="checkbox-group">
                ' . gerarCheckbox($a['lamina_descamacao'] ?? false, 'Descamação') . '
                ' . gerarCheckbox($a['lamina_descolamento'] ?? false, 'Descolamento') . '
                ' . gerarCheckbox($a['lamina_manchas'] ?? false, 'Manchas') . '
                ' . gerarCheckbox($a['lamina_estrias'] ?? false, 'Estrias') . '
                ' . gerarCheckbox($a['lamina_outros'] ?? false, 'Outros: ' . htmlspecialchars($a['lamina_outros_especifique'] ?? '')) . '
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Procedimentos</div>
            <div class="checkbox-group">
                ' . gerarCheckbox($a['proc_manicure'] ?? false, 'Manicure') . '
                ' . gerarCheckbox($a['proc_pedicure'] ?? false, 'Pedicure') . '
                ' . gerarCheckbox($a['proc_esmaltacao'] ?? false, 'Esmaltação') . '
                ' . gerarCheckbox($a['proc_cutilagem'] ?? false, 'Cutilagem') . '
                ' . gerarCheckbox($a['proc_outro'] ?? false, 'Outro: ' . htmlspecialchars($a['proc_outro_especifique'] ?? '')) . '
            </div>
            
            <div class="field-group" style="margin-top: 20px;">
                <div class="field">
                    <label>Técnica Aplicada</label>
                    <div class="value">' . htmlspecialchars($a['tecnica_aplicada'] ?? '') . '</div>
                </div>
                <div class="field">
                    <label>Formato</label>
                    <div class="value">' . htmlspecialchars($a['formato'] ?? '') . '</div>
                </div>
            </div>
            
            <div class="field-group">
                <div class="field">
                    <label>Cor</label>
                    <div class="value">' . htmlspecialchars($a['cor'] ?? '') . '</div>
                </div>
                <div class="field">
                    <label>Data do Procedimento</label>
                    <div class="value">' . ($a['data_procedimento'] ? date('d/m/Y', strtotime($a['data_procedimento'])) : '') . '</div>
                </div>
            </div>
            
            <div class="field">
                <label>Detalhes</label>
                <div class="value" style="min-height: 60px;">' . nl2br(htmlspecialchars($a['detalhes'] ?? '')) . '</div>
            </div>
        </div>
        
        <div class="term">
            <div class="term-text">
                <p>Autorizo a realização do procedimento e o registro fotográfico do antes e depois, para documentação e divulgação da profissional. As declarações acima são verdadeiras, não cabendo à profissional a responsabilidade por informações omitidas nesta avaliação. Me comprometo a seguir todas as recomendações necessárias após o procedimento.</p>
            </div>
            <div class="signature-area">
                <div class="field-group">
                    <div class="field">
                        <div class="signature-line"></div>
                        <div class="signature-label">Local e Data</div>
                    </div>
                    <div class="field">
                        <div class="signature-line"></div>
                        <div class="signature-label">Assinatura do Cliente</div>
                    </div>
                </div>
            </div>
        </div>
    </div>';
}

function gerarPagina2($a) {
    return '
    <div class="page">
        <div class="header">
            <h1>FICHA DE ANAMNESE</h1>
            <h2>Spa dos Pés</h2>
        </div>
        
        <div class="section">
            <div class="field-group">
                <div class="field">
                    <label>Nome</label>
                    <div class="value">' . htmlspecialchars($a['spa_nome'] ?? '') . '</div>
                </div>
                <div class="field">
                    <label>Data de Nascimento</label>
                    <div class="value">' . ($a['spa_nascimento'] ? date('d/m/Y', strtotime($a['spa_nascimento'])) : '') . '</div>
                </div>
            </div>
            
            <div class="field-group">
                <div class="field">
                    <label>Sexo</label>
                    <div class="radio-group">
                        ' . gerarRadio($a['spa_sexo'] ?? '', 'F') . '
                        ' . gerarRadio($a['spa_sexo'] ?? '', 'M') . '
                    </div>
                </div>
                <div class="field">
                    <label>Telefone</label>
                    <div class="value">' . htmlspecialchars($a['spa_telefone'] ?? '') . '</div>
                </div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Avaliação</div>
            ' . gerarQuestaoSimNao($a['spa_alergia_pele'] ?? '', 'Tem alergia na pele?') . '
            ' . gerarQuestaoSimNao($a['spa_cirurgia'] ?? '', 'Cirurgia nos membros inferiores?') . '
            ' . gerarQuestaoSimNao($a['spa_diabetes'] ?? '', 'Tem diabetes?') . '
            ' . gerarQuestaoSimNao($a['spa_medicamentos'] ?? '', 'Usa medicamentos?') . '
            ' . gerarQuestaoSimNao($a['spa_gestante'] ?? '', 'Gestante?') . '
            ' . gerarQuestaoSimNao($a['spa_ja_fez'] ?? '', 'Já fez spa dos pés antes?') . '
            ' . gerarQuestaoSimNaoComTexto($a['spa_dores_pes'] ?? '', 'Sente dores nos pés?', $a['spa_dores_especifique'] ?? '') . '
        </div>
        
        <div class="term">
            <div class="term-text">
                <p>Autorizo a realização do procedimento e o registro fotográfico do antes e depois, para documentação e divulgação da profissional. As declarações acima são verdadeiras, não cabendo à profissional a responsabilidade por informações omitidas nesta avaliação. Me comprometo a seguir todas as recomendações necessárias após o procedimento.</p>
            </div>
            <div class="signature-area">
                <div class="field-group">
                    <div class="field">
                        <div class="signature-line"></div>
                        <div class="signature-label">Local e Data</div>
                    </div>
                    <div class="field">
                        <div class="signature-line"></div>
                        <div class="signature-label">Assinatura do Cliente</div>
                    </div>
                </div>
            </div>
        </div>
    </div>';
}

function gerarPagina3($a) {
    return '
    <div class="page">
        <div class="header">
            <h1>D3 ESTÉTICA & BELEZA</h1>
            <h2>FICHA DE ANAMNESE - ESTÉTICA FACIAL</h2>
        </div>
        
        <div class="section">
            <div class="section-title">Informações Pessoais</div>
            <div class="field-group">
                <div class="field">
                    <label>Nome</label>
                    <div class="value">' . htmlspecialchars($a['facial_nome'] ?? '') . '</div>
                </div>
                <div class="field">
                    <label>Nascimento</label>
                    <div class="value">' . ($a['facial_nascimento'] ? date('d/m/Y', strtotime($a['facial_nascimento'])) : '') . '</div>
                </div>
            </div>
            
            <div class="field-group">
                <div class="field">
                    <label>Sexo</label>
                    <div class="radio-group">
                        ' . gerarRadio($a['facial_sexo'] ?? '', 'Feminino') . '
                        ' . gerarRadio($a['facial_sexo'] ?? '', 'Masculino') . '
                    </div>
                </div>
                <div class="field">
                    <label>Telefone</label>
                    <div class="value">' . htmlspecialchars($a['facial_telefone'] ?? '') . '</div>
                </div>
            </div>
            
            <div class="field-group">
                <div class="field">
                    <label>Endereço</label>
                    <div class="value">' . htmlspecialchars($a['facial_endereco'] ?? '') . '</div>
                </div>
                <div class="field">
                    <label>Rede Social</label>
                    <div class="value">' . htmlspecialchars($a['facial_rede_social'] ?? '') . '</div>
                </div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Checklist</div>
            ' . gerarQuestaoSimNao($a['facial_expoe_sol'] ?? '', 'Se expõe ao sol com frequência?') . '
            ' . gerarQuestaoSimNao($a['facial_funcionamento_intestinal'] ?? '', 'Funcionamento intestinal regular?') . '
            ' . gerarQuestaoSimNao($a['facial_marcapasso'] ?? '', 'Portador de marcapasso?') . '
            ' . gerarQuestaoSimNaoComTexto($a['facial_gestante'] ?? '', 'Gestante?', $a['facial_gestante_semanas'] ?? '') . '
            ' . gerarQuestaoSimNaoComTexto($a['facial_anticoncepcional'] ?? '', 'Utiliza anticoncepcional?', $a['facial_anticoncepcional_qual'] ?? '') . '
            ' . gerarQuestaoSimNao($a['facial_ingere_agua'] ?? '', 'Ingere água com frequência?') . '
            ' . gerarQuestaoSimNao($a['facial_ingere_alcool'] ?? '', 'Ingere bebida alcoólica?') . '
            ' . gerarQuestaoSimNao($a['facial_filtro_solar'] ?? '', 'Usa filtro solar?') . '
            ' . gerarQuestaoSimNao($a['facial_tabagismo'] ?? '', 'Tabagismo?') . '
            ' . gerarQuestaoSimNao($a['facial_alteracoes_cardiacas'] ?? '', 'Alterações cardíacas?') . '
            ' . gerarQuestaoSimNao($a['facial_roacutan'] ?? '', 'Já usou Roacutan?') . '
            ' . gerarQuestaoSimNao($a['facial_periodo_menstrual'] ?? '', 'Está no período menstrual?') . '
            ' . gerarQuestaoSimNao($a['facial_qualidade_sono'] ?? '', 'Boa qualidade do sono?') . '
            ' . gerarQuestaoSimNao($a['facial_protese'] ?? '', 'Possui prótese corporal/facial?') . '
            ' . gerarQuestaoSimNaoComTexto($a['facial_tratamento_anterior'] ?? '', 'Tratamento facial anterior?', $a['facial_tratamento_qual'] ?? '') . '
            ' . gerarQuestaoSimNaoComTexto($a['facial_cremes_locoes'] ?? '', 'Cremes ou loções faciais?', $a['facial_cremes_quais'] ?? '') . '
            ' . gerarQuestaoSimNaoComTexto($a['facial_problemas_pele'] ?? '', 'Problemas de pele?', $a['facial_problemas_qual'] ?? '') . '
            ' . gerarQuestaoSimNaoComTexto($a['facial_atividade_fisica'] ?? '', 'Pratica atividade física?', $a['facial_atividade_especifique'] ?? '') . '
            ' . gerarQuestaoSimNaoComTexto($a['facial_alergia'] ?? '', 'Possui algum tipo de alergia?', $a['facial_alergia_especifique'] ?? '') . '
            ' . gerarQuestaoSimNao($a['facial_alto_indice_glicemico'] ?? '', 'Consome alimentos de alto índice glicêmico?') . '
        </div>
        
        <div class="section">
            <div class="field">
                <label>Observações</label>
                <div class="value" style="min-height: 80px;">' . nl2br(htmlspecialchars($a['facial_observacoes'] ?? '')) . '</div>
            </div>
        </div>
    </div>';
}

function gerarPagina4($a) {
    return '
    <div class="page">
        <div class="header">
            <h1>AVALIAÇÃO DA PELE</h1>
        </div>
        
        <div class="section">
            <div class="section-title">Hipercromias (Muita pigmentação)</div>
            <div class="checkbox-group">
                ' . gerarCheckbox($a['hipercromia_melasma'] ?? false, 'Melasma') . '
                ' . gerarCheckbox($a['hipercromia_cloasma'] ?? false, 'Cloasma') . '
                ' . gerarCheckbox($a['hipercromia_hpi'] ?? false, 'HPI') . '
                ' . gerarCheckbox($a['hipercromia_efelides'] ?? false, 'Efélides') . '
                ' . gerarCheckbox($a['hipercromia_melanose'] ?? false, 'Melanose Solar "Mancha de Sol"') . '
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Hipocromias (Pouca pigmentação, manchas brancas na pele)</div>
            <div class="checkbox-group">
                ' . gerarCheckbox($a['hipocromia_lesao'] ?? false, 'Advindo de lesão (queimadura, bolhas, ferimento)') . '
                ' . gerarCheckbox($a['hipocromia_radiacao'] ?? false, 'Exposição à radiação') . '
                ' . gerarCheckbox($a['hipocromia_outros'] ?? false, 'Outros: ' . htmlspecialchars($a['hipocromia_outros_especifique'] ?? '')) . '
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Lesões / Acne</div>
            <div class="checkbox-group">
                ' . gerarCheckbox($a['lesao_comedoes'] ?? false, 'Comedões "Cravos"') . '
                ' . gerarCheckbox($a['lesao_pustulas'] ?? false, 'Pústulas "Espinhas"') . '
                ' . gerarCheckbox($a['lesao_nodulos'] ?? false, 'Nódulos "Espinha interna"') . '
                ' . gerarCheckbox($a['lesao_millium'] ?? false, 'Millium') . '
                ' . gerarCheckbox($a['lesao_ostios'] ?? false, 'Óstios abertos') . '
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Cicatriz</div>
            <div class="checkbox-group">
                ' . gerarCheckbox($a['cicatriz_hipertrofica'] ?? false, 'Cicatriz hipertrófica') . '
                ' . gerarCheckbox($a['cicatriz_queloideana'] ?? false, 'Cicatriz queloideana') . '
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Biotipo de Pele</div>
            <div class="radio-group">
                ' . gerarRadio($a['biotipo'] ?? '', 'Alípica') . '
                ' . gerarRadio($a['biotipo'] ?? '', 'Lipídica') . '
                ' . gerarRadio($a['biotipo'] ?? '', 'Normal') . '
                ' . gerarRadio($a['biotipo'] ?? '', 'Mista') . '
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Espessura</div>
            <div class="radio-group">
                ' . gerarRadio($a['espessura'] ?? '', 'Espessa') . '
                ' . gerarRadio($a['espessura'] ?? '', 'Fina') . '
                ' . gerarRadio($a['espessura'] ?? '', 'Muito fina') . '
                ' . gerarRadio($a['espessura'] ?? '', 'Grossa') . '
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Fototipo</div>
            <div class="radio-group">
                ' . gerarRadio($a['fototipo'] ?? '', '1') . '
                ' . gerarRadio($a['fototipo'] ?? '', '2') . '
                ' . gerarRadio($a['fototipo'] ?? '', '3') . '
                ' . gerarRadio($a['fototipo'] ?? '', '4') . '
                ' . gerarRadio($a['fototipo'] ?? '', '5') . '
                ' . gerarRadio($a['fototipo'] ?? '', '6') . '
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Grau de Envelhecimento</div>
            <div class="radio-group">
                ' . gerarRadio($a['envelhecimento'] ?? '', 'Leve') . '
                ' . gerarRadio($a['envelhecimento'] ?? '', 'Moderada') . '
                ' . gerarRadio($a['envelhecimento'] ?? '', 'Avançado') . '
                ' . gerarRadio($a['envelhecimento'] ?? '', 'Severo') . '
            </div>
        </div>
    </div>';
}

function gerarPagina5($a) {
    $medidas = ['braco', 'peito', 'cintura', 'coxa', 'quadril', 'panturrilha'];
    $pesos = ['peso_1', 'peso_2', 'peso_3', 'peso_4'];
    
    $html = '
    <div class="page">
        <div class="header">
            <h1>FICHA DE MEDIDAS</h1>
        </div>
        
        <div class="measurements">
            <div class="silhouette"></div>
            <div class="measurement-grid">';
    
    foreach ($medidas as $medida) {
        $html .= '
                <div class="measurement-item">
                    <label>' . ucfirst($medida) . '</label>';
        for ($i = 1; $i <= 4; $i++) {
            $valor = $a[$medida . '_' . $i] ?? '';
            $html .= '
                    <div class="measurement-line">
                        <span>' . $i . '.</span>
                        <div class="value">' . htmlspecialchars($valor) . '</div>
                    </div>';
        }
        $html .= '
                </div>';
    }
    
    $html .= '
            </div>
        </div>
        
        <div class="section" style="margin-top: 30px;">
            <div class="field-group">
                <div class="field">
                    <label>Peso</label>';
    foreach ($pesos as $peso) {
        $valor = $a[$peso] ?? '';
        $html .= '
                    <div class="measurement-line">
                        <span>' . substr($peso, -1) . '.</span>
                        <div class="value">' . htmlspecialchars($valor) . '</div>
                    </div>';
    }
    $html .= '
                </div>
                <div class="field">
                    <label>Anotações</label>
                    <div class="value" style="min-height: 120px;">' . nl2br(htmlspecialchars($a['medidas_anotacoes'] ?? '')) . '</div>
                </div>
            </div>
        </div>
    </div>';
    
    return $html;
}

function gerarPagina6($a) {
    $autoriza = $a['autorizacao_imagem'] ?? '';
    
    return '
    <div class="page">
        <div class="header">
            <h1>D3 ESTÉTICA & BELEZA</h1>
        </div>
        
        <div class="term">
            <div class="term-text">
                <p><strong>Eu, ' . htmlspecialchars($a['termo_nome'] ?? '_________________________') . ',</strong> estou ciente do tratamento e das informações e medidas tomadas. Concordo em realizar todas as sessões do tratamento que envolva técnicas manuais estéticas.</p>
                <p style="margin-top: 20px;">Confirmo que todas as informações contidas na ficha de anamnese são verdadeiras.</p>
                <p style="margin-top: 20px;">Assim sendo, concordo em iniciar este tratamento.</p>
            </div>
        </div>
        
        <div class="authorization">
            <div class="authorization-title">AUTORIZAÇÃO PARA USO DE IMAGEM</div>
            
            <div class="authorization-option">
                ' . gerarCheckbox($autoriza === 'autorizo', 'AUTORIZO o uso da minha imagem em todo e qualquer material entre fotos, documentos, redes sociais, campanhas promocionais e institucionais que estejam relacionados a profissional responsável, destinadas à divulgação ao público em geral, apenas para uso interno e/ou particular da profissional acima citada, desde que não haja desvirtuamento da finalidade.') . '
            </div>
            
            <div class="authorization-option">
                ' . gerarCheckbox($autoriza === 'nao_autorizo', 'NÃO AUTORIZO o uso da minha imagem e') . '
            </div>
            
            <div class="term-text" style="margin-top: 20px;">
                <p>Declaro que li e compreendi as condições acima descritas e que esta autorização ou negativa reflete minha decisão livre e esclarecida.</p>
            </div>
        </div>
        
        <div class="signature-area">
            <div class="field-group">
                <div class="field">
                    <div class="signature-line"></div>
                    <div class="signature-label">Local e Data</div>
                </div>
                <div class="field">
                    <div class="signature-line"></div>
                    <div class="signature-label">Assinatura do Cliente</div>
                </div>
            </div>
        </div>
    </div>';
}

// Funções auxiliares
function gerarQuestaoSimNao($valor, $pergunta) {
    $sim = ($valor === 'Sim') ? 'checked' : '';
    $nao = ($valor === 'Não') ? 'checked' : '';
    return '
    <div class="field">
        <label>' . htmlspecialchars($pergunta) . '</label>
        <div class="radio-group">
            <div class="radio-item">
                <input type="checkbox" ' . $sim . ' disabled>
                <span>Sim</span>
            </div>
            <div class="radio-item">
                <input type="checkbox" ' . $nao . ' disabled>
                <span>Não</span>
            </div>
        </div>
    </div>';
}

function gerarQuestaoSimNaoComTexto($valor, $pergunta, $texto) {
    $html = gerarQuestaoSimNao($valor, $pergunta);
    if ($valor === 'Sim' && $texto) {
        $html .= '
        <div class="field" style="margin-top: 10px; margin-left: 20px;">
            <div class="value">' . htmlspecialchars($texto) . '</div>
        </div>';
    }
    return $html;
}

function gerarCheckbox($checked, $label) {
    return '
    <div class="checkbox-item">
        <input type="checkbox" ' . ($checked ? 'checked' : '') . ' disabled>
        <span>' . htmlspecialchars($label) . '</span>
    </div>';
}

function gerarRadio($valor, $opcao) {
    $checked = ($valor === $opcao) ? 'checked' : '';
    return '
    <div class="radio-item">
        <input type="checkbox" ' . $checked . ' disabled>
        <span>' . htmlspecialchars($opcao) . '</span>
    </div>';
}


