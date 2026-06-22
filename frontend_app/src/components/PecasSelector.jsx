import React, { useState, useEffect } from 'react';
import { apiFetch } from '../utils/api.js';
import { Search, Plus, Trash2, Box } from 'lucide-react';

export default function PecasSelector({ value = [], onChange }) {
  const [pecasSelecionadas, setPecasSelecionadas] = useState(Array.isArray(value) ? value : []);
  const [produtosDisponiveis, setProdutosDisponiveis] = useState([]);
  const [busca, setBusca] = useState('');
  const [qtdInput, setQtdInput] = useState({});

  useEffect(() => {
    // Busca peças no almoxarifado (o RLS do backend já garante filtrar pelo batalhão)
    apiFetch('crud/almox_produtos?limit=500')
      .then(res => {
        if (res.status === 'sucesso') {
          setProdutosDisponiveis(res.dados || []);
        }
      })
      .catch(err => console.error("Erro ao buscar peças:", err));
  }, []);

  // Sincroniza com o FormData pai
  useEffect(() => {
    onChange(pecasSelecionadas);
  }, [pecasSelecionadas]);

  const addPeca = (produto) => {
    const qtd = parseInt(qtdInput[produto.id]) || 1;
    if (qtd <= 0) return;

    // Impede adicionar além do estoque se não for admin (Ignorado no frontend por enquanto, backend valida)
    if (qtd > produto.estoque_atual) {
       // Alert could be better, but let's keep it simple
       alert(`Atenção: A quantidade excede o estoque atual (${produto.estoque_atual}).`);
    }

    setPecasSelecionadas(prev => {
      const exists = prev.find(p => p.id_peca === produto.id);
      if (exists) {
        return prev.map(p => p.id_peca === produto.id ? { ...p, quantidade: p.quantidade + qtd } : p);
      }
      return [...prev, { id_peca: produto.id, nome: produto.nome, quantidade: qtd }];
    });

    setQtdInput(prev => ({ ...prev, [produto.id]: '' })); // Limpa o input
  };

  const removePeca = (id_peca) => {
    setPecasSelecionadas(prev => prev.filter(p => p.id_peca !== id_peca));
  };

  const handleQtdChange = (id, val) => {
    setQtdInput(prev => ({ ...prev, [id]: val }));
  };

  const filtrados = produtosDisponiveis.filter(p => 
    p.nome?.toLowerCase().includes(busca.toLowerCase()) || 
    p.part_number?.toLowerCase().includes(busca.toLowerCase()) ||
    p.nsn?.toLowerCase().includes(busca.toLowerCase())
  );

  return (
    <div className="border rounded-4 p-3 mb-3 bg-light" style={{ borderColor: '#e2e8f0' }}>
      <div className="d-flex align-items-center mb-3">
        <Box size={20} className="text-primary me-2" />
        <h6 className="fw-bolder mb-0 text-dark">Peças do Almoxarifado</h6>
      </div>

      <div className="row">
        {/* Lado Esquerdo: Busca e Lista de Peças */}
        <div className="col-12 col-md-6 border-end">
          <div className="input-icon mb-3">
            <span className="input-icon-addon"><Search size={16} className="text-muted" /></span>
            <input 
              type="text" 
              className="form-control bg-white" 
              placeholder="Buscar por Nome, NSN ou PN..." 
              value={busca}
              onChange={(e) => setBusca(e.target.value)}
            />
          </div>

          <div className="list-group list-group-flush rounded border" style={{ maxHeight: '250px', overflowY: 'auto' }}>
            {filtrados.length === 0 ? (
              <div className="p-3 text-center text-muted small">Nenhuma peça encontrada.</div>
            ) : (
              filtrados.map(p => (
                <div key={p.id} className="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-2">
                  <div style={{ maxWidth: '60%' }}>
                    <div className="fw-bold text-dark text-truncate" style={{ fontSize: '14px' }}>{p.nome}</div>
                    <div className="text-muted" style={{ fontSize: '11px' }}>Estoque: {p.estoque_atual} {p.unidade_medida || 'UN'}</div>
                  </div>
                  <div className="d-flex align-items-center gap-2">
                    <input 
                      type="number" 
                      className="form-control form-control-sm text-center" 
                      style={{ width: '60px' }} 
                      placeholder="Qtd" 
                      min="1"
                      value={qtdInput[p.id] || ''}
                      onChange={(e) => handleQtdChange(p.id, e.target.value)}
                    />
                    <button type="button" className="btn btn-sm btn-primary px-2" onClick={() => addPeca(p)}>
                      <Plus size={16} />
                    </button>
                  </div>
                </div>
              ))
            )}
          </div>
        </div>

        {/* Lado Direito: Peças Selecionadas (Carrinho) */}
        <div className="col-12 col-md-6">
          <h6 className="fw-bold text-secondary mb-3 mt-3 mt-md-0" style={{ fontSize: '13px' }}>Peças Incluídas nesta O.S.</h6>
          
          {pecasSelecionadas.length === 0 ? (
            <div className="p-4 text-center border rounded border-dashed bg-white">
              <span className="text-muted small">Nenhuma peça selecionada para aplicação.</span>
            </div>
          ) : (
            <ul className="list-group list-group-flush border rounded">
              {pecasSelecionadas.map(sel => (
                <li key={sel.id_peca} className="list-group-item d-flex justify-content-between align-items-center bg-white p-2">
                  <div>
                    <span className="fw-bold text-dark" style={{ fontSize: '13px' }}>{sel.nome}</span>
                  </div>
                  <div className="d-flex align-items-center gap-3">
                    <span className="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-3 py-1">
                      {sel.quantidade} UN
                    </span>
                    <button type="button" className="btn btn-sm btn-link text-danger p-0" onClick={() => removePeca(sel.id_peca)}>
                      <Trash2 size={16} />
                    </button>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>
    </div>
  );
}
