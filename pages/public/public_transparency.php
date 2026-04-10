<?php
require_once __DIR__ . '/../../includes/init.php';
session_start();
require_once __DIR__ . '/../../includes/db_config.php';

$conn = getDBConnection();

function moneyFmt($value) {
    return 'Rs. ' . number_format((float)$value);
}

function donorInitials($name) {
    $name = trim((string)$name);
    if ($name === '' || strtolower($name) === 'anonymous') {
        return 'AN';
    }
    $parts = preg_split('/\s+/', $name);
    if (!$parts) {
        return strtoupper(substr($name, 0, 2));
    }
    $first = strtoupper(substr($parts[0], 0, 1));
    $second = '';
    if (count($parts) > 1) {
        $second = strtoupper(substr($parts[count($parts) - 1], 0, 1));
    } elseif (strlen($parts[0]) > 1) {
        $second = strtoupper(substr($parts[0], 1, 1));
    }
    return $first . $second;
}

function relativeTime($dateTime) {
    if (empty($dateTime)) {
        return 'Unknown';
    }
    $now = new DateTime();
    $then = new DateTime($dateTime);
    $seconds = max(0, $now->getTimestamp() - $then->getTimestamp());
    if ($seconds < 60) {
        return 'Just now';
    }
    if ($seconds < 3600) {
        $m = (int) floor($seconds / 60);
        return $m . ' minute' . ($m === 1 ? '' : 's') . ' ago';
    }
    if ($seconds < 86400) {
        $h = (int) floor($seconds / 3600);
        return $h . ' hour' . ($h === 1 ? '' : 's') . ' ago';
    }
    $d = (int) floor($seconds / 86400);
    return $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
}

$totalDonations = 0.0;
$fundsUtilised = 0.0;
$totalDonors = 0;
$livesImpacted = 0;

$statsRow = $conn->query("SELECT COALESCE(SUM(amount),0) AS total FROM donations WHERE status IN ('paid','verified')");
if ($statsRow) {
    $totalDonations = (float)($statsRow->fetch_assoc()['total'] ?? 0);
}

$utilisedRow = $conn->query("SELECT COALESCE(SUM(amount),0) AS total FROM bills WHERE status='paid'");
if ($utilisedRow) {
    $fundsUtilised = (float)($utilisedRow->fetch_assoc()['total'] ?? 0);
}

$donorRow = $conn->query("SELECT COUNT(DISTINCT CASE WHEN donor_email IS NOT NULL AND donor_email <> '' THEN LOWER(donor_email) ELSE CONCAT('name:', LOWER(TRIM(COALESCE(donor_name,'')))) END) AS cnt FROM donations WHERE status IN ('paid','verified')");
if ($donorRow) {
    $totalDonors = (int)($donorRow->fetch_assoc()['cnt'] ?? 0);
}

$impactedRow = $conn->query("SELECT COUNT(*) AS cnt FROM monks WHERE status='active'");
if ($impactedRow) {
    $livesImpacted = (int)($impactedRow->fetch_assoc()['cnt'] ?? 0);
}

$utilPercent = ($totalDonations > 0) ? min(100, round(($fundsUtilised / $totalDonations) * 100)) : 0;
$utilOffset = 314 - (314 * ($utilPercent / 100));

$breakdownRows = [];
$qBreakdown = $conn->query("SELECT c.name AS category, COALESCE(SUM(b.amount),0) AS total FROM bills b LEFT JOIN categories c ON b.category_id = c.category_id WHERE b.status='paid' GROUP BY c.category_id, c.name HAVING total > 0 ORDER BY total DESC LIMIT 5");
if ($qBreakdown && $qBreakdown->num_rows > 0) {
    while ($row = $qBreakdown->fetch_assoc()) {
        $breakdownRows[] = $row;
    }
} else {
    $qBreakdownFallback = $conn->query("SELECT c.name AS category, COALESCE(SUM(d.amount),0) AS total FROM donations d LEFT JOIN categories c ON d.category_id = c.category_id WHERE d.status IN ('paid','verified') GROUP BY c.category_id, c.name HAVING total > 0 ORDER BY total DESC LIMIT 5");
    if ($qBreakdownFallback) {
        while ($row = $qBreakdownFallback->fetch_assoc()) {
            $breakdownRows[] = $row;
        }
    }
}

$breakdownTotal = 0.0;
foreach ($breakdownRows as $r) {
    $breakdownTotal += (float)$r['total'];
}

$breakdownColors = ['#D4622A', '#F0864A', '#F0A050', '#C9A070', '#D4B896'];

$donationRows = [];
$donationsPerPage = 5;
$donationPage = max(1, (int)($_GET['donation_page'] ?? 1));
$donationTotalRows = 0;
$qDonationCount = $conn->query("SELECT COUNT(*) AS cnt FROM donations");
if ($qDonationCount) {
    $donationTotalRows = (int)($qDonationCount->fetch_assoc()['cnt'] ?? 0);
}
$donationTotalPages = max(1, (int)ceil($donationTotalRows / $donationsPerPage));
$donationPage = min($donationPage, $donationTotalPages);
$donationOffset = ($donationPage - 1) * $donationsPerPage;

