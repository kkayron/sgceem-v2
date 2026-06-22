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

---
> Desenvolvido e Arquitetado por **Kayron Santos** (@kkayron)
