const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const context = { window: {} };
vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname, '../public/js/import-name.js'), 'utf8'), context);
const { split, format } = context.window.ImportName;

for (const source of ['Dela Cruz, Juan Carlos P.', 'Juan Carlos P. Dela Cruz', 'Juan Carlos P Dela Cruz']) {
    assert.equal(format(source), 'Dela Cruz, Juan Carlos P.');
    assert.equal(split(source).first, 'Juan Carlos');
    assert.equal(split(source).last, 'Dela Cruz');
}
assert.equal(format('Santos, Maria'), 'Santos, Maria');
assert.equal(format('Maria Santos'), 'Santos, Maria');
assert.equal(format('Jose P. Mercado Jr.'), 'Mercado, Jose P. JR');
assert.equal(format('Peña, José Ñ.'), 'Peña, José Ñ.');
assert.equal(format('Prince'), 'Prince');
assert.equal(format(''), '');
console.log('Import name preview checks passed.');
