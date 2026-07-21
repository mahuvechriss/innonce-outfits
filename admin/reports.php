<?php
$tab = $_GET['tab'] ?? 'sales';
$from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to   = $_GET['to'] ?? date('Y-m-d');

// ── Sales Overview ──
$salesTotal   = 0; $salesOrders = 0; $salesAov = 0;
$salesDaily   = [];
$salesMonthly = [];
$salesCat     = [];
$salesData    = [];

if ($tab === 'sales') {
    // Summary
    $st = $db->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(total),0) as rev FROM orders WHERE payment_status='paid' AND DATE(created_at) BETWEEN ? AND ?");
    $st->execute([$from, $to]);
    $salesOrders = (int)$st->fetch()['cnt'];
    $st->execute([$from, $to]);
    $sr = $st->fetch();
    $salesTotal = (float)$sr['rev'];
    $salesAov = $salesOrders > 0 ? round($salesTotal / $salesOrders) : 0;

    // Daily breakdown
    $st = $db->prepare("SELECT DATE(created_at) as d, COUNT(*) as cnt, COALESCE(SUM(total),0) as rev FROM orders WHERE payment_status='paid' AND DATE(created_at) BETWEEN ? AND ? GROUP BY d ORDER BY d");
    $st->execute([$from, $to]);
    $salesDaily = $st->fetchAll();

    // Monthly
    $st = $db->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') as m, COUNT(*) as cnt, COALESCE(SUM(total),0) as rev FROM orders WHERE payment_status='paid' AND DATE(created_at) BETWEEN ? AND ? GROUP BY m ORDER BY m");
    $st->execute([$from, $to]);
    $salesMonthly = $st->fetchAll();

    // By category
    $st = $db->prepare("SELECT cat.name_en, COUNT(oi.id) as cnt, COALESCE(SUM(oi.total),0) as rev FROM order_items oi JOIN products p ON p.id=oi.product_id JOIN categories cat ON cat.id=p.category_id JOIN orders o ON o.id=oi.order_id WHERE o.payment_status='paid' AND DATE(o.created_at) BETWEEN ? AND ? GROUP BY cat.id, cat.name_en ORDER BY rev DESC");
    $st->execute([$from, $to]);
    $salesCat = $st->fetchAll();

    // All sales data for export
    $st = $db->prepare("SELECT o.order_number, o.created_at, o.total, o.subtotal, o.shipping, o.discount, o.payment_method, o.status, u.name as customer FROM orders o LEFT JOIN users u ON u.id=o.user_id WHERE o.payment_status='paid' AND DATE(o.created_at) BETWEEN ? AND ? ORDER BY o.created_at DESC");
    $st->execute([$from, $to]);
    $salesData = $st->fetchAll();
}

// ── Products ──
$topProducts   = [];
$lowStock      = [];
$brandPerf     = [];

if ($tab === 'products') {
    $st = $db->prepare("SELECT p.id, p.name_en, p.price, p.discount_price, p.quantity, c.name_en as cat, COUNT(oi.id) as sold, COALESCE(SUM(oi.total),0) as rev FROM products p LEFT JOIN categories c ON c.id=p.category_id LEFT JOIN order_items oi ON oi.product_id=p.id LEFT JOIN orders o ON o.id=oi.order_id AND o.payment_status='paid' AND DATE(o.created_at) BETWEEN ? AND ? GROUP BY p.id ORDER BY sold DESC LIMIT 20");
    $st->execute([$from, $to]);
    $topProducts = $st->fetchAll();

    $lowStock = $db->query("SELECT p.id, p.name_en, p.price, p.quantity, c.name_en as cat FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.quantity < 10 ORDER BY p.quantity ASC LIMIT 20")->fetchAll();

    $st = $db->prepare("SELECT COALESCE(p.brand,'General') as brand, COUNT(oi.id) as sold, COALESCE(SUM(oi.total),0) as rev FROM products p JOIN order_items oi ON oi.product_id=p.id JOIN orders o ON o.id=oi.order_id WHERE o.payment_status='paid' AND DATE(o.created_at) BETWEEN ? AND ? GROUP BY p.brand ORDER BY rev DESC");
    $st->execute([$from, $to]);
    $brandPerf = $st->fetchAll();
}

