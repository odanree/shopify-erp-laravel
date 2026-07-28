const http = require('http');

const inventory = new Map();

const server = http.createServer((req, res) => {
    let body = '';
    req.on('data', (chunk) => (body += chunk));
    req.on('end', () => {
        const send = (status, data) => {
            res.writeHead(status, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify(data));
        };

        if (req.method === 'GET' && req.url === '/health') return send(200, { ok: true });

        if (req.method === 'POST' && req.url === '/orders') {
            const payload = safeJson(body);
            return send(201, { erp_order_id: `ERP-${Date.now()}`, echo: payload });
        }

        if (req.method === 'POST' && req.url === '/inventory') {
            const { sku, quantity } = safeJson(body) || {};
            const corrected = Math.max(0, Math.min(999, Number(quantity) || 0));
            inventory.set(sku, corrected);
            return send(200, { sku, quantity: corrected, corrected: corrected !== Number(quantity) });
        }

        if (req.method === 'GET' && req.url.startsWith('/inventory/')) {
            const sku = decodeURIComponent(req.url.split('/inventory/')[1]);
            return send(200, { sku, quantity: inventory.get(sku) ?? 0 });
        }

        send(404, { error: 'not_found' });
    });
});

function safeJson(s) { try { return JSON.parse(s); } catch { return null; } }

const port = process.env.PORT || 3000;
server.listen(port, () => console.log(`mock-erp listening on :${port}`));
