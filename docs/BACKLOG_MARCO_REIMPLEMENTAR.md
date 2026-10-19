# Backlog: reimplementar o trabalho da branch `marco`

A branch `marco` foi removida após a migração para o GLPI 12 e **não foi mergeada**. Todo o histórico está preservado na tag:

```
arquivo/marco-pre-glpi12  ->  347a43a
```

Para consultar o código original:

```bash
git fetch --tags
git show arquivo/marco-pre-glpi12                          # ultimo commit
git log main..arquivo/marco-pre-glpi12                     # os 23 commits
git diff main arquivo/marco-pre-glpi12 -- <arquivo>        # diferenca de um arquivo
git checkout arquivo/marco-pre-glpi12 -- <arquivo>         # trazer um arquivo para consulta
```

> Reimplementar sobre a `dev` já com a camada de compatibilidade GLPI 11/12 (`src/compat.php`). Não faça merge da tag: ela parte de uma base anterior à migração e causaria conflitos em `setup.php`, `config`, `profile` e `validation`.

## Itens a reimplementar

Os commits abaixo estão em ordem cronológica.

### A. Mensagens: replies, quotes e deleção
| Item | Commits de referência |
|---|---|
| Replies/Quotes nativos do WhatsApp com visualização | `e9e6b65`, `88e6635`, `967493f` |
| Clicar na citação leva à mensagem de origem | `0b4085b` |
| Animação de foco em faixa que esmaece em 3s | `408359a`, `4cfcfb8` |
| Menu contextual na mensagem | `e0073ba`, `461eea3`, `7f612df` |
| Deletar mensagem para todos (Evolution API v2, `deleteMessageForEveryone`) | `0be7410`, `10d19d8` |

Pontos de atenção:
- O parser de quotes deve usar `strpos` e substring, não regex sensível a quebra de linha.
- A assinatura deve ser injetada **depois** do tratamento da citação, senão o parser quebra.

### B. Áudio
| Item | Commits de referência |
|---|---|
| Gravação nativa de áudio, como no WhatsApp Web | `9908c7c`, `fcc712a` |
| Player de áudio no estilo do WhatsApp | `7a49080`, `9dd9e05`, `b21cddd`, `23117e7` |
| Esconder o nome do arquivo em áudios | `9dd9e05`, `23117e7` |
| Fallback de clipboard e microfone em HTTP | `461eea3` |

### C. Arquivos pesados
| Item | Commits de referência |
|---|---|
| Salvar em disco, por causa do `max_allowed_packet` do MySQL | `848fa14` |
| Upload de ZIPs pesados e tags PDF/EXCEL | `49ca833` |
| Remover a legenda intrusiva de anexos | `0be7410` |

Parte disso já pode estar coberta pelo `MediaStorage`. Conferir antes de reimplementar.

### D. Interface
| Item | Commits de referência |
|---|---|
| Refatoração de abas, modais, arquivos e realtime | `8e3cd63` |
| Botão de Nota Interna como toggle visual na caixa de texto | `693c4dd`, `b21cddd` |
| Prefixo `55` padrão no campo de telefone do novo chat | `f15568d`, `a6300e1` |

O modal não pode apagar o `55` quando é aberto via JavaScript.

### E. Bug 1: atendimento encerrado
| Item | Commit de referência |
|---|---|
| Garantir `date_mod` na criação do chat e priorizar chats ativos na query | `347a43a` |

## Bugs e melhorias ainda pendentes

1. Mensagem enviada do celular após encerrar o atendimento cai em Chat, e não em Fila.
2. A mensagem "0" enviada pelo celular não chega.
3. Disparar mensagem automática ao encerrar o atendimento.
4. Disparar mensagem automática ao transferir o atendimento.
5. A aba Chats deve mostrar só os atendimentos do usuário logado.
6. As abas Fila e Chats devem atualizar automaticamente.

O item 6 pode já estar resolvido pelo Mercure/SSE da `main`. Validar antes.

## Ordem sugerida

1. Bugs 1 e 6, por afetarem o fluxo básico.
2. Itens 2 a 5 da lista de pendentes.
3. Bloco D (interface), depois A (replies e deleção).
4. Bloco B (áudio) por último, por ser o de maior risco.

Cada item deve ser feito em um commit separado na `dev`, com um teste manual curto descrito no commit.
