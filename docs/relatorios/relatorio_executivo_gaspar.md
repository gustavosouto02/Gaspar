# GASPAR — Relatório Executivo da Plataforma
**Sistema Institucional de Gestão por Processos e Central de Demandas**

---

## 1. Visão Geral e Propósito

O **Gaspar** é a plataforma institucional desenvolvida para transformar a forma como as demandas, compras, contratações e fluxos de trabalho são executados e acompanhados na organização. 

Concebido com base nas melhores práticas de gestão por processos (*Business Process Management*), o sistema substitui solicitações dispersas em e-mails, mensagens instantâneas e planilhas paralelas por um ambiente único, intuitivo e auditável, garantindo que cada solicitação chegue à pessoa certa, no momento correto e dentro do prazo acordado.

---

## 2. Desafios que o Gaspar Resolve

Antes da implantação de uma solução centralizada, organizações frequentemente enfrentam:

* **Falta de visibilidade do andamento:** Demandantes e gestores precisam perguntar repetidamente com quem está uma solicitação e qual é a previsão de entrega.
* **Perda ou descontrole de prazos:** Sem alertas formais e prazos pré-definidos, etapas prioritárias sofrem atrasos invisíveis.
* **Retrabalho e dados incompletos:** Solicitações que chegam com informações faltantes geram trocas infindáveis de mensagens para coleta de dados adicionais.
* **Falta de histórico e rastreabilidade:** Dificuldade em auditorias ou conferências futuras para identificar quem aprovou, quando executou ou que decisões foram tomadas ao longo do caminho.

O Gaspar elimina esses gargalos estruturando as etapas de trabalho de maneira visual, padronizada e transparente.

---

## 3. Como o Sistema Funciona na Prática

O ciclo de uma demanda no Gaspar foi projetado para ser simples para quem solicita e rigoroso para quem gerencia. Vamos usar como exemplo o processo de contratação de serviços avulsos:

```
[ Solicitante ] ➔ Abre a demanda com campos específicos do processo
       │
       ▼
[ Triagem / Licitando ] ➔ Responsável técnico analisa valores e fornecedores
       │
       ▼ (Regra Automática: Avaliação de alçadas e limites de valor)
       ├─ Se valor até R$ X ────➔ Compra Direta
       ├─ Se entre R$ X e R$ Y ➔ Aprovação da Gestão
       └─ Se acima de R$ Y ─────➔ Aprovação da Diretoria
       │
       ▼
[ Aquisição e Quitação ] ➔ Setor financeiro conclui o pagamento e entrega
       │
       ▼
[ Encerramento & Avaliação ] ➔ Solicitante avalia o atendimento com nota e comentário
```

---

## 4. Principais Funcionalidades para a Operação

### 4.1. Painel Inicial Inteligente (Home / Dashboard)
* **Visão de Lista ou Quadro (Kanban):** Cada colaborador visualiza suas tarefas em formato de tabela organizada ou em colunas dinâmicas separadas por situação de atendimento.
* **Foco no que é prioridade:** As demandas que dependem de ação do usuário aparecem em destaque no topo da lista com avisos visuais claros (incluindo pendências de aprovação ou de avaliação).

### 4.2. Formulários Inteligentes por Tipo de Processo
* Em vez de formulários genéricos, cada processo (ex.: *Contratação de Serviços*, *Compras*, *Viagens*) exibe exatamente os campos necessários para a tomada de decisão (como preço, justificativa, anexos, dados bancários e fornecedor).

### 4.3. Regras de Encaminhamento Automático (Gateways)
* O sistema avalia os dados preenchidos e direciona a solicitação automaticamente para o caminho correto, garantindo o cumprimento de alçadas orçamentárias e normas institucionais sem depender de intervenções manuais.

### 4.4. Controle Rigoroso de Prazos (SLA)
* Cada processo possui um tempo padrão estimado para conclusão (ex.: 120 horas úteis).
* O prazo é calculado de forma automática a partir do momento em que a demanda entra em atendimento.
* Alertas preventivos são disparados para que os responsáveis não sejam surpreendidos pelo vencimento.

### 4.5. Notificações Automáticas por E-mail
* Envio automático de avisos por e-mail para demandantes e executores a cada mudança de situação, registro de relato oficial ou atribuição de responsabilidade, sempre acompanhados de link direto para a demanda.

### 4.6. Rastreabilidade Completa ("Por quem passou")
* O histórico completo de tramitação fica gravado em ordem cronológica, registrando quem solicitou, quem aprovou, quem executou e quais observações foram registradas em cada etapa.

### 4.7. Pesquisa de Satisfação Pós-Atendimento
* Ao final do fluxo, o solicitante é convidado a avaliar o atendimento com nota e comentário. As avaliações alimentam indicadores contínuos de qualidade dos serviços internos.

### 4.8. Relatório Oficial em PDF com 1 Clique
* Geração instantânea de um documento formal contendo o cabeçalho institucional, dados gerais, controle de tempo, formulário preenchido, histórico de responsáveis e avaliação de satisfação.

---

## 5. Benefícios e Ganhos para a Organização

| Dimensão | Antes do Gaspar | Com o Gaspar |
| :--- | :--- | :--- |
| **Comunicação** | E-mails perdidos e mensagens em aplicativos paralelos | Ponto central de atendimento com notificações automáticas |
| **Previsibilidade** | Prazos subjetivos ou não monitorados | Controle de SLA em horas, com alertas de vencimento |
| **Segurança e Regras** | Dependência da memória de quem opera o fluxo | Roteamento automático baseado em regras e alçadas fixadas |
| **Auditoria e Transparência** | Histórico fragmentado e difícil de consolidar | Linha do tempo completa e registro em PDF exportável |
| **Gestão da Qualidade** | Sem retorno estruturado de satisfação | Medição formal de satisfação ao término de cada demanda |

---

## 6. Considerações Finais

O **Gaspar** consolida um ambiente de trabalho mais ágil, organizado e transparente para toda a equipe. Ele assegura que a instituição mantenha um padrão consistente de atendimento, reduza o tempo gasto em checagens manuais e disponha de dados confiáveis para a tomada de decisões estratégicas.
