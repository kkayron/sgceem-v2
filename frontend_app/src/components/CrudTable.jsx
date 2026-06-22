import { motion } from 'framer-motion';
import { useState, useEffect, useRef } from 'react';
import Swal from 'sweetalert2';
import * as pdfjsLib from 'pdfjs-dist';
import pdfWorkerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

import { useExport } from '../hooks/useExport.js';
import { useExtrator } from '../hooks/useExtrator.js';

// Configuração Nativa do Worker do PDF via Vite (Impede erros de CDN)
pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorkerUrl;

import { apiFetch } from '../utils/api.js';
import PecasSelector from './PecasSelector.jsx';

export default function CrudTable({ titulo, subtitulo, endpoint, colunas, formulario = null, deletavel = false, customColumns = null, filtrosStatus = [], customActions = [] }) {
  const [dados, setDados] = useState([]);
  const [loading, setLoading] = useState(true);
  const [busca, setBusca] = useState('');
  const [statusAtivo, setStatusAtivo] = useState('');
  const fileInputRef = useRef(null);

  // Dynamic config for lazy scaffolding
  const [dynamicColunas, setDynamicColunas] = useState(() => {
    if (customColumns) return customColumns;
    return colunas || [];
  });
  const [dynamicFormulario, setDynamicFormulario] = useState(() => {
    if (customColumns) {
      return customColumns.map(c => ({ ...c, name: c.key }));
    }
    return formulario || [];
  });

  // Pagination State
  const [pagina, setPagina] = useState(1);
  const [paginacao, setPaginacao] = useState({ total: 0, pagina_atual: 1, limite: 50, total_paginas: 1 });

  // Smart Relations Dictionary (to automatically turn foreign keys into selects)
  const relationsDict = {
      'batalhao': { endpoint: 'oms', labelKey: 'abreviatura', valueKey: 'id' },
      'id_marca': { endpoint: 'marcas', labelKey: 'descricao', valueKey: 'id' },
      'id_modelo': { endpoint: 'modelos', labelKey: 'descricao', valueKey: 'id' },
      'funcao': { endpoint: 'funcoes', labelKey: 'nome', valueKey: 'id' },
      'tipo': { endpoint: 'tipos', labelKey: 'nome', valueKey: 'id' },
      'status': { options: [{label: 'Ativo', value: '1'}, {label: 'Inativo', value: '0'}] },
      'ativo': { options: [{label: 'Sim', value: '1'}, {label: 'Não', value: '0'}] }
  };

  // Modal State
  const [showModal, setShowModal] = useState(false);
  const [formMode, setFormMode] = useState('CREATE'); // 'CREATE' or 'EDIT'
  const [formData, setFormData] = useState({});
  const [formId, setFormId] = useState(null);

  // Permissoes do Modulo
  const [permissoes, setPermissoes] = useState(null);

  useEffect(() => {
    try {
      const stored = JSON.parse(localStorage.getItem('sgceem_permissoes') || '{}');
      const modulePath = endpoint.replace('crud/', '').split('?')[0].split('/')[0];
      if (stored[modulePath]) {
        setPermissoes(stored[modulePath]);
      }
    } catch(e) {}
  }, [endpoint]);

  const carregar = (page = pagina, currentStatus = statusAtivo) => {
    setLoading(true);
    const separator = endpoint.includes('?') ? '&' : '?';
    const queryString = `${separator}page=${page}&limit=${paginacao.limite}&busca=${encodeURIComponent(busca)}&status=${encodeURIComponent(currentStatus)}`;
    
      apiFetch(`${endpoint}${queryString}`)
      .then(d => { 
        if (d.status === 'sucesso') {
          const fetchedData = d.dados || [];
          setDados(fetchedData); 
          
          // If parent explicitly passed columns or customColumns, use them strictly. Otherwise, auto-generate.
          if (fetchedData.length > 0) {
              if (!colunas && !customColumns) {
                  const keys = Object.keys(fetchedData[0]);
                  const newCols = keys.map(k => ({ key: k, label: k.toUpperCase().replace(/_/g, ' ') }));
                  setDynamicColunas(newCols);
              }
              
              if (!formulario && !customColumns) {
                  const keys = Object.keys(fetchedData[0]);
                  const newForm = keys.filter(k => k !== 'id').map(k => {
                      const rel = relationsDict[k.toLowerCase()];
                      if (rel) {
                          if (rel.options) {
                              return { name: k, label: k.toUpperCase().replace(/_/g, ' '), type: 'select', col: 6, options: rel.options };
                          }
                          return { name: k, label: k.toUpperCase().replace(/_/g, ' '), type: 'select', col: 6, options: [], asyncRel: rel };
                      }
                      return { name: k, label: k.toUpperCase().replace(/_/g, ' '), type: 'text', col: 6 };
                  });
                  setDynamicFormulario(newForm);
              }
              
              if (d.paginacao) {
                  setPaginacao(d.paginacao);
                  setPagina(d.paginacao.pagina_atual);
              }
          } else {
              if (customColumns) {
                  setDynamicColunas(customColumns);
                  setDynamicFormulario(customColumns.map(c => ({ ...c, name: c.key })));
              } else {
                  setDynamicColunas(colunas || []);
                  setDynamicFormulario(formulario || []);
              }
          }
        } 
      })
      .catch(() => setDados([]))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    carregar();
    const handleDbUpdate = () => {
      if (formMode === null) carregar();
    };
    window.addEventListener('db_updated', handleDbUpdate);

    // Auto-open logic for Dashboard Quick Actions
    if (window.location.hash.includes('autoOpen=true')) {
       setFormMode('CREATE');
       
       if (window.location.hash.includes('extrator=true')) {
          const extratorData = localStorage.getItem('sgceem_extrator_data');
          if (extratorData) {
             setFormData(JSON.parse(extratorData));
             localStorage.removeItem('sgceem_extrator_data');
          } else {
             setFormData({});
          }
       } else {
          setFormData({});
       }
       
       setShowModal(true);
       window.history.replaceState(null, '', window.location.hash.replace('?autoOpen=true', '').replace('&autoOpen=true', '').replace('?extrator=true', '').replace('&extrator=true', ''));
    }

    return () => window.removeEventListener('db_updated', handleDbUpdate);
  }, [endpoint]);

  // Handle Search submit manually to debounce/avoid fetching on every keystroke
  const handleBusca = (e) => {
      if (e.key === 'Enter') carregar(1);
  };

  const abrirModalCadastro = () => {
    carregarRelacoesFormulario();
    setFormMode('CREATE');
    setFormData({});
    setFormId(null);
    setShowModal(true);
  };

  const abrirModalEdicao = (item) => {
    carregarRelacoesFormulario();
    setFormMode('EDIT');
    setFormData(item);
    setFormId(item.id);
    setShowModal(true);
  };

  const carregarRelacoesFormulario = () => {
      // Procura por campos que tem `asyncRel` e preenche eles
      const hasAsync = dynamicFormulario.some(f => f.asyncRel && (!f.options || f.options.length === 0));
      if (!hasAsync) return;
      
      const newForm = [...dynamicFormulario];
      
      newForm.forEach(async (field, idx) => {
          if (field.asyncRel && (!field.options || field.options.length === 0)) {
              try {
                  const r = await apiFetch(field.asyncRel.endpoint);
                  if (r.status === 'sucesso' && r.dados) {
                      newForm[idx].options = r.dados.map(item => ({
                          label: item[field.asyncRel.labelKey] || item.nome || item.descricao || item.id,
                          value: item[field.asyncRel.valueKey] || item.id
                      }));
                      setDynamicFormulario([...newForm]);
                  }
              } catch (e) { console.error('Erro ao buscar relacao', field.name); }
          }
      });
  };

  const salvarFormulario = (e) => {
    e.preventDefault();
    const method = formMode === 'CREATE' ? 'POST' : 'PUT';
    const url = formMode === 'CREATE' ? endpoint : `${endpoint}/${formId}`;

    Swal.fire({ title: 'Salvando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    apiFetch(url, {
      method,
      body: JSON.stringify(formData)
    })
    .then(d => {
      if (d.status === 'sucesso') {
        Swal.fire('Sucesso!', d.mensagem || 'Registro salvo com sucesso.', 'success');
        setShowModal(false);
        carregar();
      } else {
        Swal.fire('Erro!', d.mensagem || 'Falha ao salvar.', 'error');
      }
    })
    .catch(err => Swal.fire('Erro de Conexão', err.message, 'error'));
  };

  const handleChange = (e, field) => {
    let val = e.target.value;
    // Simple mask for CNPJ if requested
    if (field.mask === 'cnpj') {
      val = val.replace(/\D/g, '');
      if (val.length > 14) val = val.slice(0, 14);
      if (val.length > 12) val = val.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2}).*/, "$1.$2.$3/$4-$5");
      else if (val.length > 8) val = val.replace(/^(\d{2})(\d{3})(\d{3})(\d{0,4}).*/, "$1.$2.$3/$4");
      else if (val.length > 5) val = val.replace(/^(\d{2})(\d{3})(\d{0,3}).*/, "$1.$2.$3");
      else if (val.length > 2) val = val.replace(/^(\d{2})(\d{0,3}).*/, "$1.$2");
    }
    setFormData(prev => ({ ...prev, [field.name]: val }));
  };

  const deletar = (id) => {
    Swal.fire({ title: 'Confirmar remoção?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sim', cancelButtonText: 'Cancelar' })
      .then(r => {
        if (r.isConfirmed) {
          apiFetch(`${endpoint}/${id}`, { method: 'DELETE' })
            .then(() => { carregar(); Swal.fire('Removido!', '', 'success'); })
            .catch(() => Swal.fire('Erro', 'Falha ao remover o registro.', 'error'));
        }
      });
  };

  const dadosFiltrados = dados.filter(item => {
    if (!busca) return true;
    return Object.values(item).some(v => String(v || '').toLowerCase().includes(busca.toLowerCase()));
  });

  const { exportarExcel, exportarPDF, imprimir } = useExport(titulo, dynamicColunas, dadosFiltrados);
  const { importarArquivo } = useExtrator(endpoint, carregar, carregarRelacoesFormulario, setFormMode, setFormData, setShowModal);

  return (
    <motion.div className="page-inner" initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: -20 }} transition={{ duration: 0.3 }}>
      <div className="d-flex justify-content-between align-items-center py-3 flex-wrap gap-3">
        <div>
          <h3 className="fw-bold mb-1">{titulo}</h3>
          <h6 className="text-muted">{subtitulo}</h6>
        </div>
        <div className="d-flex gap-2 flex-wrap align-items-center">
          <div className="input-group" style={{ width: 250 }}>
            <input type="text" className="form-control" placeholder="Pesquisar..." value={busca} onChange={e => setBusca(e.target.value)} onKeyDown={handleBusca} />
            <button className="btn btn-outline-secondary" onClick={() => carregar(1)}><i className="fas fa-search"></i></button>
          </div>
          
          <div className="btn-group shadow-sm">
            <button className="btn btn-outline-success" onClick={exportarExcel} title="Exportar Excel"><i className="fas fa-file-excel"></i></button>
            <button className="btn btn-outline-danger" onClick={exportarPDF} title="Exportar PDF"><i className="fas fa-file-pdf"></i></button>
            <button className="btn btn-outline-dark" onClick={imprimir} title="Imprimir"><i className="fas fa-print"></i></button>
          </div>

          <button className="btn btn-secondary shadow-sm" onClick={() => fileInputRef.current.click()} title="Importar">
            <i className="fas fa-upload"></i>
          </button>
          <input type="file" ref={fileInputRef} onChange={(e) => importarArquivo(e, fileInputRef)} accept=".xlsx, .xls, .pdf" style={{ display: 'none' }} />
          
          {dynamicFormulario.length > 0 && (permissoes ? permissoes.can_create : true) && (
            <button className="btn btn-primary shadow-sm" onClick={abrirModalCadastro}>
              <i className="fas fa-plus me-1"></i> Cadastrar
            </button>
          )}

          <button className="btn btn-light shadow-sm" onClick={carregar} title="Atualizar"><i className="fas fa-sync-alt text-primary"></i></button>
        </div>
      </div>

      <div className="card card-round shadow-sm border-0 mt-2">
        <div className="card-body p-0">
          
          {/* Status Chips Filter */}
          {filtrosStatus && filtrosStatus.length > 0 && (
            <div className="d-flex flex-wrap gap-2 mb-3 px-3 mt-3">
              <span className="text-muted small align-self-center me-2 fw-bold">Filtrar:</span>
              <button 
                className={`btn btn-sm rounded-pill px-3 ${statusAtivo === '' ? 'btn-primary' : 'btn-outline-secondary'}`}
                onClick={() => { setStatusAtivo(''); carregar(1, ''); }}
              >
                Todos
              </button>
              {filtrosStatus.map((st, idx) => (
                <button 
                  key={idx} 
                  className={`btn btn-sm rounded-pill px-3 ${statusAtivo === st ? 'btn-primary shadow-sm' : 'btn-outline-secondary'}`}
                  onClick={() => { 
                    const novo = statusAtivo === st ? '' : st; 
                    setStatusAtivo(novo); 
                    carregar(1, novo); 
                  }}
                >
                  {st}
                </button>
              ))}
            </div>
          )}

          {loading ? (
            <div className="text-center py-5"><div className="spinner-border text-primary"></div><p className="mt-2 text-muted fw-bold">Carregando dados seguros...</p></div>
          ) : dadosFiltrados.length === 0 ? (
            <div className="text-center text-muted py-5"><i className="fas fa-folder-open fa-3x mb-3 text-secondary opacity-50"></i><h5>Nenhum registro encontrado</h5></div>
          ) : (
            <div className="table-responsive shadow-sm" style={{ maxHeight: '65vh', overflowY: 'auto', overflowX: 'auto' }}>
              <table className="table table-hover table-borderless align-middle mb-0" style={{ whiteSpace: 'nowrap' }}>
                <thead className="table-light">
                  <tr>
                    {dynamicColunas.map((col, i) => <th key={i} className="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">{col.label}</th>)}
                    {((dynamicFormulario.length > 0 && (permissoes ? permissoes.can_edit : true)) || (deletavel && (permissoes ? permissoes.can_delete : true)) || customActions.length > 0) && <th className="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7" style={{ position: 'sticky', right: 0, backgroundColor: '#f8f9fa', zIndex: 2, boxShadow: '-2px 0 5px rgba(0,0,0,0.05)' }}>Ações</th>}
                  </tr>
                </thead>
                <tbody>
                  {dadosFiltrados.map((item, idx) => (
                    <tr key={item.id || idx}>
                      {dynamicColunas.map((col, i) => <td key={i} className="px-3 py-2">{col.render ? col.render(item) : (item[col.key] ?? '-')}</td>)}
                      {((dynamicFormulario.length > 0 && (permissoes ? permissoes.can_edit : true)) || (deletavel && (permissoes ? permissoes.can_delete : true)) || customActions.length > 0) && (
                        <td className="text-center" style={{ position: 'sticky', right: 0, backgroundColor: '#fff', zIndex: 1, boxShadow: '-2px 0 5px rgba(0,0,0,0.05)' }}>
                          <div className="d-flex justify-content-center gap-1">
                            {customActions.map((act, idxAct) => (
                              <button key={`act-${idxAct}`} className={`btn btn-sm ${act.className || 'btn-outline-info'} border-0`} onClick={() => act.onClick(item)} title={act.tooltip}>
                                {act.icon}
                              </button>
                            ))}
                            {dynamicFormulario.length > 0 && (permissoes ? permissoes.can_edit : true) && (
                              <button className="btn btn-sm btn-outline-primary border-0" onClick={() => abrirModalEdicao(item)} title="Editar"><i className="fa fa-edit"></i></button>
                            )}
                            {deletavel && (permissoes ? permissoes.can_delete : true) && (
                              <button className="btn btn-sm btn-outline-danger border-0" onClick={() => deletar(item.id)} title="Remover"><i className="fa fa-trash"></i></button>
                            )}
                          </div>
                        </td>
                      )}
                    </tr>
                  ))}
                </tbody>
              </table>
              <div className="card-footer bg-light text-muted small d-flex justify-content-between align-items-center">
                <span>
                    {paginacao.total > 0 
                      ? `Exibindo ${dadosFiltrados.length} de ${paginacao.total} registros (Página ${paginacao.pagina_atual} de ${paginacao.total_paginas})`
                      : `Exibindo ${dadosFiltrados.length} registro(s) no total.`
                    }
                </span>
                
                {paginacao.total_paginas > 1 && (
                    <div className="btn-group">
                        <button className="btn btn-sm btn-outline-primary" disabled={pagina === 1} onClick={() => { const p = pagina - 1; setPagina(p); carregar(p); }}>Anterior</button>
                        <button className="btn btn-sm btn-outline-primary" disabled={pagina === paginacao.total_paginas} onClick={() => { const p = pagina + 1; setPagina(p); carregar(p); }}>Próxima</button>
                    </div>
                )}
              </div>
              </div>
          )}
        </div>
      </div>

      {/* Modal de Formulário Genérico */}
      {showModal && dynamicFormulario.length > 0 && (
        <div className="modal fade show d-block" style={{ backgroundColor: 'rgba(0,0,0,0.5)', zIndex: 1050 }}>
          <div className="modal-dialog modal-dialog-centered modal-lg">
            <div className="modal-content border-0 shadow-lg">
              <form onSubmit={salvarFormulario}>
                <div className="modal-header bg-light">
                  <h5 className="modal-title fw-bold text-dark">
                    <i className={`fas ${formMode === 'CREATE' ? 'fa-plus-circle text-primary' : 'fa-edit text-warning'} me-2`}></i>
                    {formMode === 'CREATE' ? `Cadastrar ${titulo}` : `Editar ${titulo}`}
                  </h5>
                  <button type="button" className="btn-close" onClick={() => setShowModal(false)}></button>
                </div>
                <div className="modal-body p-4">
                  <div className="row g-3">
                    {dynamicFormulario.map((f, i) => (
                      <div className={`col-md-${f.col || 12}`} key={i}>
                        <label className="form-label fw-semibold">{f.label}</label>
                        {f.type === 'select' ? (
                          <select 
                            className="form-select shadow-sm" 
                            required={f.required} 
                            value={formData[f.name] || ''} 
                            onChange={(e) => handleChange(e, f)}
                          >
                            <option value="">Selecione...</option>
                            {f.options && f.options.map((opt, oi) => {
                              const val = typeof opt === 'object' ? opt.value : opt;
                              const lbl = typeof opt === 'object' ? opt.label : opt;
                              return <option key={oi} value={val}>{lbl}</option>;
                            })}
                          </select>
                        ) : f.type === 'textarea' ? (
                          <textarea 
                            className="form-control shadow-sm" 
                            required={f.required}
                            value={formData[f.name] || ''}
                            onChange={(e) => handleChange(e, f)}
                            placeholder={f.placeholder || ''}
                            rows="3"
                          ></textarea>
                        ) : f.type === 'pecas_selector' ? (
                          <PecasSelector 
                            value={formData[f.name] || []} 
                            onChange={(pecas) => handleChange({ target: { value: pecas } }, f)}
                          />
                        ) : (
                          <input 
                            type={f.type || 'text'} 
                            className="form-control shadow-sm" 
                            required={f.required}
                            value={formData[f.name] || ''}
                            onChange={(e) => handleChange(e, f)}
                            placeholder={f.placeholder || ''}
                          />
                        )}
                      </div>
                    ))}
                  </div>
                </div>
                <div className="modal-footer bg-light">
                  <button type="button" className="btn btn-secondary" onClick={() => setShowModal(false)}>Cancelar</button>
                  <button type="submit" className="btn btn-success"><i className="fas fa-save me-1"></i> {formMode === 'CREATE' ? 'Cadastrar' : 'Salvar Alterações'}</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}
    </motion.div>
  );
}
