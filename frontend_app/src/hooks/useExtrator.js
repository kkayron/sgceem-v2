import Swal from 'sweetalert2';
import * as XLSX from 'xlsx';
import { apiFetch } from '../utils/api.js';

export function useExtrator(endpoint, carregar, carregarRelacoesFormulario, setFormMode, setFormData, setShowModal) {
  const importarArquivo = async (e, fileInputRef) => {
    const file = e.target.files[0];
    if (!file) return;
    
    Swal.fire({ title: 'Processando...', html: 'Lendo dados do arquivo', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
      if (file.name.endsWith('.xlsx') || file.name.endsWith('.xls')) {
        const data = await file.arrayBuffer();
        const workbook = XLSX.read(data);
        const worksheet = workbook.Sheets[workbook.SheetNames[0]];
        const json = XLSX.utils.sheet_to_json(worksheet);
        
        const response = await apiFetch(`${endpoint.replace('crud/', 'crud_import/')}`, 'POST', { rows: json });
        
        if (response.status === 'sucesso') {
          Swal.fire('Planilha Importada!', `Foram salvos ${response.inseridos} registros com sucesso.`, 'success');
          carregar(1);
        } else {
          Swal.fire('Erro na Importação', response.mensagem || 'Falha ao salvar no banco.', 'error');
        }
      } else if (file.name.toLowerCase().endsWith('.pdf') || file.name.toLowerCase().endsWith('.xml')) {
        const formData = new FormData();
        formData.append('documento', file);
        
        Swal.fire({ title: 'Processando com IA Python...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        
        try {
            const token = localStorage.getItem('sgceem_token');
            const res = await fetch('/api/v1/extract_document', {
                method: 'POST',
                headers: { 'Authorization': `Bearer ${token}` },
                body: formData
            });
            const data = await res.json();
            
            if (data.status === 'success') {
                const extracao = data.dados;
                
                let htmlResumo = `
                  <div class="text-start small">
                    <b>Tipo:</b> ${data.tipo}<br>
                    <b>CNPJ:</b> ${extracao.cnpj_fornecedor || 'Não encontrado'}<br>
                    <b>Fornecedor:</b> ${extracao.nome_empresa || 'Não encontrado'}<br>
                    <b>Nº Documento:</b> ${extracao.numero_nf || 'Não encontrado'}<br>
                    ${extracao.chave_acesso ? `<b>Chave de Acesso:</b> ${extracao.chave_acesso}<br>` : ''}
                    ${extracao.natureza_despesa ? `<b>Natureza da Despesa:</b> ${extracao.natureza_despesa}<br>` : ''}
                    ${extracao.data_emissao ? `<b>Data de Emissão:</b> ${extracao.data_emissao}<br>` : ''}
                    <hr class="my-2">
                    ${extracao.descricao_empenho ? `<b>Descrição:</b> <small>${extracao.descricao_empenho}</small><br>` : ''}
                    <b>Item de Compra/Serviço:</b> ${extracao.item_descricao}<br>
                    <b>Quantidade:</b> ${extracao.quantidade_item}<br>
                    <b>Valor Unitário:</b> R$ ${extracao.valor_unitario || extracao.valor_produto}<br>
                    <b class="text-success">Valor Total:</b> R$ ${extracao.valor_total}
                  </div>
                `;

                Swal.fire({
                  title: `Extrator IA: ${data.tipo}`,
                  html: htmlResumo,
                  icon: 'success',
                  confirmButtonText: 'Preencher Formulário'
                }).then((result) => {
                  if(result.isConfirmed) {
                      carregarRelacoesFormulario();
                      setFormMode('CREATE');
                      setFormData({ 
                        ...extracao,
                        nmr_empenho: data.tipo === 'Nota de Empenho' ? extracao.numero_nf : ''
                      });
                      setShowModal(true);
                  }
                });
            } else {
                Swal.fire('Erro na Extração Python', data.message || 'Falha ao processar.', 'error');
            }
        } catch (e) {
            Swal.fire('Erro de Servidor', 'Falha ao conectar com o Motor Python de IA.', 'error');
        }
      } else {
        Swal.fire('Erro', 'Formato não suportado. Use .xlsx, .pdf ou .xml', 'error');
      }
    } catch (error) {
      Swal.fire('Erro na Leitura', error.message, 'error');
    }
    if (fileInputRef && fileInputRef.current) {
        fileInputRef.current.value = '';
    }
  };

  return { importarArquivo };
}
