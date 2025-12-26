# Script SQL - Tabela de Formulários

## 📋 Arquivo: `formularios.sql`

Este script cria a tabela `formularios` que armazena os formulários enviados pelos clientes. Os dados são sincronizados automaticamente da tabela `agendamentos` através de triggers.

## ✅ O que está incluído:

### Estrutura da Tabela `formularios`:

1. **`id`** - ID único do formulário (auto increment)
2. **`agendamento_id`** - ID do agendamento original (referência)
3. **`servico_id`** - ID do serviço solicitado
4. **`nome`** - Nome completo do cliente
5. **`email`** - Email do cliente
6. **`telefone`** - Telefone do cliente
7. **`regiao`** - Bairro/Região do cliente
8. **`bairro`** - Bairro específico do cliente
9. **`status`** - Status do formulário: `pendente`, `confirmado`, `cancelado`, `concluido`
10. **`observacoes`** - Observações do formulário
11. **`data_agendamento`** - Data do agendamento solicitado
12. **`hora_agendamento`** - Hora do agendamento solicitado
13. **`servico_nome`** - Nome do serviço (cache)
14. **`servico_preco`** - Preço do serviço (cache)
15. **`servico_imagem`** - Caminho da imagem do serviço (cache)
16. **`servico_descricao`** - Descrição do serviço (cache)
17. **`servico_categoria`** - Categoria do serviço (cache)
18. **`created_at`** - Data de criação do registro
19. **`updated_at`** - Data da última atualização
20. **`sincronizado_at`** - Data da última sincronização com agendamentos

### Triggers Automáticos:

- **`after_agendamento_insert`** - Sincroniza automaticamente quando um novo agendamento é criado
- **`after_agendamento_update`** - Atualiza o formulário quando o agendamento é atualizado

### Procedure de Sincronização:

- **`sync_agendamentos_to_formularios()`** - Procedure para sincronizar todos os agendamentos existentes

## 🚀 Como usar:

### Opção 1: Via phpMyAdmin

1. Acesse o phpMyAdmin
2. Selecione seu banco de dados
3. Vá em "SQL" ou "Importar"
4. Execute o arquivo `formularios.sql`

### Opção 2: Via linha de comando

```bash
mysql -u usuario -p nome_do_banco < backend/database/formularios.sql
```

### Opção 3: Via cliente MySQL

1. Conecte-se ao banco de dados
2. Execute: `source backend/database/formularios.sql;`

## 📝 Sincronização Inicial:

Após criar a tabela, execute a procedure para sincronizar os dados existentes:

```sql
CALL sync_agendamentos_to_formularios();
```

## 🔄 Sincronização Automática:

A partir de agora, todos os novos agendamentos serão automaticamente sincronizados para a tabela `formularios` através dos triggers.

## 📌 Notas Importantes:

- A tabela `formularios` é independente da tabela `agendamentos`
- Os dados são sincronizados automaticamente via triggers
- Você pode atualizar os formulários sem afetar os agendamentos
- A tabela mantém cache das informações do serviço para melhor performance

