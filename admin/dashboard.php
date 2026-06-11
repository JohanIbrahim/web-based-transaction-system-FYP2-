<?php
/**
 * Admin Dashboard — Analytics Panel with Chart.js
 * 
 * Professional dashboard with KPI cards, charts, and data tables.
 * Date-filterable via GET parameters.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin_auth.php';

startSession();
requireAdminRole();

$pageTitle = 'Dashboard - Smart Transaction System';

// ──────────────────────────────────────────────
// 1. DATE FILTER
// ──────────────────────────────────────────────
$today = date('Y-m-d');
$firstOfMonth = date('Y-m-01');

$from = $_GET['from'] ?? $firstOfMonth;
$to   = $_GET['to']   ?? $today;

// Validate dates
$dtFrom = DateTime::createFromFormat('Y-m-d', $from);
$dtTo   = DateTime::createFromFormat('Y-m-d', $to);
if (!$dtFrom || !$dtTo) {
    $from = $firstOfMonth;
    $to   = $today;
    $dtFrom = DateTime::createFromFormat('Y-m-d', $from);
    $dtTo   = DateTime::createFromFormat('Y-m-d', $to);
}
if ($dtFrom > $dtTo) {
    $tmp = $from; $from = $to; $to = $tmp;
    $dtFrom = DateTime::createFromFormat('Y-m-d', $from);
    $dtTo   = DateTime::createFromFormat('Y-m-d', $to);
}

// Previous period (same length before current)
$interval = $dtFrom->diff($dtTo);
$prevTo   = (clone $dtFrom)->modify('-1 day')->format('Y-m-d');
$prevFrom = (clone $dtFrom)->modify("-{$interval->days} days")->modify('-1 day')->format('Y-m-d');

$dateLabel = 'Showing: ' . $dtFrom->format('d M Y') . ' – ' . $dtTo->format('d M Y');

// Determine grouping for Chart A
$daysDiff = (int) $dtFrom->diff($dtTo)->days;
if ($daysDiff <= 1) {
    $groupFormat = '%H'; // hours
    $groupSelect = "DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')";
    $groupBy     = "HOUR(created_at)";
    $labelFormat = 'hour';
} elseif ($daysDiff <= 14) {
    $groupSelect = "DATE(created_at)";
    $groupBy     = "DATE(created_at)";
    $labelFormat = 'day';
} elseif ($daysDiff <= 62) {
    $groupSelect = "DATE(created_at)";
    $groupBy     = "DATE(created_at)";
    $labelFormat = 'day';
} elseif ($daysDiff <= 365) {
    $groupSelect = "DATE_FORMAT(created_at, '%Y-%u')";
    $groupBy     = "YEARWEEK(created_at, 1)";
    $labelFormat = 'week';
} else {
    $groupSelect = "DATE_FORMAT(created_at, '%Y-%m')";
    $groupBy     = "DATE_FORMAT(created_at, '%Y-%m')";
    $labelFormat = 'month';
}

$pdo = getDBConnection();

// ──────────────────────────────────────────────
// 2. KPI QUERIES
// ──────────────────────────────────────────────

// Current period
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status='completed' AND DATE(created_at) BETWEEN :from AND :to");
$stmt->execute([':from' => $from, ':to' => $to]);
$kpi_revenue = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) BETWEEN :from AND :to");
$stmt->execute([':from' => $from, ':to' => $to]);
$kpi_total_orders = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status='completed' AND DATE(created_at) BETWEEN :from AND :to");
$stmt->execute([':from' => $from, ':to' => $to]);
$kpi_completed = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status='pending' AND DATE(created_at) BETWEEN :from AND :to");
$stmt->execute([':from' => $from, ':to' => $to]);
$kpi_pending = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status='cancelled' AND DATE(created_at) BETWEEN :from AND :to");
$stmt->execute([':from' => $from, ':to' => $to]);
$kpi_cancelled = (int) $stmt->fetchColumn();

$kpi_avg_order = $kpi_completed > 0 ? $kpi_revenue / $kpi_completed : 0;

$stmt = $pdo->query("SELECT COUNT(*) FROM promotions WHERE is_active=1 AND start_date <= CURDATE() AND end_date >= CURDATE()");
$kpi_active_promos = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM coupons WHERE is_used=1 AND used_at BETWEEN :from AND :to");
$stmt->execute([':from' => $from . ' 00:00:00', ':to' => $to . ' 23:59:59']);
$kpi_coupons_redeemed = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(o.total_amount - (SELECT COALESCE(SUM(subtotal),0) FROM order_items WHERE order_id=o.id)),0) FROM orders o WHERE o.coupon_id IS NOT NULL AND o.status='completed' AND DATE(o.created_at) BETWEEN :from AND :to");
$stmt->execute([':from' => $from, ':to' => $to]);
$kpi_discount_given = (float) $stmt->fetchColumn();

// Previous period (for trends)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status='completed' AND DATE(created_at) BETWEEN :from AND :to");
$stmt->execute([':from' => $prevFrom, ':to' => $prevTo]);
$prev_revenue = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) BETWEEN :from AND :to");
$stmt->execute([':from' => $prevFrom, ':to' => $prevTo]);
$prev_orders = (int) $stmt->fetchColumn();

$revenue_trend = $prev_revenue > 0 ? round(($kpi_revenue - $prev_revenue) / $prev_revenue * 100, 1) : 0;
$orders_trend  = $prev_orders > 0 ? round(($kpi_total_orders - $prev_orders) / $prev_orders * 100, 1) : 0;

// ──────────────────────────────────────────────
// 3. CHART A — Revenue & Orders Over Time
// ──────────────────────────────────────────────
$chartA_labels = [];
$chartA_revenue = [];
$chartA_orders = [];

$stmt = $pdo->prepare("SELECT {$groupSelect} AS period, SUM(total_amount) AS revenue, COUNT(*) AS order_count FROM orders WHERE status='completed' AND DATE(created_at) BETWEEN :from AND :to GROUP BY {$groupBy} ORDER BY period ASC");
$stmt->execute([':from' => $from, ':to' => $to]);
$chartARows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build lookup
$chartALookup = [];
foreach ($chartARows as $r) {
    $chartALookup[$r['period']] = $r;
}

// Generate full date range
$cursor = clone $dtFrom;
$endCursor = clone $dtTo;
while ($cursor <= $endCursor) {
    if ($labelFormat === 'hour') {
        for ($h = 0; $h <= 23; $h++) {
            $period = $cursor->format('Y-m-d') . sprintf(' %02d:00:00', $h);
            $label = $cursor->format('Y-m-d') . ' ' . $h . ':00';
            $chartA_labels[] = $label;
            $chartA_revenue[] = (float)($chartALookup[$period]['revenue'] ?? 0);
            $chartA_orders[] = (int)($chartALookup[$period]['order_count'] ?? 0);
        }
    } elseif ($labelFormat === 'day') {
        $period = $cursor->format('Y-m-d');
        $chartA_labels[] = $cursor->format('d M');
        $chartA_revenue[] = (float)($chartALookup[$period]['revenue'] ?? 0);
        $chartA_orders[] = (int)($chartALookup[$period]['order_count'] ?? 0);
    } elseif ($labelFormat === 'week') {
        $period = $cursor->format('Y') . '-' . $cursor->format('W');
        $chartA_labels[] = 'W' . $cursor->format('W');
        $chartA_revenue[] = (float)($chartALookup[$period]['revenue'] ?? 0);
        $chartA_orders[] = (int)($chartALookup[$period]['order_count'] ?? 0);
        $cursor->modify('+6 days');
    } elseif ($labelFormat === 'month') {
        $period = $cursor->format('Y-m');
        $chartA_labels[] = $cursor->format('M Y');
        $chartA_revenue[] = (float)($chartALookup[$period]['revenue'] ?? 0);
        $chartA_orders[] = (int)($chartALookup[$period]['order_count'] ?? 0);
        $cursor->modify('+1 month');
        continue;
    }
    $cursor->modify('+1 day');
}

// ──────────────────────────────────────────────
// 4. CHART B — Order Status Breakdown
// ──────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT status, COUNT(*) AS count FROM orders WHERE DATE(created_at) BETWEEN :from AND :to GROUP BY status");
$stmt->execute([':from' => $from, ':to' => $to]);
$chartB_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
$chartB_labels = [];
$chartB_values = [];
$chartB_colors = [];
$statusColors = [
    'completed' => '#437a22',
    'pending'   => '#d19900',
    'preparing' => '#006494',
    'ready'     => '#01696f',
    'cancelled' => '#a12c7b',
];
foreach ($chartB_data as $r) {
    $chartB_labels[] = ucfirst($r['status']);
    $chartB_values[] = (int)$r['count'];
    $chartB_colors[] = $statusColors[$r['status']] ?? '#a8a29e';
}

// ──────────────────────────────────────────────
// 5. CHART C — Top 5 Best-Selling Items
// ──────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT oi.product_name, SUM(oi.quantity) AS total_qty, SUM(oi.subtotal) AS total_revenue FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE o.status='completed' AND DATE(o.created_at) BETWEEN :from AND :to GROUP BY oi.product_name ORDER BY total_qty DESC LIMIT 5");
$stmt->execute([':from' => $from, ':to' => $to]);
$chartC_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
$chartC_labels = [];
$chartC_values = [];
$chartC_revenues = [];
foreach ($chartC_data as $r) {
    $chartC_labels[] = $r['product_name'];
    $chartC_values[] = (int)$r['total_qty'];
    $chartC_revenues[] = (float)$r['total_revenue'];
}
$chartC_labels = array_reverse($chartC_labels);
$chartC_values = array_reverse($chartC_values);
$chartC_revenues = array_reverse($chartC_revenues);

// ──────────────────────────────────────────────
// 6. CHART D — Revenue by Payment Method
// ──────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT p.payment_method, COUNT(*) AS count, SUM(o.total_amount) AS revenue FROM payments p JOIN orders o ON p.order_id = o.id WHERE o.status='completed' AND DATE(o.created_at) BETWEEN :from AND :to GROUP BY p.payment_method");
$stmt->execute([':from' => $from, ':to' => $to]);
$chartD_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
$chartD_labels = [];
$chartD_values = [];
$chartD_colors = [];
$payColors = [
    'cash'    => '#da7101',
    'ewallet' => '#01696f',
    'online'  => '#006494',
];
foreach ($chartD_data as $r) {
    $chartD_labels[] = ucfirst($r['payment_method']);
    $chartD_values[] = (float)$r['revenue'];
    $chartD_colors[] = $payColors[$r['payment_method']] ?? '#a8a29e';
}

// ──────────────────────────────────────────────
// 7. CHART E — Hourly Order Distribution
// ──────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT HOUR(created_at) AS hour, COUNT(*) AS order_count FROM orders WHERE DATE(created_at) BETWEEN :from AND :to GROUP BY HOUR(created_at) ORDER BY hour ASC");
$stmt->execute([':from' => $from, ':to' => $to]);
$chartE_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
$chartELookup = [];
foreach ($chartE_data as $r) {
    $chartELookup[(int)$r['hour']] = (int)$r['order_count'];
}
$chartE_labels = [];
$chartE_values = [];
$chartE_colors = [];
$maxOrders = !empty($chartE_data) ? max(array_column($chartE_data, 'order_count')) : 0;
for ($h = 0; $h <= 23; $h++) {
    $chartE_labels[] = ($h === 0 ? '12AM' : ($h < 12 ? $h . 'AM' : ($h === 12 ? '12PM' : ($h - 12) . 'PM')));
    $val = $chartELookup[$h] ?? 0;
    $chartE_values[] = $val;
    if ($maxOrders > 0 && $val === $maxOrders) {
        $chartE_colors[] = '#d19900'; // peak = gold
    } else {
        $intensity = $maxOrders > 0 ? $val / $maxOrders : 0;
        $r = 1; $g = 105 + round((1 - $intensity) * 50); $b = 111 + round((1 - $intensity) * 50);
        $chartE_colors[] = "rgba({$r},{$g},{$b},0.8)";
    }
}

// ──────────────────────────────────────────────
// 8. RECENT ORDERS
// ──────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT o.id, o.customer_name, o.total_amount, o.status, o.payment_status, o.created_at, (SELECT COUNT(*) FROM order_items WHERE order_id=o.id) AS item_count FROM orders o ORDER BY o.created_at DESC LIMIT 10");
$stmt->execute();
$recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ──────────────────────────────────────────────
// 9. SUMMARY STATS
// ──────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi JOIN orders o ON oi.order_id=o.id WHERE o.status='completed' AND DATE(o.created_at) BETWEEN :from AND :to");
$stmt->execute([':from' => $from, ':to' => $to]);
$total_items_sold = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM coupons WHERE is_used=1 AND used_at BETWEEN :from AND :to");
$stmt->execute([':from' => $from . ' 00:00:00', ':to' => $to . ' 23:59:59']);
$coupons_used = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status='cancelled' AND DATE(created_at) BETWEEN :from AND :to");
$stmt->execute([':from' => $from, ':to' => $to]);
$cancelled_count = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT u.name, COUNT(o.id) AS cnt FROM orders o JOIN users u ON o.customer_id=u.id WHERE o.status='completed' AND DATE(o.created_at) BETWEEN :from AND :to GROUP BY o.customer_id ORDER BY cnt DESC LIMIT 1");
$stmt->execute([':from' => $from, ':to' => $to]);
$topCustomerRow = $stmt->fetch(PDO::FETCH_ASSOC);
$top_customer = $topCustomerRow ? $topCustomerRow['name'] : 'N/A';

$stmt = $pdo->prepare("SELECT DATE(created_at) AS d, COUNT(*) AS cnt FROM orders WHERE DATE(created_at) BETWEEN :from AND :to GROUP BY DATE(created_at) ORDER BY cnt DESC LIMIT 1");
$stmt->execute([':from' => $from, ':to' => $to]);
$busiestDayRow = $stmt->fetch(PDO::FETCH_ASSOC);
$busiest_day = $busiestDayRow ? date('l, d M Y', strtotime($busiestDayRow['d'])) : 'N/A';

// Promo orders count
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT o.id) FROM orders o JOIN order_items oi ON oi.order_id=o.id JOIN promotion_products pp ON pp.product_id=oi.product_id WHERE o.status='completed' AND DATE(o.created_at) BETWEEN :from AND :to");
$stmt->execute([':from' => $from, ':to' => $to]);
$promo_orders = (int) $stmt->fetchColumn();

// ──────────────────────────────────────────────
// JSON ENCODE FOR CHARTS
// ──────────────────────────────────────────────
$json_chartA_labels  = json_encode($chartA_labels);
$json_chartA_revenue = json_encode($chartA_revenue);
$json_chartA_orders  = json_encode($chartA_orders);
$json_chartB_labels  = json_encode($chartB_labels);
$json_chartB_values  = json_encode($chartB_values);
$json_chartB_colors  = json_encode($chartB_colors);
$json_chartC_labels  = json_encode($chartC_labels);
$json_chartC_values  = json_encode($chartC_values);
$json_chartC_revs    = json_encode($chartC_revenues);
$json_chartD_labels  = json_encode($chartD_labels);
$json_chartD_values  = json_encode($chartD_values);
$json_chartD_colors  = json_encode($chartD_colors);
$json_chartE_labels  = json_encode($chartE_labels);
$json_chartE_values  = json_encode($chartE_values);
$json_chartE_colors  = json_encode($chartE_colors);

include __DIR__ . '/../includes/header.php';
?>
<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>

<style>
/* ── Dashboard Layout ── */
.dashboard-filter {
    position: sticky;
    top: 60px;
    z-index: 100;
    background: #fff;
    border-bottom: 1px solid var(--neutral-200);
    padding: 0.75rem 0;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm);
}
.filter-inner {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
}
.filter-pills { display: flex; flex-wrap: wrap; gap: 0.35rem; }
.filter-pill {
    padding: 0.35rem 0.85rem;
    font-size: 0.8rem;
    font-weight: 500;
    border: 1px solid var(--neutral-300);
    border-radius: 9999px;
    background: #fff;
    color: var(--neutral-600);
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}
.filter-pill:hover { border-color: var(--primary); color: var(--primary); }
.filter-pill.active { background: var(--primary); border-color: var(--primary); color: #fff; }
.filter-custom { display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap; margin-left: auto; }
.filter-custom input[type="date"] {
    padding: 0.3rem 0.5rem;
    font-size: 0.8rem;
    border: 1px solid var(--neutral-300);
    border-radius: 6px;
    font-family: inherit;
}
.filter-custom .btn { padding: 0.3rem 0.75rem; font-size: 0.8rem; }
.filter-label { font-size: 0.8rem; color: var(--neutral-500); margin-left: 0.5rem; }

/* ── KPI Cards ── */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.kpi-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--neutral-200);
    padding: 1.25rem;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    transition: box-shadow 0.2s;
}
.kpi-card:hover { box-shadow: var(--shadow-md); }
.kpi-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}
.kpi-body { flex: 1; min-width: 0; }
.kpi-value {
    font-size: 1.6rem;
    font-weight: 700;
    line-height: 1.2;
    color: var(--neutral-900);
}
.kpi-label {
    font-size: 0.8rem;
    color: var(--neutral-500);
    margin-top: 0.15rem;
}
.kpi-sub {
    font-size: 0.75rem;
    color: var(--neutral-400);
    margin-top: 0.1rem;
}
.kpi-trend {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.75rem;
    font-weight: 600;
    margin-top: 0.3rem;
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
}
.kpi-trend.up { background: #dcfce7; color: #166534; }
.kpi-trend.down { background: #fee2e2; color: #991b1b; }

/* ── Chart Cards ── */
.chart-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--neutral-200);
    padding: 1.25rem;
}
.chart-card h3 {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.15rem;
}
.chart-card .chart-sub {
    font-size: 0.8rem;
    color: var(--neutral-500);
    margin-bottom: 0.75rem;
}
.chart-container { position: relative; width: 100%; }
.chart-container.chart-lg { height: 320px; }
.chart-container.chart-md { height: 260px; }
.chart-container.chart-sm { height: 200px; }

