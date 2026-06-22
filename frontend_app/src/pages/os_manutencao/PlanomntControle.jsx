import CrudTable from "../../components/CrudTable.jsx";

export default function PlanomntControle() {
  const colunas = [
    { key: "id", label: "Nº" },
    { key: "nome_plano", label: "Nome do Plano" },
    { key: "tipo_servico", label: "Tipo de Serviço" },
    { key: "periodicidade_km", label: "Periodicidade KM" },
    { key: "periodicidade_dias", label: "Periodicidade Dias" },
    { key: "ativo", label: "Status" },
    { key: "descricao", label: "Instruções" },
  ];

  const formulario = [];

  return (
    <CrudTable 
      titulo="Controle e Alertas de Manutenção" 
      subtitulo="Monitoramento de planos de manutenção programada (somente leitura)" 
      endpoint="crud/mnt_planos" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={false}
    />
  );
}
