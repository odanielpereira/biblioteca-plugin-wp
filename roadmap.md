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

## Ciclo 7 — Versão 7.0.0 ← EM ANDAMENTO

### Fase 11: Ferramentas Pedagógicas ← FASE ATIVA
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

## Ciclo 8 — Versão 8.0.0 ← EM PLANEJAMENTO

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

#### Fase 12D — Limpeza de Código Morto
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

#### Fase 12F — Status e Diagnóstico
*   **Descrição:** Páginas de status do sistema e logs.
*   **Tarefas:**
    1.  [ ] Página de Status: versão do plugin, PHP, WordPress, memória, chaves API.
    2.  [ ] Contador de chamadas API (Groq) com estatísticas de uso.
    3.  [ ] Logs de erro e diagnóstico.

#### Fase 12G — Campos Dinâmicos para Alunos
*   **Descrição:** Adaptar o gerenciador de campos dinâmicos para suportar também metadados de usuário (user_meta).
*   **Tarefas:**
    1.  [ ] Interface unificada com abas: "Campos de Livros" e "Campos de Alunos".
    2.  [ ] Prefixo `_bm_user_` para campos dinâmicos de alunos.
    3.  [ ] Mesmos tipos: texto curto, texto longo.
    4.  [ ] Drag and drop, renomear, ocultar/mostrar.
    5.  [ ] Nenhum campo fixo obrigatório — gestor define tudo.

#### Fase 12H — Importação de Alunos em Massa
*   **Descrição:** Página de importação de alunos via CSV com mapeamento dinâmico de colunas.
*   **Tarefas:**
    1.  [ ] Subpágina "Importar Alunos" no menu Usuários ou Livros.
    2.  [ ] Upload de CSV com mapeamento dinâmico (igual ao de livros).
    3.  [ ] Colunas mapeáveis: user_login, display_name, user_email, user_pass + campos dinâmicos + bm_student_group.
    4.  [ ] Criação automática de usuários com role `bm_student`.
    5.  [ ] Status: "approved" (direto) ou "pending" (aguardando aprovação).
    6.  [ ] Detecção de duplicados por e-mail.
    7.  [ ] Relatório: X importados, Y ignorados, Z duplicados.

#### Fase 12I — Dashboard e Cadastro de Alunos
*   **Descrição:** Exibir campos dinâmicos no dashboard do aluno e no formulário de autocadastro.
*   **Tarefas:**
    1.  [ ] Dashboard do aluno exibe campos dinâmicos preenchidos.
    2.  [ ] Shortcode `[bm_register]` atualizado com campos dinâmicos.
    3.  [ ] Página de edição de aluno no admin (dados nativos + campos dinâmicos + histórico).
    4.  [ ] Professor vê dados do aluno em modo leitura.

#### Fase 12J — Administração de Alunos
*   **Descrição:** Interface completa para Admin e Gestor gerenciarem alunos.
*   **Tarefas:**
    1.  [ ] Subpágina "Alunos" com listagem (tabela com colunas customizáveis).
    2.  [ ] Filtros por grupo, status, turno, série.
    3.  [ ] Ações em lote: aprovar, suspender, excluir.
    4.  [ ] Página individual do aluno (dados + campos dinâmicos + histórico de leitura/XP/medalhas).

    #### Fase 12K — Atendimento (Empréstimo Rápido no Balcão)
*   **Descrição:** Tela de atendimento físico para Gestor/Admin realizar empréstimos e devoluções rapidamente.
*   **Tarefas:**
    1.  [ ] Subpágina "Atendimento" no menu Biblioteca (acesso: Gestor e Admin).
    2.  [ ] Campo de busca de livro com autocomplete e exibição de disponibilidade em tempo real.
    3.  [ ] Indicador visual se o livro é "Consulta local" (não pode sair da biblioteca).
    4.  [ ] Campo de busca de aluno com autocomplete e status (pendências, livros ativos, limite).
    5.  [ ] Modal de cadastro rápido de aluno (nome, e-mail, telefone, campos dinâmicos) na mesma tela.
    6.  [ ] Botão "Emprestar" que aplica regras (limite, prazo, consulta local) e atualiza estoque.
    7.  [ ] Botão "Devolver" na mesma tela.
    8.  [ ] Alerta visual se aluno tem devolução atrasada ou atingiu limite de empréstimos.
    9.  [ ] Suporte a leitor de código de barras (campo com foco automático para ISBN/ID).
    10. [ ] Histórico rápido do aluno (últimos 3 livros lidos).
    11. [ ] Checkbox "Consulta local" no cadastro/edição do livro (metadado `_bm_consulta_local`).

