const input = JSON.parse(await readStdin());

function formatMoneyMinor(minor, currencySymbol = '£') {
    const amount = (Number(minor) / 100).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    return currencySymbol + amount;
}

function buildChargeBreakdown(row, currencySymbol = '£') {
    if (! row || row.item_type === 'group') {
        return null;
    }

    const unitPriceMinor = Number(row.unit_price) || 0;
    const quantity = parseFloat(row.quantity) || 0;
    const days = Math.max(1, Number(row.days) || 0);
    const rentalMinor = Math.round(quantity * unitPriceMinor * days);

    return {
        days_line: `Days: ${formatMoneyMinor(unitPriceMinor, currencySymbol)} × ${days}`,
        rental_charge_display: formatMoneyMinor(rentalMinor, currencySymbol),
        surcharge_display: formatMoneyMinor(0, currencySymbol),
    };
}

function applyPricingField(row, field, value, currencySymbol = '£') {
    if (field === 'unit_price') {
        const major = parseFloat(String(value).replace(/[^0-9.-]/g, '')) || 0;
        row.unit_price = Math.round(major * 100);
        row.unit_price_raw = major.toFixed(2);
        row.unit_price_display = formatMoneyMinor(row.unit_price, currencySymbol);
    } else if (field === 'quantity') {
        row.quantity = String(Math.round((parseFloat(value) || 0) * 100) / 100);
    } else if (field === 'days') {
        row.days = Math.max(0, parseInt(value || '0', 10));
    } else if (field === 'discount_percent') {
        row.discount_percent = value === '' ? null : String(Math.round((parseFloat(value) || 0) * 100) / 100);
    }

    const disc = row.discount_percent ? parseFloat(row.discount_percent) : 0;
    const gross = (parseFloat(row.quantity) || 0) * (row.days || 0) * (row.unit_price || 0);
    row.charge_total = Math.round(gross * (1 - disc / 100));
    row.charge_total_display = formatMoneyMinor(row.charge_total, currencySymbol);
    row.charge_breakdown = buildChargeBreakdown(row, currencySymbol);
}

if (input.action === 'simulateMoneyThousandsSeparator') {
    const rows = [
        { id: 1, depth: 1, item_type: 'group', charge_total: 0 },
        { id: 2, depth: 2, item_type: 'product', charge_total: 123456 },
    ];

    const groupSubtotal = rows
        .slice(1)
        .filter((row) => row.item_type !== 'group')
        .reduce((sum, row) => sum + (Number(row.charge_total) || 0), 0);

    process.stdout.write(JSON.stringify({
        formatted: formatMoneyMinor(123456),
        groupSubtotal: formatMoneyMinor(groupSubtotal),
        grandTotal: formatMoneyMinor(groupSubtotal),
        dealSubline: `Deal price applied — ${formatMoneyMinor(9876543)}`,
    }));

    process.exit(0);
}

if (input.action === 'simulateChargeBreakdownRefreshOnInlineEdit') {
    const row = {
        id: 42,
        item_type: 'product',
        quantity: '2',
        days: 3,
        unit_price: 1500,
        discount_percent: null,
        charge_total: 9000,
        charge_breakdown: buildChargeBreakdown({
            id: 42,
            item_type: 'product',
            quantity: '2',
            days: 3,
            unit_price: 1500,
        }),
    };

    applyPricingField(row, 'unit_price', '25.00');
    const afterPrice = { ...row.charge_breakdown };

    applyPricingField(row, 'days', '5');
    const afterDays = { ...row.charge_breakdown };

    applyPricingField(row, 'discount_percent', '10');
    const afterDiscount = { ...row.charge_breakdown };

    process.stdout.write(JSON.stringify({
        chargeTotalDisplay: row.charge_total_display,
        afterPrice,
        afterDays,
        afterDiscount,
    }));

    process.exit(0);
}

if (input.action === 'simulateRowChargeTotalKeepsCommasOnInlineEdit') {
    const row = {
        id: 99,
        item_type: 'product',
        quantity: '2',
        days: 1,
        unit_price: 61728,
        discount_percent: null,
        charge_total: 123456,
        charge_total_display: formatMoneyMinor(123456),
    };

    applyPricingField(row, 'quantity', '3');
    const afterQty = row.charge_total_display;

    applyPricingField(row, 'unit_price', '1000.00');
    const afterPrice = row.charge_total_display;

    process.stdout.write(JSON.stringify({
        afterQty,
        afterPrice,
        unitPriceDisplay: row.unit_price_display,
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
