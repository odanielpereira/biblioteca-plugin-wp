# Roadmap

## Ciclo 1 — Versão 1.0.0 ← CONCLUÍDO

### Fase 0: Planejamento e Estrutura de Governança ← FASE CONCLUÍDA
### Fase 1: Estrutura Base e Custom Post Type (CPT) ← FASE CONCLUÍDA
### Fase 2: Metaboxes e Campos Personalizados ← FASE CONCLUÍDA
### Fase 4: Interface de Listagem e Visualização ← FASE CONCLUÍDA
### Fase 5: Desativação, Desinstalação e Limpeza ← FASE CONCLUÍDA

---

## Ciclo 2 — Versão 2.0.0 ← CONCLUÍDO

### Fase 6: Importação e Exportação CSV ← FASE CONCLUÍDA
*   **Fase 6A — Importação CSV** ✅
*   **Fase 6B — Exportação CSV** ✅
*   **Fase 6C — Ajustes de Usabilidade** ✅

---

## Ciclo 3 — Versão 3.0.0 ← CONCLUÍDO

### Fase 7: Expansão da Ficha Catalográfica ← FASE CONCLUÍDA
*   **Fase 7A — Campos Fixos de Catalogação** ✅
*   **Fase 7B — Campos Dinâmicos** ✅
*   **Fase 7C — Taxonomias** ✅
*   **Fase 7D — Capa do Livro** ✅
*   **Fase 7E — Exportação Flexível** ✅
*   **Fase 7F — Soft Delete e Auditoria** ✅
*   **Fase 7G — Mapeamento Dinâmico de Colunas** ✅
*   **Fase 7H — Gerenciamento de Campos** ✅

---

## Ciclo 4 — Versão 4.0.0 ← CONCLUÍDO

### Fase 8: Vitrine Pública e Página do Livro ← FASE CONCLUÍDA
*   **Fase 8A — Tornar CPT Público** ✅
*   **Fase 8B — Página Individual do Livro (Single)** ✅
*   **Fase 8C — Página de Catálogo (Archive)** ✅
*   **Fase 8C-B — Correções Cirúrgicas** ✅
*   **Fase 8D — Filtros Inteligentes na Vitrine** ✅ (MVP parcial)
*   **Fase 8E — Vitrine Visual** ✅
*   **Fase 8F — Busca Automática de Sinopse** ✅
*   **Fase 8G — Classificação Interdisciplinar por IA** ✅ (substituída pela 11A-B)

---

## Ciclo 5 — Versão 5.0.0 ← CONCLUÍDO

### Fase 9: Usuários, Reservas e Empréstimos ← FASE CONCLUÍDA
*   **Fase 9A — Perfis de Usuário (Roles Customizadas)** ✅
*   **Fase 9B — Autocadastro e Aprovação** ✅
*   **Fase 9C — Sistema de Reservas** ✅
*   **Fase 9D — Empréstimos e Devoluções** ✅
*   **Fase 9E — Controle de Estoque Matemático** ✅
*   **Fase 9F — Integração com WhatsApp** ✅
*   **Fase 9G — Dashboards por Perfil** ✅
*   **Fase 9H — Modularização** ✅

---

## Ciclo 6 — Versão 6.0.0 ← CONCLUÍDO

### Fase 10: Gamificação e Engajamento ← FASE CONCLUÍDA

#### Fase 10A — Ranking de Leitores ✅
#### Fase 10B — Ficha de Leitura ✅
#### Fase 10C — Vídeo-Resenha e Resenha Oficial ✅
#### Fase 10D — XP e Medalhas (Badges) ✅
#### Fase 10E — Central de APIs e Configurações ✅

---

## Ciclo 7 — Versão 7.0.0 ← CONCLUÍDO

### Fase 11: Ferramentas Pedagógicas ← FASE CONCLUÍDA
*   **Objetivo:** Fornecer ferramentas de apoio pedagógico: gerador de atividades, classificação por disciplina, CDU/Cutter, chatbot e geração de etiquetas.
*   **Critério de saída:** Professores geram atividades por IA. Livros classificados por disciplina automaticamente. CDU/Cutter atribuídos. Etiquetas podem ser impressas. Chatbot responde sobre o acervo.

