import CrudTable from "../../components/CrudTable.jsx";

export default function FinpregoesListagem() {
  const colunas = [
    { key: "id", label: "ID" },
    { key: "nmr_pregao", label: "Nº Pregão" },
    { key: "descricao_pregao", label: "Descrição" },
    { key: "ano_pregao", label: "Ano" },
    { key: "tipo_pregao", label: "Tipo" },
    { key: "data_homologacao", label: "Homologação" },
    { key: "data_validade", label: "Validade" },
  ];

  const formulario = [
    { name: "nmr_pregao", label: "Número do Pregão", type: "text", col: 4, required: true, placeholder: "Ex: PE 001/2026" },
    { name: "descricao_pregao", label: "Descrição / Objeto", type: "text", col: 8, required: true },
    { name: "ano_pregao", label: "Ano", type: "number", col: 3, required: true, placeholder: "2026" },
    { name: "tipo_pregao", label: "Tipo", type: "select", col: 3, required: true, options: [
      { value: "Eletrônico", label: "Pregão Eletrônico" },
      { value: "Presencial", label: "Pregão Presencial" },
      { value: "ARP", label: "Ata de Registro de Preços" },
      { value: "Dispensa", label: "Dispensa de Licitação" },
    ]},
    { name: "data_homologacao", label: "Data de Homologação", type: "date", col: 3 },
    { name: "data_validade", label: "Data de Validade", type: "date", col: 3 },
    { name: "orgao_gerenciador", label: "Órgão Gerenciador", type: "text", col: 6 },
    { name: "observacao", label: "Observações", type: "text", col: 6 },
  ];

  return (
    <CrudTable 
      titulo="Pregões e Licitações" 
      subtitulo="Controle de pregões, atas e processos licitatórios" 
      endpoint="crud/fin_pregao" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={true}
    />
  );
}
