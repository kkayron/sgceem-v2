import CrudTable from "../../components/CrudTable.jsx";

export default function StafichasListagem() {
  const colunas = [
    { key: "id", label: "Nº Ficha" },
    { key: "id_viatura", label: "ID Viatura" },
    { key: "motorista", label: "Motorista" },
    { key: "destino", label: "Destino" },
    { key: "data_saida", label: "Data Saída" },
    { key: "hora_saida", label: "Hora Saída" },
    { key: "data_retorno", label: "Data Retorno" },
    { key: "hora_retorno", label: "Hora Retorno" },
    { key: "km_saida", label: "KM Saída" },
    { key: "km_retorno", label: "KM Retorno" },
    { key: "status", label: "Status" },
  ];

  const formulario = [
    { name: "id_viatura", label: "ID da Viatura", type: "number", col: 4, required: true },
    { name: "motorista", label: "Motorista", type: "text", col: 4, required: true },
    { name: "destino", label: "Destino", type: "text", col: 4, required: true },
    { name: "data_saida", label: "Data de Saída", type: "date", col: 3, required: true },
    { name: "hora_saida", label: "Hora de Saída", type: "time", col: 3 },
    { name: "data_retorno", label: "Data de Retorno", type: "date", col: 3 },
    { name: "hora_retorno", label: "Hora de Retorno", type: "time", col: 3 },
    { name: "km_saida", label: "KM Saída", type: "number", col: 3 },
    { name: "km_retorno", label: "KM Retorno", type: "number", col: 3 },
    { name: "status", label: "Status", type: "select", col: 3, required: true, options: [
      { value: "Em Uso", label: "Em Uso" },
      { value: "Retornada", label: "Retornada" },
      { value: "Pendente", label: "Pendente" },
    ]},
    { name: "autorizado_por", label: "Autorizado por", type: "text", col: 3 },
    { name: "observacao", label: "Observação / Missão", type: "text", col: 12 },
  ];

  return (
    <CrudTable 
      titulo="Fichas STA" 
      subtitulo="Controle de fichas de emprego dos meios (Saída/Retorno)" 
      endpoint="crud/sta_fichas" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={true}
    />
  );
}
