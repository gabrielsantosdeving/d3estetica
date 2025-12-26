# Sistema de Deploy Automático - D3 Estética

Sistema completo de deploy automático usando apenas PHP puro, sem dependências externas.

## 📋 Índice

1. [Instalação](#instalação)
2. [Configuração](#configuração)
3. [Uso](#uso)
4. [Estrutura de Arquivos](#estrutura-de-arquivos)
5. [Segurança](#segurança)
6. [Troubleshooting](#troubleshooting)

## 🚀 Instalação

### 1. Estrutura de Pastas

Coloque os arquivos na seguinte estrutura:

```
public_html/
├── backend/
│   ├── deploy/
│   │   ├── deploy-config.php      ← Configurações
│   │   ├── deploy-functions.php   ← Funções auxiliares
│   │   ├── deploy.php            ← Script CLI
│   │   ├── deploy-panel.php       ← Interface web
│   │   └── deploy.log            ← Logs (criado automaticamente)
│   └── admin/
│       └── index.html             ← Painel admin (já atualizado)
└── backups/                       ← Backups (criado automaticamente)
```

### 2. Permissões

Certifique-se de que as pastas têm permissões de escrita:

```bash
chmod 755 backend/deploy
chmod 755 backups
chmod 644 backend/deploy/*.php
```

## ⚙️ Configuração

### 1. Configurar deploy-config.php

Abra o arquivo `backend/deploy/deploy-config.php` e configure:

```php
'ftp' => [
    'host' => 'ftp.d3estetica.com.br',     // Seu host FTP
    'port' => 21,                           // Porta FTP (21 ou 22)
    'username' => 'u123456789',              // Seu usuário FTP
    'password' => 'SUA_SENHA_FTP',          // Sua senha FTP
    'timeout' => 30,
    'passive' => true,
    'ssl' => false,                         // true para FTPS
],

'paths' => [
    'local' => dirname(__DIR__),            // Pasta local (raiz do projeto)
    'remote' => '/public_html',             // Pasta remota na Hostinger
    'backup' => dirname(__DIR__) . '/backups',
],

'security' => [
    'token' => 'ALTERE_ESTE_TOKEN_PARA_ALGO_SEGURO', // Token de segurança
    'allowed_ips' => [],                    // IPs permitidos (vazio = todos)
    'require_admin' => true,                // Exigir login admin
],
```

### 2. Obter Credenciais FTP da Hostinger

1. Acesse o painel da Hostinger
2. Vá em **Hospedagem** → **Gerenciar** → **FTP**
3. Anote:
   - **Host FTP**: geralmente `ftp.seusite.com.br`
   - **Usuário**: geralmente começa com `u` seguido de números
   - **Senha**: a senha do FTP
   - **Porta**: geralmente 21 (FTP) ou 22 (SFTP)

### 3. Configurar Token de Segurança

No arquivo `deploy-config.php`, altere o token:

```php
'token' => 'MeuTokenSuperSeguro123!@#',
```

**IMPORTANTE**: Use um token forte e único. Não compartilhe este token.

## 📖 Uso

### Opção 1: Via Linha de Comando (CLI)

#### Deploy Normal

```bash
cd backend/deploy
php deploy.php
```

#### Simular Deploy (Dry-Run)

```bash
php deploy.php --dry-run
```

#### Deploy Sem Backup

```bash
php deploy.php --no-backup
```

### Opção 2: Via Painel Web

1. Acesse o painel administrativo: `https://seusite.com.br/backend/admin/`
2. Faça login como administrador
3. Clique em **Deploy** no menu lateral
4. Digite o token de segurança
5. Escolha:
   - **Simular Deploy**: Apenas lista os arquivos sem enviar
   - **Executar Deploy Agora**: Faz o upload real

## 📁 Estrutura de Arquivos

### deploy-config.php
Arquivo de configuração com credenciais FTP, pastas e opções.

### deploy-functions.php
Funções auxiliares:
- `deploy_log()` - Sistema de logs
- `should_exclude()` - Verifica se arquivo deve ser ignorado
- `get_files_recursive()` - Lista arquivos recursivamente
- `ftp_connect_server()` - Conecta ao FTP
- `ftp_upload_file()` - Faz upload de arquivo
- `create_backup()` - Cria backup do servidor
- `cleanup_old_backups()` - Remove backups antigos

### deploy.php
Script principal para linha de comando. Executa o deploy completo.

### deploy-panel.php
Interface web integrada ao painel admin. Permite executar deploy via navegador.

## 🔒 Segurança

### 1. Proteção do Arquivo de Configuração

O arquivo `deploy-config.php` contém credenciais sensíveis. Proteja-o:

```apache
# .htaccess na pasta deploy/
<Files "deploy-config.php">
    Order Allow,Deny
    Deny from all
</Files>
```

### 2. Token de Segurança

- Use um token forte (mínimo 32 caracteres)
- Não compartilhe o token
- Altere o token periodicamente

### 3. IPs Permitidos (Opcional)

Para restringir acesso por IP:

```php
'allowed_ips' => ['192.168.1.100', '203.0.113.0'],
```

### 4. Autenticação Admin

O painel web exige login como administrador. Certifique-se de que:
- A sessão admin está funcionando
- O arquivo `index.php` do admin verifica autenticação

## 🔄 Sistema de Backup

### Como Funciona

1. Antes de cada deploy, um backup é criado automaticamente
2. Backups são salvos em `backups/backup_YYYY-MM-DD_HHMMSS`
3. Backups podem ser comprimidos em ZIP
4. Backups antigos são removidos automaticamente (mantém apenas os últimos 10)

### Restaurar Backup

Para restaurar um backup:

1. Acesse a pasta `backups/`
2. Escolha o backup desejado
3. Descompacte (se estiver em ZIP)
4. Faça upload manual ou use o deploy reverso

## 📝 Logs

Os logs são salvos em `backend/deploy/deploy.log`:

```
[2024-01-15 10:30:45] [INFO] Conectando ao servidor FTP: ftp.d3estetica.com.br:21
[2024-01-15 10:30:46] [INFO] Login realizado com sucesso
[2024-01-15 10:30:47] [INFO] Upload: frontend/index.html
[2024-01-15 10:30:48] [ERROR] ERRO ao fazer upload de arquivo.php: Falha ao conectar
```

## 🐛 Troubleshooting

### Erro: "Falha ao conectar ao servidor FTP"

**Soluções:**
1. Verifique se o host FTP está correto
2. Verifique se a porta está correta (21 para FTP, 22 para SFTP)
3. Verifique se o firewall permite conexões FTP
4. Tente com `'ssl' => true` para FTPS

### Erro: "Falha ao fazer login no FTP"

**Soluções:**
1. Verifique usuário e senha
2. Certifique-se de que a conta FTP está ativa
3. Verifique se não há restrições de IP na Hostinger

### Erro: "Pasta local não encontrada"

**Soluções:**
1. Verifique o caminho em `deploy-config.php`
2. Use caminhos absolutos se necessário
3. Certifique-se de que a pasta existe

### Upload muito lento

**Soluções:**
1. Aumente o timeout em `deploy-config.php`
2. Verifique sua conexão de internet
3. Considere fazer deploy apenas de arquivos alterados

### Arquivos não aparecem no servidor

**Soluções:**
1. Verifique se o caminho remoto está correto (`/public_html`)
2. Verifique permissões de escrita no servidor
3. Verifique se não há erros no log

## 📞 Suporte

Para problemas ou dúvidas:
1. Verifique os logs em `deploy.log`
2. Execute em modo dry-run primeiro
3. Verifique as configurações FTP na Hostinger

## 🔄 Fluxo de Trabalho Recomendado

1. **Desenvolver localmente** - Faça suas alterações
2. **Testar localmente** - Certifique-se de que tudo funciona
3. **Simular deploy** - Execute `php deploy.php --dry-run`
4. **Revisar lista** - Verifique se os arquivos corretos serão enviados
5. **Executar deploy** - Execute `php deploy.php` ou use o painel web
6. **Verificar** - Acesse o site e confirme que está funcionando

## ⚠️ Importante

- **Sempre faça backup** antes de deploy em produção
- **Teste em ambiente de desenvolvimento** primeiro
- **Mantenha o token de segurança seguro**
- **Não commite** o arquivo `deploy-config.php` no Git
- **Revise os arquivos** que serão enviados antes de executar

---

**Desenvolvido para D3 Estética**  
Sistema de deploy automático usando PHP puro.

