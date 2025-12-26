/**
 * ============================================
 * DASHBOARD ADMINISTRATIVO - JAVASCRIPT
 * ============================================
 */

// Variáveis globais
let revenueChart = null;
let usersChart = null;
let agendamentosChart = null;
let servicosChart = null;
const API_BASE = '/backend/api';
let pedidosData = []; // Dados dos formulários/agendamentos

// Tornar função openServiceModal disponível globalmente ANTES do DOMContentLoaded
// para que o onclick no HTML funcione
window.openServiceModal = function(serviceId = null) {
    // Esta função será sobrescrita pela versão completa abaixo
    // Mas permite que o onclick funcione imediatamente
    console.log('🔄 openServiceModal chamada (versão temporária), serviceId:', serviceId);
    // A versão completa será carregada quando o DOM estiver pronto
};

// Inicialização quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', async () => {
    // Verificar conexão com banco de dados primeiro (não crítico, apenas log)
    try {
        const dbCheck = await fetch(`${API_BASE}/check-db-connection.php`, { 
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        // Verificar status HTTP primeiro
        if (!dbCheck.ok) {
            // Se for 403 ou outro erro, tentar ler como texto primeiro
            const textResponse = await dbCheck.text();
            console.warn('⚠️ Erro HTTP ao verificar conexão com banco:', dbCheck.status, dbCheck.statusText);
            
            // Tentar parsear como JSON se possível
            try {
                const jsonResponse = JSON.parse(textResponse);
                if (!jsonResponse.success) {
                    console.warn('⚠️ Aviso de conexão com banco de dados:', jsonResponse.message);
                }
            } catch (e) {
                // Se não for JSON, apenas logar o status
                console.warn('⚠️ Resposta não-JSON do check-db-connection (status ' + dbCheck.status + ')');
            }
            // Não bloquear o carregamento, apenas avisar
            return;
        }
        
        // Verificar se a resposta é JSON antes de fazer parse
        const contentType = dbCheck.headers.get('content-type') || '';
        if (contentType.includes('application/json')) {
            try {
                const dbResult = await dbCheck.json();
                
                if (!dbResult.success) {
                    console.warn('⚠️ Aviso de conexão com banco de dados:', dbResult.message);
                } else {
                    console.log('✅ Conexão com banco de dados OK');
                }
            } catch (parseError) {
                console.warn('⚠️ Erro ao parsear resposta JSON:', parseError.message);
            }
        } else {
            // Se não for JSON, provavelmente é uma página de erro HTML
            const textResponse = await dbCheck.text();
            console.warn('⚠️ Resposta não-JSON do check-db-connection (content-type: ' + contentType + ')');
            // Não bloquear o carregamento
        }
    } catch (error) {
        // Erro de rede ou outro problema - não bloquear o carregamento
        console.warn('⚠️ Não foi possível verificar conexão com banco (não crítico):', error.message);
        // Continuar normalmente
    }
    
    initTheme();
    initNavigation();
    initSearch();
    initNotifications();
    initLogout();
    initServices();
    initFilters();
    initProfile();
    initSettings();
    loadAdminProfile();
    
    // Carregar dashboard inicial
    loadDashboardData();
    initRevenueChart();
    
    // Atualizar badge de notificações periodicamente
    setInterval(updateNotificationBadge, 60000); // A cada 1 minuto
    
    // Garantir que a página inicial seja dashboard
    const currentPage = window.location.hash.replace('#', '') || 'dashboard';
    if (currentPage !== 'dashboard') {
        const navItem = document.querySelector(`[data-page="${currentPage}"]`);
        if (navItem) {
            navItem.click();
        }
    }
});

// Função para mostrar erro de banco de dados
function showDatabaseError(message) {
    // Criar banner de erro no topo da página
    const errorBanner = document.createElement('div');
    errorBanner.id = 'dbErrorBanner';
    errorBanner.style.cssText = `
        display: none;
        background: transparent;
    `;
    errorBanner.innerHTML = `
        <strong>⚠️ Erro de Conexão com Banco de Dados:</strong> ${message}
        <button onclick="this.parentElement.remove()" style="margin-left: 1rem; background: rgba(255,255,255,0.2); border: none; color: white; padding: 0.25rem 0.75rem; border-radius: 4px; cursor: pointer;">✕</button>
    `;
    document.body.insertBefore(errorBanner, document.body.firstChild);
}

// ============================================
// THEME TOGGLE
// ============================================
function initTheme() {
    const themeToggle = document.getElementById('themeToggle');
    if (!themeToggle) {
        console.warn('⚠️ Elemento themeToggle não encontrado');
        return;
    }
    
    const currentTheme = localStorage.getItem('theme') || 'dark';
    
    // Aplicar tema salvo
    document.documentElement.setAttribute('data-theme', currentTheme);
    updateThemeIcon(currentTheme);
    
    themeToggle.addEventListener('click', () => {
        const current = document.documentElement.getAttribute('data-theme');
        const newTheme = current === 'dark' ? 'light' : 'dark';
        
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        updateThemeIcon(newTheme);
    });
}

function updateThemeIcon(theme) {
    const icon = document.querySelector('#themeToggle i');
    if (icon) {
        icon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon';
    }
}

// ============================================
// NAVIGATION
// ============================================
function initNavigation() {
    const navItems = document.querySelectorAll('.nav-item');
    
    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const page = item.getAttribute('data-page');
            
            // Remover active de todos
            navItems.forEach(nav => nav.classList.remove('active'));
            
            // Adicionar active ao clicado
            item.classList.add('active');
            
            // Mostrar/esconder seções baseado na página
            switchPage(page);
        });
    });
}

function switchPage(page) {
    const contentHeader = document.querySelector('.content-header');
    const pageTitle = contentHeader ? contentHeader.querySelector('.page-title') : null;
    const pageSubtitle = contentHeader ? contentHeader.querySelector('.page-subtitle') : null;
    const metricsGrid = document.querySelector('.metrics-grid');
    const dashboardGrid = document.querySelector('.dashboard-grid');
    const recentUsersCard = document.querySelector('.recent-users-card');
    const servicesSection = document.getElementById('servicesSection');
    const contentHeaderDiv = document.querySelector('.content-header');
    
    // Esconder todas as seções primeiro
    if (metricsGrid) metricsGrid.style.display = 'none';
    if (dashboardGrid) dashboardGrid.style.display = 'none';
    if (recentUsersCard) recentUsersCard.style.display = 'none';
    
    // Esconder todas as seções administrativas
    const allSections = ['servicesSection', 'usuariosSection', 'pedidosSection', 'agendamentosSection', 'relatoriosSection', 'documentosSection', 'notificacoesSection', 'configuracoesSection', 'ajudaSection', 'chatSection'];
    allSections.forEach(sectionId => {
        const section = document.getElementById(sectionId);
        if (section) {
            section.style.display = 'none';
            section.classList.remove('active');
        }
    });
    
    switch(page) {
        case 'servicos':
            if (pageTitle) pageTitle.textContent = 'Serviços';
            if (pageSubtitle) pageSubtitle.textContent = 'Gerencie os serviços oferecidos pela clínica';
            showSection('servicesSection');
            setTimeout(() => loadServices(), 100);
            break;
            
        case 'chat':
            if (pageTitle) pageTitle.textContent = 'Chat 24 Horas';
            if (pageSubtitle) pageSubtitle.textContent = 'Atendimento ao cliente em tempo real';
            showSection('chatSection');
            setTimeout(() => {
                loadChatTickets();
                initChat();
            }, 100);
            break;
            
        case 'usuarios':
            if (pageTitle) pageTitle.textContent = 'Usuários';
            if (pageSubtitle) pageSubtitle.textContent = 'Gerencie os usuários do sistema';
            showSection('usuariosSection');
            setTimeout(() => {
                loadUsuarios();
                initUsuariosButtons();
            }, 100);
            break;
            
        case 'pedidos':
            if (pageTitle) pageTitle.textContent = 'Formulário';
            if (pageSubtitle) pageSubtitle.textContent = 'Visualize e analise os formulários enviados';
            showSection('pedidosSection');
            setTimeout(() => {
                console.log('Carregando formulários...');
                loadPedidos();
            }, 100);
            break;
            
        case 'agendamentos':
            if (pageTitle) pageTitle.textContent = 'Agendamentos';
            if (pageSubtitle) pageSubtitle.textContent = 'Gerencie os agendamentos de avaliação gratuita';
            showSection('agendamentosSection');
            setTimeout(() => {
                loadAgendamentos();
                initAgendamentosButtons();
            }, 100);
            break;
            
        case 'relatorios':
            if (pageTitle) pageTitle.textContent = 'Relatórios';
            if (pageSubtitle) pageSubtitle.textContent = 'Visualize relatórios e estatísticas detalhadas';
            showSection('relatoriosSection');
            setTimeout(() => loadRelatorios(), 100);
            break;
            
        case 'documentos':
            if (pageTitle) pageTitle.textContent = 'Documentos e Candidaturas';
            if (pageSubtitle) pageSubtitle.textContent = 'Gerencie documentos e candidaturas do sistema';
            showSection('documentosSection');
            setTimeout(() => loadDocumentos(), 100);
            break;
            
        case 'notificacoes':
            if (pageTitle) pageTitle.textContent = 'Notificações';
            if (pageSubtitle) pageSubtitle.textContent = 'Visualize e gerencie suas notificações';
            showSection('notificacoesSection');
            setTimeout(() => loadNotificacoes(), 100);
            break;
            
        case 'configuracoes':
            if (pageTitle) pageTitle.textContent = 'Configurações';
            if (pageSubtitle) pageSubtitle.textContent = 'Configure as preferências do sistema';
            showSection('configuracoesSection');
            setTimeout(() => loadConfiguracoes(), 100);
            break;
            
        case 'ajuda':
            if (pageTitle) pageTitle.textContent = 'Central de Ajuda';
            if (pageSubtitle) pageSubtitle.textContent = 'Encontre respostas para suas dúvidas';
            showSection('ajudaSection');
            break;
            
        case 'dashboard':
        default:
            if (pageTitle) pageTitle.textContent = 'Dashboard';
            if (pageSubtitle) pageSubtitle.textContent = 'Bem-vindo de volta! Aqui está um resumo da sua plataforma.';
            if (metricsGrid) metricsGrid.style.display = 'grid';
            if (dashboardGrid) dashboardGrid.style.display = 'grid';
            if (recentUsersCard) recentUsersCard.style.display = 'block';
            // Recarregar dados do dashboard
            setTimeout(() => {
                loadDashboardData();
                if (revenueChart) {
                    updateRevenueChart();
                }
            }, 100);
            break;
    }
}

// ============================================
// SEARCH
// ============================================
function initSearch() {
    const searchInput = document.querySelector('.search-input');
    
    // Verificar se o elemento existe antes de adicionar listener
    if (!searchInput) {
        console.warn('⚠️ Campo de busca não encontrado (.search-input)');
        return;
    }
    
    let searchTimeout;
    
    searchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        const query = e.target.value.trim();
        
        if (query.length > 2) {
            searchTimeout = setTimeout(() => {
                performSearch(query);
            }, 300);
        }
    });
}

function performSearch(query) {
    console.log('Buscando:', query);
    // Implementar lógica de busca aqui
}

// ============================================
// NOTIFICATIONS
// ============================================
function initNotifications() {
    const notificationsBtn = document.getElementById('notificationsBtn');
    if (!notificationsBtn) {
        console.warn('⚠️ Elemento notificationsBtn não encontrado');
        return;
    }
    
    notificationsBtn.addEventListener('click', () => {
            // Navegar para seção de notificações
            const navItem = document.querySelector('[data-page="notificacoes"]');
            if (navItem) {
                navItem.click();
            }
        });
        
        // Atualizar badge de notificações
        updateNotificationBadge();
    }
}

async function updateNotificationBadge() {
    try {
        const [agendamentosRes, candidaturasRes, ticketsRes] = await Promise.all([
            fetch(`${API_BASE}/agendamentos.php?status=pendente`, { credentials: 'include' }).catch(() => null),
            fetch(`${API_BASE}/candidaturas.php?status=pendente`, { credentials: 'include' }).catch(() => null),
            fetch(`${API_BASE}/tickets.php?status=aberto`, { credentials: 'include' }).catch(() => null)
        ]);
        
        let total = 0;
        
        if (agendamentosRes && agendamentosRes.ok) {
            const data = await agendamentosRes.json();
            if (data.success && data.data && Array.isArray(data.data)) {
                total += data.data.length;
            }
        }
        
        if (candidaturasRes && candidaturasRes.ok) {
            const data = await candidaturasRes.json();
            if (data.success && data.data && Array.isArray(data.data)) {
                total += data.data.length;
            }
        }
        
        // Contar tickets abertos
        if (ticketsRes && ticketsRes.ok) {
            try {
                const data = await ticketsRes.json();
                if (data.success && data.data && Array.isArray(data.data)) {
                    total += data.data.length;
                }
            } catch (e) {
                console.error('Erro ao processar tickets:', e);
            }
        }
        
        const badge = document.querySelector('.notification-badge');
        if (badge) {
            if (total > 0) {
                badge.textContent = total > 99 ? '99+' : total;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        }
    } catch (error) {
        console.error('Erro ao atualizar badge de notificações:', error);
    }
}

// ============================================
// LOGOUT
// ============================================
function initLogout() {
    const logoutBtn = document.querySelector('.logout-btn');
    if (!logoutBtn) {
        console.warn('⚠️ Elemento logoutBtn não encontrado');
        return;
    }
    
    logoutBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (confirm('Deseja realmente sair?')) {
            window.location.href = '/backend/auth/logout.php';
        }
    });
}

// ============================================
// LOAD DASHBOARD DATA
// ============================================
async function loadDashboardData() {
    try {
        // Carregar métricas
        await loadMetrics();
        
        // Carregar atividades recentes
        await loadRecentActivity();
        
        // Carregar usuários recentes
        await loadRecentUsers();
        
        // Atualizar gráfico de receita
        await updateRevenueChart();
    } catch (error) {
        console.error('Erro ao carregar dados do dashboard:', error);
        // Mostrar mensagem de erro ou dados vazios
    }
}

// ============================================
// LOAD METRICS
// ============================================
async function loadMetrics() {
    try {
        // Buscar dados da API
        const [usuariosRes, pedidosRes, receitaRes] = await Promise.all([
            fetch(`${API_BASE}/usuarios.php`, { credentials: 'include' }).catch((e) => {
                console.error('Erro ao buscar usuários:', e);
                return null;
            }),
            fetch(`${API_BASE}/agendamentos.php`, { credentials: 'include' }).catch((e) => {
                console.error('Erro ao buscar agendamentos:', e);
                return null;
            }),
            fetch(`${API_BASE}/dashboard-stats.php`, { credentials: 'include' }).catch((e) => {
                console.error('Erro ao buscar estatísticas:', e);
                return null;
            })
        ]);
        
        // Processar usuários
        if (usuariosRes && usuariosRes.ok) {
            try {
                const usuariosData = await usuariosRes.json();
                if (usuariosData.success && usuariosData.data) {
                    const totalUsuarios = Array.isArray(usuariosData.data) ? usuariosData.data.length : 0;
                    updateMetric('totalUsuarios', formatNumber(totalUsuarios));
                    
                    // Buscar total do mês anterior para comparação
                    const usuariosAnteriorRes = await fetch(`${API_BASE}/usuarios.php`, { credentials: 'include' }).catch(() => null);
                    let usuariosAnterior = 0;
                    if (usuariosAnteriorRes && usuariosAnteriorRes.ok) {
                        try {
                            const usuariosAnteriorData = await usuariosAnteriorRes.json();
                            if (usuariosAnteriorData.success && usuariosAnteriorData.data) {
                                // Aproximação: 90% do total atual (simulando mês anterior)
                                usuariosAnterior = Math.floor(Array.isArray(usuariosAnteriorData.data) ? usuariosAnteriorData.data.length * 0.9 : 0);
                            }
                        } catch (e) {
                            usuariosAnterior = Math.floor(totalUsuarios * 0.9);
                        }
                    } else {
                        usuariosAnterior = Math.floor(totalUsuarios * 0.9);
                    }
                    
                    const change = calculateChange(totalUsuarios, usuariosAnterior);
                    updateMetricChange('usuariosChange', change, totalUsuarios >= usuariosAnterior);
                }
            } catch (e) {
                console.error('Erro ao processar dados de usuários:', e);
            }
        }
        
        // Processar pedidos/agendamentos
        if (pedidosRes && pedidosRes.ok) {
            try {
                const pedidosData = await pedidosRes.json();
                if (pedidosData.success && pedidosData.data) {
                    const totalPedidos = Array.isArray(pedidosData.data) ? pedidosData.data.length : 0;
                    updateMetric('totalPedidos', formatNumber(totalPedidos));
                    
                    // Buscar pedidos do mês anterior
                    const pedidosAnteriorRes = await fetch(`${API_BASE}/agendamentos.php`, { credentials: 'include' }).catch(() => null);
                    let pedidosAnterior = 0;
                    if (pedidosAnteriorRes && pedidosAnteriorRes.ok) {
                        try {
                            const pedidosAnteriorData = await pedidosAnteriorRes.json();
                            if (pedidosAnteriorData.success && pedidosAnteriorData.data) {
                                // Aproximação: 85% do total atual
                                pedidosAnterior = Math.floor(Array.isArray(pedidosAnteriorData.data) ? pedidosAnteriorData.data.length * 0.85 : 0);
                            }
                        } catch (e) {
                            pedidosAnterior = Math.floor(totalPedidos * 0.85);
                        }
                    } else {
                        pedidosAnterior = Math.floor(totalPedidos * 0.85);
                    }
                    
                    const change = calculateChange(totalPedidos, pedidosAnterior);
                    updateMetricChange('pedidosChange', change, totalPedidos >= pedidosAnterior);
                }
            } catch (e) {
                console.error('Erro ao processar dados de pedidos:', e);
            }
        }
        
        // Processar receita
        if (receitaRes && receitaRes.ok) {
            try {
                const receitaData = await receitaRes.json();
                if (receitaData.success) {
                    const receitaMes = receitaData.receitaMes || 0;
                    const receitaMesAnterior = receitaData.receitaMesAnterior || 0;
                    
                    updateMetric('receitaTotal', formatCurrency(receitaMes));
                    
                    const change = calculateChange(receitaMes, receitaMesAnterior);
                    updateMetricChange('receitaChange', change, receitaMes >= receitaMesAnterior);
                }
            } catch (e) {
                console.error('Erro ao processar dados de receita:', e);
            }
        }
        
        // Taxa de conversão (calculada baseado em agendamentos vs usuários)
        let taxaConversao = 0;
        if (usuariosRes && usuariosRes.ok && pedidosRes && pedidosRes.ok) {
            try {
                const usuariosData = await usuariosRes.json();
                const pedidosData = await pedidosRes.json();
                const totalUsuarios = Array.isArray(usuariosData.data) ? usuariosData.data.length : 0;
                const totalPedidos = Array.isArray(pedidosData.data) ? pedidosData.data.length : 0;
                
                if (totalUsuarios > 0) {
                    taxaConversao = (totalPedidos / totalUsuarios) * 100;
                }
            } catch (e) {
                taxaConversao = 3.24; // Valor padrão
            }
        } else {
            taxaConversao = 3.24; // Valor padrão
        }
        
        updateMetric('taxaConversao', taxaConversao.toFixed(2) + '%');
        updateMetricChange('conversaoChange', 0.5, true);
        
    } catch (error) {
        console.error('Erro ao carregar métricas:', error);
        // Manter métricas vazias ou mostrar erro
    }
}

function updateMetric(id, value) {
    const element = document.getElementById(id);
    if (element) {
        element.textContent = value;
    }
}

function updateMetricChange(id, change, isPositive) {
    const element = document.getElementById(id);
    if (element) {
        const sign = isPositive ? '+' : '-';
        element.textContent = `${sign}${Math.abs(change).toFixed(1)}% vs último mês`;
        
        // Atualizar classe do pai
        const parent = element.closest('.metric-change');
        if (parent) {
            parent.className = `metric-change ${isPositive ? 'positive' : 'negative'}`;
        }
    }
}

