const rupiahFormatter = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

const numberFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 });

const dateFormatter = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });

const timeFormatter = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' });

export function formatRupiah(value) {
    const number = Number(value);
    return Number.isFinite(number) ? rupiahFormatter.format(number) : '-';
}

export function formatNumber(value) {
    const number = Number(value);
    return Number.isFinite(number) ? numberFormatter.format(number) : '-';
}

export function formatDate(value) {
    if (!value) return '-';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '-' : dateFormatter.format(date);
}

export function formatTime(value) {
    if (!value) return '';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '' : timeFormatter.format(date);
}

/**
 * Money input helpers. The form keeps a plain numeric string ("15000" or "15000.5", dot as the
 * decimal point) so backend `numeric` validation is unchanged; only the visible text is localized
 * ("15.000" / "15.000,5": dot for thousands, comma for decimals, like Indonesian usage).
 */
export function parseRupiahInput(text, maxDecimals = 2) {
    const typed = String(text ?? '').replace(/[^\d,]/g, '');
    const commaAt = typed.indexOf(',');
    const hasComma = commaAt !== -1;
    const integerSource = hasComma ? typed.slice(0, commaAt) : typed;
    const decimalDigits = hasComma && maxDecimals > 0 ? typed.slice(commaAt + 1).replace(/,/g, '').slice(0, maxDecimals) : '';
    const integerDigits = integerSource.replace(/^0+(?=\d)/, '') || (decimalDigits ? '0' : '');

    return {
        raw: integerDigits + (decimalDigits ? `.${decimalDigits}` : ''),
        // A trailing comma is kept while typing so "15," does not collapse to "15".
        trailingComma: hasComma && maxDecimals > 0 && decimalDigits === '',
    };
}

export function formatRupiahInput(raw, { trailingComma = false } = {}) {
    const [integerPart = '', decimalPart = ''] = String(raw ?? '').split('.');
    if (integerPart === '' && decimalPart === '') return trailingComma ? '0,' : '';

    const grouped = (integerPart || '0').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    if (decimalPart !== '') return `${grouped},${decimalPart}`;

    return trailingComma ? `${grouped},` : grouped;
}
