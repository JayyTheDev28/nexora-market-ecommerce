import Alpine from 'alpinejs';

window.Alpine = Alpine;

/* ---------------------------------------------------------------------
 * Nexora client-side cart & orders stores.
 *
 * There is no backend yet — everything here lives in localStorage on
 * the buyer's own browser. This is intentional for the current build
 * phase: it lets Add to Cart / Checkout / My Orders feel like one
 * connected flow without a database. When the real backend lands,
 * these two stores are the seam to swap out for API calls.
 * ------------------------------------------------------------------- */

const CART_KEY = 'nexora_cart_v1';
const ORDERS_KEY = 'nexora_orders_v1';

function loadJSON(key, fallback) {
    try {
        const raw = window.localStorage.getItem(key);
        return raw ? JSON.parse(raw) : fallback;
    } catch (e) {
        console.error(`Failed to read ${key} from localStorage`, e);
        return fallback;
    }
}

function saveJSON(key, value) {
    try {
        window.localStorage.setItem(key, JSON.stringify(value));
    } catch (e) {
        console.error(`Failed to write ${key} to localStorage`, e);
    }
}

function lineKey(id, variant) {
    const v = variant && typeof variant === 'object'
        ? Object.entries(variant).sort().map(([k, val]) => `${k}:${val}`).join('|')
        : (variant || '');
    return `${id}__${v}`;
}

Alpine.store('cart', {
    items: loadJSON(CART_KEY, []), // { key, id, name, image, price, seller, variant, qty, selected }

    persist() {
        saveJSON(CART_KEY, this.items);
    },

    add(product, variant = null, qty = 1) {
        const key = lineKey(product.id, variant);
        const existing = this.items.find((i) => i.key === key);
        if (existing) {
            existing.qty += qty;
        } else {
            this.items.push({
                key,
                id: product.id,
                name: product.name,
                image: product.image,
                price: product.price,
                seller: product.seller ?? '',
                variant: variant,
                qty: qty,
                selected: true,
            });
        }
        this.persist();
    },

    remove(key) {
        this.items = this.items.filter((i) => i.key !== key);
        this.persist();
    },

    setQty(key, qty) {
        const item = this.items.find((i) => i.key === key);
        if (!item) return;
        item.qty = Math.max(1, qty);
        this.persist();
    },

    toggle(key) {
        const item = this.items.find((i) => i.key === key);
        if (!item) return;
        item.selected = !item.selected;
        this.persist();
    },

    selectAll(state) {
        this.items.forEach((i) => (i.selected = state));
        this.persist();
    },

    clearSelected() {
        this.items = this.items.filter((i) => !i.selected);
        this.persist();
    },

    get count() {
        return this.items.reduce((sum, i) => sum + i.qty, 0);
    },

    get selectedItems() {
        return this.items.filter((i) => i.selected);
    },

    get subtotal() {
        return this.selectedItems.reduce((sum, i) => sum + i.price * i.qty, 0);
    },
});

Alpine.store('orders', {
    items: loadJSON(ORDERS_KEY, []),

    persist() {
        saveJSON(ORDERS_KEY, this.items);
    },

    seedIfEmpty() {
        if (this.items.length > 0) return;
        const now = Date.now();
        const day = 24 * 60 * 60 * 1000;
        this.items = [
            {
                id: 'NX-10231',
                placedAt: now - 1 * day,
                status: 'to_ship',
                total: 1547.0,
                paymentMethod: 'Cash on Delivery',
                lines: [
                    { name: 'Nimbus Mechanical Keyboard', image: 'https://placehold.co/200x200/e5eeff/0058be?text=Keyboard', qty: 1, price: 1299.0 },
                    { name: 'Artisan Ceramic Pour-Over Set', image: 'https://placehold.co/200x200/e5eeff/0058be?text=Ceramic', qty: 1, price: 248.0 },
                ],
            },
            {
                id: 'NX-10198',
                placedAt: now - 4 * day,
                status: 'in_transit',
                total: 2150.0,
                paymentMethod: 'E-Wallet',
                lines: [
                    { name: 'Classic Leather Tote', image: 'https://placehold.co/200x200/eef4ff/0058be?text=Tote', qty: 1, price: 2150.0 },
                ],
            },
            {
                id: 'NX-10142',
                placedAt: now - 8 * day,
                status: 'out_for_delivery',
                total: 1400.0,
                paymentMethod: 'Cash on Delivery',
                lines: [
                    { name: 'AeroMax Elite Running Shoe', image: 'https://placehold.co/200x200/eef4ff/0058be?text=Shoe', qty: 1, price: 1400.0 },
                ],
            },
            {
                id: 'NX-10077',
                placedAt: now - 20 * day,
                status: 'completed',
                total: 650.0,
                paymentMethod: 'E-Wallet',
                lines: [
                    { name: 'Artisan Ceramic Pour-Over Set', image: 'https://placehold.co/200x200/eef4ff/0058be?text=Ceramic', qty: 1, price: 650.0 },
                ],
            },
        ];
        this.persist();
    },

    place({ lines, total, address, paymentMethod }) {
        const order = {
            id: 'NX-' + Math.floor(10000 + Math.random() * 89999),
            placedAt: Date.now(),
            status: 'to_ship',
            total,
            address,
            paymentMethod,
            lines,
        };
        this.items.unshift(order);
        this.persist();
        return order;
    },

    confirmReceipt(id) {
        const order = this.items.find((o) => o.id === id);
        if (!order) return;
        order.status = 'completed';
        this.persist();
    },

    byStatus(status) {
        return this.items.filter((o) => o.status === status);
    },
});

Alpine.start();