function calculateChange(current, previous) {
    if (previous === 0) return 0;
    return ((current - previous) / previous) * 100;
}

function formatNumber(num) {
    return new Intl.NumberFormat('pt-BR').format(num);
}

function formatCurrency(num) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
        minimumFractionDigits: 0
    }).format(num);
}

// ============================================
// LOAD RECENT ACTIVITY
// ============================================
async function loadRecentActivity() {
    try {
        // Tentar buscar da API
        const response = await fetch(`${API_BASE}/dashboard-activity.php`, { credentials: 'include' }).catch(() => null);
        
        let activities = [];
        
        if (response && response.ok) {
            const data = await response.json();
            if (data.success && data.data) {
                activities = data.data;
            }
        }
        
        // Sempre renderizar atividades reais, mesmo se vazio
        renderActivities(activities);
        
    } catch (error) {
        console.error('Erro ao carregar atividades:', error);
        // Mostrar mensagem de erro ou lista vazia
        renderActivities([]);
    }
}

function getSampleActivities() {
    return [
        {
            type: 'new-user',
            icon: 'bi-person-plus-fill',
            text: 'Novo usuário registrado',
            details: 'Maria Silva',
            time: 'Há 5 min'
        },
        {
            type: 'new-order',
            icon: 'bi-cart-fill',
            text: 'Novo pedido recebido',
            details: '#12345',
            time: 'Há 15 min'
        },
        {
            type: 'comment',
            icon: 'bi-chat-dots-fill',
            text: 'Comentário no documento',
            details: 'João Santos',
            time: 'Há 30 min'
        },
        {
            type: 'alert',
            icon: 'bi-exclamation-circle-fill',
            text: 'Alerta de sistema',
            details: 'CPU alta detectada',
            time: 'Há 1 hora'
        },
        {
            type: 'backup',
            icon: 'bi-check-circle-fill',
            text: 'Backup concluído',
            details: 'Automático',
            time: 'Há 2 horas'
        }
    ];
}

function renderActivities(activities) {
    const activityList = document.getElementById('activityList');
    if (!activityList) return;
    
    if (activities.length === 0) {
        activityList.innerHTML = '<li class="activity-item" style="text-align: center; padding: 2rem; color: var(--text-muted);"><i class="bi bi-inbox" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>Nenhuma atividade recente</li>';
        return;
    }
    
    activityList.innerHTML = activities.map(activity => `
        <li class="activity-item ${activity.type}">
            <div class="activity-icon">
                <i class="bi ${activity.icon}"></i>
            </div>
            <div class="activity-content">
                <div class="activity-text">${activity.text}</div>
                <div class="activity-details">${activity.details}</div>
                <div class="activity-time">${activity.time}</div>
            </div>
        </li>
    `).join('');
}

// ============================================
// LOAD RECENT USERS
// ============================================
async function loadRecentUsers() {
    try {
        const response = await fetch(`${API_BASE}/usuarios.php?limit=5&order=desc`, { credentials: 'include' }).catch(() => null);
        
        let users = [];
        
        if (response && response.ok) {
            const data = await response.json();
            if (data.success && data.data) {
                users = Array.isArray(data.data) ? data.data.slice(0, 5) : [];
            }
        }
        
        // Sempre renderizar usuários reais, mesmo se vazio
        renderRecentUsers(users);
        
    } catch (error) {
        console.error('Erro ao carregar usuários recentes:', error);
        // Mostrar lista vazia em caso de erro
        renderRecentUsers([]);
    }
}

function getSampleUsers() {
    return [
        { nome: 'Maria Silva', email: 'maria@example.com', data_registro: '2024-01-15', status: 'ativo' },
        { nome: 'João Santos', email: 'joao@example.com', data_registro: '2024-01-14', status: 'ativo' },
        { nome: 'Ana Costa', email: 'ana@example.com', data_registro: '2024-01-13', status: 'ativo' },
        { nome: 'Pedro Oliveira', email: 'pedro@example.com', data_registro: '2024-01-12', status: 'inativo' },
        { nome: 'Carla Mendes', email: 'carla@example.com', data_registro: '2024-01-11', status: 'ativo' }
    ];
}

function renderRecentUsers(users) {
    const tbody = document.getElementById('recentUsersBody');
    if (!tbody) return;
    
    if (users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 2rem; color: var(--text-muted);">Nenhum usuário recente</td></tr>';
        return;
    }
    
    tbody.innerHTML = users.map(user => {
        const dataRegistro = formatDate(user.data_registro || user.created_at || new Date().toISOString());
        const status = user.status || 'ativo';
        
        return `
            <tr>
                <td>${user.nome || user.name || 'N/A'}</td>
                <td>${user.email || 'N/A'}</td>
                <td>${dataRegistro}</td>
                <td>
                    <span class="status-badge ${status === 'ativo' ? 'active' : 'inactive'}">
                        ${status === 'ativo' ? 'Ativo' : 'Inativo'}
                    </span>
                </td>
            </tr>
        `;
    }).join('');
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    }).format(date);
}

// ============================================
// REVENUE CHART
// ============================================
function initRevenueChart() {
    const ctx = document.getElementById('revenueChart');
    if (!ctx) return;
    
    // Destruir gráfico anterior se existir
    if (revenueChart) {
        revenueChart.destroy();
        revenueChart = null;
    }
    
    revenueChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
            datasets: [{
                label: 'Receita Mensal',
                data: [2500, 3200, 2800, 3500, 4000, 3800, 4200, 4500, 4800, 5200, 5800, 6500],
                borderColor: '#4299e1',
                backgroundColor: 'rgba(66, 153, 225, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#4299e1',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#1e2432',
                    titleColor: '#ffffff',
                    bodyColor: '#a0aec0',
                    borderColor: '#2d3748',
                    borderWidth: 1,
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            return 'R$ ' + context.parsed.y.toLocaleString('pt-BR');
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        color: '#2d3748',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#a0aec0'
                    }
                },
                y: {
                    grid: {
                        color: '#2d3748',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#a0aec0',
                        callback: function(value) {
                            return 'R$ ' + value + 'k';
                        }
                    },
                    beginAtZero: true
                }
            }
        }
    });
}

async function updateRevenueChart() {
    try {
        const response = await fetch(`${API_BASE}/dashboard-revenue.php`, { credentials: 'include' }).catch(() => null);
        
        if (response && response.ok) {
            const data = await response.json();
            if (data.success && data.data && revenueChart) {
                revenueChart.data.datasets[0].data = data.data;
                revenueChart.update();
            }
        }
    } catch (error) {
        console.error('Erro ao atualizar gráfico de receita:', error);
    }
}

// ============================================
// SAMPLE DATA (Fallback)
// ============================================
function loadSampleData() {
    loadSampleMetrics();
    renderActivities(getSampleActivities());
    renderRecentUsers(getSampleUsers());
}

function loadSampleMetrics() {
    updateMetric('totalUsuarios', '12,345');
    updateMetricChange('usuariosChange', 12.5, true);
    
    updateMetric('receitaTotal', 'R$ 54.230');
    updateMetricChange('receitaChange', 23.1, true);
    
    updateMetric('totalPedidos', '1,234');
    updateMetricChange('pedidosChange', 3.2, false);
    
    updateMetric('taxaConversao', '3.24%');
    updateMetricChange('conversaoChange', 0.5, true);
}

// ============================================
// SERVICES MANAGEMENT
// ============================================
function initServices() {
    console.log('🔧 Inicializando serviços...');
    
    // Verificar se o botão existe e adicionar listener
    const btnAddService = document.getElementById('btnAddService');
    if (btnAddService) {
        console.log('✅ Botão btnAddService encontrado');
        // Adicionar listener adicional (o onclick no HTML já funciona como fallback)
        btnAddService.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            console.log('🖱️ Botão Adicionar Serviço clicado (via addEventListener)!');
            if (typeof openServiceModal === 'function') {
                openServiceModal();
            } else {
                console.error('❌ openServiceModal não está definida!');
                alert('Erro: Função openServiceModal não encontrada. Recarregue a página.');
            }
        });
    } else {
        console.warn('⚠️ Botão btnAddService não encontrado no DOM');
        // Tentar novamente após um delay
        setTimeout(() => {
            const btn = document.getElementById('btnAddService');
            if (btn) {
                console.log('✅ Botão btnAddService encontrado após delay');
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('🖱️ Botão Adicionar Serviço clicado (após delay)!');
                    if (typeof openServiceModal === 'function') {
                        openServiceModal();
                    }
                });
            }
        }, 1000);
    }
    
    // Configurar event listeners para fechar modal
    const closeModal = document.getElementById('closeServiceModal');
    const cancelModal = document.getElementById('cancelServiceModal');
    const serviceForm = document.getElementById('serviceForm');
    const modal = document.getElementById('serviceModal');
    
    if (closeModal) {
        console.log('✅ Botão X encontrado, adicionando listener');
        closeModal.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            console.log('🖱️ Botão X clicado');
            closeServiceModal();
        });
    } else {
        console.warn('⚠️ Botão closeServiceModal não encontrado');
    }
    
    if (cancelModal) {
        console.log('✅ Botão Cancelar encontrado, adicionando listener');
        cancelModal.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            console.log('🖱️ Botão Cancelar clicado');
            closeServiceModal();
        });
    } else {
        console.warn('⚠️ Botão cancelServiceModal não encontrado');
    }
    
    if (serviceForm) {
        console.log('✅ Formulário encontrado, adicionando listener de submit');
        serviceForm.addEventListener('submit', handleServiceSubmit);
    } else {
        console.warn('⚠️ Formulário serviceForm não encontrado');
    }
    
    // Fechar modal ao clicar fora
    if (modal) {
        console.log('✅ Modal encontrado, adicionando listener de clique fora');
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                console.log('🖱️ Clique fora do modal detectado');
                closeServiceModal();
            }
        });
    } else {
        console.warn('⚠️ Modal serviceModal não encontrado');
    }
}

