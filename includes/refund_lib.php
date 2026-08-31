<?php
/**
 * Refund SLA enforcement — hotel-first refund flow.
 *
 * When a booking is cancelled/rejected, the refund is owed by the HOTEL
 * (not admin) — the hotel received 100% of the guest's money via payout, so
 * it holds the refund obligation. Sending it isn't the end of the story
 * either — the guest must confirm receipt before it counts as settled.
 * Three independent failure paths all lead to the same outcome (hotel
 * disabled, refund escalated to admin):
 *
 *  A) Silence — hotel never sends anything:
 *       refundWarnAt (requestedAt+24h)     -> warning notification
 *       refundEscalateAt (requestedAt+48h) -> escalate + disable
 *
 *  B) Wrong/insufficient amount submitted:
 *       1st wrong attempt  -> immediate warning, refundEscalateAt reset to
 *                             now+24h (a final, shortened window)
 *       2nd wrong attempt  -> escalate + disable immediately, no more waiting
 *
 *  C) Guest disputes a 'sent' refund (says the money never arrived):
 *       refundDisputeEscalateAt (disputedAt+24h) -> escalate + disable,
 *       unless the hotel resends (and the guest reconfirms) before then.
 *
 * This file's hp_check_refund_slas() implements the time-based halves of A
 * and C — B is handled inline wherever a hotel submits a refund (see
 * hotel_portal/ajax/submit_hotel_refund.php). Call hp_check_refund_slas()
 * on every hotel_portal/admin page load (cheap — small dataset) and from a
 * standalone cron script for reliability when nobody is browsing.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/travelix/includes/firestore_admin.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/travelix/includes/commission_lib.php'; // for hp_money()

/** Shared terminal action for every refund SLA failure path: hand the refund to admin and disable the hotel. */
function hp_escalate_refund($serviceAccountPath, $projectId, array $booking, string $docPath, string $reasonMessage): void
{
    $hotelId = (string)($booking['hotelId'] ?? '');
    hp_firestore_patch($serviceAccountPath, $projectId, $docPath, [
        'refundStatus' => 'escalated',
        'refundOwner' => 'admin',
    ]);

    if ($hotelId !== '') {
        hp_firestore_patch($serviceAccountPath, $projectId, 'hotels/' . $hotelId, [
            'disabled' => true,
            'disabledReason' => 'refund_sla',
            'disabledAt' => date('c'),
        ]);

        hp_firestore_create($serviceAccountPath, $projectId, 'notifications', [
            'audience' => 'hotel', 'hotelId' => $hotelId,
            'title' => 'Account Disabled — Refund Overdue',
            'message' => $reasonMessage,
            'type' => 'refund_escalated', 'icon' => 'fa-solid fa-ban',
            'link' => '/travelix/hotel_portal/refunds.php',
            'isRead' => false, 'createdAt' => date('c'),
        ]);
    }

    hp_firestore_create($serviceAccountPath, $projectId, 'notifications', [
        'audience' => 'admin',
        'title' => 'Refund Escalated — Hotel Failed to Pay',
        'message' => 'A hotel did not settle a customer refund in time (' . hp_money((float)($booking['refundAmount'] ?? 0)) . '). Please pay the customer directly.',
        'type' => 'refund_pending', 'icon' => 'fa-solid fa-triangle-exclamation',
        'link' => '/travelix/admin_manage/refunds.php',
        'isRead' => false, 'createdAt' => date('c'),
    ]);
}

/**
 * Scans every hotel_bookings doc with a refund still owned by the hotel and
 * applies the SLA timers (paths A and C above). Returns a summary of
 * actions taken (for cron logging) — safe to call repeatedly, each check is
 * idempotent.
 */
function hp_check_refund_slas($serviceAccountPath, $projectId): array
{
    $nowMs = (int) round(microtime(true) * 1000);
    $warned = [];
    $escalated = [];

    // Path A — hotel never sent anything.
    $pendingBookings = hp_firestore_query($serviceAccountPath, $projectId, 'hotel_bookings', 'refundStatus', 'pending');

    foreach ($pendingBookings as $b) {
        if ((string)($b['refundOwner'] ?? '') !== 'hotel') continue;

        $id = (string)($b['id'] ?? '');
        $warnAt = (float)($b['refundWarnAt'] ?? 0);
        $escalateAt = (float)($b['refundEscalateAt'] ?? 0);
        $alreadyWarned = (bool)($b['refundWarned'] ?? false);

        $docPath = 'hotel_bookings/' . $id;

        // Escalate first — if both thresholds have passed, escalation wins
        // over just sending a (now pointless) warning.
        if ($escalateAt > 0 && $nowMs >= $escalateAt) {
            hp_escalate_refund($serviceAccountPath, $projectId, $b, $docPath,
                'You did not send a customer refund in time. Your account has been disabled until Travelix admin re-enables it.');
            $escalated[] = $id;
            continue;
        }

        if ($warnAt > 0 && $nowMs >= $warnAt && !$alreadyWarned) {
            $hotelId = (string)($b['hotelId'] ?? '');
            hp_firestore_patch($serviceAccountPath, $projectId, $docPath, [
                'refundWarned' => true,
            ]);

            if ($hotelId !== '') {
                hp_firestore_create($serviceAccountPath, $projectId, 'notifications', [
                    'audience' => 'hotel', 'hotelId' => $hotelId,
                    'title' => 'Refund Overdue — Action Needed',
                    'message' => 'A customer refund of ' . hp_money((float)($b['refundAmount'] ?? 0)) . ' is overdue. Send it within 24 hours or your account will be disabled.',
                    'type' => 'refund_pending', 'icon' => 'fa-solid fa-hourglass-half',
                    'link' => '/travelix/hotel_portal/refunds.php',
                    'isRead' => false, 'createdAt' => date('c'),
                ]);
            }

            $warned[] = $id;
        }
    }

    // Path C — guest disputed a 'sent' refund and the hotel hasn't resolved
    // it (resent + guest reconfirmed) within 24 hours.
    $disputedBookings = hp_firestore_query($serviceAccountPath, $projectId, 'hotel_bookings', 'refundStatus', 'disputed');

    foreach ($disputedBookings as $b) {
        if ((string)($b['refundOwner'] ?? '') !== 'hotel') continue;

        $id = (string)($b['id'] ?? '');
        $disputeEscalateAt = (float)($b['refundDisputeEscalateAt'] ?? 0);
        if ($disputeEscalateAt <= 0 || $nowMs < $disputeEscalateAt) continue;

        $docPath = 'hotel_bookings/' . $id;
        hp_escalate_refund($serviceAccountPath, $projectId, $b, $docPath,
            'A guest disputed a refund you sent and you did not resolve it within 24 hours. Your account has been disabled until Travelix admin re-enables it.');
        $escalated[] = $id;
    }

    return ['warned' => $warned, 'escalated' => $escalated];
}
