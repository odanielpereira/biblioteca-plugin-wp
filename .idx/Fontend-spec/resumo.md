# Resumo Executivo: Projeto Book Manager (Dashboard de BI)

Este documento sintetiza a arquitetura e o planejamento estabelecidos para a modernização do painel de relatórios do plugin **Book Manager**, transformando dados brutos em um sistema dinâmico de Inteligência de Negócios (BI).

## 1. O Objetivo do Projeto
Transformar um dashboard estático, que apenas exibe dados brutos (logs de leitura, empréstimos), em uma interface de **BI dinâmico** com:
* **KPIs (Indicadores de Performance):** Cards com dados sintetizados.
* **Visualização Avançada:** Gráficos interativos (Linhas, Radar, Pizza, Ranking).
* **Sincronia:** Atualização em tempo real via AJAX sem recarregar a página.
* **Escalabilidade:** Arquitetura desacoplada (PHP no Backend, JS/Tailwind no Frontend).

## 2. Documentação Técnica Gerada
* **Dicionário de Dados:** Define o "Contrato de JSON". Mapeia os campos (`bm_report_type`, `bm_subject_id`, etc.) e sua origem no banco/meta do WordPress.
* **Dicionário de Complementos (Lógica de BI):** Define como o JavaScript deve processar os dados brutos (cálculo de XP, ranking, atrasos, tendências) para gerar métricas de valor.
* **Spec Final:** Documento de diretrizes técnicas (Tailwind, Grid Bento, arquitetura local, segurança com `nonces`).
* **Roadmap de Implementação:** Plano de 4 etapas:
    1. **Core e Contrato (Back-end).**
    2. **Ponte AJAX (JSON Bridge).**
    3. **Interface Bento Grid (V0/Frontend).**
    4. **Sincronização e Refinamento.**

## 3. Arquitetura e Infraestrutura
* **Processamento:** O PHP atua como *Data Warehouse* (entrega dados brutos); o JavaScript atua como *Motor de BI* (processa, ordena e calcula variações).
* **Estilo:** Design "Bento Grid" (Tailwind CSS) para organização modular.
* **Independência:** Implementação 100% local (sem CDNs).
* **Estrutura de Pastas (Hierarquia):**
    * `assets/css/` (Tailwind compilado)
    * `assets/js/` (Lógica de BI e Bridge AJAX)
    * `includes/` (Motor PHP/Core)
    * `templates/` (Layout HTML Bento)

## 4. O Prompt de Execução (V0)
Foi definido um prompt específico para o V0, com instruções cirúrgicas para:
* Ignorar CDNs (garantir infraestrutura local).
* Estruturar o HTML com `data-component="bm-chart"` para facilitar o *binding* dos dados via JS.
* Manter a semântica correta dos campos de filtro (`name="bm_..."`).

## 5. Próximos Passos
O projeto está pronto para a execução. O desenvolvedor deve agora:
1.  Utilizar o **Prompt V0** para gerar a interface.
2.  Implementar o **JSON Bridge** (Tarefa 1 e 2 do Roadmap).
3.  Conectar o motor PHP ao frontend local seguindo a hierarquia de arquivos definida.

---
*Este resumo contém todos os parâmetros técnicos e estratégicos necessários para a continuidade do desenvolvimento em qualquer ambiente de IA ou IDE.*