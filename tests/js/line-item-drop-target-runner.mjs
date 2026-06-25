import {
    applyDragBlockMove,
    buildPersistNodes,
    parentAt,
    resolveDropTarget,
} from '../../resources/js/line-item-drop-target.js';

const input = JSON.parse(await readStdin());

if (input.action === 'parentAt') {
    const parent = parentAt(input.rest, input.insertIndex, input.depth);

    process.stdout.write(JSON.stringify({
        parentId: parent?.id ?? null,
        parentType: parent?.item_type ?? null,
    }));

    process.exit(0);
}

if (input.action === 'applyDragBlockMove') {
    const result = applyDragBlockMove(input.rows, input.drag);

    process.stdout.write(JSON.stringify({
        applied: result.applied,
        reason: result.reason ?? null,
        nodes: result.nodes ?? buildPersistNodes(result.rows),
    }));

    process.exit(0);
}

const result = resolveDropTarget(input);

process.stdout.write(JSON.stringify({
    insertIndex: result.insertIndex,
    targetDepth: result.targetDepth,
    beforeId: result.beforeId,
    valid: result.valid,
    highlightGroupId: result.highlightGroupId,
    parentId: result.parent?.id ?? null,
    parentType: result.parent?.item_type ?? null,
}));

async function readStdin() {
    const chunks = [];

    for await (const chunk of process.stdin) {
        chunks.push(chunk);
    }

    return Buffer.concat(chunks).toString('utf8');
}
