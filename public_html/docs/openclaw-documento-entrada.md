# Instruções: OpenClaw — Consulta Documento de Entrada

> Estas instruções ensinam o agente a responder perguntas sobre **documentos de entrada (NF entrada / contas a pagar por setor)** consultando o sistema Popper Conecta via API interna.

---

## 1. ENDPOINT E AUTENTICAÇÃO

**URL base:**
```
POST https://popperconecta.com.br/api/totvs/agent.php
```
ou local:
```
POST http://[IP_XAMPP]/api/totvs/agent.php
```

**Headers obrigatórios:**
```
Content-Type: application/json
X-Internal-Agent-Token: pc_test_2026_04_X7mQ9pL2vN8rK4sT1yH6cJ3wF5bD0zA
```

**Formato do body:**
```json
{
  "action": "<nome_da_action>",
  "from": "YYYY-MM-DD",
  "to": "YYYY-MM-DD"
}
```

---

## 2. ACTIONS DISPONÍVEIS — DOCUMENTO DE ENTRADA

| Action | Quando usar |
|---|---|
| `documento_entrada_resumo` | Total gasto, qtd de títulos, próximos vencimentos do período |
| `documento_entrada_rankings` | Ranking por setor (centro de custo), natureza, fornecedor |
| `documento_entrada_proximos` | Títulos vencendo nos próximos 3, 7 ou 15 dias |

---

## 3. PARÂMETROS

| Parâmetro | Tipo | Padrão | Descrição |
|---|---|---|---|
| `from` | `YYYY-MM-DD` | 1º do mês atual | Data inicial do vencimento |
| `to` | `YYYY-MM-DD` | Último dia do mês atual | Data final do vencimento |
| `janela` | string | `15_dias` | Só para `proximos`: `3_dias`, `7_dias`, `15_dias` |
| `limit` | int (1–100) | `20` | Só para `rankings`: quantidade de itens |
| `force` | bool | `false` | `true` = ignora cache, busca direto do TOTVS |

**Mês atual (sem parâmetros):** omite `from`/`to` e o sistema usa o mês corrente automaticamente.

---

## 4. MAPEAMENTO: PERGUNTA → CHAMADA API

### 4.1 "Quanto Facilities gastou esse mês?"

Centro de custo FACILITIES = código `1.2.6`

```json
POST /api/totvs/agent.php
{
  "action": "documento_entrada_rankings",
  "from": "2026-04-01",
  "to": "2026-04-30"
}
```

**Onde encontrar no retorno:**
```
data.rankings.centro_custo[]  →  filtra item com key == "FACILITIES"
item.total  →  valor total gasto
item.qtd    →  quantidade de títulos
```

**Resposta formatada para usuário:**
> "Facilities gastou **R$ X.XXX,XX** em abril/2026 em **N títulos**."

---

### 4.2 "O que vence essa semana?" / "O que vence nos próximos 7 dias?"

```json
{
  "action": "documento_entrada_proximos",
  "janela": "7_dias"
}
```

**Retorno:**
```
data.proximos.7_dias.total   →  valor total vencendo
data.proximos.7_dias.items[] →  lista de títulos com:
  - fornecedor_nome
  - vencimento_fmt   (DD/MM/YYYY)
  - centro_custo_nome
  - VL_PARCELA
```

---

### 4.3 "Qual setor gastou mais esse mês?"

```json
{
  "action": "documento_entrada_rankings"
}
```

**Retorno:**
```
data.rankings.centro_custo[]  →  já vem ordenado do maior para menor
  [0].key    →  nome do setor
  [0].total  →  valor total
  [0].qtd    →  qtd de títulos
```

---

### 4.4 "Mostre todos os gastos de RH"

```json
{
  "action": "documento_entrada_rankings"
}
```

**Onde encontrar:**
```
data.centro_fornecedores["RH"].fornecedores[]  →  fornecedores do setor RH
  - nome
  - total
  - qtd
  - percent  (% dentro do setor)
```

