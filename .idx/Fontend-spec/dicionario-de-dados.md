### Dicionário de Dados: Motor Book Manager

| Campo (Input/Chave) | Origem (Banco/Meta) | Tipo/Formato | Lógica/Regra de Negócio |
| :--- | :--- | :--- | :--- |
| `bm_report_type` | `GET` | String | Define a função `bm_report_...` executada no PHP. |
| `bm_period` | `GET` | String | Define o intervalo de `timestamp` (since/until). |
| `bm_date_start` | `GET` | Date (Y-m-d) | Parâmetro para modo `custom`. |
| `bm_date_end` | `GET` | Date (Y-m-d) | Parâmetro para modo `custom`. |
| `bm_subject_id` | `user_meta` | Integer | ID do aluno (referência para `student_performance`). |
| `bm_group` | `user_meta` | String | Chave `_bm_user_Turma`. |
| `name` | `wp_users` | String | `display_name` do aluno. |
| `books_read` | `post_meta` | Integer | Contagem de posts `bm_book` com status 'devolvido'. |
| `reviews` | `post_meta` | Integer | Contagem de resenhas vinculadas ao ID do aluno. |
| `videos` | `post_meta` | Integer | Contagem de vídeos vinculados ao ID do aluno. |
| `xp` | `user_meta` | Integer | Acumulado calculado por regra de complemento. |
| `badges` | `user_meta` | Array | Lista de identificadores de medalhas. |
| `penalties` | `post_meta` | Array | Registros de multa/advertência (type, note, date, until). |

### Dicionário de Complementos: Lógica de BI

| Complemento | Fonte da Lógica | Regra de Cálculo |
| :--- | :--- | :--- |
| **Ranking de Gênero** | `wp_get_post_terms` | Agrupamento de termos `bm_genre` em empréstimos ativos. |
| **Status de Atraso** | `return_date` | Comparação: `hoje > data_devolução`. |
| **Tendência de Leitura** | `loan_date` | Soma acumulada de empréstimos por intervalo de mês. |
| **XP** | `bm_calculate_xp` | $XP = (Livros \times 10) + (Resenhas \times 5) + (Vídeos \times 5)$. |
| **Penalidades** | `_bm_reservations` | Filtro por status (`penalized`, `suspended`). |



### Dicionário de Complementos: Lógica de BI

| Complemento | Identificador (Key) | Fonte (PHP/Meta) | Regra de Negócio (Lógica) |
| :--- | :--- | :--- | :--- |
| **Ranking de Gênero** | `genre_counts` | `wp_get_post_terms` | Agrupamento de `bm_genre` em posts de empréstimo. |
| **Status de Atraso** | `has_overdue` | `return_date` | `bool`: `today > return_date` (Empréstimo ativo). |
| **Tendência de Leitura**| `months` | `loan_date` | Vetor de frequência temporal (soma por mês). |
| **Cálculo de XP** | `xp` | `bm_calculate_xp` | $(Livros \times 10) + (Resenhas \times 5) + (Vídeos \times 5)$. |
| **Penalidades** | `penalties` | `_bm_reservations` | Filtro de status: `penalized` ou `suspended`. |
| **Reservas Pendentes** | `total_reservations`| `_bm_reservations` | Contagem de registros com status `waiting`. |
| **Leitura por Turma** | `average` | `group` | Média: `total_books_read_in_group / total_students_in_group`. |
| **Medalhas** | `badges` | `get_user_meta` | Atribuição baseada em limiares: `books_read` >= 10, etc. |
| **Nunca Leram** | `never_read` | `get_users` | Lista de usuários `bm_student` com 0 empréstimos processados. |



### Catálogo de Componentes e Utilitários (BI Layer)

**A. Componentes de Interface (V0/Frontend):**
1. `KPI_Card`: Exibe números destacados com variação percentual.
2. `Bento_Grid_Container`: Layout principal.
3. `Dynamic_Chart`: Slot genérico (adaptável para Linha, Pizza, Radar).
4. `Student_Table`: Tabela com hover e estados de linha.

**B. Utilitários de BI (Lógica JS):**
1. `formatJSONData()`: Transforma o dado bruto do PHP em formato de série temporal.
2. `calculateVariance()`: Calcula o % de crescimento entre períodos.
3. `rankEntities()`: Ordena alunos ou turmas pelo desempenho (Top 3).


