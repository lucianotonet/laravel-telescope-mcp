# Guia Completo: Implementando Skills do Laravel Boost no `laravel-telescope-mcp`

## 1. Contexto: O que são Skills no Laravel Boost?

O Laravel Boost v2 introduziu o conceito de **Agent Skills** — módulos de conhecimento leves e ativados sob demanda que agentes de IA (Claude Code, Cursor, Copilot, etc.) carregam apenas quando relevantes para a tarefa atual. Diferente das **guidelines** (que são carregadas de forma fixa no início), as skills reduzem o "context bloat" e melhoram a qualidade do código gerado.

O formato segue o padrão aberto **Agent Skills** (agentskills.io), usado por Claude Code, GitHub Copilot, Cursor e outros.

### Como funciona o carregamento progressivo (Progressive Disclosure):

1. **Nível 1 — Metadata (sempre em contexto):** O `name` e `description` do frontmatter YAML de TODAS as skills instaladas são pré-carregados no system prompt do agente (~100 palavras por skill). É com base nisso que o agente decide se a skill é relevante.

2. **Nível 2 — SKILL.md body (sob demanda):** Se o agente decidir que a skill é relevante, ele lê o corpo completo do SKILL.md via bash/filesystem.

3. **Nível 3 — Arquivos auxiliares (sob demanda):** Se as instruções do SKILL.md referenciam outros arquivos (scripts, references, assets), o agente os lê conforme necessidade. Scripts são executados sem carregar o código no contexto — apenas o output entra no contexto.

---

## 2. Onde colocar os arquivos no seu pacote

O Laravel Boost detecta automaticamente skills de pacotes third-party pelo caminho convencional:

```
laravel-telescope-mcp/
├── src/
├── config/
├── resources/
│   └── boost/
│       ├── guidelines/
│       │   └── core.blade.php          ← Guidelines (carregadas sempre, upfront)
│       └── skills/
│           └── telescope-mcp-debugging/
│               ├── SKILL.md            ← Arquivo principal (OBRIGATÓRIO)
│               ├── references/         ← Documentação de referência (opcional)
│               │   └── TOOLS.md        ← Referência detalhada das 19 tools
│               ├── scripts/            ← Scripts executáveis (opcional)
│               └── assets/             ← Templates, exemplos (opcional)
└── ...
```

**Quando o usuário roda `php artisan boost:install`**, o Boost detecta o pacote `lucianotonet/laravel-telescope-mcp` no `composer.json` e automaticamente oferece para instalar as skills encontradas em `resources/boost/skills/`.

**Quando o usuário roda `php artisan boost:update`**, as skills são atualizadas.

---

## 3. Estrutura do SKILL.md — Requisitos Técnicos

### 3.1 Frontmatter YAML (OBRIGATÓRIO)

O frontmatter define os metadados que ficam SEMPRE no system prompt:

```yaml
---
name: telescope-mcp-debugging
description: Analyze and debug Laravel applications using Telescope MCP telemetry data. Use when working with application monitoring, debugging slow queries, inspecting HTTP requests, analyzing exceptions, reviewing logs, checking queued jobs, or any task involving Laravel Telescope data accessed via MCP tools.
---
```

**Regras de validação:**

| Campo | Regras |
|-------|--------|
| `name` | Máximo 64 caracteres. Apenas letras minúsculas, números e hífens. Sem XML tags. Sem palavras reservadas ("anthropic", "claude"). |
| `description` | Máximo 1024 caracteres. Não pode ser vazio. Sem XML tags. Deve descrever O QUE a skill faz E QUANDO usar. Escrito em terceira pessoa. |

**Convenção de nomenclatura:** Use a forma gerúndio em inglês (verb + -ing) ou o padrão `{pacote}-{domínio}`. Exemplos do Boost oficial: `livewire-development`, `pest-testing`, `tailwindcss-development`.

**A description é o trigger principal.** Todo "quando usar" deve estar aqui, NÃO no corpo do SKILL.md (o corpo só é carregado DEPOIS do trigger). Inclua palavras-chave específicas que o agente possa usar para matching:

```
✅ BOM: "...debugging slow queries, inspecting HTTP requests, analyzing exceptions..."
❌ RUIM: "Use for various debugging tasks" (muito genérico)
```

**Não inclua outros campos** no frontmatter além de `name` e `description`. Campos como `license`, `version`, `compatibility` são opcionais e raramente necessários.

### 3.2 Corpo do SKILL.md (Markdown)

