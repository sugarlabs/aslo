// 
// Scans an install.js file against our function whitelist. 
// Any functions that do not match are printed as a single line
//

// Safe functions for install.js
let whitelist = [
    'initInstall',
    'cancelInstall',
    'getFolder',
    'addDirectory',
    'performInstall'
];

// Main jshydra entry point
function process_js(ast) {
    if (ast == null) 
        return;

    for (let key in ast) {
        if (ast[key] == JSOP_CALL) {
            // The first child's atom is the name of the 
            // function call
            checkWhitelist(ast.kids[0]);
        }
    }
    
    for each (let kid in ast.kids) {
        process_js(kid);
    }
        
}

// Scans the whitelist for a match, and prints the offending function if not found
function checkWhitelist(node) {
    let name = node.atom
    for each (let safe in whitelist)
        if (name == safe)
            return;

    _print(node.line + ":" + name);   
}