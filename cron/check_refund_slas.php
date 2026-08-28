<?php
/**
 * Standalone cron entry point for the refund SLA check — schedule this via
 * cPanel's "Cron Jobs" (every 15–30 minutes is plenty) so warnings/escalation
 * fire even when nobody happens to be browsing the hotel or admin portal:
 *
 *   php /home/<cpanel-user>/public_html/travelix/cron/check_refund_slas.php
 *
 * (adjust the path to wherever this project actually lives on the server)
 *
 * The same check also runs opportunistically on every hotel_portal/refunds.php
 * and admin_manage/commission_payments.php page load — this cron is a
 * reliability backstop, not the only place it runs.
 */

$docRoot = dirname(__DIR__);
require_once $docRoot . '/config/firebase_config.php';
require_once $docRoot . '/includes/refund_lib.php';

$saPath = $docRoot . '/config/firebase-service-account.json';
$projectId = FIREBASE_PROJECT_ID;

$result = hp_check_refund_slas($saPath, $projectId);

$timestamp = date('c');
echo "[{$timestamp}] Refund SLA check — warned: " . count($result['warned']) . ", escalated: " . count($result['escalated']) . "\n";
