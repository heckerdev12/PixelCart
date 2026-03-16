# PixelCart 💻

A full-stack e-commerce website for premium laptops, built with HTML, CSS, JavaScript, PHP and MySQL. Features M-Pesa STK Push payments via Safaricom's Daraja API.

---

## Pages

| Page | File | Description |
|------|------|-------------|
| Home | `index.html` | Hero, featured laptops, M-Pesa section |
| Products | `products.html` | 12 laptops with live filters and search |
| Product Detail | `product-detail.html` | Dynamic single product page via URL param |
| Cart | `cart.html` | localStorage cart with qty controls |
| Checkout | `checkout.html` | Delivery form + M-Pesa STK Push |
| Services | `services.html` | Repair, upgrade, trade-in services |
| About | `about.html` | Company history, mission, vision, team |
| Contact | `contact.html` | Contact form with JS validation + Google Map |

---

## Tech Stack

- **Frontend** — HTML5, CSS3, JavaScript (ES6)
- **Backend** — PHP 8.2
- **Database** — MySQL (via XAMPP)
- **Payments** — Safaricom Daraja API (M-Pesa STK Push)
- **Local Server** — XAMPP (Apache + MySQL)
- **Tunnel** — ngrok (exposes localhost for Daraja callbacks)

---

## Project Structure

```
PixelCart/
├── index.html
├── products.html
├── product-detail.html
├── cart.html
├── checkout.html
├── services.html
├── about.html
├── contact.html
├── css/
│   └── style.css          # Global styles, CSS variables, navbar
├── js/
│   └── main.js            # Cart utilities, localStorage, toast notifications
├── images/
│   ├── logo.png
│   ├── hero.jpg
│   ├── about.jpg
│   ├── strip-1.jpg
│   ├── strip-2.jpg
│   ├── strip-3.jpg
│   └── laptop-1.jpg ... laptop-12.jpg
└── api/
    ├── mpesa_auth.php      # Gets Daraja access token
    ├── mpesa_stk.php       # Triggers STK Push + saves order to DB
    ├── mpesa_callback.php  # Receives payment confirmation from Safaricom
    └── check_payment.php  # Polled by frontend to check payment status
```

---

## Setup & Installation

### Prerequisites
- XAMPP (Apache + MySQL + PHP)
- ngrok account (free tier works)
- Safaricom Developer account at developer.safaricom.co.ke

### Steps

**1. Clone the repository**
```bash
git clone https://github.com/heckerdev12/PixelCart.git .
```

**2. Place files in XAMPP htdocs**
```
C:\xampp\htdocs\PixelCart\
```

**3. Start XAMPP**
- Open XAMPP Control Panel
- Start Apache and MySQL

**4. Create the database**
- Go to `http://localhost/phpmyadmin`
- Click SQL tab and run `database.sql`

**5. Configure API keys**

In `api/mpesa_auth.php`:
```php
define('CONSUMER_KEY',    'your_consumer_key');
define('CONSUMER_SECRET', 'your_consumer_secret');
```

In `api/mpesa_stk.php`:
```php
define('PASSKEY',      'your_passkey');
define('CALLBACK_URL', 'https://your-ngrok-url.ngrok-free.dev/PixelCart/api/mpesa_callback.php');
```

**6. Start ngrok**
```bash
ngrok http 80
```
Copy the `https://xxxx.ngrok-free.dev` URL and update `CALLBACK_URL` in `mpesa_stk.php`.

**7. Open the site**
```
http://localhost/PixelCart/index.html
```

---

## M-Pesa Payment Flow

```
Customer clicks Pay
       ↓
checkout.html sends POST to mpesa_stk.php
       ↓
mpesa_stk.php gets token → sends STK Push to Safaricom
       ↓
Safaricom fires callback to mpesa_callback.php (via ngrok)
       ↓
mpesa_callback.php updates payments table → status = PAID
       ↓
checkout.html polls check_payment.php every 4 seconds
       ↓
Status = PAID → success screen + cart cleared
```

---

## Database Tables

| Table | Purpose |
|-------|---------|
| `payments` | M-Pesa transaction records |
| `orders` | Customer order details |
| `order_items` | Individual items per order |
| `products` | Product catalogue (for future PHP integration) |

---

## JavaScript Features

- **Live product filtering** — brand, category, price range, search
- **Sort** — featured, price low-high, price high-low, A-Z
- **localStorage cart** — persists across pages, max 5 per item
- **Form validation** — checkout and contact forms with inline errors
- **Payment polling** — checks DB every 4 seconds for M-Pesa confirmation
- **Toast notifications** — add to cart, payment success, max qty reached

---

## CSS Architecture

All pages share `css/style.css` for global variables, navbar, and reset. Each page has its own `<style>` block for page-specific styles.

**Color palette:**
```css
--bg:           #0f0f0f   /* Deep dark background */
--surface:      #161616   /* Cards and panels */
--green:        #3ECF8E   /* Primary accent (Supabase + M-Pesa green) */
--text-primary: #f0f0f0   /* Primary text */
--text-muted:   #888888   /* Secondary text */
--border:       #2a2a2a   /* Subtle borders */
```

---

## Notes

- Sandbox mode uses `ResultCode 1037` as success (no real PIN required)
- Switch `CALLBACK_URL` and Daraja endpoint to production for live payments
- ngrok free tier generates a new URL on every restart — update `CALLBACK_URL` each time