---

### 4.5 "Quais fornecedores entregaram NF esse mês?"

```json
{
  "action": "documento_entrada_rankings",
  "limit": 50
}
```

**Retorno:**
```
data.rankings.fornecedor[]  →  ordenado por valor
  - key    →  nome do fornecedor
  - total  →  valor total
  - qtd    →  quantidade de NFs
```

---

### 4.6 "Detalhe das NFs do fornecedor X"

```json
{
  "action": "documento_entrada_resumo"
}
```

**Retorno:**
```
data.titulos_por_fornecedor["NOME DO FORNECEDOR"].titulos[]
  - nf_numero
  - serie
  - emissao       (DD/MM/YYYY)
  - vencimento    (DD/MM/YYYY)
  - vl_parcela
  - vl_saldo
  - natureza
  - centro_custo
  - parcela
```

---

### 4.7 "Qual o total de contas a pagar do mês de março?"

```json
{
  "action": "documento_entrada_resumo",
  "from": "2026-03-01",
  "to": "2026-03-31"
}
```

**Retorno:**
```
data.resumo.total_valor  →  total do período
data.resumo.total_qtd    →  quantidade de títulos
data.resumo.proximos_3_dias   →  vencendo em 3 dias
data.resumo.proximos_7_dias   →  vencendo em 7 dias
data.resumo.proximos_15_dias  →  vencendo em 15 dias
```

---

### 4.8 "Por natureza — quanto foi gasto em serviços?"

```json
{
  "action": "documento_entrada_rankings"
}
```

**Retorno:**
```
data.rankings.natureza[]  →  ordenado do maior para menor
  - key    →  nome da natureza
  - total
  - qtd

data.natureza_fornecedores["NOME_NATUREZA"].fornecedores[]
  →  quais fornecedores pertencem àquela natureza
```

---

## 5. DICIONÁRIO DE CENTROS DE CUSTO

| Código | Setor |
|---|---|
| 1.1.1 | CONTABILIDADE |
| 1.1.2 | FISCAL |
| 1.1.3 | TI |
| 1.1.4 | FINANCEIRO |
| 1.1.5 | RH |
| 1.1.6 | ADMINISTRATIVO |
| 1.1.7 | FATURAMENTO |
| 1.1.8 | LOGÍSTICA |
| 1.1.9 | COMPRAS |
| 1.1.10 | COMEX |
| 1.2.1 | VENDAS |
| 1.2.2 | MARKETING |
| 1.2.3 | E-COMMERCE |
| 1.2.4 | REPRESENTANTES |
| 1.2.5 | SAC |
| **1.2.6** | **FACILITIES** |
| 1.2.7 | RATEIO |
| 1.2.8 | TRADE MARKETING |
| 1.2.9 | COMUNICAÇÃO |
| 1.2.10 | PRODUTO |
| 1.2.11 | APOIO A VENDAS |
| 1.2.12 | VENDAS DISTRIBUIDOR |
| 1.3.1 | DIRETORIA COMERCIAL |
| 1.3.2 | DIRETORIA |
| 1.9.1 | BEAUTY FAIR |
| 1.9.2 | NOVOS NEGÓCIOS |
| 1.9.3 | PROJETO N |
| 1.9.4 | CELEBRA SHOW 2025 |

> A API já retorna o nome do setor resolvido — o código é só para referência.

---

## 6. ESTRUTURA COMPLETA DO RETORNO

