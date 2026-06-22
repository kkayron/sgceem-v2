import re

full_text = """
Código Nome
160203 2° BATALHÃO DE ENGENHARIA DE CONSTRUÇÃO
CNPJ Endereço
07.549.168/0001-08 AV. FREI SERAFIM N. 2833( A ADM DEC 37221 (27/04/55))BE 18/55
Município UF Telefone
TERESINA PI OD: (86)3131-4559 RITEX: 873-4559

Ano Tipo Número
2026 NE 320

Célula Orçamentária
Esfera PTRES Fonte de Recurso Natureza da Despesa UGR
1 235623 1000000000 449030 393003

Data de Emissão Tipo Processo Taxa de Câmbio Valor
09/06/2026 Global 64040.000597/2024-84 0,0000 125.424,00

Favorecido
Código Nome
06.537.334/0001-85 MINERACAO SAO VICENTE LTDA
Endereço
BOA ESPERANCA, POCOS D S/N BR 101, KM ZONA RURAL
Município UF Telefone
ITAGIMIRIM BA

Amparo Legal
Código Modalidade de Licitação
179 PREGAO
Ato Normativo Artigo Parágrafo Incíso
Lei 14.133/2021 28

Descrição
160106-BR367.PVM" - NOTA DE CREDITO Nº 2026NC502952. PEC N° 19792. ITEM O.O.G: 3.3.1
367/MG. PREGAO N° 90028/2024 - 160203.
Local da Entrega
DST BR - 367
Informação Complementar
16020305001242025 - UASG Minuta: 160203
Sistema de Origem
COMPRASNET-ME

Versão Data/Hora Operação
002 12/06/2026 08:48:05 Alteração
"""

val_total = re.search(r'(?:Total da Lista|Valor do Empenho|Valor Total)[\s\S]{0,100}?([\d\.,]{4,})', full_text, re.IGNORECASE)
if not val_total: val_total = re.search(r'Valor\s+([\d\.,]{4,})', full_text)

print("val_total:", val_total.group(1) if val_total else "None")

qtd_match = re.search(r'(?:Inclusão|Quantidade)[\s\S]{0,100}?(?:\n|^)\s*\d*\s*([\d\.,]+)\s+([\d\.,]+)(?:\s+([\d\.,]+))?', full_text, re.IGNORECASE)
print("qtd_match:", qtd_match.groups() if qtd_match else "None")