O corpo deve ser conciso, acionável e focado em best practices. **Mantenha abaixo de 500 linhas.** Se precisar de mais conteúdo, divida em arquivos de referência.

**Princípio fundamental:** Claude já é muito inteligente. Só adicione contexto que ele NÃO teria sozinho. Não explique o que é um HTTP request ou uma exception — explique como o seu pacote específico expõe esses dados.

**Estrutura recomendada para o corpo:**

```markdown
# Telescope MCP Debugging

## Overview
Breve descrição do que o pacote faz e como o agente deve usá-lo.

## Available MCP Tools
Lista resumida das tools disponíveis com seus nomes exatos e parâmetros-chave.

## Common Workflows
Padrões de uso mais comuns com exemplos concretos.

## Configuration
Como o pacote é configurado e valores importantes.

## Troubleshooting Patterns
Padrões comuns de debugging que o agente deve reconhecer.
```

---

## 4. Arquivos Auxiliares Recomendados

### 4.1 `references/TOOLS.md`

Referência detalhada de cada uma das 19 MCP tools. O agente só lê este arquivo quando precisa de detalhes sobre uma tool específica. Aqui você pode ser mais verboso:

```markdown
# Telescope MCP Tools Reference

## telescope.requests
Retrieves recent HTTP requests recorded by Telescope.

**Parameters:**
- `limit` (integer, default: 10): Number of requests to return
- `method` (string, optional): Filter by HTTP method (GET, POST, etc.)
- `status` (integer, optional): Filter by status code
...

**Example response:**
```json
{
  "data": [...]
}
```

**When to use:** Use when the user asks about recent HTTP activity...
```

### 4.2 `references/CONFIGURATION.md` (opcional)

Se a configuração for complexa, separe em arquivo próprio:

```markdown
# Telescope MCP Configuration

## Environment Variables
- `TELESCOPE_MCP_ENABLED` (default: true)
- `TELESCOPE_MCP_PATH` (default: "telescope-mcp")
...

## Config File (config/telescope-mcp.php)
...
```

### 4.3 `scripts/` (opcional)

Scripts Python ou Bash que o agente pode executar. Útil para tarefas repetitivas ou que exigem processamento determinístico. O agente executa o script via bash e recebe apenas o output — o código do script NÃO entra no context window.

### 4.4 Arquivos que NÃO devem ser criados

A skill deve conter APENAS o essencial para o agente fazer o trabalho. **NÃO crie:**

- README.md (documentação para humanos)
- CHANGELOG.md
- Arquivos de setup/teste
- Documentação de processos internos
- Arquivos duplicando informação que já está no SKILL.md

---

## 5. Guidelines vs Skills — O que criar para cada caso

| Aspecto | Guidelines | Skills |
|---------|-----------|--------|
| **Quando carrega** | Sempre, upfront | Sob demanda |
| **Onde colocar** | `resources/boost/guidelines/core.blade.php` | `resources/boost/skills/{nome}/SKILL.md` |
| **Escopo** | Convenções gerais, best practices globais | Workflows específicos, referências detalhadas |
| **Tamanho ideal** | Curto e conciso | Pode ter arquivos de referência maiores |
| **Para o telescope-mcp** | Visão geral do pacote, configuração básica | Detalhes das tools, padrões de debug, workflows |

**Recomendação para o seu pacote:** Crie AMBOS:

1. **Guideline** (`resources/boost/guidelines/core.blade.php`): Visão geral de 1-2 parágrafos sobre o que o pacote faz, como instalar/configurar, e que o agente deve usar as MCP tools do Telescope para debugging.

2. **Skill** (`resources/boost/skills/telescope-mcp-debugging/SKILL.md`): Detalhes das tools, workflows de debugging, exemplos de uso.

---

## 6. Plano de implementação — Arquivos a criar

### 6.1 `resources/boost/guidelines/core.blade.php`

```blade
## Laravel Telescope MCP

This application uses `lucianotonet/laravel-telescope-mcp` to expose Laravel Telescope
telemetry data via MCP. When debugging application issues, use the Telescope MCP tools
to inspect HTTP requests, exceptions, logs, slow queries, jobs, and more.

### Quick Reference

- Endpoint: `{{ config('telescope-mcp.path', 'telescope-mcp') }}`
- 19 MCP tools available for real-time telemetry access
- Use `@laravel-telescope-mcp` prefix to invoke tools

### Best Practices

- Always check recent exceptions first when investigating errors
- Use the `queries` tool with `slow: true` to identify N+1 and performance issues
- Cross-reference request IDs across tools for full request lifecycle analysis
```

