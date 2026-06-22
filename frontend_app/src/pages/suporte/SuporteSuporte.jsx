import { motion } from 'framer-motion';

export default function SuporteSuporte() {
  const faqs = [
    { q: "Como cadastrar uma nova viatura?", a: "Acesse Gestão de Frota → Cadastro. Preencha placa, modelo, marca e status, e clique em 'Cadastrar'." },
    { q: "Como exportar dados para Excel?", a: "Em qualquer listagem, clique no botão verde com ícone de Excel no topo da tabela. O download começará automaticamente." },
    { q: "Como resetar a senha de um usuário?", a: "Apenas o Administrador pode resetar senhas. Acesse Administração → Cadastrar Usuário, selecione o usuário e use a opção de reset." },
    { q: "Por que não consigo ver alguns módulos?", a: "O sistema possui controle de acesso (RBAC). Se um módulo não aparece para você, significa que sua função não possui permissão. Solicite ao Administrador." },
    { q: "O que significa 'Mês Fechado'?", a: "Quando o Admin ativa o Fechamento de Mês, operações de escrita (criar/editar) são bloqueadas para fins de balanço. Apenas Admin e Desenvolvedor podem operar." },
    { q: "Como abrir uma Ordem de Serviço?", a: "Acesse Ordens de Serviço → Controle de OS. Clique em 'Cadastrar', informe a placa da viatura, data e descrição do defeito." },
  ];

  const contatos = [
    { icon: "fas fa-envelope", label: "E-mail", value: "suporte@sgceem.mil.br" },
    { icon: "fas fa-phone", label: "Ramal", value: "2367 / 2368" },
    { icon: "fas fa-clock", label: "Expediente", value: "Seg-Sex, 07:30 às 17:00" },
  ];

  return (
    <motion.div className="page-inner" initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.3 }}>
      <div className="d-flex justify-content-between align-items-center py-3">
        <div>
          <h3 className="fw-bold mb-1">Central de Ajuda</h3>
          <h6 className="text-muted">Perguntas frequentes e canais de suporte</h6>
        </div>
      </div>

      <div className="row g-4">
        <div className="col-md-8">
          <div className="card border-0 shadow-sm" style={{ borderRadius: 16 }}>
            <div className="card-header bg-white border-0 pt-4 px-4">
              <h5 className="fw-bold mb-0"><i className="fas fa-question-circle text-primary me-2"></i>Perguntas Frequentes (FAQ)</h5>
            </div>
            <div className="card-body px-4 pb-4">
              <div className="accordion" id="faqAccordion">
                {faqs.map((faq, i) => (
                  <div className="accordion-item border-0 mb-2" key={i}>
                    <h2 className="accordion-header">
                      <button className="accordion-button collapsed fw-semibold bg-light rounded-3" type="button" data-bs-toggle="collapse" data-bs-target={`#faq-${i}`}>
                        {faq.q}
                      </button>
                    </h2>
                    <div id={`faq-${i}`} className="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                      <div className="accordion-body text-muted">{faq.a}</div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>

        <div className="col-md-4">
          <div className="card border-0 shadow-sm" style={{ borderRadius: 16 }}>
            <div className="card-header bg-white border-0 pt-4 px-4">
              <h5 className="fw-bold mb-0"><i className="fas fa-headset text-success me-2"></i>Contato</h5>
            </div>
            <div className="card-body px-4 pb-4">
              <div className="d-flex flex-column gap-3">
                {contatos.map((c, i) => (
                  <div key={i} className="d-flex align-items-center gap-3 p-3 bg-light rounded-3">
                    <i className={`${c.icon} text-primary`} style={{ fontSize: 20, width: 28 }}></i>
                    <div>
                      <small className="text-muted d-block">{c.label}</small>
                      <span className="fw-semibold">{c.value}</span>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>

          <div className="card border-0 shadow-sm mt-4" style={{ borderRadius: 16, background: 'linear-gradient(135deg, #1a237e, #283593)', color: 'white' }}>
            <div className="card-body p-4 text-center">
              <i className="fas fa-tools mb-3" style={{ fontSize: 36 }}></i>
              <h5 className="fw-bold">Problema Urgente?</h5>
              <p className="mb-3 opacity-75" style={{ fontSize: 14 }}>Para problemas críticos que impeçam a operação do sistema, contate diretamente o Setor de TI.</p>
              <div className="bg-white bg-opacity-10 rounded-3 p-2">
                <span className="fw-bold">Ramal Emergencial: 2300</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </motion.div>
  );
}
