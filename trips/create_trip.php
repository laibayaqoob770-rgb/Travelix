<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$baseUrl = '/travelix';

if (!isset($_SESSION['user']) || empty($_SESSION['user']['uid'])) {
    header('Location: ' . $baseUrl . '/auth/login.php');
    exit;
}

$currentUserUid = (string)($_SESSION['user']['uid'] ?? '');
$currentUserEmail = (string)($_SESSION['user']['email'] ?? '');
$editTripId = trim((string)($_GET['edit'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Trip | Travelix</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="/travelix/assets/vendor/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/vendor/leaflet/leaflet.css">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/trips/assets/create_trip.css">
</head>

<body
    data-user-id="<?php echo htmlspecialchars($currentUserUid, ENT_QUOTES, 'UTF-8'); ?>"
    data-user-email="<?php echo htmlspecialchars($currentUserEmail, ENT_QUOTES, 'UTF-8'); ?>"
>

<div class="hero-navbar-wrap">
    <?php include '../includes/user_top_navbar.php'; ?>
</div>

<section class="trip-hero">
    <div class="trip-hero-overlay"></div>
    <div class="trip-hero-content">
        <span class="trip-hero-badge">Travelix Planner</span>
        <h1><?= $editTripId !== '' ? 'Edit Your Trip' : 'Create Your Smart Trip' ?></h1>
        <p>
            <?php if ($editTripId !== ''): ?>
                Update your destination, dates, travel preferences, or selected places below.
            <?php else: ?>
                Plan your destination step by step with major places, dates, travel preferences,
                weather insights, and route support.
            <?php endif; ?>
        </p>
    </div>
</section>

<section class="create-trip-main-section">
    <div class="container-fluid px-xl-4 px-lg-3 px-2">
        <div class="trip-layout-card">
            <div class="row g-0">

                <div class="col-xl-6 col-lg-6">
                    <div class="left-panel">
                        <?php include 'partials/trip_stepper.php'; ?>

                        <form id="tripWizardForm" novalidate>
                            <?php include 'partials/trip_step_1_destination.php'; ?>
                            <?php include 'partials/trip_step_2_dates.php'; ?>
                            <?php include 'partials/trip_step_3_preferences.php'; ?>
                            <?php include 'partials/trip_step_4_review.php'; ?>
                        </form>
                    </div>
                </div>

                <div class="col-xl-6 col-lg-6">
                    <div class="right-panel">
                        <div class="map-panel-top">
                            <div class="map-panel-title-wrap">
                                <h3 class="map-panel-title">Trip Map</h3>
                                <p class="map-panel-subtitle">
                                    Your selected destination and places will appear here.
                                </p>
                            </div>
                        </div>

                        <div class="map-wrapper">
                            <div id="tripMap"></div>
                        </div>

                        <div class="map-selected-summary" id="mapSelectedSummary">
                            <div class="map-selected-summary-title">Selected Places</div>
                            <div class="map-selected-summary-text">
                                No places selected yet.
                            </div>
                        </div>

                        <div class="map-preview-below-wrap">
                            <div class="section-mini-header map-preview-header">
                                <h4>Map Place Preview</h4>
                                <p>Hover or click a numbered marker to see full details for that place.</p>
                            </div>

                            <div id="mapPlacePreview" class="map-place-preview hidden" aria-live="polite">
                                <button
                                    type="button"
                                    id="closeMapPreview"
                                    class="map-place-preview-close"
                                    aria-label="Close place preview"
                                >
                                    &times;
                                </button>

                                <div id="mapPlacePreviewContent">
                                    <div class="empty-state-box compact">
                                        <div class="empty-state-icon">🗺️</div>
                                        <h5>No place selected on map</h5>
                                        <p>
                                            Hover or click a numbered marker on the map to see its image, description,
                                            reasons to visit, and travel tips.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<?php include '../includes/user_bottom_footer.php'; ?>

<script src="<?php echo $baseUrl; ?>/assets/vendor/leaflet/leaflet.js"></script>
<script src="<?php echo $baseUrl; ?>/assets/js/travelix_autocomplete.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
function showTravelixLoader(title = 'Loading...', text = 'Please wait...') {
    Swal.fire({
        title: title,
        text: text,
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });
}

function showTravelixSuccess(title = 'Success', text = '') {
    return Swal.fire({
        icon: 'success',
        title: title,
        text: text,
        confirmButtonColor: '#1484B4'
    });
}

function showTravelixError(title = 'Oops...', text = '') {
    return Swal.fire({
        icon: 'error',
        title: title,
        text: text,
        confirmButtonColor: '#1484B4'
    });
}

function showTravelixInfo(title = 'Info', text = '') {
    return Swal.fire({
        icon: 'info',
        title: title,
        text: text,
        confirmButtonColor: '#1484B4'
    });
}

window.cityPlaces = {};
</script>

<script type="module" src="<?php echo $baseUrl; ?>/trips/assets/create_trip.js"></script>

<script type="module">
import { firebaseConfig } from "<?php echo $baseUrl; ?>/config/firebase-config.js";

import { initializeApp, getApp, getApps } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-app.js";

import {
    getAuth,
    onAuthStateChanged
} from "https://www.gstatic.com/firebasejs/10.12.5/firebase-auth.js";

import {
    getFirestore,
    collection,
    addDoc,
    doc,
    getDoc,
    updateDoc,
    serverTimestamp
} from "https://www.gstatic.com/firebasejs/10.12.5/firebase-firestore.js";

const app = getApps().length ? getApp() : initializeApp(firebaseConfig);
const auth = getAuth(app);
const db = getFirestore(app);

window.travelixEditTripId = <?php echo json_encode($editTripId); ?>;

// When editing a saved trip, fetch it and hand back a draft object matching
// create_trip.js's own draft shape, so the normal loadDraft() flow (which
// every step already reads from) pre-fills the wizard with it — called once,
// before create_trip.js's DOMContentLoaded handler restores the draft.
window.travelixFetchEditTripDraft = async function () {
    if (!window.travelixEditTripId) return null;

    try {
        const authUser = await waitForFirebaseAuth();
        if (!authUser) return null;

        const tripSnap = await getDoc(doc(db, "trips", window.travelixEditTripId));
        if (!tripSnap.exists()) return null;

        const trip = tripSnap.data();
        if (trip.uid !== authUser.uid) return null;

        return {
            toCity: trip.destination || trip.toCity || "",
            arrivalDate: trip.arrivalDate || "",
            departureDate: trip.departureDate || "",
            travelers: trip.travelers || "",
            budget: trip.budget || "",
            privateCar: trip.privateCar || "",
            hotelOption: trip.hotelOption || "",
            tripNotes: trip.tripNotes || "",
            selectedPlaces: Array.isArray(trip.selectedPlaces) ? trip.selectedPlaces : [],
            latestWeatherSummary: trip.weatherSummary || ""
        };
    } catch (e) {
        console.warn("Could not load trip for editing:", e);
        return null;
    }
};

const form = document.getElementById("tripWizardForm");
const saveTripBtn = document.getElementById("saveTripBtn");

if (saveTripBtn) {
    saveTripBtn.type = "button";
}

function waitForFirebaseAuth() {
    return new Promise((resolve) => {
        onAuthStateChanged(auth, (user) => {
            resolve(user || null);
        });
    });
}

// The PHP session's uid is only set once, at the login.php form submission.
// If Firebase Auth's own persisted session outlives that PHP session (e.g. a
// fresh browser session on a device where Firebase was already signed in),
// $_SESSION['user']['uid'] can go stale while authUser.uid is still current.
// Re-sync it here so the server-side ownership check on the PDF download
// endpoint (which trusts $_SESSION, not the client) matches the uid the trip
// is about to be saved under.
async function resyncPhpSession(authUser) {
    try {
        const userSnap = await getDoc(doc(db, "users", authUser.uid));
        const userData = userSnap.exists() ? userSnap.data() : {};

        const formData = new FormData();
        formData.append("action", "set_login_session");
        formData.append("uid", authUser.uid);
        formData.append("first_name", userData.firstName || "User");
        formData.append("last_name", userData.lastName || "");
        formData.append("email", userData.email || authUser.email || "");
        formData.append("profile_image", userData.profileImage || "<?php echo $baseUrl; ?>/images/default_profile.png");
        formData.append("role", userData.role || "user");

        await fetch("<?php echo $baseUrl; ?>/auth/login.php", {
            method: "POST",
            body: formData,
            credentials: "same-origin"
        });
    } catch (e) {
        // Best-effort — if this fails, the existing session is used as-is.
    }
}

function valueOf(id) {
    return document.getElementById(id)?.value?.trim() || "";
}

function textOf(id) {
    return document.getElementById(id)?.innerText?.trim() || "";
}

// Mirrors create_trip.js's getPlaceImagePath() so the saved trip stores a
// ready-to-use site path instead of a bare filename.
function resolvePlaceImagePath(place) {
    const imageName = String(place?.image || "").trim();
    const imageSource = String(place?.image_source || "").trim().toLowerCase();

    if (!imageName) return "";
    if (imageSource === "admin") return `/travelix/location_images/${imageName}`;
    if (imageName.startsWith("/")) return imageName;
    return `/travelix/images/${imageName}`;
}

function joinIfArray(value) {
    return Array.isArray(value) ? value.filter(Boolean).join("; ") : (value || "");
}

// Reads the real in-memory place objects (window.selectedPlaces, kept in
// sync by create_trip.js's setSelectedPlaces()) instead of scraping the
// rendered cards, so image/lat/lng/description actually make it into the
// saved trip rather than always saving as empty strings.
function getSelectedPlaces() {
    const source = Array.isArray(window.selectedPlaces) ? window.selectedPlaces : [];

    return source.filter((place) => place && place.name).map((place, index) => ({
        order: index + 1,
        name: place.name,
        lat: place.lat ?? "",
        lng: place.lng ?? "",
        image: resolvePlaceImagePath(place),
        description: place.description || "",
        why_go: joinIfArray(place.why_go),
        know_before_you_go: joinIfArray(place.know_before_you_go)
    }));
}

async function saveTripNow(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
    }

    try {
        const authUser = await waitForFirebaseAuth();

        if (!authUser) {
            await showTravelixError(
                "Firebase Login Missing",
                "Please log out and log in again before saving your trip."
            );
            return;
        }

        await resyncPhpSession(authUser);

        const destination = valueOf("toCity");
        const arrivalDate = valueOf("arrivalDate");
        const departureDate = valueOf("departureDate");
        const travelers = valueOf("travelers");
        const budget = valueOf("budget");
        const privateCar = valueOf("privateCar");
        const hotelOption = valueOf("hotelOption");
        const tripNotes = valueOf("tripNotes");

        const selectedPlaces = getSelectedPlaces();

        if (!destination) {
            await showTravelixInfo("Destination Required", "Please select your destination city.");
            return;
        }

        if (!arrivalDate || !departureDate) {
            await showTravelixInfo("Dates Required", "Please select arrival and departure dates.");
            return;
        }

        if (!travelers || Number(travelers) < 1) {
            await showTravelixInfo("Travellers Required", "Please enter number of travellers.");
            return;
        }

        if (!budget) {
            await showTravelixInfo("Budget Required", "Please select your budget.");
            return;
        }

        if (!privateCar) {
            await showTravelixInfo("Private Car Required", "Please select private car option.");
            return;
        }

        if (!hotelOption) {
            await showTravelixInfo("Hotel Option Required", "Please select hotel option.");
            return;
        }

        if (saveTripBtn) {
            saveTripBtn.disabled = true;
        }

        showTravelixLoader("Saving trip...", "Please wait while we save your trip.");

        const tripData = {
            uid: authUser.uid,
            userId: authUser.uid,
            userEmail: authUser.email || "<?php echo htmlspecialchars($currentUserEmail, ENT_QUOTES, 'UTF-8'); ?>",

            destination: destination,
            toCity: destination,
            arrivalDate: arrivalDate,
            departureDate: departureDate,
            travelers: Number(travelers),
            budget: budget,
            privateCar: privateCar,
            hotelOption: hotelOption,

            selectedPlaces: selectedPlaces,
            tripNotes: tripNotes,

            suggestedHotel: textOf("suggestedHotel"),
            suggestedTransport: textOf("suggestedTransport"),
            suggestedMinBudget: textOf("suggestedMinBudget"),
            estimatedBudget: textOf("reviewEstimatedBudget"),

            weatherSummary: window.latestWeatherSummary || "",
            status: "saved",

            createdAt: serverTimestamp(),
            updatedAt: serverTimestamp()
        };

        try {
            const storedBooking = localStorage.getItem("travelix_hotel_booking");
            if (storedBooking) {
                const bookedHotel = JSON.parse(storedBooking);
                tripData.bookedHotel = bookedHotel;
                tripData.hotelBookingId = bookedHotel.id || "";
            }
        } catch (e) {}

        const isEditing = !!window.travelixEditTripId;
        let docRef;

        if (isEditing) {
            delete tripData.createdAt; // preserve the original createdAt — updateDoc only touches given fields
            await updateDoc(doc(db, "trips", window.travelixEditTripId), tripData);
            docRef = { id: window.travelixEditTripId };
        } else {
            docRef = await addDoc(collection(db, "trips"), tripData);
        }

        await window.travelixAddNotification?.({
            title: isEditing ? "Trip Updated" : "Trip Saved",
            message: `Your ${destination} trip has been ${isEditing ? "updated" : "saved"} successfully.`,
            type: "trip_saved",
            link: "<?php echo $baseUrl; ?>/manage_bookings/history.php"
        });

        localStorage.removeItem("travelix_trip_draft_v3");
        localStorage.removeItem("travelix_return_to_step");
        localStorage.removeItem("travelix_step3_data");
        localStorage.removeItem("travelix_hotel_booking");

        // Email a PDF copy of the itinerary — best-effort, doesn't block the save flow.
        fetch("<?php echo $baseUrl; ?>/trips/ajax/email_trip_pdf.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify({ tripId: docRef.id })
        }).catch(() => {});

        const result = await Swal.fire({
            icon: "success",
            title: isEditing ? "Trip Updated" : "Trip Saved",
            text: isEditing
                ? "Your trip has been updated successfully. An updated PDF copy has also been emailed to you."
                : "Your trip has been saved successfully. A PDF copy has also been emailed to you.",
            showDenyButton: true,
            confirmButtonText: "Go to Dashboard",
            denyButtonText: "Download PDF",
            confirmButtonColor: "#1484B4",
            denyButtonColor: "#059669"
        });

        if (result.isDenied) {
            window.open("<?php echo $baseUrl; ?>/trips/ajax/download_trip_pdf.php?id=" + docRef.id, "_blank");
        }

        window.location.href = "<?php echo $baseUrl; ?>/dashboard/user_dashboard.php";

    } catch (error) {
        console.error("Save Trip Error:", error);

        await showTravelixError(
            "Save Failed",
            error?.message || "Unable to save trip right now. Please try again."
        );
    } finally {
        if (saveTripBtn) {
            saveTripBtn.disabled = false;
        }
    }
}

saveTripBtn?.addEventListener("click", saveTripNow, true);

form?.addEventListener("submit", saveTripNow, true);
</script>

</body>
</html>