// Tells the server which time zone this browser uses, so a follow-up that is "due today" means the resident's own today.
// Only the zone's name, such as "Asia/Manila", is sent, and only to RISE itself. Until it has been sent once, the server
// counts days in its own time zone.

const cookie = 'rise_tz';

try {
    const zone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const saved = document.cookie.split('; ').find((part) => part.startsWith(`${cookie}=`))?.slice(cookie.length + 1);

    if (zone && decodeURIComponent(saved ?? '') !== zone) {
        document.cookie = `${cookie}=${encodeURIComponent(zone)}; path=/; max-age=31536000; samesite=lax`;
    }
} catch {
    // Without time zone support in Intl, the server's own day is used
}
