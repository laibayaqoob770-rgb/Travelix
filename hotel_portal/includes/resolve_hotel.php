<?php
/**
 * Hotel-portal specific server-side lookups.
 *
 * The generic Firestore / Identity Toolkit primitives live in
 * includes/firestore_admin.php and are shared with the admin pages.
 * Everything goes through the admin service account, which bypasses Firestore
 * client security rules — a hotel_staff account can be blocked from running
 * collection queries, which previously left the dashboard stuck on
 * "no hotel assigned".
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/travelix/includes/firestore_admin.php';

/**
 * Returns the full hotel document linked to this staff account, as
 * ['id' => ..., ...fields], or null. Looks the hotel up by staff_uid — the
 * authoritative link the admin sets — so a stale or wrong hotel_id in the
 * PHP session can never produce a wrong/empty dashboard.
 */
function hp_get_staff_hotel($serviceAccountPath, $projectId, $uid)
{
    if ($uid === '') return null;
    $rows = hp_firestore_query($serviceAccountPath, $projectId, 'hotels', 'staff_uid', $uid, 1);
    return $rows[0] ?? null;
}

/** Bookings for a hotel, decoded server-side. Returns a list of plain arrays. */
function hp_get_hotel_bookings($serviceAccountPath, $projectId, $hotelId)
{
    if ($hotelId === '') return [];
    return hp_firestore_query($serviceAccountPath, $projectId, 'hotel_bookings', 'hotelId', $hotelId);
}

/** Finds a hotel_staff record by email. Returns decoded fields + id, or null. */
function hp_find_staff_by_email($serviceAccountPath, $projectId, $email)
{
    if ($email === '') return null;
    $rows = hp_firestore_query($serviceAccountPath, $projectId, 'hotel_staff', 'email', $email, 1);
    return $rows[0] ?? null;
}

/**
 * Loads this staff account's hotel and keeps the session in sync with it.
 * Call at the top of every portal page (after the session guard). Returns the
 * full hotel array, or null when the admin hasn't linked a hotel yet.
 */
function hp_load_portal_hotel()
{
    if (empty($_SESSION['hotel_staff'])) return null;

    $baseUrl = '/travelix';
    $configPath = $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase_config.php';
    if (!file_exists($configPath)) return null;
    require_once $configPath;

    $serviceAccountPath = $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/config/firebase-service-account.json';

    try {
        $hotel = hp_get_staff_hotel($serviceAccountPath, FIREBASE_PROJECT_ID, $_SESSION['hotel_staff']['uid']);
    } catch (Throwable $e) {
        $hotel = null;
    }

    if ($hotel) {
        // Keep the session in step with the authoritative record.
        $_SESSION['hotel_staff']['hotel_id']   = $hotel['id'];
        $_SESSION['hotel_staff']['hotel_name'] = $hotel['name'] ?? '';
    }

    return $hotel;
}

/**
 * Back-compat wrapper used by ajax/set_session.php AND by portal pages that
 * don't need the hotel array themselves (hotel_bookings.php, edit_hotel.php)
 * — those still get the mid-session disabled-account kick-out via this call.
 */
function ensure_staff_hotel_linked()
{
    $hotel = hp_load_portal_hotel();
    if (!empty($_SESSION['hotel_staff'])) {
        hp_enforce_hotel_active($hotel);
    }
}

/**
 * Call after hp_load_portal_hotel() on every portal PAGE (never from
 * set_session.php, which needs to handle the disabled case itself and
 * return JSON rather than a redirect). Kicks out a staff member mid-session
 * if their hotel got disabled (refund SLA) or deactivated after they logged
 * in — otherwise a still-open browser tab could keep working an account the
 * admin just shut down.
 */
function hp_enforce_hotel_active(?array $hotel): void
{
    if (!$hotel) return;

    $disabled = !empty($hotel['disabled']) || strtolower((string)($hotel['status'] ?? 'active')) === 'inactive';
    if (!$disabled) return;

    unset($_SESSION['hotel_staff']);
    header('Location: /travelix/hotel_portal/login.php?disabled=1');
    exit;
}