// ── Customers ──
$newCustomers    = 0;
$totalCustomers  = 0;
$repeatRate      = 0;
$topCustomers    = [];
$customerGrowth  = [];

if ($tab === 'customers') {
    $totalCustomers = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
    $st = $db->prepare("SELECT COUNT(*) FROM users WHERE role='customer' AND DATE(created_at) BETWEEN ? AND ?");
    $st->execute([$from, $to]);
    $newCustomers = (int)$st->fetchColumn();

    $st = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN ocount>1 THEN 1 ELSE 0 END) as repeat_cust FROM (SELECT user_id, COUNT(*) as ocount FROM orders WHERE payment_status='paid' AND DATE(created_at) BETWEEN ? AND ? GROUP BY user_id) t");
    $st->execute([$from, $to]);
    $rr = $st->fetch();
    $repeatRate = $rr['total'] > 0 ? round($rr['repeat_cust'] / $rr['total'] * 100, 1) : 0;

    $st = $db->prepare("SELECT u.id, u.name, u.email, u.created_at, COUNT(o.id) as orders, COALESCE(SUM(o.total),0) as spent FROM users u JOIN orders o ON o.user_id=u.id WHERE u.role='customer' AND o.payment_status='paid' AND DATE(o.created_at) BETWEEN ? AND ? GROUP BY u.id ORDER BY spent DESC LIMIT 10");
    $st->execute([$from, $to]);
    $topCustomers = $st->fetchAll();

    $st = $db->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') as m, COUNT(*) as cnt FROM users WHERE role='customer' AND DATE(created_at) BETWEEN ? AND ? GROUP BY m ORDER BY m");
    $st->execute([$from, $to]);
    $customerGrowth = $st->fetchAll();
}

// ── Payments ──
$paymentMethods = [];
$paySuccess     = 0; $payTotal = 0;

if ($tab === 'payments') {
    $st = $db->prepare("SELECT COALESCE(payment_method,'Unknown') as method, COUNT(*) as cnt, COALESCE(SUM(total),0) as rev FROM orders WHERE payment_status='paid' AND DATE(created_at) BETWEEN ? AND ? GROUP BY payment_method ORDER BY rev DESC");
    $st->execute([$from, $to]);
    $paymentMethods = $st->fetchAll();

    $st = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status='completed' OR status='paid' THEN 1 ELSE 0 END) as success FROM payment_transactions WHERE DATE(created_at) BETWEEN ? AND ?");
    $st->execute([$from, $to]);
    $ps = $st->fetch();
    $payTotal = (int)$ps['total'];
    $paySuccess = (int)$ps['success'];
}

// ── Orders Status ──
$statusFunnel = [];

if ($tab === 'orders') {
    $st = $db->prepare("SELECT status, COUNT(*) as cnt FROM orders WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY status ORDER BY FIELD(status,'pending','confirmed','processing','packed','shipped','delivered','cancelled')");
    $st->execute([$from, $to]);
    $statusFunnel = $st->fetchAll();
}

