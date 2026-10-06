import Alpine from 'alpinejs';

window.Alpine = Alpine;

/**
 * JSON fetch helper that automatically attaches the CSRF token (from the
 * <meta name="csrf-token"> tag in the layout) and, on a 401 (session
 * expired / not logged in), sends the browser to /login instead of
 * silently failing.
 *
 * Usage: await api('/cart/items', { method: 'POST', body: {...} })
 */
window.api = async function api(url, { method = 'GET', body = null } = {}) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;

    const res = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': token,
        },
        body: body ? JSON.stringify(body) : null,
    });

    if (res.status === 401) {
        window.location.href = '/login';
        return null;
    }

    if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        throw new Error(data.message || 'Something went wrong. Please try again.');
    }

    return res.status === 204 ? null : res.json();
};

Alpine.start();
