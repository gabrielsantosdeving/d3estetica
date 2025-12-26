/**
 * ============================================
 * PAINEL ADMINISTRATIVO - D3 ESTÉTICA
 * Sistema completo de gerenciamento
 * ============================================
 */

class AdminPanel {
    constructor() {
        this.currentSection = 'servicos';
        this.currentView = 'list'; // list, form, structure
        this.currentItem = null;
        this.chatInterval = null;
        this.apiBase = '/backend/api';
        this.init();
    }

    init() {
        this.setupNavigation();
        this.setupToolbar();
        this.loadSection('servicos');
        this.initChat();
    }

    // ============================================
    // NAVEGAÇÃO
    // ============================================
    setupNavigation() {
        // Navegação principal
        document.querySelectorAll('.main-nav a').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const section = link.dataset.section || link.textContent.trim().toLowerCase();
                this.switchMainSection(section);
            });
        });

        // Sidebar
        document.querySelectorAll('.db-item').forEach(item => {
            item.addEventListener('click', () => {
                document.querySelectorAll('.db-item').forEach(i => i.classList.remove('active'));
                item.classList.add('active');
                const section = item.dataset.section;
                this.loadSection(section);
            });
        });
    }

    switchMainSection(section) {
        document.querySelectorAll('.main-nav a').forEach(a => {
            if (a.dataset.section === section) {
                a.classList.add('active');
            } else {
                a.classList.remove('active');
            }
        });

        const sectionMap = {
            'configuracoes': () => this.showDatabaseStructure(),
            'agendamentos': () => this.loadAgendamentos(),
            'clientes': () => this.loadClientes(),
            'usuarios': () => this.loadUsuarios(),
            'chat': () => this.showChat(),
            'blog': () => this.loadSection('blogs'),
            'servicos': () => this.loadSection('servicos'),
            'dashboard': () => this.loadSection('servicos')
        };

        if (sectionMap[section]) {
            sectionMap[section]();
        }
    }

    // ============================================
    // TOOLBAR
    // ============================================
    setupToolbar() {
        document.querySelectorAll('.toolbar-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const action = btn.textContent.trim();
                
                // Atualizar botão ativo
                document.querySelectorAll('.toolbar-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                if (action.includes('Estrutura')) {
                    this.showDatabaseStructure();
                } else if (action.includes('Novo')) {
                    this.showForm('new');
                } else if (action.includes('Atualizar')) {
                    this.loadSection(this.currentSection);
                } else if (action.includes('Buscar')) {
                    // Implementar busca
                    const searchTerm = prompt('Digite o termo de busca:');
                    if (searchTerm) {
                        this.searchItems(searchTerm);
                    }
                }
            });
        });
    }

    // ============================================
    // CARREGAR SEÇÕES
    // ============================================
    async loadSection(section) {
        this.currentSection = section;
        this.updateBreadcrumb(section);

        switch(section) {
            case 'servicos':
                await this.loadServicos();
                break;
            case 'blogs':
                await this.loadBlogs();
                break;
            case 'promocoes':
                await this.loadPromocoes();
                break;
            case 'avaliacoes':
                await this.loadAvaliacoes();
                break;
            default:
                await this.loadServicos();
        }
    }

    updateBreadcrumb(section) {
        const sectionNames = {
            'servicos': 'Serviços',
            'blogs': 'Blogs',
            'promocoes': 'Promoções',
            'avaliacoes': 'Avaliações'
        };
        document.getElementById('breadcrumbSection').textContent = sectionNames[section] || section;
        document.getElementById('tablesTitle').textContent = `Gerenciar ${sectionNames[section] || section}`;
    }

    // ============================================
    // SERVIÇOS
    // ============================================
    async loadServicos() {
        try {
            const response = await fetch(`${this.apiBase}/servicos.php`, {
                credentials: 'include'
            });
            const result = await response.json();
            
            if (result.success) {
                this.renderTable(result.data, this.getServicosColumns());
                this.updateSummary(result.data);
            } else {
                console.error('Erro ao carregar serviços:', result.message);
            }
        } catch (error) {
            console.error('Erro ao carregar serviços:', error);
        }
    }

    getServicosColumns() {
        return [
            { key: 'id', label: 'ID', width: '60px' },
            { key: 'nome', label: 'Nome' },
            { key: 'categoria', label: 'Categoria' },
            { key: 'preco', label: 'Preço', format: 'currency' },
            { key: 'preco_original', label: 'Preço Original', format: 'currency' },
            { key: 'vendidos', label: 'Vendidos' },
            { key: 'status', label: 'Status', format: 'badge' },
            { key: 'actions', label: 'Ações', format: 'actions' }
        ];
    }

    // ============================================
    // BLOGS
    // ============================================
    async loadBlogs() {
        try {
            const response = await fetch(`${this.apiBase}/blog.php`, {
                credentials: 'include'
            });
            const result = await response.json();
            
            if (result.success) {
                this.renderTable(result.data, this.getBlogsColumns());
                this.updateSummary(result.data);
            } else {
                console.error('Erro ao carregar blogs:', result.message);
            }
        } catch (error) {
            console.error('Erro ao carregar blogs:', error);
        }
    }

    getBlogsColumns() {
        return [
            { key: 'id', label: 'ID', width: '60px' },
            { key: 'titulo', label: 'Título' },
            { key: 'autor', label: 'Autor' },
            { key: 'visualizacoes', label: 'Visualizações' },
            { key: 'status', label: 'Status', format: 'badge' },
            { key: 'created_at', label: 'Data', format: 'date' },
            { key: 'actions', label: 'Ações', format: 'actions' }
        ];
    }

    // ============================================
    // PROMOÇÕES
    // ============================================
    async loadPromocoes() {
        try {
            const response = await fetch(`${this.apiBase}/promocoes.php`, {
                credentials: 'include'
            });
            const result = await response.json();
            
            if (result.success) {
                this.renderTable(result.data, this.getPromocoesColumns());
                this.updateSummary(result.data);
            } else {
                console.error('Erro ao carregar promoções:', result.message);
            }
        } catch (error) {
            console.error('Erro ao carregar promoções:', error);
        }
    }

    getPromocoesColumns() {
        return [
            { key: 'id', label: 'ID', width: '60px' },
            { key: 'titulo', label: 'Título' },
            { key: 'desconto', label: 'Desconto', format: 'percent' },
            { key: 'validade', label: 'Validade', format: 'date' },
            { key: 'status', label: 'Status', format: 'badge' },
            { key: 'created_at', label: 'Criado em', format: 'date' },
            { key: 'actions', label: 'Ações', format: 'actions' }
        ];
    }

    // ============================================
    // AVALIAÇÕES
    // ============================================
    async loadAvaliacoes() {
        // Por enquanto, mostrar mensagem
        const tbody = document.getElementById('tablesBody');
        tbody.innerHTML = '<tr><td colspan="10" style="text-align: center; padding: 40px;">Sistema de avaliações em desenvolvimento</td></tr>';
        this.updateSummary([]);
    }

    // ============================================
    // CLIENTES VIP
    // ============================================
    async loadClientes() {
        try {
            const response = await fetch(`${this.apiBase}/vips.php`, {
                credentials: 'include'
            });
            const result = await response.json();
            
            if (result.success) {
                this.renderTable(result.data, this.getClientesColumns());
                this.updateSummary(result.data);
            } else {
                console.error('Erro ao carregar clientes:', result.message);
            }
        } catch (error) {
            console.error('Erro ao carregar clientes:', error);
        }
    }

    getClientesColumns() {
        return [
            { key: 'id', label: 'ID', width: '60px' },
            { key: 'nome', label: 'Nome' },
            { key: 'email', label: 'E-mail' },
            { key: 'telefone', label: 'Telefone' },
            { key: 'plano_nome', label: 'Plano' },
            { key: 'status', label: 'Status', format: 'badge' },
            { key: 'created_at', label: 'Assinado em', format: 'date' },
            { key: 'actions', label: 'Ações', format: 'actions' }
        ];
    }

    // ============================================
    // USUÁRIOS
    // ============================================
    async loadUsuarios() {
        try {
            const response = await fetch(`${this.apiBase}/usuarios.php?tipo=all`, {
                credentials: 'include'
            });
            const result = await response.json();
            
            if (result.success) {
                this.renderTable(result.data, this.getUsuariosColumns());
                this.updateSummary(result.data);
            } else {
                console.error('Erro ao carregar usuários:', result.message);
            }
        } catch (error) {
            console.error('Erro ao carregar usuários:', error);
        }
    }

    getUsuariosColumns() {
        return [
            { key: 'id', label: 'ID', width: '60px' },
            { key: 'nome', label: 'Nome' },
            { key: 'email', label: 'E-mail' },
            { key: 'tipo', label: 'Tipo', format: 'badge' },
            { key: 'status', label: 'Status', format: 'badge' },
            { key: 'created_at', label: 'Criado em', format: 'date' },
            { key: 'actions', label: 'Ações', format: 'actions' }
        ];
    }

    // ============================================
    // AGENDAMENTOS
    // ============================================
    async loadAgendamentos() {
        try {
            const response = await fetch(`${this.apiBase}/agendamentos.php`, {
                credentials: 'include'
            });
            const result = await response.json();
            
            if (result.success) {
                this.renderTable(result.data, this.getAgendamentosColumns());
                this.updateSummary(result.data);
            } else {
                console.error('Erro ao carregar agendamentos:', result.message);
            }
        } catch (error) {
            console.error('Erro ao carregar agendamentos:', error);
        }
    }

    getAgendamentosColumns() {
        return [
            { key: 'id', label: 'ID', width: '60px' },
            { key: 'nome', label: 'Nome' },
            { key: 'email', label: 'E-mail' },
            { key: 'telefone', label: 'Telefone' },
            { key: 'regiao', label: 'Região' },
            { key: 'status', label: 'Status', format: 'badge' },
            { key: 'created_at', label: 'Agendado em', format: 'date' },
            { key: 'actions', label: 'Ações', format: 'actions' }
        ];
    }

    // ============================================
    // ESTRUTURA DO BANCO DE DADOS
    // ============================================
    async showDatabaseStructure() {
        this.currentView = 'structure';
        
        // Atualizar breadcrumb
        document.getElementById('breadcrumbSection').textContent = 'Estrutura do Banco de Dados';
        document.getElementById('tablesTitle').textContent = 'Estrutura do Banco de Dados';
        
        try {
            const response = await fetch(`${this.apiBase}/database-structure.php`, {
                credentials: 'include'
            });
            const result = await response.json();
            
            if (result.success) {
                this.renderDatabaseStructure(result);
            } else {
                const tbody = document.getElementById('tablesBody');
                tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 40px;">Erro: ${result.message || 'Erro ao carregar estrutura do banco de dados'}</td></tr>`;
            }
        } catch (error) {
            console.error('Erro ao carregar estrutura:', error);
            const tbody = document.getElementById('tablesBody');
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 40px;">Erro ao conectar com o servidor</td></tr>';
        }
    }

    renderDatabaseStructure(data) {
        const tbody = document.getElementById('tablesBody');
        const thead = document.getElementById('tablesHead');
        
        thead.innerHTML = '<tr><th>Tabela</th><th>Colunas</th><th>Índices</th><th>Foreign Keys</th><th>Registros</th></tr>';
        
        let html = '';
        for (const [tableName, tableData] of Object.entries(data.tables)) {
            html += `
                <tr>
                    <td><strong>${tableName}</strong></td>
                    <td>
                        <div style="max-height: 200px; overflow-y: auto;">
                            ${tableData.columns.map(col => `
                                <div style="padding: 4px 0; border-bottom: 1px solid #e0ddd8;">
                                    <strong>${col.Field}</strong> 
                                    <span style="color: #666; font-size: 12px;">${col.Type}</span>
                                    ${col.Null === 'NO' ? '<span style="color: red;">*</span>' : ''}
                                </div>
                            `).join('')}
                        </div>
                    </td>
                    <td>${tableData.indexes.length} índice(s)</td>
                    <td>${tableData.foreign_keys.length} FK(s)</td>
                    <td><strong>${tableData.row_count}</strong></td>
                </tr>
            `;
        }
        
        tbody.innerHTML = html;
        document.getElementById('tablesTitle').textContent = `Estrutura do Banco de Dados - ${data.database}`;
        document.getElementById('tablesFooter').textContent = `Total: ${Object.keys(data.tables).length} tabelas | Atualizado: ${data.timestamp}`;
    }

    // ============================================
    // RENDERIZAR TABELA
    // ============================================
    renderTable(data, columns) {
        const thead = document.getElementById('tablesHead');
        const tbody = document.getElementById('tablesBody');
        
        // Cabeçalho
        thead.innerHTML = '<tr>' + columns.map(col => `<th style="width: ${col.width || 'auto'}">${col.label}</th>`).join('') + '</tr>';
        
        // Corpo
        if (data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="${columns.length}" style="text-align: center; padding: 40px;">Nenhum registro encontrado</td></tr>`;
        } else {
            tbody.innerHTML = data.map(item => {
                let row = '<tr>';
                columns.forEach(col => {
                    if (col.format === 'actions') {
                        row += `<td class="actions">
                            <a href="#" onclick="adminPanel.editItem(${item.id})" title="Editar"><i class="bi bi-pencil"></i></a>
                            <a href="#" onclick="adminPanel.deleteItem(${item.id})" title="Excluir" style="color: #dc3545;"><i class="bi bi-trash"></i></a>
                        </td>`;
                    } else if (col.format === 'badge') {
                        const status = item[col.key] || 'ativo';
                        const color = status === 'ativo' || status === 'publicado' || status === 'aberto' ? '#28a745' : '#dc3545';
                        row += `<td><span style="background: ${color}; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px;">${status}</span></td>`;
                    } else if (col.format === 'currency') {
                        const value = item[col.key] || 0;
                        row += `<td>R$ ${parseFloat(value).toFixed(2).replace('.', ',')}</td>`;
                    } else if (col.format === 'percent') {
                        row += `<td>${item[col.key] || 0}%</td>`;
                    } else if (col.format === 'date') {
                        const date = new Date(item[col.key]);
                        row += `<td>${date.toLocaleDateString('pt-BR')}</td>`;
                    } else {
                        row += `<td>${item[col.key] || '-'}</td>`;
                    }
                });
                row += '</tr>';
                return row;
            }).join('');
        }
        
        document.getElementById('tablesFooter').textContent = `Total: ${data.length} itens exibidos`;
    }

    // ============================================
    // ATUALIZAR RESUMO
    // ============================================
    updateSummary(data) {
        const ativos = data.filter(item => (item.status === 'ativo' || item.status === 'publicado' || item.status === 'aberto')).length;
        const inativos = data.length - ativos;
        const ultimaAlteracao = data.length > 0 ? new Date(data[0].updated_at || data[0].created_at).toLocaleDateString('pt-BR') : '–';
        
        document.getElementById('summaryValue1').textContent = data.length;
        document.getElementById('summaryValue2').textContent = ativos;
        document.getElementById('summaryValue3').textContent = data.filter(item => item.destaque || item.status === 'publicado').length;
        document.getElementById('summaryValue4').textContent = ultimaAlteracao;
    }

    // ============================================
    // FORMULÁRIOS
    // ============================================
    async showForm(mode, itemId = null) {
        this.currentView = 'form';
        this.currentItem = itemId;
        
        let formFields = [];
        let title = '';
        
        switch(this.currentSection) {
            case 'servicos':
                formFields = this.getServicosFormFields();
                title = mode === 'new' ? 'Novo Serviço' : 'Editar Serviço';
                break;
            case 'blogs':
                formFields = this.getBlogsFormFields();
                title = mode === 'new' ? 'Novo Post' : 'Editar Post';
                break;
            case 'promocoes':
                formFields = this.getPromocoesFormFields();
                title = mode === 'new' ? 'Nova Promoção' : 'Editar Promoção';
                break;
        }
        
        let formData = {};
        if (mode === 'edit' && itemId) {
            // Carregar dados do item
            const endpoint = `${this.apiBase}/${this.getEndpointName()}.php?id=${itemId}`;
            const response = await fetch(endpoint, {
                credentials: 'include'
            });
            const result = await response.json();
            if (result.success && result.data) {
                formData = result.data;
            }
        }
        
        this.renderModal(title, formFields, formData, mode);
    }

    getServicosFormFields() {
        return [
            { name: 'nome', label: 'Nome', type: 'text', required: true },
            { name: 'categoria', label: 'Categoria', type: 'select', options: [
                { value: 'facial', label: 'Facial' },
                { value: 'corporal', label: 'Corporal' },
                { value: 'beleza', label: 'Beleza' }
            ], required: true },
            { name: 'descricao', label: 'Descrição', type: 'textarea', required: true },
            { name: 'preco', label: 'Preço', type: 'number', step: '0.01', required: true },
            { name: 'preco_original', label: 'Preço Original', type: 'number', step: '0.01' },
            { name: 'imagem_path', label: 'Caminho da Imagem', type: 'text' },
            { name: 'status', label: 'Status', type: 'select', options: [
                { value: 'ativo', label: 'Ativo' },
                { value: 'inativo', label: 'Inativo' }
            ], required: true }
        ];
    }

    getBlogsFormFields() {
        return [
            { name: 'titulo', label: 'Título', type: 'text', required: true },
            { name: 'resumo', label: 'Resumo', type: 'textarea' },
            { name: 'conteudo', label: 'Conteúdo', type: 'textarea', required: true },
            { name: 'imagem_path', label: 'Caminho da Imagem', type: 'text' },
            { name: 'autor', label: 'Autor', type: 'text' },
            { name: 'status', label: 'Status', type: 'select', options: [
                { value: 'publicado', label: 'Publicado' },
                { value: 'rascunho', label: 'Rascunho' }
            ], required: true }
        ];
    }

    getPromocoesFormFields() {
        return [
            { name: 'titulo', label: 'Título', type: 'text', required: true },
            { name: 'descricao', label: 'Descrição', type: 'textarea', required: true },
            { name: 'desconto', label: 'Desconto (%)', type: 'number', step: '0.01' },
            { name: 'validade', label: 'Validade', type: 'date' },
            { name: 'imagem_path', label: 'Caminho da Imagem', type: 'text' },
            { name: 'status', label: 'Status', type: 'select', options: [
                { value: 'ativa', label: 'Ativa' },
                { value: 'inativa', label: 'Inativa' }
            ], required: true }
        ];
    }

    renderModal(title, fields, data, mode) {
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.id = 'formModal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3>${title}</h3>
                    <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">&times;</button>
                </div>
                <form id="itemForm">
                    ${fields.map(field => this.renderFormField(field, data[field.name] || '')).join('')}
                    <div class="modal-actions">
                        <button type="button" class="btn-modal btn-modal-secondary" onclick="document.getElementById('formModal').remove()">Cancelar</button>
                        <button type="submit" class="btn-modal btn-modal-primary">Salvar</button>
                    </div>
                </form>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Fechar ao clicar fora
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.remove();
        });
        
        // Submeter formulário
        document.getElementById('itemForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.saveItem(mode);
        });
    }

    renderFormField(field, value) {
        let input = '';
        
        if (field.type === 'select') {
            input = `
                <select name="${field.name}" ${field.required ? 'required' : ''}>
                    <option value="">Selecione...</option>
                    ${field.options.map(opt => `<option value="${opt.value}" ${value === opt.value ? 'selected' : ''}>${opt.label}</option>`).join('')}
                </select>
            `;
        } else if (field.type === 'textarea') {
            input = `<textarea name="${field.name}" ${field.required ? 'required' : ''}>${value}</textarea>`;
        } else {
            input = `<input type="${field.type}" name="${field.name}" value="${value}" step="${field.step || ''}" ${field.required ? 'required' : ''}>`;
        }
        
        return `
            <div class="form-group-modal">
                <label>${field.label} ${field.required ? '<span style="color: red;">*</span>' : ''}</label>
                ${input}
            </div>
        `;
    }

    async saveItem(mode) {
        const form = document.getElementById('itemForm');
        const formData = new FormData(form);
        const data = {};
        
        for (const [key, value] of formData.entries()) {
            data[key] = value;
        }
        
        if (mode === 'edit' && this.currentItem) {
            data.id = this.currentItem;
        }
        
        try {
            const endpoint = `${this.apiBase}/${this.getEndpointName()}.php`;
            const method = mode === 'new' ? 'POST' : 'PUT';
            
            const response = await fetch(endpoint, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
                credentials: 'include'
            });
            
            const result = await response.json();
            
            if (result.success) {
                document.getElementById('formModal').remove();
                await this.loadSection(this.currentSection);
            } else {
                alert('Erro ao salvar: ' + (result.message || 'Erro desconhecido'));
            }
        } catch (error) {
            console.error('Erro ao salvar:', error);
            alert('Erro ao salvar item');
        }
    }

    getEndpointName() {
        const endpoints = {
            'servicos': 'servicos',
            'blogs': 'blog',
            'promocoes': 'promocoes'
        };
        return endpoints[this.currentSection] || 'servicos';
    }

    editItem(id) {
        this.currentItem = id;
        this.showForm('edit', id);
    }

    async deleteItem(id) {
        if (!confirm('Tem certeza que deseja excluir este item?')) return;
        
        try {
            const endpoint = `${this.apiBase}/${this.currentSection === 'servicos' ? 'servicos' : this.currentSection === 'blogs' ? 'blog' : 'promocoes'}.php?id=${id}`;
            const response = await fetch(endpoint, { 
                method: 'DELETE',
                credentials: 'include'
            });
            const result = await response.json();
            
            if (result.success) {
                this.loadSection(this.currentSection);
            }
        } catch (error) {
            console.error('Erro ao excluir:', error);
        }
    }

    // ============================================
    // CHAT 24H
    // ============================================
    initChat() {
        // Chat será inicializado quando a seção for acessada
        this.currentTicketId = null;
    }

    searchItems(term) {
        // Implementar busca na tabela atual
        const tbody = document.getElementById('tablesBody');
        const rows = tbody.querySelectorAll('tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term.toLowerCase()) ? '' : 'none';
        });
    }

    async showChat() {
        // Criar interface de chat
        const mainContent = document.querySelector('.main-content');
        mainContent.innerHTML = `
            <div class="chat-admin-container">
                <div class="chat-header">
                    <h2><i class="bi bi-chat-dots"></i> Chat 24 Horas</h2>
                    <button class="btn-refresh" onclick="adminPanel.loadChatTickets()"><i class="bi bi-arrow-clockwise"></i></button>
                </div>
                <div style="display: grid; grid-template-columns: 300px 1fr; height: calc(100vh - 300px);">
                    <div class="chat-tickets-list" id="chatTicketsList"></div>
                    <div class="chat-messages-container" id="chatMessagesContainer" style="display: flex; flex-direction: column;">
                        <div class="chat-messages" id="chatMessages" style="flex: 1; overflow-y: auto; padding: 20px; background: var(--cor-bege);"></div>
                        <div class="chat-input-container">
                            <input type="text" id="chatInput" placeholder="Digite sua mensagem..." onkeypress="if(event.key === 'Enter') adminPanel.sendChatMessage()">
                            <button onclick="adminPanel.sendChatMessage()"><i class="bi bi-send"></i> Enviar</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        await this.loadChatTickets();
        this.startChatPolling();
    }

    async loadChatTickets() {
        try {
            const response = await fetch(`${this.apiBase}/tickets.php`, {
                credentials: 'include'
            });
            const result = await response.json();
            
            if (result.success) {
                this.renderChatTickets(result.data);
            } else {
                console.error('Erro ao carregar tickets:', result.message);
            }
        } catch (error) {
            console.error('Erro ao carregar tickets:', error);
        }
    }

    renderChatTickets(tickets) {
        const container = document.getElementById('chatTicketsList');
        if (!container) return;
        
        const abertos = tickets.filter(t => t.status === 'aberto');
        
        container.innerHTML = abertos.map(ticket => `
            <div class="chat-ticket-item" onclick="adminPanel.openChatTicket(${ticket.id})">
                <div class="ticket-info">
                    <strong>${ticket.cliente_nome || 'Anônimo'}</strong>
                    <span>${ticket.cliente_email || ''}</span>
                </div>
                <div class="ticket-status ${ticket.status}">${ticket.status}</div>
            </div>
        `).join('');
    }

    async openChatTicket(ticketId) {
        this.currentTicketId = ticketId;
        document.getElementById('chatMessagesContainer').style.display = 'flex';
        
        await this.loadChatMessages(ticketId);
    }

    async loadChatMessages(ticketId) {
        try {
            const response = await fetch(`${this.apiBase}/tickets.php?action=mensagens&id=${ticketId}`, {
                credentials: 'include'
            });
            const result = await response.json();
            
            if (result.success) {
                this.renderChatMessages(result.data);
            } else {
                console.error('Erro ao carregar mensagens:', result.message);
            }
        } catch (error) {
            console.error('Erro ao carregar mensagens:', error);
        }
    }

    renderChatMessages(messages) {
        const container = document.getElementById('chatMessages');
        if (!container) return;
        
        // Preservar scroll position para evitar piscar
        const wasAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 50;
        const oldScroll = container.scrollTop;
        
        // Gerar HTML das mensagens
        const newHtml = messages.map(msg => `
            <div class="chat-message ${msg.tipo}" data-msg-id="${msg.id}">
                <div class="message-header">
                    <strong>${msg.nome}</strong>
                    <span>${new Date(msg.created_at).toLocaleTimeString('pt-BR')}</span>
                </div>
                <div class="message-content">${this.escapeHtml(msg.mensagem)}</div>
            </div>
        `).join('');
        
        // Atualizar apenas se houver mudanças (comparar número de mensagens e IDs)
        const currentMsgIds = Array.from(container.querySelectorAll('[data-msg-id]')).map(el => el.dataset.msgId).join(',');
        const newMsgIds = messages.map(m => m.id).join(',');
        
        if (currentMsgIds !== newMsgIds) {
            // Usar requestAnimationFrame para atualização suave
            requestAnimationFrame(() => {
                container.innerHTML = newHtml;
                
                // Restaurar scroll position de forma suave
                requestAnimationFrame(() => {
                    if (wasAtBottom) {
                        container.scrollTop = container.scrollHeight;
                    } else {
                        container.scrollTop = oldScroll;
                    }
                });
            });
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    async sendChatMessage() {
        const input = document.getElementById('chatInput');
        const message = input.value.trim();
        
        if (!message || !this.currentTicketId) return;
        
        try {
            const response = await fetch(`${this.apiBase}/tickets.php?action=mensagem`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    ticket_id: this.currentTicketId,
                    mensagem: message,
                    tipo: 'admin',
                    nome: 'Administrador'
                }),
                credentials: 'include'
            });
            
            const result = await response.json();
            if (result.success) {
                input.value = '';
                await this.loadChatMessages(this.currentTicketId);
            }
        } catch (error) {
            console.error('Erro ao enviar mensagem:', error);
        }
    }

    startChatPolling() {
        // Atualizar chat a cada 2 segundos (2000ms) de forma suave sem piscar
        if (this.chatInterval) clearInterval(this.chatInterval);
        
        const updateChat = async () => {
            if (this.currentTicketId) {
                // Atualizar mensagens suavemente preservando scroll
                const container = document.getElementById('chatMessages');
                if (container) {
                    const scrollPos = container.scrollTop;
                    const isAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 50;
                    
                    await this.loadChatMessages(this.currentTicketId);
                    
                    // Usar requestAnimationFrame para atualização suave
                    requestAnimationFrame(() => {
                        if (isAtBottom) {
                            container.scrollTop = container.scrollHeight;
                        } else {
                            container.scrollTop = scrollPos;
                        }
                    });
                }
            }
            await this.loadChatTickets();
        };
        
        // Executar imediatamente
        updateChat();
        
        // Depois a cada 2 segundos
        this.chatInterval = setInterval(updateChat, 2000);
    }
}

// Inicializar quando DOM estiver pronto
let adminPanel;
document.addEventListener('DOMContentLoaded', () => {
    adminPanel = new AdminPanel();
});

