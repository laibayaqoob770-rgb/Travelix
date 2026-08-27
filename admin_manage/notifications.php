<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$baseUrl='/travelix';
if (empty($_SESSION['user']['uid'])) { header('Location: '.$baseUrl.'/auth/login.php'); exit; }
if (strtolower((string)($_SESSION['user']['role']??''))!=='admin') { header('Location: '.$baseUrl.'/dashboard/admin_dashboard.php'); exit; }
require_once $_SERVER['DOCUMENT_ROOT'].$baseUrl.'/config/firebase_config.php';
require_once $_SERVER['DOCUMENT_ROOT'].$baseUrl.'/includes/firestore_admin.php';
$rows=hp_firestore_query($_SERVER['DOCUMENT_ROOT'].$baseUrl.'/config/firebase-service-account.json',FIREBASE_PROJECT_ID,'notifications','audience','admin');
usort($rows,fn($a,$b)=>strtotime((string)($b['createdAt']??''))<=>strtotime((string)($a['createdAt']??'')));
function nh($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Notifications — Travelix Admin</title>
<link rel="stylesheet" href="/travelix/assets/vendor/bootstrap.min.css"><link rel="stylesheet" href="/travelix/assets/vendor/fontawesome/all.min.css">
<style>body{background:#f0f4fa;font-family:Segoe UI,sans-serif}.admin-page{display:flex;gap:24px;padding:16px;min-height:100vh}.admin-content{flex:1;padding:18px}.notice-card{max-width:980px;background:#fff;border-radius:20px;box-shadow:0 3px 18px #0f172a12;overflow:hidden}.notice-head{padding:22px 25px;border-bottom:1px solid #e5e7eb}.notice{display:flex;gap:14px;padding:18px 24px;border-bottom:1px solid #eef2f7;color:#0f172a;text-decoration:none}.notice:hover{background:#f8fbff}.notice.unread{border-left:4px solid #1484B4;background:#f0f9ff}.notice h3{font-size:15px;margin:0 0 4px}.notice p{font-size:13px;color:#64748b;margin:0}.notice time{font-size:11px;color:#94a3b8}.empty{padding:50px;text-align:center;color:#94a3b8}@media(max-width:991px){.admin-page{display:block;padding:0}.admin-content{padding:16px}}</style></head><body>
<div class="admin-page"><?php include $_SERVER['DOCUMENT_ROOT'].$baseUrl.'/includes/admin_sidebar.php'; ?><main class="admin-content"><div class="notice-card"><div class="notice-head"><h1 style="font-size:24px;margin:0">All Notifications</h1><p style="margin:5px 0 0;color:#64748b">Admin payments, payouts, bookings and refund alerts.</p></div>
<?php if(!$rows):?><div class="empty">No notifications yet.</div><?php else:foreach($rows as $n):?><a class="notice <?= empty($n['isRead'])?'unread':'' ?>" href="<?= nh($n['link']??'#') ?>"><div><i class="<?= nh($n['icon']??'fa-solid fa-bell') ?>"></i></div><div><h3><?= nh($n['title']??'Notification') ?></h3><p><?= nh($n['message']??'') ?></p><time><?= nh($n['createdAt']??'') ?></time></div></a><?php endforeach;endif;?>
</div></main></div></body></html>
