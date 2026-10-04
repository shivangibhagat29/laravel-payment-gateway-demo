<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Razorpay Checkout Demo</title>
    <style>
        body { font-family: system-ui, sans-serif; background:#f4f6f8; display:flex; justify-content:center; padding:48px 16px; }
        .card { background:#fff; max-width:380px; width:100%; padding:28px; border-radius:12px; box-shadow:0 4px 18px rgba(0,0,0,.08); }
        h1 { font-size:20px; margin:0 0 4px; } p.sub { color:#666; margin:0 0 20px; font-size:14px; }
        label { display:block; font-size:13px; margin:12px 0 4px; color:#333; }
        input { width:100%; padding:10px; border:1px solid #ccc; border-radius:8px; box-sizing:border-box; font-size:15px; }
        button { margin-top:20px; width:100%; padding:12px; border:0; border-radius:8px; background:#0F4C5C; color:#fff; font-size:16px; cursor:pointer; }
        button:disabled { opacity:.6; }
        #msg { margin-top:16px; font-size:14px; }
    </style>
</head>
<body>
<div class="card">
    <h1>Pay with Razorpay</h1>
    <p class="sub">Test mode demo — no real money is charged.</p>

    <form id="pay-form">
        <label for="name">Name</label>
        <input id="name" name="name" required value="Test User">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" required value="test@example.com">
        <label for="amount">Amount (INR)</label>
        <input id="amount" name="amount" type="number" min="1" step="1" required value="499">
        <button id="pay-btn" type="submit">Pay now</button>
    </form>
    <div id="msg"></div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
const form = document.getElementById('pay-form');
const btn = document.getElementById('pay-btn');
const msg = document.getElementById('msg');

async function post(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(body),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Request failed');
    return data;
}

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    btn.disabled = true;
    msg.textContent = 'Creating order...';

    try {
        // 1) Server creates the order at Razorpay
        const order = await post('/api/payments/order', Object.fromEntries(new FormData(form)));

        // 2) Open Razorpay Checkout
        const rzp = new Razorpay({
            key: order.key,
            order_id: order.order_id,
            amount: order.amount,
            currency: order.currency,
            name: 'Laravel Payment Demo',
            prefill: { name: order.name, email: order.email },
            // 3) On success, send ids + signature to the server for verification
            handler: async (response) => {
                try {
                    const result = await post('/api/payments/verify', response);
                    msg.textContent = result.message + ' Receipt: ' + result.receipt;
                } catch (err) {
                    msg.textContent = err.message;
                }
                btn.disabled = false;
            },
            modal: { ondismiss: () => { msg.textContent = 'Payment cancelled.'; btn.disabled = false; } },
        });
        rzp.on('payment.failed', (r) => { msg.textContent = 'Payment failed: ' + r.error.description; });
        rzp.open();
        msg.textContent = '';
    } catch (err) {
        msg.textContent = err.message;
        btn.disabled = false;
    }
});
</script>
</body>
</html>