$qDonations = $conn->query("SELECT d.donation_id, COALESCE(NULLIF(d.donor_name,''), 'Anonymous') AS donor_name, COALESCE(c.name, 'General') AS category, d.amount, d.created_at, d.method, d.status FROM donations d LEFT JOIN categories c ON d.category_id = c.category_id ORDER BY d.created_at DESC LIMIT $donationsPerPage OFFSET $donationOffset");
if ($qDonations) {
    while ($row = $qDonations->fetch_assoc()) {
        $donationRows[] = $row;
    }
}

$expenseRows = [];
$qExpenses = $conn->query("SELECT b.bill_id, COALESCE(NULLIF(b.description,''), COALESCE(NULLIF(b.vendor_name,''), 'Operational Expense')) AS item_name, COALESCE(c.name,'Uncategorized') AS category, b.bill_date, b.amount FROM bills b LEFT JOIN categories c ON b.category_id = c.category_id WHERE b.status='paid' ORDER BY COALESCE(b.paid_date, b.bill_date, DATE(b.created_at)) DESC LIMIT 6");
if ($qExpenses && $qExpenses->num_rows > 0) {
    while ($row = $qExpenses->fetch_assoc()) {
        $expenseRows[] = $row;
    }
} else {
    $qExpensesFallback = $conn->query("SELECT b.bill_id, COALESCE(NULLIF(b.description,''), COALESCE(NULLIF(b.vendor_name,''), 'Operational Expense')) AS item_name, COALESCE(c.name,'Uncategorized') AS category, b.bill_date, b.amount FROM bills b LEFT JOIN categories c ON b.category_id = c.category_id ORDER BY COALESCE(b.bill_date, DATE(b.created_at)) DESC LIMIT 6");
    if ($qExpensesFallback) {
        while ($row = $qExpensesFallback->fetch_assoc()) {
            $expenseRows[] = $row;
        }
    }
}

$monthlyData = [];
$monthlyKeys = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime(date('Y-m-01') . " -$i months");
    $key = date('Y-m', $ts);
    $monthlyKeys[] = $key;
    $monthlyData[$key] = [
        'label' => date('M', $ts),
        'total' => 0.0
    ];
}

$startMonth = $monthlyKeys[0] . '-01';
$qMonthly = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COALESCE(SUM(amount),0) AS total FROM donations WHERE status IN ('paid','verified') AND created_at >= '$startMonth' GROUP BY ym");
if ($qMonthly) {
    while ($row = $qMonthly->fetch_assoc()) {
        if (isset($monthlyData[$row['ym']])) {
            $monthlyData[$row['ym']]['total'] = (float)$row['total'];
        }
    }
}

$monthlyMax = 1.0;
$last6MonthsTotal = 0.0;
foreach ($monthlyData as $md) {
    $monthlyMax = max($monthlyMax, (float)$md['total']);
    $last6MonthsTotal += (float)$md['total'];
}

$recentDonorRows = [];
$qRecent = $conn->query("SELECT COALESCE(NULLIF(donor_name,''), 'Anonymous') AS donor_name, amount, created_at FROM donations WHERE status IN ('paid','verified') ORDER BY created_at DESC LIMIT 5");
if ($qRecent) {
    while ($row = $qRecent->fetch_assoc()) {
        $recentDonorRows[] = $row;
    }
}

$acceptedDatesRows = [];
$acceptedDatesPerPage = 5;
$acceptedDatesPage = max(1, (int)($_GET['alms_page'] ?? 1));
$acceptedDatesTotalRows = 0;
$qAcceptedDatesCount = $conn->query("SELECT COUNT(*) AS cnt FROM donation_date_requests WHERE status='approved'");
if ($qAcceptedDatesCount) {
    $acceptedDatesTotalRows = (int)($qAcceptedDatesCount->fetch_assoc()['cnt'] ?? 0);
}
$acceptedDatesTotalPages = max(1, (int)ceil($acceptedDatesTotalRows / $acceptedDatesPerPage));
$acceptedDatesPage = min($acceptedDatesPage, $acceptedDatesTotalPages);
$acceptedDatesOffset = ($acceptedDatesPage - 1) * $acceptedDatesPerPage;