#### Fase 11A — Gerador de Atividades por IA ← CONCLUÍDA
*   **Tarefas:**
    1.  [x] Botão "Gerar Atividades" na edição e na vitrine do livro.
    2.  [x] Integração com Groq (Llama 3.3 70B Versatile).
    3.  [x] Atividades salvas em _bm_activities com cache.
    4.  [x] Exibição na metabox e na página pública do livro.
    5.  [x] Acesso: Professor, Gestor e Admin.

#### Fase 11A-B — Classificação por Disciplina com IA ← CONCLUÍDA
*   **Tarefas:**
    1.  [x] Função bm_classify_book_with_ai() reescrita para Groq.
    2.  [x] Análise binária por disciplina (Sim/Não) com justificativas.
    3.  [x] Disciplinas marcadas via wp_set_post_terms().
    4.  [x] Justificativas salvas em _bm_discipline_justifications.
    5.  [x] Exibição na página do livro (pills + seção de justificativas).
    6.  [x] Integração na importação CSV com checkbox.

#### Fase 11B — CDU e Cutter
*   **Descrição:** Classificação catalográfica automatizada por IA.
*   **Tarefas:**
    1.  [x] Botão "Gerar Número de Chamada" na edição do livro.
    2.  [x] IA sugere Classificação (CDU/CDD) baseado no título + sinopse + gênero.
    3.  [x] Cálculo automático da Tabela Cutter-Sanborn (autor + título).
    4.  [x] Campos: `_bm_cdu` (Classificação) e `_bm_cutter` (Cutter).
    5.  [x] Cache de resultados (`_bm_cutter_cached`), bloqueio de edição e histórico com restauração.
    6.  [x] Integração na importação CSV com prioridade: CSV > IA > Manual.

#### Fase 11C — Geração de Etiquetas
*   **Descrição:** Impressão de etiquetas para lombada dos livros.
*   **Tarefas:**
     **Tarefas:**
    1.  [x] Página "Etiquetas" no menu Livros.
    2.  [x] Selecionar livros via checkboxes com filtros.
    3.  [x] Gerar folha A4 com etiquetas formatadas (3 colunas × 8 linhas).
    4.  [x] Layout: autor, título, classificação, cutter, edição, exemplar, código de barras.
    5.  [x] CSS @media print para impressão direta.
    6.  [x] Carrinho persistente via sessão PHP.
    7.  [x] Botão "Adicionar etiqueta" na página individual do livro.
    8.  [x] Suporte a múltiplos exemplares (Ex. 1/56).

#### Fase 11E — Chatbot da Biblioteca ← CONCLUÍDA
*   **Tarefas:**
    1.  [x] Botão flutuante 💬 no canto inferior direito do site.
    2.  [x] Integração com Groq para responder sobre o acervo.
    3.  [x] Prompt inclui catálogo com títulos, autores, localização e disponibilidade.
    4.  [x] Funciona para visitantes e logados via AJAX.
    5.  [x] Não revela dados pessoais de alunos.

---

## Ciclo 8 — Versão 8.0.0 ← CONCLUÍDO

### Fase 12: Infraestrutura e Configurações
*   **Objetivo:** Tornar o plugin configurável, adaptável a qualquer escola e preparado para virada de ano letivo.
*   **Critério de saída:** Escola configura nome, logo, cores, limites e prazos. Virada de ano letivo funcional. Código limpo e permissoes revisadas.

#### Fase 12A — Página de Configurações
*   **Descrição:** Central de configurações do plugin.
*    **Tarefas:**
    1.  [x] Subpágina "Configurações" no menu Livros (acesso: Admin).
    2.  [x] Campos: limites de reservas por aluno, máximo de empréstimos, prazo padrão de empréstimo (dias), prazo de reserva (horas).
    3.  [x] Salvar como get_option('bm_settings').
    4.  [x] Integrar com funções de reserva, empréstimo e dashboard.
    5.  [x] Verificar estoque ao confirmar empréstimo.
    6.  [x] Botão "Rejeitar" reserva + colunas de posição e estoque.

#### Fase 12B — White Label
*   **Descrição:** Personalização da identidade visual da escola.
*   **Tarefas:**
    1.  [x] Checkbox de ativar/desativar identidade visual.
    2.  [x] Campo: nome da escola (substitui "Catálogo de Livros").
    3.  [x] Upload de logo (exibida no header do catálogo).
    4.  [x] Texto do rodapé e URL do site da escola.
    5.  [x] Integração com archive e single.

