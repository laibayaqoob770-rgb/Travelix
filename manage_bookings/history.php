<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$baseUrl = '/travelix';
$currentUser = $_SESSION['user'] ?? [];

if (!isset($_SESSION['user']) || empty($_SESSION['user']['uid'])) {
    header('Location: ' . $baseUrl . '/auth/login.php');
    exit;
}

$firstName = (string)($currentUser['first_name'] ?? 'Traveler');
$lastName = (string)($currentUser['last_name'] ?? '');
$email = (string)($currentUser['email'] ?? '');
$profileImage = (string)($currentUser['profile_image'] ?? ($baseUrl . '/images/default_profile.png'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Trips | Travelix</title>

    <link rel="stylesheet" href="/travelix/assets/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/travelix/assets/js/travelix_swal_autoclose.js"></script>

    <style>
        :root {
            --theme-color: #1484B4;
            --theme-dark: #0f6d95;
            --theme-soft: #eaf7fc;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border-soft: rgba(15, 23, 42, 0.08);
            --shadow-soft: 0 16px 40px rgba(15, 23, 42, 0.08);
            --success-soft: rgba(34, 197, 94, 0.12);
            --success-text: #15803d;
            --danger: #dc2626;
        }

        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: #f5f9fc;
            color: var(--text-dark);
        }

        .history-page-wrap {
            padding: 140px 18px 60px;
        }

        .history-page-inner {
            max-width: 1280px;
            margin: 0 auto;
        }

        .history-hero {
            background: linear-gradient(135deg, rgba(20,132,180,0.95), rgba(15,109,149,0.95));
            border-radius: 28px;
            padding: 36px 34px;
            color: #fff;
            margin-bottom: 28px;
            box-shadow: var(--shadow-soft);
        }

        .history-hero h1 {
            margin: 0 0 10px;
            font-size: 44px;
            font-weight: 800;
        }

        .history-hero p {
            margin: 0;
            max-width: 780px;
            line-height: 1.8;
            color: rgba(255,255,255,0.92);
        }

        .history-summary-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }

        .history-summary-card {
            background: #fff;
            border-radius: 24px;
            padding: 22px;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-soft);
        }

        .history-summary-card .icon {
            width: 54px;
            height: 54px;
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--theme-soft);
            color: var(--theme-color);
            font-size: 22px;
            margin-bottom: 14px;
        }

        .history-summary-card .label {
            font-size: 13px;
            font-weight: 800;
            color: #7c8aa0;
            text-transform: uppercase;
            margin-bottom: 10px;
            display: block;
        }

        .history-summary-card .value {
            font-size: 28px;
            font-weight: 800;
            line-height: 1.3;
            word-break: break-word;
        }

        .history-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 24px;
        }

        .history-sidebar,
        .history-content-card {
            background: #fff;
            border-radius: 28px;
            padding: 24px;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-soft);
        }

        .history-user-box {
            text-align: center;
            margin-bottom: 26px;
        }

        .history-user-box img {
            width: 104px;
            height: 104px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(20,132,180,0.14);
            margin-bottom: 14px;
        }

        .history-user-box h4 {
            margin: 0 0 6px;
            font-size: 24px;
            font-weight: 800;
        }

        .history-user-box p {
            margin: 0;
            color: var(--text-muted);
            word-break: break-word;
        }

        .history-tab-btn {
            width: 100%;
            border: none;
            margin-bottom: 12px;
            border-radius: 16px;
            padding: 15px 16px;
            text-align: left;
            font-size: 15px;
            font-weight: 800;
            background: var(--theme-color);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .history-content-head {
            display: flex;
            align-items: start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 22px;
            flex-wrap: wrap;
        }

        .history-content-head h2 {
            margin: 0 0 6px;
            font-size: 32px;
            font-weight: 800;
        }

        .history-content-head p {
            margin: 0;
            color: var(--text-muted);
            line-height: 1.7;
        }

        .history-refresh-btn {
            border: none;
            border-radius: 999px;
            padding: 12px 18px;
            background: var(--theme-color);
            color: #fff;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 12px 24px rgba(20,132,180,0.20);
        }

        .history-refresh-btn:hover {
            background: var(--theme-dark);
        }

        .history-loader {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            min-height: 160px;
            color: var(--text-muted);
            font-weight: 700;
        }

        .history-empty {
            text-align: center;
            padding: 50px 20px;
            border-radius: 22px;
            background: #f8fbfd;
            border: 1px dashed rgba(15, 23, 42, 0.12);
        }

        .history-empty i {
            font-size: 46px;
            color: var(--theme-color);
            margin-bottom: 12px;
            display: inline-block;
        }

        .history-empty h4 {
            font-weight: 800;
            margin-bottom: 8px;
        }

        .history-empty p {
            margin: 0;
            color: var(--text-muted);
        }

        .history-list {
            display: grid;
            gap: 18px;
        }

        .history-item-card {
            background: #fff;
            border-radius: 24px;
            padding: 22px;
            border: 1px solid var(--border-soft);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
        }

        .history-item-top {
            display: flex;
            justify-content: space-between;
            align-items: start;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .history-item-title {
            margin: 0 0 5px;
            font-size: 24px;
            font-weight: 800;
        }

        .history-item-subtitle {
            margin: 0;
            color: var(--text-muted);
            line-height: 1.6;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
            background: var(--success-soft);
            color: var(--success-text);
        }

        .history-info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 18px;
        }

        .history-info-box {
            background: #f8fbfd;
            border-radius: 18px;
            padding: 15px 16px;
            border: 1px solid rgba(15, 23, 42, 0.06);
        }

        .history-info-box .label {
            display: block;
            font-size: 12px;
            font-weight: 800;
            color: #7c8aa0;
            text-transform: uppercase;
            margin-bottom: 7px;
        }

        .history-info-box .value {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-dark);
            word-break: break-word;
        }

        .history-block {
            margin-top: 16px;
            background: #f8fbfd;
            border-radius: 20px;
            padding: 18px;
            border: 1px solid rgba(15, 23, 42, 0.06);
        }

        .history-block h5 {
            margin: 0 0 12px;
            font-size: 18px;
            font-weight: 800;
        }

        .history-text {
            margin: 0;
            color: var(--text-dark);
            line-height: 1.85;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .places-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .place-chip {
            background: #fff;
            border: 1px solid rgba(20,132,180,0.14);
            color: var(--theme-dark);
            border-radius: 999px;
            padding: 9px 14px;
            font-size: 13px;
            font-weight: 700;
        }

        .trip-actions {
            margin-top: 18px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }

        .delete-trip-btn {
            border: none;
            border-radius: 999px;
            padding: 11px 18px;
            background: #fee2e2;
            color: #b91c1c;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .delete-trip-btn:hover {
            background: #fecaca;
        }

        .download-trip-btn {
            border: none;
            border-radius: 999px;
            padding: 11px 18px;
            background: var(--theme-soft);
            color: var(--theme-dark);
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .download-trip-btn:hover {
            background: #d9f0fb;
            color: var(--theme-dark);
        }

        .edit-trip-btn {
            border: none;
            border-radius: 999px;
            padding: 11px 18px;
            background: #dcfce7;
            color: #166534;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .edit-trip-btn:hover {
            background: #bbf7d0;
            color: #166534;
        }

        @media (max-width: 1100px) {
            .history-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .history-page-wrap {
                padding: 120px 14px 50px;
            }

            .history-hero {
                padding: 26px 20px;
            }

            .history-hero h1 {
                font-size: 34px;
            }

            .history-summary-grid,
            .history-info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php include '../includes/user_top_navbar.php'; ?>

<div class="history-page-wrap">
    <div class="history-page-inner">
        <section class="history-hero">
            <h1>Your Saved Trips</h1>
            <p>
                Review the trips you've saved with Travelix.
                Hotel bookings are not shown on this page.
            </p>
        </section>

        <div class="history-summary-grid">
            <div class="history-summary-card">
                <div class="icon"><i class="bi bi-map"></i></div>
                <span class="label">Saved Trips</span>
                <div class="value" id="summaryTripsCount">0</div>
            </div>

            <div class="history-summary-card">
                <div class="icon"><i class="bi bi-geo-alt"></i></div>
                <span class="label">Latest Destination</span>
                <div class="value" id="summaryLatestDestination" style="font-size:20px;">-</div>
            </div>
        </div>

        <div class="history-layout">
            <aside class="history-sidebar">
                <div class="history-user-box">
                    <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Profile">
                    <h4><?php echo htmlspecialchars(trim($firstName . ' ' . $lastName) ?: 'Traveler'); ?></h4>
                    <p><?php echo htmlspecialchars($email ?: 'No email available'); ?></p>
                </div>

                <button type="button" class="history-tab-btn">
                    <span><i class="bi bi-map me-2"></i>Saved Trips</span>
                    <span id="sideTripsCount">0</span>
                </button>
            </aside>

            <section class="history-content-card">
                <div class="history-content-head">
                    <div>
                        <h2>Saved Trips</h2>
                        <p>View, edit, and delete your saved trips.</p>
                    </div>

                    <button type="button" class="history-refresh-btn" id="refreshHistoryBtn">
                        <i class="bi bi-arrow-repeat"></i>
                        Refresh Trips
                    </button>
                </div>

                <div class="history-loader" id="savedTripsLoader">
                    <div class="spinner-border text-info" role="status"></div>
                    <span>Loading your saved trips...</span>
                </div>

                <div class="history-list" id="savedTripsList"></div>
            </section>
        </div>
    </div>
</div>

<?php include '../includes/user_bottom_footer.php'; ?>

<script type="module">
import { firebaseConfig } from "../config/firebase-config.js";

import { initializeApp, getApp, getApps } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-app.js";

import {
    getAuth,
    onAuthStateChanged
} from "https://www.gstatic.com/firebasejs/10.12.5/firebase-auth.js";

import {
    getFirestore,
    collection,
    query,
    where,
    getDocs,
    deleteDoc,
    doc
} from "https://www.gstatic.com/firebasejs/10.12.5/firebase-firestore.js";

const app = getApps().length ? getApp() : initializeApp(firebaseConfig);
const auth = getAuth(app);
const db = getFirestore(app);

const summaryTripsCount = document.getElementById('summaryTripsCount');
const summaryLatestDestination = document.getElementById('summaryLatestDestination');
const sideTripsCount = document.getElementById('sideTripsCount');
const savedTripsList = document.getElementById('savedTripsList');
const savedTripsLoader = document.getElementById('savedTripsLoader');
const refreshHistoryBtn = document.getElementById('refreshHistoryBtn');

let cachedTrips = [];

function waitForFirebaseAuth() {
    return new Promise((resolve) => {
        onAuthStateChanged(auth, (user) => {
            resolve(user || null);
        });
    });
}

function escapeHtml(value = '') {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
}

function formatTimestamp(ts) {
    try {
        if (!ts) return 'Date not available';

        if (typeof ts.toDate === 'function') {
            return ts.toDate().toLocaleString();
        }

        if (ts.seconds) {
            return new Date(ts.seconds * 1000).toLocaleString();
        }

        return new Date(ts).toLocaleString();
    } catch (error) {
        return 'Date not available';
    }
}

function showEmptyState() {
    savedTripsList.innerHTML = `
        <div class="history-empty">
            <i class="bi bi-map"></i>
            <h4>No saved trips yet</h4>
            <p>Your saved AI trip plans will appear here after you save them from Create Trip.</p>
        </div>
    `;
}

function sortByCreatedAtDesc(items) {
    return [...items].sort((a, b) => {
        const aTime = a?.createdAt?.seconds || 0;
        const bTime = b?.createdAt?.seconds || 0;
        return bTime - aTime;
    });
}

function dedupeTrips(trips) {
    const map = new Map();
    trips.forEach((trip) => {
        if (trip.id) {
            map.set(trip.id, trip);
        }
    });
    return Array.from(map.values());
}

function getBookedHotelName(trip) {
    if (trip.bookedHotel?.hotelName) return trip.bookedHotel.hotelName;
    if (trip.bookedHotelName) return trip.bookedHotelName;
    if (trip.hotelOption === 'No') return 'No hotel selected';
    return '-';
}

function renderSavedTrips(trips) {
    savedTripsLoader.style.display = 'none';
    savedTripsList.innerHTML = '';

    summaryTripsCount.textContent = trips.length;
    sideTripsCount.textContent = trips.length;
    summaryLatestDestination.textContent = trips[0]?.destination || trips[0]?.toCity || '-';

    if (!Array.isArray(trips) || !trips.length) {
        showEmptyState();
        return;
    }

    trips.forEach((trip) => {
        const selectedPlaces = Array.isArray(trip.selectedPlaces) ? trip.selectedPlaces : [];
        const bookedHotelName = getBookedHotelName(trip);
        const bookedHotel = trip.bookedHotel || null;

        // bookedHotel.status/refundAmount/refundStatus are kept current by
        // hotel/manage_bookings.php and hotel_portal/hotel_bookings.php,
        // which push updates here whenever the linked hotel_bookings status
        // changes (confirm / cancel / reject) — see syncLinkedTripBookingStatus.
        const bookingStatus = String(bookedHotel?.status || 'pending').toLowerCase();
        const statusBadgeMap = {
            pending: { text: 'Awaiting Hotel Verification', style: 'background:#fef9c3;color:#854d0e;' },
            confirmed: { text: 'Confirmed', style: 'background:#dcfce7;color:#166534;' },
            cancelled: { text: 'Cancelled', style: 'background:#fee2e2;color:#b91c1c;' }
        };
        const statusBadge = statusBadgeMap[bookingStatus] || statusBadgeMap.pending;

        const refundStatus = String(bookedHotel?.refundStatus || '').toLowerCase();
        const refundAmount = Number(bookedHotel?.refundAmount || 0);
        let refundHtml = '';
        if (bookingStatus === 'cancelled' && refundAmount > 0) {
            const refundProofUrl = bookedHotel?.refundProofUrl || '';
            const proofLink = refundProofUrl
                ? ` <a href="${escapeHtml(refundProofUrl)}" target="_blank" style="color:#1484B4;font-weight:700;text-decoration:underline;">View Proof</a>`
                : '';
            refundHtml = refundStatus === 'sent'
                ? `<div style="margin-top:10px;font-size:12.5px;font-weight:700;color:#166534;"><i class="bi bi-check-circle-fill"></i> Refund sent · PKR ${refundAmount.toLocaleString()}${proofLink}</div>`
                : `<div style="margin-top:10px;font-size:12.5px;font-weight:700;color:#92400e;"><i class="bi bi-hourglass-split"></i> Refund pending (admin) · PKR ${refundAmount.toLocaleString()}</div>`;
        }

        const hotelDetailsHtml = bookedHotel ? `
            <div class="history-block">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
                    <h5 style="margin:0;">Hotel Booking Details</h5>
                    <span style="display:inline-flex;align-items:center;padding:6px 12px;border-radius:999px;font-size:11.5px;font-weight:800;${statusBadge.style}">${statusBadge.text}</span>
                </div>
                <div class="history-info-grid">
                    <div class="history-info-box">
                        <span class="label">Hotel Name</span>
                        <div class="value">${escapeHtml(bookedHotel.hotelName || '-')}</div>
                    </div>
                    <div class="history-info-box">
                        <span class="label">Room Type</span>
                        <div class="value">${escapeHtml(bookedHotel.roomType || '-')}</div>
                    </div>
                    <div class="history-info-box">
                        <span class="label">Total Paid</span>
                        <div class="value">${(bookedHotel.totalCharged || bookedHotel.hotelPrice) ? 'PKR ' + Number(bookedHotel.totalCharged || bookedHotel.hotelPrice).toLocaleString() : '-'}</div>
                    </div>
                    <div class="history-info-box">
                        <span class="label">Check-in → Check-out</span>
                        <div class="value">${escapeHtml(bookedHotel.arrivalDate || '-')} → ${escapeHtml(bookedHotel.departureDate || '-')}</div>
                    </div>
                </div>
                ${refundHtml}
            </div>
        ` : '';

        const placesHtml = selectedPlaces.length
            ? selectedPlaces.map((place, index) => {
                return `<span class="place-chip">${index + 1}. ${escapeHtml(place?.name || 'Place')}</span>`;
            }).join('')
            : `<span class="place-chip">No places selected</span>`;

        const card = document.createElement('div');
        card.className = 'history-item-card';

        card.innerHTML = `
            <div class="history-item-top">
                <div>
                    <h3 class="history-item-title">${escapeHtml(trip.destination || trip.toCity || 'Saved Trip')}</h3>
                    <p class="history-item-subtitle">Saved on ${escapeHtml(formatTimestamp(trip.createdAt))}</p>
                </div>

                <span class="status-pill">
                    <i class="bi bi-check-circle-fill"></i>
                    Saved Trip
                </span>
            </div>

            <div class="history-info-grid">
                <div class="history-info-box">
                    <span class="label">Dates</span>
                    <div class="value">${escapeHtml(trip.arrivalDate || '-')} → ${escapeHtml(trip.departureDate || '-')}</div>
                </div>

                <div class="history-info-box">
                    <span class="label">Travellers</span>
                    <div class="value">${escapeHtml(trip.travelers || '-')}</div>
                </div>

                <div class="history-info-box">
                    <span class="label">Budget</span>
                    <div class="value">${escapeHtml(trip.budget || '-')}</div>
                </div>

                <div class="history-info-box">
                    <span class="label">Private Car</span>
                    <div class="value">${escapeHtml(trip.privateCar || '-')}</div>
                </div>

                <div class="history-info-box">
                    <span class="label">Booked Hotel</span>
                    <div class="value">${escapeHtml(bookedHotelName)}</div>
                </div>

                <div class="history-info-box">
                    <span class="label">Estimated Budget</span>
                    <div class="value">${escapeHtml(trip.estimatedBudget || '-')}</div>
                </div>
            </div>

            <div class="history-block">
                <h5>Selected Places</h5>
                <div class="places-chips">${placesHtml}</div>
            </div>

            ${hotelDetailsHtml}

            <div class="history-block">
                <h5>Trip Notes</h5>
                <p class="history-text">${escapeHtml(trip.tripNotes || 'No trip notes added.')}</p>
            </div>

            <div class="trip-actions">
                <a href="/travelix/trips/create_trip.php?edit=${encodeURIComponent(trip.id)}" class="edit-trip-btn">
                    <i class="bi bi-pencil-square"></i>
                    Edit Trip
                </a>
                <a href="/travelix/trips/ajax/download_trip_pdf.php?id=${encodeURIComponent(trip.id)}" target="_blank" class="download-trip-btn">
                    <i class="bi bi-file-earmark-pdf"></i>
                    Download Report
                </a>
                <button type="button" class="delete-trip-btn" data-id="${escapeHtml(trip.id)}">
                    <i class="bi bi-trash"></i>
                    Delete Trip
                </button>
            </div>
        `;

        savedTripsList.appendChild(card);
    });

    bindDeleteTripButtons();
}

async function loadTrips() {
    savedTripsLoader.style.display = 'flex';
    savedTripsList.innerHTML = '';

    try {
        const authUser = await waitForFirebaseAuth();

        if (!authUser) {
            savedTripsLoader.style.display = 'none';
            savedTripsList.innerHTML = `
                <div class="history-empty">
                    <i class="bi bi-exclamation-circle"></i>
                    <h4>Firebase login not found</h4>
                    <p>Please log out and log in again so Firebase Auth can load your saved trips.</p>
                </div>
            `;
            return;
        }

        const tripsRef = collection(db, 'trips');

        const q1 = query(tripsRef, where('userId', '==', authUser.uid));
        const q2 = query(tripsRef, where('uid', '==', authUser.uid));

        const [snapshot1, snapshot2] = await Promise.all([
            getDocs(q1),
            getDocs(q2)
        ]);

        const trips = [];

        snapshot1.forEach((docSnap) => {
            trips.push({
                id: docSnap.id,
                ...docSnap.data()
            });
        });

        snapshot2.forEach((docSnap) => {
            trips.push({
                id: docSnap.id,
                ...docSnap.data()
            });
        });

        cachedTrips = sortByCreatedAtDesc(dedupeTrips(trips));
        renderSavedTrips(cachedTrips);

    } catch (error) {
        console.error('Trips load error:', error);

        savedTripsLoader.style.display = 'none';

        Swal.fire({
            icon: 'error',
            title: 'Load Failed',
            text: error?.message || 'Could not load your saved trips right now.',
            confirmButtonColor: '#1484B4'
        });
    }
}

function bindDeleteTripButtons() {
    document.querySelectorAll('.delete-trip-btn').forEach((button) => {
        button.addEventListener('click', async function () {
            const tripId = this.dataset.id || '';

            if (!tripId) return;

            const result = await Swal.fire({
                icon: 'warning',
                title: 'Delete this trip?',
                text: 'This saved trip will be permanently removed.',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#d33'
            });

            if (!result.isConfirmed) return;

            try {
                Swal.fire({
                    title: 'Deleting trip...',
                    text: 'Please wait.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading()
                });

                await deleteDoc(doc(db, 'trips', tripId));

                try {
                    await window.travelixAddNotification?.({
                        title: "Saved Trip Deleted",
                        message: "Your saved trip has been deleted successfully.",
                        type: "saved_trips",
                        link: "/travelix/manage_bookings/history.php"
                    });
                } catch (notificationError) {
                    console.warn("Notification not added:", notificationError);
                }

                await Swal.fire({
                    icon: 'success',
                    title: 'Trip Deleted',
                    text: 'Your saved trip has been deleted successfully.',
                    confirmButtonColor: '#1484B4'
                });

                await loadTrips();

            } catch (error) {
                console.error('Delete trip error:', error);

                Swal.fire({
                    icon: 'error',
                    title: 'Delete Failed',
                    text: error?.message || 'Could not delete this trip.',
                    confirmButtonColor: '#1484B4'
                });
            }
        });
    });
}

refreshHistoryBtn?.addEventListener('click', async () => {
    Swal.fire({
        title: 'Refreshing trips...',
        text: 'Please wait while we load your latest saved trips.',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    await loadTrips();
    Swal.close();
});

document.addEventListener('DOMContentLoaded', loadTrips);
</script>

</body>
</html>