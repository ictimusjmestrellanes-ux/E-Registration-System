// Keep parsing aligned with App\Support\ImportName; retain source names for import.
(function (root) {
    function split(value) {
        value = String(value || '').trim().replace(/\s+/gu, ' ');
        let suffix = '';
        const ending = value.match(/[\s,]+(JR\.?|SR\.?|II|III|IV|V)$/i);
        if (ending) {
            suffix = ending[1].replace(/\.$/, '').toUpperCase();
            value = value.slice(0, -ending[0].length).trim();
        }
        let first = '', middle = '', last = '';
        if (value.includes(',')) {
            const comma = value.indexOf(',');
            last = value.slice(0, comma).trim();
            first = value.slice(comma + 1).trim();
            const initial = first.match(/^(.*?)\s+([\p{L}]\.?)$/u);
            if (initial) [, first, middle] = initial;
        } else {
            const initial = value.match(/^(.+?)\s+([\p{L}]\.?)\s+(.+)$/u);
            if (initial) {
                [, first, middle, last] = initial;
            } else {
                const parts = value ? value.split(' ') : [];
                first = parts.shift() || '';
                last = parts.pop() || '';
                middle = parts.join(' ');
            }
        }
        return { first, middle, last, suffix };
    }
    function format(value) {
        const name = split(value);
        const initial = name.middle ? Array.from(name.middle)[0].toUpperCase() + '.' : '';
        const given = [name.first, initial].filter(Boolean).join(' ');
        return [name.last && given ? name.last + ', ' + given : name.last || given, name.suffix]
            .filter(Boolean).join(' ');
    }
    root.ImportName = { split, format };
})(typeof window !== 'undefined' ? window : globalThis);