## Ciclo de Polimento — Versão 8.5.0 ← PLANEJADO

### 68 itens mapeados (consolidados em documento separado)

### Imagens de Capa (Fase 7D / Fase 8E)
1. Aumentar resolução das capas (zoom=2) — ✅ concluído
2. Hotlink vs download local
3. Placeholder no single — ✅ concluído

### Importação CSV (Fase 6A / Fase 7D / Fase 7G)
4. Checkbox "Buscar capas" e "Buscar sinopses" com aviso de lentidão
5. Importação assíncrona para grandes arquivos
6. Melhorar detecção de título/autor

### Exportação CSV (Fase 6B / Fase 6C)
7. Aviso de sucesso pós-download

### Gerenciamento de Campos (Fase 7H)
8. Corrigir drag and drop
9. Campos fixos removíveis
10. Página de configurações para API Keys — ✅ (Fase 10E)

### Interface e Usabilidade (Fase 7E / Fase 8B / Ciclo 2)
11. Bulk action quebrado
12. Seleção individual de duplicados
13. Layout visual (protótipo Stitch)

### Segurança e Performance (Fase 8C-B)
14. Nonces e sanitização — ✅ concluído
15. Unificar funções de capa — ✅ concluída

### Funcionalidades Adicionais (Fase 6A / Fase 8D / Fase 8E)
16. Relatório visual de importação com cores
17. Responsividade das capas no archive
18. Cruzamento de filtros no archive

### Integração IA (Fase 8G / Fase 11A-B)
19. Chave API Gemini válida — ✅ (substituída por Groq)

### Ciclo 5 — Pendências
20. Página de instalação (primeiro acesso)
21. Portal de login com redirecionamento
22. Visibilidade configurável de campos por perfil
23. Campos dinâmicos conforme perfil
24. Revisar hierarquia de perfis
25. Centralizar menu de administração
26. Substituir alert() por modal
27. Dashboard de leitura com seletor de período
28. Interface de reserva para Professor/Gestor (dropdown)
29. Clareza visual do estoque
30. Contador regressivo refinado
31. Contador de mensagens WhatsApp
32. Desmembrar book-manager.php — ✅ (Fase 9H)
33. Criador de Taxonomias Dinâmicas
34. Configuração de limites por perfil
35. Ajustar limite de reservas (bug)
36. Refinar monitoramento do Professor
37. Limpar roles sujas na ativação
38. Remover role bm_super_admin redundante

### Análise Gemini — Correções
39. Tornar uninstall.php autocontido
40. Substituir manage_options por capabilities granulares

### Performance
41. Otimizar queries dos dashboards

### Ranking e Fichas (Ciclo 6)
42. Ranking no dashboard do aluno
43. Filtros configuráveis no ranking
44. Perfil público do leitor
45. Exibir resenhas aprovadas no single
46. Vitrine de resenhas no perfil público
47. Curadoria de resenhas na página do livro
48. Ranking de livros [bm_top_books]
49. Vídeo-resenhas na página do livro via CSV
50. Redirecionamento após login
51. Dashboard enriquecido com relatórios
52. Design system para dashboards

### Vídeo e Embed (Ciclo 6)
53. Suporte a Instagram Reels
54. Importação CSV com coluna de vídeo
55. Resenha oficial do Gestor/Admin — ✅ (Fase 10C)
56. Estrelas opcionais na ficha de leitura — ✅ (Fase 10B)
57. Corrigir embed de TikTok e Instagram

### XP e Medalhas (Ciclo 6)
58. Exibir XP ganho por ficha
59. Gestor definir XP manualmente ao aprovar
60. Exibir XP na seção Minhas Fichas
61. Link Minhas Fichas no dashboard

### APIs e IA (Ciclo 7)
62. Refatorar constantes para usar central de APIs
63. Substituir Gemini por Groq/DeepSeek — ✅ (Fase 11A-B)
64. Avaliar remoção do bloco 8G (lixo)
65. Refinar prompt da classificação por IA
66. Configurar persona/tom da IA na central
67. Configurações do Chatbot (ativar/desativar, perfil, persona)
68. Criar Fase 12E — Limpeza de código morto