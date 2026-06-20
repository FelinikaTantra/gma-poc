import Babel from '@babel/standalone';
import fs from 'fs';

const code = fs.readFileSync('./scratch/extracted.js', 'utf8');

try {
    // Register custom preset
    Babel.registerPreset("custom-react", {
        presets: [
            [Babel.availablePresets["react"], { runtime: "classic" }]
        ]
    });

    const output = Babel.transform(code, {
        presets: ['custom-react']
    }).code;
    
    console.log("Compilation Successful using registered custom-react preset!");
    console.log("Does output contain 'import' statement?");
    const hasImport = output.includes('import ') || output.includes('import{') || output.includes('import *');
    console.log(hasImport ? "YES" : "NO");
} catch (e) {
    console.error("Compilation failed with error:");
    console.error(e);
}
