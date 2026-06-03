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
*   **Fase 8G — Classificação Interdisciplinar por IA** ✅ (código pronto, chave API pendente)

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

## Ciclo 6 — Versão 6.0.0 ← EM PLANEJAMENTO

### Fase 10: Gamificação e Engajamento ← FASE ATIVA
*   **Objetivo:** Implementar sistema de pontos, ranking de leitores, fichas de leitura, vídeo-resenhas e medalhas automáticas para engajar alunos.
*   **Critério de saída:** Alunos acumulam XP ao ler e resenhar livros. Ranking público exibe os top leitores. Medalhas são concedidas automaticamente.

#### Fase 10A — Ranking de Leitores
*   **Descrição:** Vitrine com os alunos que mais leram, por período.
*   **Tarefas:**
    1.  [x] Criar shortcode [bm_ranking] para exibir ranking em qualquer página.
    2.  [x] Query para contar empréstimos devolvidos por aluno.
    3.  [x] Filtro por período: semana, mês, bimestre, ano.
    4.  [x] Exibição: foto do aluno (avatar), nome, quantidade de livros lidos.
    5.  [x] Top 10 com destaque visual (medalhas 🥇🥈🥉 para os 3 primeiros).
    6.  [ ] Integrar hook bm_after_catalog_grid() para carrossel de "Mais Lidos". → Ciclo de Polimento

#### Fase 10B — Ficha de Leitura
*   **Descrição:** Formulário para o aluno preencher após ler um livro.
*   **Tarefas:**
    1.  [x] Criar shortcode [bm_reading_log] com formulário.
    2.  [x] Campos: nota (estrelas 1-5), resenha (textarea), data da leitura (auto).
    3.  [x] Selecionar livro dentre os já devolvidos pelo aluno.
    4.  [x] Salvar como metadado do usuário (_bm_reading_log).
    5.  [x] Opção de aprovação pelo Gestor para liberar XP.

#### Fase 10C — Vídeo-Resenha
*   **Descrição:** Campo de link de vídeo na ficha de leitura.
*   **Tarefas:**
    1.  [x] Adicionar campo de URL de vídeo na ficha de leitura.
    2.  [x] Exibir vídeos aprovados na página individual do livro.
    3.  [x] Suporte a YouTube, TikTok, Instagram (embed ou link).

#### Fase 10D — XP e Medalhas (Badges)
*   **Descrição:** Sistema de pontos e conquistas automáticas.
*   **Tarefas:**
    1.  [x] Função bm_add_xp($user_id, $amount, $reason).
    2.  [x] Regras: livro lido = 10 XP, resenha = 5 XP, vídeo = 10 XP.
    3.  [x] Função bm_check_badges($user_id) — verifica e concede medalhas.
    4.  [x] Medalhas: Rato de Biblioteca, Leitor Voraz, Mestre das Ciências, Crítico de Cinema.
    5.  [x] Exibição no dashboard do aluno e no ranking.
    6.  [x] Shortcode [bm_badges] para exibir medalhas do aluno logado.

---

## Ciclo 7 — Versão 7.0.0 ← PLANEJADO

### Fase 11: Ferramentas Pedagógicas
*   **Objetivo:** Fornecer ferramentas de apoio pedagógico: gerador de atividades, classificação CDU/Cutter e geração de etiquetas.
*   **Critério de saída:** Professores geram atividades por IA. Livros têm CDU/Cutter. Etiquetas podem ser impressas.

#### Fase 11A — Gerador de Atividades por IA
*   **Descrição:** Professor recebe sugestões de dinâmicas de aula baseadas no livro.
*   **Tarefas:**
    1.  [ ] Botão "Gerar Atividade" no dashboard do Professor (para cada livro).
    2.  [ ] Enviar título, autor, sinopse e disciplina para API Gemini.
    3.  [ ] Prompt: "Sugira 3 atividades pedagógicas para este livro na disciplina X".
    4.  [ ] Salvar resultado como metadado do livro (`_bm_activities`) para cache.
    5.  [ ] Exibição formatada no dashboard do Professor.