$qAcceptedDates = $conn->query("SELECT request_id, donor_name, donor_email, requested_date, meal_type, reviewed_at FROM donation_date_requests WHERE status='approved' ORDER BY requested_date DESC LIMIT $acceptedDatesPerPage OFFSET $acceptedDatesOffset");
if ($qAcceptedDates) {
    while ($row = $qAcceptedDates->fetch_assoc()) {
        $acceptedDatesRows[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transparency &amp; Reports &mdash; Seela suwa herath</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=Raleway:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
            --white:#FFFFFF;--ivory:#FFFBF7;--cream:#FEF3E8;--sand:#F5E0C8;
            --orange:#D4622A;--orange-mid:#F0864A;--orange-light:#F0A050;--orange-pale:#FDEBD8;
            --text-dark:#1E1610;--text-mid:#5A4A3A;--text-light:#9A8070;
            --border:rgba(210,170,130,0.28);
            --success:#2E7D52;--success-bg:#EAF5EE;
            --pending:#B8780A;--pending-bg:#FEF6E4;
            --rejected:#B94040;--rejected-bg:#FDEAEA;
        }
        html{scroll-behavior:smooth}
        body{font-family:'Raleway',sans-serif;font-weight:300;background:var(--white);color:var(--text-dark);overflow-x:hidden}

        /* â”€â”€ NAV (solid â€” no hero image on this page) â”€â”€ */
        nav{position:sticky;top:0;left:0;right:0;z-index:200;padding:0 6%;height:72px;display:flex;align-items:center;justify-content:space-between;background:rgba(255,255,255,.97);backdrop-filter:blur(16px);border-bottom:1px solid var(--border)}
        .nav-logo{display:flex;align-items:center;gap:12px;text-decoration:none}
        .nav-logo-mark{width:36px;height:36px;background:linear-gradient(135deg,var(--orange),var(--orange-light));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;color:#fff}
        .nav-logo-name{font-family:'EB Garamond',serif;font-size:1.25rem;font-weight:600;color:var(--text-dark)}
        .nav-logo-sub{font-size:.6rem;color:var(--text-light);letter-spacing:.13em;text-transform:uppercase;display:block;margin-top:-4px}
        .nav-links{display:flex;align-items:center;gap:28px;list-style:none}
        .nav-links a{text-decoration:none;color:var(--text-mid);font-size:.83rem;font-weight:400;letter-spacing:.06em;text-transform:uppercase;transition:color .2s}
        .nav-links a:hover,.nav-links a.active{color:var(--orange)}
        .nav-donate{background:var(--orange)!important;color:#fff!important;padding:9px 24px!important;border-radius:40px!important;font-weight:500!important}
        .nav-donate:hover{background:var(--text-dark)!important}

        /* â”€â”€ PAGE HEADER â”€â”€ */
        .page-header{background:#EDE6DC;padding:24px 6% 28px;position:relative;overflow:visible;text-align:center}
        .page-header::before{display:none}
        .page-header-inner{position:relative;z-index:1;max-width:1160px;margin:0 auto;background:transparent;border-radius:0;padding:28px 52px;box-shadow:none}
        .page-header-eyebrow{font-size:.74rem;font-weight:500;letter-spacing:.2em;text-transform:uppercase;color:var(--text-light);margin-bottom:16px}
        .page-header h1{font-family:'EB Garamond',serif;font-size:clamp(2.3rem,4.5vw,3.3rem);font-weight:300;color:var(--text-dark);line-height:1.15;margin-bottom:18px}
        .page-header h1 em{font-style:italic;color:var(--orange-light);font-weight:400}
        .page-header p{font-size:.97rem;color:var(--text-mid);line-height:1.85;max-width:540px;margin:0 auto}

        /* â”€â”€ SUMMARY CARDS (overlapping) â”€â”€ */
        .summary-wrap{padding:0 6%;margin-top:32px;position:relative;z-index:10;margin-bottom:72px}
        .summary-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:24px;max-width:1160px;margin:0 auto}
        .s-card{background:var(--cream);border-radius:18px;padding:40px 28px;border:none;box-shadow:0 4px 16px rgba(0,0,0,.04);text-align:center;position:relative;overflow:hidden;transition:transform .3s,box-shadow .3s}
        .s-card:hover{transform:translateY(-5px);box-shadow:0 14px 44px rgba(212,98,42,.16)}
        .s-card::after{display:none}
        .s-card-icon{font-size:1.9rem;margin-bottom:14px}
        .s-card-num{font-family:'EB Garamond',serif;font-size:2.1rem;font-weight:600;color:var(--orange);line-height:1;margin-bottom:10px}
        .s-card-label{font-size:.77rem;color:var(--text-light);letter-spacing:.09em;text-transform:uppercase;font-weight:500}
        .s-card-sub{font-size:.80rem;color:var(--text-mid);margin-top:8px;line-height:1.4}

        /* â”€â”€ MAIN LAYOUT â”€â”€ */
        .main{width:88%;max-width:1160px;margin:0 auto 100px;padding:0;display:grid;grid-template-columns:1fr 340px;gap:44px;align-items:start}

        /* â”€â”€ SECTION TITLES â”€â”€ */
        .sec-label{font-size:.72rem;font-weight:500;letter-spacing:.18em;text-transform:uppercase;color:var(--orange);display:block;margin-bottom:10px}
        .sec-title{font-family:'EB Garamond',serif;font-size:1.9rem;font-weight:300;color:var(--text-dark);margin-bottom:24px;line-height:1.2}

        /* â”€â”€ FUND BREAKDOWN â”€â”€ */
        .card{background:var(--cream);border:1.5px solid rgba(212,98,42,.12);border-radius:18px;padding:40px;margin-bottom:36px;box-shadow:0 4px 16px rgba(0,0,0,.04)}
        .card-title{font-family:'EB Garamond',serif;font-size:1.32rem;font-weight:600;color:var(--text-dark);margin-bottom:28px;padding-bottom:18px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px}
        .card-title span{font-size:1.25rem}

        /* Donut-style bars */
        .breakdown-item{margin-bottom:22px}
        .breakdown-item:last-child{margin-bottom:0}
        .bd-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
        .bd-label{font-size:.90rem;color:var(--text-mid);display:flex;align-items:center;gap:10px}
        .bd-dot{width:11px;height:11px;border-radius:50%;flex-shrink:0}
        .bd-vals{font-size:.88rem;color:var(--text-dark);font-weight:500}
        .bd-pct{font-size:.77rem;color:var(--text-light);margin-left:8px}
        .progress-track{height:9px;background:var(--sand);border-radius:40px;overflow:hidden}
        .progress-fill{height:100%;border-radius:40px;transition:width 1.2s ease}

        /* â”€â”€ DONATIONS TABLE â”€â”€ */
        .table-wrap{overflow-x:auto;margin-top:8px}
        .dtable{width:100%;border-collapse:collapse;font-size:.87rem}
        .dtable thead th{padding:12px 16px;text-align:left;font-size:.72rem;font-weight:500;letter-spacing:.11em;text-transform:uppercase;color:var(--text-mid);border-bottom:2px solid rgba(212,98,42,.15);background:var(--cream);white-space:nowrap}
        .dtable tbody td{padding:16px;border-bottom:1px solid rgba(212,98,42,.08);color:var(--text-mid);vertical-align:middle}
        .dtable tbody tr:last-child td{border-bottom:none}
        .dtable tbody tr:hover td{background:var(--cream)}
        .donor-name{font-weight:500;color:var(--text-dark)}
        .amount-cell{font-family:'EB Garamond',serif;font-size:1.05rem;font-weight:600;color:var(--orange);white-space:nowrap}
        .badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:.72rem;font-weight:500;letter-spacing:.04em;white-space:nowrap}
        .badge-verified{background:var(--success-bg);color:var(--success)}
        .badge-pending{background:var(--pending-bg);color:var(--pending)}
        .badge-rejected{background:var(--rejected-bg);color:var(--rejected)}
        .category-tag{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.72rem;background:var(--orange-pale);color:var(--orange);font-weight:400}

        /* â”€â”€ FILTER BAR â”€â”€ */
        .filter-bar{display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap}
        .filter-bar select,.filter-bar input{padding:9px 14px;border:1.5px solid rgba(212,98,42,.12);border-radius:8px;background:var(--cream);color:var(--text-mid);font-family:'Raleway',sans-serif;font-size:.83rem;outline:none;transition:border-color .2s}
        .filter-bar select:focus,.filter-bar input:focus{border-color:var(--orange)}
        .filter-bar input{flex:1;min-width:180px}
        .filter-tag{padding:7px 16px;border:1.5px solid rgba(212,98,42,.12);border-radius:20px;font-size:.78rem;color:var(--text-mid);cursor:pointer;transition:all .2s;background:var(--cream)}
        .filter-tag:hover,.filter-tag.on{background:var(--orange);border-color:var(--orange);color:#fff}

        /* â”€â”€ EXPENDITURE TABLE â”€â”€ */
        .exp-item{display:flex;align-items:center;gap:18px;padding:18px 0;border-bottom:1px solid var(--border)}
        .exp-item:last-child{border-bottom:none}
        .exp-icon{width:48px;height:48px;border-radius:14px;background:var(--orange-pale);display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0}
        .exp-info{flex:1}
        .exp-name{font-size:.93rem;font-weight:500;color:var(--text-dark);margin-bottom:4px}
        .exp-meta{font-size:.77rem;color:var(--text-light)}
        .exp-amount{font-family:'EB Garamond',serif;font-size:1.13rem;font-weight:600;color:var(--text-dark);white-space:nowrap}

        /* â”€â”€ SIDEBAR â”€â”€ */
        .sidebar>*{margin-bottom:32px}
        .mini-card{background:var(--cream);border:1.5px solid rgba(212,98,42,.12);border-radius:18px;padding:32px;box-shadow:0 4px 16px rgba(0,0,0,.04)}
        .mini-card-title{font-family:'EB Garamond',serif;font-size:1.16rem;font-weight:600;color:var(--text-dark);margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--border)}

        /* Monthly chart bars */
        .bar-chart{display:flex;align-items:flex-end;gap:7px;height:110px;margin-bottom:14px}
        .bar-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:5px}
        .bar{width:100%;border-radius:5px 5px 0 0;background:linear-gradient(to top,var(--orange),var(--orange-light));transition:background .2s;position:relative;min-height:4px}
        .bar:hover{background:var(--orange)}
        .bar-lbl{font-size:.68rem;color:var(--text-light);letter-spacing:.05em;font-weight:500}
        .bar-val{font-size:.68rem;color:var(--orange);font-weight:500}

        /* Recent donors list */
        .donor-row{display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1px solid var(--border)}
        .donor-row:last-child{border-bottom:none}
        .donor-avatar{width:40px;height:40px;border-radius:50%;background:var(--orange-pale);display:flex;align-items:center;justify-content:center;font-size:.88rem;font-weight:600;color:var(--orange);flex-shrink:0}
        .donor-info{flex:1}
        .donor-info .name{font-size:.91rem;font-weight:500;color:var(--text-dark)}
        .donor-info .time{font-size:.76rem;color:var(--text-light)}
        .donor-amt{font-family:'EB Garamond',serif;font-size:1.05rem;font-weight:600;color:var(--orange)}

        /* Utilisation ring */
        .util-ring-wrap{text-align:center;padding:12px 0 20px}
        .util-ring{position:relative;width:130px;height:130px;margin:0 auto 16px}
        .util-ring svg{transform:rotate(-90deg)}
        .util-ring-label{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center}
        .util-ring-pct{font-family:'EB Garamond',serif;font-size:1.75rem;font-weight:600;color:var(--orange);line-height:1}
        .util-ring-sub{font-size:.68rem;color:var(--text-light);letter-spacing:.06em}
        .util-legend{display:flex;justify-content:center;gap:18px;font-size:.78rem;color:var(--text-mid)}
        .util-dot{width:9px;height:9px;border-radius:50%;display:inline-block;margin-right:6px}

        /* Download CTA */
        .dl-btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:14px;border:1.5px solid rgba(212,98,42,.15);border-radius:12px;font-size:.88rem;color:var(--text-mid);text-decoration:none;transition:all .2s;margin-bottom:12px;background:var(--cream)}
        .dl-btn:hover{border-color:var(--orange);color:var(--orange);background:var(--orange-pale)}
        .dl-btn:last-child{margin-bottom:0}

        /* â”€â”€ FOOTER â”€â”€ */
        footer{background:#4A3B32;padding:60px 6% 28px}
        .foot-grid{max-width:1100px;margin:0 auto;display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:48px;padding-bottom:40px;border-bottom:1px solid rgba(255,255,255,.07);margin-bottom:24px}
        .foot-brand{font-family:'EB Garamond',serif;font-size:1.5rem;color:#fff;margin-bottom:12px;font-weight:600}
        .foot-tag{font-size:.84rem;color:rgba(255,255,255,.38);line-height:1.7;max-width:240px}
        .foot-col h4{font-size:.68rem;font-weight:500;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.28);margin-bottom:14px}
        .foot-col ul{list-style:none}
        .foot-col ul li{margin-bottom:9px}
        .foot-col ul a{color:rgba(255,255,255,.48);text-decoration:none;font-size:.86rem;transition:color .2s}
        .foot-col ul a:hover{color:var(--orange-light)}
        .foot-btm{max-width:1100px;margin:0 auto;display:flex;justify-content:space-between;font-size:.77rem;color:rgba(255,255,255,.22)}

        /* â”€â”€ PAGINATION â”€â”€ */
        .pagination{display:flex;justify-content:center;gap:10px;margin-top:24px}
        .page-btn{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;border:1.5px solid var(--border);background:var(--white);font-size:.85rem;color:var(--text-mid);cursor:pointer;text-decoration:none;transition:all .2s;font-weight:500}
        .page-btn:hover,.page-btn.active{background:var(--orange);border-color:var(--orange);color:#fff}

        /* â”€â”€ RESPONSIVE â”€â”€ */
        @media(max-width:960px){.main{grid-template-columns:1fr}.summary-cards{grid-template-columns:repeat(2,1fr)}.sidebar{display:grid;grid-template-columns:1fr 1fr;gap:28px}.page-header-inner{padding:48px 40px}.foot-grid{grid-template-columns:1fr 1fr}}
        @media(max-width:600px){.summary-cards{grid-template-columns:1fr 1fr}.nav-links{display:none}.filter-bar{flex-direction:column;align-items:stretch}.sidebar{grid-template-columns:1fr}.page-header-inner{padding:40px 28px}.main{margin-bottom:80px}.foot-grid{grid-template-columns:1fr}.foot-btm{flex-direction:column;gap:8px}}

        /* â”€â”€ ANIMATIONS â”€â”€ */
        @keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
        .s-card{animation:fadeUp .5s both}
        .s-card:nth-child(1){animation-delay:.05s}
        .s-card:nth-child(2){animation-delay:.1s}
        .s-card:nth-child(3){animation-delay:.15s}
        .s-card:nth-child(4){animation-delay:.2s}
        @keyframes growBar{from{width:0}to{width:var(--w)}}
    </style>
</head>
<body>

<!-- â•â•â• NAV â•â•â• -->
<nav>
    <a href="/test/index.php" class="nav-logo">
        <div class="nav-logo-mark">&#9784;</div>
        <div>
            <span class="nav-logo-name">Seela suwa herath</span>
            <span class="nav-logo-sub">Monastery Welfare</span>
        </div>
    </a>
    <ul class="nav-links">
        <li><a href="/test/index.php#mission">Our Mission</a></li>
        <li><a href="/test/index.php#how">How It Works</a></li>
        <li><a href="/test/pages/public/public_transparency.php" class="active">Transparency</a></li>
        <li><a href="/test/pages/auth/login.php">Sign In</a></li>
        <li><a href="/test/pages/public/public_donate.php" class="nav-donate">Donate Now</a></li>
    </ul>
</nav>

<!-- â•â•â• PAGE HEADER â•â•â• -->
<div class="page-header">
    <div class="page-header-inner">
        <div class="page-header-eyebrow">Open Books &middot; Public Accountability</div>
        <h1>Full <em>Transparency</em> Reports</h1>
        <p>Every donation received and every rupee spent is recorded here publicly, permanently, and honestly.</p>
    </div>
</div>

<!-- â•â•â• SUMMARY CARDS (overlapping) â•â•â• -->
<div class="summary-wrap">
    <div class="summary-cards">
        <div class="s-card">
            <div class="s-card-icon"></div>
            <div class="s-card-num"><?= moneyFmt($totalDonations) ?></div>
            <div class="s-card-label">Total Donations</div>
            <div class="s-card-sub">Paid + verified donations</div>
        </div>
        <div class="s-card">
            <div class="s-card-icon"></div>
            <div class="s-card-num"><?= moneyFmt($fundsUtilised) ?></div>
            <div class="s-card-label">Funds Utilised</div>
            <div class="s-card-sub"><?= $utilPercent ?>% utilisation rate</div>
        </div>
        <div class="s-card">
            <div class="s-card-icon"></div>
            <div class="s-card-num"><?= number_format($totalDonors) ?></div>
            <div class="s-card-label">Total Donors</div>
            <div class="s-card-sub">Unique donor identities</div>
        </div>
        <div class="s-card">
            <div class="s-card-icon"></div>
            <div class="s-card-num"><?= number_format($livesImpacted) ?></div>
            <div class="s-card-label">Lives Impacted</div>
            <div class="s-card-sub">Active monks in care</div>
        </div>
    </div>
</div>

<!-- â•â•â• MAIN CONTENT â•â•â• -->
<div class="main">

    <!-- â”€â”€â”€ LEFT COLUMN â”€â”€â”€ -->
    <div>

        <!-- TOP ROW: FUND BREAKDOWN + UTILISATION -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:44px;margin-bottom:36px;align-items:start">
            <!-- FUND BREAKDOWN -->
            <div class="card">
            <div class="card-title"><span></span> Fund Allocation Breakdown</div>

            <?php foreach($breakdownRows as $idx => $row):
                $label = $row['category'] ?: 'Uncategorized';
                $amt = (float)$row['total'];
                $pct = $breakdownTotal > 0 ? round(($amt / $breakdownTotal) * 100) : 0;
                $color = $breakdownColors[$idx % count($breakdownColors)];
            ?>
            <div class="breakdown-item">
                <div class="bd-row">
                    <div class="bd-label">
                        <div class="bd-dot" style="background:<?= $color ?>"></div>
                        <?= htmlspecialchars($label) ?>
                    </div>
                    <div class="bd-vals">
                        <?= moneyFmt($amt) ?>
                        <span class="bd-pct"><?= $pct ?>%</span>
                    </div>
                </div>
                <div class="progress-track">
                    <div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($breakdownRows)): ?>
            <div style="font-size:.88rem;color:var(--text-light)">No verified financial records yet.</div>
            <?php endif; ?>
            </div>

            <!-- UTILISATION RING (moved to top row) -->
            <div class="mini-card">
                <div class="mini-card-title">Funds Utilisation</div>
                <div class="util-ring-wrap">
                    <div class="util-ring">
                        <svg width="120" height="120" viewBox="0 0 120 120">
                            <circle cx="60" cy="60" r="50" fill="none" stroke="#F5E0C8" stroke-width="12"/>
                            <circle cx="60" cy="60" r="50" fill="none" stroke="#D4622A" stroke-width="12"
                                stroke-dasharray="314" stroke-dashoffset="<?= number_format($utilOffset, 1, '.', '') ?>"
                                    stroke-linecap="round"/>
                        </svg>
                        <div class="util-ring-label">
                            <div class="util-ring-pct"><?= $utilPercent ?>%</div>
                            <div class="util-ring-sub">USED</div>
                        </div>
                    </div>
                    <div class="util-legend">
                        <span><span class="util-dot" style="background:var(--orange)"></span>Utilised</span>
                        <span><span class="util-dot" style="background:var(--sand)"></span>Reserve</span>
                    </div>
                </div>
                <div style="font-size:.8rem;color:var(--text-light);text-align:center;line-height:1.6">
                    <?= moneyFmt($fundsUtilised) ?> of <?= moneyFmt($totalDonations) ?><br>allocated to welfare programs
                </div>
            </div>
        </div><!-- end top row grid -->

        <!-- DONATIONS TABLE -->
        <div class="card" id="donation-records">
            <div class="card-title"><span></span> Donation Records</div>

            <div style="font-size:.8rem;color:var(--text-light);margin-bottom:16px">Showing latest donations from backend records.</div>

            <div class="table-wrap">
                <table class="dtable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Donor</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Method</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
require_once __DIR__ . '/../../includes/init.php';
                        $statusMap = [
                            'verified' => ['badge-verified','&#10003; Verified'],
                            'pending'  => ['badge-pending', '&#9203; Pending'],
                            'rejected' => ['badge-rejected','&#10005; Rejected'],
                            'paid'     => ['badge-verified','&#10003; Paid'],
                            'failed'   => ['badge-rejected','&#10005; Failed'],
                            'cancelled'=> ['badge-rejected','&#10005; Cancelled'],
                        ];
                        foreach($donationRows as $row):
                            $status = strtolower((string)$row['status']);
                            [$cls,$lbl] = $statusMap[$status] ?? ['badge-pending', ucfirst($status ?: 'Unknown')];
                            $method = match(strtolower((string)$row['method'])) {
                                'card_sandbox' => 'Card',
                                'bank' => 'Bank Slip',
                                'cash' => 'Cash',
                                default => ucfirst((string)$row['method'])
                            };
                        ?>
                        <tr>
                            <td style="color:var(--text-light);font-size:.75rem"><?= 'DON-' . str_pad((string)$row['donation_id'], 4, '0', STR_PAD_LEFT) ?></td>
                            <td><span class="donor-name"><?= htmlspecialchars($row['donor_name']) ?></span></td>
                            <td><span class="category-tag"><?= htmlspecialchars($row['category']) ?></span></td>
                            <td><span class="amount-cell"><?= moneyFmt($row['amount']) ?></span></td>
                            <td style="color:var(--text-light);font-size:.82rem"><?= date('d M Y', strtotime((string)$row['created_at'])) ?></td>
                            <td style="font-size:.82rem"><?= htmlspecialchars($method) ?></td>
                            <td><span class="badge <?= $cls ?>"><?= $lbl ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($donationRows)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;color:var(--text-light)">No donations found yet.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($donationTotalPages > 1): ?>
            <div class="pagination">
                <?php for ($p = 1; $p <= $donationTotalPages; $p++): ?>
                    <a href="?donation_page=<?= $p ?>#donation-records" class="page-btn <?= $p === $donationPage ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- EXPENDITURE -->
        <div class="card">
            <div class="card-title"><span></span> Recent Expenditures</div>
            <?php
require_once __DIR__ . '/../../includes/init.php';
            $expenseIcons = ['&#127973;', '&#127834;', '&#128138;', '&#127968;', '&#128652;', '&#128230;'];
            foreach($expenseRows as $idx => $row):
                $icon = $expenseIcons[$idx % count($expenseIcons)];
            ?>
            <div class="exp-item">
                <div class="exp-icon"><?= $icon ?></div>
                <div class="exp-info">
                    <div class="exp-name"><?= htmlspecialchars($row['item_name']) ?></div>
                    <div class="exp-meta"><span class="category-tag" style="font-size:.68rem"><?= htmlspecialchars($row['category']) ?></span> &nbsp;<?= date('M d, Y', strtotime((string)$row['bill_date'])) ?></div>
                </div>
                <div class="exp-amount"><?= moneyFmt($row['amount']) ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($expenseRows)): ?>
            <div style="font-size:.88rem;color:var(--text-light)">No expenditure records available yet.</div>
            <?php endif; ?>
        </div>

        <!-- ACCEPTED DONATION DATES -->
        <div class="card" id="accepted-alms-dates">
            <div class="card-title"><span></span> Accepted Daily Alms Dates</div>
            
            <div style="font-size:.8rem;color:var(--text-light);margin-bottom:16px">Upcoming approved donation dates from community members.</div>

            <div class="table-wrap">
                <table class="dtable">
                    <thead>
                        <tr>
                            <th>Donor Name</th>
                            <th>Email</th>
                            <th>Donation Date</th>
                            <th>Meal Type</th>
                            <th>Approved On</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($acceptedDatesRows as $row): ?>
                        <tr>
                            <td><span class="donor-name"><?= htmlspecialchars($row['donor_name']) ?></span></td>
                            <td style="font-size:.82rem;color:var(--text-mid)"><?= htmlspecialchars($row['donor_email']) ?></td>
                            <td>
                                <strong style="color:var(--orange)"><?= date('d M Y', strtotime((string)$row['requested_date'])) ?></strong>
                            </td>
                            <td style="font-size:.82rem">
                                <span class="category-tag"><?= ucfirst(htmlspecialchars($row['meal_type'])) ?></span>
                            </td>
                            <td style="font-size:.75rem;color:var(--text-light)"><?= !empty($row['reviewed_at']) ? date('d M Y', strtotime((string)$row['reviewed_at'])) : '&mdash;' ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($acceptedDatesRows)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center;color:var(--text-light)">No approved donation dates scheduled yet.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($acceptedDatesTotalPages > 1): ?>
            <div class="pagination">
                <?php for ($p = 1; $p <= $acceptedDatesTotalPages; $p++): ?>
                    <a href="?alms_page=<?= $p ?>#accepted-alms-dates" class="page-btn <?= $p === $acceptedDatesPage ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- end left col -->

    <!-- â”€â”€â”€ SIDEBAR â”€â”€â”€ -->
    <div class="sidebar">

        <!-- MONTHLY CHART -->
        <div class="mini-card">
            <div class="mini-card-title">Monthly Donations (2026)</div>
            <?php $months = array_values($monthlyData); ?>
            <div class="bar-chart">
                <?php foreach($months as $m):
                    $v = (float)$m['total'];
                    $h = $v > 0 ? round(($v / $monthlyMax) * 90) : 4;
                    $color = $v > 0 ? 'var(--orange-pale)' : 'var(--border)';
                ?>
                <div class="bar-col">
                    <?php if($v > 0): ?><div class="bar-val" style="font-size:.6rem">Rs.<?= number_format(round($v / 1000)) ?>k</div><?php endif; ?>
                    <div class="bar" data-color="<?= $color ?>" style="height:<?= $h ?>px;background:<?= $color ?>"></div>
                    <div class="bar-lbl"><?= htmlspecialchars($m['label']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="font-size:.75rem;color:var(--text-light);text-align:center">Last 6 months total: <?= moneyFmt($last6MonthsTotal) ?></div>
        </div>

        <!-- RECENT DONORS -->
        <div class="mini-card">
            <div class="mini-card-title">Recent Donors</div>
            <?php foreach($recentDonorRows as $row): ?>
            <div class="donor-row">
                <div class="donor-avatar"><?= donorInitials($row['donor_name']) ?></div>
                <div class="donor-info">
                    <div class="name"><?= htmlspecialchars($row['donor_name']) ?></div>
                    <div class="time"><?= htmlspecialchars(relativeTime($row['created_at'])) ?></div>
                </div>
                <div class="donor-amt"><?= moneyFmt($row['amount']) ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($recentDonorRows)): ?>
            <div style="font-size:.82rem;color:var(--text-light)">No recent donor activity yet.</div>
            <?php endif; ?>
        </div>

        <!-- DONATE CTA -->
        <div style="background:#EDE6DC;border:1.5px solid rgba(210,170,130,.28);border-radius:20px;padding:36px 28px;text-align:center;box-shadow:0 6px 24px rgba(50,38,25,.08)">
            <div style="font-size:2.4rem;margin-bottom:14px">&#128591;</div>
            <div style="font-family:'EB Garamond',serif;font-size:1.4rem;color:var(--text-dark);margin-bottom:12px;font-weight:400">Be Part of This</div>
            <div style="font-size:.86rem;color:var(--text-mid);margin-bottom:24px;line-height:1.7">Your donation appears in this report &mdash; fully transparent, fully accountable.</div>
            <a href="/test/pages/public/public_donate.php" style="display:inline-block;background:var(--orange);color:#fff;padding:14px 28px;border-radius:12px;text-decoration:none;font-size:.93rem;font-weight:600;transition:all .2s;box-shadow:0 4px 12px rgba(212,98,42,.18)">Contribute Now &rarr;</a>
        </div>

    </div><!-- end sidebar -->
</div><!-- end main -->

<!-- â•â•â• FOOTER â•â•â• -->
<footer>
    <div class="foot-grid">
        <div>
            <div class="foot-brand">&#9784; Seela suwa herath</div>
            <p class="foot-tag">Supporting monastery welfare through community generosity, transparent governance, and compassionate care.</p>
        </div>
        <div class="foot-col">
            <h4>Platform</h4>
            <ul>
                <li><a href="/test/pages/public/public_donate.php">Donate</a></li>
                <li><a href="/test/pages/public/public_transparency.php">Transparency</a></li>
                <li><a href="/test/pages/auth/register.php">Register</a></li>
                <li><a href="/test/pages/auth/login.php">Sign In</a></li>
            </ul>
        </div>
        <div class="foot-col">
            <h4>Welfare</h4>
            <ul>
                <li><a href="#">Healthcare</a></li>
                <li><a href="#">Housing</a></li>
                <li><a href="#">Appointments</a></li>
                <li><a href="#">Reports</a></li>
            </ul>
        </div>
        <div class="foot-col">
            <h4>Info</h4>
            <ul>
                <li><a href="#">About Us</a></li>
                <li><a href="#">Contact</a></li>
                <li><a href="#">Privacy Policy</a></li>
            </ul>
        </div>
    </div>
    <div class="foot-btm"><span>&copy; 2026 Seela suwa herath Monastery Welfare Platform</span><span>Made with in Sri Lanka</span></div>
</footer>

<script>
// Animate progress bars on load
window.addEventListener('load', () => {
    document.querySelectorAll('.progress-fill').forEach(el => {
        const w = el.style.width;
        el.style.width = '0';
        setTimeout(() => el.style.width = w, 100);
    });
});

// Bar chart hover highlight
document.querySelectorAll('.bar').forEach(b => {
    b.addEventListener('mouseenter', () => b.style.background = 'var(--orange)');
    b.addEventListener('mouseleave', () => b.style.background = b.dataset.color || 'var(--orange-pale)');
});
</script>
</body>
</html>

