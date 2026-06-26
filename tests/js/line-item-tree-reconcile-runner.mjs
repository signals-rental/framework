import { reconcileLocalTree } from '../../resources/js/line-item-tree-reconcile.js';

const input = JSON.parse(await readStdin());

const result = reconcileLocalTree(
    input.localRows ?? [],
    input.serverRows ?? [],
    input.pendingLocalIds ?? [],
);

process.stdout.write(JSON.stringify({
    ids: result.rows.map((row) => Number(row.id)),
    names: result.rows.map((row) => row.name ?? null),
    conflicts: result.conflicts,
}));

async function readStdin() {
    const chunks = [];

    for await (const chunk of process.stdin) {
        chunks.push(chunk);
    }

    return Buffer.concat(chunks).toString('utf8');
}
