// Shows server timestamps in the viewer's own time zone. Without JavaScript they stay in UTC, labelled as such.

const styles = {
    date: { dateStyle: 'medium' },
    datetime: { dateStyle: 'medium', timeStyle: 'short' },
    short: { day: 'numeric', month: 'short' },
    month: { month: 'long', year: 'numeric' },
    // The three parts of a date stamp
    day: { day: '2-digit' },
    monthyear: { month: 'short', year: 'numeric' },
    weekdaytime: { weekday: 'short', hour: '2-digit', minute: '2-digit' },
    // Date and time with the time zone named, for the printed report
    stamped: { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', timeZoneName: 'short' },
    // A camera clock's own time (sent as if it were UTC), shown as it is in the viewer's date format, never shifted
    wall: { dateStyle: 'medium', timeStyle: 'short', timeZone: 'UTC' },
    // A day the resident chose for a follow-up (sent as midnight UTC): a calendar date, so never shifted either
    calendar: { dateStyle: 'medium', timeZone: 'UTC' },
    calendarshort: { weekday: 'short', day: 'numeric', month: 'short', timeZone: 'UTC' },
    calendarlong: { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' },
};

export function formatLocal(value, style = 'datetime') {
    const date = value instanceof Date ? value : new Date(value);

    return Number.isNaN(date.getTime()) ? null : new Intl.DateTimeFormat(undefined, styles[style] ?? styles.datetime).format(date);
}

export function localizeTimes(root) {
    for (const element of root.querySelectorAll('[data-local-time]')) {
        const text = formatLocal(element.dataset.datetime ?? element.getAttribute('datetime'), element.dataset.localTime);

        if (text) {
            element.textContent = (element.dataset.prefix ?? '') + text + (element.dataset.suffix ?? '');
        }
    }
}
