import { merge, productHitId } from '../../resources/js/opportunity-product-search.js';

const input = JSON.parse(await readStdin());

if (input.action === 'simulateMergeDedupesMixedIdTypes') {
    const productId = 42;
    const localHit = {
        id: productId,
        name: 'Chauvet DJ Esprite',
        sku: 'ESPRITE',
        default_rate: '125.00',
        accessories: [],
    };
    const serverHit = {
        id: String(productId),
        name: 'Chauvet DJ Esprite',
        sku: 'ESPRITE',
        default_rate: '125.00',
        accessories: [],
        availability: 'available',
        image_url: 'https://example.test/thumb.jpg',
    };

    const merged = merge([localHit], [serverHit]);

    process.stdout.write(JSON.stringify({
        mergedCount: merged.length,
        mergedIds: merged.map((hit) => hit.id),
        productHitIdLocal: productHitId(localHit.id),
        productHitIdServer: productHitId(serverHit.id),
        availabilityMerged: merged[0]?.availability ?? null,
        imageMerged: merged[0]?.image_url ?? null,
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
