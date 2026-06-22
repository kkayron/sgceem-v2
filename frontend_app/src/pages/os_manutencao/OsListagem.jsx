import CrudTable from "../../components/CrudTable.jsx";

export default function OsListagem() {
  const colunas = [
    { key: "id", label: "Nº OS" },
    { key: "placa_vtr", label: "Viatura (Placa)" },
    { key: "data_abertura", label: "Data Abertura" },
    { key: "status", label: "Status" }
  ];

  const formulario = [
    { name: "placa_vtr", label: "Placa da Viatura", type: "text", col: 6, required: true },
    { name: "data_abertura", label: "Data de Abertura", type: "date", col: 6, required: true },
    { name: "mecanico_responsavel", label: "Mecânico Responsável", type: "text", col: 6 },
    { name: "status", label: "Status da OS", type: "select", col: 6, options: [
        { value: "Aberto", label: "Aberto (Na fila)" },
        { value: "Em Andamento", label: "Em Andamento" },
        { value: "Concluído", label: "Concluído (Pronto)\ " }
    ]},
    { name: "descricao_defeito", label: "Descrição do Defeito", type: "text", col: 12, required: true },
    { name: "pecas", label: "Consumo de Peças do Almoxarifado", type: "pecas_selector", col: 12 }
  ];

  return (
    <CrudTable 
      titulo="Ordens de Serviço" 
      subtitulo="Controle de ordens de serviço de manutenção" 
      endpoint="crud/os_principal" 
      colunas={colunas} 
      formulario={formulario} 
      deletavel={true}
    />
  );
}
