import sys
import json
import os
import re

def format_brl(val_str):
    if not val_str: return ''
    val_str = str(val_str).strip()
    if val_str in [',00', '0,00', '0']: return '0,00'
    clean = re.sub(r'[^\d\.,]', '', val_str)
    if not clean: return '0,00'
    if ',' in clean:
        parts = clean.rsplit(',', 1)
        inteiro = parts[0].replace('.', '')
        decimal = parts[1]
        try:
            num = float(f"{inteiro}.{decimal}")
            return f"{num:,.2f}".replace(',', 'X').replace('.', ',').replace('X', '.')
        except: return clean
    else:
        try:
            num = float(clean)
            return f"{num:,.2f}".replace(',', 'X').replace('.', ',').replace('X', '.')
        except: return clean

def process_xml(file_path):
    try:
        import xmltodict
        with open(file_path, 'r', encoding='utf-8') as f:
            xml_data = f.read()
        
        doc = xmltodict.parse(xml_data)
        nfe = doc.get('nfeProc', {}).get('NFe', {}).get('infNFe', {})
        if not nfe:
            nfe = doc.get('NFe', {}).get('infNFe', {})
            
        emit = nfe.get('emit', {})
        total = nfe.get('total', {}).get('ICMSTot', {})
        ide = nfe.get('ide', {})
        
        cnpj = emit.get('CNPJ', '')
        if cnpj and len(cnpj) == 14:
            cnpj = f"{cnpj[:2]}.{cnpj[2:5]}.{cnpj[5:8]}/{cnpj[8:12]}-{cnpj[12:]}"
            
        produtos = nfe.get('det', [])
        if not isinstance(produtos, list):
            produtos = [produtos]
            
        descricoes = []
        quantidades = []
        valores_unit = []
        for p in produtos:
            prod = p.get('prod', {})
            descricoes.append(prod.get('xProd', ''))
            quantidades.append(prod.get('qCom', '0'))
            valores_unit.append(prod.get('vUnCom', '0'))
            
        return {
            "status": "success",
            "tipo": "Nota Fiscal (XML)",
            "dados": {
                "cnpj_fornecedor": cnpj,
                "nome_empresa": emit.get('xNome', ''),
                "numero_nf": ide.get('nNF', ''),
                "chave_acesso": ide.get('Id', '').replace('NFe', '') if ide.get('Id') else '',
                "valor_total": format_brl(total.get('vNF', '0.00').replace('.', ',')),
                "valor_produto": format_brl(total.get('vProd', '0.00').replace('.', ',')),
                "valor_frete": format_brl(total.get('vFrete', '0.00').replace('.', ',')),
                "item_descricao": "\n".join(descricoes),
                "quantidade_item": quantidades[0] if quantidades else '',
                "valor_unitario": format_brl(valores_unit[0]) if valores_unit else ''
            }
        }
    except Exception as e:
        return {"status": "error", "message": str(e)}

