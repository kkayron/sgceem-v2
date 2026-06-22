import { motion } from 'framer-motion';
import { Link } from 'react-router-dom';

export default function UnderConstruction() {
  return (
    <motion.div 
      className="page-inner"
      initial={{ opacity: 0, scale: 0.9 }}
      animate={{ opacity: 1, scale: 1 }}
      exit={{ opacity: 0, scale: 0.9 }}
      transition={{ duration: 0.3 }}
    >
      <div className="d-flex justify-content-center align-items-center flex-column" style={{ minHeight: '60vh', textAlign: 'center' }}>
        <i className="fas fa-tools text-warning mb-4" style={{ fontSize: '80px' }}></i>
        <h2 className="fw-bold text-dark">Módulo em Refatoração</h2>
        <p className="text-muted fs-5 mb-4" style={{ maxWidth: '600px' }}>
          Este módulo ainda está rodando na arquitetura antiga (PHP Monolítico) e será migrado para o novo motor React em breve.
        </p>
        <Link to="/" className="btn btn-primary btn-round btn-lg">
          <i className="fas fa-arrow-left me-2"></i> Voltar ao Painel Central
        </Link>
      </div>
    </motion.div>
  );
}
