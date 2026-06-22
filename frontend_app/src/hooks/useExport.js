import Swal from 'sweetalert2';
import * as ExcelJS from 'exceljs';
import { saveAs } from 'file-saver';
import jsPDF from 'jspdf';
import 'jspdf-autotable';
import printJS from 'print-js';

export function useExport(titulo, dynamicColunas, dadosFiltrados) {
  const exportarExcel = async () => {
    if (dadosFiltrados.length === 0) return Swal.fire('Aviso', 'Não há dados para exportar.', 'warning');
    
    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet('Relatório');
    
    worksheet.columns = dynamicColunas.map(col => ({
      header: col.label,
      key: col.key,
      width: Math.max(col.label.length + 5, 15)
    }));

    dadosFiltrados.forEach(item => worksheet.addRow(item));

    worksheet.getRow(1).eachCell((cell) => {
      cell.font = { bold: true, color: { argb: 'FFFFFFFF' } };
      cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1E293B' } };
      cell.alignment = { vertical: 'middle', horizontal: 'center' };
      cell.border = { top: { style: 'thin' }, left: { style: 'thin' }, bottom: { style: 'thin' }, right: { style: 'thin' } };
    });

    const buffer = await workbook.xlsx.writeBuffer();
    saveAs(new Blob([buffer]), `${titulo.replace(/\s+/g, '_')}_RelatorioOficial.xlsx`);
  };

  const exportarPDF = () => {
    if (dadosFiltrados.length === 0) return Swal.fire('Aviso', 'Não há dados para exportar.', 'warning');
    const doc = new jsPDF('l', 'pt', 'a4');
    
    // Cabeçalho Militar Oficial
    doc.setFontSize(14);
    doc.setFont('helvetica', 'bold');
    doc.text('ESTADO MAIOR - COMANDO GERAL', 40, 40);
    doc.setFontSize(10);
    doc.setFont('helvetica', 'normal');
    doc.text('Sistema de Gestão e Controle Estratégico de Empenhos e Manutenção (SGCEEM v2.0)', 40, 55);
    
    doc.setFontSize(12);
    doc.setFont('helvetica', 'bold');
    doc.text(`RELATÓRIO OFICIAL: ${titulo.toUpperCase()}`, 40, 80);
    doc.setLineWidth(1);
    doc.line(40, 85, 800, 85);

    const tableColumn = dynamicColunas.map(c => c.label);
    const tableRows = dadosFiltrados.map(item => dynamicColunas.map(c => item[c.key] || ''));
    
    doc.autoTable({ 
      head: [tableColumn], 
      body: tableRows, 
      startY: 95, 
      theme: 'grid',
      headStyles: { fillColor: [30, 41, 59], textColor: [255, 255, 255] },
      alternateRowStyles: { fillColor: [241, 245, 249] },
      styles: { fontSize: 8, cellPadding: 4 }
    });
    
    // Rodapé de Assinatura
    const finalY = doc.lastAutoTable.finalY || 100;
    if (finalY + 100 < doc.internal.pageSize.getHeight()) {
      doc.line(300, finalY + 60, 540, finalY + 60);
      doc.text('Assinatura do Chefe da Seção / Comandante', 315, finalY + 75);
    }
    
    doc.save(`${titulo.replace(/\s+/g, '_')}_Relatorio_Militar.pdf`);
  };

  const imprimir = () => {
    if (dadosFiltrados.length === 0) return Swal.fire('Aviso', 'Não há dados para imprimir.', 'warning');
    
    const printData = dadosFiltrados.map(item => {
      const row = {};
      dynamicColunas.forEach(col => { row[col.label] = item[col.key] || ''; });
      return row;
    });

    const properties = dynamicColunas.map(col => col.label);
    
    const dataAtual = new Date().toLocaleDateString('pt-BR');
    const horaAtual = new Date().toLocaleTimeString('pt-BR');

    printJS({
      printable: printData,
      properties: properties,
      type: 'json',
      header: `
        <div style="text-align: center; font-family: 'Times New Roman', serif; margin-bottom: 20px;">
          <h3 style="margin: 0;">ESTADO MAIOR - COMANDO GERAL</h3>
          <h5 style="margin: 5px 0;">SGCEEM v2.0 - Relatório Oficial</h5>
          <hr style="border: 1px solid black;" />
          <h2 style="margin: 10px 0;">${titulo.toUpperCase()}</h2>
        </div>
      `,
      style: `
        @page { size: landscape; margin: 15mm; }
        body { font-family: 'Arial', sans-serif; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
        th, td { border: 1px solid #444; padding: 6px; text-align: left; }
        th { background-color: #e2e8f0; -webkit-print-color-adjust: exact; color: #000; font-weight: bold; }
        tr:nth-child(even) { background-color: #f8fafc; -webkit-print-color-adjust: exact; }
      `,
      documentTitle: `Documento_${titulo}`,
      // Injeta rodapé de assinatura e metadados via HTML puro na impressão
      bottom: `
        <div style="margin-top: 50px; text-align: center; font-family: 'Arial', sans-serif;">
          <div style="width: 300px; margin: 0 auto; border-top: 1px solid black; padding-top: 5px;">
            <strong>Assinatura do Chefe / Responsável</strong>
          </div>
          <div style="margin-top: 30px; font-size: 10px; color: #666; text-align: right;">
            <em>Impresso do SGCEEM em ${dataAtual} às ${horaAtual}</em>
          </div>
        </div>
      `
    });
  };

  return { exportarExcel, exportarPDF, imprimir };
}
