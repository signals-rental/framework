import { serializeRowsForCache } from '../../resources/js/line-item-cache.js';

const input = JSON.parse(await readStdin());
const rows = serializeRowsForCache(input.rows, input.oppId);

process.stdout.write(JSON.stringify({
    count: rows.length,
    ids: rows.map((r) => r.id),
    cloneable: rows.every((row) => {
        try {
            structuredClone(row);

            return true;
        } catch {
            return false;
        }
    }),
}));

async function readStdin() {
    const chunks = [];

    for await (const chunk of process.stdin) {
        chunks.push(chunk);
    }

    return Buffer.concat(chunks).toString('utf8');
}
