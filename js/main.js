// ══════════════════════════════════════
//  PIXELCART — main.js
//  Global utilities & cart management
// ══════════════════════════════════════

const CART_KEY = 'pixelcart_cart';

// ── Cart Helpers ──
const Cart = {
    get() {
        return JSON.parse(localStorage.getItem(CART_KEY) || '[]');
    },
    save(cart) {
        localStorage.setItem(CART_KEY, JSON.stringify(cart));
        Cart.updateBadge();
    },
    add(product) {
        const cart = Cart.get();
        const existing = cart.find(i => i.id === product.id);
        if (existing) {
            existing.qty = (existing.qty || 1) + 1;
        } else {
            cart.push({ ...product, qty: 1 });
        }
        Cart.save(cart);
        Cart.showToast(`${product.name} added to cart`);
    },
    remove(id) {
        const cart = Cart.get().filter(i => i.id !== id);
        Cart.save(cart);
    },
    total() {
        return Cart.get().reduce((sum, i) => sum + (i.price * (i.qty || 1)), 0);
    },
    count() {
        return Cart.get().reduce((sum, i) => sum + (i.qty || 1), 0);
    },
    updateBadge() {
        const el = document.getElementById('cartCount');
        if (el) el.textContent = Cart.count();
    },
    showToast(msg) {
        const toast = document.createElement('div');
        toast.className = 'pc-toast';
        toast.textContent = msg;
        toast.style.cssText = `
      position:fixed; bottom:2rem; right:2rem; z-index:9999;
      background:#161616; border:1px solid rgba(62,207,142,0.35);
      color:#f0f0f0; font-family:'DM Sans',sans-serif; font-size:0.85rem;
      padding:0.85rem 1.4rem; border-radius:10px;
      box-shadow:0 8px 30px rgba(0,0,0,0.4);
      animation:toastIn 0.3s ease;
    `;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = '0.3s';
            setTimeout(() => toast.remove(), 300);
        }, 2500);
    }
};

// Toast keyframe
const toastStyle = document.createElement('style');
toastStyle.textContent = `@keyframes toastIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }`;
document.head.appendChild(toastStyle);

// Init cart badge on all pages
Cart.updateBadge();