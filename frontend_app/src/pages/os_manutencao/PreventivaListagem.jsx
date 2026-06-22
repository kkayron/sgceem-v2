import CrudTable from "../../components/CrudTable.jsx";

export default function PreventivaListagem() {
  const colunas = [
    { key: "id", label: "Nº" },
    { key: "id_viatura", label: "ID Viatura" },
    { key: "tipo_manutencao", label: "Tipo Manutenção" },
    { key: "descricao", label: "Descrição do Serviço" },
    { key: "km_realizada", label: "KM Realizada" },
    { key: "data_realizada", label: "Data Realizada" },
    { key: "proxima_km", label: "Próxima KM" },
    { key: "proxima_data", label: "Próxima Data" },
    { key: "status", label: "Status" },
  ];

  const formulario = [
    { name: "id_viatura", label: "ID da Viatura", type: "number", col: 4, required: true },
    { name: "tipo_manutencao", label: "Tipo de Manutenção", type: "select", col: 4, required: true, options: [
      { value: "Troca de Óleo", label: "Troca de Óleo" },
      { value: "Filtros", label: "Troca de Filtros" },
      { value: "Freios", label: "Revisão de Freios" },
      { value: "Suspensão", label: "Suspensão" },
      { value: "Elétrica", label: "Elétrica" },
      { value: "Pneus", label: "Rodízio/Troca de Pneus" },
      { value: "Revisão Geral", label: "Revisão Geral" },
      { value: "Outro", label: "Outro" },
    ]},
    { name: "status", label: "Status", type: "select", col: 4, required: true, options: [
      { value: "Pendente", label: "Pendente" },
      { value: "Realizada", label: "Realizada" },
      { value: "Atrasada", label: "Atrasada" },
    ]},
    { name: "descricao", label: "Descrição do Serviço", type: "text", col: 12, required: true },
    { name: "km_realizada", label: "KM na Realização", type: "number", col: 3 },
    { name: "data_realizada", label: "Data Realizada", type: "date", col: 3 },
    { name: "proxima_km", label: "Próxima KM Prevista", type: "number", col: 3 },
    { name: "proxima_data", label: "Próxima Data Prevista", type: "date", col: 3 },
    { name: "observacoes", label: "Observações", type: "text", col: 12 },
  ];

  return (
    <CrudTable 
      titulo="Manutenção Preventiva" 
      subtitulo="Controle de manutenções preventivas da frota" 
      endpoint="crud/manutencao" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={true}
    />
  );
}
