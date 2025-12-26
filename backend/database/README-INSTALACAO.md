# 📦 Instalação Completa do Banco de Dados - D3 Estética

Este guia explica como instalar o banco de dados completo do sistema D3 Estética.

## 🚀 Instalação Rápida

### Opção 1: Instalação Automática (Recomendado)

1. **Configure as credenciais do banco de dados** no arquivo:
   ```
   backend/database/install-completo.php
   ```
   
   Edite as variáveis no início do arquivo:
   ```php
   $db_config = [
       'host' => 'localhost',
       'name' => 'seu_banco_de_dados',
       'user' => 'seu_usuario',
       'pass' => 'sua_senha',
       'charset' => 'utf8mb4'
   ];
   ```

2. **Execute o script de instalação**:
   - Acesse no navegador: `http://seudominio.com/backend/database/install-completo.php`
   - Ou execute via linha de comando: `php backend/database/install-completo.php`

3. **Pronto!** O banco de dados será criado automaticamente com todas as tabelas.

### Opção 2: Instalação Manual via SQL

1. **Crie o banco de dados** (se ainda não existir):
   ```sql
   CREATE DATABASE seu_banco_de_dados CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Execute o script SQL**:
   ```bash
   mysql -u seu_usuario -p seu_banco_de_dados < backend/database/d3estetica-completo.sql
   ```

   Ou importe via phpMyAdmin/MySQL Workbench.

## 📊 Estrutura do Banco de Dados

O banco de dados contém **19 tabelas** organizadas por funcionalidade:

### 👥 Usuários e Autenticação (5 tabelas)
- `administradores` - Administradores do sistema
- `usuarios` - Usuários gerais
- `clientes` - Clientes da clínica
- `doutoras` - Profissionais (doutoras)
- `admin_tokens` - Tokens de autenticação 2FA

### 🛍️ Serviços e Produtos (3 tabelas)
- `servicos` - Serviços oferecidos
  - Campos: `criado_por`, `o_que_esta_incluso`, `created_at`, `updated_at`
- `servico_valores` - Valores alternativos para serviços
- `promocoes` - Promoções ativas

### 📅 Agendamentos (2 tabelas)
- `agendamentos` - Agendamentos de clientes
- `anamneses` - Fichas de anamnese

### ⭐ VIP (2 tabelas)
- `planos_vip` - Planos VIP disponíveis
- `vips` - Clientes VIP

### 💬 Suporte (2 tabelas)
- `tickets` - Tickets de suporte
- `mensagens_chat` - Mensagens dos tickets

### 📝 Conteúdo (1 tabela)
- `blog_posts` - Posts do blog

### 👔 Recursos Humanos (2 tabelas)
- `candidaturas` - Candidaturas para trabalhar conosco
- `vagas` - Vagas de emprego

### 💳 Pagamentos (1 tabela)
- `pedidos` - Pedidos e pagamentos (Mercado Pago)

### 🗺️ Auxiliares (1 tabela)
- `bairros_uberlandia` - Bairros de Uberlândia para autocomplete

## 🔐 Credenciais Padrão

Após a instalação, você terá um administrador padrão:

- **Email:** `admin@d3estetica.com.br`
- **Senha:** `admin123`
- **CPF:** `00000000000`
- **Código 2FA:** `272204`

⚠️ **IMPORTANTE:** Altere a senha e CPF após o primeiro login!

## 🔧 Configuração Pós-Instalação

1. **Configure o arquivo de conexão:**
   ```
   backend/config/database.php
   ```

2. **Configure o código 2FA (se necessário):**
   - Acesse: `backend/admin/setup-2fa-default.php`
   - Isso atualizará o código 2FA para todos os administradores

3. **Acesse o painel administrativo:**
   ```
   http://seudominio.com/backend/admin/index.php
   ```

## 📋 Verificação

Para verificar se todas as tabelas foram criadas corretamente:

1. Acesse: `backend/database/verify-database.php`
2. Ou execute: `php backend/database/verify-database.php`

## 🔄 Atualizações

Se você já tem um banco de dados e precisa apenas adicionar campos novos:

1. **Para adicionar campos em serviços:**
   - Execute: `backend/database/add-servicos-fields.php`
   - Ou o SQL: `backend/database/add-servicos-fields.sql`

## 📝 Notas Importantes

- Todas as tabelas usam `utf8mb4_unicode_ci` para suporte completo a caracteres especiais
- Foreign keys estão configuradas com `ON DELETE SET NULL` ou `ON DELETE CASCADE` conforme apropriado
- Índices foram criados nas colunas mais consultadas para otimização
- Timestamps automáticos (`created_at`, `updated_at`) estão configurados

## 🆘 Suporte

Se encontrar problemas durante a instalação:

1. Verifique os logs de erro do PHP
2. Verifique as credenciais do banco de dados
3. Certifique-se de que o usuário do MySQL tem permissões para criar tabelas
4. Verifique se o charset `utf8mb4` está disponível no seu MySQL

## 📚 Documentação Adicional

- Estrutura detalhada: `docs/database-documentation.md`
- APIs disponíveis: `docs/api.md`
- Configuração completa: `docs/CONFIGURACAO_COMPLETA.md`

