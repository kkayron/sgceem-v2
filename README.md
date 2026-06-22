# SGCEEM v2.0 - Sistema de Gestão e Controle de Frotas e Empenhos Militares

O **SGCEEM v2.0** é a evolução moderna e blindada do antigo sistema legado de gestão de Organizações Militares. Trata-se de um Action-Driven Command Center (Centro de Comando Orientado a Ações) projetado para otimizar os módulos de Frota, Almoxarifado e Financeiro com máxima segurança e performance.

## 🚀 O que mudou? (Legado vs v2.0)

A arquitetura passou por uma reescrita profunda, abandonando os gargalos do passado e adotando o estado da arte do desenvolvimento web full-stack.

| Critério | ⚠️ Versão Antiga (Legado) | 🛡️ Versão 2.0 (Atual) |
| :--- | :--- | :--- |
| **Padrão de UI/UX** | Bootstrap Básico e páginas estáticas (Recarregamento F5). | **React (Vite) + SPA.** Interface Modular (Cockpit) com transições fluidas e estado global. |
| **Comunicação com o Servidor** | Envio de formulários POST pesados. HTML misturado com PHP. | **API REST (JSON)** com `Bramus Router`. Arquitetura 100% Desacoplada (Frontend/Backend independentes). |
| **Segurança e Autenticação** | Sessões em PHP (`$_SESSION`) vulneráveis a Hijacking. | **JSON Web Tokens (JWT) com Blacklist Inteligente**. Tokens revogados instantaneamente ao logout. |
| **Controle de Acesso de Dados** | Filtros manuais de Batalhão (`WHERE om_id = X`) suscetíveis a esquecimentos (vazamentos). | **RLS (Row-Level Security) Virtual Engine.** O backend injeta o Batalhão automaticamente via Engine de Modelos (`BaseModel.php`). Escudo Phantom Column. |
| **Gestão do Banco de Dados** | Conexões abertas dispersas em cada arquivo. Queries manuais repetitivas. | **ORM Customizado (`BaseModel`) com Segurança Anti-SQL Injection.** Operações CRUD automáticas com suporte a chaves primárias compostas. |
| **Lógica de Automação (Ação)** | Menus passivos. O usuário procurava o que precisava. | **Action-Driven Design.** O painel detecta estoque crítico ou OS pendentes e gera *Cards de Ação* para guiar a operação diária. |

## 🛠️ Tecnologias e Stack

### Frontend
* **React 18** (Vite Engine)
* **React Router DOM** (Navegação SPA)
* **Lucide Icons** (Iconografia Militar e Moderna)
* **Tailwind CSS / Custom CSS** (Painéis Dark Mode e Microanimações)

### Backend (Central API)
* **PHP 8.2+** (Orientado a Objetos)
* **Bramus Router** (Roteamento Rápido de APIs)
* **Firebase JWT** (Autenticação Stateless)
* **PrintJS / PDFMake** (Emissão de Dossiês Oficiais de Viatura)
* **MySQL/MariaDB** (Armazenamento Relacional Estruturado)

## 📦 Features de Destaque
- **Seletor Inteligente de Peças:** Integra o Almoxarifado com as Ordens de Serviço (Oficina) abatendo o estoque em tempo real.
- **Painel de Deus (Godmode):** Interface de visualização panorâmica de logs e tokens ativos protegida por blindagem RCE.
- **Dossiê em PDF:** Exportação formal do Livro Histórico de Viaturas com um clique.
- **Fábrica Dinâmica de Formulários:** Componente genérico (`CrudTable.jsx`) capaz de renderizar formulários e tabelas para qualquer entidade do banco de dados baseando-se em esquemas JSON predefinidos.

## 💻 Como Rodar Localmente (Ambiente de Desenvolvimento)

Para executar o SGCEEM v2.0 na sua máquina local, você precisará de **Node.js**, **PHP 8.2+** e **Composer** previamente instalados e adicionados às variáveis de ambiente (PATH).
> **Nota:** Os passos abaixo são 100% compatíveis com **Windows (CMD/PowerShell)**, **Linux (Terminal)** e **macOS**.

### 1. Configurando o Banco de Dados
- O sistema já possui um arquivo `.env` configurado na raiz para um banco de dados PostgreSQL na nuvem (Supabase).
- Se você desejar rodar testes com um banco local totalmente isolado, suba uma instância do PostgreSQL, importe o arquivo `sgceem_v2_pg.sql` contido na raiz e atualize as credenciais (`DB_HOST`, `DB_USERNAME`, etc.) no seu `.env`.

### 2. Iniciando o Servidor Backend (API)
Abra o seu terminal (CMD/PowerShell no Windows ou Terminal no Linux) na pasta raiz do projeto e execute:
```sh
cd backend
composer install
php -S localhost:8000 router.php
```

### 3. Iniciando o Servidor Frontend (React/Vite)
Abra um **segundo terminal** na pasta raiz do projeto e execute:
```sh
cd frontend_app
npm install
npm run dev
```

Após isso, o painel do sistema estará disponível no seu navegador em `http://localhost:5173` (ou a porta informada pelo Vite).

---
> Desenvolvido e Arquitetado por **Kayron Santos** (@kkayron)