#### Fase 12C — Virada de Ano Letivo
*   **Descrição:** Botão para resetar dados na virada do ano.
*   **Tarefas:**
    1.  [x] Toggle ativar/desativar sistema de virada.
    2.  [x] Data configurável (mês/dia) para qualquer hemisfério.
    3.  [x] Checkboxes independentes: resetar XP, resetar medalhas.
    4.  [x] Limpar reservas pendentes.
    5.  [x] Ativar recadastramento de alunos (apenas bm_student).
    6.  [x] Backup automático dos rankings antes da virada.
    7.  [x] Seção "Limpeza de Histórico" com modal de confirmação.
    8.  [x] Checkboxes: fichas, resenhas, vídeos, avaliações, empréstimos.
    9.  [x] Filtro por ano na limpeza de histórico.
    10. [x] Exportar dados dos alunos via CSV.
    11. [x] Log de viradas (bm_year_transition_log).
    12. [x] Confirmação dupla antes de executar.

#### Fase 12D — Limpeza de Código Morto → MOVIDO PARA CICLO DE POLIMENTO
*   **Descrição:** Remover funções não utilizadas identificadas no documento POSSÍVEIS LIXOS.
*   **Tarefas:**
    1.  [ ] Remover bloco `// FASE 8G` (versão Gemini).
    2.  [ ] Remover função `bm_deepseek_request()`.
    3.  [ ] Remover `bm_super_admin` de `bm_register_roles()`, `bm_remove_roles()` e `bm_get_user_role()`.
    4.  [ ] Remover ou manter como fallback constantes do wp-config.
    5.  [ ] Testar todas as funcionalidades após remoção.

#### Fase 12E — Refinamentos de Sistema ← CONCLUÍDA
*   **Descrição:** Centralizar menu, revisar permissões, criar taxonomias dinâmicas e limites configuráveis.
*   **Tarefas:**
    1.  [x] Centralizar menu de administração (menu principal "Biblioteca").
    2.  [x] Criador de Taxonomias Dinâmicas: gestor cria suas próprias taxonomias via interface.
    3.  [ ] Configuração de limites por perfil: máximo de reservas e empréstimos por aluno. → MOVIDO PARA CICLO DE POLIMENTO
    4.  [x] Limpar roles sujas (gestor_biblioteca, aluno, professor) na ativação.
    5.  [ ] Revisão de permissões: substituir `manage_options` por capabilities granulares. → MOVIDO PARA CICLO DE POLIMENTO COM ESCOPO EXPANDIDO (Interface de permissões do Gestor)
    6.  [x] Seletor CDU ou CDD na central de configurações.
    7.  [x] Visibilidade configurável de campos administrativos por perfil.

#### Fase 12F — Status e Diagnóstico → MOVIDO PARA CICLO DE POLIMENTO
*   **Descrição:** Páginas de status do sistema e logs.
*   **Tarefas:**
    1.  [ ] Página de Status: versão do plugin, PHP, WordPress, memória, chaves API.
    2.  [ ] Contador de chamadas API (Groq) com estatísticas de uso.
    3.  [ ] Logs de erro e diagnóstico.

#### Fase 12G — Campos Dinâmicos para Alunos ← CONCLUÍDA
*   **Descrição:** Adaptar o gerenciador de campos dinâmicos para suportar também metadados de usuário (user_meta).
*   **Tarefas:**
    1.  [x] Interface unificada com abas: "Campos de Livros" e "Campos de Alunos".
    2.  [x] Prefixo `_bm_user_` para campos dinâmicos de alunos.
    3.  [x] Mesmos tipos: texto curto, texto longo.
    4.  [x] Drag and drop, renomear, ocultar/mostrar.
    5.  [x] Nenhum campo fixo obrigatório — gestor define tudo.
    6.  [x] Campos pré-instalados na ativação: Nome completo, E-mail, Telefone, Série/Ano, Turno, Turma.

#### Fase 12H — Importação de Alunos em Massa ← CONCLUÍDA
*   **Descrição:** Página de importação de alunos via CSV com mapeamento dinâmico de colunas.
*   **Tarefas:**
    1.  [x] Subpágina "Importar Alunos" no menu Biblioteca.
    2.  [x] Upload de CSV com mapeamento dinâmico (igual ao de livros).
    3.  [x] Colunas mapeáveis: campos dinâmicos (_bm_user_*).
    4.  [x] Criação automática de usuários com role `bm_student`.
    5.  [x] Status: "approved" (direto) ou "pending" (aguardando aprovação).
    6.  [x] Detecção de duplicados por e-mail.
    7.  [x] Relatório: X importados, Y ignorados, Z duplicados.