// ── Excel / PDF Export ──
if (isset($_GET['export']) && $_GET['export'] === 'xls') {
    $filename = "report-$tab-" . date('Y-m-d');
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    echo '<html><head><meta charset="UTF-8"><style>td,th{border:1px solid #ccc;padding:6px;font-size:12px}th{background:#FF8C00;color:#fff;font-weight:600}tr:nth-child(even){background:#FFF5EB}</style></head><body>';
    echo '<table>';

    if ($tab === 'sales') {
        echo '<tr><th>Order #</th><th>Date</th><th>Customer</th><th>Subtotal</th><th>Shipping</th><th>Discount</th><th>Total</th><th>Payment</th><th>Status</th></tr>';
        foreach ($salesData as $r) {
            echo '<tr><td>' . escape($r['order_number']) . '</td><td>' . $r['created_at'] . '</td><td>' . escape($r['customer'] ?? 'Guest') . '</td><td>' . number_format($r['subtotal']) . '</td><td>' . number_format($r['shipping'] ?? 0) . '</td><td>' . number_format($r['discount'] ?? 0) . '</td><td>' . number_format($r['total']) . '</td><td>' . escape($r['payment_method'] ?? '') . '</td><td>' . $r['status'] . '</td></tr>';
        }
    } elseif ($tab === 'products') {
        echo '<tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Sold</th><th>Revenue</th></tr>';
        foreach ($topProducts as $r) {
            echo '<tr><td>' . escape($r['name_en']) . '</td><td>' . escape($r['cat'] ?? '') . '</td><td>' . number_format($r['price']) . '</td><td>' . $r['quantity'] . '</td><td>' . $r['sold'] . '</td><td>' . number_format($r['rev']) . '</td></tr>';
        }
    } elseif ($tab === 'customers') {
        echo '<tr><th>Name</th><th>Email</th><th>Registered</th><th>Orders</th><th>Total Spent</th></tr>';
        foreach ($topCustomers as $r) {
            echo '<tr><td>' . escape($r['name']) . '</td><td>' . escape($r['email']) . '</td><td>' . $r['created_at'] . '</td><td>' . $r['orders'] . '</td><td>' . number_format($r['spent']) . '</td></tr>';
        }
    } elseif ($tab === 'payments') {
        echo '<tr><th>Method</th><th>Orders</th><th>Revenue</th></tr>';
        foreach ($paymentMethods as $r) {
            echo '<tr><td>' . escape($r['method']) . '</td><td>' . $r['cnt'] . '</td><td>' . number_format($r['rev']) . '</td></tr>';
        }
    } elseif ($tab === 'orders') {
        echo '<tr><th>Status</th><th>Count</th></tr>';
        foreach ($statusFunnel as $r) {
            echo '<tr><td>' . $r['status'] . '</td><td>' . $r['cnt'] . '</td></tr>';
        }
    }

    echo '</table></body></html>';
    exit;
}

