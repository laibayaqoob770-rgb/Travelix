<?php
/**
 * Travelix payout ledger.
 *
 * Guests pay Travelix directly (hotel price + 12% platform fee on top), so
 * there is no "hotel pays admin commission" flow any more — the only ledger
 * that matters is what Travelix owes each hotel (100% of their price) and
 * what has actually been paid out.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/travelix/includes/firestore_admin.php';

// The 12% platform fee is charged ON TOP of the hotel's price (added to what
// the guest pays), not deducted from the hotel's share — so the hotel is
// always owed 100% of hotelPrice, never 88%.
const HP_HOTEL_SHARE_RATE = 1.0;

/** All payouts admin has sent to hotels, optionally scoped to one hotel. Newest first. */
function hp_get_payout_payments($serviceAccountPath, $projectId, $hotelId = null)
{
    $rows = $hotelId
        ? hp_firestore_query($serviceAccountPath, $projectId, 'payout_payments', 'hotelId', $hotelId)
        : hp_firestore_query($serviceAccountPath, $projectId, 'payout_payments');

    usort($rows, function ($a, $b) {
        return (int)($b['sentAt'] ?? 0) <=> (int)($a['sentAt'] ?? 0);
    });

    return $rows;
}

/**
 * Builds the per-booking payout ledger for one hotel under the centralized
 * payment model: guests now pay Travelix directly (hotel's price + 12%
 * platform fee on top), so admin owes each hotel the FULL hotelPrice — the
 * platform fee is never deducted from the hotel's share. Mirrors hp_build_commission_ledger()
 * but in the opposite direction — a payout record (collection `payout_payments`)
 * is admin asserting "I sent this hotel their share for these bookings".
 *
 * A payout starts 'pending' (admin uploaded proof, hotel hasn't acknowledged
 * receipt yet) and only becomes 'paid' once the hotel confirms it received
 * the money — mirrors the hotel's own booking-payment proof flow, just with
 * the two sides swapped.
 */
function hp_build_payout_ledger(array $bookings, array $payouts)
{
    $paidIds = [];
    $pendingIds = [];
    foreach ($payouts as $p) {
        $ids = is_array($p['bookingIds'] ?? null) ? $p['bookingIds'] : [];
        $isConfirmed = (string)($p['status'] ?? 'pending') === 'confirmed';
        foreach ($ids as $id) {
            if ($isConfirmed) {
                $paidIds[$id] = true;
            } else {
                $pendingIds[$id] = true;
            }
        }
    }

    $rows = [];
    $total = $paid = $due = $pending = 0.0;
    $counts = ['paid' => 0, 'due' => 0, 'pending' => 0];

    foreach ($bookings as $b) {
        $bookingStatus = strtolower((string)($b['bookingStatus'] ?? $b['status'] ?? 'confirmed'));
        // Payout happens before final hotel confirmation. Include verified
        // bookings while admin is sending / hotel is confirming the money.
        if (!in_array($bookingStatus, ['payment_verified', 'pending_hotel_confirmation', 'confirmed'], true)) continue;

        $id      = (string)($b['id'] ?? '');
        $amount  = (float)($b['hotelPrice'] ?? $b['total_amount'] ?? 0);
        $payout  = $amount * HP_HOTEL_SHARE_RATE;
        $status  = isset($paidIds[$id]) ? 'paid' : (isset($pendingIds[$id]) ? 'pending' : 'due');

        if ($status === 'paid') $paid += $payout;
        elseif ($status === 'pending') $pending += $payout;
        else $due += $payout;
        $counts[$status]++;
        $total += $payout;

        $rows[] = [
            'id'            => $id,
            'guest'         => (string)($b['userEmail'] ?? $b['guestName'] ?? 'Guest'),
            'arrivalDate'   => (string)($b['arrivalDate'] ?? ''),
            'departureDate' => (string)($b['departureDate'] ?? ''),
            'amount'        => $amount,
            'payout'        => $payout,
            'status'        => $status,
        ];
    }

    usort($rows, function ($a, $b) {
        return strcmp($b['arrivalDate'], $a['arrivalDate']);
    });

    return [
        'rows'    => $rows,
        'total'   => $total,
        'paid'    => $paid,
        'due'     => $due,
        'pending' => $pending,
        'counts'  => $counts,
    ];
}

/** Convenience: full payout ledger for one hotel, loading bookings + payouts itself. */
function hp_hotel_payout($serviceAccountPath, $projectId, $hotelId)
{
    if ($hotelId === '') {
        return ['rows' => [], 'total' => 0, 'paid' => 0, 'due' => 0,
                'counts' => ['paid' => 0, 'due' => 0], 'payouts' => []];
    }

    $bookings = hp_firestore_query($serviceAccountPath, $projectId, 'hotel_bookings', 'hotelId', $hotelId);
    $payouts  = hp_get_payout_payments($serviceAccountPath, $projectId, $hotelId);

    $ledger = hp_build_payout_ledger($bookings, $payouts);
    $ledger['payouts'] = $payouts;

    return $ledger;
}

/**
 * Calculates the refund a guest is entitled to when cancelling a booking,
 * based on the hotel's cancellationPolicy ({type, windowHours, refundPercent})
 * and how many hours remain before arrival. Never trust a browser-supplied
 * refund amount — this is the single source of truth, called both for the
 * pre-cancel preview and when actually processing the cancellation.
 */
function hp_calculate_refund(array $hotel, string $arrivalDate, float $totalPaid): array
{
    $policy        = is_array($hotel['cancellationPolicy'] ?? null) ? $hotel['cancellationPolicy'] : [];
    $type          = (string)($policy['type'] ?? 'free');
    $windowHours   = (float)($policy['windowHours'] ?? 24);
    $refundPercent = (float)($policy['refundPercent'] ?? 100);

    if ($type === 'non_refundable') {
        return [
            'refundAmount'  => 0.0,
            'refundPercent' => 0,
            'canCancel'     => true,
            'reason'        => "This hotel's cancellation policy is non-refundable.",
        ];
    }

    $arrivalTs = strtotime($arrivalDate . ' 00:00:00');

    if ($arrivalTs === false) {
        return [
            'refundAmount'  => 0.0,
            'refundPercent' => 0,
            'canCancel'     => false,
            'reason'        => 'Could not determine the arrival date for this booking.',
        ];
    }

    $hoursUntilArrival = ($arrivalTs - time()) / 3600;

    if ($hoursUntilArrival >= $windowHours) {
        $percent = $type === 'free' ? 100.0 : $refundPercent;
        $amount  = round($totalPaid * ($percent / 100), 2);

        return [
            'refundAmount'  => $amount,
            'refundPercent' => $percent,
            'canCancel'     => true,
            'reason'        => "Cancelled more than {$windowHours} hours before check-in — {$percent}% refund applies.",
        ];
    }

    return [
        'refundAmount'  => 0.0,
        'refundPercent' => 0,
        'canCancel'     => true,
        'reason'        => "Cancelled within {$windowHours} hours of check-in — no refund applies under this hotel's policy.",
    ];
}

/** Formats a PKR amount for display. */
function hp_money($n)
{
    return 'PKR ' . number_format((float)$n);
}

/** Human label + colour for a ledger status. */
function hp_status_style($status)
{
    switch ($status) {
        case 'paid':    return ['Paid',                '#166534', '#f0fdf4'];
        case 'pending': return ['Awaiting Verification','#92400e', '#fffbeb'];
        default:        return ['Unpaid',              '#991b1b', '#fef2f2'];
    }
}