#### Fase 11B — CDU e Cutter
*   **Descrição:** Classificação catalográfica automatizada por IA.
*   **Tarefas:**
    1.  [ ] Botão "Classificar CDU/Cutter" na edição do livro.
    2.  [ ] IA sugere código CDU baseado no título + sinopse + gênero.
    3.  [ ] Cálculo automático da Tabela Cutter-Sanborn (autor + título).
    4.  [ ] Campos: `_bm_cdu` e `_bm_cutter`.
    5.  [ ] Cache de resultados (`_bm_cdu_cached`).

#### Fase 11C — Geração de Etiquetas
*   **Descrição:** Impressão de etiquetas para lombada dos livros.
*   **Tarefas:**
    1.  [ ] Página "Etiquetas" no menu Livros.
    2.  [ ] Selecionar livros via checkboxes.
    3.  [ ] Gerar folha A4 com etiquetas formatadas.
    4.  [ ] Layout: código de barras, CDU, Cutter, título, autor.
    5.  [ ] CSS @media print para impressão direta.

---

## Ciclo 8 — Versão 8.0.0 ← PLANEJADO

### Fase 12: Infraestrutura e Configurações
*   **Objetivo:** Tornar o plugin configurável, adaptável a qualquer escola e preparado para virada de ano letivo.
*   **Critério de saída:** Escola configura nome, logo e limites. Virada de ano letivo funcional. Importação via Google Drive.

#### Fase 12A — Página de Configurações
*   **Descrição:** Central de configurações do plugin.
*   **Tarefas:**
    1.  [ ] Subpágina "Configurações" no menu Livros (acesso: Admin).
    2.  [ ] Campos: API Key Google Books, API Key Gemini.
    3.  [ ] Limites: máximo de reservas por aluno, máximo de empréstimos por aluno, prazo padrão.
    4.  [ ] Salvar como `get_option('bm_settings')`.

#### Fase 12B — White Label
*   **Descrição:** Personalização da identidade visual da escola.
*   **Tarefas:**
    1.  [ ] Campo: nome da escola (substitui "Catálogo de Livros").
    2.  [ ] Upload de logo (exibida no header do catálogo).
    3.  [ ] Cores personalizáveis (primária, secundária).
    4.  [ ] Anos letivos configuráveis.

#### Fase 12C — Virada de Ano Letivo
*   **Descrição:** Botão para resetar dados na virada do ano.
*   **Tarefas:**
    1.  [ ] Botão "Virada de Ano Letivo" no painel do Admin.
    2.  [ ] Arquivar rankings atuais (salvar como histórico).
    3.  [ ] Resetar XP e medalhas (opcional).
    4.  [ ] Limpar reservas antigas.
    5.  [ ] Ativar trava de recadastramento obrigatório.
    6.  [ ] Confirmação dupla antes de executar.

#### Fase 12D — Integração Google Drive
*   **Descrição:** Importar planilhas direto do Google Sheets.
*   **Tarefas:**
    1.  [ ] Campo de URL do Google Sheets na importação CSV.
    2.  [ ] Baixar planilha como CSV via `wp_remote_get()`.
    3.  [ ] Processar com o mesmo fluxo de importação existente.

---

## Ciclo de Polimento — Versão 8.5.0 ← PLANEJADO

### 38 itens mapeados (consolidados em documento separado)

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
10. Página de configurações para API Keys — movido para Fase 12A

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

### Integração IA (Fase 8G)
19. Chave API Gemini válida — pendente

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
32. Desmembrar book-manager.php — ✅ concluído (Fase 9H)
33. Criador de Taxonomias Dinâmicas
34. Configuração de limites por perfil
35. Ajustar limite de reservas (bug)
36. Refinar monitoramento do Professor
37. Limpar roles sujas na ativação
38. Remover role bm_super_admin redundante