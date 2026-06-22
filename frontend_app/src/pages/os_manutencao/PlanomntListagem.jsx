import CrudTable from "../../components/CrudTable.jsx";

export default function PlanomntListagem() {
  const colunas = [
    { key: "id", label: "Nº" },
    { key: "nome_plano", label: "Nome do Plano" },
    { key: "tipo_servico", label: "Tipo de Serviço" },
    { key: "periodicidade_km", label: "Periodicidade (KM)" },
    { key: "periodicidade_dias", label: "Periodicidade (Dias)" },
    { key: "ativo", label: "Ativo" },
  ];

  const formulario = [
    { name: "nome_plano", label: "Nome do Plano", type: "text", col: 6, required: true, placeholder: "Ex: Troca de Óleo Periódica" },
    { name: "tipo_servico", label: "Tipo de Serviço", type: "select", col: 6, required: true, options: [
      { value: "Troca de Óleo", label: "Troca de Óleo" },
      { value: "Filtros", label: "Troca de Filtros" },
      { value: "Freios", label: "Revisão de Freios" },
      { value: "Pneus", label: "Rodízio de Pneus" },
      { value: "Revisão Geral", label: "Revisão Geral" },
      { value: "Suspensão", label: "Suspensão" },
      { value: "Elétrica", label: "Elétrica" },
    ]},
    { name: "periodicidade_km", label: "A cada quantos KM?", type: "number", col: 4, placeholder: "Ex: 10000" },
    { name: "periodicidade_dias", label: "A cada quantos dias?", type: "number", col: 4, placeholder: "Ex: 180" },
    { name: "ativo", label: "Status", type: "select", col: 4, options: [
      { value: "1", label: "Ativo" },
      { value: "0", label: "Inativo" },
    ]},
    { name: "descricao", label: "Descrição / Instrução do Plano", type: "text", col: 12 },
  ];

  return (
    <CrudTable 
      titulo="Planos de Manutenção" 
      subtitulo="Planos de manutenção periódica programada" 
      endpoint="crud/mnt_planos" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={true}
    />
  );
}