#### Fase 12I — Dashboard e Cadastro de Alunos ← CONCLUÍDA
*   **Descrição:** Exibir campos dinâmicos no dashboard do aluno e no formulário de autocadastro.
*   **Tarefas:**
    1.  [x] Dashboard do aluno exibe campos dinâmicos preenchidos.
    2.  [x] Shortcode `[bm_register]` atualizado: perfil primeiro, campos dinâmicos condicionais, trava de recadastramento.
    3.  [x] Página de edição de aluno no admin (dados nativos + campos dinâmicos + histórico).
    4.  [x] Professor vê dados do aluno em modo leitura.
    5.  [x] Busca rápida de livros no dashboard do aluno.

#### Fase 12J — Administração de Alunos ← CONCLUÍDA
*   **Descrição:** Interface completa para Admin e Gestor gerenciarem alunos.
*   **Tarefas:**
    1.  [x] Subpágina "Alunos" com listagem (tabela com colunas customizáveis).
    2.  [x] Filtros por grupo, status, turno, série, atraso.
    3.  [x] Ações em lote: aprovar, suspender, excluir.
    4.  [x] Página individual do aluno (dados + campos dinâmicos + histórico de leitura/XP/medalhas).
    5.  [x] Exportar histórico do aluno (CSV).
    6.  [x] Indicador visual de pendências + WhatsApp + observações internas + bloqueio por atraso.

#### Fase 12K — Atendimento (Empréstimo Rápido no Balcão) ← CONCLUÍDA
*   **Descrição:** Tela de atendimento físico para Gestor/Admin realizar empréstimos e devoluções rapidamente.
*   **Tarefas:**
    1.  [x] Subpágina "Atendimento" no menu Biblioteca.
    2.  [x] Campo de busca de livro com autocomplete e exibição de disponibilidade em tempo real.
    3.  [x] Indicador visual "Consulta local".
    4.  [x] Campo de busca de aluno com autocomplete e status (pendências, livros ativos, limite).
    5.  [x] Modal de cadastro/edição rápido de aluno na mesma tela.
    6.  [x] Botão "Emprestar" com verificação de regras (limite, prazo, consulta local, bloqueio por atraso).
    7.  [x] Botão "Devolver" com registro de danos.
    8.  [x] Botão "Renovar" (+7 dias).
    9.  [x] Suporte a leitor de código de barras (campo com foco automático para ISBN).
    10. [x] Histórico rápido do aluno (últimos 3 livros).
    11. [x] Cadastro de livro por ISBN via Google Books API.
    12. [x] Fila de espera visível.


## Ciclo 9 — Versão 9.0.0 ← CICLO DE POLIMENTO EM ANDAMENTO

### Fase 14 (Polimento) — Limpeza de Código Morto
*   **Descrição:** Remover código obsoleto e funções não utilizadas.
*   **Tarefas:**
    1.  [x] Remover bloco FASE 8G (versão Gemini).
    2.  [x] Remover função bm_deepseek_request().
    3.  [x] Remover bm_super_admin de bm_register_roles(), bm_remove_roles() e bm_get_user_role().
    4.  [x] Remover ou manter como fallback constantes do wp-config.
    5.  [ ] Testar todas as funcionalidades após remoção.
    6.  [x] Varredura completa de código órfão: funções não chamadas, hooks sem callback, options não utilizadas.
    7.  [x] Atualizar versão no cabeçalho de book-manager.php de 1.0.0 para 8.0.0.

### Fase 15 (Polimento) — Performance, Auditoria e uninstall
*   **Descrição:** Otimizar performance, expandir logs e ajustar desinstalação.
*   **Tarefas:**
    1.  [ ] Tornar uninstall.php autocontido. → MOVIDO PARA CICLO DE PÓS-POLIMENTO
    2.  [x] Otimizar performance: cache de queries repetidas (transients), paginação.
    3.  [x] Expandir auditoria para ações de alunos: aprovar, suspender, excluir (log de quem fez o quê).

