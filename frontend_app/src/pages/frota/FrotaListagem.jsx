import CrudTable from "../../components/CrudTable.jsx";

export default function FrotaListagem() {
  const colunas = [
    { key: "id", label: "ID" },
    { key: "placa", label: "Placa / Prefixo" },
    { key: "modelo", label: "Veículo/Modelo" },
    { key: "subunidade", label: "Subunidade" },
    { key: "status", label: "Status Operacional" }
  ];

  const formulario = [
    { name: "placa", label: "Placa da Viatura", type: "text", col: 6, required: true },
    { name: "prefixo_sga", label: "Prefixo SGA", type: "text", col: 6 },
    { name: "marca", label: "Marca", type: "text", col: 6 },
    { name: "modelo", label: "Modelo", type: "text", col: 6, required: true },
    { name: "ano", label: "Ano de Fabricação", type: "number", col: 4 },
    { name: "chassi", label: "Chassi", type: "text", col: 4 },
    { name: "renavam", label: "Renavam", type: "text", col: 4 },
    { name: "subunidade", label: "Subunidade Destino", type: "text", col: 6 },
    { name: "status", label: "Status Atual", type: "select", col: 6, options: [
        { value: "Disponível", label: "Disponível (Pronta para uso)" },
        { value: "Em Manutenção", label: "Em Manutenção (Oficina)" },
        { value: "Baixada", label: "Baixada (Inoperante)" }
    ]}
  ];

  return (
    <CrudTable 
      titulo="Listagem de Frota" 
      subtitulo="Viaturas e equipamentos cadastrados" 
      endpoint="crud/frota" 
      colunas={colunas} 
      formulario={formulario} 
      deletavel={true}
    />
  );
}
