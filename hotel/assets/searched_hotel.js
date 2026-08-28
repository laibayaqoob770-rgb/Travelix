const searchedHotelsPayload = window.searchedHotelsData || {};
const searchedHotels = Array.isArray(searchedHotelsPayload.hotels) ? searchedHotelsPayload.hotels : [];
const cityCenter = searchedHotelsPayload.center || { lat: 33.6844, lng: 73.0479 };
const isLoggedIn = Boolean(searchedHotelsPayload.isLoggedIn);
const loginUrl = searchedHotelsPayload.loginUrl || '/travelix/auth/login.php';

let hotelMap = null;
let hotelMarkers = [];
let highlightedIndex = null;

function createHotelPriceIcon(price, active = false) {
    return L.divIcon({
        className: 'hotel-price-marker-shell',
        html: `<div class="hotel-price-marker ${active ? 'active' : ''}">PKR ${Number(price || 0).toLocaleString()}</div>`,
        iconSize: [118, 38],
        iconAnchor: [59, 19],
        popupAnchor: [0, -16]
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

function getHotelLat(hotel) {
    return Number(hotel?.lat ?? 0);
}

function getHotelLng(hotel) {
    return Number(hotel?.lng ?? 0);
}

function getHotelPricePerNight(hotel) {
    return Number(hotel?.price_per_night ?? 0);
}

function getHotelTotalPrice(hotel) {
    return Number(hotel?.total_price ?? 0);
}

function getHotelName(hotel) {
    return String(hotel?.name ?? 'Hotel');
}

function getHotelAddress(hotel) {
    return String(hotel?.address ?? '');
}

function fitAllHotels() {
    if (!hotelMap || !hotelMarkers.length) return;

    const group = L.featureGroup(hotelMarkers);
    const bounds = group.getBounds();

    if (bounds.isValid()) {
        hotelMap.fitBounds(bounds.pad(0.22));
    }
}

function setActiveHotel(index, openPopup = false) {
    if (!hotelMarkers.length) return;

    highlightedIndex = index;

    hotelMarkers.forEach((marker, i) => {
        const hotel = searchedHotels[i] || {};
        marker.setIcon(createHotelPriceIcon(getHotelPricePerNight(hotel), i === index));

        if (i === index && openPopup) {
            marker.openPopup();
        }
    });

    document.querySelectorAll('.hotel-result-card').forEach((card, i) => {
        card.classList.toggle('active', i === index);
    });

    const selectedHotel = searchedHotels[index];
    const lat = getHotelLat(selectedHotel);
    const lng = getHotelLng(selectedHotel);

    if (selectedHotel && hotelMap && !Number.isNaN(lat) && !Number.isNaN(lng)) {
        hotelMap.setView([lat, lng], 13, { animate: true });
    }
}

function initSearchedHotelMap() {
    const mapElement = document.getElementById('searchedHotelMap');
    if (!mapElement || typeof L === 'undefined') return;

    hotelMap = L.map('searchedHotelMap', {
        zoomControl: true
    }).setView(
        [Number(cityCenter.lat || 33.6844), Number(cityCenter.lng || 73.0479)],
        searchedHotels.length ? 12 : 10
    );

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(hotelMap);

    if (!searchedHotels.length) return;

    searchedHotels.forEach((hotel, index) => {
        const lat = getHotelLat(hotel);
        const lng = getHotelLng(hotel);

        if (Number.isNaN(lat) || Number.isNaN(lng) || lat === 0 || lng === 0) return;

        const marker = L.marker([lat, lng], {
            icon: createHotelPriceIcon(getHotelPricePerNight(hotel), false)
        }).addTo(hotelMap);

        marker.bindPopup(`
            <div class="hotel-map-popup">
                <h5>${escapeHtml(getHotelName(hotel))}</h5>
                <p>${escapeHtml(getHotelAddress(hotel))}</p>
                <p><strong>PKR ${getHotelTotalPrice(hotel).toLocaleString()}</strong> total</p>
            </div>
        `);

        marker.on('click', () => {
            setActiveHotel(index, true);
        });

        hotelMarkers.push(marker);
    });

    if (hotelMarkers.length) {
        fitAllHotels();
    }
}

function bindHotelCards() {
    document.querySelectorAll('.hotel-result-card').forEach((card, index) => {
        card.addEventListener('click', function (e) {
            if (e.target.closest('.view-deal-btn')) return;
            setActiveHotel(index, true);
        });
    });
}

function buildBookingUrl(button) {
    const params = new URLSearchParams({
        hotelId: button.dataset.hotelId || '',
        hotelName: button.dataset.hotelName || '',
        hotelPrice: button.dataset.hotelPrice || '',
        hotelPriceNight: button.dataset.hotelPriceNight || '',
        hotelImage: button.dataset.hotelImage || '',
        hotelAddress: button.dataset.hotelAddress || '',
        hotelRating: button.dataset.hotelRating || '',
        hotelReviews: button.dataset.hotelReviews || '',
        hotelStars: button.dataset.hotelStars || '',
        toCity: searchedHotelsPayload.city || '',
        arrivalDate: searchedHotelsPayload.arrivalDate || '',
        departureDate: searchedHotelsPayload.departureDate || '',
        rooms: searchedHotelsPayload.rooms || 1,
        adults: searchedHotelsPayload.adults || 1,
        children: searchedHotelsPayload.children || 0,
        nights: searchedHotelsPayload.nights || 1,
        source: searchedHotelsPayload.source || '',
        returnStep: searchedHotelsPayload.returnStep || 3,
    });

    return `/travelix/hotel/book_hotel.php?${params.toString()}`;
}

function redirectToLogin() {
    const currentUrl = window.location.pathname + window.location.search;
    const target = `${loginUrl}?redirect=${encodeURIComponent(currentUrl)}`;

    Swal.fire({
        title: 'Redirecting to login...',
        text: 'Please wait while we take you to the login page.',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    setTimeout(() => {
        window.location.href = target;
    }, 900);
}

function bindBookNowButtons() {
    document.querySelectorAll('.view-deal-btn').forEach((button, index) => {
        button.addEventListener('click', async function (e) {
            e.stopPropagation();

            if (!isLoggedIn) {
                const result = await Swal.fire({
                    icon: 'warning',
                    title: 'Login Required',
                    text: 'Please log in first to book a hotel.',
                    confirmButtonText: 'Go to Login',
                    showCancelButton: true,
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#1484B4'
                });

                if (result.isConfirmed) {
                    redirectToLogin();
                }
                return;
            }

            setActiveHotel(index, true);

            const bookingUrl = buildBookingUrl(this);

            Swal.fire({
                title: 'Opening booking page...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            setTimeout(() => {
                window.location.href = bookingUrl;
            }, 700);
        });
    });
}

function bindFitHotelsButton() {
    const fitHotelsBtn = document.getElementById('fitHotelsBtn');
    if (!fitHotelsBtn) return;

    fitHotelsBtn.addEventListener('click', function () {
        Swal.fire({
            title: 'Updating map...',
            text: 'Showing all hotels in this area.',
            timer: 700,
            timerProgressBar: true,
            showConfirmButton: false,
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
                fitAllHotels();
            }
        });
    });
}

function applyHotelFilters() {
    const priceMinInput = document.getElementById('priceRangeMin');
    const priceMaxInput = document.getElementById('priceRangeMax');
    const ratingInput = document.getElementById('ratingRangeMin');
    if (!priceMinInput || !priceMaxInput) return;

    let priceMin = Number(priceMinInput.value);
    let priceMax = Number(priceMaxInput.value);
    if (priceMin > priceMax) {
        [priceMin, priceMax] = [priceMax, priceMin];
    }
    const ratingMin = Number(ratingInput?.value || 0);

    const priceLabel = document.getElementById('priceRangeLabel');
    if (priceLabel) {
        priceLabel.textContent = `PKR ${priceMin.toLocaleString()} - PKR ${priceMax.toLocaleString()}`;
    }
    const ratingLabel = document.getElementById('ratingRangeLabel');
    if (ratingLabel) {
        ratingLabel.textContent = ratingMin > 0 ? `${ratingMin}+` : 'Any';
    }

    const cards = document.querySelectorAll('.hotel-result-card');
    let visibleCount = 0;

    cards.forEach((card) => {
        const price = Number(card.dataset.price || 0);
        const rating = Number(card.dataset.rating || 0);
        const matches = price >= priceMin && price <= priceMax && rating >= ratingMin;
        card.style.display = matches ? '' : 'none';
        if (matches) visibleCount++;
    });

    let emptyBox = document.getElementById('noFilterMatchBox');
    const resultsList = document.getElementById('hotelResultsList');
    if (visibleCount === 0 && cards.length > 0) {
        if (!emptyBox && resultsList) {
            emptyBox = document.createElement('div');
            emptyBox.id = 'noFilterMatchBox';
            emptyBox.className = 'empty-results-box';
            emptyBox.innerHTML = '<h4>No hotels match these filters</h4><p>Try widening the price range or lowering the minimum rating.</p>';
            resultsList.appendChild(emptyBox);
        }
        if (emptyBox) emptyBox.style.display = '';
    } else if (emptyBox) {
        emptyBox.style.display = 'none';
    }
}

function bindFilterButtons() {
    const priceMinInput = document.getElementById('priceRangeMin');
    const priceMaxInput = document.getElementById('priceRangeMax');
    const ratingInput = document.getElementById('ratingRangeMin');
    const resetBtn = document.getElementById('resetHotelFiltersBtn');

    [priceMinInput, priceMaxInput, ratingInput].forEach((input) => {
        input?.addEventListener('input', applyHotelFilters);
    });

    resetBtn?.addEventListener('click', function () {
        if (priceMinInput) priceMinInput.value = priceMinInput.min;
        if (priceMaxInput) priceMaxInput.value = priceMaxInput.max;
        if (ratingInput) ratingInput.value = 0;
        applyHotelFilters();
    });

    applyHotelFilters();
}

document.addEventListener('DOMContentLoaded', function () {
    initSearchedHotelMap();
    bindHotelCards();
    bindBookNowButtons();
    bindFitHotelsButton();
    bindFilterButtons();

    if (searchedHotels.length && hotelMarkers.length) {
        setActiveHotel(0, false);
    }
});