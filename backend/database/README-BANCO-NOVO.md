# Banco de Dados Novo - D3 Estética

## 📋 Arquivo: `banco-novo-completo.sql`

Este arquivo cria um **banco de dados completamente novo** do zero, organizado e funcional para o painel administrativo.

## ✅ O que está incluído:

### Estrutura Completa (19 Tabelas):
1. **administradores** - Administradores do sistema
2. **usuarios** - Usuários gerais
3. **clientes** - Clientes da clínica
4. **doutoras** - Profissionais
5. **admin_tokens** - Tokens de autenticação 2FA (com token_hash)
6. **servicos** - Serviços oferecidos (com todos os campos necessários)
7. **servico_valores** - Valores alternativos
8. **promocoes** - Promoções
9. **agendamentos** - Agendamentos (estrutura correta: nome, email, telefone, regiao, bairro)
10. **anamneses** - Fichas de anamnese
11. **planos_vip** - Planos VIP (com desconto_percentual e destaque)
12. **vips** - Clientes VIP
13. **tickets** - Tickets de suporte
14. **mensagens_chat** - Mensagens dos tickets
15. **blog_posts** - Posts do blog
16. **candidaturas** - Candidaturas
17. **vagas** - Vagas de emprego
18. **bairros_uberlandia** - Bairros para autocomplete
19. **pedidos** - Pedidos e pagamentos (Mercado Pago)

### Dados Iniciais:
- ✅ **Administrador padrão**:
  - Email: `admin@d3estetica.com.br`
  - Senha: `admin123`
  - Código 2FA: `272204`

- ✅ **17 Serviços de exemplo**:
  - 6 serviços faciais
  - 6 serviços corporais
  - 5 serviços de beleza

## 🚀 Como usar:

### Opção 1: Via phpMyAdmin
1. Acesse o phpMyAdmin
2. Selecione seu banco de dados (`u863732122_d3esteticaa`)
3. Vá em "SQL" ou "Importar"
4. Execute o arquivo `banco-novo-completo.sql`

### Opção 2: Via linha de comando
```bash
mysql -u u863732122_admind3 -p u863732122_d3esteticaa < backend/database/banco-novo-completo.sql
```

### Opção 3: Via cliente MySQL
1. Abra o arquivo `banco-novo-completo.sql`
2. Copie todo o conteúdo
3. Cole no seu cliente MySQL (MySQL Workbench, DBeaver, etc.)
4. Execute

## ⚠️ IMPORTANTE:

- Este arquivo é para criar um banco **NOVO do zero**
- Se você já tem dados no banco, use: `atualizar-banco-existente.sql`
- O arquivo usa `INSERT IGNORE` para não duplicar dados
- Todas as foreign keys estão configuradas corretamente

## ✅ Após executar:

1. O banco estará completamente funcional
2. Você poderá fazer login no painel com:
   - Email: `admin@d3estetica.com.br`
   - Senha: `admin123`
   - Código 2FA: `272204`
3. Os 17 serviços estarão disponíveis
4. Todas as funcionalidades do painel funcionarão corretamente

## 🔧 Estrutura Organizada:

- Todas as tabelas têm índices nas colunas mais consultadas
- Foreign keys configuradas corretamente
- Charset utf8mb4 para suporte completo a caracteres especiais
- Timestamps automáticos (created_at, updated_at)
- Status padrão configurado

## 📝 Notas:

- Altere a senha do administrador após o primeiro login
- Os serviços podem ser editados/removidos pelo painel
- A estrutura está 100% compatível com o código PHP

