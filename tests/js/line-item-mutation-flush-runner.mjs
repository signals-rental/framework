import {
    orderFlushBatch,
    resolveServerItemId,
    rowsEligibleForPersistTree,
    shouldScheduleFlushRetry,
} from '../../resources/js/line-item-mutation-flush.js';

const input = JSON.parse(await readStdin());

if (input.action === 'resolveServerItemId') {
    process.stdout.write(JSON.stringify({
        id: resolveServerItemId(input.id, input.rows || []),
    }));

    process.exit(0);
}

if (input.action === 'rowsEligibleForPersistTree') {
    const pending = new Set((input.pendingDeleteIds || []).map(Number));
    const rows = rowsEligibleForPersistTree(input.rows || [], pending);

    process.stdout.write(JSON.stringify({
        ids: rows.map((row) => row.id),
    }));

    process.exit(0);
}

if (input.action === 'shouldScheduleFlushRetry') {
    process.stdout.write(JSON.stringify({
        schedule: shouldScheduleFlushRetry(input),
    }));

    process.exit(0);
}

if (input.action === 'orderFlushBatch') {
    const ordered = orderFlushBatch(input.batch || []);

    process.stdout.write(JSON.stringify({
        kinds: ordered.map((mutation) => mutation.kind),
    }));

    process.exit(0);
}

throw new Error(`Unknown action: ${input.action}`);

async function readStdin() {
    const chunks = [];

    for await (const chunk of process.stdin) {
        chunks.push(chunk);
    }

    return Buffer.concat(chunks).toString('utf8');
}
