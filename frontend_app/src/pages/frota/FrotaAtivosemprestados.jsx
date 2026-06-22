import CrudTable from "../../components/CrudTable.jsx";

export default function FrotaAtivosemprestados() {
  const colunas = [
    { key: "id", label: "ID" },
    { key: "placa", label: "Placa" },
    { key: "prefixo_sga", label: "Prefixo SGA" },
    { key: "marca", label: "Marca" },
    { key: "modelo", label: "Modelo" },
    { key: "subunidade", label: "Destino Empréstimo" },
    { key: "status", label: "Status" },
    { key: "disponibilidade", label: "Disponibilidade" },
  ];

  const formulario = [
    { name: "placa", label: "Placa", type: "text", col: 4, required: true },
    { name: "prefixo_sga", label: "Prefixo SGA", type: "text", col: 4 },
    { name: "modelo", label: "Modelo", type: "text", col: 4 },
    { name: "subunidade", label: "OM/Subunidade de Destino", type: "text", col: 6, required: true },
    { name: "disponibilidade", label: "Previsão de Retorno", type: "text", col: 6, placeholder: "Ex: 30/07/2026" },
    { name: "status", label: "Status", type: "select", col: 6, options: [
      { value: "Emprestada", label: "Emprestada" },
      { value: "Devolvida", label: "Devolvida" },
    ]},
  ];

  return (
    <CrudTable 
      titulo="Ativos Emprestados" 
      subtitulo="Controle de viaturas e equipamentos emprestados a outras OMs" 
      endpoint="crud/frota" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={false}
    />
  );
}
