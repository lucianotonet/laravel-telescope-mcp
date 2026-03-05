# Análise de Edge Cases: Skills no `laravel-telescope-mcp`

## O fluxo real dos arquivos (entendendo o problema)

Antes de analisar os edge cases, é fundamental entender como cada peça se encaixa:

```
┌─────────────────────────────────────────────────────────────────────┐
│  SEU PACOTE (vendor/lucianotonet/laravel-telescope-mcp/)           │
│                                                                     │
│  resources/boost/skills/telescope-mcp-debugging/SKILL.md           │
│       │                                                             │
│       │  Estes arquivos SÃO INERTES. Nenhum agente os lê           │
│       │  diretamente. São apenas "fonte" para o Boost copiar.      │
│       ▼                                                             │
│  ┌──────────────────────┐    ┌─────────────────────────────────┐   │
│  │  COM Boost instalado │    │  SEM Boost instalado            │   │
│  │                      │    │                                  │   │
│  │  boost:install copia │    │  Arquivos ficam em vendor/       │   │
│  │  para o projeto:     │    │  Nunca são lidos por ninguém.    │   │
│  │                      │    │  Zero overhead. Zero impacto.    │   │
│  │  .ai/skills/         │    │                                  │   │
│  │  CLAUDE.md           │    │  O agente NÃO vê nada do        │   │
│  │  .cursor/rules/      │    │  telescope-mcp automaticamente.  │   │
│  │  etc.                │    │                                  │   │
│  └──────────────────────┘    └─────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
```

**Ponto-chave:** `resources/boost/` é uma convenção do Laravel Boost para auto-discovery de pacotes. Sem o Boost, esses arquivos são tão inertes quanto qualquer outro arquivo estático dentro de `vendor/`.

---

## Edge Case 1: Usuário NÃO usa Laravel Boost

**Situação:** O desenvolvedor usa `laravel-telescope-mcp` com Claude Code, Cursor ou Copilot diretamente, sem Boost.

**O que acontece:** Os arquivos em `resources/boost/skills/` ficam invisíveis. Nenhum agente faz scan dentro de `vendor/`. Os agentes só leem skills dos seguintes caminhos no projeto:

| Agente | Caminhos de discovery |
|--------|----------------------|
| Claude Code | `~/.claude/skills/`, `.claude/skills/` |
| GitHub Copilot | `~/.copilot/skills/`, `.github/skills/`, `.claude/skills/` (legacy) |
| Cursor | `~/.cursor/skills/`, `.cursor/skills/` |
| Codex | `.claude/skills/` |

**Problema:** O agente não terá nenhuma skill sobre telescope-mcp. Ele ainda poderá usar as MCP tools (se o servidor MCP estiver configurado), mas sem guidance de como usá-las de forma eficiente.

**Solução recomendada:** Ofereça um comando artisan para instalar as skills diretamente, sem depender do Boost:

```php
// php artisan telescope-mcp:install-skills
// Copia SKILL.md para .claude/skills/ e/ou .github/skills/
```

Ou documente no README como o usuário pode copiar manualmente:
```bash
# Para Claude Code
mkdir -p .claude/skills/telescope-mcp-debugging
cp vendor/lucianotonet/laravel-telescope-mcp/resources/boost/skills/telescope-mcp-debugging/SKILL.md .claude/skills/telescope-mcp-debugging/

# Para GitHub Copilot
mkdir -p .github/skills/telescope-mcp-debugging
cp vendor/lucianotonet/laravel-telescope-mcp/resources/boost/skills/telescope-mcp-debugging/SKILL.md .github/skills/telescope-mcp-debugging/
```

**Há overhead?** NÃO. Arquivos dentro de `vendor/` que ninguém lê não consomem contexto, não são carregados, não afetam performance.

---

## Edge Case 2: Duplicação de arquivos entre `.ai/skills/` e `.claude/skills/`

**Situação:** O usuário usa Boost (que copia para `.ai/skills/`) E usa Claude Code (que lê de `.claude/skills/`).