/* ── Bottom Row ── */
.bottom-grid {
    display: grid;
    grid-template-columns: 3fr 2fr;
    gap: 1rem;
    margin-top: 1.5rem;
}
.recent-orders-table { width: 100%; font-size: 0.85rem; }
.recent-orders-table th { padding: 0.6rem 0.75rem; }
.recent-orders-table td { padding: 0.6rem 0.75rem; }
.recent-orders-table tbody tr { cursor: pointer; }
.recent-orders-table tbody tr:hover { background: var(--primary-bg); }
.summary-stats { display: flex; flex-direction: column; gap: 0.6rem; }
.summary-stat {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.4rem 0;
    border-bottom: 1px solid var(--neutral-100);
    font-size: 0.85rem;
}
.summary-stat:last-child { border-bottom: none; }
.summary-stat .stat-label { color: var(--neutral-600); display: flex; align-items: center; gap: 0.4rem; }
.summary-stat .stat-value { font-weight: 600; color: var(--neutral-900); }

/* ── Skeleton Loading ── */
.skeleton {
    background: linear-gradient(90deg, var(--neutral-100) 25%, var(--neutral-200) 50%, var(--neutral-100) 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
    border-radius: 6px;
}
@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
.skeleton-h2 { height: 1.5rem; width: 40%; margin-bottom: 0.5rem; }
.skeleton-p { height: 0.85rem; width: 60%; margin-bottom: 1rem; }
.skeleton-card { height: 100px; border-radius: 12px; }
.skeleton-chart { height: 320px; border-radius: 12px; }

/* ── Empty State ── */
.empty-chart {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--neutral-400);
    font-size: 0.9rem;
}
.empty-chart .empty-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }

