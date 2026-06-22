import CrudTable from "../../components/CrudTable.jsx";
import { useState } from "react";
import HistoricoViatura from "./HistoricoViatura.jsx";
import { BookOpen } from "lucide-react";

export default function Frota() {
  const [selectedViatura, setSelectedViatura] = useState(null);

  if (selectedViatura) {
    return <HistoricoViatura viatura={selectedViatura} onBack={() => setSelectedViatura(null)} />;
  }

  const colunas = [
    { key: "id", label: "ID" },
    { key: "prefixo_sga", label: "Prefixo SGA" },
    { key: "placa", label: "Placa" },
    { key: "marca", label: "Marca" },
    { key: "modelo", label: "Modelo" },
    { key: "ano", label: "Ano" },
    { key: "subunidade", label: "Subunidade" },
    { key: "batalhao", label: "OM" },
    { key: "status", label: "Status", render: (item) => {
      const statusMap = {
        'Disponível': 'bg-success',
        'Em Manutenção': 'bg-warning text-dark',
        'Baixada': 'bg-danger',
        'Emprestada': 'bg-info',
      };
      const badge = statusMap[item.status] || 'bg-secondary';
      return <span className={`badge ${badge}`}>{item.status || 'Indefinido'}</span>;
    }},
  ];

  const formulario = [
    { name: "ativo", label: "Ativo", type: "select", col: 6, required: true, options: [
      { value: "Sim", label: "Sim" },
      { value: "Não", label: "Não" }
    ]},
    { name: "tipo", label: "Tipo", type: "select", col: 6, required: true, options: [
      { value: "Viatura", label: "Viatura" },
      { value: "Equipamento", label: "Equipamento" },
      { value: "Embarcação", label: "Embarcação" }
    ]},
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
    { name: "batalhao", label: "Organização Militar (OM)", type: "text", col: 6, required: true, placeholder: "Ex: 1º BEC" },
    { name: "subunidade", label: "Subunidade Destino", type: "text", col: 6 },
    { name: "status", label: "Status Operacional", type: "select", col: 6, required: true, options: [
      { value: "Disponível", label: "Disponível (Pronta para uso)" },
      { value: "Em Manutenção", label: "Em Manutenção (Oficina)" },
      { value: "Baixada", label: "Baixada (Inoperante)" },
      { value: "Emprestada", label: "Emprestada (Outra OM)" },
    ]},
  ];

  return (
    <CrudTable 
      titulo="Viaturas e Equipamentos" 
      subtitulo="Gestão completa da frota — cadastro, edição e controle" 
      endpoint="crud/frota" 
      colunas={colunas}
      formulario={formulario} 
      deletavel={true}
      customActions={[
        {
          icon: <><BookOpen size={14} className="me-1" /> Dossiê</>,
          className: 'btn-outline-primary fw-bold px-3 rounded-pill shadow-sm border',
          tooltip: 'Abrir Livro Histórico',
          onClick: (item) => setSelectedViatura(item)
        }
      ]}
    />
  );
}