**O que acontece no Boost:** Quando roda `boost:install`, o Boost pega o SKILL.md de `resources/boost/skills/` e o instala nos caminhos relevantes para os agentes que o usuário selecionou. O Boost já sabe copiar para `.claude/skills/` (Claude Code), `.cursor/rules/` (Cursor), `.github/skills/` (Copilot), etc., dependendo da escolha durante a instalação.

**Então preciso duplicar?** NÃO. O Boost já faz essa distribuição. Você mantém UMA FONTE em `resources/boost/skills/` e o Boost cuida de distribuir para os destinos certos.

**MAS** se quiser dar suporte a quem NÃO usa Boost, você tem duas opções:

- **Opção A — Comando artisan** (recomendado): Crie um comando que detecta quais agentes o usuário usa e copia para os caminhos corretos.

- **Opção B — Documentação**: Explique no README como copiar manualmente.

---

## Edge Case 3: Mesma skill instalada por dois caminhos diferentes

**Situação:** Usuário instala a skill via Boost (`boost:install`) e também copia manualmente para `.claude/skills/`.

**O que acontece:** O agente carrega a skill duplicada. No Claude Code, se houver conflito de nomes, a prioridade é:

```
enterprise > personal (~/.claude/skills/) > project (.claude/skills/)
```

O Boost instala em `.claude/skills/` (project level). Se o usuário copiar manualmente para o mesmo local, sobrescreve. Se copiar para `~/.claude/skills/` (personal), a versão pessoal ganha prioridade.

**Problema real?** Baixo. No pior caso, a skill aparece duplicada no metadata com nomes diferentes, desperdiçando ~100 tokens de contexto a mais. Não quebra nada.

**Mitigação:** Use o EXATO mesmo `name` no frontmatter. Se ambas tiverem `name: telescope-mcp-debugging`, a de maior prioridade sobrescreve a outra.

---

## Edge Case 4: Versões divergentes da skill

**Situação:** Usuário atualiza o pacote via `composer update` mas não roda `boost:update`.

**O que acontece:** A skill no projeto (`.claude/skills/` ou `.ai/skills/`) fica desatualizada em relação à nova versão em `vendor/`. As instruções podem referenciar tools ou parâmetros que mudaram.

**Mitigação:**
1. Adicione `boost:update` ao `post-update-cmd` do Composer (já documentado no Boost)
2. Considere adicionar um alerta no ServiceProvider se detectar versão desatualizada
3. Mantenha backward compatibility nas instruções da skill

---

## Edge Case 5: Conflito com outra skill de mesmo nome

**Situação:** Outro pacote (ex: `bradleybernard/telescope-mcp`) usa o mesmo `name: telescope-mcp-debugging`.

**O que acontece:** Depende do agente, mas geralmente a última instalada sobrescreve. No Boost, a skill do `.ai/skills/` (custom) tem prioridade sobre a de `resources/boost/skills/` (pacote).

**Mitigação:** Use um nome específico. `telescope-mcp-debugging` é bom. Mas seria mais seguro usar `tonet-telescope-mcp` ou algo que inclua sua identidade se houver preocupação real de conflito.

---

## Edge Case 6: Skill referencia MCP tools que não estão conectadas

**Situação:** O usuário instalou as skills via Boost mas não configurou o MCP server do telescope-mcp.

**O que acontece:** A skill diz ao agente "use a tool `telescope.requests`", mas o agente não tem essa tool disponível. O agente vai tentar usar, falhar, e potencialmente entrar em loop tentando resolver.

**Mitigação:** No SKILL.md, adicione uma seção de pré-requisitos:

```markdown
## Prerequisites
This skill requires the Laravel Telescope MCP server to be running and connected.
If tools return errors, verify:
1. Telescope MCP is enabled: `TELESCOPE_MCP_ENABLED=true`
2. The MCP server is registered in your agent's config
3. The endpoint is accessible: `http://127.0.0.1:8000/telescope-mcp`
```

---

## Edge Case 7: O pacote está em Laravel 10/11 mas Boost requer Laravel 12

**Situação:** Seu pacote suporta Laravel 10+. Mas o Boost é focado em Laravel 12 (embora suporte 10/11).

**O que acontece:** Se o usuário usa Laravel 10 sem Boost, as skills em `resources/boost/` ficam inertes (sem problema). Se usa Laravel 10 COM Boost, funciona normalmente — Boost suporta Laravel 10+.

**Problema real?** Nenhum. Os arquivos `resources/boost/` são arquivos estáticos Markdown. Não dependem de nenhuma versão de framework para existir.

---

## Edge Case 8: Skill muito grande sobrecarrega o contexto

**Situação:** Se você documentar todas as 19 tools detalhadamente no SKILL.md, pode ultrapassar 500 linhas.

**O que acontece:** Quando o agente ativa a skill, ele carrega o SKILL.md inteiro no contexto. Se for muito grande, consome tokens que poderiam ser usados para a conversa.

**Solução já planejada:** A arquitetura de progressive disclosure resolve isso:
- SKILL.md fica enxuto (~200 linhas): overview + lista resumida das tools + workflows comuns
- `references/TOOLS.md` tem os detalhes: o agente só lê quando precisa de parâmetros específicos

---

## Edge Case 9: Usuário usa Copilot (não Claude) — Blade syntax no guideline

**Situação:** As guidelines em `resources/boost/guidelines/core.blade.php` usam syntax Blade (`{{ config(...) }}`). Se o Copilot tentar ler isso diretamente, verá syntax Blade crua.

**O que acontece:** Isto NÃO é problema porque:
1. O Copilot nunca lê de `resources/boost/` diretamente
2. O Boost processa o Blade e gera arquivos finais para cada agente
3. Sem Boost, o arquivo simplesmente não é lido

**Mas para a skill (SKILL.md):** Use Markdown puro, sem Blade. O SKILL.md deve funcionar standalone.

---

## Decisão de arquitetura: O que criar no pacote

Dado todos os edge cases, a estratégia ideal é:

### Camada 1 — Para usuários COM Boost (automático)
```
resources/boost/
├── guidelines/core.blade.php
└── skills/telescope-mcp-debugging/
    ├── SKILL.md
    └── references/TOOLS.md
```
Funciona automaticamente via `boost:install`.

### Camada 2 — Para usuários SEM Boost (comando artisan)
```php
// Comando: php artisan telescope-mcp:install-skills {agent?}
// Detecta agentes disponíveis e copia para os caminhos corretos:
// --claude  → .claude/skills/telescope-mcp-debugging/
// --copilot → .github/skills/telescope-mcp-debugging/
// --cursor  → .cursor/skills/telescope-mcp-debugging/
// --all     → todos os acima
```

O comando copia do `resources/boost/skills/` para o destino. **UMA FONTE, múltiplos destinos.**

### Os arquivos NÃO devem ser duplicados no repositório
Mantenha apenas UM SKILL.md em `resources/boost/skills/telescope-mcp-debugging/`. O comando artisan e o Boost copiam a partir deste mesmo arquivo. Sem duplicação, sem risco de divergência.

---

## Resumo: Tabela de cenários

| Cenário | Funciona? | Overhead? | Ação necessária |
|---------|-----------|-----------|-----------------|
| Com Boost instalado | ✅ Automático | Nenhum | Nenhuma |
| Sem Boost, com Claude Code | ❌ Não automático | Nenhum | Comando artisan ou cópia manual |
| Sem Boost, com Copilot | ❌ Não automático | Nenhum | Comando artisan ou cópia manual |
| Sem Boost, sem agente AI | ✅ Ignora | Nenhum | Nada a fazer |
| Boost + cópia manual (duplicado) | ✅ Funciona | ~100 tokens a mais | Usar mesmo `name` para dedup |
| composer update sem boost:update | ⚠️ Desatualizado | Nenhum | Documentar `post-update-cmd` |
| Skill ativada sem MCP server | ⚠️ Tools falham | Nenhum | Pré-requisitos no SKILL.md |
| Arquivo em vendor/ sem Boost | ✅ Inerte | Zero | Nenhuma |