### Fase 16 (Polimento) — Gerenciar Campos e Taxonomias
*   **Descrição:** Refinar gerenciador de campos dinâmicos e unificar classificações.
*   **Tarefas:**
    1.  [x] Corrigir ordem do drag and drop que às vezes sai do lugar ao recarregar a página. (✅ concluído)
    2.  [x] Permitir que campos fixos (ISBN, Localização, Exemplares) sejam removíveis/ocultáveis.
    3.  [x] Campos dinâmicos conforme perfil (Aluno: série/ano; Professor: disciplinas).
    4.  [x] Unificar campo de Classificação: bloquear criação de campo dinâmico "CDU", "CDD" ou "Classificação".
    5.  [x] Ordem dos campos dinâmicos no modal de cadastro/edição do Atendimento não reflete drag and drop.

### Fase 17 (Polimento) — Status, Diagnóstico e Configurações
*   **Descrição:** Painel de controle do sistema e configurações avançadas.
*   **Tarefas:**
    1.  [x] Página de Status: versão do plugin, PHP, WordPress, memória, chaves API configuradas.
    2.  [x] Contador de chamadas API (Groq) com estatísticas de uso.
    3.  [x] Logs de erro e diagnóstico.
    4.  [x] Configuração de limites por perfil: máximo de reservas e empréstimos por aluno.
    5.  [x] Interface de permissões do Gestor: Admin marca quais funcionalidades o Gestor pode acessar.

### Fase 18 (Polimento) — Listagem, Menu e Usabilidade
*   **Descrição:** Corrigir bugs de interface e organizar navegação.
*   **Tarefas:**
    1.  [x] Diagnosticar e corrigir bulk action quebrado (mover vários livros para lixeira).
    2.  [x] Organizar menu Biblioteca no wp-admin: unir submenus relacionados em abas/telas unificadas.

### Fase 19 (Polimento) — Importação e Exportação CSV
*   **Descrição:** Melhorias no fluxo de importação/exportação de dados.
*   **Tarefas:**
    1.  [x] Checkbox individuais para cada funcionalidade da Google Books API com aviso de impacto na velocidade.
    2.  [ ] Importação assíncrona para grandes arquivos (evitar timeout). → MOVIDO PARA CICLO DE PÓS-POLIMENTO
    3.  [ ] Melhorar detecção de título/autor (evitar que autor vire parte do título em snippets). → MOVIDO PARA CICLO DE PÓS-POLIMENTO
    4.  [x] Aviso de sucesso pós-download na exportação ("X livros exportados").
    5.  [ ] Seleção individual de duplicados com checkbox na importação. → MOVIDO PARA CICLO DE PÓS-POLIMENTO
    6.  [x] Relatório visual de importação com status colorido (verde/vermelho/amarelo/cinza).
    7.  [x] Importação CSV com coluna de URL de vídeo-resenha.
    8.  [x] Importação dedicada de Número de Chamada via CSV para bibliotecas em migração.
    9.  [ ] Barra de progresso na importação CSV com animação CSS. → MOVIDO PARA CICLO DE PÓS-POLIMENTO

### Fase 20 (Polimento) — Capas, Filtros, IA e APIs ← CONCLUÍDA
- **Descrição:** Refinar integração com Google Books, Groq e ChatGPT.
- **Tarefas:**
    1. [x] Avaliar opção de hotlink vs download local para economizar espaço.
    2. [x] Ajustar responsividade das capas no archive.
    3. [x] Corrigir cruzamento de filtros no archive nativo (/livros/).
    4. [x] Refatorar constantes do wp-config para usar central de APIs.
    5. [x] Refinar prompt da classificação por IA: respostas mais detalhadas e lúdicas.
    6. [x] Configurar persona/tom da IA na central de APIs.
    7. [x] Configurações do Chatbot: ativar/desativar.
    8. [ ] Preenchimento automático via ISBN → MOVIDO PARA PÓS-POLIMENTO
    9. [ ] Buscar avaliação do Google Books → MOVIDO PARA PÓS-POLIMENTO
    10. [ ] Livros relacionados via Google Books → MOVIDO PARA PÓS-POLIMENTO

### Fase 21 (Polimento) — Páginas Públicas (archive e single)
*   **Descrição:** Refinar layout e exibição pública dos livros.
*   **Tarefas:**
    1.  [x] Placeholder para capas quebradas ou ausentes no single. (✅ concluído)
    2.  [ ] Layout visual das páginas públicas (aplicar protótipo do Stitch).
    3.  [ ] Exibir resenhas aprovadas na página individual do livro.

