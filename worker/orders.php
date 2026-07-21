<?php
require_once __DIR__ . "/../config.php";
requireWorker();
$pageTitle = t("Worker Orders", "Maagizo ya Mfanyakazi");
$userId = $_SESSION["user_id"];
$action = $_GET["action"] ?? "list";
if ($action === "view" && !empty($_GET["id"])) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND worker_id = ?");
    $stmt->execute([$_GET["id"], $userId]);
    $order = $stmt->fetch();
    if (!$order) { redirect("orders.php", "Order not found.", "error"); }
    $items = $db->prepare("SELECT oi.*, p.name_en, p.name_sw, p.slug, (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
    $items->execute([$order["id"]]);
    $items = $items->fetchAll();
    $address = json_decode($order["shipping_address"], true);
    require_once __DIR__ . "/../includes/header.php"; ?>
    <style>.item-thumb{width:50px;height:50px;object-fit:cover;border-radius:6px;flex-shrink:0}</style>
    <div class="container py-5">
        <a href="orders.php" class="btn btn-outline-dark-custom btn-sm mb-3"><i class="fas fa-arrow-left me-1"></i><?= t("Back to orders", "Rudi kwenye maagizo") ?></a>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <div>
                <h4 class="fw-700 mb-1" style="font-family: 'Playfair Display', serif;">#<?= escape($order["order_number"]) ?></h4>
                <small class="text-muted"><i class="fas fa-calendar me-1"></i><?= date("F d, Y h:i A", strtotime($order["created_at"])) ?></small>
            </div>
            <span class="order-status order-status-<?= $order["status"] ?> fs-6"><?= ucfirst($order["status"]) ?></span>
        </div>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="form-card p-4 mb-4">
                    <h6 class="fw-700 mb-3"><i class="fas fa-user me-2 text-gold"></i><?= t("Customer", "Mteja") ?></h6>
                    <p class="mb-1"><strong><?= escape($order["customer_name"]) ?></strong></p>
                    <p class="mb-0 text-muted small"><i class="fas fa-phone me-1"></i><?= escape($order["customer_phone"]) ?></p>
                </div>
                <div class="form-card p-4 mb-4">
                    <h6 class="fw-700 mb-3"><i class="fas fa-box me-2 text-gold"></i><?= t("Items", "Bidhaa") ?></h6>
                    <?php foreach ($items as $item): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-secondary">
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($item["image"]): ?>
                            <img src="<?= SITE_URL ?>/<?= escape($item["image"]) ?>" alt="" class="item-thumb">
                            <?php endif; ?>
                            <div>
                                <strong><?= escape(t($item["name_en"], $item["name_sw"])) ?></strong>
                                <?php if ($item["size"]): ?><br><small class="text-muted"><i class="fas fa-ruler me-1"></i><?= escape($item["size"]) ?></small><?php endif; ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <small><?= $item["quantity"] ?> x <?= formatMoney($item["price"]) ?></small>
                            <br><strong><?= formatMoney($item["total"]) ?></strong>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="d-flex justify-content-between fw-700 fs-5 mt-3">
                        <span><?= t("Total", "Jumla") ?></span>
                        <span class="text-gold"><?= formatMoney($order["total"]) ?></span>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <?php if ($order["delivery_method"] === "delivery"): ?>
                <div class="form-card p-4 mb-3">
                    <h6 class="fw-700 mb-3"><i class="fas fa-map-marker-alt me-2 text-gold"></i><?= t("Delivery Address", "Anwani ya Uwasilishaji") ?></h6>
                    <div class="small">
                        <p class="mb-1"><?= escape($address["street"] ?? "") ?></p>
                        <p class="mb-1"><?= escape($address["city"] ?? "") ?>, <?= escape($address["region"] ?? "") ?></p>
                        <p class="mb-1"><?= escape($address["country"] ?? "") ?></p>
                    </div>
                    <?php if ($order["latitude"] && $order["longitude"]): ?>
                    <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $order["latitude"] ?>,<?= $order["longitude"] ?>" target="_blank" class="btn btn-success w-100 mt-3">
                        <i class="fas fa-route me-2"></i><?= t("Open Google Maps", "Fungua Google Maps") ?>
                    </a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="form-card p-4 mb-3">
                    <h6 class="fw-700 mb-3"><i class="fas fa-store me-2 text-gold"></i><?= t("Pick up at shop", "Inachukuliwa dukani") ?></h6>
                    <p class="small text-muted mb-0"><?= t("This order is for pickup at the physical shop.", "Agizo hili linachukuliwa katika duka la bidhaa.") ?></p>
                    <a href="https://www.google.com/maps/dir/?api=1&destination=INNOCE+OUTFITS,+One+way,+Tenth+Rd,+Dodoma" target="_blank" class="btn btn-success w-100 mt-3">
                        <i class="fas fa-route me-2"></i><?= t("Directions to shop", "Maelekezo ya dukani") ?>
                    </a>
                </div>
                <?php endif; ?>
                <div class="form-card p-4">
                    <h6 class="fw-700 mb-3"><i class="fas fa-credit-card me-2 text-gold"></i><?= t("Payment", "Malipo") ?></h6>
                    <div class="small">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted"><?= t("Method", "Njia") ?></span>
                            <span><?= ucfirst($order["payment_method"]) ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted"><?= t("Status", "Hali") ?></span>
                            <span class="text-<?= $order["payment_status"] === "paid" ? "success" : "warning" ?> fw-600"><?= ucfirst($order["payment_status"]) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    ?>
    <script>
    if (navigator.geolocation) {
        function sendLocation(pos) {
            var data = new FormData();
            data.append("latitude", pos.coords.latitude);
            data.append("longitude", pos.coords.longitude);
            navigator.sendBeacon("update-location.php", data);
        }
        navigator.geolocation.getCurrentPosition(sendLocation);
        setInterval(function() {
            navigator.geolocation.getCurrentPosition(sendLocation);
        }, 30000);
    }
    </script>
    <?php
    require_once __DIR__ . "/../includes/footer.php";
    exit;
}
$stmt = $db->prepare("SELECT * FROM orders WHERE worker_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();
require_once __DIR__ . "/../includes/header.php"; ?>
<div class="container py-5">
    <div class="section-header mb-4">
        <div class="section-icon bg-gold"><i class="fas fa-clipboard-list"></i></div>
        <h3 class="fw-700 mb-0" style="font-family: 'Playfair Display', serif;"><?= t("My Orders", "Maagizo Yangu") ?></h3>
        <?php if ($orders): ?><span class="text-muted ms-auto small"><?= count($orders) ?> <?= t("orders", "maagizo") ?></span><?php endif; ?>
    </div>
    <?php if (!$orders): ?>
    <div class="empty-state py-5">
        <div class="empty-icon"><i class="fas fa-box-open"></i></div>
        <h5><?= t("No orders assigned", "Hakuna maagizo yaliyokabidhiwa") ?></h5>
        <p class="text-muted"><?= t("You have not been assigned any orders yet.", "Hujakabidhiwa maagizo yoyote bado.") ?></p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?= t("Customer", "Mteja") ?></th>
                    <th><?= t("Phone", "Simu") ?></th>
                    <th><?= t("Total", "Jumla") ?></th>
                    <th><?= t("Method", "Njia") ?></th>
                    <th><?= t("Status", "Hali") ?></th>
                    <th><?= t("Date", "Tarehe") ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td class="fw-600">#<?= escape($order["order_number"]) ?></td>
                    <td><?= escape($order["customer_name"]) ?></td>
                    <td><?= escape($order["customer_phone"]) ?></td>
                    <td class="text-gold fw-600"><?= formatMoney($order["total"]) ?></td>
                    <td><span class="badge bg-<?= $order["delivery_method"] === "delivery" ? "info" : "secondary" ?>"><?= $order["delivery_method"] === "delivery" ? t("Delivery", "Uwasilishaji") : t("Pickup", "Kuchukua") ?></span></td>
                    <td><span class="order-status order-status-<?= $order["status"] ?>"><?= ucfirst($order["status"]) ?></span></td>
                    <td><small><?= date("M d, Y", strtotime($order["created_at"])) ?></small></td>
                    <td>
                        <?php if ($order["delivery_method"] === "delivery" && $order["latitude"] && $order["longitude"]): ?>
                        <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $order["latitude"] ?>,<?= $order["longitude"] ?>" target="_blank" class="btn btn-sm btn-success me-1"><i class="fas fa-route me-1"></i><?= t("Track", "Fuatilia") ?></a>
                        <?php elseif ($order["delivery_method"] === "pickup"): ?>
                        <a href="https://www.google.com/maps/dir/?api=1&destination=INNOCE+OUTFITS,+One+way,+Tenth+Rd,+Dodoma" target="_blank" class="btn btn-sm btn-success me-1"><i class="fas fa-route me-1"></i><?= t("Shop", "Duka") ?></a>
                        <?php endif; ?>
                        <a href="orders.php?action=view&id=<?= $order["id"] ?>" class="btn btn-outline-gold btn-sm"><i class="fas fa-eye me-1"></i><?= t("View", "Angalia") ?></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<script>
if (navigator.geolocation) {
    function sendLocation(pos) {
        var data = new FormData();
        data.append("latitude", pos.coords.latitude);
        data.append("longitude", pos.coords.longitude);
        navigator.sendBeacon("update-location.php", data);
    }
    navigator.geolocation.getCurrentPosition(sendLocation);
    setInterval(function() {
        navigator.geolocation.getCurrentPosition(sendLocation);
    }, 30000);
}
</script>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
