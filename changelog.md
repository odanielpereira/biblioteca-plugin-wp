# Changelog

Histórico completo e detalhado de todas as atividades, modificações e decisões do projeto.

---

## Instruções para Leitura e Atualização (Máquina e Humano)

1.  **Cada entrada deve conter:** Data, Ação, Detalhes, Ferramenta utilizada e Decisão tomada (se houver).
2.  **Formato sequencial:** A entrada mais recente recebe o número mais alto. Nenhum número pode ser reutilizado ou reordenado.
3.  **Proibido apagar entradas:** Mesmo que uma ação seja revertida, registre a reversão como uma nova entrada. O histórico é imutável.
4.  **Regra de Ouro:** Antes de propor ou implementar qualquer código, o LLM deve ler este arquivo para verificar o histórico consolidado e evitar duplicação de funções ou regressão de decisões já tomadas.
5.  **Ao concluir uma fase do `roadmap.md`:** Registre aqui com o número da fase e o critério de saída validado.
6.  **Ao atualizar qualquer documento da pirâmide (`claude.md`, `escopo.md`, `roadmap.md`):** Registre aqui imediatamente, citando o documento alterado e o motivo.
7.  **Este arquivo é a âncora da realidade.** Se houver conflito entre o que o LLM "acha" e o que está aqui, este arquivo prevalece.

---

## Log de Atividades

**1 - Data:** 2026-05-29
- **Ação:** Criação do arquivo `escopo.md` (versão 1 — Gemini).
- **Detalhes:** Documenta os requisitos essenciais, a funcionalidade principal, o controle de acesso e a abordagem técnica proposta para o plugin. Versão genérica, sem colunas de banco ou obrigações de limpeza explicitadas.
- **Ferramenta:** `write_file`

**2 - Data:** 2026-05-29
- **Ação:** Criação do arquivo `changelog.md`.
- **Detalhes:** Estabelece o log de mudanças para rastrear todas as atividades subsequentes do projeto.
- **Ferramenta:** `write_file`

**3 - Data:** 2026-05-29
- **Ação:** Criação do arquivo `roadmap.md` (versão 1 — Gemini).
- **Detalhes:** Delineia as fases de desenvolvimento do projeto (Fase 0 a Fase 4). Fase 0 marcada como concluída prematuramente.
- **Ferramenta:** `write_file`

**4 - Data:** 2026-05-29
- **Ação:** Revisão do formato do `changelog.md`.
- **Detalhes:** Alteração do formato de lista para uma sequência numérica para maior clareza e precisão no rastreamento de cada ação.
- **Ferramenta:** `write_file`

**5 - Data:** 2026-05-29
- **Ação:** Correção e atualização do `roadmap.md`.
- **Detalhes:** Marcou a tarefa "Definição do Roadmap" como concluída e removeu referências a documentos não solicitados (`arquitetura.md`, `padroes-de-codigo.md`). **Decisão:** Apenas os documentos explicitamente solicitados pelo usuário devem existir.
- **Ferramenta:** `write_file`

**6 - Data:** 2026-05-29
- **Ação:** Criação do arquivo `claude.md`.
- **Detalhes:** Adiciona ao projeto um arquivo com diretrizes comportamentais para reduzir erros comuns de codificação, traduzido para o português. Inclui o Princípio 5 com a hierarquia de documentos.
- **Ferramenta:** `write_file`

**7 - Data:** 2026-05-30
- **Ação:** Revisão estrutural do `escopo.md` (versão 2 — engenharia de contexto).
- **Detalhes:** Substituição do escopo genérico por versão com: prefixo `bm_` para CPT e meta keys, lista de 8 funções WordPress obrigatórias com links para developer.wordpress.org, estrutura de dados explicitada (`wp_posts` e `wp_postmeta`), permissões via `manage_options`, comportamento de exclusão via lixeira, obrigações de limpeza (activation/deactivation/uninstall hooks), e 7 barreiras negativas explícitas.
- **Ferramenta:** `write_file`
- **Decisão:** Esta versão do escopo é a base imutável para todas as fases subsequentes. Qualquer alteração deve ser registrada neste changelog.

**8 - Data:** 2026-05-30
- **Ação:** Revisão da Fase 1 do `roadmap.md` (versão 2).
- **Detalhes:** Fase 1 reescrita com: tarefas reduzidas de 8 para 6, critérios de verificação por tarefa, inclusão de `register_activation_hook()` e `register_deactivation_hook()`, critério de saída explícito. Fase 0 mantida como `[x]` somente após validação do novo escopo.
- **Ferramenta:** `write_file`
- **Decisão:** A Fase 1 começa com o CPT `bm_book`, não `book`.

**9 - Data:** 2026-05-30
- **Ação:** Atualização do `claude.md`.
- **Detalhes:** Adicionada cláusula de fallback para documentos ausentes ou vazios na pirâmide, protocolo de resolução de conflito entre `changelog.md` e `roadmap.md` (em caso de conflito, `changelog.md` prevalece por ser a âncora da realidade), e exemplos de prolixidade proibida.
- **Ferramenta:** `write_file`
- **Decisão:** O `changelog.md` tem precedência sobre o `roadmap.md` em caso de divergência sobre o estado atual do projeto.

**10 - Data:** 2026-05-30
- **Ação:** Atualização do próprio `changelog.md` (versão 2).
- **Detalhes:** Adicionada seção de "Instruções para Leitura e Atualização" com 7 regras, adicionadas entradas 7, 8, 9 e 10, adicionado campo de data em todas as entradas existentes.
- **Ferramenta:** `write_file`
- **Decisão:** Este changelog agora segue o próprio padrão que estabelece. As instruções são parte integrante do arquivo.

**11 - Data:** 2026-05-30
- **Ação:** Atualização do `claude.md` — adição da Cláusula de Fallback.
- **Detalhes:** Inserida a Cláusula de Fallback no Princípio 5 com 3 sub-regras: declarar documento ausente, prosseguir com disponíveis, proibição de alucinar conteúdo faltante. As outras duas melhorias previamente planejadas (protocolo de conflito changelog vs roadmap e exemplos de prolixidade) não foram implementadas por decisão do usuário.
- **Ferramenta:** `write_file`
- **Decisão:** A entrada 9 deste changelog fica parcialmente retificada. Apenas a cláusula de fallback foi adicionada. As demais melhorias mencionadas na entrada 9 não ocorreram e não são mais requeridas.