```json
{
  "ok": true,
  "action": "documento_entrada_rankings",
  "data": {
    "periodo": { "from": "2026-04-01", "to": "2026-04-30" },

    "resumo": {
      "total_qtd": 142,
      "total_valor": 89432.50,
      "proximos_3_dias": 4200.00,
      "proximos_7_dias": 12800.00,
      "proximos_15_dias": 31500.00
    },

    "rankings": {
      "centro_custo": [
        { "key": "FACILITIES", "total": 18200.00, "qtd": 23 },
        { "key": "RH", "total": 14500.00, "qtd": 18 }
      ],
      "natureza": [
        { "key": "SERVIÇOS", "total": 32000.00, "qtd": 45 }
      ],
      "fornecedor": [
        { "key": "FORNECEDOR LTDA", "total": 9800.00, "qtd": 5 }
      ],
      "max_centro": 18200.00,
      "max_natureza": 32000.00,
      "max_fornecedor": 9800.00
    },

    "centro_fornecedores": {
      "FACILITIES": {
        "centro": "FACILITIES",
        "total": 18200.00,
        "fornecedores": [
          { "nome": "FORNECEDOR X", "qtd": 3, "total": 8000.00, "percent": 43.9 }
        ]
      }
    },

    "natureza_fornecedores": { ... },

    "titulos_por_fornecedor": {
      "FORNECEDOR X": {
        "fornecedor": "FORNECEDOR X",
        "total": 8000.00,
        "qtd": 3,
        "titulos": [
          {
            "nf_numero": "001234",
            "serie": "1",
            "emissao": "05/04/2026",
            "vencimento": "20/04/2026",
            "natureza": "SERVIÇOS",
            "centro_custo": "FACILITIES",
            "parcela": "001",
            "vl_parcela": 4000.00,
            "vl_saldo": 4000.00,
            "vl_total_nf": 4000.00
          }
        ]
      }
    },

    "proximos": {
      "3_dias":  { "total": 4200.00, "items": [ ... ] },
      "7_dias":  { "total": 12800.00, "items": [ ... ] },
      "15_dias": { "total": 31500.00, "items": [ ... ] }
    }
  }
}
```

---

## 7. REGRAS DE INTERPRETAÇÃO

1. **Mês corrente** — omite `from`/`to`. Sistema usa 1º ao último dia do mês atual.
2. **Mês específico** — usa `from: "YYYY-MM-01"` e `to: "YYYY-MM-31"` (ou último dia real).
3. **"Próximos N dias"** — usa `documento_entrada_proximos` com `janela: "N_dias"`.
4. **Filtro por setor** — chama `rankings` e filtra `data.rankings.centro_custo` pelo nome do setor. A API não filtra por setor diretamente — retorna todos e você filtra no retorno.
5. **Cache** — respostas ficam cacheadas por 120s. Para dados em tempo real, envia `"force": true`.
6. **Valores** — sempre em **reais (BRL)**. Formatar como `R$ X.XXX,XX`.
7. **Datas** — retornam em `DD/MM/YYYY` nos campos `_fmt`. Campo bruto `DT_VENCIMENTO` está em `YYYYMMDD`.

---

## 8. EXEMPLOS DE PERGUNTAS E CHAMADAS

| Pergunta do usuário | Action | Parâmetros extras |
|---|---|---|
| "Quanto Facilities gastou esse mês?" | `documento_entrada_rankings` | — |
| "O que vence nos próximos 3 dias?" | `documento_entrada_proximos` | `janela: "3_dias"` |
| "Total de NFs de março" | `documento_entrada_resumo` | `from: "2026-03-01"`, `to: "2026-03-31"` |
| "Maior fornecedor deste mês" | `documento_entrada_rankings` | — → `rankings.fornecedor[0]` |
| "Quanto RH gastou em fevereiro?" | `documento_entrada_rankings` | `from/to` fev → filtra `rankings.centro_custo` por "RH" |
| "NFs do fornecedor XPTO" | `documento_entrada_resumo` | — → `titulos_por_fornecedor["XPTO"]` |
| "Vencimentos da semana" | `documento_entrada_proximos` | `janela: "7_dias"` |
| "Qual setor mais gastou?" | `documento_entrada_rankings` | — → `rankings.centro_custo[0]` |