// ── PDF Export ──
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    require_once __DIR__ . '/../vendor/autoload.php';

    ob_clean();
    $html = '<html><head><meta charset="UTF-8"><style>
        body{font-family:sans-serif;font-size:11px;color:#333}
        h2{color:#FF8C00;margin-bottom:5px}
        table{width:100%;border-collapse:collapse;margin-bottom:20px}
        th{background:#FF8C00;color:#fff;padding:6px 8px;text-align:left;font-size:11px}
        td{padding:5px 8px;border-bottom:1px solid #eee}
        tr:nth-child(even){background:#FFF5EB}
        .summary{display:flex;gap:15px;margin-bottom:20px}
        .card{border:1px solid #ddd;padding:12px 20px;text-align:center;flex:1}
        .num{font-size:18px;font-weight:700;color:#FF8C00}
        .lbl{font-size:10px;color:#888}
        .bar-bg{height:8px;background:#eee;border-radius:4px}
        .bar-fill{height:100%;background:#FF8C00;border-radius:4px}
        .badge{display:inline-block;padding:2px 8px;border-radius:3px;color:#fff;font-size:10px}
    </style></head><body>';
    $logoPath = __DIR__ . '/../assets/images/logo.png';
    $logoData = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : '';
    $logoSrc = $logoData ? 'data:image/png;base64,' . $logoData : '';
    $html .= '<div style="display:flex;align-items:center;gap:12px;margin-bottom:15px">';
    $html .= $logoSrc ? '<img src="' . $logoSrc . '" style="height:40px;width:40px;border-radius:50%">' : '';
    $html .= '<div><h2 style="margin:0">' . ucfirst($tab) . ' Report</h2><small>' . SITE_NAME . '</small></div></div>';
    $html .= "<p>Period: $from — $to</p>";

    if ($tab === 'sales') {
        $html .= '<div class="summary"><div class="card"><div class="num">' . formatMoney($salesTotal) . '</div><div class="lbl">Revenue</div></div><div class="card"><div class="num">' . $salesOrders . '</div><div class="lbl">Orders</div></div><div class="card"><div class="num">' . formatMoney($salesAov) . '</div><div class="lbl">Avg Order Value</div></div></div>';
        $html .= '<h3>Daily Sales</h3><table><tr><th>Date</th><th>Orders</th><th>Revenue</th></tr>';
        foreach ($salesDaily as $d) $html .= "<tr><td>{$d['d']}</td><td>{$d['cnt']}</td><td>" . formatMoney($d['rev']) . '</td></tr>';
        $html .= '</table><h3>Monthly Sales</h3><table><tr><th>Month</th><th>Orders</th><th>Revenue</th></tr>';
        foreach ($salesMonthly as $d) $html .= "<tr><td>{$d['m']}</td><td>{$d['cnt']}</td><td>" . formatMoney($d['rev']) . '</td></tr>';
        $html .= '</table>';
    } elseif ($tab === 'products') {
        $html .= '<h3>Top Selling Products</h3><table><tr><th>#</th><th>Product</th><th>Category</th><th>Price</th><th>Sold</th><th>Revenue</th></tr>';
        $i=0; foreach ($topProducts as $r) { $i++; $html .= "<tr><td>$i</td><td>" . escape($r['name_en']) . '</td><td>' . escape($r['cat']??'') . '</td><td>' . formatMoney($r['discount_price']?:$r['price']) . "</td><td>{$r['sold']}</td><td>" . formatMoney($r['rev']) . '</td></tr>'; }
        $html .= '</table><h3>Low Stock Alerts</h3><table><tr><th>Product</th><th>Stock</th></tr>';
        foreach ($lowStock as $r) $html .= '<tr><td>' . escape($r['name_en']) . "</td><td>{$r['quantity']}</td></tr>";
        $html .= '</table>';
    } elseif ($tab === 'customers') {
        $html .= '<div class="summary"><div class="card"><div class="num">' . $totalCustomers . '</div><div class="lbl">Total Customers</div></div><div class="card"><div class="num">' . $newCustomers . '</div><div class="lbl">New</div></div><div class="card"><div class="num">' . $repeatRate . '%</div><div class="lbl">Repeat Rate</div></div></div>';
        $html .= '<h3>Top Customers</h3><table><tr><th>Name</th><th>Email</th><th>Orders</th><th>Spent</th></tr>';
        foreach ($topCustomers as $r) $html .= '<tr><td>' . escape($r['name']) . '</td><td>' . escape($r['email']) . "</td><td>{$r['orders']}</td><td>" . formatMoney($r['spent']) . '</td></tr>';
        $html .= '</table>';
    } elseif ($tab === 'payments') {
        $html .= '<div class="summary"><div class="card"><div class="num">' . $payTotal . '</div><div class="lbl">Transactions</div></div><div class="card"><div class="num" style="color:green">' . $paySuccess . '</div><div class="lbl">Successful</div></div><div class="card"><div class="num" style="color:#D42426">' . ($payTotal-$paySuccess) . '</div><div class="lbl">Failed</div></div></div>';
        $html .= '<h3>Revenue by Method</h3><table><tr><th>Method</th><th>Orders</th><th>Revenue</th></tr>';
        foreach ($paymentMethods as $r) $html .= '<tr><td>' . escape(ucwords(str_replace('_',' ',$r['method']))) . "</td><td>{$r['cnt']}</td><td>" . formatMoney($r['rev']) . '</td></tr>';
        $html .= '</table>';
    } elseif ($tab === 'orders') {
        $html .= '<h3>Order Status</h3><table><tr><th>Status</th><th>Count</th></tr>';
        foreach ($statusFunnel as $r) $html .= "<tr><td>{$r['status']}</td><td>{$r['cnt']}</td></tr>";
        $html .= '</table>';
    }

    $html .= '</body></html>';

    $dompdf = new Dompdf\Dompdf();
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->loadHtml($html);
    $dompdf->render();
    $dompdf->stream("report-$tab-" . date('Y-m-d') . '.pdf', ['Attachment' => true]);
    exit;
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-3">
        <img src="<?= SITE_URL ?>/assets/images/logo.png" alt="<?= SITE_NAME ?>" style="height:45px;width:45px;border-radius:50%;object-fit:cover">
        <div>
            <h4 class="fw-600 mb-0"><?= __t('reports') ?></h4>
            <small class="text-muted"><?= SITE_NAME ?></small>
        </div>
    </div>
    <div class="d-flex gap-2 align-items-center no-print">
        <a href="?action=reports&tab=<?= $tab ?>&from=<?= $from ?>&to=<?= $to ?>&export=xls" class="btn btn-sm btn-outline-dark-custom"><i class="fas fa-file-excel me-1"></i>Excel</a>
        <a href="?action=reports&tab=<?= $tab ?>&from=<?= $from ?>&to=<?= $to ?>&export=pdf" class="btn btn-sm btn-outline-dark-custom"><i class="fas fa-file-pdf me-1"></i>PDF</a>
        <button onclick="window.print()" class="btn btn-sm btn-outline-dark-custom"><i class="fas fa-print me-1"></i>Print</button>
    </div>
</div>

<!-- Filter Form -->
<form class="row g-2 mb-3 align-items-end no-print" method="get">
    <input type="hidden" name="action" value="reports">
    <input type="hidden" name="tab" value="<?= $tab ?>">
    <div class="col-auto">
        <label class="small fw-600 d-block"><?= __t('date') ?></label>
        <input type="date" name="from" class="form-control form-control-sm" value="<?= $from ?>">
    </div>
    <div class="col-auto">
        <label class="small fw-600 d-block">&nbsp;</label>
        <input type="date" name="to" class="form-control form-control-sm" value="<?= $to ?>">
    </div>
    <div class="col-auto">
        <label class="small fw-600 d-block">&nbsp;</label>
        <button class="btn btn-sm text-nowrap" style="background:var(--gold,#FF8C00);color:#fff;border:none"><i class="fas fa-filter me-1"></i>Filter</button>
    </div>
    <div class="col-auto">
        <label class="small fw-600 d-block">&nbsp;</label>
        <a href="?action=reports&tab=<?= $tab ?>" class="btn btn-sm btn-outline-dark-custom">Clear</a>
    </div>
</form>

<!-- Tabs -->
<div class="tabs mb-3 d-flex flex-wrap gap-1 no-print">
    <?php $tabs = [
        'sales'     => ['Sales', 'fa-chart-line'],
        'products'  => ['Products', 'fa-box'],
        'customers' => ['Customers', 'fa-users'],
        'payments'  => ['Payments', 'fa-credit-card'],
        'orders'    => ['Orders', 'fa-truck'],
    ];
    foreach ($tabs as $key => $info): ?>
        <a href="?action=reports&tab=<?= $key ?>&from=<?= $from ?>&to=<?= $to ?>" class="btn btn-sm <?= $tab === $key ? 'text-white' : 'btn-outline-dark-custom' ?>" style="<?= $tab === $key ? 'background:var(--gold,#FF8C00);border-color:var(--gold,#FF8C00)' : '' ?>"><i class="fas <?= $info[1] ?> me-1"></i><?= $info[0] ?></a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'sales'): ?>
    <!-- Summary Cards -->
    <div class="row g-3 mb-3">
        <div class="col-md-3 col-6"><div class="border p-3 rounded text-center"><div class="fs-3 fw-700" style="color:var(--gold,#FF8C00)"><?= formatMoney($salesTotal) ?></div><div class="small text-muted">Revenue</div></div></div>
        <div class="col-md-3 col-6"><div class="border p-3 rounded text-center"><div class="fs-3 fw-700"><?= $salesOrders ?></div><div class="small text-muted">Orders</div></div></div>
        <div class="col-md-3 col-6"><div class="border p-3 rounded text-center"><div class="fs-3 fw-700"><?= formatMoney($salesAov) ?></div><div class="small text-muted">Avg Order Value</div></div></div>
        <div class="col-md-3 col-6"><div class="border p-3 rounded text-center"><div class="fs-3 fw-700"><?= count($salesDaily) ?></div><div class="small text-muted">Days with Sales</div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-md-6"><div class="border p-3"><h6 class="fw-600 mb-2">Daily Sales</h6>
            <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Date</th><th>Orders</th><th>Revenue</th></tr></thead>
            <tbody><?php foreach ($salesDaily as $d): ?><tr><td><?= $d['d'] ?></td><td><?= $d['cnt'] ?></td><td><?= formatMoney($d['rev']) ?></td></tr><?php endforeach; if (!$salesDaily): ?><tr><td colspan="3" class="text-muted text-center">No data</td></tr><?php endif; ?></tbody></table></div></div></div>
        <div class="col-md-6"><div class="border p-3"><h6 class="fw-600 mb-2">Monthly Sales</h6>
            <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Month</th><th>Orders</th><th>Revenue</th></tr></thead>
            <tbody><?php foreach ($salesMonthly as $d): ?><tr><td><?= $d['m'] ?></td><td><?= $d['cnt'] ?></td><td><?= formatMoney($d['rev']) ?></td></tr><?php endforeach; if (!$salesMonthly): ?><tr><td colspan="3" class="text-muted text-center">No data</td></tr><?php endif; ?></tbody></table></div></div></div>
        <div class="col-12"><div class="border p-3"><h6 class="fw-600 mb-2">Revenue by Category</h6>
            <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Category</th><th>Items Sold</th><th>Revenue</th><th>%</th></tr></thead>
            <tbody><?php foreach ($salesCat as $d): $pct = $salesTotal > 0 ? round($d['rev']/$salesTotal*100,1) : 0; ?><tr><td><?= escape($d['name_en']) ?></td><td><?= $d['cnt'] ?></td><td><?= formatMoney($d['rev']) ?></td><td><div class="d-flex align-items-center gap-2"><div class="bar-bg flex-grow-1" style="height:8px;background:#eee;border-radius:4px"><div class="bar-fill" style="width:<?= $pct ?>%;height:100%;background:var(--gold,#FF8C00);border-radius:4px;transition:width .3s"></div></div><span class="small"><?= $pct ?>%</span></div></td></tr><?php endforeach; if (!$salesCat): ?><tr><td colspan="4" class="text-muted text-center">No data</td></tr><?php endif; ?></tbody></table></div></div></div>
    </div>

<?php elseif ($tab === 'products'): ?>
    <div class="row g-3">
        <div class="col-md-7"><div class="border p-3"><h6 class="fw-600 mb-2">Top Selling Products</h6>
            <div class="table-responsive"><table class="table table-sm"><thead><tr><th>#</th><th>Product</th><th>Category</th><th>Price</th><th>Sold</th><th>Revenue</th></tr></thead>
            <tbody><?php $i=0; foreach ($topProducts as $r): $i++; ?><tr><td><?= $i ?></td><td><a href="?action=products&edit=<?= $r['id'] ?>"><?= escape($r['name_en']) ?></a></td><td><?= escape($r['cat'] ?? '') ?></td><td><?= formatMoney($r['discount_price'] ?: $r['price']) ?></td><td><span class="badge bg-dark"><?= $r['sold'] ?></span></td><td><?= formatMoney($r['rev']) ?></td></tr><?php endforeach; if (!$topProducts): ?><tr><td colspan="6" class="text-muted text-center">No sales in this period</td></tr><?php endif; ?></tbody></table></div></div></div>
        <div class="col-md-5">
            <div class="border p-3 mb-3"><h6 class="fw-600 mb-2">Low Stock Alerts</h6>
                <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Product</th><th>Category</th><th>Stock</th></tr></thead>
                <tbody><?php foreach ($lowStock as $r): ?><tr><td><a href="?action=products&edit=<?= $r['id'] ?>"><?= escape($r['name_en']) ?></a></td><td><?= escape($r['cat'] ?? '') ?></td><td><span class="badge" style="background:#D42426;color:#fff"><?= $r['quantity'] ?></span></td></tr><?php endforeach; if (!$lowStock): ?><tr><td colspan="3" class="text-muted text-center">All well stocked</td></tr><?php endif; ?></tbody></table></div></div>
            <div class="border p-3"><h6 class="fw-600 mb-2">Brand Performance</h6>
                <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Brand</th><th>Sold</th><th>Revenue</th></tr></thead>
                <tbody><?php foreach ($brandPerf as $r): ?><tr><td><?= escape($r['brand']) ?></td><td><?= $r['sold'] ?></td><td><?= formatMoney($r['rev']) ?></td></tr><?php endforeach; if (!$brandPerf): ?><tr><td colspan="3" class="text-muted text-center">No data</td></tr><?php endif; ?></tbody></table></div></div>
        </div>
    </div>

<?php elseif ($tab === 'customers'): ?>
    <div class="row g-3 mb-3">
        <div class="col-md-3 col-6"><div class="border p-3 rounded text-center"><div class="fs-3 fw-700"><?= $totalCustomers ?></div><div class="small text-muted">Total Customers</div></div></div>
        <div class="col-md-3 col-6"><div class="border p-3 rounded text-center"><div class="fs-3 fw-700" style="color:var(--gold,#FF8C00)"><?= $newCustomers ?></div><div class="small text-muted">New (this period)</div></div></div>
        <div class="col-md-3 col-6"><div class="border p-3 rounded text-center"><div class="fs-3 fw-700"><?= $repeatRate ?>%</div><div class="small text-muted">Repeat Rate</div></div></div>
        <div class="col-md-3 col-6"><div class="border p-3 rounded text-center"><div class="fs-3 fw-700"><?= count($customerGrowth) ?></div><div class="small text-muted">Active Months</div></div></div>
    </div>
    <div class="row g-3">
        <div class="col-md-6"><div class="border p-3"><h6 class="fw-600 mb-2">Top Customers by Spend</h6>
            <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Name</th><th>Email</th><th>Orders</th><th>Total Spent</th></tr></thead>
            <tbody><?php foreach ($topCustomers as $r): ?><tr><td><?= escape($r['name']) ?></td><td><?= escape($r['email']) ?></td><td><?= $r['orders'] ?></td><td><?= formatMoney($r['spent']) ?></td></tr><?php endforeach; if (!$topCustomers): ?><tr><td colspan="4" class="text-muted text-center">No customers in this period</td></tr><?php endif; ?></tbody></table></div></div></div>
        <div class="col-md-6"><div class="border p-3"><h6 class="fw-600 mb-2">Customer Growth</h6>
            <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Month</th><th>New Customers</th></tr></thead>
            <tbody><?php foreach ($customerGrowth as $r): ?><tr><td><?= $r['m'] ?></td><td><?= $r['cnt'] ?></td></tr><?php endforeach; if (!$customerGrowth): ?><tr><td colspan="2" class="text-muted text-center">No data</td></tr><?php endif; ?></tbody></table></div></div></div>
    </div>

<?php elseif ($tab === 'payments'): ?>
    <div class="row g-3 mb-3">
        <div class="col-md-3 col-6"><div class="border p-3 rounded text-center"><div class="fs-3 fw-700"><?= $payTotal ?></div><div class="small text-muted">Total Transactions</div></div></div>
        <div class="col-md-3 col-6"><div class="border p-3 rounded text-center"><div class="fs-3 fw-700" style="color:green"><?= $paySuccess ?></div><div class="small text-muted">Successful</div></div></div>
        <div class="col-md-3 col-6"><div class="border p-3 rounded text-center"><div class="fs-3 fw-700" style="color:#D42426"><?= $payTotal - $paySuccess ?></div><div class="small text-muted">Failed</div></div></div>
        <div class="col-md-3 col-6"><div class="border p-3 rounded text-center"><div class="fs-3 fw-700"><?= $payTotal > 0 ? round($paySuccess/$payTotal*100,1) : 0 ?>%</div><div class="small text-muted">Success Rate</div></div></div>
    </div>
    <div class="border p-3"><h6 class="fw-600 mb-2">Revenue by Payment Method</h6>
        <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Method</th><th>Orders</th><th>Revenue</th><th>%</th></tr></thead>
        <tbody><?php $payTotalRev = array_sum(array_column($paymentMethods, 'rev')); foreach ($paymentMethods as $r): $pct = $payTotalRev > 0 ? round($r['rev']/$payTotalRev*100,1) : 0; ?><tr><td><?= escape(ucwords(str_replace('_',' ',$r['method']))) ?></td><td><?= $r['cnt'] ?></td><td><?= formatMoney($r['rev']) ?></td><td><div class="d-flex align-items-center gap-2"><div class="bar-bg flex-grow-1" style="height:8px;background:#eee;border-radius:4px"><div class="bar-fill" style="width:<?= $pct ?>%;height:100%;background:var(--gold,#FF8C00);border-radius:4px"></div></div><span class="small"><?= $pct ?>%</span></div></td></tr><?php endforeach; if (!$paymentMethods): ?><tr><td colspan="4" class="text-muted text-center">No data</td></tr><?php endif; ?></tbody></table></div></div>

<?php elseif ($tab === 'orders'): ?>
    <div class="border p-3"><h6 class="fw-600 mb-2">Order Status Funnel</h6>
        <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Status</th><th>Count</th><th>%</th></tr></thead>
        <tbody><?php $totalOrders = array_sum(array_column($statusFunnel, 'cnt')); foreach ($statusFunnel as $r): $pct = $totalOrders > 0 ? round($r['cnt']/$totalOrders*100,1) : 0; ?><tr><td><span class="badge" style="background:<?= match($r['status']){'pending'=>'#FF8C00','confirmed'=>'#0066CC','processing'=>'#9B59B6','packed'=>'#1EB53A','shipped'=>'#008000','delivered'=>'#059669','cancelled'=>'#D42426',default=>'#666'} ?>"><?= ucfirst($r['status']) ?></span></td><td><?= $r['cnt'] ?></td><td><div class="d-flex align-items-center gap-2"><div class="bar-bg flex-grow-1" style="height:8px;background:#eee;border-radius:4px"><div class="bar-fill" style="width:<?= $pct ?>%;height:100%;background:var(--gold,#FF8C00);border-radius:4px"></div></div><span class="small"><?= $pct ?>%</span></div></td></tr><?php endforeach; if (!$statusFunnel): ?><tr><td colspan="3" class="text-muted text-center">No orders in this period</td></tr><?php endif; ?></tbody></table></div></div>

<?php endif; ?>

<style>
@media print {
    .no-print { display: none !important; }
    nav.navbar, .admin-sidebar { display: none !important; }
    .admin-content { margin: 0 !important; padding: 10px !important; width: 100% !important; max-width: 100% !important; flex: 0 0 100% !important; }
    .container-fluid > .row > .col-md-10 { flex: 0 0 100% !important; width: 100% !important; max-width: 100% !important; }
    .admin-page .container-fluid > .row { display: block !important; }
    .border { border: 1px solid #ddd !important; }
    .badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .bar-fill, .bar-bg { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    body { font-size: 11px; }
    .table { font-size: 10px; }
}
.tabs .btn { font-size: 13px; padding: 4px 12px; }
</style>