### Fase 22 (Polimento) — Central de Exportar/Importar Tudo
*   **Descrição:** Interface unificada para exportação e importação completa de dados.
*   **Tarefas:**
    1.  [ ] Subpágina "Exportar/Importar Dados" com abas Exportar e Importar.
    2.  [ ] Exportar: checkboxes para módulos (livros, alunos, histórico, fichas, taxonomias, configurações) ou "Tudo". ZIP com CSVs ou CSV único. Opção XML.
    3.  [ ] Importar: upload de ZIP ou CSVs individuais com mapeamento dinâmico.

### Fase 23 (Polimento) — Sistema de Multas ← CONCLUÍDA
- **Descrição:** Sistema configurável de penalidades por atraso.
- **Tarefas:**
    1. [x] Página "Regras de Multa" nas Configurações: tipo, duração/valor, progressão.
    2. [x] Cálculo automático ao devolver com atraso (bm_calculate_penalty).
    3. [x] Histórico de multas na página individual do aluno.
    4. [x] Bloqueio automático se multa ativa (bm_check_penalty_block).
    5. [x] Notificação de multa via WhatsApp.
    6. [x] Penalidade manual individual pelo Gestor.
    7. [x] Descrições nas penalidades e anotações de devolução.
    8. [x] Dashboard do aluno exibe "Minhas Ocorrências".

### Fase 24 (Polimento) — Empréstimos, Reservas e WhatsApp ← CONCLUÍDA
- **Descrição:** Refinar fluxo de circulação e notificações.
- **Tarefas:**
    1. [x] Melhorar interface de reserva para Professor/Gestor (dropdown de alunos).
    2. [x] Melhorar clareza visual dos números de estoque (barra de progresso).
    3. [ ] Refinar contador regressivo: notificações automáticas por e-mail. → MOVIDO PARA PÓS-POLIMENTO
    4. [x] Contador de mensagens WhatsApp enviadas por empréstimo.
    5. [x] Corrigir bug: aluno sem limites de reserva.
    6. [ ] Refinar monitoramento do Professor. → MOVIDO PARA PÓS-POLIMENTO
    7. [ ] Notificação por e-mail: lembrete de devolução, reserva disponível, multa. → MOVIDO PARA PÓS-POLIMENTO
    8. [x] Renovação online pelo aluno sem intervenção do Gestor.
    9. [x] Bloquear reserva para aluno com atraso (modal explicativo).
    10. [x] Fila de espera notifica automaticamente próximo aluno via WhatsApp.
    11. [x] Devolver livro na página de detalhes do aluno (sincronização bidirecional).
    12. [x] Filtro de busca textual na página de empréstimos.
    13. [x] Links na tabela de empréstimos (livro e aluno).
    14. [x] Todos os botões de empréstimos convertidos para AJAX.

### Fase 25 (Polimento) — Funcionalidades para Biblioteca Escolar
*   **Descrição:** Recursos específicos para o contexto escolar brasileiro.
*   **Tarefas:**
    1.  [x] Reserva antecipada para professor.
    2.  [ ] Lista de leitura obrigatória. → MOVIDO PARA PÓS-POLIMENTO
    3.  [ ] Relatório de turma. → MOVIDO PARA FASES 31/32
    4.  [x] Painel de aniversariantes.
    5.  [ ] Empréstimo entre bibliotecas.  → MOVIDO PARA PÓS-POLIMENTO

### Fase 26 (Polimento) — Funcionalidades para Qualquer Biblioteca
*   **Descrição:** Recursos universais para qualquer tipo de biblioteca.
*   **Tarefas:**
    1.  [x] Sugestão de aquisição.
    2.  [ ] Catálogo público com busca avançada expandida. → MOVIDO PARA PÓS-POLIMENTO
    3.  [x] Integração com redes sociais.
    4.  [ ] Modo acessibilidade. → MOVIDO PARA PÓS-POLIMENTO
    5.  [ ] API pública do acervo. → MOVIDO PARA PÓS-POLIMENTO
    6.  [ ] Estatísticas de uso. → MOVIDO PARA FASE 31
    7.  [ ] Checklist de inventário. → MOVIDO PARA PÓS-POLIMENTO

