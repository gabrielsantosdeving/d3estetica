# Scripts de Migração do Banco de Dados

## Problema Identificado

A tabela `agendamentos` estava com estrutura diferente do que o código PHP esperava, causando erros ao tentar inserir dados.

## Solução

Foram criados scripts para corrigir a estrutura do banco de dados:

### 1. Script SQL de Migração (`fix-agendamentos-table.sql`)

Execute este script no seu banco de dados MySQL para corrigir a tabela `agendamentos`:

```sql
-- Execute o arquivo: backend/database/fix-agendamentos-table.sql
```

### 2. Script PHP de Migração (`migrate-agendamentos.php`)

Execute este script via navegador ou linha de comando:

**Via navegador:**
```
http://seudominio.com/backend/database/migrate-agendamentos.php
```

**Via linha de comando:**
```bash
php backend/database/migrate-agendamentos.php
```

### 3. Banco de Dados Completo Atualizado (`d3estetica-completo.sql`)

O arquivo `d3estetica-completo.sql` foi atualizado com a estrutura correta. Se você está criando o banco do zero, execute este arquivo.

## Mudanças Realizadas

### Tabela `agendamentos`

**Colunas adicionadas/corrigidas:**
- `nome` - Nome do cliente (substitui `cliente_nome`)
- `email` - Email do cliente (substitui `cliente_email`)
- `telefone` - Telefone do cliente (substitui `cliente_telefone`)
- `regiao` - Região do cliente (NOVO)
- `bairro` - Bairro do cliente (NOVO)

**Colunas modificadas:**
- `data_agendamento` - Agora permite NULL
- `hora_agendamento` - Agora permite NULL

**Índices adicionados:**
- `idx_email` - Índice no campo email
- `idx_regiao` - Índice no campo regiao

### Tabela `planos_vip`

**Colunas adicionadas:**
- `desconto_percentual` - Desconto percentual do plano
- `destaque` - Se o plano deve ser destacado

**Índices adicionados:**
- `idx_tipo` - Índice no campo tipo
- `idx_destaque` - Índice no campo destaque

## Como Executar

### Opção 1: Se o banco já existe

Execute o script de migração PHP:
```bash
php backend/database/migrate-agendamentos.php
```

### Opção 2: Se está criando do zero

Execute o arquivo SQL completo:
```sql
-- Importe o arquivo: backend/database/d3estetica-completo.sql
```

### Opção 3: Via phpMyAdmin ou cliente MySQL

1. Acesse seu painel de controle do banco de dados
2. Selecione o banco de dados `u863732122_d3esteticaa`
3. Vá em "SQL" ou "Importar"
4. Execute o conteúdo do arquivo `fix-agendamentos-table.sql`

## Verificação

Após executar a migração, verifique se as colunas foram criadas:

```sql
DESCRIBE agendamentos;
DESCRIBE planos_vip;
```

Você deve ver as novas colunas listadas.

## Notas Importantes

- O script de migração preserva os dados existentes
- Se houver colunas antigas (`cliente_nome`, `cliente_email`, etc.), os dados serão migrados automaticamente
- As colunas antigas NÃO são removidas automaticamente (comentadas no script SQL)
- Execute os scripts com cuidado em produção