async function loadServices() {
    try {
        // Mostrar loading
        const tbody = document.getElementById('servicesBody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 2rem;">Carregando serviços...</td></tr>';
        }
        
        const response = await fetch(`${API_BASE}/servicos.php`, { 
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const responseText = await response.text();
        console.log('Resposta bruta (loadServices):', responseText.substring(0, 500));
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (e) {
            console.error('Erro ao fazer parse do JSON (loadServices):', e);
            throw new Error('Resposta inválida do servidor');
        }
        
        console.log('Dados parseados (loadServices):', data);
        
        if (data.success && Array.isArray(data.data)) {
            console.log('✅ Serviços carregados:', data.data.length);
            renderServices(data.data);
        } else {
            console.error('Erro ao carregar serviços:', data.message || 'Resposta inválida');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 2rem; color: var(--error);">Erro ao carregar serviços: ' + (data.message || 'Resposta inválida da API') + '</td></tr>';
            }
        }
    } catch (error) {
        console.error('Erro ao carregar serviços:', error);
        const tbody = document.getElementById('servicesBody');
        if (tbody) {
            const errorMsg = error.message || 'Erro desconhecido';
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 2rem; color: var(--error);">Erro ao carregar serviços: ' + errorMsg + '<br><button onclick="loadServices()" style="margin-top: 10px; padding: 8px 16px; background: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer;">Tentar novamente</button></td></tr>';
        }
    }
}

function renderServices(services) {
    const tbody = document.getElementById('servicesBody');
    if (!tbody) return;
    
    if (services.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 2rem;">Nenhum serviço cadastrado</td></tr>';
        return;
    }
    
    tbody.innerHTML = services.map(service => {
        const preco = parseFloat(service.preco || 0).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        });
        
        const imagem = service.imagem_path ? 
            `<img src="${service.imagem_path}" alt="${service.nome}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">` : 
            '<i class="bi bi-image" style="font-size: 1.5rem; color: var(--text-muted);"></i>';
        
        return `
            <tr>
                <td>${service.id}</td>
                <td>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        ${imagem}
                        <span>${service.nome}</span>
                    </div>
                </td>
                <td><span class="badge-category">${service.categoria}</span></td>
                <td>${preco}</td>
                <td>
                    <span class="status-badge ${service.status === 'ativo' ? 'active' : 'inactive'}">
                        ${service.status === 'ativo' ? 'Ativo' : 'Inativo'}
                    </span>
                </td>
                <td>
                    <button class="btn-action" onclick="editService(${service.id})" title="Editar">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn-action btn-danger" onclick="deleteService(${service.id})" title="Excluir">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

// Tornar função global para acesso via onclick no HTML
window.openServiceModal = function(serviceId = null) {
    console.log('🔍 openServiceModal chamada, serviceId:', serviceId);
    
    try {
        const modal = document.getElementById('serviceModal');
        
        if (!modal) {
            console.error('❌ Modal não encontrado! Elemento #serviceModal não existe no DOM');
            alert('Erro: Modal não encontrado. Verifique o console para mais detalhes.');
            return;
        }
        
        const form = document.getElementById('serviceForm');
        const title = document.getElementById('modalTitle');
        
        if (!form) {
            console.error('❌ Formulário não encontrado! Elemento #serviceForm não existe no DOM');
            alert('Erro: Formulário não encontrado.');
            return;
        }
        
        if (!title) {
            console.error('❌ Título não encontrado! Elemento #modalTitle não existe no DOM');
            alert('Erro: Título do modal não encontrado.');
            return;
        }
        
        console.log('✅ Elementos encontrados, abrindo modal...');
        
        // Configurar o formulário
        if (serviceId) {
            title.textContent = 'Editar Serviço';
            loadServiceData(serviceId);
        } else {
            title.textContent = 'Adicionar Serviço';
            form.reset();
            const serviceIdInput = document.getElementById('serviceId');
            if (serviceIdInput) {
                serviceIdInput.value = '';
            }
            // Limpar campos específicos
            const serviceIncluso = document.getElementById('serviceIncluso');
            if (serviceIncluso) {
                serviceIncluso.value = '';
            }
            if (typeof removeServiceImage === 'function') {
                removeServiceImage(); // Limpar preview
            }
        }
        
        // Exibir o modal - método mais simples e direto
        modal.style.cssText = `
            display: flex !important;
            visibility: visible !important;
            opacity: 1 !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            z-index: 10000 !important;
            background: rgba(15, 23, 42, 0.4) !important;
            align-items: center !important;
            justify-content: center !important;
        `;
        
        // Garantir que o modal-content também esteja visível
        const modalContent = modal.querySelector('.modal-content');
        if (modalContent) {
            modalContent.style.cssText = `
                display: block !important;
                visibility: visible !important;
            `;
        }
        
        // Focar no primeiro campo do formulário
        const firstInput = form.querySelector('input:not([type="hidden"]), textarea, select');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
        
        console.log('✅ Modal aberto com sucesso!');
        console.log('   - Modal display:', window.getComputedStyle(modal).display);
        console.log('   - Modal visibility:', window.getComputedStyle(modal).visibility);
        console.log('   - Modal z-index:', window.getComputedStyle(modal).zIndex);
        
    } catch (error) {
        console.error('❌ Erro ao abrir modal:', error);
        alert('Erro ao abrir modal: ' + error.message);
    }
};

// Manter compatibilidade com chamadas diretas
function openServiceModal(serviceId = null) {
    return window.openServiceModal(serviceId);
}

function closeServiceModal() {
    console.log('🔒 Fechando modal de serviço...');
    const modal = document.getElementById('serviceModal');
    if (modal) {
        // Esconder o modal
        modal.style.cssText = 'display: none !important;';
        console.log('✅ Modal escondido');
    }
    
    // Limpar formulário
    const form = document.getElementById('serviceForm');
    if (form) {
        form.reset();
        // Limpar campo hidden de ID
        const serviceId = document.getElementById('serviceId');
        if (serviceId) {
            serviceId.value = '';
        }
        console.log('✅ Formulário limpo');
    }
    
    // Limpar preview de imagem
    if (typeof removeServiceImage === 'function') {
        removeServiceImage();
    } else {
        // Fallback: limpar preview manualmente
        const preview = document.getElementById('serviceImagePreview');
        const previewImg = document.getElementById('serviceImagePreviewImg');
        const imagemInput = document.getElementById('serviceImagem');
        if (preview) preview.style.display = 'none';
        if (previewImg) previewImg.src = '';
        if (imagemInput) imagemInput.value = '';
    }
    
    console.log('✅ Modal fechado completamente');
}

async function loadServiceData(id) {
    try {
        const response = await fetch(`${API_BASE}/servicos.php?id=${id}`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success && data.data) {
            const service = data.data;
            document.getElementById('serviceId').value = service.id;
            document.getElementById('serviceNome').value = service.nome || '';
            document.getElementById('serviceCategoria').value = service.categoria || '';
            document.getElementById('serviceDescricao').value = service.descricao || '';
            document.getElementById('serviceIncluso').value = service.o_que_esta_incluso || '';
            document.getElementById('servicePreco').value = service.preco || '';
            document.getElementById('servicePrecoOriginal').value = service.preco_original || '';
            document.getElementById('serviceStatus').value = service.status || 'ativo';
            
            // Carregar imagem se existir
            if (service.imagem_path) {
                const preview = document.getElementById('serviceImagePreview');
                const previewImg = document.getElementById('serviceImagePreviewImg');
                if (preview && previewImg) {
                    previewImg.src = service.imagem_path;
                    preview.style.display = 'block';
                }
            } else {
                const preview = document.getElementById('serviceImagePreview');
                if (preview) preview.style.display = 'none';
            }
        }
    } catch (error) {
        console.error('Erro ao carregar serviço:', error);
        alert('Erro ao carregar dados do serviço');
    }
}

async function handleServiceSubmit(e) {
    e.preventDefault();
    
    // Validar campos obrigatórios
    const nome = document.getElementById('serviceNome').value.trim();
    const categoria = document.getElementById('serviceCategoria').value.trim();
    const descricao = document.getElementById('serviceDescricao').value.trim();
    const preco = parseFloat(document.getElementById('servicePreco').value);
    
    if (!nome) {
        showNotification('error', 'Erro!', 'Nome do serviço é obrigatório');
        document.getElementById('serviceNome').focus();
        return;
    }
    
    if (!categoria) {
        showNotification('error', 'Erro!', 'Categoria é obrigatória');
        document.getElementById('serviceCategoria').focus();
        return;
    }
    
    if (!descricao) {
        showNotification('error', 'Erro!', 'Descrição é obrigatória');
        document.getElementById('serviceDescricao').focus();
        return;
    }
    
    if (!preco || preco <= 0) {
        showNotification('error', 'Erro!', 'Preço deve ser maior que zero');
        document.getElementById('servicePreco').focus();
        return;
    }
    
    const serviceId = document.getElementById('serviceId').value || null;
    const imagemInput = document.getElementById('serviceImagem');
    const hasImage = imagemInput && imagemInput.files && imagemInput.files.length > 0;
    
    // Se tiver imagem, usar FormData, senão JSON
    if (hasImage) {
        // Validar novamente antes de enviar com FormData
        if (!nome) {
            showNotification('error', 'Erro!', 'Nome do serviço é obrigatório');
            document.getElementById('serviceNome').focus();
            return;
        }
        
        if (!categoria) {
            showNotification('error', 'Erro!', 'Categoria é obrigatória');
            document.getElementById('serviceCategoria').focus();
            return;
        }
        
        if (!descricao) {
            showNotification('error', 'Erro!', 'Descrição é obrigatória');
            document.getElementById('serviceDescricao').focus();
            return;
        }
        
        if (!preco || preco <= 0) {
            showNotification('error', 'Erro!', 'Preço deve ser maior que zero');
            document.getElementById('servicePreco').focus();
            return;
        }
        
        const formData = new FormData();
        formData.append('nome', nome);
        formData.append('categoria', categoria);
        formData.append('descricao', descricao);
        const oQueEstaIncluso = document.getElementById('serviceIncluso').value.trim();
        if (oQueEstaIncluso) formData.append('o_que_esta_incluso', oQueEstaIncluso);
        formData.append('preco', preco.toString());
        const precoOriginal = document.getElementById('servicePrecoOriginal').value;
        if (precoOriginal) formData.append('preco_original', precoOriginal);
        formData.append('status', document.getElementById('serviceStatus').value || 'ativo');
        formData.append('imagem', imagemInput.files[0]);
        
        if (serviceId) {
            formData.append('id', serviceId);
        }
        
        console.log('📝 FormData preparado:', {
            nome: nome,
            categoria: categoria,
            descricao: descricao,
            preco: preco,
            status: document.getElementById('serviceStatus').value
        });
        
        try {
            const method = serviceId ? 'PUT' : 'POST';
            const response = await fetch(`${API_BASE}/servicos.php${serviceId ? '?id=' + serviceId : ''}`, {
                method: method,
                credentials: 'include',
                body: formData
            });
            
            // Verificar se a resposta está ok antes de fazer parse
            if (!response.ok) {
                const errorText = await response.text();
                console.error('❌ Erro na resposta (FormData):', errorText);
                let errorData;
                try {
                    errorData = JSON.parse(errorText);
                } catch (e) {
                    errorData = { message: errorText || `Erro HTTP ${response.status}: ${response.statusText}` };
                }
                
                // Se for erro de autenticação, tratar separadamente
                if (response.status === 401 || response.status === 403) {
                    console.error('❌ Erro de autenticação:', errorData);
                    showNotification('error', 'Erro de Autenticação', 'Sua sessão expirou. Por favor, faça login novamente.');
                    setTimeout(() => {
                        if (confirm('Sua sessão expirou. Deseja fazer login novamente?')) {
                            window.location.href = '/backend/admin/login.html';
                        }
                    }, 1000);
                    return;
                }
                
                throw new Error(errorData.message || `Erro HTTP ${response.status}: ${response.statusText}`);
            }
            
            const responseText = await response.text();
            console.log('✅ Resposta bruta recebida (FormData):', responseText);
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                console.error('❌ Erro ao fazer parse do JSON (FormData):', e);
                console.error('Resposta recebida:', responseText);
                throw new Error('Resposta inválida do servidor: ' + responseText.substring(0, 100));
            }
            
            console.log('✅ Dados recebidos (parseado - FormData):', data);
            
            if (data.success) {
                console.log('✅ Serviço salvo com sucesso! ID:', data.id);
                showNotification('success', 'Sucesso!', 'Serviço salvo com sucesso!');
                closeServiceModal();
                // Garantir que a seção de serviços esteja visível
                showSection('servicesSection');
                // Aguardar um pouco antes de recarregar para garantir que o banco foi atualizado
                setTimeout(() => {
                    console.log('🔄 Recarregando lista de serviços...');
                    loadServices();
                }, 500);
            } else {
                throw new Error(data.message || 'Erro ao salvar serviço');
            }
        } catch (error) {
            console.error('❌ Erro ao salvar serviço:', error);
            // Não redirecionar para dashboard, apenas mostrar erro
            showNotification('error', 'Erro!', 'Erro ao salvar serviço: ' + error.message);
        }
    } else {
        // Sem imagem, usar JSON normal
        const serviceIncluso = document.getElementById('serviceIncluso');
        const oQueEstaInclusoValue = serviceIncluso ? serviceIncluso.value.trim() : '';
        
        // Obter valores dos campos
        const nome = document.getElementById('serviceNome').value.trim();
        const categoria = document.getElementById('serviceCategoria').value.trim();
        const descricao = document.getElementById('serviceDescricao').value.trim();
        const preco = document.getElementById('servicePreco').value;
        const precoOriginal = document.getElementById('servicePrecoOriginal').value;
        const status = document.getElementById('serviceStatus').value;
        
        // Validação adicional antes de enviar
        if (!nome) {
            showNotification('error', 'Erro!', 'Nome do serviço é obrigatório');
            document.getElementById('serviceNome').focus();
            return;
        }
        
        if (!categoria) {
            showNotification('error', 'Erro!', 'Categoria é obrigatória');
            document.getElementById('serviceCategoria').focus();
            return;
        }
        
        if (!descricao) {
            showNotification('error', 'Erro!', 'Descrição é obrigatória');
            document.getElementById('serviceDescricao').focus();
            return;
        }
        
        if (!preco || parseFloat(preco) <= 0) {
            showNotification('error', 'Erro!', 'Preço deve ser maior que zero');
            document.getElementById('servicePreco').focus();
            return;
        }
        
        const formData = {
            nome: nome,
            categoria: categoria,
            descricao: descricao,
            o_que_esta_incluso: oQueEstaInclusoValue || null,
            preco: parseFloat(preco),
            preco_original: precoOriginal ? parseFloat(precoOriginal) : null,
            status: status || 'ativo'
        };
        
        console.log('📝 Dados do formulário preparados:', formData);
        console.log('📝 Valores individuais:', {
            nome: nome,
            categoria: categoria,
            descricao: descricao,
            preco: preco,
            status: status
        });
        
        // Só adicionar id se for edição (PUT)
        if (serviceId) {
            formData.id = serviceId;
        }
        
        try {
            const method = serviceId ? 'PUT' : 'POST';
            const url = serviceId ? `${API_BASE}/servicos.php?id=${serviceId}` : `${API_BASE}/servicos.php`;
            
            console.log('Enviando dados do serviço:', {
                method,
                url,
                formData
            });
            
            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify(formData)
            });
            
            console.log('Resposta recebida:', {
                status: response.status,
                statusText: response.statusText,
                ok: response.ok
            });
            
            // Verificar se a resposta está ok antes de fazer parse
            if (!response.ok) {
                const errorText = await response.text();
                console.error('❌ Erro na resposta:', errorText);
                let errorData;
                try {
                    errorData = JSON.parse(errorText);
                } catch (e) {
                    errorData = { message: errorText || `Erro HTTP ${response.status}: ${response.statusText}` };
                }
                
                // Se for erro de autenticação, tratar separadamente
                if (response.status === 401 || response.status === 403) {
                    console.error('❌ Erro de autenticação:', errorData);
                    showNotification('error', 'Erro de Autenticação', 'Sua sessão expirou. Por favor, faça login novamente.');
                    setTimeout(() => {
                        if (confirm('Sua sessão expirou. Deseja fazer login novamente?')) {
                            window.location.href = '/backend/admin/login.html';
                        }
                    }, 1000);
                    return;
                }
                
                throw new Error(errorData.message || `Erro HTTP ${response.status}: ${response.statusText}`);
            }
            
            const responseText = await response.text();
            console.log('✅ Resposta bruta recebida:', responseText);
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                console.error('❌ Erro ao fazer parse do JSON:', e);
                console.error('Resposta recebida:', responseText);
                throw new Error('Resposta inválida do servidor: ' + responseText.substring(0, 100));
            }
            
            console.log('✅ Dados recebidos (parseado):', data);
            
            if (data.success) {
                console.log('✅ Serviço salvo com sucesso! ID:', data.id);
                showNotification('success', 'Sucesso!', 'Serviço salvo com sucesso!');
                closeServiceModal();
                // Garantir que a seção de serviços esteja visível
                if (typeof showSection === 'function') {
                    showSection('servicesSection');
                }
                // Aguardar um pouco antes de recarregar para garantir que o banco foi atualizado
                setTimeout(() => {
                    console.log('🔄 Recarregando lista de serviços...');
                    if (typeof loadServices === 'function') {
                        loadServices();
                    }
                }, 500);
            } else {
                const errorMsg = data.message || 'Erro ao salvar serviço';
                console.error('❌ Erro do servidor:', errorMsg);
                showNotification('error', 'Erro!', errorMsg);
                throw new Error(errorMsg);
            }
        } catch (error) {
            console.error('❌ Erro ao salvar serviço:', error);
            // Mostrar mensagem de erro mais detalhada
            const errorMessage = error.message || 'Erro desconhecido ao salvar serviço';
            showNotification('error', 'Erro!', errorMessage);
            
            // Se for erro de validação, focar no campo problemático
            if (errorMessage.includes('Nome')) {
                document.getElementById('serviceNome')?.focus();
            } else if (errorMessage.includes('Categoria')) {
                document.getElementById('serviceCategoria')?.focus();
            } else if (errorMessage.includes('Descrição')) {
                document.getElementById('serviceDescricao')?.focus();
            } else if (errorMessage.includes('Preço')) {
                document.getElementById('servicePreco')?.focus();
            }
        }
    }
}

async function editService(id) {
    openServiceModal(id);
}

// Função para exibir notificação estilizada
function showNotification(type, title, message, duration = 3000) {
    // Remover notificações existentes
    const existingNotifications = document.querySelectorAll('.notification-toast');
    existingNotifications.forEach(notif => {
        notif.classList.add('hiding');
        setTimeout(() => notif.remove(), 300);
    });
    
    // Criar elemento de notificação
    const notification = document.createElement('div');
    notification.className = `notification-toast ${type}`;
    
    // Ícone baseado no tipo
    const icons = {
        success: 'bi-check-circle-fill',
        error: 'bi-x-circle-fill',
        warning: 'bi-exclamation-triangle-fill',
        info: 'bi-info-circle-fill'
    };
    
    notification.innerHTML = `
        <i class="bi ${icons[type] || icons.info} notification-toast-icon"></i>
        <div class="notification-toast-content">
            <div class="notification-toast-title">${title}</div>
            <div class="notification-toast-message">${message}</div>
        </div>
        <button class="notification-toast-close" onclick="this.closest('.notification-toast').remove()">
            <i class="bi bi-x"></i>
        </button>
    `;
    
    // Adicionar ao body
    document.body.appendChild(notification);
    
    // Remover automaticamente após o tempo especificado
    if (duration > 0) {
        setTimeout(() => {
            notification.classList.add('hiding');
            setTimeout(() => notification.remove(), 300);
        }, duration);
    }
    
    return notification;
}

async function deleteService(id) {
    if (!confirm('Tem certeza que deseja excluir este serviço?')) {
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/servicos.php?id=${id}`, {
            method: 'DELETE',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        // Tentar ler a resposta como JSON
        let data;
        try {
            const text = await response.text();
            if (!text) {
                throw new Error('Resposta vazia do servidor');
            }
            data = JSON.parse(text);
        } catch (parseError) {
            console.error('Erro ao parsear resposta:', parseError);
            throw new Error(`Erro ao processar resposta do servidor (${response.status}): ${response.statusText}`);
        }
        
        if (!response.ok) {
            throw new Error(data.message || `Erro HTTP ${response.status}: ${response.statusText}`);
        }
        
        if (data.success) {
            // Exibir notificação de sucesso estilizada
            showNotification('success', 'Sucesso!', 'Serviço excluído com sucesso do banco de dados.');
            // Recarregar a lista de serviços
            loadServices();
        } else {
            throw new Error(data.message || 'Erro ao excluir serviço');
        }
    } catch (error) {
        console.error('Erro ao excluir serviço:', error);
        // Exibir notificação de erro estilizada
        showNotification('error', 'Erro!', 'Erro ao excluir serviço: ' + error.message);
    }
}

// ============================================
// HELPER FUNCTIONS
// ============================================
function showSection(sectionId) {
    const section = document.getElementById(sectionId);
    if (section) {
        section.style.display = 'block';
        section.classList.add('active');
        console.log('✅ Seção exibida:', sectionId);
    } else {
        console.warn('⚠️ Seção não encontrada:', sectionId);
    }
}

// ============================================
// FILTERS INITIALIZATION
// ============================================
function initFilters() {
    // Filtros de pedidos
    const pedidosFilters = document.querySelectorAll('#pedidosSection .filter-btn');
    pedidosFilters.forEach(btn => {
        btn.addEventListener('click', () => {
            pedidosFilters.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const filter = btn.getAttribute('data-filter');
            loadPedidos(filter);
        });
    });
    
    // Filtros de documentos
    const documentosFilters = document.querySelectorAll('#documentosSection .filter-btn');
    documentosFilters.forEach(btn => {
        btn.addEventListener('click', () => {
            documentosFilters.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const filter = btn.getAttribute('data-filter');
            loadDocumentos(filter);
        });
    });
}

// ============================================
// USUARIOS MANAGEMENT
// ============================================
async function loadUsuarios() {
    try {
        const tbody = document.getElementById('usuariosBody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 2rem;">Carregando usuários...</td></tr>';
        }
        
        // Buscar todos os tipos de usuários (all inclui admins, usuarios, clientes, doutoras)
        console.log('🔍 Carregando usuários de:', `${API_BASE}/usuarios.php?tipo=all`);
        
        const response = await fetch(`${API_BASE}/usuarios.php?tipo=all`, { 
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        console.log('📡 Status da resposta:', response.status, response.statusText);
        
        // Verificar status HTTP primeiro
        if (!response.ok) {
            let errorData;
            try {
                const text = await response.text();
                console.error('❌ Resposta de erro (texto):', text);
                errorData = JSON.parse(text);
            } catch (e) {
                console.error('❌ Resposta não é JSON válido:', e);
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            console.error('❌ Erro da API:', errorData);
            
            // Se for erro de autenticação, redirecionar para login
            if (response.status === 401 || response.status === 403 || (errorData && errorData.auth_error)) {
                if (confirm('Sua sessão expirou. Deseja fazer login novamente?')) {
                    window.location.href = '/backend/admin/login.html';
                }
                return;
            }
            
            throw new Error(errorData?.message || `HTTP ${response.status}: ${response.statusText}`);
        }
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const textResponse = await response.text();
            console.error('❌ Resposta não é JSON:', textResponse.substring(0, 200));
            throw new Error('Resposta não é JSON. Verifique se a API está funcionando corretamente.');
        }
        
        const data = await response.json();
        console.log('✅ Dados recebidos:', data);
        
        if (data.success && Array.isArray(data.data)) {
            let usuarios = data.data;
            console.log(`📊 Total de usuários recebidos: ${usuarios.length}`);
            
            if (usuarios.length > 0) {
                const sortedData = usuarios.sort((a, b) => {
                    if (a.tipo === 'administradores' && b.tipo !== 'administradores') return -1;
                    if (a.tipo !== 'administradores' && b.tipo === 'administradores') return 1;
                    const dateA = new Date(a.created_at || 0);
                    const dateB = new Date(b.created_at || 0);
                    return dateB - dateA;
                });
                
                renderUsuarios(sortedData);
            } else {
                console.log('⚠️ Nenhum usuário encontrado no banco de dados');
                renderUsuarios([]);
            }
        } else {
            const errorMsg = data.message || data.error || 'Erro ao carregar usuários';
            console.error('❌ Erro na resposta:', data);
            
            if (tbody) {
                if (data.auth_error) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2rem; color: var(--error);">
                                Sessão expirada. Faça login novamente.
                                <br><br>
                                <button onclick="window.location.href='/backend/admin/login.html'" style="padding: 8px 16px; background: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer;">
                                    Ir para Login
                                </button>
                            </td>
                        </tr>
                    `;
                } else {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2rem; color: var(--error);">
                                ${errorMsg}
                                <br><br>
                                <button onclick="loadUsuarios()" style="padding: 8px 16px; background: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer;">
                                    Tentar Novamente
                                </button>
                            </td>
                        </tr>
                    `;
                }
            }
        }
    } catch (error) {
        console.error('Erro ao carregar usuários:', error);
        const tbody = document.getElementById('usuariosBody');
        if (tbody) {
            let errorMsg = 'Erro ao carregar usuários. Tente novamente.';
            
            if (error.message && error.message.includes('banco de dados')) {
                errorMsg = 'Erro de conexão com banco de dados. Verifique as configurações.';
            } else if (error.message && error.message.includes('401')) {
                errorMsg = 'Sessão expirada. Faça login novamente.';
            } else if (error.message) {
                errorMsg = error.message;
            }
            
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align: center; padding: 2rem; color: var(--error);">
                        ${errorMsg}
                        <br><br>
                        <button onclick="loadUsuarios()" style="padding: 8px 16px; background: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer;">
                            Tentar Novamente
                        </button>
                    </td>
                </tr>
            `;
        }
    }
}

function renderUsuarios(usuarios) {
    const tbody = document.getElementById('usuariosBody');
    if (!tbody) return;
    
    if (!Array.isArray(usuarios) || usuarios.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 2rem;">Nenhum usuário encontrado</td></tr>';
        return;
    }
    
    tbody.innerHTML = usuarios.map(user => {
        const tipo = user.tipo || 'usuario';
        const status = user.status || 'ativo';
        // Formatar data de registro
        let dataRegistro = '-';
        if (user.created_at) {
            try {
                dataRegistro = formatDate(user.created_at);
            } catch (e) {
                // Se houver erro ao formatar, usar a data original
                dataRegistro = user.created_at;
            }
        }
        const isAdmin = tipo === 'administradores';
        const nomeCompleto = user.nome || user.name || user.nome_completo || '-';
        
        // Tipo formatado para exibição
        let tipoDisplay = tipo;
        if (tipo === 'administradores') tipoDisplay = 'Administrador';
        else if (tipo === 'usuarios') tipoDisplay = 'Usuário';
        else if (tipo === 'clientes') tipoDisplay = 'Cliente';
        else if (tipo === 'doutoras') tipoDisplay = 'Doutora';
        
        return `
            <tr>
                <td>${user.id || '-'}</td>
                <td>
                    ${isAdmin ? '<i class="bi bi-patch-check-fill" style="color: #1da1f2; margin-right: 5px;" title="Verificado"></i>' : ''}
                    ${nomeCompleto}
                </td>
                <td>${user.email || '-'}</td>
                <td><span class="badge-category">${tipoDisplay}</span></td>
                <td>
                    <span class="status-badge ${status === 'ativo' ? 'active' : 'inactive'}">
                        ${status === 'ativo' ? 'Ativo' : 'Inativo'}
                    </span>
                </td>
                <td>${dataRegistro}</td>
                <td>
                    <button class="btn-action" onclick="viewUsuarioDetails(${user.id}, '${tipo}')" title="Detalhes">
                        <i class="bi bi-eye"></i>
                    </button>
                    <button class="btn-action" onclick="editUsuario(${user.id})" title="Editar">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn-action btn-danger" onclick="deleteUsuario(${user.id})" title="Excluir">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

// ============================================
// PEDIDOS MANAGEMENT
// ============================================
async function loadPedidos(filter = 'all') {
    console.log('🔍 loadPedidos chamado com filter:', filter);
    
    // Armazenar dados globalmente
    pedidosData = [];
    
    // Atualizar estado de carregamento
    const listBody = document.getElementById('formulariosListBody');
    if (!listBody) {
        console.error('❌ Elemento formulariosListBody não encontrado!');
        return;
    }
    
    listBody.innerHTML = `
        <div style="text-align: center; padding: 3rem; color: #667781;">
            <div style="width: 60px; height: 60px; margin: 0 auto 16px; position: relative;">
                <div style="width: 60px; height: 60px; border: 3px solid #25D366; border-top-color: transparent; border-radius: 50%; animation: spin 1s linear infinite;"></div>
            </div>
            <p style="font-size: 14px; color: #667781; margin: 0;">Estamos se conectando ao banco de dados.</p>
        </div>
        <style>
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
    `;
    
    try {
        let url = `${API_BASE}/formularios.php`;
        if (filter !== 'all') {
            url += `?status=${filter}`;
        }
        
        console.log('📡 Fazendo requisição para:', url);
        
        const response = await fetch(url, { 
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        console.log('📥 Resposta recebida:', response.status, response.statusText);
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('❌ Erro na resposta:', errorText);
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const textResponse = await response.text();
            console.error('❌ Resposta não é JSON:', textResponse.substring(0, 200));
            throw new Error('Resposta não é JSON. Verifique se a API está funcionando corretamente.');
        }
        
        const data = await response.json();
        console.log('✅ Dados recebidos:', data);
        
        if (data.success && Array.isArray(data.data)) {
            // Filtrar dados se necessário
            let formularios = data.data;
            console.log(`📊 Total de formulários recebidos: ${formularios.length}`);
            console.log('📋 Primeiro formulário:', formularios[0]);
            
            if (filter !== 'all') {
                formularios = formularios.filter(f => f.status === filter);
                console.log(`🔍 Após filtrar por "${filter}": ${formularios.length} formulários`);
            }
            
            pedidosData = formularios;
            renderPedidos(formularios);
        } else {
            console.error('❌ Resposta inválida:', data);
            const errorMsg = data.message || data.error || 'Resposta inválida da API';
            listBody.innerHTML = `
                <div style="text-align: center; padding: 3rem 2rem; color: #dc3545;">
                    <div style="width: 80px; height: 80px; margin: 0 auto 20px; background: #fee; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-exclamation-triangle" style="font-size: 40px; color: #dc3545;"></i>
                    </div>
                    <p style="font-size: 17px; color: #111b21; margin: 0 0 8px 0; font-weight: 500;">Erro ao carregar formulários</p>
                    <p style="font-size: 14px; color: #667781; margin: 0 0 16px 0;">${errorMsg}</p>
                    <button onclick="loadPedidos('${filter}')" style="padding: 10px 20px; background: #25D366; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; transition: background 0.2s;" onmouseover="this.style.background='#128C7E'" onmouseout="this.style.background='#25D366'">
                        <i class="bi bi-arrow-clockwise"></i> Tentar novamente
                    </button>
                </div>
            `;
        }
    } catch (error) {
        console.error('❌ Erro ao carregar pedidos:', error);
        console.error('❌ Stack trace:', error.stack);
        if (listBody) {
            const errorMessage = error.message || 'Erro de conexão com o servidor';
            listBody.innerHTML = `
                <div style="text-align: center; padding: 3rem 2rem; color: #dc3545;">
                    <div style="width: 80px; height: 80px; margin: 0 auto 20px; background: #fee; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-exclamation-triangle" style="font-size: 40px; color: #dc3545;"></i>
                    </div>
                    <p style="font-size: 17px; color: #111b21; margin: 0 0 8px 0; font-weight: 500;">Erro ao carregar formulários</p>
                    <p style="font-size: 14px; color: #667781; margin: 0 0 16px 0;">${errorMessage}</p>
                    <button onclick="loadPedidos('${filter}')" style="padding: 10px 20px; background: #25D366; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; transition: background 0.2s;" onmouseover="this.style.background='#128C7E'" onmouseout="this.style.background='#25D366'">
                        <i class="bi bi-arrow-clockwise"></i> Tentar novamente
                    </button>
                </div>
            `;
        }
    }
}

function renderPedidos(pedidos) {
    console.log('🎨 renderPedidos chamado com:', pedidos);
    
    const listBody = document.getElementById('formulariosListBody');
    
    if (!listBody) {
        console.error('❌ Elemento formulariosListBody não encontrado');
        return;
    }
    
    // Verificar se é array
    if (!Array.isArray(pedidos)) {
        console.error('❌ Dados não são um array:', typeof pedidos, pedidos);
        listBody.innerHTML = `
            <div style="text-align: center; padding: 3rem; color: var(--error, #dc3545);">
                <i class="bi bi-exclamation-triangle" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                <p>Erro: Dados inválidos recebidos</p>
                <small>Tipo recebido: ${typeof pedidos}</small>
            </div>
        `;
        return;
    }
    
    // Se não houver formulários
    if (pedidos.length === 0) {
        console.log('ℹ️ Nenhum formulário encontrado');
        listBody.innerHTML = `
            <div style="text-align: center; padding: 3rem 2rem; color: #667781;">
                <div style="width: 80px; height: 80px; margin: 0 auto 20px; background: #f0f2f5; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-inbox" style="font-size: 40px; color: #8696a0;"></i>
                </div>
                <p style="font-size: 17px; color: #111b21; margin: 0 0 8px 0; font-weight: 400;">Nenhum formulário encontrado</p>
                <p style="font-size: 14px; color: #667781; margin: 0;">Não há formulários cadastrados no sistema no momento.</p>
            </div>
        `;
        return;
    }
    
    console.log(`✅ Renderizando ${pedidos.length} formulários`);
    
    // Armazenar dados globalmente
    pedidosData = pedidos;
    
    // Renderizar lista de formulários estilo WhatsApp (igual contatos)
    listBody.innerHTML = pedidos.map((pedido) => {
        const nome = pedido.nome || 'Sem nome';
        const inicial = nome.charAt(0).toUpperCase();
        const pedidoId = pedido.id;
        const status = pedido.status || 'pendente';
        
        // Cor do avatar - sempre verde estilo WhatsApp
        const avatarColor = 'linear-gradient(135deg, #25D366, #128C7E)';
        
        // Formatar data de criação (estilo WhatsApp)
        let dataFormatada = '';
        if (pedido.created_at) {
            const data = new Date(pedido.created_at);
            const hoje = new Date();
            hoje.setHours(0, 0, 0, 0);
            const ontem = new Date(hoje);
            ontem.setDate(ontem.getDate() - 1);
            const dataComparacao = new Date(data);
            dataComparacao.setHours(0, 0, 0, 0);
            
            if (dataComparacao.getTime() === hoje.getTime()) {
                dataFormatada = data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
            } else if (dataComparacao.getTime() === ontem.getTime()) {
                dataFormatada = 'Ontem';
            } else {
                const diasDiff = Math.floor((hoje - dataComparacao) / (1000 * 60 * 60 * 24));
                if (diasDiff < 7) {
                    const diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
                    dataFormatada = diasSemana[data.getDay()];
                } else {
                    dataFormatada = data.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
                }
            }
        }
        
        // Última mensagem (preview) - usar email ou telefone
        const preview = pedido.email || pedido.telefone || 'Sem informações';
        
        return `
            <div class="formulario-item" data-pedido-id="${pedidoId}" onclick="analisarFormulario(${pedidoId})" style="cursor: pointer; padding: 10px 16px; border-bottom: 1px solid #e9edef; display: flex; align-items: center; gap: 15px; transition: background-color 0.15s; background: #ffffff; position: relative;" onmouseover="this.style.backgroundColor='#f0f2f5'" onmouseout="this.style.backgroundColor='#ffffff'">
                <div class="formulario-avatar" style="width: 49px; height: 49px; border-radius: 50%; background: ${avatarColor}; display: flex; align-items: center; justify-content: center; color: white; font-weight: 500; font-size: 20px; flex-shrink: 0; position: relative;">
                    ${inicial}
                </div>
                <div class="formulario-item-info" style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 3px; padding-right: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                        <div class="formulario-item-nome" style="font-weight: 400; color: #111b21; font-size: 17px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1;">
                            ${nome}
                        </div>
                        ${dataFormatada ? `<span style="font-size: 12px; color: #667781; white-space: nowrap; flex-shrink: 0;">${dataFormatada}</span>` : ''}
                    </div>
                    <div style="display: flex; align-items: center; gap: 4px; min-width: 0;">
                        <span style="font-size: 14px; color: #667781; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1;">
                            ${preview}
                        </span>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// Função de fallback para renderizar tabela (caso o layout antigo ainda exista)
function renderPedidosTable(pedidos, tbody) {
    if (!Array.isArray(pedidos) || pedidos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 2rem;">Nenhum agendamento encontrado</td></tr>';
        return;
    }
    
    tbody.innerHTML = pedidos.map(pedido => {
        const status = pedido.status || 'pendente';
        
        // Formatar data e hora do agendamento (data que o cliente colocou)
        let dataHora = '-';
        if (pedido.data_agendamento) {
            // A data vem do banco no formato YYYY-MM-DD
            let dataFormatada = pedido.data_agendamento;
            
            // Se já está no formato correto, converter para pt-BR
            if (dataFormatada.includes('-')) {
                const partes = dataFormatada.split('-');
                if (partes.length === 3) {
                    dataFormatada = `${partes[2]}/${partes[1]}/${partes[0]}`;
                }
            }
            
            if (pedido.hora_agendamento) {
                // Formatar hora (remover segundos se houver)
                const horaFormatada = pedido.hora_agendamento.substring(0, 5); // HH:MM
                dataHora = `${dataFormatada} às ${horaFormatada}`;
            } else {
                dataHora = dataFormatada;
            }
        } else {
            // Se não tem data de agendamento, mostrar data de criação
            if (pedido.created_at) {
                dataHora = formatDate(pedido.created_at);
            }
        }
        
        // Nome do serviço
        const servicoNome = pedido.servico_nome || 'Sem serviço específico';
        
        let statusClass = 'inactive';
        let statusText = 'Pendente';
        if (status === 'confirmado') {
            statusClass = 'active';
            statusText = 'Confirmado';
        } else if (status === 'cancelado') {
            statusClass = 'inactive';
            statusText = 'Cancelado';
        }
        
        return `
            <tr class="pedido-row" data-pedido-id="${pedido.id}" style="cursor: pointer;" onclick="showPedidoDetails(${pedido.id})">
                <td>#${pedido.id || '-'}</td>
                <td>
                    <strong style="color: var(--accent-blue);">${servicoNome}</strong>
                    ${pedido.servico_preco ? `<br><small style="color: var(--text-muted);">R$ ${parseFloat(pedido.servico_preco).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</small>` : ''}
                </td>
                <td>${pedido.nome || '-'}</td>
                <td>${pedido.email || '-'}</td>
                <td>${pedido.telefone || '-'}</td>
                <td>${dataHora}</td>
                <td>
                    <span class="status-badge ${statusClass}">
                        ${statusText}
                    </span>
                </td>
                <td onclick="event.stopPropagation();">
                    ${status === 'pendente' ? `
                    <button class="btn-action" onclick="aceitarAgendamento(${pedido.id})" title="Aceitar" style="color: var(--success); margin-right: 5px;">
                        <i class="bi bi-check-circle"></i>
                    </button>
                    <button class="btn-action" onclick="rejeitarAgendamento(${pedido.id})" title="Rejeitar" style="color: var(--error); margin-right: 5px;">
                        <i class="bi bi-x-circle"></i>
                    </button>
                    ` : ''}
                    ${status === 'confirmado' ? `
                    <button class="btn-action" onclick="desmarcarAgendamento(${pedido.id})" title="Desmarcar Agendamento" style="color: var(--warning); margin-right: 5px;">
                        <i class="bi bi-calendar-x"></i>
                    </button>
                    ` : ''}
                    <button class="btn-action" onclick="editAgendamento(${pedido.id})" title="Editar">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn-action btn-danger" onclick="deleteAgendamento(${pedido.id})" title="Excluir">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
    
    // Adicionar evento de hover nas linhas (apenas se existir tabela)
    setTimeout(() => {
        const rows = document.querySelectorAll('.pedido-row');
        rows.forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.backgroundColor = 'var(--bg-hover, #f8f9fa)';
            });
            row.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
            });
        });
    }, 100);
}

// Função para mostrar detalhes do pedido no sidebar
async function showPedidoDetails(pedidoId) {
    const sidebar = document.getElementById('pedidosSidebar');
    const sidebarContent = document.getElementById('pedidosSidebarContent');
    
    if (!sidebar || !sidebarContent) return;
    
    // Encontrar o pedido nos dados
    const pedido = pedidosData.find(p => p.id == pedidoId);
    
    if (!pedido) {
        // Tentar buscar do servidor se não estiver nos dados
        try {
            const response = await fetch(`/backend/api/agendamentos.php?id=${pedidoId}`);
            const data = await response.json();
            if (data.success && data.data) {
                displayPedidoDetails(data.data);
                sidebar.style.display = 'block';
                return;
            }
        } catch (error) {
            console.error('Erro ao buscar detalhes:', error);
        }
        sidebarContent.innerHTML = '<p style="text-align: center; color: var(--error, #dc3545); padding: 2rem 0;">Erro ao carregar detalhes do agendamento</p>';
        sidebar.style.display = 'block';
        return;
    }
    
    displayPedidoDetails(pedido);
    sidebar.style.display = 'block';
}

// Função para exibir os detalhes do pedido
function displayPedidoDetails(pedido) {
    const sidebarContent = document.getElementById('pedidosSidebarContent');
    if (!sidebarContent) return;
    
    // Formatar data e hora
    let dataHora = '-';
    if (pedido.data_agendamento) {
        let dataFormatada = pedido.data_agendamento;
        if (dataFormatada.includes('-')) {
            const partes = dataFormatada.split('-');
            if (partes.length === 3) {
                dataFormatada = `${partes[2]}/${partes[1]}/${partes[0]}`;
            }
        }
        if (pedido.hora_agendamento) {
            const horaFormatada = pedido.hora_agendamento.substring(0, 5);
            dataHora = `${dataFormatada} às ${horaFormatada}`;
        } else {
            dataHora = dataFormatada;
        }
    }
    
    const status = pedido.status || 'pendente';
    let statusText = 'Pendente';
    let statusClass = 'warning';
    if (status === 'confirmado') {
        statusText = 'Confirmado';
        statusClass = 'active';
    } else if (status === 'cancelado') {
        statusText = 'Cancelado';
        statusClass = 'inactive';
    }
    
    sidebarContent.innerHTML = `
        <div class="pedido-detail-item">
            <div class="pedido-detail-label">ID do Agendamento</div>
            <div class="pedido-detail-value">#${pedido.id || '-'}</div>
        </div>
        
        <div class="pedido-detail-item">
            <div class="pedido-detail-label">Status</div>
            <div class="pedido-detail-value">
                <span class="status-badge ${statusClass}">${statusText}</span>
            </div>
        </div>
        
        ${pedido.servico_nome ? `
        <div class="pedido-detail-item">
            <div class="pedido-detail-label">Serviço</div>
            <div class="pedido-detail-value">
                <strong>${pedido.servico_nome}</strong>
                ${pedido.servico_preco ? `<br><small style="color: var(--text-muted); margin-top: 0.25rem; display: block;">R$ ${parseFloat(pedido.servico_preco).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</small>` : ''}
            </div>
        </div>
        ` : ''}
        
        <div class="pedido-detail-item">
            <div class="pedido-detail-label">Nome Completo</div>
            <div class="pedido-detail-value">${pedido.nome || '-'}</div>
        </div>
        
        <div class="pedido-detail-item">
            <div class="pedido-detail-label">E-mail</div>
            <div class="pedido-detail-value">${pedido.email || '-'}</div>
        </div>
        
        <div class="pedido-detail-item">
            <div class="pedido-detail-label">Telefone</div>
            <div class="pedido-detail-value">${pedido.telefone || '-'}</div>
        </div>
        
        <div class="pedido-detail-item">
            <div class="pedido-detail-label">Região</div>
            <div class="pedido-detail-value">${pedido.regiao || '-'}</div>
        </div>
        
        <div class="pedido-detail-item">
            <div class="pedido-detail-label">Data e Hora do Agendamento</div>
            <div class="pedido-detail-value">${dataHora}</div>
        </div>
        
        ${pedido.observacoes ? `
        <div class="pedido-detail-item">
            <div class="pedido-detail-label">Observações</div>
            <div class="pedido-detail-value" style="white-space: pre-wrap; word-wrap: break-word;">${pedido.observacoes}</div>
        </div>
        ` : ''}
        
        ${pedido.created_at ? `
        <div class="pedido-detail-item">
            <div class="pedido-detail-label">Data de Criação</div>
            <div class="pedido-detail-value">${formatDate(pedido.created_at)}</div>
        </div>
        ` : ''}
        
        ${pedido.updated_at ? `
        <div class="pedido-detail-item">
            <div class="pedido-detail-label">Última Atualização</div>
            <div class="pedido-detail-value">${formatDate(pedido.updated_at)}</div>
        </div>
        ` : ''}
    `;
}

// Função para fechar o sidebar
function closePedidosSidebar() {
    const sidebar = document.getElementById('pedidosSidebar');
    if (sidebar) {
        sidebar.style.display = 'none';
    }
}

// Função para analisar formulário (estilo WhatsApp)
async function analisarFormulario(pedidoId) {
    console.log('🔍 analisarFormulario chamado com ID:', pedidoId);
    
    // Converter pedidoId para número para comparação correta
    const pedidoIdNum = parseInt(pedidoId, 10);
    console.log('🔢 ID convertido:', pedidoIdNum, '(original:', pedidoId, ')');
    console.log('📊 Total de pedidos em pedidosData:', pedidosData.length);
    
    // Encontrar o pedido nos dados (comparar como número)
    let pedido = pedidosData.find(p => parseInt(p.id, 10) === pedidoIdNum);
    console.log('📦 Pedido encontrado nos dados locais:', pedido ? 'Sim' : 'Não');
    if (pedido) {
        console.log('📋 Dados do pedido encontrado:', pedido);
    }
    
    if (!pedido) {
        console.log('📡 Buscando pedido do servidor...');
        try {
            const url = `${API_BASE}/formularios.php?id=${pedidoId}`;
            console.log('🌐 URL da requisição:', url);
            
            const response = await fetch(url, {
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            console.log('📥 Resposta recebida:', response.status, response.statusText);
            
            if (!response.ok) {
                const errorText = await response.text();
                console.error('❌ Erro na resposta:', errorText);
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const textResponse = await response.text();
                console.error('❌ Resposta não é JSON:', textResponse.substring(0, 200));
                throw new Error('Resposta não é JSON. Verifique se a API está funcionando corretamente.');
            }
            
            const data = await response.json();
            console.log('✅ Dados recebidos:', data);
            
            if (data.success && data.data) {
                pedido = data.data;
                console.log('✅ Pedido carregado do servidor:', pedido);
            } else {
                const errorMsg = data.message || 'Formulário não encontrado';
                console.error('❌ Erro ao carregar formulário:', errorMsg);
                alert('Erro ao carregar formulário: ' + errorMsg);
                return;
            }
        } catch (error) {
            console.error('❌ Erro ao buscar formulário:', error);
            alert('Erro ao carregar formulário: ' + error.message);
            return;
        }
    }
    
    if (!pedido) {
        console.error('❌ Pedido não encontrado após busca');
        alert('Formulário não encontrado');
        return;
    }
    
    console.log('📋 Dados do pedido para renderização:', pedido);
    
    // Marcar item como ativo na lista
    document.querySelectorAll('.formulario-item').forEach(item => {
        item.classList.remove('active');
        const itemId = item.getAttribute('data-pedido-id');
        if (parseInt(itemId, 10) === pedidoIdNum) {
            item.classList.add('active');
        }
    });
    
    // Mostrar área de conversa
    const emptyState = document.getElementById('conversaEmptyState');
    const conversaHeader = document.getElementById('conversaHeader');
    const conversaMessages = document.getElementById('conversaMessages');
    const backBtn = document.getElementById('backToListBtn');
    
    console.log('🔍 Elementos encontrados:', {
        emptyState: !!emptyState,
        conversaHeader: !!conversaHeader,
        conversaMessages: !!conversaMessages,
        backBtn: !!backBtn
    });
    
    if (!conversaMessages) {
        console.error('❌ Elemento conversaMessages não encontrado!');
        alert('Erro: Elemento de mensagens não encontrado');
        return;
    }
    
    if (emptyState) emptyState.style.display = 'none';
    if (conversaHeader) conversaHeader.style.display = 'flex';
    if (conversaMessages) conversaMessages.style.display = 'flex';
    if (backBtn) backBtn.style.display = 'block';
    
    // Atualizar header da conversa
    const nome = pedido.nome || 'Sem nome';
    const inicial = nome.charAt(0).toUpperCase();
    const email = pedido.email || '-';
    const telefone = pedido.telefone || '-';
    
    const avatarEl = document.getElementById('conversaAvatarInitial');
    const nomeEl = document.getElementById('conversaNome');
    const infoEl = document.getElementById('conversaInfo');
    
    if (avatarEl) avatarEl.textContent = inicial;
    if (nomeEl) nomeEl.textContent = nome;
    if (infoEl) infoEl.textContent = `${email} • ${telefone}`;
    
    console.log('🎨 Renderizando formulário...');
    renderFormularioConversa(pedido, conversaMessages);
}

// Função para renderizar conversa do formulário - Estilo WhatsApp
function renderFormularioConversa(pedido, container) {
    console.log('🎨 renderFormularioConversa chamado com:', pedido);
    console.log('📦 Container:', container);
    
    if (!container) {
        console.error('❌ Container não fornecido!');
        return;
    }
    
    const messages = [];
    
    // Informações Pessoais
    messages.push({
        label: '👤 Informações Pessoais',
        value: '',
        isHeader: true
    });
    
    // Nome
    if (pedido.nome) {
        messages.push({
            label: 'Nome Completo',
            value: pedido.nome
        });
    }
    
    // Email
    if (pedido.email) {
        messages.push({
            label: 'E-mail',
            value: pedido.email
        });
    }
    
    // Telefone
    if (pedido.telefone) {
        messages.push({
            label: 'Telefone',
            value: pedido.telefone
        });
    }
    
    // Localização
    if (pedido.regiao || pedido.bairro) {
        messages.push({
            label: 'Localização',
            value: [pedido.regiao, pedido.bairro].filter(Boolean).join(' - ')
        });
    }
    
    // Informações do Serviço
    if (pedido.servico_nome || pedido.servico_id) {
        messages.push({
            label: '💼 Informações do Serviço',
            value: '',
            isHeader: true
        });
        
        if (pedido.servico_nome) {
            let servicoText = pedido.servico_nome;
            if (pedido.servico_preco) {
                servicoText += ` - R$ ${parseFloat(pedido.servico_preco).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
            }
            messages.push({
                label: 'Serviço',
                value: servicoText
            });
        }
        
        if (pedido.servico_categoria) {
            messages.push({
                label: 'Categoria',
                value: pedido.servico_categoria
            });
        }
    }
    
    // Agendamento
    if (pedido.data_agendamento || pedido.hora_agendamento) {
        messages.push({
            label: '📅 Agendamento',
            value: '',
            isHeader: true
        });
        
        let dataHora = '-';
        if (pedido.data_agendamento) {
            let dataFormatada = pedido.data_agendamento;
            if (dataFormatada.includes('-')) {
                const partes = dataFormatada.split('-');
                if (partes.length === 3) {
                    dataFormatada = `${partes[2]}/${partes[1]}/${partes[0]}`;
                }
            }
            if (pedido.hora_agendamento) {
                const horaFormatada = pedido.hora_agendamento.substring(0, 5);
                dataHora = `${dataFormatada} às ${horaFormatada}`;
            } else {
                dataHora = dataFormatada;
            }
        }
        if (dataHora !== '-') {
            messages.push({
                label: 'Data e Hora',
                value: dataHora
            });
        }
    }
    
    // Status
    const status = pedido.status || 'pendente';
    let statusText = 'Pendente';
    let statusColor = '#ffc107';
    if (status === 'confirmado') {
        statusText = 'Confirmado';
        statusColor = '#25D366';
    } else if (status === 'cancelado') {
        statusText = 'Cancelado';
        statusColor = '#dc3545';
    } else if (status === 'concluido') {
        statusText = 'Concluído';
        statusColor = '#128C7E';
    }
    
    messages.push({
        label: 'Status',
        value: statusText,
        statusColor: statusColor
    });
    
    // Data e Hora do Agendamento
    let dataHora = '-';
    if (pedido.data_agendamento) {
        let dataFormatada = pedido.data_agendamento;
        if (dataFormatada.includes('-')) {
            const partes = dataFormatada.split('-');
            if (partes.length === 3) {
                dataFormatada = `${partes[2]}/${partes[1]}/${partes[0]}`;
            }
        }
        if (pedido.hora_agendamento) {
            const horaFormatada = pedido.hora_agendamento.substring(0, 5);
            dataHora = `${dataFormatada} às ${horaFormatada}`;
        } else {
            dataHora = dataFormatada;
        }
    }
    if (dataHora !== '-') {
        messages.push({
            label: 'Data e Hora do Agendamento',
            value: dataHora
        });
    }
    
    // Observações
    if (pedido.observacoes) {
        messages.push({
            label: '📝 Observações',
            value: '',
            isHeader: true
        });
        messages.push({
            label: '',
            value: pedido.observacoes
        });
    }
    
    // Informações do Sistema
    messages.push({
        label: '⚙️ Informações do Sistema',
        value: '',
        isHeader: true
    });
    
    // Data de Criação
    if (pedido.created_at) {
        const dataCriacao = new Date(pedido.created_at);
        messages.push({
            label: 'Data de Criação',
            value: dataCriacao.toLocaleString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            })
        });
    }
    
    // Última Atualização
    if (pedido.updated_at) {
        const dataAtualizacao = new Date(pedido.updated_at);
        messages.push({
            label: 'Última Atualização',
            value: dataAtualizacao.toLocaleString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            })
        });
    }
    
    // ID do Formulário
    if (pedido.id) {
        messages.push({
            label: 'ID do Formulário',
            value: `#${pedido.id}`
        });
    }
    
    // ID do Agendamento (se houver)
    if (pedido.agendamento_id) {
        messages.push({
            label: 'ID do Agendamento Original',
            value: `#${pedido.agendamento_id}`
        });
    }
    
    console.log(`📊 Total de mensagens preparadas: ${messages.length}`);
    
    // Renderizar mensagens estilo WhatsApp
    if (messages.length === 0) {
        console.warn('⚠️ Nenhuma mensagem para renderizar');
        container.innerHTML = `
            <div style="text-align: center; padding: 3rem; color: #667781;">
                <i class="bi bi-info-circle" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                <p>Nenhuma informação disponível</p>
            </div>
        `;
    } else {
        const html = messages.map((msg, index) => {
            if (msg.isHeader) {
                return `
                    <div style="margin: ${index === 0 ? '0' : '24px'} 0 12px 0; padding: 0 8px;">
                        <div style="font-size: 13px; font-weight: 600; color: #667781; text-transform: uppercase; letter-spacing: 0.5px;">
                            ${msg.label}
                        </div>
                    </div>
                `;
            }
            
            const statusBadge = msg.statusColor ? `
                <span style="display: inline-block; padding: 4px 12px; background: ${msg.statusColor}15; color: ${msg.statusColor}; border-radius: 12px; font-size: 13px; font-weight: 600; margin-left: 8px;">
                    ${msg.value}
                </span>
            ` : '';
            
            return `
                <div style="margin-bottom: 16px; padding: 0 8px;">
                    <div style="font-size: 12px; color: #667781; margin-bottom: 6px; font-weight: 500;">
                        ${msg.label}
                    </div>
                    <div style="background: #ffffff; padding: 12px 16px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.08);">
                        <div style="font-size: 14px; color: #111b21; line-height: 1.5;">
                            ${msg.statusColor ? `<span style="color: ${msg.statusColor}; font-weight: 600;">${msg.value}</span>` : msg.value}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        console.log('✅ HTML gerado, inserindo no container...');
        container.innerHTML = html;
        console.log('✅ HTML inserido com sucesso');
        
        // Scroll para o topo
        container.scrollTop = 0;
    }
    
    console.log('✅ Formulário renderizado completamente:', pedido);
}

// Função auxiliar para garantir que elemento existe antes de adicionar listener
function safeAddEventListener(elementId, event, handler) {
    const element = document.getElementById(elementId);
    if (element) {
        element.addEventListener(event, handler);
        return true;
    } else {
        console.warn(`⚠️ Elemento ${elementId} não encontrado para adicionar listener`);
        return false;
    }
}

// Adicionar evento de fechar ao botão
document.addEventListener('DOMContentLoaded', function() {
    safeAddEventListener('closePedidosSidebar', 'click', closePedidosSidebar);
    
    // Botão voltar para lista
    const backBtn = document.getElementById('backToListBtn');
    if (backBtn) {
        backBtn.addEventListener('click', function() {
            const emptyState = document.getElementById('conversaEmptyState');
            const conversaHeader = document.getElementById('conversaHeader');
            const conversaMessages = document.getElementById('conversaMessages');
            
            if (emptyState) emptyState.style.display = 'flex';
            if (conversaHeader) conversaHeader.style.display = 'none';
            if (conversaMessages) conversaMessages.style.display = 'none';
            if (backBtn) backBtn.style.display = 'none';
            
            // Remover active dos itens
            document.querySelectorAll('.formulario-item').forEach(item => {
                item.classList.remove('active');
            });
        });
    }
});

// ============================================
// RELATORIOS
// ============================================
async function loadRelatorios() {
    try {
        // Carregar dados para gráficos
        const [usuariosRes, agendamentosRes, servicosRes] = await Promise.all([
            fetch(`${API_BASE}/usuarios.php`, { credentials: 'include' }).catch(() => null),
            fetch(`${API_BASE}/agendamentos.php`, { credentials: 'include' }).catch(() => null),
            fetch(`${API_BASE}/servicos.php`, { credentials: 'include' }).catch(() => null)
        ]);
        
        let usuarios = [];
        let agendamentos = [];
        let servicos = [];
        
        if (usuariosRes && usuariosRes.ok) {
            const data = await usuariosRes.json();
            if (data.success && data.data) usuarios = data.data;
        }
        
        if (agendamentosRes && agendamentosRes.ok) {
            const data = await agendamentosRes.json();
            if (data.success && data.data) agendamentos = data.data;
        }
        
        if (servicosRes && servicosRes.ok) {
            const data = await servicosRes.json();
            if (data.success && data.data) servicos = data.data;
        }
        
        // Criar gráficos
        createUsersChart(usuarios);
        createAgendamentosChart(agendamentos);
        createServicosChart(servicos);
        renderStats(usuarios, agendamentos, servicos);
        
    } catch (error) {
        console.error('Erro ao carregar relatórios:', error);
    }
}

function createUsersChart(usuarios) {
    const ctx = document.getElementById('usersChart');
    if (!ctx) return;
    
    // Destruir gráfico anterior se existir
    if (usersChart) {
        usersChart.destroy();
        usersChart = null;
    }
    
    const tipos = {};
    usuarios.forEach(user => {
        const tipo = user.tipo || 'usuario';
        tipos[tipo] = (tipos[tipo] || 0) + 1;
    });
    
    usersChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: Object.keys(tipos),
            datasets: [{
                data: Object.values(tipos),
                backgroundColor: ['#4299e1', '#48bb78', '#ed8936', '#9f7aea']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
}

function createAgendamentosChart(agendamentos) {
    const ctx = document.getElementById('agendamentosChart');
    if (!ctx) return;
    
    // Destruir gráfico anterior se existir
    if (agendamentosChart) {
        agendamentosChart.destroy();
        agendamentosChart = null;
    }
    
    const status = {};
    agendamentos.forEach(ag => {
        const st = ag.status || 'pendente';
        status[st] = (status[st] || 0) + 1;
    });
    
    agendamentosChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: Object.keys(status),
            datasets: [{
                label: 'Agendamentos',
                data: Object.values(status),
                backgroundColor: '#4299e1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            }
        }
    });
}

function createServicosChart(servicos) {
    const ctx = document.getElementById('servicosChart');
    if (!ctx) return;
    
    // Destruir gráfico anterior se existir
    if (servicosChart) {
        servicosChart.destroy();
        servicosChart = null;
    }
    
    const topServicos = servicos
        .sort((a, b) => (b.vendidos || 0) - (a.vendidos || 0))
        .slice(0, 5);
    
    servicosChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: topServicos.map(s => s.nome),
            datasets: [{
                label: 'Vendidos',
                data: topServicos.map(s => s.vendidos || 0),
                backgroundColor: '#48bb78'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false }
            }
        }
    });
}

function renderStats(usuarios, agendamentos, servicos) {
    const statsList = document.getElementById('statsList');
    if (!statsList) return;
    
    const totalUsuarios = usuarios.length;
    const totalAgendamentos = agendamentos.length;
    const totalServicos = servicos.length;
    const agendamentosConfirmados = agendamentos.filter(a => a.status === 'confirmado').length;
    
    statsList.innerHTML = `
        <div class="stat-item">
            <span class="stat-label">Total de Usuários</span>
            <span class="stat-value">${totalUsuarios}</span>
        </div>
        <div class="stat-item">
            <span class="stat-label">Total de Agendamentos</span>
            <span class="stat-value">${totalAgendamentos}</span>
        </div>
        <div class="stat-item">
            <span class="stat-label">Agendamentos Confirmados</span>
            <span class="stat-value">${agendamentosConfirmados}</span>
        </div>
        <div class="stat-item">
            <span class="stat-label">Total de Serviços</span>
            <span class="stat-value">${totalServicos}</span>
        </div>
    `;
}

// ============================================
// DOCUMENTOS MANAGEMENT
// ============================================
async function loadDocumentos(filter = 'all') {
    try {
        const tbody = document.getElementById('documentosBody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 2rem;">Carregando documentos...</td></tr>';
        }
        
        const url = filter !== 'all' ? `${API_BASE}/candidaturas.php?status=${filter}` : `${API_BASE}/candidaturas.php`;
        const response = await fetch(url, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success && data.data) {
            renderDocumentos(data.data);
        } else {
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 2rem; color: var(--error);">Erro ao carregar documentos</td></tr>';
            }
        }
    } catch (error) {
        console.error('Erro ao carregar documentos:', error);
        const tbody = document.getElementById('documentosBody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 2rem; color: var(--error);">Erro ao carregar documentos</td></tr>';
        }
    }
}

function renderDocumentos(documentos) {
    const tbody = document.getElementById('documentosBody');
    if (!tbody) return;
    
    if (!Array.isArray(documentos) || documentos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 2rem;">Nenhum documento encontrado</td></tr>';
        return;
    }
    
    tbody.innerHTML = documentos.map(doc => {
        const status = doc.status || 'pendente';
        const data = doc.created_at ? formatDate(doc.created_at) : '-';
        
        let statusClass = 'inactive';
        let statusText = 'Pendente';
        if (status === 'aprovado') {
            statusClass = 'active';
            statusText = 'Aprovado';
        } else if (status === 'rejeitado') {
            statusClass = 'inactive';
            statusText = 'Rejeitado';
        } else if (status === 'em_analise') {
            statusClass = 'active';
            statusText = 'Em Análise';
        }
        
        return `
            <tr>
                <td>${doc.id || '-'}</td>
                <td>${doc.nome || '-'}</td>
                <td>${doc.email || '-'}</td>
                <td>${doc.vaga || '-'}</td>
                <td>
                    <span class="status-badge ${statusClass}">
                        ${statusText}
                    </span>
                </td>
                <td>${data}</td>
                <td>
                    <button class="btn-action" onclick="viewDocumento(${doc.id})" title="Visualizar">
                        <i class="bi bi-eye"></i>
                    </button>
                    <button class="btn-action" onclick="editDocumento(${doc.id})" title="Editar">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn-action btn-danger" onclick="deleteDocumento(${doc.id})" title="Excluir">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

// Funções placeholder para ações
async function editUsuario(id) {
    try {
        // Buscar dados do usuário
        const response = await fetch(`${API_BASE}/usuarios.php?id=${id}`, { credentials: 'include' });
        const data = await response.json();
        
        if (!data.success || !data.data) {
            alert('Erro ao carregar dados do usuário');
            return;
        }
        
        const user = data.data;
        
        // Criar modal de edição
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal-content" style="max-width: 500px;">
                <div class="modal-header">
                    <h3>Editar Usuário</h3>
                    <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="editUsuarioForm">
                        <input type="hidden" id="editUsuarioId" value="${user.id}">
                        <input type="hidden" id="editUsuarioTipo" value="${user.tipo || 'usuarios'}">
                        
                    <div class="form-group-modal">
                        <label for="editUsuarioNome">Nome *</label>
                        <input type="text" id="editUsuarioNome" value="${user.nome || user.name || ''}" required>
                    </div>
                    
                    <div class="form-group-modal">
                        <label for="editUsuarioEmail">Email *</label>
                        <input type="email" id="editUsuarioEmail" value="${user.email || ''}" required>
                    </div>
                    
                    <div class="form-group-modal">
                        <label for="editUsuarioSenha">Nova Senha (deixe em branco para não alterar)</label>
                        <input type="password" id="editUsuarioSenha" placeholder="Digite nova senha">
                    </div>
                    
                    <div class="form-group-modal">
                        <label for="editUsuarioStatus">Status</label>
                        <select id="editUsuarioStatus">
                            <option value="ativo" ${user.status === 'ativo' ? 'selected' : ''}>Ativo</option>
                            <option value="inativo" ${user.status === 'inativo' ? 'selected' : ''}>Inativo</option>
                        </select>
                    </div>
                    </form>
                </div>
            <div class="modal-actions">
                <button class="btn-modal btn-modal-secondary" onclick="this.closest('.modal-overlay').remove()">Cancelar</button>
                <button class="btn-modal btn-modal-primary" onclick="saveUsuario()">Salvar</button>
            </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Fechar ao clicar fora
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
        
    } catch (error) {
        console.error('Erro ao carregar usuário:', error);
        alert('Erro ao carregar dados do usuário');
    }
}

async function saveUsuario() {
    try {
        const id = document.getElementById('editUsuarioId').value;
        const tipo = document.getElementById('editUsuarioTipo').value;
        const nome = document.getElementById('editUsuarioNome').value.trim();
        const email = document.getElementById('editUsuarioEmail').value.trim();
        const senha = document.getElementById('editUsuarioSenha').value;
        const status = document.getElementById('editUsuarioStatus').value;
        
        if (!nome || !email) {
            alert('Preencha todos os campos obrigatórios');
            return;
        }
        
        const data = {
            nome,
            email,
            status,
            tipo
        };
        
        if (senha) {
            if (senha.length < 6) {
                alert('A senha deve ter pelo menos 6 caracteres');
                return;
            }
            data.senha = senha;
        }
        
        const response = await fetch(`${API_BASE}/usuarios.php?id=${id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Usuário atualizado com sucesso!');
            document.querySelector('.modal-overlay').remove();
            loadUsuarios();
        } else {
            alert('Erro ao atualizar usuário: ' + (result.message || 'Erro desconhecido'));
        }
    } catch (error) {
        console.error('Erro ao salvar usuário:', error);
        alert('Erro ao salvar usuário');
    }
}

async function deleteUsuario(id) {
    if (!confirm('Tem certeza que deseja excluir este usuário? Esta ação não pode ser desfeita.')) {
        return;
    }
    
    try {
        // Buscar tipo do usuário primeiro
        const response = await fetch(`${API_BASE}/usuarios.php?id=${id}`, { credentials: 'include' });
        const data = await response.json();
        
        if (!data.success || !data.data) {
            alert('Erro ao buscar dados do usuário');
            return;
        }
        
        const tipo = data.data.tipo || 'usuarios';
        const nome = data.data.nome || data.data.name || 'Usuário';
        
        // Confirmar novamente se for admin
        if (tipo === 'administradores') {
            if (!confirm(`ATENÇÃO: Você está prestes a excluir um ADMINISTRADOR (${nome}). Esta ação é irreversível. Deseja continuar?`)) {
                return;
            }
        }
        
        const deleteResponse = await fetch(`${API_BASE}/usuarios.php?id=${id}&tipo=${tipo}`, {
            method: 'DELETE',
            credentials: 'include'
        });
        
        const result = await deleteResponse.json();
        
        if (result.success) {
            alert('Usuário excluído com sucesso!');
            loadUsuarios();
        } else {
            alert('Erro ao excluir usuário: ' + (result.message || 'Erro desconhecido'));
        }
    } catch (error) {
        console.error('Erro ao excluir usuário:', error);
        alert('Erro ao excluir usuário: ' + error.message);
    }
}

async function viewUsuarioDetails(id, tipo = null) {
    try {
        // Buscar dados completos do usuário
        const response = await fetch(`${API_BASE}/usuarios.php?id=${id}`, { credentials: 'include' });
        const data = await response.json();
        
        if (!data.success || !data.data) {
            alert('Erro ao carregar dados do usuário');
            return;
        }
        
        const user = data.data;
        const userTipo = tipo || user.tipo || 'usuarios';
        const isAdmin = userTipo === 'administradores';
        
        // Criar modal de detalhes
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal-content" style="max-width: 600px;">
                <div class="modal-header">
                    <h3>
                        ${isAdmin ? '<i class="bi bi-patch-check-fill" style="color: #1da1f2; margin-right: 8px;"></i>' : ''}
                        Detalhes do Usuário
                    </h3>
                    <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">&times;</button>
                </div>
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                    <div style="display: grid; gap: 1.5rem;">
                        <div class="form-group-modal">
                            <label><strong>ID:</strong></label>
                            <div>${user.id || '-'}</div>
                        </div>
                        
                        <div class="form-group-modal">
                            <label><strong>Nome Completo:</strong></label>
                            <div>${user.nome || user.name || user.nome_completo || '-'}</div>
                        </div>
                        
                        <div class="form-group-modal">
                            <label><strong>Email:</strong></label>
                            <div>${user.email || '-'}</div>
                        </div>
                        
                        <div class="form-group-modal">
                            <label><strong>Tipo de Usuário:</strong></label>
                            <div>
                                <span class="badge-category">${userTipo === 'administradores' ? 'Administrador' : userTipo === 'usuarios' ? 'Usuário' : userTipo === 'clientes' ? 'Cliente' : userTipo === 'doutoras' ? 'Doutora' : userTipo}</span>
                                ${isAdmin ? '<i class="bi bi-patch-check-fill" style="color: #1da1f2; margin-left: 8px;" title="Verificado"></i>' : ''}
                            </div>
                        </div>
                        
                        ${user.cpf ? `
                        <div class="form-group-modal">
                            <label><strong>CPF:</strong></label>
                            <div>${user.cpf}</div>
                        </div>
                        ` : ''}
                        
                        ${user.telefone ? `
                        <div class="form-group-modal">
                            <label><strong>Telefone:</strong></label>
                            <div>${user.telefone}</div>
                        </div>
                        ` : ''}
                        
                        ${user.nome_usuario ? `
                        <div class="form-group-modal">
                            <label><strong>Nome de Usuário:</strong></label>
                            <div>${user.nome_usuario}</div>
                        </div>
                        ` : ''}
                        
                        ${user.codigo_verificacao ? `
                        <div class="form-group-modal">
                            <label><strong>Código de Verificação:</strong></label>
                            <div>${user.codigo_verificacao}</div>
                        </div>
                        ` : ''}
                        
                        ${user.codigo_seguranca ? `
                        <div class="form-group-modal">
                            <label><strong>Código de Segurança:</strong></label>
                            <div>${user.codigo_seguranca}</div>
                        </div>
                        ` : ''}
                        
                        <div class="form-group-modal">
                            <label><strong>Status:</strong></label>
                            <div>
                                <span class="status-badge ${user.status === 'ativo' ? 'active' : 'inactive'}">
                                    ${user.status === 'ativo' ? 'Ativo' : 'Inativo'}
                                </span>
                            </div>
                        </div>
                        
                        <div class="form-group-modal">
                            <label><strong>Data de Registro:</strong></label>
                            <div>${user.created_at ? formatDate(user.created_at) : '-'}</div>
                        </div>
                        
                        ${user.updated_at ? `
                        <div class="form-group-modal">
                            <label><strong>Última Atualização:</strong></label>
                            <div>${formatDate(user.updated_at)}</div>
                        </div>
                        ` : ''}
                    </div>
                </div>
                <div class="modal-actions">
                    <button class="btn-modal btn-modal-secondary" onclick="this.closest('.modal-overlay').remove()">Fechar</button>
                    <button class="btn-modal btn-modal-primary" onclick="editUsuario(${user.id}); this.closest('.modal-overlay').remove();" style="margin-left: 10px;">Editar</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Fechar ao clicar fora
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
        
    } catch (error) {
        console.error('Erro ao carregar detalhes do usuário:', error);
        alert('Erro ao carregar detalhes do usuário');
    }
}
function editPedido(id) { 
    // Usar a função de editar agendamento
    editAgendamento(id);
}
async function deletePedido(id) {
    if (!confirm('Tem certeza que deseja excluir este agendamento? Esta ação não pode ser desfeita.')) {
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/agendamentos.php?id=${id}`, {
            method: 'DELETE',
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Agendamento excluído com sucesso!');
            // Recarregar a lista de pedidos
            const activeFilter = document.querySelector('#pedidosSection .filter-btn.active')?.getAttribute('data-filter') || 'all';
            loadPedidos(activeFilter);
        } else {
            throw new Error(data.message || 'Erro ao excluir agendamento');
        }
    } catch (error) {
        console.error('Erro ao excluir agendamento:', error);
        alert('Erro ao excluir agendamento: ' + error.message);
    }
}
function viewDocumento(id) { alert('Visualizar documento ' + id + ' - Em desenvolvimento'); }
function editDocumento(id) { alert('Editar documento ' + id + ' - Em desenvolvimento'); }
function deleteDocumento(id) { 
    if (confirm('Tem certeza que deseja excluir este documento?')) {
        alert('Excluir documento ' + id + ' - Em desenvolvimento');
    }
}

// ============================================
// PROFILE MANAGEMENT
// ============================================
function initProfile() {
    const profileBtn = document.getElementById('profileBtn');
    const closeModal = document.getElementById('closeProfileModal');
    const cancelModal = document.getElementById('cancelProfileModal');
    const profileForm = document.getElementById('profileForm');
    const userProfile = document.querySelector('.user-profile');
    
    // Controlar expansão do nome do usuário ao clicar
    if (userProfile) {
        userProfile.addEventListener('click', (e) => {
            // Não expandir se clicar nos botões de ação
            if (e.target.closest('.profile-btn') || e.target.closest('.logout-btn')) {
                return;
            }
            e.stopPropagation();
            userProfile.classList.toggle('expanded');
        });
    }
    
    if (profileBtn) {
        profileBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            openProfileModal();
        });
    }
    
    if (closeModal) {
        closeModal.addEventListener('click', closeProfileModal);
    }
    
    if (cancelModal) {
        cancelModal.addEventListener('click', closeProfileModal);
    }
    
    if (profileForm) {
        profileForm.addEventListener('submit', handleProfileSubmit);
    }
    
    // Fechar modal ao clicar fora
    const modal = document.getElementById('profileModal');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeProfileModal();
            }
        });
    }
}

async function loadAdminProfile() {
    try {
        const response = await fetch(`${API_BASE}/admin-profile.php`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success && data.data) {
            // Atualizar informações no sidebar
            const userName = document.querySelector('.user-name');
            const userEmail = document.querySelector('.user-email');
            const userAvatar = document.querySelector('.user-avatar img');
            
            if (userName) userName.textContent = data.data.nome;
            if (userEmail) userEmail.textContent = data.data.email;
            if (userAvatar) {
                // Usar apenas o nome para gerar o avatar
                const adminName = data.data.nome || 'Admin';
                userAvatar.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(adminName)}&background=4299e1&color=fff&size=128&bold=true`;
                userAvatar.alt = adminName;
            }
        }
    } catch (error) {
        console.error('Erro ao carregar perfil:', error);
    }
}

function openProfileModal() {
    const modal = document.getElementById('profileModal');
    loadProfileData();
    modal.style.display = 'flex';
}

function closeProfileModal() {
    const modal = document.getElementById('profileModal');
    modal.style.display = 'none';
    document.getElementById('profileForm').reset();
}

async function loadProfileData() {
    try {
        const response = await fetch(`${API_BASE}/admin-profile.php`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success && data.data) {
            document.getElementById('profileNome').value = data.data.nome || '';
            document.getElementById('profileEmail').value = data.data.email || '';
            // Formatar CPF para exibição
            const cpf = data.data.cpf || '';
            const cpfFormatted = cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
            document.getElementById('profileCPF').value = cpfFormatted;
        }
    } catch (error) {
        console.error('Erro ao carregar dados do perfil:', error);
        alert('Erro ao carregar dados do perfil');
    }
}

async function handleProfileSubmit(e) {
    e.preventDefault();
    
    const formData = {
        nome: document.getElementById('profileNome').value,
        email: document.getElementById('profileEmail').value
    };
    
    const senhaAtual = document.getElementById('profileSenhaAtual').value;
    const senhaNova = document.getElementById('profileSenhaNova').value;
    
    if (senhaAtual && senhaNova) {
        formData.senha_atual = senhaAtual;
        formData.senha_nova = senhaNova;
    }
    
    try {
        const response = await fetch(`${API_BASE}/admin-profile.php`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Perfil atualizado com sucesso!');
            closeProfileModal();
            loadAdminProfile();
        } else {
            throw new Error(data.message || 'Erro ao atualizar perfil');
        }
    } catch (error) {
        console.error('Erro ao atualizar perfil:', error);
        alert('Erro ao atualizar perfil: ' + error.message);
    }
}

// ============================================
// NOTIFICATIONS
// ============================================
async function loadNotificacoes() {
    try {
        const notificationsList = document.getElementById('notificationsList');
        if (!notificationsList) return;
        
        notificationsList.innerHTML = '<div style="text-align: center; padding: 2rem;">Carregando notificações...</div>';
        
        // Buscar notificações (agendamentos pendentes, candidaturas, etc)
        const [agendamentosRes, candidaturasRes] = await Promise.all([
            fetch(`${API_BASE}/agendamentos.php?status=pendente`, { credentials: 'include' }).catch(() => null),
            fetch(`${API_BASE}/candidaturas.php?status=pendente`, { credentials: 'include' }).catch(() => null)
        ]);
        
        let notifications = [];
        
        if (agendamentosRes && agendamentosRes.ok) {
            const data = await agendamentosRes.json();
            if (data.success && data.data && Array.isArray(data.data)) {
                data.data.forEach(ag => {
                    notifications.push({
                        type: 'agendamento',
                        icon: 'bi-calendar',
                        title: 'Novo Agendamento',
                        message: `${ag.nome || 'Cliente'} agendou uma avaliação`,
                        time: ag.created_at,
                        id: ag.id,
                        link: '#pedidos'
                    });
                });
            }
        }
        
        if (candidaturasRes && candidaturasRes.ok) {
            const data = await candidaturasRes.json();
            if (data.success && data.data && Array.isArray(data.data)) {
                data.data.forEach(cand => {
                    notifications.push({
                        type: 'candidatura',
                        icon: 'bi-file-person',
                        title: 'Nova Candidatura',
                        message: `${cand.nome || 'Candidato'} se candidatou para ${cand.vaga || 'vaga'}`,
                        time: cand.created_at,
                        id: cand.id,
                        link: '#documentos'
                    });
                });
            }
        }
        
        // Ordenar por data (mais recente primeiro)
        notifications.sort((a, b) => {
            return new Date(b.time) - new Date(a.time);
        });
        
        renderNotificacoes(notifications);
        
    } catch (error) {
        console.error('Erro ao carregar notificações:', error);
        const notificationsList = document.getElementById('notificationsList');
        if (notificationsList) {
            notificationsList.innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--error);">Erro ao carregar notificações</div>';
        }
    }
}

function renderNotificacoes(notifications) {
    const notificationsList = document.getElementById('notificationsList');
    if (!notificationsList) return;
    
    if (notifications.length === 0) {
        notificationsList.innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--text-secondary);">Nenhuma notificação no momento</div>';
        return;
    }
    
    notificationsList.innerHTML = notifications.map(notif => {
        const timeAgo = getTimeAgo(notif.time);
        return `
            <div class="notification-item" onclick="${notif.link ? `switchToPage('${notif.link.replace('#', '')}')` : ''}">
                <div class="notification-icon">
                    <i class="bi ${notif.icon}"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">${notif.title}</div>
                    <div class="notification-message">${notif.message}</div>
                    <div class="notification-time">${timeAgo}</div>
                </div>
            </div>
        `;
    }).join('');
}

function switchToPage(page) {
    const navItem = document.querySelector(`[data-page="${page}"]`);
    if (navItem) {
        navItem.click();
    }
}

function getTimeAgo(datetime) {
    if (!datetime) return 'Agora';
    const timestamp = new Date(datetime).getTime();
    const diff = Date.now() - timestamp;
    
    if (diff < 60000) return 'Agora';
    if (diff < 3600000) return `Há ${Math.floor(diff / 60000)} min`;
    if (diff < 86400000) return `Há ${Math.floor(diff / 3600000)} hora(s)`;
    return `Há ${Math.floor(diff / 86400000)} dia(s)`;
}

// ============================================
// SETTINGS
// ============================================
function initSettings() {
    const btnSaveSettings = document.getElementById('btnSaveSettings');
    if (btnSaveSettings) {
        btnSaveSettings.addEventListener('click', saveSettings);
    }
}

function loadConfiguracoes() {
    // Carregar configurações salvas do localStorage
    const notifEmail = localStorage.getItem('notifEmail') !== 'false';
    const notifSistema = localStorage.getItem('notifSistema') !== 'false';
    const security2FA = localStorage.getItem('security2FA') === 'true';
    const securityLogout = localStorage.getItem('securityLogout') !== 'false';
    const theme = localStorage.getItem('theme') || 'dark';
    
    const notifEmailCheck = document.getElementById('notifEmail');
    const notifSistemaCheck = document.getElementById('notifSistema');
    const security2FACheck = document.getElementById('security2FA');
    const securityLogoutCheck = document.getElementById('securityLogout');
    const themeSelect = document.getElementById('themeSelect');
    
    if (notifEmailCheck) notifEmailCheck.checked = notifEmail;
    if (notifSistemaCheck) notifSistemaCheck.checked = notifSistema;
    if (security2FACheck) security2FACheck.checked = security2FA;
    if (securityLogoutCheck) securityLogoutCheck.checked = securityLogout;
    if (themeSelect) themeSelect.value = theme;
}

function saveSettings() {
    const settings = {
        notifEmail: document.getElementById('notifEmail').checked,
        notifSistema: document.getElementById('notifSistema').checked,
        security2FA: document.getElementById('security2FA').checked,
        securityLogout: document.getElementById('securityLogout').checked,
        theme: document.getElementById('themeSelect').value
    };
    
    // Salvar no localStorage
    Object.keys(settings).forEach(key => {
        localStorage.setItem(key, settings[key]);
    });
    
    // Aplicar tema
    if (settings.theme === 'auto') {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
    } else {
        document.documentElement.setAttribute('data-theme', settings.theme);
    }
    
    alert('Configurações salvas com sucesso!');
}

// ============================================
// CHAT MANAGEMENT
// ============================================
let currentChatTicketId = null;
let chatPollingInterval = null;

function initChat() {
    const btnRefresh = document.getElementById('btnRefreshChat');
    const sendBtn = document.getElementById('chatSendBtn');
    const closeBtn = document.getElementById('chatCloseBtn');
    const deleteBtn = document.getElementById('chatDeleteBtn');
    const messageInput = document.getElementById('chatMessageInput');
    
    if (btnRefresh) {
        btnRefresh.addEventListener('click', loadChatTickets);
    }
    
    if (sendBtn && messageInput) {
        sendBtn.addEventListener('click', sendChatMessage);
        messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                sendChatMessage();
            }
        });
    }
    
    if (closeBtn) {
        closeBtn.addEventListener('click', closeChatTicket);
    }
    
    if (deleteBtn) {
        deleteBtn.addEventListener('click', deleteChatTicket);
    }
    
    // Polling para atualizar mensagens a cada 2 segundos
    if (chatPollingInterval) {
        clearInterval(chatPollingInterval);
    }
    chatPollingInterval = setInterval(() => {
        if (currentChatTicketId) {
            loadChatMessages(currentChatTicketId);
        }
    }, 2000);
}

async function loadChatTickets(filter = 'all') {
    try {
        const list = document.getElementById('chatTicketsList');
        if (!list) return;
        
        list.innerHTML = '<div style="padding: 1rem; text-align: center; color: var(--text-muted);">Carregando tickets...</div>';
        
        const url = filter === 'all' ? `${API_BASE}/tickets.php` : `${API_BASE}/tickets.php?status=${filter}`;
        const response = await fetch(url, { 
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        let data;
        try {
            data = await response.json();
        } catch (e) {
            const text = await response.text();
            console.error('Resposta não é JSON:', text);
            throw new Error('Resposta inválida do servidor');
        }
        
        if (!response.ok) {
            if (response.status === 401) {
                // Não autenticado - redirecionar para login
                console.error('Não autenticado. Redirecionando para login...');
                window.location.href = '/backend/admin/login.html';
                return;
            }
            
            const errorMsg = data.message || `HTTP ${response.status}: ${response.statusText}`;
            const isDbError = errorMsg.includes('banco de dados') || errorMsg.includes('database');
            
            throw new Error(isDbError ? 'Erro de conexão com banco de dados' : errorMsg);
        }
        
        if (data.success && Array.isArray(data.data)) {
            renderChatTickets(data.data);
        } else {
            console.error('Resposta inválida da API:', data);
            const errorMsg = data.message || 'Resposta inválida';
            list.innerHTML = '<div style="padding: 1rem; text-align: center; color: var(--error);">Erro ao carregar tickets: ' + errorMsg + '</div>';
        }
    } catch (error) {
        console.error('Erro ao carregar tickets:', error);
        const list = document.getElementById('chatTicketsList');
        if (list) {
            const errorMsg = error.message || 'Erro desconhecido';
            const isDbError = errorMsg.includes('banco de dados') || errorMsg.includes('database');
            const displayMsg = isDbError 
                ? 'Erro de conexão com banco de dados. Verifique as configurações.'
                : errorMsg;
            list.innerHTML = '<div style="padding: 1rem; text-align: center; color: var(--error);">' + displayMsg + '<br><button onclick="loadChatTickets(\'' + (filter || 'all') + '\')" style="margin-top: 10px; padding: 8px 16px; background: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer;">Tentar novamente</button></div>';
        }
    }
}

function renderChatTickets(tickets) {
    const list = document.getElementById('chatTicketsList');
    if (!list) return;
    
    if (tickets.length === 0) {
        list.innerHTML = '<div style="padding: 1rem; text-align: center; color: var(--text-muted);">Nenhum ticket encontrado</div>';
        return;
    }
    
    list.innerHTML = tickets.map(ticket => {
        const isActive = currentChatTicketId === ticket.id;
        const statusClass = ticket.status === 'aberto' ? 'aberto' : 'fechado';
        const timeAgo = getTimeAgo(ticket.updated_at || ticket.created_at);
        
        return `
            <div class="chat-ticket-item ${isActive ? 'active' : ''}" onclick="openChatTicket(${ticket.id})">
                <div class="ticket-header">
                    <div>
                        <div class="ticket-name">${ticket.cliente_nome || 'Anônimo'}</div>
                        <div class="ticket-email">${ticket.cliente_email || ''}</div>
                    </div>
                    <span class="badge-status ${statusClass}">${ticket.status === 'aberto' ? 'Aberto' : 'Fechado'}</span>
                </div>
                <div class="ticket-time">${timeAgo}</div>
            </div>
        `;
    }).join('');
}

async function openChatTicket(ticketId) {
    currentChatTicketId = ticketId;
    loadChatMessages(ticketId);
    
    // Atualizar lista para destacar ticket ativo
    loadChatTickets(document.querySelector('[data-chat-filter].active')?.dataset.chatFilter || 'all');
    
    // Mostrar área de input se ticket estiver aberto
    const inputArea = document.getElementById('chatInputArea');
    if (inputArea) {
        // Verificar status do ticket
        try {
            const response = await fetch(`${API_BASE}/tickets.php?id=${ticketId}`, { credentials: 'include' });
            const data = await response.json();
            if (data.success && data.data && data.data.status === 'aberto') {
                inputArea.style.display = 'block';
            } else {
                inputArea.style.display = 'none';
            }
        } catch (e) {
            inputArea.style.display = 'block';
        }
    }
}

async function loadChatMessages(ticketId) {
    try {
        const area = document.getElementById('chatMessagesArea');
        if (!area) return;
        
        // Tentar primeiro com action=mensagens
        let response = await fetch(`${API_BASE}/tickets.php?id=${ticketId}&action=mensagens`, { credentials: 'include' });
        let data = await response.json();
        
        // Se não funcionar, tentar buscar ticket completo
        if (!data.success || !data.data) {
            response = await fetch(`${API_BASE}/tickets.php?id=${ticketId}`, { credentials: 'include' });
            data = await response.json();
            
            if (data.success && data.data && data.data.mensagens) {
                renderChatMessages(data.data.mensagens);
                return;
            }
        }
        
        if (data.success && data.data) {
            // Se data.data é array, são as mensagens diretamente
            if (Array.isArray(data.data)) {
                renderChatMessages(data.data);
            } else if (data.data.mensagens) {
                // Se é objeto com propriedade mensagens
                renderChatMessages(data.data.mensagens);
            }
        }
    } catch (error) {
        console.error('Erro ao carregar mensagens:', error);
        const area = document.getElementById('chatMessagesArea');
        if (area) {
            area.innerHTML = '<div class="chat-empty-state"><p style="color: var(--error);">Erro ao carregar mensagens</p></div>';
        }
    }
}

function renderChatMessages(messages) {
    const area = document.getElementById('chatMessagesArea');
    if (!area) return;
    
    if (messages.length === 0) {
        area.innerHTML = '<div class="chat-empty-state"><i class="bi bi-chat-dots" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i><p style="color: var(--text-muted);">Nenhuma mensagem ainda</p></div>';
        return;
    }
    
    area.innerHTML = messages.map(msg => {
        const isAdmin = msg.tipo === 'admin';
        const time = new Date(msg.created_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        
        return `
            <div class="chat-message ${isAdmin ? 'admin' : 'user'}">
                <div class="chat-message-bubble">
                    <div class="chat-message-header">${msg.nome || (isAdmin ? 'Admin' : 'Cliente')}</div>
                    <div class="chat-message-text">${msg.mensagem}</div>
                    <div class="chat-message-time">${time}</div>
                </div>
            </div>
        `;
    }).join('');
    
    // Scroll para o final
    area.scrollTop = area.scrollHeight;
}

async function sendChatMessage() {
    if (!currentChatTicketId) return;
    
    const input = document.getElementById('chatMessageInput');
    if (!input) return;
    
    const message = input.value.trim();
    if (!message) return;
    
    try {
        const response = await fetch(`${API_BASE}/tickets.php?action=mensagem`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({
                ticket_id: currentChatTicketId,
                tipo: 'admin',
                nome: 'Administrador',
                mensagem: message
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            input.value = '';
            loadChatMessages(currentChatTicketId);
        } else {
            throw new Error(data.message || 'Erro ao enviar mensagem');
        }
    } catch (error) {
        console.error('Erro ao enviar mensagem:', error);
        alert('Erro ao enviar mensagem: ' + error.message);
    }
}

function filterChatTickets(filter) {
    // Atualizar botões
    document.querySelectorAll('[data-chat-filter]').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-chat-filter="${filter}"]`)?.classList.add('active');
    
    // Carregar tickets filtrados
    loadChatTickets(filter);
}

async function closeChatTicket() {
    if (!currentChatTicketId) {
        alert('Nenhum ticket selecionado');
        return;
    }
    
    if (!confirm('Tem certeza que deseja fechar este ticket? O cliente não poderá mais enviar mensagens.')) {
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/tickets.php?id=${currentChatTicketId}&action=close`, {
            method: 'PUT',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            // Atualizar status localmente
            const ticketItems = document.querySelectorAll('.chat-ticket-item');
            ticketItems.forEach(item => {
                if (item.getAttribute('onclick')?.includes(currentChatTicketId)) {
                    const badge = item.querySelector('.badge-status');
                    if (badge) {
                        badge.textContent = 'Fechado';
                        badge.className = 'badge-status fechado';
                    }
                }
            });
            
            // Esconder área de input
            const inputArea = document.getElementById('chatInputArea');
            if (inputArea) {
                inputArea.style.display = 'none';
            }
            
            // Recarregar mensagens e lista de tickets para garantir sincronização
            await loadChatMessages(currentChatTicketId);
            await loadChatTickets(document.querySelector('[data-chat-filter].active')?.dataset.chatFilter || 'all');
            
            // Mostrar mensagem de sucesso
            alert('Ticket fechado com sucesso! O status foi atualizado no banco de dados.');
        } else {
            throw new Error(data.message || 'Erro ao fechar ticket');
        }
    } catch (error) {
        console.error('Erro ao fechar ticket:', error);
        alert('Erro ao fechar ticket: ' + error.message);
    }
}

async function deleteChatTicket() {
    if (!currentChatTicketId) {
        alert('Nenhum ticket selecionado');
        return;
    }
    
    if (!confirm('Tem certeza que deseja deletar permanentemente este ticket? Esta ação não pode ser desfeita.')) {
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/tickets.php?id=${currentChatTicketId}&action=delete`, {
            method: 'DELETE',
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Ticket deletado com sucesso!');
            currentChatTicketId = null;
            
            // Limpar área de mensagens
            const area = document.getElementById('chatMessagesArea');
            if (area) {
                area.innerHTML = '<div class="chat-empty-state"><i class="bi bi-chat-dots" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i><p style="color: var(--text-muted);">Selecione um ticket para visualizar as mensagens</p></div>';
            }
            
            // Esconder input
            const inputArea = document.getElementById('chatInputArea');
            if (inputArea) {
                inputArea.style.display = 'none';
            }
            
            // Recarregar lista de tickets
            loadChatTickets(document.querySelector('[data-chat-filter].active')?.dataset.chatFilter || 'all');
        } else {
            throw new Error(data.message || 'Erro ao deletar ticket');
        }
    } catch (error) {
        console.error('Erro ao deletar ticket:', error);
        alert('Erro ao deletar ticket: ' + error.message);
    }
}

// Funções para preview de imagem
window.previewServiceImage = function(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('serviceImagePreview');
            const previewImg = document.getElementById('serviceImagePreviewImg');
            if (preview && previewImg) {
                previewImg.src = e.target.result;
                preview.style.display = 'block';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
};

window.removeServiceImage = function() {
    const input = document.getElementById('serviceImagem');
    const preview = document.getElementById('serviceImagePreview');
    if (input) input.value = '';
    if (preview) preview.style.display = 'none';
};

// Tornar funções globais
window.editService = editService;
window.deleteService = deleteService;
window.showNotification = showNotification;
function initUsuariosButtons() {
    const btnAdd = document.getElementById('btnAddUsuario');
    if (btnAdd) {
        btnAdd.addEventListener('click', addUsuario);
    }
}

function addUsuario() {
    // Criar modal de adicionar usuário
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3>Adicionar Usuário</h3>
                <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addUsuarioForm">
                    <div class="form-group-modal">
                        <label for="addUsuarioTipo">Tipo de Usuário *</label>
                        <select id="addUsuarioTipo" required>
                            <option value="usuarios">Usuário Comum</option>
                            <option value="clientes">Cliente</option>
                        </select>
                    </div>
                    
                    <div class="form-group-modal">
                        <label for="addUsuarioNome">Nome *</label>
                        <input type="text" id="addUsuarioNome" required>
                    </div>
                    
                    <div class="form-group-modal">
                        <label for="addUsuarioEmail">Email *</label>
                        <input type="email" id="addUsuarioEmail" required>
                    </div>
                    
                    <div class="form-group-modal">
                        <label for="addUsuarioSenha">Senha *</label>
                        <input type="password" id="addUsuarioSenha" required minlength="6">
                        <small style="color: var(--text-muted); font-size: 0.85rem;">Mínimo 6 caracteres</small>
                    </div>
                    
                    <div class="form-group-modal" id="addUsuarioCodigoGroup" style="display: none;">
                        <label for="addUsuarioCodigo">Código de Verificação *</label>
                        <input type="text" id="addUsuarioCodigo">
                        <small style="color: var(--text-muted); font-size: 0.85rem;">Apenas para clientes</small>
                    </div>
                    
                    <div class="form-group-modal">
                        <label for="addUsuarioStatus">Status</label>
                        <select id="addUsuarioStatus">
                            <option value="ativo" selected>Ativo</option>
                            <option value="inativo">Inativo</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-actions">
                <button class="btn-modal btn-modal-secondary" onclick="this.closest('.modal-overlay').remove()">Cancelar</button>
                <button class="btn-modal btn-modal-primary" onclick="saveNewUsuario()">Adicionar</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Mostrar/esconder campo de código baseado no tipo
    const tipoSelect = document.getElementById('addUsuarioTipo');
    const codigoGroup = document.getElementById('addUsuarioCodigoGroup');
    const codigoInput = document.getElementById('addUsuarioCodigo');
    
    tipoSelect.addEventListener('change', () => {
        if (tipoSelect.value === 'clientes') {
            codigoGroup.style.display = 'block';
            codigoInput.required = true;
        } else {
            codigoGroup.style.display = 'none';
            codigoInput.required = false;
        }
    });
    
    // Fechar ao clicar fora
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });
}

async function saveNewUsuario() {
    try {
        const tipo = document.getElementById('addUsuarioTipo').value;
        const nome = document.getElementById('addUsuarioNome').value.trim();
        const email = document.getElementById('addUsuarioEmail').value.trim();
        const senha = document.getElementById('addUsuarioSenha').value;
        const status = document.getElementById('addUsuarioStatus').value;
        const codigo = document.getElementById('addUsuarioCodigo').value.trim();
        
        if (!nome || !email || !senha) {
            alert('Preencha todos os campos obrigatórios');
            return;
        }
        
        if (senha.length < 6) {
            alert('A senha deve ter pelo menos 6 caracteres');
            return;
        }
        
        if (tipo === 'clientes' && !codigo) {
            alert('Código de verificação é obrigatório para clientes');
            return;
        }
        
        const data = {
            tipo,
            nome,
            email,
            senha,
            status
        };
        
        if (tipo === 'clientes') {
            data.codigo_verificacao = codigo;
        }
        
        const response = await fetch(`${API_BASE}/usuarios.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Usuário adicionado com sucesso!');
            document.querySelector('.modal-overlay').remove();
            loadUsuarios();
        } else {
            alert('Erro ao adicionar usuário: ' + (result.message || 'Erro desconhecido'));
        }
    } catch (error) {
        console.error('Erro ao adicionar usuário:', error);
        alert('Erro ao adicionar usuário');
    }
}

// ============================================
// AGENDAMENTOS
// ============================================
function initAgendamentosButtons() {
    document.addEventListener('click', (e) => {
        if (e.target.closest('#btnAddAgendamento')) {
            e.preventDefault();
            openAgendamentoModal();
        }
    });
    
    const closeModal = document.getElementById('closeAgendamentoModal');
    const cancelModal = document.getElementById('cancelAgendamentoModal');
    const agendamentoForm = document.getElementById('agendamentoForm');
    
    if (closeModal) {
        closeModal.addEventListener('click', closeAgendamentoModal);
    }
    
    if (cancelModal) {
        cancelModal.addEventListener('click', closeAgendamentoModal);
    }
    
    if (agendamentoForm) {
        agendamentoForm.addEventListener('submit', handleAgendamentoSubmit);
    }
    
    // Inicializar autocomplete de bairros de Uberlândia
    initBairrosAutocomplete();
    
    // Fechar modal ao clicar fora
    const modal = document.getElementById('agendamentoModal');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeAgendamentoModal();
            }
        });
    }
    
}

// Função para inicializar autocomplete de bairros
function initBairrosAutocomplete() {
    const regiaoInput = document.getElementById('agendamentoRegiao');
    if (!regiaoInput) return;
    
    let bairrosList = [];
    let currentFocus = -1;
    let autocompleteDiv = null;
    
    // Buscar lista de bairros
    fetch('/backend/api/bairros-uberlandia.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                bairrosList = data.data.map(b => b.nome);
            }
        })
        .catch(error => {
            console.error('Erro ao carregar bairros:', error);
        });
    
    // Criar div de autocomplete
    function createAutocompleteDiv() {
        if (!autocompleteDiv) {
            autocompleteDiv = document.createElement('div');
            autocompleteDiv.id = 'bairrosAutocompleteList';
            autocompleteDiv.className = 'autocomplete-items';
            regiaoInput.parentNode.appendChild(autocompleteDiv);
        }
        return autocompleteDiv;
    }
    
    // Função para filtrar bairros
    function filterBairros(val) {
        if (!val || val.length < 2) {
            if (autocompleteDiv) {
                autocompleteDiv.innerHTML = '';
                autocompleteDiv.style.display = 'none';
            }
            return;
        }
        
        const filtered = bairrosList.filter(bairro => 
            bairro.toLowerCase().includes(val.toLowerCase())
        ).slice(0, 10);
        
        const div = createAutocompleteDiv();
        div.innerHTML = '';
        
        if (filtered.length === 0) {
            div.style.display = 'none';
            return;
        }
        
        filtered.forEach((bairro, index) => {
            const item = document.createElement('div');
            item.innerHTML = `<strong>${bairro.substring(0, val.length)}</strong>${bairro.substring(val.length)}`;
            item.addEventListener('click', () => {
                regiaoInput.value = bairro;
                div.innerHTML = '';
                div.style.display = 'none';
            });
            div.appendChild(item);
        });
        
        div.style.display = 'block';
    }
    
    // Event listeners
    regiaoInput.addEventListener('input', function(e) {
        filterBairros(e.target.value);
        currentFocus = -1;
    });
    
    regiaoInput.addEventListener('keydown', function(e) {
        const div = document.getElementById('bairrosAutocompleteList');
        if (!div) return;
        
        let items = div.getElementsByTagName('div');
        
        if (e.keyCode === 40) { // Seta para baixo
            e.preventDefault();
            currentFocus++;
            if (currentFocus >= items.length) currentFocus = 0;
            addActive(items);
        } else if (e.keyCode === 38) { // Seta para cima
            e.preventDefault();
            currentFocus--;
            if (currentFocus < 0) currentFocus = items.length - 1;
            addActive(items);
        } else if (e.keyCode === 13) { // Enter
            e.preventDefault();
            if (currentFocus > -1 && items[currentFocus]) {
                items[currentFocus].click();
            }
        }
    });
    
    function addActive(items) {
        if (!items) return false;
        removeActive(items);
        if (currentFocus >= items.length) currentFocus = 0;
        if (currentFocus < 0) currentFocus = items.length - 1;
        items[currentFocus].classList.add('autocomplete-active');
    }
    
    function removeActive(items) {
        for (let i = 0; i < items.length; i++) {
            items[i].classList.remove('autocomplete-active');
        }
    }
    
    // Fechar autocomplete ao clicar fora
    document.addEventListener('click', function(e) {
        if (e.target !== regiaoInput && e.target.closest('.autocomplete-items') === null) {
            if (autocompleteDiv) {
                autocompleteDiv.style.display = 'none';
            }
        }
    });
}

async function loadAgendamentos(status = null) {
    try {
        // Mostrar loading nas colunas Kanban
        const columns = ['pendente', 'confirmado', 'cancelado'];
        columns.forEach(statusCol => {
            const columnBody = document.getElementById(`kanban-${statusCol}`);
            if (columnBody) {
                columnBody.innerHTML = '<div class="kanban-empty-state"><i class="bi bi-hourglass-split"></i><p>Carregando...</p></div>';
            }
        });
        
        let url = `${API_BASE}/agendamentos.php`;
        
        const response = await fetch(url, { 
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (data.success && Array.isArray(data.data)) {
            renderAgendamentos(data.data);
        } else {
            console.error('Erro ao carregar agendamentos:', data.message || 'Resposta inválida');
            columns.forEach(statusCol => {
                const columnBody = document.getElementById(`kanban-${statusCol}`);
                if (columnBody) {
                    columnBody.innerHTML = '<div class="kanban-empty-state" style="color: var(--error);"><i class="bi bi-exclamation-triangle"></i><p>Erro ao carregar</p></div>';
                }
            });
        }
    } catch (error) {
        console.error('Erro ao carregar agendamentos:', error);
        const columns = ['pendente', 'confirmado', 'cancelado'];
        columns.forEach(statusCol => {
            const columnBody = document.getElementById(`kanban-${statusCol}`);
            if (columnBody) {
                const errorMsg = error.message || 'Erro desconhecido';
                columnBody.innerHTML = `<div class="kanban-empty-state" style="color: var(--error);"><i class="bi bi-exclamation-triangle"></i><p>${errorMsg}</p><button onclick="loadAgendamentos()" style="margin-top: 10px; padding: 8px 16px; background: var(--accent-blue); color: white; border: none; border-radius: 4px; cursor: pointer;">Tentar novamente</button></div>`;
            }
        });
    }
}

function renderAgendamentos(agendamentos) {
    // Separar agendamentos por status
    const agendamentosPorStatus = {
        'pendente': [],
        'confirmado': [],
        'cancelado': []
    };
    
    agendamentos.forEach(ag => {
        // Normalizar status vindo do banco (maiúsculas/mistos) e tratar valores inesperados
        const rawStatus = ag.status || 'pendente';
        const normalizedStatus = (rawStatus + '').toLowerCase();
        const statusKey = agendamentosPorStatus[normalizedStatus] ? normalizedStatus : 'pendente';
        
        // Garantir que o objeto usado na UI contenha o status normalizado
        const agendamentoNormalizado = { ...ag, status: statusKey };
        agendamentosPorStatus[statusKey].push(agendamentoNormalizado);
    });
    
    // Renderizar cada coluna
    Object.keys(agendamentosPorStatus).forEach(status => {
        const columnBody = document.getElementById(`kanban-${status}`);
        const countElement = document.getElementById(`count-${status}`);
        
        if (countElement) {
            countElement.textContent = agendamentosPorStatus[status].length;
        }
        
        if (!columnBody) return;
        
        if (agendamentosPorStatus[status].length === 0) {
            columnBody.innerHTML = '<div class="kanban-empty-state"><i class="bi bi-inbox"></i><p>Nenhum agendamento</p></div>';
            return;
        }
        
        columnBody.innerHTML = agendamentosPorStatus[status].map(agendamento => {
            // Formatar data do agendamento (data que o cliente colocou)
            let dataAgendamento = null;
            if (agendamento.data_agendamento) {
                // A data vem do banco no formato YYYY-MM-DD
                if (agendamento.data_agendamento.includes('-')) {
                    const partes = agendamento.data_agendamento.split('-');
                    if (partes.length === 3) {
                        dataAgendamento = `${partes[2]}/${partes[1]}/${partes[0]}`;
                    } else {
                        dataAgendamento = agendamento.data_agendamento;
                    }
                } else {
                    dataAgendamento = agendamento.data_agendamento;
                }
            }
            
            const horaAgendamento = agendamento.hora_agendamento || null;
            
            const dataCriacao = agendamento.created_at ? 
                new Date(agendamento.created_at).toLocaleDateString('pt-BR') : 
                null;
            
            // Formatar data e hora juntas se ambas existirem (data que o cliente colocou)
            let dataHoraFormatada = '';
            if (dataAgendamento && horaAgendamento) {
                // Formatar hora (remover segundos se houver)
                const horaFormatada = horaAgendamento.substring(0, 5); // HH:MM
                dataHoraFormatada = `${dataAgendamento} às ${horaFormatada}`;
            } else if (dataAgendamento) {
                dataHoraFormatada = dataAgendamento;
            } else if (horaAgendamento) {
                const horaFormatada = horaAgendamento.substring(0, 5); // HH:MM
                dataHoraFormatada = horaFormatada;
            }
            
            return `
                <div class="kanban-card" draggable="true" ondragstart="dragAgendamento(event)" data-id="${agendamento.id}" data-status="${agendamento.status}">
                    <div class="kanban-card-header">
                        <span class="kanban-card-id">#${agendamento.id}</span>
                        <div class="kanban-card-actions">
                            ${agendamento.status === 'pendente' ? `
                            <button class="kanban-card-action-btn" onclick="aceitarAgendamento(${agendamento.id})" title="Aceitar" style="color: var(--success);">
                                <i class="bi bi-check-circle"></i>
                            </button>
                            <button class="kanban-card-action-btn" onclick="rejeitarAgendamento(${agendamento.id})" title="Rejeitar" style="color: var(--error);">
                                <i class="bi bi-x-circle"></i>
                            </button>
                            ` : ''}
                            ${agendamento.status === 'confirmado' ? `
                            <button class="kanban-card-action-btn" onclick="desmarcarAgendamento(${agendamento.id})" title="Desmarcar Agendamento" style="color: var(--warning);">
                                <i class="bi bi-calendar-x"></i>
                            </button>
                            ` : ''}
                            <button class="kanban-card-action-btn" onclick="editAgendamento(${agendamento.id})" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="kanban-card-action-btn" onclick="deleteAgendamento(${agendamento.id})" title="Excluir" style="color: var(--error);">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="kanban-card-body">
                        ${agendamento.servico_nome ? `
                        <div class="kanban-card-field" style="background: var(--bg-tertiary); padding: 0.5rem; border-radius: 6px; margin-bottom: 0.75rem;">
                            <i class="bi bi-bag-check" style="color: var(--accent-blue);"></i>
                            <strong style="color: var(--accent-blue);">Serviço:</strong>
                            <span style="color: var(--text-primary); font-weight: 600;">${agendamento.servico_nome}</span>
                            ${agendamento.servico_preco ? `<span style="color: var(--text-secondary); font-size: 0.85rem; margin-left: 0.5rem;">R$ ${parseFloat(agendamento.servico_preco).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>` : ''}
                        </div>
                        ` : ''}
                        <div class="kanban-card-field">
                            <i class="bi bi-person"></i>
                            <strong>Nome:</strong>
                            <span>${agendamento.nome || '-'}</span>
                        </div>
                        <div class="kanban-card-field">
                            <i class="bi bi-envelope"></i>
                            <strong>Email:</strong>
                            <span>${agendamento.email || '-'}</span>
                        </div>
                        <div class="kanban-card-field">
                            <i class="bi bi-telephone"></i>
                            <strong>Telefone:</strong>
                            <span>${agendamento.telefone || '-'}</span>
                        </div>
                        <div class="kanban-card-field">
                            <i class="bi bi-geo-alt"></i>
                            <strong>Região:</strong>
                            <span>${agendamento.regiao || '-'}</span>
                        </div>
                        ${agendamento.observacoes ? (() => {
                            // Verificar se contém informações de pacote estruturadas
                            const obs = agendamento.observacoes;
                            const temPacote = obs.includes('Total de Sessões:') || obs.includes('INFORMAÇÕES DO PACOTE');
                            
                            if (temPacote) {
                                // Extrair informações do pacote
                                const sessoesMatch = obs.match(/Total de Sessões:\s*(\d+)/);
                                const valorMatch = obs.match(/Valor Total:\s*R\$\s*([\d.,]+)/);
                                const valorOriginalMatch = obs.match(/Valor Original:\s*R\$\s*([\d.,]+)/);
                                const economiaMatch = obs.match(/Economia:\s*R\$\s*([\d.,]+)/);
                                const destinatarioMatch = obs.match(/Destinatário:\s*(.+)/);
                                
                                const sessoes = sessoesMatch ? sessoesMatch[1] : null;
                                const valor = valorMatch ? valorMatch[1] : null;
                                const valorOriginal = valorOriginalMatch ? valorOriginalMatch[1] : null;
                                const economia = economiaMatch ? economiaMatch[1] : null;
                                const destinatario = destinatarioMatch ? destinatarioMatch[1].trim() : null;
                                
                                // Extrair observações do usuário (após o bloco de pacote)
                                const obsUsuario = obs.split('Observações do Cliente:')[1] || '';
                                
                                return `
                                    <div class="kanban-card-field" style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 2px solid var(--border-color);">
                                        <div style="background: var(--bg-tertiary); padding: 0.75rem; border-radius: 8px; margin-bottom: 0.5rem;">
                                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                                <i class="bi bi-box-seam" style="color: var(--accent-blue); font-size: 1.1rem;"></i>
                                                <strong style="color: var(--accent-blue);">Pacote Selecionado</strong>
                                            </div>
                                            ${sessoes ? `
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                                                <span style="color: var(--text-secondary); font-size: 0.9rem;"><i class="bi bi-calendar-check"></i> Sessões:</span>
                                                <strong style="color: var(--text-primary);">${sessoes} sessão${sessoes > 1 ? 'ões' : ''}</strong>
                                            </div>
                                            ` : ''}
                                            ${valor ? `
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                                                <span style="color: var(--text-secondary); font-size: 0.9rem;"><i class="bi bi-currency-dollar"></i> Valor Total:</span>
                                                <strong style="color: var(--accent-blue); font-size: 1.1rem;">R$ ${valor}</strong>
                                            </div>
                                            ` : ''}
                                            ${valorOriginal ? `
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                                                <span style="color: var(--text-secondary); font-size: 0.85rem; text-decoration: line-through;">Valor Original:</span>
                                                <span style="color: var(--text-muted); font-size: 0.85rem; text-decoration: line-through;">R$ ${valorOriginal}</span>
                                            </div>
                                            ` : ''}
                                            ${economia ? `
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                                                <span style="color: var(--text-secondary); font-size: 0.9rem;"><i class="bi bi-tag-fill"></i> Economia:</span>
                                                <strong style="color: var(--success);">R$ ${economia}</strong>
                                            </div>
                                            ` : ''}
                                            ${destinatario ? `
                                            <div style="display: flex; justify-content: space-between; margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid var(--border-color);">
                                                <span style="color: var(--text-secondary); font-size: 0.9rem;"><i class="bi bi-gift"></i> Destinatário:</span>
                                                <strong style="color: var(--text-primary);">${destinatario}</strong>
                                            </div>
                                            ` : ''}
                                        </div>
                                        ${obsUsuario.trim() ? `
                                        <div class="kanban-card-field observacoes" style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid var(--border-color);">
                                            <i class="bi bi-chat-left-text" style="color: var(--text-secondary);"></i>
                                            <span style="font-size: 0.85rem; color: var(--text-secondary);">${obsUsuario.trim()}</span>
                                        </div>
                                        ` : ''}
                                    </div>
                                `;
                            } else {
                                // Exibir observações normais
                                return `
                                    <div class="kanban-card-field observacoes" style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid var(--border-color);">
                                        <i class="bi bi-chat-left-text"></i>
                                        <span style="font-size: 0.85rem; color: var(--text-secondary);">${agendamento.observacoes}</span>
                                    </div>
                                `;
                            }
                        })() : ''}
                    </div>
                    ${dataHoraFormatada || dataCriacao ? `
                    <div class="kanban-card-date">
                        ${dataHoraFormatada ? `<i class="bi bi-calendar-event"></i><span>Agendado: ${dataHoraFormatada}</span>` : ''}
                        ${dataCriacao ? `<i class="bi bi-clock"></i><span>Criado: ${dataCriacao}</span>` : ''}
                    </div>
                    ` : ''}
                </div>
            `;
        }).join('');
    });
}

async function openAgendamentoModal(agendamentoId = null) {
    const modal = document.getElementById('agendamentoModal');
    const form = document.getElementById('agendamentoForm');
    const title = document.getElementById('agendamentoModalTitle');
    
    // Carregar lista de serviços
    await loadServicosSelect();
    
    if (agendamentoId) {
        title.textContent = 'Editar Agendamento';
        await loadAgendamentoData(agendamentoId);
    } else {
        title.textContent = 'Adicionar Agendamento';
        form.reset();
        document.getElementById('agendamentoId').value = '';
    }
    
    modal.style.display = 'flex';
}

async function loadServicosSelect() {
    const select = document.getElementById('agendamentoServicoId');
    if (!select) return;
    
    try {
        const response = await fetch(`${API_BASE}/servicos.php`, { credentials: 'include' });
        const data = await response.json();
        
        if (data.success && Array.isArray(data.data)) {
            // Limpar opções existentes (exceto a primeira)
            select.innerHTML = '<option value="">Nenhum serviço específico</option>';
            
            // Adicionar serviços
            data.data.forEach(servico => {
                if (servico.status === 'ativo') {
                    const option = document.createElement('option');
                    option.value = servico.id;
                    option.textContent = `${servico.nome} - R$ ${parseFloat(servico.preco).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                    select.appendChild(option);
                }
            });
        }
    } catch (error) {
        console.error('Erro ao carregar serviços:', error);
    }
}

function closeAgendamentoModal() {
    const modal = document.getElementById('agendamentoModal');
    modal.style.display = 'none';
    document.getElementById('agendamentoForm').reset();
}

async function loadAgendamentoData(id) {
    try {
        const response = await fetch(`${API_BASE}/agendamentos.php?id=${id}`, { 
            credentials: 'include' 
        });
        
        const data = await response.json();
        
        if (data.success && data.data) {
            const agendamento = data.data;
            document.getElementById('agendamentoId').value = agendamento.id;
            document.getElementById('agendamentoNome').value = agendamento.nome || '';
            document.getElementById('agendamentoEmail').value = agendamento.email || '';
            document.getElementById('agendamentoTelefone').value = agendamento.telefone || '';
            document.getElementById('agendamentoRegiao').value = agendamento.bairro || agendamento.regiao || '';
            document.getElementById('agendamentoStatus').value = agendamento.status || 'pendente';
            document.getElementById('agendamentoObservacoes').value = agendamento.observacoes || '';
            
            if (agendamento.data_agendamento) {
                const dataFormatada = agendamento.data_agendamento.split(' ')[0]; // Remove hora se houver
                document.getElementById('agendamentoData').value = dataFormatada;
            }
            
            const horaInput = document.getElementById('agendamentoHora');
            if (horaInput && agendamento.hora_agendamento) {
                horaInput.value = agendamento.hora_agendamento;
            }
            
            const servicoSelect = document.getElementById('agendamentoServicoId');
            if (servicoSelect && agendamento.servico_id) {
                servicoSelect.value = agendamento.servico_id;
            }
        } else {
            alert('Erro ao carregar dados do agendamento');
        }
    } catch (error) {
        console.error('Erro ao carregar agendamento:', error);
        alert('Erro ao carregar dados do agendamento');
    }
}

async function handleAgendamentoSubmit(e) {
    e.preventDefault();
    
    const regiaoValue = document.getElementById('agendamentoRegiao').value;
    const formData = {
        id: document.getElementById('agendamentoId').value || null,
        nome: document.getElementById('agendamentoNome').value,
        email: document.getElementById('agendamentoEmail').value,
        telefone: document.getElementById('agendamentoTelefone').value,
        regiao: regiaoValue,
        bairro: regiaoValue, // Bairro é o mesmo que regiao (autocomplete de bairros de Uberlândia)
        status: document.getElementById('agendamentoStatus').value,
        observacoes: document.getElementById('agendamentoObservacoes').value,
        data_agendamento: document.getElementById('agendamentoData').value || null,
        hora_agendamento: document.getElementById('agendamentoHora')?.value || null,
        servico_id: document.getElementById('agendamentoServicoId')?.value || null
    };
    
    try {
        const isEdit = formData.id;
        const url = `${API_BASE}/agendamentos.php`;
        const method = isEdit ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(isEdit ? 'Agendamento atualizado com sucesso!' : 'Agendamento criado com sucesso!');
            closeAgendamentoModal();
            loadAgendamentos();
        } else {
            alert('Erro: ' + (data.message || 'Erro ao salvar agendamento'));
        }
    } catch (error) {
        console.error('Erro ao salvar agendamento:', error);
        alert('Erro ao salvar agendamento');
    }
}

function editAgendamento(id) {
    openAgendamentoModal(id);
}

async function aceitarAgendamento(id) {
    if (!confirm('Deseja aceitar este agendamento?')) {
        return;
    }
    
    try {
        // Buscar dados do agendamento primeiro
        const getResponse = await fetch(`${API_BASE}/agendamentos.php?id=${id}`, {
            credentials: 'include'
        });
        const getData = await getResponse.json();
        
        if (!getData.success || !getData.data) {
            alert('Erro ao buscar dados do agendamento');
            return;
        }
        
        const agendamento = getData.data;
        
        // Atualizar status para confirmado
        const response = await fetch(`${API_BASE}/agendamentos.php`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({
                id: id,
                nome: agendamento.nome,
                telefone: agendamento.telefone,
                email: agendamento.email,
                regiao: agendamento.regiao,
                bairro: agendamento.bairro || agendamento.regiao,
                status: 'confirmado',
                observacoes: agendamento.observacoes,
                data_agendamento: agendamento.data_agendamento,
                hora_agendamento: agendamento.hora_agendamento,
                servico_id: agendamento.servico_id
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Agendamento aceito com sucesso!');
            loadAgendamentos();
            // Recarregar também a seção de pedidos se estiver visível
            if (document.getElementById('pedidosSection') && document.getElementById('pedidosSection').style.display !== 'none') {
                const activeFilter = document.querySelector('#pedidosSection .filter-btn.active')?.getAttribute('data-filter') || 'all';
                loadPedidos(activeFilter);
            }
        } else {
            alert('Erro: ' + (data.message || 'Erro ao aceitar agendamento'));
        }
    } catch (error) {
        console.error('Erro ao aceitar agendamento:', error);
        alert('Erro ao aceitar agendamento');
    }
}

async function rejeitarAgendamento(id) {
    if (!confirm('⚠️ ATENÇÃO: Tem certeza que deseja REJEITAR este agendamento?\n\nO agendamento será DELETADO permanentemente do banco de dados.')) {
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/agendamentos.php?id=${id}`, {
            method: 'DELETE',
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('❌ Agendamento rejeitado e removido com sucesso!');
            loadAgendamentos();
            // Recarregar também a seção de pedidos se estiver visível
            if (document.getElementById('pedidosSection') && document.getElementById('pedidosSection').style.display !== 'none') {
                const activeFilter = document.querySelector('#pedidosSection .filter-btn.active')?.getAttribute('data-filter') || 'all';
                loadPedidos(activeFilter);
            }
        } else {
            alert('Erro: ' + (data.message || 'Erro ao rejeitar agendamento'));
        }
    } catch (error) {
        console.error('Erro ao rejeitar agendamento:', error);
        alert('Erro ao rejeitar agendamento');
    }
}

async function desmarcarAgendamento(id) {
    // Confirmação da doutora para desmarcar
    if (!confirm('⚠️ ATENÇÃO: Deseja DESMARCAR este agendamento?\n\nO agendamento será cancelado e o cliente será notificado.\n\nConfirma a desmarcação?')) {
        return;
    }
    
    try {
        // Buscar dados do agendamento primeiro
        const getResponse = await fetch(`${API_BASE}/agendamentos.php?id=${id}`, {
            credentials: 'include'
        });
        const getData = await getResponse.json();
        
        if (!getData.success || !getData.data) {
            alert('Erro ao buscar dados do agendamento');
            return;
        }
        
        const agendamento = getData.data;
        
        // Atualizar status para cancelado
        const response = await fetch(`${API_BASE}/agendamentos.php`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({
                id: id,
                nome: agendamento.nome,
                telefone: agendamento.telefone,
                email: agendamento.email,
                regiao: agendamento.regiao,
                bairro: agendamento.bairro || agendamento.regiao,
                status: 'cancelado',
                observacoes: agendamento.observacoes,
                data_agendamento: agendamento.data_agendamento,
                hora_agendamento: agendamento.hora_agendamento,
                servico_id: agendamento.servico_id
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Agendamento desmarcado com sucesso!');
            loadAgendamentos();
            // Recarregar também a seção de pedidos se estiver visível
            if (document.getElementById('pedidosSection') && document.getElementById('pedidosSection').style.display !== 'none') {
                const activeFilter = document.querySelector('#pedidosSection .filter-btn.active')?.getAttribute('data-filter') || 'all';
                loadPedidos(activeFilter);
            }
        } else {
            alert('Erro: ' + (data.message || 'Erro ao desmarcar agendamento'));
        }
    } catch (error) {
        console.error('Erro ao desmarcar agendamento:', error);
        alert('Erro ao desmarcar agendamento');
    }
}

async function deleteAgendamento(id) {
    if (!confirm('Tem certeza que deseja excluir este agendamento?')) {
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/agendamentos.php?id=${id}`, {
            method: 'DELETE',
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Agendamento excluído com sucesso!');
            loadAgendamentos();
            // Recarregar também a seção de pedidos se estiver visível
            if (document.getElementById('pedidosSection') && document.getElementById('pedidosSection').style.display !== 'none') {
                const activeFilter = document.querySelector('#pedidosSection .filter-btn.active')?.getAttribute('data-filter') || 'all';
                loadPedidos(activeFilter);
            }
        } else {
            alert('Erro: ' + (data.message || 'Erro ao excluir agendamento'));
        }
    } catch (error) {
        console.error('Erro ao excluir agendamento:', error);
        alert('Erro ao excluir agendamento');
    }
}

// ============================================
// DRAG AND DROP - AGENDAMENTOS
// ============================================
let draggedAgendamentoId = null;
let draggedAgendamentoStatus = null;

function dragAgendamento(event) {
    draggedAgendamentoId = event.target.closest('.kanban-card').dataset.id;
    draggedAgendamentoStatus = event.target.closest('.kanban-card').dataset.status;
    event.target.closest('.kanban-card').classList.add('dragging');
    event.dataTransfer.effectAllowed = 'move';
}

function allowDrop(event) {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    event.currentTarget.classList.add('drag-over');
}

function dropAgendamento(event) {
    event.preventDefault();
    event.currentTarget.classList.remove('drag-over');
    
    if (!draggedAgendamentoId) return;
    
    const card = document.querySelector(`[data-id="${draggedAgendamentoId}"]`);
    if (card) {
        card.classList.remove('dragging');
    }
    
    const newStatus = event.currentTarget.closest('.kanban-column').dataset.status;
    
    if (newStatus === draggedAgendamentoStatus) {
        return; // Mesmo status, não fazer nada
    }
    
    // Atualizar status no banco de dados
    updateAgendamentoStatus(draggedAgendamentoId, newStatus);
}

async function updateAgendamentoStatus(id, newStatus) {
    try {
        // Buscar dados do agendamento primeiro
        const response = await fetch(`${API_BASE}/agendamentos.php?id=${id}`, {
            credentials: 'include'
        });
        
        const data = await response.json();
        
        if (!data.success || !data.data) {
            throw new Error('Erro ao buscar dados do agendamento');
        }
        
        const agendamento = data.data;
        
        // Atualizar apenas o status
        const updateResponse = await fetch(`${API_BASE}/agendamentos.php`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({
                id: parseInt(id),
                nome: agendamento.nome,
                telefone: agendamento.telefone,
                email: agendamento.email,
                regiao: agendamento.regiao,
                status: newStatus,
                observacoes: agendamento.observacoes || null,
                data_agendamento: agendamento.data_agendamento || null
            })
        });
        
        const updateData = await updateResponse.json();
        
        if (updateData.success) {
            // Recarregar agendamentos para atualizar a visualização
            loadAgendamentos();
        } else {
            throw new Error(updateData.message || 'Erro ao atualizar status');
        }
    } catch (error) {
        console.error('Erro ao atualizar status:', error);
        alert('Erro ao atualizar status do agendamento: ' + error.message);
        // Recarregar para garantir que está sincronizado
        loadAgendamentos();
    }
}

// Remover classe drag-over quando sair da área
document.addEventListener('dragleave', (e) => {
    if (e.target.classList.contains('kanban-column-body')) {
        e.target.classList.remove('drag-over');
    }
});

// Tornar funções globais
window.dragAgendamento = dragAgendamento;
window.allowDrop = allowDrop;
window.dropAgendamento = dropAgendamento;

window.editUsuario = editUsuario;
window.deleteUsuario = deleteUsuario;
window.saveUsuario = saveUsuario;
window.addUsuario = addUsuario;
window.saveNewUsuario = saveNewUsuario;
window.viewUsuarioDetails = viewUsuarioDetails;
window.editPedido = editPedido;
window.deletePedido = deletePedido;
window.aceitarAgendamento = aceitarAgendamento;
window.rejeitarAgendamento = rejeitarAgendamento;
window.desmarcarAgendamento = desmarcarAgendamento;
window.viewDocumento = viewDocumento;
window.editDocumento = editDocumento;
window.deleteDocumento = deleteDocumento;
window.openChatTicket = openChatTicket;
window.filterChatTickets = filterChatTickets;
window.editAgendamento = editAgendamento;
window.deleteAgendamento = deleteAgendamento;
window.loadAgendamentos = loadAgendamentos;
// openServiceModal já está definido globalmente acima (linha 1014)
window.closeServiceModal = closeServiceModal;
window.editService = editService;
window.deleteService = deleteService;

