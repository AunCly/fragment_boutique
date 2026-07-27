import Alpine from 'alpinejs';
import '../images/hero-evoli-room.png';

Alpine.data('shop', () => ({
    items: [],
    isCartOpen: false,
    isCheckingOut: false,
    customTitle: '',
    customPrice: '',

    init() {
        const saved = localStorage.getItem('fragment_cart');
        if (saved) {
            this.items = JSON.parse(saved);
        }
        this.$watch('items', (value) => {
            localStorage.setItem('fragment_cart', JSON.stringify(value));
        }, { deep: true });

        window.addEventListener('fragment:add-to-cart', (e) => {
            this.items.push({
                type: 'custom',
                title: e.detail.title,
                price: e.detail.price,
                currency: e.detail.currency ?? 'EUR',
                quantity: 1,
            });
            this.isCartOpen = true;
        });
    },

    get totalItems() {
        return this.items.reduce((sum, item) => sum + item.quantity, 0);
    },

    get totalPrice() {
        return this.items.reduce((sum, item) => sum + item.price * item.quantity, 0);
    },

    addVariant(product) {
        const existing = this.items.find(
            (i) => i.type === 'variant' && i.variantId === product.variantId,
        );
        if (existing) {
            existing.quantity++;
        } else {
            this.items.push({ type: 'variant', quantity: 1, ...product });
        }
        this.isCartOpen = true;
    },

    addCustom() {
        if (!this.customTitle || !this.customPrice) return;
        this.items.push({
            type: 'custom',
            title: this.customTitle,
            price: parseFloat(this.customPrice),
            currency: 'EUR',
            quantity: 1,
        });
        this.customTitle = '';
        this.customPrice = '';
        this.isCartOpen = true;
    },

    increaseQty(index) {
        this.items[index].quantity++;
    },

    decreaseQty(index) {
        if (this.items[index].quantity > 1) {
            this.items[index].quantity--;
        } else {
            this.removeItem(index);
        }
    },

    removeItem(index) {
        this.items.splice(index, 1);
    },

    formatPrice(amount, currency) {
        return new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: currency || 'EUR',
        }).format(amount);
    },

    async checkout() {
        if (this.items.length === 0 || this.isCheckingOut) return;
        this.isCheckingOut = true;

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

        const response = await fetch('/checkout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
            body: JSON.stringify({ items: this.items }),
        });

        const data = await response.json();
        if (data.redirect) {
            window.location.href = data.redirect;
        } else {
            this.isCheckingOut = false;
        }
    },
}));

Alpine.start();
