import fs from 'fs';
import * as pdfjsLib from 'pdfjs-dist/build/pdf.mjs';

async function parse(filePath) {
    const data = new Uint8Array(fs.readFileSync(filePath));
    const doc = await pdfjsLib.getDocument({ data }).promise;
    let text = '';
    for (let i = 1; i <= doc.numPages; i++) {
        const page = await doc.getPage(i);
        const content = await page.getTextContent();
        text += content.items.map(s => s.str).join(' ') + '\n';
    }
    console.log("----", filePath, "----");
    console.log(text);
}

await parse('/home/kayrondev/Downloads/NF_112875_assinado.pdf');
await parse('/home/kayrondev/Downloads/NE_160203_2026NE000320_v002_06537334000185_20260617194613.pdf');