### 6.2 `resources/boost/skills/telescope-mcp-debugging/SKILL.md`

```yaml
---
name: telescope-mcp-debugging
description: Analyze and debug Laravel applications using Telescope MCP telemetry data including HTTP requests, exceptions, database queries, logs, jobs, mail, notifications, cache operations, redis commands, scheduled tasks, model events, gates, views, and dumps. Use when debugging application issues, investigating slow queries, inspecting HTTP traffic, reviewing error logs, or analyzing any Telescope-recorded data via MCP protocol tools.
---
```

Seguido do corpo com:

- Overview (2-3 linhas)
- Lista das 19 tools com nome exato e descrição de 1 linha cada
- Seção "Common Debugging Workflows" com 3-5 cenários
- Seção "Configuration" com variáveis de ambiente e config
- Referência ao `references/TOOLS.md` para detalhes completos

### 6.3 `resources/boost/skills/telescope-mcp-debugging/references/TOOLS.md`

Documentação detalhada de cada uma das 19 tools:

- `telescope.requests` — HTTP requests
- `telescope.exceptions` — Exception entries
- `telescope.queries` — Database queries (com flag `slow`)
- `telescope.logs` — Log entries (com filtro por `level`)
- `telescope.jobs` — Queued jobs
- `telescope.mail` — Sent emails
- `telescope.notifications` — Notifications
- `telescope.cache` — Cache operations
- `telescope.redis` — Redis commands
- `telescope.schedule` — Scheduled tasks
- `telescope.models` — Model events
- `telescope.gates` — Gate/authorization checks
- `telescope.views` — Rendered views
- `telescope.dumps` — Variable dumps
- `telescope.events` — Application events
- `telescope.batches` — Job batches
- `telescope.commands` — Artisan commands executados
- `telescope.client-requests` — Outgoing HTTP requests
- `telescope.entry` — Entry detail by ID

Para cada tool: parâmetros, response format, exemplo de uso, e quando usar.

---

## 7. Checklist de boas práticas

- [ ] **name** no frontmatter: lowercase, hífens, max 64 chars, sem "claude"/"anthropic"
- [ ] **description** no frontmatter: max 1024 chars, terceira pessoa, com keywords de trigger
- [ ] **SKILL.md body** abaixo de 500 linhas
- [ ] Conteúdo é acionável e concreto (exemplos de código, nomes exatos de tools)
- [ ] Não duplica conhecimento que o LLM já tem (não explica o que é HTTP, SQL, etc.)
- [ ] Referências a arquivos auxiliares estão claras no SKILL.md
- [ ] Nenhum arquivo desnecessário (README, CHANGELOG, docs de processo)
- [ ] Guidelines em `resources/boost/guidelines/core.blade.php`
- [ ] Skills em `resources/boost/skills/{skill-name}/SKILL.md`
- [ ] Testado com `php artisan boost:install` após inclusão no pacote
- [ ] Testado com `php artisan boost:update` para verificar atualização

---

## 8. Como testar

1. **Instale o pacote** em um projeto Laravel com Boost instalado
2. **Rode `php artisan boost:install`** — a skill deve aparecer na lista de seleção
3. **Rode `php artisan boost:update`** — a skill deve ser atualizada
4. **Teste com um agente AI** (Claude Code, Cursor):
   - Peça "debug my slow queries using Telescope" → deve ativar a skill
   - Peça "check the last exceptions in my app" → deve ativar a skill
   - Peça algo não relacionado → NÃO deve ativar a skill
5. **Verifique context usage**: A skill não deve consumir contexto quando não ativada

---

## 9. Como o usuário pode override/customizar

Os usuários do seu pacote podem:

- **Override completo**: Criar `.ai/skills/telescope-mcp-debugging/SKILL.md` no projeto
- **Instalar de GitHub**: `php artisan boost:add-skill lucianotonet/laravel-telescope-mcp`
- **Adicionar skills próprias**: Criar skills complementares em `.ai/skills/`

---

## 10. Referências

- Documentação oficial do Laravel Boost: https://laravel.com/docs/12.x/boost
- Agent Skills format: https://agentskills.io
- Best practices da Anthropic para skills: https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices
- Claude Code Skills docs: https://code.claude.com/docs/en/skills
- Skill Creator (referência de como criar skills): https://github.com/anthropics/skills/blob/main/skills/skill-creator/SKILL.md
- Laravel News sobre Boost v2: https://laravel-news.com/laravel-boost-v2
