# 🌱 florescer

> **Plante o hábito. Colha o conhecimento. Floresça de verdade.**

**florescer** é uma plataforma de estudos completa, gratuita e focada em quem quer organizar a rotina, acompanhar a evolução e alcançar seus objetivos tudo em um só lugar.

🔗 **[florescerapp.com.br](https://florescerapp.com.br/florescer/public/index.php)**

---

## ✨ Sobre o projeto

O florescer nasceu da ideia de que estudar bem vai além de ter o conteúdo certo é sobre construir um **caminho claro**, manter o **hábito** e celebrar cada conquista no processo.

A plataforma reúne ferramentas de organização, acompanhamento, produtividade e comunidade em uma interface limpa e acessível para qualquer pessoa que queira focar de verdade nos estudos.

---

## 🗂️ Funcionalidades

### 🎯 Objetivos
Crie um objetivo de estudo, monte seu plano e organize suas matérias. Dentro de cada matéria, estruture o aprendizado por **unidades**, adicione **assuntos** e crie **aulas** com links do YouTube para estudar do seu jeito.

### 📚 Aulas e conteúdo
- Navegação simples entre aulas  avance e volte quando quiser
- Crie novas aulas dentro de aulas existentes para um aprendizado ainda mais organizado
- Links do YouTube integrados diretamente no conteúdo

### ⏱️ Cronômetro Pomodoro
Técnica de foco integrada para manter a concentração. Estude por blocos, descanse na medida certa e mantenha o ritmo.

### 📝 Anotações
Escreva, copie e organize anotações diretamente na plataforma. Simples, rápido e sempre disponível durante os estudos.

### 📋 Meus Trabalhos
Gerencie tarefas de forma prática. Adicione trabalhos, marque como **em andamento**, finalize como **entregue** ou exclua quando quiser.

### 📅 Calendário
Planeje sua rotina com um calendário completo. Crie eventos, acompanhe compromissos ao longo dos meses e marque como concluído para manter tudo organizado.

### 🧪 Simulados
Teste seus conhecimentos na prática. Responda questões, finalize os testes e acompanhe seu desempenho com detalhes.

### 📊 Notas
Registre suas avaliações com até cinco notas diferentes e acompanhe sua evolução de forma clara e visual.

### 📈 Progresso
Acompanhe sua evolução ao longo do tempo e celebre cada conquista alcançada na jornada.

### 🗓️ Histórico
Veja sua frequência de estudos em um calendário visual. Cada dia de dedicação fica registrado motivação garantida.

### 💬 Chat
Conecte-se com outros alunos da plataforma, tire dúvidas e faça parte de uma comunidade que estuda junto.

### 🏆 Gamificação
Sistema de **XP**, **níveis** e **streaks** para tornar a jornada ainda mais motivadora. Continue estudando, suba de nível e mantenha sua sequência ativa.

### 🛒 Loja de Cursos
Acesse conteúdos complementares para potencializar seus estudos e ir além do básico.

### 💚 Apoiar o projeto
Gostou da plataforma? Contribua via **Pix ou QR Code** e ajude o florescer a crescer.

### 💬 Feedbacks
Deixe sua opinião, reporte problemas e ajude a melhorar a plataforma a cada atualização.

### 👤 Perfil
Gerencie suas informações pessoais, acompanhe seu desempenho geral e **compartilhe suas conquistas nos stories** celebre cada passo da sua evolução.

---

## 🛡️ Painel Administrativo

O florescer conta com um painel admin completo e seguro para gerenciamento da plataforma:

| Seção | Descrição |
|---|---|
| Dashboard | Visão geral da plataforma em tempo real |
| Usuários | Gerenciamento de contas e sessões ativas |
| Simulados | Criação e gestão de questões |
| Frases do dia | Conteúdo motivacional da plataforma |
| Cursos | Gerenciamento da loja de cursos |
| Feedbacks | Acompanhamento e resposta dos alunos |
| Professores | Aprovação de candidatos e gestão de saques |
| Configurações | Configurações gerais da plataforma |

### 🔒 Segurança
- Autenticação com **2FA TOTP** (Google Authenticator)
- **Rate limiting** progressivo com bloqueio exponencial
- **Session fingerprint** contra session hijacking
- **Audit log** completo de todas as ações administrativas
- Proteção contra timing attacks e brute force

---

## 🚀 Em breve  Módulo Professores

O florescer está desenvolvendo um módulo completo de professores particulares integrado à plataforma:

### Para os alunos
- Encontrar professores verificados no **Ranking público**
- Contratar **correção de redações** com feedback detalhado (competências C1–C5, nota até 1000)
- Agendar **aulas particulares** com link liberado automaticamente 5 minutos antes
- Avaliar professores e contribuir com o ranking

### Para os professores
- **Dashboard financeiro** com saldo, extrato e solicitação de saque via Pix
- **Fila de correções** com sistema de notas por competências ENEM
- **Agenda de aulas** com gerenciamento de horários disponíveis
- **Chat seguro** com alunos monitorado contra troca de contato externo
- **Perfil verificado** com diploma, certificados e avaliações públicas
- **Ranking** por avaliação professores premium em destaque

### Modelo de comissão

| Serviço | Professor recebe | Plataforma retém |
|---|---|---|
| Correção de redação | 90% | 10% |
| Aula particular | 80% | 20% |

- Professor define livremente seus preços
- Saque mínimo: **R$ 50,00** · Processamento em até 1 dia útil
- Pagamentos via **Mercado Pago**
- Sistema anti-contato externo com suspensão automática após 3 tentativas

---

## 🗃️ Estrutura do projeto

```
florescer/
├── admin/                  ← Painel administrativo
│   ├── api/                ← APIs do admin
│   ├── includes/           ← Auth e funções
│   └── views/              ← Telas do painel
│
├── api/                    ← APIs da plataforma do aluno
│   ├── auth.php
│   ├── lessons.php
│   ├── objectives.php
│   ├── simulated.php
│   ├── grades.php
│   ├── store.php
│   ├── chat.php
│   ├── timer.php
│   └── ...
│
├── config/                 ← Configurações (não versionado)
│   ├── db.php
│   └── mail.php
│
├── includes/               ← Auth, funções e helpers
│   ├── auth.php
│   ├── functions.php
│   └── session.php
│
├── public/                 ← Área do aluno
│   └── views/
│       ├── dashboard.php
│       ├── objectives.php
│       ├── simulated.php
│       ├── performance.php
│       ├── progress.php
│       ├── history.php
│       ├── work.php
│       ├── works_calendar.php
│       ├── materials.php
│       ├── chat.php
│       ├── store.php
│       ├── feedbacks.php
│       └── profile.php
│
├── teachers/               ← Módulo de professores (em desenvolvimento)
│   ├── api/
│   ├── views/
│   ├── config/
│   └── public/
│
├── vendor/                 ← PHPMailer
└── services/               ← StreakService
```

---

## 🧰 Stack tecnológica

| Camada | Tecnologia |
|---|---|
| Frontend | HTML, CSS, JavaScript |
| Backend | PHP 8.1+ |
| Banco de dados | MySQL |
| E-mail | PHPMailer |
| Pagamentos | Mercado Pago *(em integração)* |
| Hospedagem | Hostinger |

---

## 🔮 Roadmap

- [x] Plataforma de estudos completa ,objetivos, aulas, simulados, notas
- [x] Ferramentas de produtividade, Pomodoro, anotações, calendário, trabalhos
- [x] Gamificação, XP, níveis e streaks
- [x] Chat entre alunos
- [x] Loja de cursos
- [x] Painel admin com 2FA e audit log
- [x] Sistema de feedbacks
- [ ] Módulo de professores, correção de redações e aulas particulares
- [ ] Integração completa com Mercado Pago
- [ ] Correção de redação própria pela plataforma
- [ ] Ranking global de alunos
- [ ] Funcionalidades premium para alunos
- [ ] Notificações por e-mail automáticas
- [ ] App mobile

---

## 👤 Autor

Desenvolvido por **Guilherme Silva**

[![GitHub](https://img.shields.io/badge/GitHub-lGuilherme--7-181717?style=flat&logo=github)](https://github.com/lGuilherme-7)

---

## 📄 Licença

Distribuído sob a licença especificada no arquivo [`LICENSE`](./LICENSE).

---

<div align="center">

**🌱 florescer**

*Plante o hábito. Colha o conhecimento. Floresça de verdade.*

**[florescerapp.com.br](https://florescerapp.com.br/florescer/public/index.php)**

</div>
