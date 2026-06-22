import CrudTable from "../../components/CrudTable.jsx";

export default function AlmoxdepositosListagem() {
  const colunas = [
    { key: "id", label: "ID" },
    { key: "nome", label: "Nome do Depósito" },
    { key: "localizacao", label: "Localização" },
    { key: "responsavel", label: "Responsável" },
    { key: "capacidade", label: "Capacidade" },
    { key: "ativo", label: "Ativo" },
  ];

  const formulario = [
    { name: "nome", label: "Nome do Depósito", type: "text", col: 6, required: true, placeholder: "Ex: Depósito Central" },
    { name: "localizacao", label: "Localização", type: "text", col: 6, required: true, placeholder: "Ex: Pavilhão B" },
    { name: "responsavel", label: "Responsável", type: "text", col: 4 },
    { name: "capacidade", label: "Capacidade", type: "text", col: 4, placeholder: "Ex: 500m²" },
    { name: "ativo", label: "Status", type: "select", col: 4, options: [
      { value: "1", label: "Ativo" },
      { value: "0", label: "Inativo" },
    ]},
    { name: "observacao", label: "Observação", type: "text", col: 12 },
  ];

  return (
    <CrudTable 
      titulo="Depósitos" 
      subtitulo="Gestão de depósitos e locais de armazenamento" 
      endpoint="crud/almox_depositos" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={true}
    />
  );
}
