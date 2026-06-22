import CrudTable from "../../components/CrudTable.jsx";

export default function StafichasEmprego() {
  const colunas = [
    { key: "id", label: "Nº" },
    { key: "id_viatura", label: "ID Viatura" },
    { key: "motorista", label: "Motorista" },
    { key: "destino", label: "Destino / Missão" },
    { key: "data_saida", label: "Data Saída" },
    { key: "km_saida", label: "KM Saída" },
    { key: "km_retorno", label: "KM Retorno" },
    { key: "status", label: "Status" },
  ];

  const formulario = [
    { name: "id_viatura", label: "ID da Viatura", type: "number", col: 4, required: true },
    { name: "motorista", label: "Motorista Escalado", type: "text", col: 4, required: true },
    { name: "destino", label: "Destino / Missão", type: "text", col: 4, required: true },
    { name: "data_saida", label: "Data de Saída", type: "date", col: 3, required: true },
    { name: "hora_saida", label: "Hora de Saída", type: "time", col: 3 },
    { name: "km_saida", label: "KM Saída", type: "number", col: 3 },
    { name: "status", label: "Status", type: "select", col: 3, required: true, options: [
      { value: "Em Uso", label: "Em Uso (Missão)" },
      { value: "Retornada", label: "Retornada" },
      { value: "Pendente", label: "Aguardando Aprovação" },
    ]},
    { name: "autorizado_por", label: "Autorizado por", type: "text", col: 6 },
    { name: "observacao", label: "Observação", type: "text", col: 6 },
  ];

  return (
    <CrudTable 
      titulo="Emprego dos Meios" 
      subtitulo="Controle de emprego de viaturas e equipamentos em missões" 
      endpoint="crud/sta_fichas" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={false}
    />
  );
}