### Fase 27 (Polimento) — Dashboards, Perfis e Gamificação
*   **Descrição:** Refinar painéis de controle e sistema de engajamento.
*   **Tarefas:**
    1.  [x] Substituir todos os alert() restantes por modal.
    2.  [x] Dashboard de leitura com seletor de período.
    3.  [x] Ranking no dashboard do aluno: exibir posição e comparação.
    4.  [x] Filtros configuráveis no ranking.
    5.  [x] Perfil público do leitor.
    6.  [x] Vitrine de resenhas no perfil público do aluno.
    7.  [x] Curadoria de resenhas na página do livro.
    8.  [x] Ranking de livros: shortcode [bm_top_books].
    9.  [ ] Dashboard enriquecido com relatórios e gráficos. → MOVIDO PARA PÓS-POLIMENTO (Stitch)
    10. [ ] Design system para dashboards: cards interativos, gráficos, ícones. → MOVIDO PARA PÓS-POLIMENTO (Stitch)
    11. [x] Exibir XP ganho por ficha na seção "Minhas Fichas".
    12. [x] Gestor/Admin definir XP manualmente ao aprovar ficha.
    13. [x] Exibir XP na seção Minhas Fichas.
    14. [x] Link "Minhas Fichas" no dashboard do aluno.
    15. [x] Duplicação de Nome e E-mail na edição nativa de usuário..

### Fase 28 (Polimento) — Vídeo e Embed
*   **Descrição:** Suporte a vídeos e correções de embed.
*   **Tarefas:**
    1.  [x] Vídeo-resenhas na página do livro via importação CSV.
    2.  [ ] Suporte a Instagram Reels no embed. → REMOVIDO DO ESCOPO
    3.  [ ] Corrigir embed de TikTok e Instagram: altura, largura, scrollbar. → REMOVIDO DO ESCOPO

### Fase 29 (Polimento) — Etiquetas e Número de Chamada
*   **Descrição:** Refinar impressão e catalogação.
*   **Tarefas:**
    1.  [x] Reordenação configurável das linhas do Número de Chamada.
    2.  [x] Otimizar layout da folha A4: reduzir margem para 27 etiquetas.

### Fase 30 (Polimento) — Página de Instalação e Identidade Visual
*   **Descrição:** Primeiro acesso e personalização inicial.
*   **Tarefas:**
    1.  [ ] Página de instalação (primeiro acesso): obriga criação do Super Admin + nome da escola. → REMOVIDO DO ESCOPO
    2.  [x] Página de configurações para API Keys.

### Fase 31 (Polimento) — Sistema de Relatórios
*   **Descrição:** Motor completo de relatórios configuráveis com visualização e exportação.
*   **Tarefas:**
    1.  [x] Motor de relatórios: função central `bm_generate_report()` com parâmetros configuráveis.
    2.  [x] Interface de relatórios: subpágina "Relatórios" com seletores (tipo, período, sujeito, filtros).
    3.  [x] Relatórios pré-definidos: Desempenho do Aluno, Leitura por Turma, Visão Geral, Multas Ativas, Ranking por Gênero, Livro Mais Emprestado, Tendência de Leitura, Relatório Configurável.
    4.  [x] Visualização em tela: tabelas + gráficos CSS puro (barras, pizza, linhas).
    5.  [x] Relatório configurável pelo usuário: montagem de campos e filtros.
    6.  [x] Relatório de turma: `bm_get_class_report($group, $period)`. → MOVIDO DA FASE 25
    7.  [x] Estatísticas de uso: `bm_get_library_stats($period)` com cache em `bm_stats_cache`. → MOVIDO DA FASE 26
    8.  [x] Exportação para PDF via window.print() (nova aba formatada).
    9.  [x] Exportação CSV removida do escopo — já existe em outros módulos.

### Fase 32 (Polimento) — Detalhamento do Empréstimo e Aluno
*   **Descrição:** Ciclo de vida completo do empréstimo com filtros, histórico e arquivamento.
*   **Tarefas:**
    1.  [x] Filtros por status na tabela de Empréstimos (Agendado, Reservado, Emprestado, Atrasado, Devolvido, Cancelado, Separado, Arquivado).
    2.  [ ] Cards de resumo no topo da página (ativos, atrasados, devolvidos no mês, agendamentos futuros). → MOVIDO PARA PÓS-POLIMENTO
    3.  [x] Tabela mostra todos os registros, não apenas ativos. Padrão: Emprestado + Atrasado.
    4.  [x] Página de detalhes do empréstimo: capa do livro, nome do aluno, linha do tempo completa, condição, penalidade, mensagens WhatsApp.
    5.  [x] Sistema de arquivamento: `_bm_archived` (post_meta), botão Arquivar individual, ação em lote, dias configuráveis.
    6.  [x] Filtro "Arquivado" para consulta de registros ocultos.
    7.  [x] Botão Desarquivar para retornar registro à listagem normal.
    8.  [x] Devolução não remove registro — muda status para "Devolvido" e mantém visível.
    9.  [x] Permitir edição de dados do aluno na página de detalhes (Nome, E-mail, Telefone, campos dinâmicos).
    10. [x] Botão "Adicionar Novo Aluno" na página de listagem de alunos.
    11. [x] Botão "Emprestar" em agendamentos Separados (tarefa extra — urgente).

