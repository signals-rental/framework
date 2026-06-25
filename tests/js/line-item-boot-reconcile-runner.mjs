import { resolveBootSource, treeStructureSignature } from '../../resources/js/line-item-boot-reconcile.js';

const input = JSON.parse(await readStdin());
const decision = resolveBootSource(input);

process.stdout.write(JSON.stringify({
    ...decision,
    seedSignature: treeStructureSignature(input.seedPayload?.tree || []),
    cacheSignature: treeStructureSignature(input.cached || []),
}));

async function readStdin() {
    const chunks = [];

    for await (const chunk of process.stdin) {
        chunks.push(chunk);
    }

    return Buffer.concat(chunks).toString('utf8');
}
