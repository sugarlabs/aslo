// Arguments: test_globals.js
// Name: Global information test

include("cleanast.js");

function process_js(ast) {
	let toplevel = clean_ast(ast);
	_print("Global variables:");
	for each (let v in toplevel.variables) {
		_print(v.name + " at " + v.loc.line);
	}
	_print("Global constants:");
	for each (let v in toplevel.constants) {
		_print(v.name + " at " + v.loc.line);
	}
	_print("Global functions:");
	for each (let v in toplevel.functions) {
		_print(v.name + " at " + v.loc.line);
	}
}
