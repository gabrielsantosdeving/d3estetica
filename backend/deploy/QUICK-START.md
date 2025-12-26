# 🚀 Guia Rápido - Deploy Automático

## Configuração Inicial (5 minutos)

### 1. Editar Configuração

Abra `backend/deploy/deploy-config.php` e configure:

```php
'ftp' => [
    'host' => 'ftp.d3estetica.com.br',     // ← Seu host FTP
    'username' => 'u123456789',              // ← Seu usuário FTP
    'password' => 'SUA_SENHA_FTP',          // ← Sua senha FTP
],

'security' => [
    'token' => 'MeuTokenSeguro123!@#',      // ← Altere para algo seguro
],
```

### 2. Obter Credenciais FTP

1. Acesse: **Hostinger → Hospedagem → Gerenciar → FTP**
2. Copie: Host, Usuário, Senha
3. Cole no `deploy-config.php`

### 3. Testar Conexão

```bash
cd backend/deploy
php deploy.php --dry-run
```

Se aparecer a lista de arquivos, está funcionando! ✅

## Uso Diário

### Opção 1: Via Painel Web (Recomendado)

1. Acesse: `https://seusite.com.br/backend/admin/`
2. Login como admin
3. Clique em **"Deploy"** no menu
4. Digite o token
5. Clique em **"Simular Deploy"** primeiro
6. Se estiver tudo certo, clique em **"Executar Deploy Agora"**

### Opção 2: Via Terminal

```bash
# Simular primeiro
php backend/deploy/deploy.php --dry-run

# Executar deploy real
php backend/deploy/deploy.php
```

## Fluxo Recomendado

```
1. Fazer alterações no código
   ↓
2. Testar localmente
   ↓
3. Simular deploy (--dry-run)
   ↓
4. Verificar lista de arquivos
   ↓
5. Executar deploy real
   ↓
6. Verificar site online
```

## ⚠️ Importante

- ✅ **Sempre simule primeiro** (`--dry-run`)
- ✅ **Backup automático** é criado antes de cada deploy
- ✅ **Token de segurança** é obrigatório no painel web
- ❌ **Não commite** `deploy-config.php` no Git
- ❌ **Não compartilhe** o token de segurança

## Problemas Comuns

### "Falha ao conectar"
→ Verifique host e porta no `deploy-config.php`

### "Falha ao fazer login"
→ Verifique usuário e senha FTP

### "Token inválido"
→ Verifique o token em `deploy-config.php`

## Pronto! 🎉

Agora você pode fazer deploy com um clique!

---

**Dúvidas?** Consulte o `README.md` completo.