Aqui está o bloco atualizado para substituir a Fase 33 no roadmap.md:

```markdown
### Fase 33 (Polimento) — Central de Exportar/Importar Tudo ← CONCLUÍDA
*   **Descrição:** Interface unificada para exportação e importação completa de dados. Consolida todos os módulos existentes (livros, alunos, histórico, fichas, taxonomias, configurações) em uma única tela com formatos ZIP e CSV. → MOVIDO DA FASE 22
*   **Tarefas:**
    1.  [x] Nova aba "Exportar/Importar Tudo" dentro da página bm_data_io existente, com sub-abas Exportar e Importar.
    2.  [x] Exportar — módulo Livros: CSV com todos os metadados (fixos, dinâmicos, taxonomias, Número de Chamada).
    3.  [x] Exportar — módulo Alunos: CSV com campos dinâmicos _bm_user_*, sem senhas.
    4.  [x] Exportar — módulo Histórico de Empréstimos: CSV com _bm_loan_history de cada aluno.
    5.  [x] Exportar — módulo Fichas de Leitura: CSV com _bm_reading_log de cada aluno.
    6.  [x] Exportar — módulo Taxonomias: CSV com termos e estrutura hierárquica de todas as taxonomias (bm_genre, bm_category, bm_discipline + dinâmicas).
    7.  [x] Exportar — módulo Configurações: JSON com todas as options do plugin.
    8.  [x] Exportar — checkbox "Tudo" que seleciona todos os módulos de uma vez.
    9.  [x] Exportar — seletor de formato: ZIP (múltiplos arquivos) ou CSV único.
    10. [x] Exportar — função bm_export_all_data() que gera cada módulo, compacta via ZipArchive e força download.
    11. [x] Importar — upload de arquivo ZIP (extrai automaticamente e identifica módulos pelo nome do arquivo).
    12. [x] Importar — upload de CSV individual com preview (primeiras 5 linhas) e mapeamento dinâmico de colunas.
    13. [x] Importar — opção "Sobrescrever dados existentes" vs "Apenas adicionar novos registros".
    14. [x] Importar — detecção de duplicados (Título+Autor+Editora para livros, E-mail para alunos) e relatório final com status colorido. ESTÁ FALAHANDO - TEM QUE REVISAR.
```
---

## Pós — Ciclos Futuros

### MARC21
93. Suporte a MARC21: importação e exportação de registros no formato .mrc (binário) e .mrk (texto). Mapeamento de campos MARC para metadados do plugin.

### Leitor de Livros Digitais
94. Leitor integrado com PDF.js e EPUB.js: upload de PDF/EPUB, leitor com virar página, ajuste de fonte, modo noturno, progresso de leitura. Botão "Ler Agora" na página do livro. Integração com APIs de domínio público.

### Google Drive
95. Integração Google Drive: importar planilhas direto do Google Sheets via URL.

### Extras
90. Pesquisa por filtros no dashboard do aluno (expandir busca rápida).
91. Impressão de comprovante de empréstimo no balcão.
92. Máscara de telefone com formatação automática.
132. Backup automático periódico (não só na virada de ano).

### Internacionalização (i18n/l10n)
133. Gerar arquivo .pot e traduzir formalmente o plugin para português do Brasil.

### Segurança e Testes Rigorosos
125. Executar Plugin Check (WordPress.org) e corrigir todos os warnings/errors.
126. Passar PHP_CodeSniffer com WordPress Coding Standards.
127. Testar com OWASP ZAP: XSS, CSRF, injeção.
128. Revisão manual de nonces, sanitização e capabilities.
129. Varredura completa de código órfão e lixo gerado durante o desenvolvimento.

### Testes Automatizados de Interface (E2E)
130. Criar suíte de testes E2E com Selenium IDE cobrindo todos os fluxos principais: cadastro de aluno, importação CSV, empréstimo/devolução no balcão, virada de ano letivo, gerenciar campos, exportação de dados.