def process_pdf(file_path):
    import pdfplumber
    import pandas as pd
    
    try:
        with pdfplumber.open(file_path) as pdf:
            full_text = ""
            for page in pdf.pages:
                text = page.extract_text()
                if text: full_text += text + "\n"
        
        is_nf = re.search(r'DANFE|DOCUMENTO AUXILIAR|NOTA FISCAL', full_text, re.IGNORECASE)
        is_ne = re.search(r'Nota de Empenho|202[0-9]NE', full_text, re.IGNORECASE)
        
        if is_nf:
            # Extração DANFE via PDFPlumber texto bruto (Regex reforçado)
            cnpj = re.search(r'\d{2}\.\d{3}\.\d{3}/\d{4}-\d{2}', full_text)
            numero = re.search(r'Nº\s*([\d\.\s]+)\s*SÉRIE', full_text)
            if not numero: numero = re.search(r'Nº\s*([\d\.]+)', full_text)
            
            nome = re.search(r'(?:RECEBEMOS DA\s+)?([A-ZÇÃÕÁÉÍÓÚ0-9\s\.\-\&]{5,40})(?:\s+0\s*-\s*ENTRADA|\s+DANFE)', full_text, re.IGNORECASE)
            if not nome: nome = re.search(r'RAZÃO SOCIAL[\s\S]{0,50}?([A-ZÇÃÕÁÉÍÓÚ0-9\s\.\-\&]{5,40})', full_text)
            
            # Captura exata lendo a linha abaixo do cabeçalho
            vt_line = re.search(r'VALOR TOTAL DA NOTA\s*\n([\s\d\.,]+)', full_text)
            if vt_line:
                numbers = re.findall(r'[\d\.,]+', vt_line.group(1))
                val_total = numbers[-1] if numbers else '0,00'
            else:
                # Fallback
                vt_match = re.findall(r'VALOR TOTAL DA NOTA[\s\S]{0,120}?(\d{1,3}(?:\.\d{3})*,\d{2})', full_text, re.IGNORECASE)
                val_total = next((v for v in vt_match if v not in ['0,00', ',00']), '0,00') if vt_match else '0,00'
            
            val_prod = re.search(r'VALOR TOTAL DOS PRODUTOS[\s\S]{0,80}?(\d{1,3}(?:\.\d{3})*,\d{2})', full_text)
            frete = re.search(r'VALOR DO FRETE[\s\S]{0,80}?(\d{1,3}(?:\.\d{3})*,\d{2})', full_text)
            
            # Adiciona o grupo para V.Total do Item (group 4)
            item_match = re.search(r'(?:\n|^)\s*\d+\s+([A-ZÇÃÕÁÉÍÓÚ0-9\s\-\.\&]{5,80}?)\s+\d{8}\s+\d{2,3}\s+\d{4}\s+[A-Z]{1,4}\s+([\d\.,]+)\s+([\d\.,]+)\s+([\d\.,]+)', full_text)
            if not item_match: 
                item_match = re.search(r'(?:IPI|ICMS)[\s\n]+\d+\s+([A-ZÇÃÕÁÉÍÓÚ0-9\s\-\.\&]{5,80}?)\s+\d{8}\s+\d{2,3}\s+\d{4}\s+[A-Z]{1,4}\s+([\d\.,]+)\s+([\d\.,]+)\s+([\d\.,]+)', full_text)
            
            chave_match = re.search(r'\d{4}\s+\d{4}\s+\d{4}\s+\d{4}\s+\d{4}\s+\d{4}\s+\d{4}\s+\d{4}\s+\d{4}\s+\d{4}\s+\d{4}', full_text)
            chave_acesso = re.sub(r'\s+', '', chave_match.group(0)) if chave_match else ''
            
            return {
                "status": "success",
                "tipo": "Nota Fiscal (DANFE)",
                "dados": {
                    "cnpj_fornecedor": cnpj.group(0) if cnpj else '',
                    "nome_empresa": nome.group(1).replace('NOME/RAZÃO SOCIAL','').replace('CNPJ/CPF','').strip() if nome else '',
                    "numero_nf": numero.group(1).replace(' ','') if numero else '',
                    "chave_acesso": chave_acesso,
                    "valor_total": format_brl(val_total),
                    "valor_produto": format_brl(val_prod.group(1)) if val_prod else '',
                    "valor_frete": format_brl(frete.group(1)) if frete else '0,00',
                    "item_descricao": item_match.group(1).strip() if item_match else '',
                    "quantidade_item": item_match.group(2) if item_match else '',
                    "valor_unitario": format_brl(item_match.group(3)) if item_match else ''
                }
            }
            
        elif is_ne:
            # Nota de Empenho usando Tabula-py e PDFPlumber
            cnpjs = re.findall(r'\d{2}\.\d{3}\.\d{3}/\d{4}-\d{2}', full_text)
            cnpj_favorecido = cnpjs[1] if len(cnpjs) > 1 else (cnpjs[0] if cnpjs else '')
            
            nmr_empenho = re.search(r'(202[0-9]\s*NE\s*\d+)', full_text)
            
            nome = re.search(r'Favorecido[\s\S]{0,150}?\d{2}\.\d{3}\.\d{3}/\d{4}-\d{2}[\s\n]+([A-ZÇÃÕÁÉÍÓÚ0-9\s\.\-\&]{5,60})(?=\s+Endereço|Endereço|CEP|\n)', full_text, re.IGNORECASE)
            if not nome and cnpj_favorecido:
                parts = full_text.split(cnpj_favorecido)
                if len(parts) > 1:
                    nome = re.search(r'^[\s\n]*([A-ZÇÃÕÁÉÍÓÚ0-9\s\.\-\&]{5,60})(?=\s+Endereço|Endereço|CEP|Município|\n)', parts[1], re.IGNORECASE)
            
            val_total = re.search(r'(?:Total da Lista|Valor do Empenho|Valor Total)[\s\S]{0,100}?\s([\d\.,]{4,})(?=\n|$)', full_text, re.IGNORECASE)
            if not val_total: val_total = re.search(r'Valor[\s\S]{0,100}?\s([\d\.,]{4,})(?=\n|$)', full_text)
            
            # Usando expressões regulares para substituir tabula-py
            
            produto = ''
            quantidade = ''
            valor_unit = ''
            item_total = '0,00'
            
            prod_match = re.search(r'Item compra[\s:-]+([\w\s\.\-\:]+?)(?=\s+\d{2,}\.\d{2}|\s+Data|\s+Operação|\n)', full_text, re.IGNORECASE)
            produto = prod_match.group(1).strip() if prod_match else ''
            
            qtd_match = re.search(r'(?:\d{2}/\d{2}/\d{4}\s+[A-Za-zãçõ]+\s+)([\d\.,]+)\s+([\d\.,]+)(?:\s+([\d\.,]+))?', full_text, re.IGNORECASE)
            if qtd_match:
                quantidade = qtd_match.group(1)
                valor_unit = qtd_match.group(2)
                if qtd_match.group(3):
                    item_total = qtd_match.group(3)
                    
            vt = val_total.group(1) if val_total else ''
            if (not vt or vt in ['0,00', ',00']) and item_total not in ['0,00', ',00']:
                vt = item_total
                
            nat_despesa = re.search(r'Natureza d[ae] Despesa[\s\S]*?\n\d+\s+\d+\s+\d+\s+(\d{6})', full_text, re.IGNORECASE)
            data_emissao_match = re.search(r'Data de Emissão[\s\S]{0,50}?(\d{2}/\d{2}/\d{4})', full_text, re.IGNORECASE)
            data_empenho_fmt = ''
            if data_emissao_match:
                # Converter DD/MM/YYYY para YYYY-MM-DD
                d, m, y = data_emissao_match.group(1).split('/')
                data_empenho_fmt = f"{y}-{m}-{d}"
                
            descricao_empenho = re.search(r'Descrição\s*\n([\s\S]{0,300}?)(?=\n\s*Local da Entrega|\n\s*Informação Complementar)', full_text, re.IGNORECASE)
                
            return {
                "status": "success",
                "tipo": "Nota de Empenho",
                "dados": {
                    "cnpj_fornecedor": cnpj_favorecido,
                    "nome_empresa": nome.group(1).strip() if nome else '',
                    "numero_nf": nmr_empenho.group(1).replace(' ','') if nmr_empenho else '',
                    "chave_acesso": '',
                    "natureza_despesa": nat_despesa.group(1) if nat_despesa else '',
                    "data_empenho": data_empenho_fmt,
                    "descricao_empenho": descricao_empenho.group(1).replace('\n', ' ').strip() if descricao_empenho else '',
                    "valor_total": format_brl(vt),
                    "item_descricao": produto,
                    "quantidade_item": quantidade,
                    "valor_produto": format_brl(valor_unit),
                    "valor_frete": '0,00'
                }
            }
            
        else:
            return {"status": "error", "message": "Documento não reconhecido como NF ou NE."}
            
    except Exception as e:
        return {"status": "error", "message": str(e)}

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"status": "error", "message": "Nenhum arquivo fornecido."}))
        sys.exit(1)
        
    file_path = sys.argv[1]
    if file_path.lower().endswith('.xml'):
        res = process_xml(file_path)
    elif file_path.lower().endswith('.pdf'):
        res = process_pdf(file_path)
    else:
        res = {"status": "error", "message": "Formato não suportado."}
        
    print(json.dumps(res))
