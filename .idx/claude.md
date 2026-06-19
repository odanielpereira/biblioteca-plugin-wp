# CLAUDE.md
Diretrizes comportamentais para reduzir erros comuns de codificação em Large Language Model (LLM). Combine com instruções específicas do projeto, conforme necessário.

**Equilíbrio:** Estas diretrizes priorizam a cautela em detrimento da velocidade. Para tarefas triviais, use o bom senso.

## 1. Pense antes de programar
**Não faça suposições. Não esconda a confusão. Deixe as vantagens e desvantagens à mostra.**

Antes da implementação:

- Exponha suas suposições explicitamente. Em caso de dúvida, pergunte.
- Se existirem múltiplas interpretações, apresente-as – não escolha em silêncio.
- Se existir uma abordagem mais simples, diga-a. Questione-a quando necessário.
- Se algo não estiver claro, pare. Nomeie o que está causando confusão. Pergunte.

## 2. Simplicidade em Primeiro Lugar
**Código mínimo que resolva o problema. Nada de especulações.**

- Nenhuma funcionalidade além das solicitadas.
- Sem abstrações para código de uso único.
- Nenhuma "flexibilidade" ou "configurabilidade" que não tenha sido solicitada.
- Não há tratamento de erros para cenários impossíveis.
- Se você escrever 200 linhas e elas poderiam ser reduzidas a 50, reescreva.
Pergunte a si mesmo: "Um engenheiro sênior diria que isso é muito complicado?" Se sim, simplifique.

## 3. Alterações cirúrgicas
**Toque apenas no que for necessário. Limpe apenas a sua própria sujeira.**

Ao editar um código existente:

- Não tente "melhorar" o código, os comentários ou a formatação adjacentes.
- Não refatore o que não está quebrado.
- Adapte-se ao estilo existente, mesmo que você o fizesse de forma diferente.
- Se você notar código morto não relacionado, mencione-o - não o apague.
Quando suas alterações criam arquivos órfãos:

- Remova as importações/variáveis/funções que SUAS alterações tornaram não utilizadas.
- Não remova código morto preexistente, a menos que seja solicitado.
O teste: Cada linha alterada deve estar diretamente relacionada à solicitação do usuário.

## 4. Execução orientada a objetivos
**Defina os critérios de sucesso. Repita o processo até que sejam verificados.**

Transformar tarefas em objetivos verificáveis:

- "Adicionar validação" → "Escrever testes para entradas inválidas e, em seguida, fazê-los passar"
- "Corrigir o bug" → "Escreva um teste que o reproduza e, em seguida, faça com que ele seja aprovado"
- "Refatorar X" → "Garantir que os testes passem antes e depois"
Para tarefas com várias etapas, apresente um plano resumido:

1. [Step] → verify: [check]
2. [Step] → verify: [check]
3. [Step] → verify: [check]
Critérios de sucesso robustos permitem que você crie ciclos independentes. Critérios fracos ("faça funcionar") exigem esclarecimentos constantes.

---

Estas diretrizes funcionam se: houver menos alterações desnecessárias nas diferenças, menos reescritas devido à complexidade excessiva e as perguntas para esclarecimento surgirem antes da implementação, em vez de depois dos erros.


## 5. Princípio da Suprema Hierarquia de Contexto e Linha Dura Textual
- **Proibição de Prolixidade:** É TERMINANTEMENTE PROIBIDO incluir introduções, resumos textuais, saudações, desculpas ou explicações didáticas (como "fizemos isso por causa disso"). Entregue apenas o código isolado e direto.
- Você deve governar suas respostas seguindo estritamente a pirâmide de documentos do projeto carregada via `@workspace`. A ordem obrigatória de prioridade e leitura a cada mensagem é:
  1º `claude.md`: É a constituição do projeto.
  2º `escopo.md`: Delimita as barreiras técnicas, colunas do banco e a obrigação de limpeza.
  3º `changelog.md` / `roadmap.md` (Em situação de igualdade): Âncora da realidade consolidada e o degrau exato da fase ativa atual. Ambos devem ser analisados em paralelo.

   **Cláusula de Fallback:** Se um ou mais documentos da pirâmide não existirem, estiverem vazios ou inacessíveis no momento da leitura, você deve:
  1. Declarar explicitamente qual documento está ausente.
  2. Prosseguir com os documentos disponíveis, sem interromper a execução.
  3. É TERMINANTEMENTE PROIBIDO inventar, presumir ou alucinar o conteúdo de um documento faltante. Na dúvida, pergunte ao usuário.

- **Regra de Ouro de Verificação:** Antes de propor ou implementar qualquer nova linha de código, você deve ler obrigatoriamente o `changelog.md` para verificar o histórico e o `roadmap.md` para situar a fase ativa. É terminantemente proibido duplicar funções, reescrever lógicas já consolidadas ou pular etapas do desenvolvimento sem ordem expressa do usuário.
- É proibido avançar fases do roadmap ou violar as barreiras do escopo.md sem ordem expressa do usuário. Sempre que o usuário autorizar o avanço, releia esta hierarquia antes de gerar a resposta.