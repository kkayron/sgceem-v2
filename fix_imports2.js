const fs = require('fs');
const path = require('path');

const srcPagesDir = path.join(__dirname, 'frontend_app', 'src', 'pages');

const categories = fs.readdirSync(srcPagesDir).filter(f => fs.statSync(path.join(srcPagesDir, f)).isDirectory());

categories.forEach(category => {
    const catPath = path.join(srcPagesDir, category);
    const files = fs.readdirSync(catPath).filter(f => f.endsWith('.jsx'));
    
    files.forEach(file => {
        const filePath = path.join(catPath, file);
        let content = fs.readFileSync(filePath, 'utf-8');
        
        // Fix mismatched quotes
        content = content.replace(/"\.\.\/\.\.\/components\/(.*?)'/g, '"../../components/$1"');
        content = content.replace(/"\.\.\/\.\.\/utils\/(.*?)'/g, '"../../utils/$1"');
        content = content.replace(/"\.\.\/\.\.\/App\.jsx'/g, '"../../App.jsx"');
        
        fs.writeFileSync(filePath, content);
    });
});

console.log('Fixed quotes in page files!');
