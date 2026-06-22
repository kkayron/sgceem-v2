import CrudTable from "../../components/CrudTable.jsx";

export default function MarcasmodelosListagem() {
  const colunas = [
    { key: "id", label: "ID" },
    { key: "descricao", label: "Marca" },
    { key: "tipo", label: "Tipo" },
    { key: "ativo", label: "Ativo" },
  ];

  const formulario = [
    { name: "descricao", label: "Nome da Marca", type: "text", col: 6, required: true, placeholder: "Ex: Toyota, Mercedes-Benz" },
    { name: "tipo", label: "Tipo", type: "select", col: 3, options: [
      { value: "VTR", label: "Viatura" },
      { value: "EQP", label: "Equipamento" },
      { value: "Ambos", label: "Ambos" },
    ]},
    { name: "ativo", label: "Ativo", type: "select", col: 3, options: [
      { value: "1", label: "Sim" },
      { value: "0", label: "Não" },
    ]},
  ];

  return (
    <CrudTable 
      titulo="Marcas e Modelos" 
      subtitulo="Cadastro de marcas de viaturas e equipamentos" 
      endpoint="crud/config_marcas" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={true}
    />
  );
}
