# Script SQL - Tabela de Agendamentos

## 📋 Arquivo: `agendamentos-completo.sql`

Este script cria e atualiza a tabela `agendamentos` com todas as colunas necessárias para o sistema de agendamento implementado.

## ✅ O que está incluído:

### Estrutura da Tabela `agendamentos`:

1. **`id`** - ID único do agendamento (auto increment)
2. **`servico_id`** - ID do serviço agendado (foreign key para `servicos`)
3. **`nome`** - Nome completo do cliente (obrigatório)
4. **`email`** - Email do cliente (obrigatório)
5. **`telefone`** - Telefone do cliente
6. **`regiao`** - Bairro/Região do cliente (obrigatório no formulário)
7. **`bairro`** - Bairro específico do cliente
8. **`status`** - Status do agendamento: `pendente`, `confirmado`, `cancelado`, `concluido`
9. **`observacoes`** - Observações do agendamento (inclui informações do pacote selecionado)
10. **`data_agendamento`** - Data do agendamento (pode ser NULL)
11. **`hora_agendamento`** - Hora do agendamento no formato HH:00 (pode ser NULL)
12. **`created_at`** - Data de criação do registro
13. **`updated_at`** - Data da última atualização

### Índices Criados:

- `idx_servico_id` - Índice para busca por serviço
- `idx_email` - Índice para busca por email
- `idx_regiao` - Índice para busca por região/bairro
- `idx_status` - Índice para filtro por status
- `idx_data_hora` - Índice composto para verificação de conflitos de horário
- `idx_created_at` - Índice para ordenação por data de criação

### Tabela Auxiliar:

- **`bairros_uberlandia`** - Tabela para armazenar bairros de Uberlândia (usada no autocomplete)

## 🚀 Como usar:

### Opção 1: Via phpMyAdmin

1. Acesse o phpMyAdmin
2. Selecione seu banco de dados
3. Vá em "SQL" ou "Importar"
4. Execute o arquivo `agendamentos-completo.sql`

### Opção 2: Via linha de comando

```bash
mysql -u usuario -p nome_do_banco < backend/database/agendamentos-completo.sql
```

### Opção 3: Via cliente MySQL

1. Abra o arquivo `agendamentos-completo.sql`
2. Copie todo o conteúdo
3. Cole no seu cliente MySQL (MySQL Workbench, DBeaver, etc.)
4. Execute

## 🔒 Segurança

Este script é **seguro** para executar em tabelas existentes:

- ✅ Verifica se as colunas existem antes de criar
- ✅ Verifica se os índices existem antes de criar
- ✅ **NÃO apaga dados existentes**
- ✅ Apenas adiciona colunas/índices que não existem
- ✅ Modifica colunas existentes apenas para permitir NULL onde necessário

## 📝 Campos Obrigatórios no Formulário:

- `nome` - Nome completo do cliente
- `email` - Email válido do cliente
- `telefone` - Telefone do cliente
- `regiao` - Bairro/Região (com autocomplete)

## 📦 Informações do Pacote:

As informações do pacote selecionado (sessões, valor, destinatário) são armazenadas no campo `observacoes` no seguinte formato:

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📦 INFORMAÇÕES DO PACOTE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Total de Sessões: X
Valor Total: R$ X,XX
Valor Original: R$ X,XX (se houver)
Economia: R$ X,XX (se houver)
Destinatário: Para Você / Presente
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Observações do Cliente:
[observações do usuário se houver]
```

## 🔍 Verificação de Conflitos:

O sistema verifica automaticamente se já existe um agendamento para a mesma data e hora antes de criar um novo. Apenas agendamentos com status `pendente` ou `confirmado` são considerados para verificação de conflito.

## 📊 Status do Agendamento:

- **`pendente`** - Agendamento aguardando confirmação
- **`confirmado`** - Agendamento confirmado
- **`cancelado`** - Agendamento cancelado
- **`concluido`** - Agendamento concluído

## 🗺️ Tabela de Bairros:

A tabela `bairros_uberlandia` é usada para o autocomplete no formulário. Você pode adicionar bairros manualmente ou usar a API `/backend/api/bairros-uberlandia.php` para gerenciar.

### Exemplo de inserção de bairros:

```sql
INSERT IGNORE INTO `bairros_uberlandia` (`nome`, `zona`) VALUES
('Centro', 'Centro'),
('Santa Mônica', 'Norte'),
('Planalto', 'Norte'),
('Morumbi', 'Norte'),
('Mansour', 'Sul'),
('Granada', 'Sul'),
('Lídice', 'Leste'),
('Umuarama', 'Oeste');
```

## ⚠️ Notas Importantes:

1. **Foreign Key**: A tabela `agendamentos` tem uma foreign key para `servicos`. Certifique-se de que a tabela `servicos` existe antes de executar o script.

2. **Charset**: A tabela usa `utf8mb4_unicode_ci` para suporte completo a caracteres especiais e emojis.

3. **Timestamps**: Os campos `created_at` e `updated_at` são atualizados automaticamente pelo MySQL.

4. **Horários**: O sistema aceita apenas horários exatos no formato HH:00 (ex: 08:00, 09:00, 10:00). Horários com minutos não são permitidos.

## 🔄 Atualizações Futuras:

Se precisar adicionar novos campos no futuro, você pode:

1. Adicionar manualmente via ALTER TABLE
2. Ou criar um novo script de migração seguindo o padrão deste arquivo

## 📞 Suporte:

Em caso de dúvidas ou problemas, verifique:

1. Se a tabela `servicos` existe
2. Se o usuário do banco tem permissões para criar tabelas e índices
3. Se o charset do banco suporta utf8mb4

