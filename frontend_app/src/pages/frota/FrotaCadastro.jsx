import CrudTable from "../../components/CrudTable.jsx";

export default function FrotaCadastro() {
  const colunas = [
    { key: "id", label: "ID" },
    { key: "placa", label: "Placa" },
    { key: "prefixo_sga", label: "Prefixo SGA" },
    { key: "marca", label: "Marca" },
    { key: "modelo", label: "Modelo" },
    { key: "ano", label: "Ano" },
    { key: "chassi", label: "Chassi" },
    { key: "status", label: "Status" },
  ];

  const formulario = [
    { name: "placa", label: "Placa da Viatura", type: "text", col: 4, required: true, placeholder: "ABC-1234" },
    { name: "prefixo_sga", label: "Prefixo SGA", type: "text", col: 4, required: true },
    { name: "marca", label: "Marca", type: "text", col: 4, required: true },
    { name: "modelo", label: "Modelo", type: "text", col: 4, required: true },
    { name: "ano", label: "Ano de Fabricação", type: "number", col: 4 },
    { name: "chassi", label: "Chassi", type: "text", col: 4 },
    { name: "renavam", label: "Renavam", type: "text", col: 4 },
    { name: "cor", label: "Cor", type: "text", col: 4 },
    { name: "combustivel", label: "Combustível", type: "select", col: 4, options: [
      { value: "Diesel", label: "Diesel" },
      { value: "Gasolina", label: "Gasolina" },
      { value: "Flex", label: "Flex" },
      { value: "Elétrico", label: "Elétrico" },
    ]},
    { name: "subunidade", label: "Subunidade Destino", type: "text", col: 6 },
    { name: "status", label: "Status", type: "select", col: 6, required: true, options: [
      { value: "Disponível", label: "Disponível" },
      { value: "Em Manutenção", label: "Em Manutenção" },
      { value: "Baixada", label: "Baixada" },
      { value: "Emprestada", label: "Emprestada" },
    ]},
  ];

  return (
    <CrudTable 
      titulo="Cadastro de Viaturas" 
      subtitulo="Cadastro completo de viaturas e equipamentos" 
      endpoint="crud/frota" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={true}
    />
  );
}
