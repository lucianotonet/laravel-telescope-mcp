# TODO

## Alta Prioridade

### 1. Padronização da Nomenclatura dos Métodos MCP ✅ (Concluído em 15/05/2024)

**Problema**: Nomes dos métodos têm prefixo inconsistente (`mcp_Laravel_Telescope_MCP_logs`, `mcp_Laravel_Telescope_MCP_http-client`).

**Solução**: Adotar padrão `telescope_mcp.<tool>` para todos os métodos.

**Tarefas**:
- [x] Atualizar `AbstractTool::getName()` para retornar o novo padrão
- [x] Atualizar testes que dependem do nome das ferramentas
- [x] Atualizar documentação e exemplos no README.md
- [x] Adicionar nota de breaking change no CHANGELOG.md

### 2. Reorganizar Documentação das Ferramentas ✅ (Concluído em 15/05/2024)

**Problema**: Lista de ferramentas no README está em formato de parágrafo, dificultando leitura rápida.

**Solução**: Criar tabela organizada com nome, descrição e exemplo de uso.

**Tarefas**:
- [x] Criar seção "Available Tools" no README com tabela
- [x] Agrupar ferramentas por categoria (Debugging, Database, Cache, etc.)
- [x] Adicionar links para exemplos detalhados de cada ferramenta

### 3. Melhorar Documentação do Manifest ✅ (Concluído em 15/05/2024)

**Problema**: Falta documentação clara sobre o manifest que é crucial para integração.

**Solução**: Adicionar seção dedicada com exemplo completo do manifest.

**Tarefas**:
- [x] Criar seção "MCP Manifest" no README
- [x] Incluir exemplo completo do manifest com todas as ferramentas
- [x] Documentar cada campo do manifest
- [x] Adicionar exemplo de como consumir o manifest em um cliente MCP

## Média Prioridade

### 4. Adicionar CHANGELOG ✅ (Concluído em 15/05/2024)

**Problema**: Não há registro formal de mudanças e versões.

**Solução**: Criar CHANGELOG.md seguindo Keep a Changelog.

**Tarefas**:
- [x] Criar CHANGELOG.md na raiz do projeto
- [x] Documentar mudanças da v1.0.0
- [x] Incluir seção de Breaking Changes
- [x] Adicionar link para CHANGELOG no README

### 5. Melhorar Quickstart ✅ (Concluído em 15/05/2024)

**Problema**: Falta um guia rápido de início que mostre valor imediato.

**Solução**: Criar seção Quickstart com exemplo prático completo.

**Tarefas**:
- [x] Criar seção Quickstart no README
- [x] Adicionar exemplo de integração com Cursor
- [x] Incluir screenshots ou GIFs demonstrativos
- [x] Adicionar troubleshooting comum

## Baixa Prioridade

### 6. Adicionar Badges

**Problema**: Faltam indicadores visuais de status do projeto.

**Solução**: Adicionar badges relevantes.

**Tarefas**:
- [ ] Configurar GitHub Actions para CI
- [ ] Adicionar badge de build status
- [ ] Adicionar badge de versão do Packagist
- [ ] Adicionar badge de licença

### 7. Expandir Testes

**Problema**: Cobertura de testes pode ser melhorada.

**Solução**: Adicionar mais testes e melhorar cobertura.

**Tarefas**:
- [ ] Adicionar testes para cada ferramenta
- [ ] Testar diferentes formatos de data
- [ ] Adicionar testes de integração
- [ ] Configurar e adicionar badge de cobertura de código

## Known Issues / Testing Notes

*   **PruneTool (`prune`) Testing**: The `PruneTool` relies on `php artisan telescope:prune`. Simulated tests show the tool is called correctly, but the Artisan command fails due to the simulation environment lacking a full Laravel setup. **Action**: Ensure to test this tool thoroughly in a complete Laravel development/staging environment to confirm the `exec('php artisan telescope:prune ...')` call functions as expected and interacts correctly with the database. 