/* ── Responsive ── */
@media (max-width: 1024px) {
    .kpi-grid { grid-template-columns: repeat(2, 1fr); }
    .bottom-grid { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
    .kpi-grid { grid-template-columns: 1fr; }
    .filter-custom { margin-left: 0; width: 100%; }
    .filter-custom input[type="date"] { flex: 1; }
}

/* ── Print ── */
@media print {
    .navbar, .dashboard-filter, .btn, .filter-pills, .filter-custom { display: none !important; }
    .kpi-card, .chart-card { break-inside: avoid; box-shadow: none; border: 1px solid #ddd; }
    .chart-container canvas { max-height: 200px; }
}
</style>

<!-- ────────────────────────────────────────── -->
<!-- SECTION 1: DATE FILTER BAR -->
<!-- ────────────────────────────────────────── -->
<div class="dashboard-filter">
    <div class="container filter-inner">
        <div class="filter-pills">
            <a href="?from=<?= $today ?>&to=<?= $today ?>" class="filter-pill <?= ($from === $today && $to === $today) ? 'active' : '' ?>">Today</a>
            <a href="?from=<?= date('Y-m-d', strtotime('-1 day')) ?>&to=<?= date('Y-m-d', strtotime('-1 day')) ?>" class="filter-pill <?= ($from === date('Y-m-d', strtotime('-1 day')) && $to === date('Y-m-d', strtotime('-1 day'))) ? 'active' : '' ?>">Yesterday</a>
            <a href="?from=<?= date('Y-m-d', strtotime('-6 days')) ?>&to=<?= $today ?>" class="filter-pill <?= ($from === date('Y-m-d', strtotime('-6 days')) && $to === $today) ? 'active' : '' ?>">Last 7 Days</a>
            <a href="?from=<?= $firstOfMonth ?>&to=<?= $today ?>" class="filter-pill <?= ($from === $firstOfMonth && $to === $today) ? 'active' : '' ?>">This Month</a>
            <a href="?from=<?= date('Y-m-01', strtotime('-1 month')) ?>&to=<?= date('Y-m-t', strtotime('-1 month')) ?>" class="filter-pill <?= ($from === date('Y-m-01', strtotime('-1 month'))) ? 'active' : '' ?>">Last Month</a>
            <a href="?from=<?= date('Y-01-01') ?>&to=<?= $today ?>" class="filter-pill <?= ($from === date('Y-01-01')) ? 'active' : '' ?>">This Year</a>
        </div>
        <form method="GET" class="filter-custom">
            <input type="date" name="from" value="<?= $from ?>" required>
            <span style="color:var(--neutral-400);font-size:0.8rem;">→</span>
            <input type="date" name="to" value="<?= $to ?>" required>
            <button type="submit" class="btn btn-sm btn-primary">Apply</button>
        </form>
        <span class="filter-label"><?= $dateLabel ?></span>
    </div>
</div>

<div class="container">
    <div class="page-header">
        <h1>Dashboard</h1>
        <p>Welcome back, <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>!</p>
    </div>

    <!-- ────────────────────────────────────── -->
    <!-- SECTION 2: KPI CARDS -->
    <!-- ────────────────────────────────────── -->
    <div class="kpi-grid" id="kpiGrid">
        <!-- Card 1: Revenue -->
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#e6f4f5;color:#01696f;">💰</div>
            <div class="kpi-body">
                <div class="kpi-value" id="kpiRevenue" data-target="<?= $kpi_revenue ?>" data-prefix="RM " data-suffix="" data-decimals="2">RM 0.00</div>
                <div class="kpi-label">Total Revenue</div>
                <div class="kpi-trend <?= $revenue_trend >= 0 ? 'up' : 'down' ?>">
                    <?= $revenue_trend >= 0 ? '▲' : '▼' ?> <?= abs($revenue_trend) ?>% vs last period
                </div>
            </div>
        </div>
        <!-- Card 2: Total Orders -->
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#dbeafe;color:#006494;">🧾</div>
            <div class="kpi-body">
                <div class="kpi-value" id="kpiOrders" data-target="<?= $kpi_total_orders ?>" data-prefix="" data-suffix="" data-decimals="0">0</div>
                <div class="kpi-label">Total Orders</div>
                <div class="kpi-sub"><?= $kpi_completed ?> completed · <?= $kpi_pending ?> pending · <?= $kpi_cancelled ?> cancelled</div>
                <div class="kpi-trend <?= $orders_trend >= 0 ? 'up' : 'down' ?>">
                    <?= $orders_trend >= 0 ? '▲' : '▼' ?> <?= abs($orders_trend) ?>% vs last period
                </div>
            </div>
        </div>
        <!-- Card 3: Completed Orders -->
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#dcfce7;color:#437a22;">✅</div>
            <div class="kpi-body">
                <div class="kpi-value" id="kpiCompleted" data-target="<?= $kpi_completed ?>" data-prefix="" data-suffix="" data-decimals="0">0</div>
                <div class="kpi-label">Completed Orders</div>
                <div class="kpi-sub"><?= $kpi_total_orders > 0 ? round($kpi_completed / $kpi_total_orders * 100, 1) : 0 ?>% of total orders</div>
            </div>
        </div>
        <!-- Card 4: Average Order Value -->
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#f3e8ff;color:#7a39bb;">📊</div>
            <div class="kpi-body">
                <div class="kpi-value" id="kpiAvg" data-target="<?= $kpi_avg_order ?>" data-prefix="RM " data-suffix="" data-decimals="2">RM 0.00</div>
                <div class="kpi-label">Average Order Value</div>
            </div>
        </div>
        <!-- Card 5: Active Promotions -->
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#fff3e0;color:#da7101;">🔥</div>
            <div class="kpi-body">
                <div class="kpi-value" id="kpiPromos" data-target="<?= $kpi_active_promos ?>" data-prefix="" data-suffix="" data-decimals="0">0</div>
                <div class="kpi-label">Active Promotions</div>
                <div class="kpi-sub"><?= $promo_orders ?> promo orders in period</div>
            </div>
        </div>
        <!-- Card 6: Coupons Redeemed -->
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#fef9e7;color:#d19900;">🎟️</div>
            <div class="kpi-body">
                <div class="kpi-value" id="kpiCoupons" data-target="<?= $kpi_coupons_redeemed ?>" data-prefix="" data-suffix="" data-decimals="0">0</div>
                <div class="kpi-label">Coupons Redeemed</div>
                <div class="kpi-sub">RM <?= number_format($kpi_discount_given, 2) ?> total savings</div>
            </div>
        </div>
    </div>

    <!-- ────────────────────────────────────── -->
    <!-- SECTION 3: MAIN CHARTS ROW -->
    <!-- ────────────────────────────────────── -->
    <div class="grid grid-2" style="margin-bottom:1.5rem;">
        <!-- Chart A: Revenue & Orders Over Time -->
        <div class="chart-card">
            <h3>Revenue & Orders Over Time</h3>
            <div class="chart-sub">Completed orders only</div>
            <div class="chart-container chart-lg">
                <canvas id="chartA"></canvas>
            </div>
        </div>
        <!-- Chart B: Order Status Breakdown -->
        <div class="chart-card">
            <h3>Order Status Breakdown</h3>
            <div class="chart-sub">All orders in period</div>
            <div class="chart-container chart-lg">
                <canvas id="chartB"></canvas>
            </div>
        </div>
    </div>

    <!-- ────────────────────────────────────── -->
    <!-- SECTION 4: SECONDARY CHARTS ROW -->
    <!-- ────────────────────────────────────── -->
    <div class="grid grid-3" style="margin-bottom:1.5rem;">
        <!-- Chart C: Top 5 Best-Selling Items -->
        <div class="chart-card">
            <h3>🏆 Top 5 Best-Selling Items</h3>
            <div class="chart-sub">By quantity sold</div>
            <div class="chart-container chart-md">
                <canvas id="chartC"></canvas>
            </div>
        </div>
        <!-- Chart D: Revenue by Payment Method -->
        <div class="chart-card">
            <h3>Revenue by Payment Method</h3>
            <div class="chart-sub">Completed orders</div>
            <div class="chart-container chart-md">
                <canvas id="chartD"></canvas>
            </div>
        </div>
        <!-- Chart E: Hourly Order Distribution -->
        <div class="chart-card">
            <h3>Peak Hours</h3>
            <div class="chart-sub">Orders by hour of day</div>
            <div class="chart-container chart-md">
                <canvas id="chartE"></canvas>
            </div>
        </div>
    </div>

    <!-- ────────────────────────────────────── -->
    <!-- SECTION 5: BOTTOM ROW -->
    <!-- ────────────────────────────────────── -->
    <div class="bottom-grid">
        <!-- Recent Orders Table -->
        <div class="chart-card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;">
                <h3 style="margin:0;">Recent Orders</h3>
                <a href="/smart-transaction/admin/orders.php" class="btn btn-sm btn-outline">View All →</a>
            </div>
            <?php if (empty($recentOrders)): ?>
                <div class="empty-chart" style="height:200px;">
                    <div class="empty-icon">📋</div>
                    <span>No orders yet</span>
                </div>
            <?php else: ?>
            <div style="max-height:400px;overflow-y:auto;">
                <table class="recent-orders-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                        <tr onclick="window.location='/smart-transaction/admin/orders.php?id=<?= (int)$order['id'] ?>'">
                            <td><strong>#<?= (int)$order['id'] ?></strong></td>
                            <td><?= htmlspecialchars($order['customer_name']) ?></td>
                            <td><?= (int)$order['item_count'] ?></td>
                            <td>RM <?= number_format((float)$order['total_amount'], 2) ?></td>
                            <td><span class="badge badge-<?= $order['payment_status'] === 'paid' ? 'paid' : 'unpaid' ?>"><?= htmlspecialchars(ucfirst($order['payment_status'])) ?></span></td>
                            <td><span class="badge badge-<?= htmlspecialchars($order['status']) ?>"><?= htmlspecialchars(ucfirst($order['status'])) ?></span></td>
                            <td style="font-size:0.8rem;color:var(--neutral-500);" class="relative-time" data-time="<?= $order['created_at'] ?>"><?= date('d M H:i', strtotime($order['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Summary Stats Panel -->
        <div class="chart-card">
            <h3 style="margin-bottom:0.75rem;">Period Summary</h3>
            <div class="summary-stats">
                <div class="summary-stat">
                    <span class="stat-label">📦 Total Items Sold</span>
                    <span class="stat-value"><?= number_format($total_items_sold) ?> items</span>
                </div>
                <div class="summary-stat">
                    <span class="stat-label">🎟️ Coupons Used</span>
                    <span class="stat-value"><?= $coupons_used ?> times</span>
                </div>
                <div class="summary-stat">
                    <span class="stat-label">💸 Total Discount Given</span>
                    <span class="stat-value">RM <?= number_format($kpi_discount_given, 2) ?></span>
                </div>
                <div class="summary-stat">
                    <span class="stat-label">🔥 Promo Orders</span>
                    <span class="stat-value"><?= $promo_orders ?></span>
                </div>
                <div class="summary-stat">
                    <span class="stat-label">❌ Cancelled Orders</span>
                    <span class="stat-value"><?= $cancelled_count ?></span>
                </div>
                <div class="summary-stat">
                    <span class="stat-label">⭐ Top Customer</span>
                    <span class="stat-value"><?= htmlspecialchars($top_customer) ?></span>
                </div>
                <div class="summary-stat">
                    <span class="stat-label">📅 Busiest Day</span>
                    <span class="stat-value"><?= htmlspecialchars($busiest_day) ?></span>
                </div>
            </div>
        </div>
    </div>

</div><!-- /.container -->

<script>
// ── Chart.js Global Defaults ──
Chart.defaults.font.family = "'Inter', 'Segoe UI', sans-serif";
Chart.defaults.font.size   = 13;
Chart.defaults.color       = '#7a7974';
Chart.defaults.plugins.legend.position = 'bottom';
Chart.defaults.plugins.tooltip.backgroundColor = '#28251d';
Chart.defaults.plugins.tooltip.titleColor = '#f9f8f5';
Chart.defaults.plugins.tooltip.bodyColor  = '#cdccca';
Chart.defaults.plugins.tooltip.padding    = 12;
Chart.defaults.plugins.tooltip.cornerRadius = 8;
Chart.defaults.animation.duration = 800;
Chart.defaults.animation.easing   = 'easeInOutQuart';
Chart.defaults.scale.grid.color = 'rgba(0,0,0,0.05)';
Chart.defaults.scale.grid.drawBorder = false;

// Register datalabels plugin
Chart.register(ChartDataLabels);

// ── Count-Up Animation ──
function animateCountUp(element, target, prefix, suffix, decimals, duration) {
    if (!element) return;
    duration = duration || 1000;
    let start = null;
    const step = (timestamp) => {
        if (!start) start = timestamp;
        const progress = Math.min((timestamp - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        const current = eased * target;
        element.textContent = prefix + 
            current.toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + 
            suffix;
        if (progress < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
}

document.addEventListener('DOMContentLoaded', function() {
    // Animate KPI cards
    document.querySelectorAll('.kpi-value').forEach(function(el) {
        var target = parseFloat(el.dataset.target) || 0;
        var prefix = el.dataset.prefix || '';
        var suffix = el.dataset.suffix || '';
        var decimals = parseInt(el.dataset.decimals) || 0;
        animateCountUp(el, target, prefix, suffix, decimals, 1000);
    });

    // ── Relative Time ──
    document.querySelectorAll('.relative-time').forEach(function(el) {
        var timeStr = el.dataset.time;
        if (!timeStr) return;
        var date = new Date(timeStr.replace(' ', 'T') + '+08:00');
        var now = new Date();
        var diffMs = now - date;
        var diffSec = Math.floor(diffMs / 1000);
        var diffMin = Math.floor(diffSec / 60);
        var diffHour = Math.floor(diffMin / 60);
        var diffDay = Math.floor(diffHour / 24);
        var label = '';
        if (diffSec < 60) label = 'Just now';
        else if (diffMin < 60) label = diffMin + ' min ago';
        else if (diffHour < 24) label = diffHour + ' hour' + (diffHour > 1 ? 's' : '') + ' ago';
        else if (diffDay < 7) label = diffDay + ' day' + (diffDay > 1 ? 's' : '') + ' ago';
        else label = date.toLocaleDateString('en-MY', { day: 'numeric', month: 'short' });
        el.textContent = label;
    });

    // ── Chart A: Revenue & Orders Over Time ──
    var ctxA = document.getElementById('chartA');
    if (ctxA) {
        new Chart(ctxA, {
            type: 'bar',
            data: {
                labels: <?= $json_chartA_labels ?>,
                datasets: [
                    {
                        label: 'Revenue (RM)',
                        data: <?= $json_chartA_revenue ?>,
                        type: 'line',
                        borderColor: '#01696f',
                        backgroundColor: 'rgba(1,105,111,0.12)',
                        borderWidth: 2.5,
                        pointBackgroundColor: '#01696f',
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'y',
                        order: 0,
                    },
                    {
                        label: 'Orders',
                        data: <?= $json_chartA_orders ?>,
                        backgroundColor: 'rgba(0,100,148,0.35)',
                        borderColor: 'rgba(0,100,148,0.6)',
                        borderWidth: 1,
                        borderRadius: 4,
                        yAxisID: 'y1',
                        order: 1,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top', labels: { usePointStyle: true, padding: 16 } },
                    datalabels: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                if (ctx.dataset.label === 'Revenue (RM)') return 'Revenue: RM ' + ctx.parsed.y.toFixed(2);
                                return 'Orders: ' + ctx.parsed.y;
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        position: 'left',
                        title: { display: true, text: 'Revenue (RM)', color: '#01696f' },
                        ticks: { callback: function(v) { return 'RM' + v; } }
                    },
                    y1: {
                        position: 'right',
                        title: { display: true, text: 'Orders', color: '#006494' },
                        grid: { drawOnChartArea: false },
                        ticks: { precision: 0 }
                    }
                }
            }
        });
    }

    // ── Chart B: Order Status Doughnut ──
    var ctxB = document.getElementById('chartB');
    if (ctxB) {
        var totalB = <?= $json_chartB_values ?>.reduce(function(a,b){return a+b;}, 0);
        new Chart(ctxB, {
            type: 'doughnut',
            data: {
                labels: <?= $json_chartB_labels ?>,
                datasets: [{
                    data: <?= $json_chartB_values ?>,
                    backgroundColor: <?= $json_chartB_colors ?>,
                    borderWidth: 2,
                    borderColor: '#fff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true } },
                    datalabels: {
                        color: '#fff',
                        font: { weight: 'bold', size: 11 },
                        formatter: function(v) {
                            return totalB > 0 ? Math.round(v / totalB * 100) + '%' : '';
                        },
                        display: function(ctx) {
                            return ctx.dataset.data[ctx.dataIndex] / totalB > 0.05;
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                var pct = totalB > 0 ? (ctx.parsed / totalB * 100).toFixed(1) : 0;
                                return ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            },
            plugins: [{
                id: 'centerText',
                beforeDraw: function(chart) {
                    var w = chart.width, h = chart.height, ctx = chart.ctx;
                    ctx.save();
                    ctx.font = '700 28px Inter, Segoe UI, sans-serif';
                    ctx.fillStyle = '#292524';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillText(totalB, w/2, h/2 - 8);
                    ctx.font = '13px Inter, Segoe UI, sans-serif';
                    ctx.fillStyle = '#78716c';
                    ctx.fillText('Total', w/2, h/2 + 18);
                    ctx.restore();
                }
            }]
        });
    }

    // ── Chart C: Top 5 Best-Selling Items ──
    var ctxC = document.getElementById('chartC');
    if (ctxC) {
        var cLabels = <?= $json_chartC_labels ?>;
        var cValues = <?= $json_chartC_values ?>;
        var cRevs = <?= $json_chartC_revs ?>;
        var cColors = ['#01696f','#028a92','#35a7a8','#6cc5c6','#a8e0e0'].slice(0, cLabels.length).reverse();
        new Chart(ctxC, {
            type: 'bar',
            data: {
                labels: cLabels,
                datasets: [{
                    data: cValues,
                    backgroundColor: cColors,
                    borderRadius: 4,
                    borderSkipped: false,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    datalabels: {
                        anchor: 'end',
                        align: 'end',
                        color: '#292524',
                        font: { weight: 'bold', size: 11 },
                        formatter: function(v) { return v + ' sold'; }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                var idx = ctx.dataIndex;
                                return 'Sold: ' + ctx.parsed.x + ' | Revenue: RM ' + (cRevs[idx] || 0).toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    x: { title: { display: true, text: 'Quantity Sold' }, ticks: { precision: 0 } },
                    y: { grid: { display: false } }
                }
            }
        });
    }

    // ── Chart D: Revenue by Payment Method ──
    var ctxD = document.getElementById('chartD');
    if (ctxD) {
        var dValues = <?= $json_chartD_values ?>;
        var dTotal = dValues.reduce(function(a,b){return a+b;}, 0);
        new Chart(ctxD, {
            type: 'pie',
            data: {
                labels: <?= $json_chartD_labels ?>,
                datasets: [{
                    data: dValues,
                    backgroundColor: <?= $json_chartD_colors ?>,
                    borderWidth: 2,
                    borderColor: '#fff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 10, usePointStyle: true } },
                    datalabels: {
                        color: '#fff',
                        font: { weight: 'bold', size: 11 },
                        formatter: function(v) {
                            return dTotal > 0 ? 'RM' + v.toFixed(0) : '';
                        },
                        display: function(ctx) {
                            return ctx.dataset.data[ctx.dataIndex] / dTotal > 0.08;
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                var pct = dTotal > 0 ? (ctx.parsed / dTotal * 100).toFixed(1) : 0;
                                return ctx.label + ': RM ' + ctx.parsed.toFixed(2) + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    // ── Chart E: Hourly Order Distribution ──
    var ctxE = document.getElementById('chartE');
    if (ctxE) {
        var eValues = <?= $json_chartE_values ?>;
        var eMax = Math.max.apply(null, eValues);
        new Chart(ctxE, {
            type: 'bar',
            data: {
                labels: <?= $json_chartE_labels ?>,
                datasets: [{
                    data: eValues,
                    backgroundColor: <?= $json_chartE_colors ?>,
                    borderRadius: 3,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    datalabels: {
                        anchor: 'end',
                        align: 'end',
                        color: '#292524',
                        font: { weight: 'bold', size: 10 },
                        formatter: function(v, ctx) {
                            return v === eMax && eMax > 0 ? '🔥 Peak' : '';
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ctx.label + ': ' + ctx.parsed.y + ' orders';
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 10 } } },
                    y: { title: { display: true, text: 'Orders' }, ticks: { precision: 0 } }
                }
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
