import CrudTable from "../../components/CrudTable.jsx";

export default function OdometroControle() {
  const colunas = [
    { key: "id", label: "Nº" },
    { key: "id_viatura", label: "ID Viatura" },
    { key: "tipo_medicao", label: "Tipo" },
    { key: "valor_medicao", label: "Leitura" },
    { key: "data_medicao", label: "Data" },
    { key: "responsavel", label: "Responsável" },
    { key: "observacao", label: "Observação" },
  ];

  const formulario = [
    { name: "id_viatura", label: "ID da Viatura", type: "number", col: 4, required: true },
    { name: "tipo_medicao", label: "Tipo de Medição", type: "select", col: 4, required: true, options: [
      { value: "Odômetro", label: "Odômetro (KM)" },
      { value: "Horímetro", label: "Horímetro (Horas)" },
    ]},
    { name: "valor_medicao", label: "Valor da Leitura", type: "number", col: 4, required: true, placeholder: "Ex: 45230" },
    { name: "data_medicao", label: "Data da Medição", type: "date", col: 4, required: true },
    { name: "responsavel", label: "Responsável pela Leitura", type: "text", col: 4 },
    { name: "observacao", label: "Observação", type: "text", col: 12 },
  ];

  return (
    <CrudTable 
      titulo="Controle de Odômetro / Horímetro" 
      subtitulo="Registro de medições de quilometragem e horas de operação" 
      endpoint="crud/controle_medicoes" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={true}
    />
  );
}
