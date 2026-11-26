/** Format a UTC database/ISO timestamp in Sri Lanka time. */
export function formatSriLankaTime(value: string | null | undefined): string {
    if (!value) return '—';
    const input = value.trim();
    // Query-builder timestamps have no offset; the database session uses UTC.
    const sql = /^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}:\d{2})(\.\d+)?$/.exec(input);
    const normalized = sql ? `${sql[1]}T${sql[2]}${sql[3] ?? ''}Z` : input;
    if (!sql && !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/i.test(input)) return value;
    const date = new Date(normalized);
    if (Number.isNaN(date.getTime())) return value;
    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Asia/Colombo', year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23',
    }).formatToParts(date);
    const part = (name: Intl.DateTimeFormatPartTypes) => parts.find((item) => item.type === name)?.value ?? '';
    return `${part('year')}-${part('month')}-${part('day')} ${part('hour')}:${part('minute')}:${part('second')}`;
}
