const storageKey = 'travelix_trip_draft_v3';

let currentStep = 1;
let map = null;
let cityMarker = null;
let selectedPlaceMarkers = [];
let selectedPlaces = [];
window.selectedPlaces = selectedPlaces;

// create_trip.php's (non-module) save-trip script reads window.selectedPlaces
// when building the trip document — module-scoped `let` never reaches `window`
// on its own, so every reassignment of selectedPlaces must go through this
// helper or the saved trip's places silently lose their image/lat/lng/etc.
function setSelectedPlaces(arr) {
    selectedPlaces = Array.isArray(arr) ? arr : [];
    window.selectedPlaces = selectedPlaces;
}

let hoveredMarkerIndex = null;
let draggedPlaceIndex = null;
let latestWeatherSummary = '';

// create_trip.php's (non-module) save-trip script reads window.latestWeatherSummary
// when building the trip document — module-scoped `let` never reaches `window` on
// its own, so every assignment to latestWeatherSummary must go through this helper
// or the saved trip's weatherSummary silently ends up empty.
function setLatestWeatherSummary(value) {
    latestWeatherSummary = value;
    window.latestWeatherSummary = value;
}
let tripDatePicker = null;
let pendingDestinationCity = '';

const toCity = document.getElementById('toCity');
const tripDateRange = document.getElementById('tripDateRange');
const arrivalDate = document.getElementById('arrivalDate');
const departureDate = document.getElementById('departureDate');

const travelers = document.getElementById('travelers');
const budget = document.getElementById('budget');
const privateCar = document.getElementById('privateCar');
const hotelOption = document.getElementById('hotelOption');
const tripNotes = document.getElementById('tripNotes');
const hotelHelperWrap = document.getElementById('hotelHelperWrap');
const addHotelBtn = document.getElementById('addHotelBtn');

const availablePlaces = document.getElementById('availablePlaces');
const selectedPlacesWrap = document.getElementById('selectedPlaces');
const mapSelectedSummary = document.getElementById('mapSelectedSummary');
const mapPlacePreview = document.getElementById('mapPlacePreview');
const mapPlacePreviewContent = document.getElementById('mapPlacePreviewContent');
const closeMapPreview = document.getElementById('closeMapPreview');

const suggestedHotel = document.getElementById('suggestedHotel');
const suggestedTransport = document.getElementById('suggestedTransport');
const suggestedMinBudget = document.getElementById('suggestedMinBudget');

const weatherBoxes = Array.from(document.querySelectorAll('[id="weatherBox"]'));
const weatherDaysGroups = Array.from(document.querySelectorAll('[id="weatherDays"]'));

const saveTripBtn = document.getElementById('saveTripBtn');

const reviewDestination = document.getElementById('reviewDestination');
const reviewDates = document.getElementById('reviewDates');
const reviewTravelers = document.getElementById('reviewTravelers');
const reviewBudget = document.getElementById('reviewBudget');
const reviewBookedHotel = document.getElementById('reviewBookedHotel');
const reviewEstimatedBudget = document.getElementById('reviewEstimatedBudget');

const tripWizardForm = document.getElementById('tripWizardForm');
const stepItems = Array.from(document.querySelectorAll('.step-item'));

function getDraft() {
    try {
        return JSON.parse(localStorage.getItem(storageKey) || '{}');
    } catch (error) {
        return {};
    }
}

function saveDraft() {
    const draft = {
        currentStep,
        toCity: toCity?.value || '',
        arrivalDate: arrivalDate?.value || '',
        departureDate: departureDate?.value || '',
        travelers: travelers?.value || '',
        budget: budget?.value || '',
        privateCar: privateCar?.value || '',
        hotelOption: hotelOption?.value || '',
        tripNotes: tripNotes?.value || '',
        selectedPlaces,
        latestWeatherSummary
    };

    localStorage.setItem(storageKey, JSON.stringify(draft));
}

function loadDraft() {
    const draft = getDraft();

    if (toCity) toCity.value = draft.toCity || '';
    pendingDestinationCity = draft.toCity || '';
    if (arrivalDate) arrivalDate.value = draft.arrivalDate || '';
    if (departureDate) departureDate.value = draft.departureDate || '';
    if (travelers) travelers.value = draft.travelers || '';
    if (budget) budget.value = draft.budget || '';
    if (privateCar) privateCar.value = draft.privateCar || '';
    if (hotelOption) hotelOption.value = draft.hotelOption || '';
    if (tripNotes) tripNotes.value = draft.tripNotes || '';

    setSelectedPlaces(Array.isArray(draft.selectedPlaces) ? draft.selectedPlaces : []);
    setLatestWeatherSummary(draft.latestWeatherSummary || '');
    currentStep = 1;
}

function smoothScrollTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

function showStepLoader(text = 'Please wait...') {
    if (typeof showTravelixLoader === 'function') {
        showTravelixLoader('Loading...', text);
    }
}

function closeStepLoader() {
    if (typeof Swal !== 'undefined') {
        Swal.close();
    }
}

function showError(title, text) {
    if (typeof showTravelixError === 'function') {
        showTravelixError(title, text);
    }
}

function showSuccess(title, text) {
    if (typeof showTravelixSuccess === 'function') {
        return showTravelixSuccess(title, text);
    }

    return Promise.resolve();
}

function formatDateYMD(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function addDays(date, days) {
    const newDate = new Date(date);
    newDate.setDate(newDate.getDate() + days);
    return newDate;
}

async function loadMergedCityPlaces() {
    try {
        const response = await fetch('/travelix/trips/data/get_merged_city_places.php', {
            cache: 'no-store'
        });

        if (!response.ok) {
            throw new Error('Failed to load merged city places.');
        }

        const data = await response.json();
        window.cityPlaces = data || {};
        populateDestinationDropdown();
        restoreSelectedPlacesFromDraft();
    } catch (error) {
        console.error('Error loading merged city places:', error);
        window.cityPlaces = {};
        populateDestinationDropdown();
        restoreSelectedPlacesFromDraft();
    }
}

let toCityAutocomplete = null;

function populateDestinationDropdown() {
    if (!toCity) return;

    if (!toCityAutocomplete && window.travelixAutocomplete) {
        toCityAutocomplete = window.travelixAutocomplete(toCity, {
            getItems: () => Object.keys(window.cityPlaces || {})
                .sort((a, b) => a.localeCompare(b))
                .map((city) => ({ value: city, label: city })),
            emptyText: 'No matching city found'
        });

        if (pendingDestinationCity) {
            toCityAutocomplete.setValue(pendingDestinationCity);
        }
    } else if (toCityAutocomplete) {
        toCityAutocomplete.refresh();
    }

    pendingDestinationCity = '';
}

function restoreSelectedPlacesFromDraft() {
    if (!toCity?.value || !Array.isArray(selectedPlaces) || !selectedPlaces.length) {
        setSelectedPlaces([]);
        return;
    }

    const cityData = window.cityPlaces?.[toCity.value];
    if (!cityData || !Array.isArray(cityData.places)) {
        setSelectedPlaces([]);
        return;
    }

    const normalizedDraftNames = selectedPlaces.map((place) =>
        String(place?.name || '').trim().toLowerCase()
    );

    setSelectedPlaces(cityData.places.filter((place) =>
        normalizedDraftNames.includes(String(place?.name || '').trim().toLowerCase())
    ));
}

function initDateLimits() {
    if (typeof flatpickr === 'undefined' || !tripDateRange || !arrivalDate || !departureDate) {
        return;
    }

    const today = new Date();

    tripDatePicker = flatpickr(tripDateRange, {
        mode: 'range',
        minDate: today,
        dateFormat: 'Y-m-d',
        showMonths: window.innerWidth <= 1200 ? 1 : 2,
        disableMobile: true,
        allowInput: false,
        clickOpens: false,
        static: false,
        position: 'below left',
        positionElement: arrivalDate,
        defaultDate: (
            arrivalDate.value && departureDate.value
                ? [arrivalDate.value, departureDate.value]
                : []
        ),
        onOpen(selectedDates, dateStr, fp) {
            if (fp.selectedDates.length === 1) {
                fp.set('maxDate', addDays(fp.selectedDates[0], 7));
            } else {
                fp.set('minDate', today);
                fp.set('maxDate', null);
            }
        },
        async onChange(selectedDates, dateStr, fp) {
            if (selectedDates.length === 1) {
                arrivalDate.value = formatDateYMD(selectedDates[0]);
                departureDate.value = '';
                fp.set('maxDate', addDays(selectedDates[0], 7));
            } else if (selectedDates.length === 2) {
                arrivalDate.value = formatDateYMD(selectedDates[0]);
                departureDate.value = formatDateYMD(selectedDates[1]);
            }

            updateReviewSummary();
            await loadWeather();
            saveDraft();
        }
    });

    // Dates can't be changed once a trip is saved (edit mode) — they may
    // already be tied to a confirmed hotel booking, and changing them here
    // wouldn't touch that booking's actual dates.
    if (window.travelixEditTripId) {
        arrivalDate.classList.add('trip-input-locked');
        departureDate.classList.add('trip-input-locked');
        return;
    }

    const openSharedCalendar = (e) => {
        e.preventDefault();
        e.stopPropagation();
        tripDatePicker?.open();
    };

    arrivalDate.addEventListener('click', openSharedCalendar);
    departureDate.addEventListener('click', openSharedCalendar);
    arrivalDate.addEventListener('focus', openSharedCalendar);
    departureDate.addEventListener('focus', openSharedCalendar);
    arrivalDate.parentElement?.addEventListener('click', openSharedCalendar);
    departureDate.parentElement?.addEventListener('click', openSharedCalendar);
}

function initMap() {
    const tripMapEl = document.getElementById('tripMap');
    if (!tripMapEl || typeof L === 'undefined') return;

    map = L.map('tripMap').setView([30.3753, 69.3451], 6);

    // Same raw OpenStreetMap tiles as the admin location picker
    // (admin_manage/add_trips.php), so both maps look consistent.
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
}

function clearSelectedMarkers() {
    selectedPlaceMarkers.forEach((entry) => {
        if (map && entry.marker) {
            map.removeLayer(entry.marker);
        }
    });

    selectedPlaceMarkers = [];
}

function createNumberedIcon(number, state = 'normal') {
    let stateClass = '';

    if (state === 'active') stateClass = 'is-active';
    if (state === 'dim') stateClass = 'is-dim';

    return L.divIcon({
        className: 'numbered-marker-shell',
        html: `<div class="numbered-marker ${stateClass}">${number}</div>`,
        iconSize: [48, 48],
        iconAnchor: [24, 24],
        popupAnchor: [0, -18]
    });
}

function getPlaceImagePath(place) {
    const imageName = String(place?.image || '').trim();
    const imageSource = String(place?.image_source || '').trim().toLowerCase();

    if (!imageName) {
        return '/travelix/images/default_place.png';
    }

    if (imageSource === 'admin') {
        return `/travelix/location_images/${imageName}`;
    }

    return `/travelix/images/${imageName}`;
}

function renderMapPlacePreview(place, index) {
    if (!mapPlacePreview || !mapPlacePreviewContent) return;

    const whyGo = Array.isArray(place.why_go) ? place.why_go : [];
    const knowBeforeYouGo = Array.isArray(place.know_before_you_go) ? place.know_before_you_go : [];

    mapPlacePreviewContent.innerHTML = `
        <img class="map-preview-image" src="${getPlaceImagePath(place)}" alt="${place.name}">
        <h4 class="map-preview-title">${index + 1}. ${place.name}</h4>
        <div class="map-preview-description">${place.description || ''}</div>

        <div class="map-preview-section">
            <h6>Why you should go</h6>
            ${
                whyGo.length
                    ? `<ol class="map-preview-list">${whyGo.map((item) => `<li>${item}</li>`).join('')}</ol>`
                    : `<p class="map-preview-empty">No details available yet.</p>`
            }
        </div>

        <div class="map-preview-section">
            <h6>Know before you go</h6>
            ${
                knowBeforeYouGo.length
                    ? `<ul class="map-preview-list">${knowBeforeYouGo.map((item) => `<li>${item}</li>`).join('')}</ul>`
                    : `<p class="map-preview-empty">No travel tips available yet.</p>`
            }
        </div>
    `;

    mapPlacePreview.classList.remove('hidden');
}

function hideMapPlacePreview() {
    if (!mapPlacePreview) return;
    mapPlacePreview.classList.add('hidden');
}

function updateMarkerStates(activeIndex = null) {
    selectedPlaceMarkers.forEach((entry, index) => {
        let state = 'normal';

        if (activeIndex !== null) {
            state = index === activeIndex ? 'active' : 'dim';
        }

        entry.marker.setIcon(createNumberedIcon(index + 1, state));
    });
}

function updateMapSelectedSummary() {
    if (!mapSelectedSummary) return;

    if (!selectedPlaces.length) {
        mapSelectedSummary.innerHTML = `
            <div class="map-selected-summary-title">Selected Places</div>
            <div class="map-selected-summary-text">No places selected yet.</div>
        `;
        return;
    }

    const placeNames = selectedPlaces.map((place, index) => `${index + 1}. ${place.name}`).join(' • ');

    mapSelectedSummary.innerHTML = `
        <div class="map-selected-summary-title">Selected Places</div>
        <div class="map-selected-summary-text">${placeNames}</div>
    `;
}

function drawSelectedPlacesOnMap() {
    if (!map) return;

    clearSelectedMarkers();

    selectedPlaces.forEach((place, index) => {
        const marker = L.marker([place.lat, place.lng], {
            icon: createNumberedIcon(index + 1, 'normal')
        }).addTo(map);

        marker.on('mouseover', () => {
            hoveredMarkerIndex = index;
            updateMarkerStates(index);
            renderMapPlacePreview(place, index);
        });

        marker.on('click', () => {
            hoveredMarkerIndex = index;
            updateMarkerStates(index);
            renderMapPlacePreview(place, index);
        });

        marker.on('mouseout', () => {
            if (hoveredMarkerIndex === index) {
                updateMarkerStates(index);
            }
        });

        selectedPlaceMarkers.push({ marker, place });
    });

    updateMapSelectedSummary();
}

function normalizePlaceName(value) {
    return String(value || '').trim().toLowerCase();
}

function getAvailablePlacesForCity(city) {
    if (!window.cityPlaces?.[city]) return [];

    return window.cityPlaces[city].places.filter((place) => {
        return !selectedPlaces.some((selected) => normalizePlaceName(selected.name) === normalizePlaceName(place.name));
    });
}

function renderSelectedPlaces() {
    if (!selectedPlacesWrap) return;

    selectedPlacesWrap.innerHTML = '';

    if (!selectedPlaces.length) {
        selectedPlacesWrap.innerHTML = `
            <div class="empty-state-box compact">
                <div class="empty-state-icon">🧭</div>
                <h5>No places selected yet</h5>
                <p>Your selected places will appear here and will also be marked on the map.</p>
            </div>
        `;

        updateMapSelectedSummary();
        clearSelectedMarkers();
        hideMapPlacePreview();
        return;
    }

    selectedPlaces.forEach((place, index) => {
        const item = document.createElement('div');
        item.className = 'place-card sortable-place-card';
        item.setAttribute('draggable', 'true');
        item.dataset.index = index;

        item.innerHTML = `
            <div class="place-number">${index + 1}</div>
            <div class="place-content" style="flex:1;">
                <h6>${place.name}</h6>
                <p>${place.description}</p>
            </div>
            <div class="place-card-actions">
                <button type="button" class="drag-handle-btn" title="Drag to reorder">↕</button>
                <button type="button" class="place-add-btn place-remove-btn" data-name="${place.name}" title="Remove place">×</button>
            </div>
        `;

        item.addEventListener('dragstart', function () {
            draggedPlaceIndex = Number(this.dataset.index);
            this.classList.add('dragging');
        });

        item.addEventListener('dragend', function () {
            this.classList.remove('dragging');
            draggedPlaceIndex = null;

            selectedPlacesWrap.querySelectorAll('.sortable-place-card').forEach((card) => {
                card.classList.remove('drag-over');
            });
        });

        item.addEventListener('dragover', function (e) {
            e.preventDefault();
            this.classList.add('drag-over');
        });

        item.addEventListener('dragleave', function () {
            this.classList.remove('drag-over');
        });

        item.addEventListener('drop', function (e) {
            e.preventDefault();
            this.classList.remove('drag-over');

            const targetIndex = Number(this.dataset.index);

            if (draggedPlaceIndex === null || draggedPlaceIndex === targetIndex) return;

            const movedPlace = selectedPlaces.splice(draggedPlaceIndex, 1)[0];
            selectedPlaces.splice(targetIndex, 0, movedPlace);

            hoveredMarkerIndex = null;
            renderSelectedPlaces();
            renderAvailablePlaces(toCity?.value || '');
            drawSelectedPlacesOnMap();
            saveDraft();
        });

        selectedPlacesWrap.appendChild(item);
    });

    selectedPlacesWrap.querySelectorAll('.place-remove-btn').forEach((button) => {
        button.addEventListener('click', function () {
            const name = this.dataset.name;

            showStepLoader(`Removing ${name}...`);

            setTimeout(() => {
                setSelectedPlaces(selectedPlaces.filter((place) => normalizePlaceName(place.name) !== normalizePlaceName(name)));
                hoveredMarkerIndex = null;
                renderSelectedPlaces();
                renderAvailablePlaces(toCity?.value || '');
                drawSelectedPlacesOnMap();
                saveDraft();
                closeStepLoader();
            }, 350);
        });
    });

    drawSelectedPlacesOnMap();
}

function renderAvailablePlaces(city) {
    if (!availablePlaces) return;

    availablePlaces.innerHTML = '';

    if (!window.cityPlaces?.[city]) {
        availablePlaces.innerHTML = `
            <div class="empty-state-box">
                <div class="empty-state-icon">📍</div>
                <h5>No city selected yet</h5>
                <p>Please choose a destination city to see major places and attractions here.</p>
            </div>
        `;

        if (cityMarker && map) {
            map.removeLayer(cityMarker);
            cityMarker = null;
        }

        clearSelectedMarkers();
        updateMapSelectedSummary();
        hideMapPlacePreview();

        if (map) {
            map.setView([30.3753, 69.3451], 6);
        }

        return;
    }

    const cityData = window.cityPlaces[city];
    const filteredPlaces = getAvailablePlacesForCity(city);

    if (cityMarker && map) {
        map.removeLayer(cityMarker);
    }

    if (map && Array.isArray(cityData.center)) {
        map.setView(cityData.center, 11);

        cityMarker = L.marker(cityData.center)
            .addTo(map)
            .bindPopup(`<b>${city}</b>`)
            .openPopup();
    }

    if (!filteredPlaces.length) {
        availablePlaces.innerHTML = `
            <div class="empty-state-box compact">
                <div class="empty-state-icon">✅</div>
                <h5>All major places added</h5>
                <p>You have already added all available places for this city.</p>
            </div>
        `;
        return;
    }

    filteredPlaces.forEach((place) => {
        const card = document.createElement('div');
        card.className = 'available-place-card';
        card.title = place.description;

        card.innerHTML = `
            <img src="${getPlaceImagePath(place)}" alt="${place.name}">
            <div class="available-place-content">
                <h6>${place.name}</h6>
                <p>${place.description}</p>
            </div>
            <button type="button" class="place-add-btn" title="Add ${place.name}">+</button>
        `;

        card.querySelector('.place-add-btn').addEventListener('click', () => {
            showStepLoader(`Adding ${place.name}...`);

            setTimeout(() => {
                selectedPlaces.push(place);
                renderSelectedPlaces();
                renderAvailablePlaces(city);
                drawSelectedPlacesOnMap();
                saveDraft();
                closeStepLoader();
            }, 350);
        });

        availablePlaces.appendChild(card);
    });
}

function getWeatherIcon(rain) {
    if (rain >= 60) return '🌧️';
    if (rain >= 30) return '⛅';
    return '☀️';
}

function getRainLevel(rain) {
    if (rain >= 60) return 'high';
    if (rain >= 30) return 'medium';
    return 'low';
}

function formatWeatherDayLabel(dateStr) {
    const date = new Date(dateStr + 'T00:00:00');
    if (isNaN(date.getTime())) return dateStr;
    return date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
}

function renderWeatherBoxes(data) {
    weatherBoxes.forEach((box) => {
        box.style.display = data?.daily?.time?.length ? 'block' : 'none';
    });

    weatherDaysGroups.forEach((container) => {
        container.innerHTML = '';

        if (!data?.daily?.time?.length) return;

        data.daily.time.forEach((date, index) => {
            const max = data.daily.temperature_2m_max[index];
            const min = data.daily.temperature_2m_min[index];
            const rain = data.daily.precipitation_probability_max[index];
            const rainLevel = getRainLevel(rain);

            const div = document.createElement('div');
            div.className = 'weather-day';
            div.innerHTML = `
                <div class="weather-day-icon">${getWeatherIcon(rain)}</div>
                <strong>${formatWeatherDayLabel(date)}</strong>
                <div class="weather-day-temp">
                    <span class="weather-day-max">${max}°</span>
                    <span class="weather-day-sep">/</span>
                    <span class="weather-day-min">${min}°C</span>
                </div>
                <span class="weather-day-rain ${rainLevel}">
                    <i class="fa-solid fa-droplet"></i> ${rain}%
                </span>
            `;

            container.appendChild(div);
        });
    });
}

async function loadWeather() {
    const city = toCity?.value || '';
    const start = arrivalDate?.value || '';
    const end = departureDate?.value || '';

    renderWeatherBoxes(null);
    setLatestWeatherSummary('');

    if (!city || !start || !end || !window.cityPlaces?.[city]) return;

    const center = window.cityPlaces[city].center;
    const url = `https://api.open-meteo.com/v1/forecast?latitude=${center[0]}&longitude=${center[1]}&daily=temperature_2m_max,temperature_2m_min,precipitation_probability_max&timezone=auto&start_date=${start}&end_date=${end}`;

    try {
        const response = await fetch(url);
        const data = await response.json();

        if (!data?.daily?.time?.length) {
            renderWeatherBoxes(null);
            return;
        }

        renderWeatherBoxes(data);

        setLatestWeatherSummary(data.daily.time.map((date, index) => {
            return `${date}: max ${data.daily.temperature_2m_max[index]}C, min ${data.daily.temperature_2m_min[index]}C, rain ${data.daily.precipitation_probability_max[index]}%`;
        }).join(' | '));

        saveDraft();
    } catch (error) {
        renderWeatherBoxes(null);
        setLatestWeatherSummary('');
    }
}

function handleHotelOptionUI() {
    if (!hotelOption || !hotelHelperWrap) return;

    hotelHelperWrap.style.display = hotelOption.value === 'No' ? 'block' : 'none';
    renderSuggestions();
    saveDraft();
}

function getSelectedPlacesSummary() {
    if (!selectedPlaces.length) return '';
    return selectedPlaces.map((place) => place.name).join(', ');
}

function getBookedHotelFromStorage() {
    try {
        const raw = localStorage.getItem('travelix_hotel_booking');
        return raw ? JSON.parse(raw) : null;
    } catch (e) {
        return null;
    }
}

function updateReviewSummary() {
    if (reviewDestination) {
        reviewDestination.textContent = toCity?.value || '-';
    }

    if (reviewDates) {
        reviewDates.textContent = arrivalDate?.value && departureDate?.value
            ? `${arrivalDate.value} → ${departureDate.value}`
            : '-';
    }

    if (reviewTravelers) {
        reviewTravelers.textContent = travelers?.value || '-';
    }

    if (reviewBudget) {
        reviewBudget.textContent = budget?.value || '-';
    }

    if (reviewBookedHotel) {
        const bookedHotel = getBookedHotelFromStorage();
        if (bookedHotel?.hotelName) {
            reviewBookedHotel.textContent = `${bookedHotel.hotelName}${bookedHotel.roomType ? ' (' + bookedHotel.roomType + ')' : ''}`;
        } else if (hotelOption?.value === 'Yes') {
            reviewBookedHotel.textContent = 'Yes, hotel needed';
        } else if (hotelOption?.value === 'No') {
            reviewBookedHotel.textContent = 'No hotel selected';
        } else {
            reviewBookedHotel.textContent = '-';
        }
    }

    if (reviewEstimatedBudget) {
        const budgetVal = budget?.value || '';
        const persons = parseInt(travelers?.value || '0', 10);

        let estimated = '-';

        if (budgetVal === 'Under PKR 20,000') {
            estimated = persons >= 4 ? 'Around PKR 18,000 - 25,000' : 'Around PKR 12,000 - 18,000';
        } else if (budgetVal === 'PKR 20,000 - 50,000') {
            estimated = persons >= 4 ? 'Around PKR 35,000 - 55,000' : 'Around PKR 25,000 - 40,000';
        } else if (budgetVal === 'PKR 50,000 - 100,000') {
            estimated = persons >= 4 ? 'Around PKR 70,000 - 110,000' : 'Around PKR 50,000 - 85,000';
        } else if (budgetVal === 'Above PKR 100,000') {
            estimated = persons >= 4 ? 'Around PKR 120,000+' : 'Around PKR 100,000+';
        }

        reviewEstimatedBudget.textContent = estimated;
    }
}

function renderSuggestions() {
    const budgetVal = budget?.value || '';
    const persons = parseInt(travelers?.value || '0', 10);
    const hasCar = privateCar?.value || '';
    const wantsHotel = hotelOption?.value || '';

    let hotelSuggestion = '-';
    let transportSuggestion = '-';
    let minBudgetSuggestion = '-';

    if (budgetVal === 'Under PKR 20,000') {
        hotelSuggestion = persons >= 4 ? 'Guest House' : 'Budget Hotel';
        transportSuggestion = hasCar === 'Yes' ? 'Private Car (Short Route)' : 'Bus / Shared Transport';
        minBudgetSuggestion = persons >= 4 ? 'PKR 18,000 - 25,000' : 'PKR 12,000 - 18,000';
    } else if (budgetVal === 'PKR 20,000 - 50,000') {
        hotelSuggestion = persons >= 4 ? 'Family Hotel' : 'Budget / Standard Hotel';
        transportSuggestion = hasCar === 'Yes' ? 'Private Car' : 'Bus / Train';
        minBudgetSuggestion = persons >= 4 ? 'PKR 35,000 - 55,000' : 'PKR 25,000 - 40,000';
    } else if (budgetVal === 'PKR 50,000 - 100,000') {
        hotelSuggestion = persons >= 4 ? 'Family Resort' : 'Standard / Luxury Hotel';
        transportSuggestion = hasCar === 'Yes' ? 'Private Car / SUV' : 'Air / Premium Transport';
        minBudgetSuggestion = persons >= 4 ? 'PKR 70,000 - 110,000' : 'PKR 50,000 - 85,000';
    } else if (budgetVal === 'Above PKR 100,000') {
        hotelSuggestion = persons >= 4 ? 'Premium Family Resort' : 'Luxury Hotel';
        transportSuggestion = hasCar === 'Yes' ? 'SUV / Private Car' : 'Air + Local Taxi';
        minBudgetSuggestion = persons >= 4 ? 'PKR 120,000+' : 'PKR 100,000+';
    }

    if (!budgetVal) {
        hotelSuggestion = wantsHotel === 'No' ? 'No hotel selected' : '-';
        transportSuggestion = '-';
        minBudgetSuggestion = '-';
    }

    // "No hotel wanted" always wins over the budget-tier suggestion — a
    // budget is asked regardless of the hotel choice, so without this the
    // budget-based branch above silently overwrote "No hotel selected"
    // with e.g. "Budget Hotel" whenever a budget was also picked.
    if (wantsHotel === 'No') {
        hotelSuggestion = 'No hotel selected';
    }

    // A hotel actually booked via the "Add Hotel" flow always wins over the
    // generic budget-based suggestion.
    const bookedHotel = getBookedHotelFromStorage();
    if (bookedHotel?.hotelName) {
        hotelSuggestion = bookedHotel.hotelName;
    }

    if (suggestedHotel) suggestedHotel.textContent = `Hotel: ${hotelSuggestion}`;
    if (suggestedTransport) suggestedTransport.textContent = `Transport: ${transportSuggestion}`;
    if (suggestedMinBudget) suggestedMinBudget.textContent = `Min Budget: ${minBudgetSuggestion}`;
}

function validateStep(step) {
    if (step === 1) {
        if (!toCity?.value) {
            showError('Select Destination', 'Please select a destination city first.');
            return false;
        }
        return true;
    }

    if (step === 2) {
        if (!arrivalDate?.value || !departureDate?.value) {
            showError('Select Dates', 'Please choose both arrival and departure dates.');
            return false;
        }

        if (departureDate.value < arrivalDate.value) {
            showError('Invalid Dates', 'Departure date must be after or equal to arrival date.');
            return false;
        }

        const start = new Date(arrivalDate.value);
        const end = new Date(departureDate.value);
        const maxAllowed = addDays(start, 7);

        if (end > maxAllowed) {
            showError('Maximum 7 Days Allowed', 'Departure date can only be up to 7 days after the arrival date.');
            return false;
        }

        return true;
    }

    if (step === 3) {
        if (!travelers?.value || parseInt(travelers.value, 10) < 1) {
            showError('Travellers Required', 'Please enter a valid number of travellers.');
            return false;
        }

        if (!budget?.value) {
            showError('Budget Required', 'Please select your budget range.');
            return false;
        }

        if (!privateCar?.value) {
            showError('Transport Information Required', 'Please tell us whether you have a private car.');
            return false;
        }

        if (!hotelOption?.value) {
            showError('Hotel Preference Required', 'Please select whether you need a hotel.');
            return false;
        }

        return true;
    }

    return true;
}

function showStep(step) {
    document.querySelectorAll('.trip-step').forEach((el) => el.classList.remove('active'));
    document.querySelector(`#step-${step}`)?.classList.add('active');

    stepItems.forEach((el) => {
        el.classList.remove('active', 'done');
        const stepNum = Number(el.dataset.step);

        if (stepNum === step) el.classList.add('active');
        if (stepNum < step) el.classList.add('done');
    });

    currentStep = step;
    updateReviewSummary();
    renderSuggestions();
    saveDraft();
    smoothScrollTop();

    if (step === 2) {
        loadWeather();
        if (tripDatePicker) {
            tripDatePicker.set('showMonths', window.innerWidth <= 1200 ? 1 : 2);
            tripDatePicker.redraw();
        }
    }

    if (step === 4) {
        loadWeather();
    }
}

function bindStepNavigation() {
    document.querySelectorAll('.trip-next-btn').forEach((button) => {
        button.addEventListener('click', function () {
            const nextStep = Number(this.dataset.next);

            if (!validateStep(currentStep)) return;

            showStepLoader('Moving to next step...');

            setTimeout(async () => {
                if (currentStep === 2) {
                    await loadWeather();
                }

                closeStepLoader();
                showStep(nextStep);
            }, 350);
        });
    });

    document.querySelectorAll('.trip-back-btn').forEach((button) => {
        button.addEventListener('click', function () {
            const prevStep = Number(this.dataset.back);

            showStepLoader('Going back...');

            setTimeout(() => {
                closeStepLoader();
                showStep(prevStep);
            }, 300);
        });
    });
}

function bindFieldListeners() {
    if (toCity) {
        toCity.addEventListener('change', function () {
            setSelectedPlaces([]);
            hoveredMarkerIndex = null;
            hideMapPlacePreview();
            renderAvailablePlaces(this.value);
            renderSelectedPlaces();
            updateReviewSummary();
            saveDraft();
        });
    }

    [toCity, travelers, budget, privateCar, hotelOption, tripNotes, arrivalDate, departureDate].forEach((input) => {
        if (!input) return;

        input.addEventListener('input', () => {
            renderSuggestions();
            updateReviewSummary();
            saveDraft();
        });

        input.addEventListener('change', () => {
            renderSuggestions();
            updateReviewSummary();
            saveDraft();
        });
    });

    if (hotelOption) {
        hotelOption.addEventListener('change', handleHotelOptionUI);
    }
}

function bindMapPreviewActions() {
    if (!closeMapPreview) return;

    closeMapPreview.addEventListener('click', () => {
        hoveredMarkerIndex = null;
        hideMapPlacePreview();
        updateMarkerStates(null);
    });
}

function bindHotelHelpers() {
    if (!addHotelBtn) return;

    addHotelBtn.addEventListener('click', () => {
        const step3Data = {
            travelers: travelers?.value || '',
            budget: budget?.value || '',
            privateCar: privateCar?.value || '',
            hotelOption: hotelOption?.value || '',
            tripNotes: tripNotes?.value || ''
        };

        localStorage.setItem('travelix_return_to_step', '3');
        localStorage.setItem('travelix_step3_data', JSON.stringify(step3Data));

        const params = new URLSearchParams({
            toCity: toCity?.value || '',
            arrivalDate: arrivalDate?.value || '',
            departureDate: departureDate?.value || '',
            travelers: travelers?.value || '',
            budget: budget?.value || '',
            source: 'create_trip',
            returnStep: '3',
            returnUrl: '/travelix/trips/create_trip.php'
        });

        window.location.href = `/travelix/hotel/hotels.php?${params.toString()}`;
    });
}

function restoreReturnFromHotel() {
    const returnStep = localStorage.getItem('travelix_return_to_step');
    const savedStep3Data = localStorage.getItem('travelix_step3_data');

    if (savedStep3Data) {
        try {
            const data = JSON.parse(savedStep3Data);

            if (travelers) travelers.value = data.travelers || '';
            if (budget) budget.value = data.budget || '';
            if (privateCar) privateCar.value = data.privateCar || '';
            if (hotelOption) hotelOption.value = data.hotelOption || '';
            if (tripNotes) tripNotes.value = data.tripNotes || '';
        } catch (error) {
            console.error('Could not restore step 3 data:', error);
        }
    }

    return returnStep;
}

function initialRender() {
    renderAvailablePlaces(toCity?.value || '');
    renderSelectedPlaces();
    updateReviewSummary();
    renderSuggestions();
    renderWeatherBoxes(null);

    handleHotelOptionUI();

    if (toCity?.value) {
        renderAvailablePlaces(toCity.value);
    } else {
        renderAvailablePlaces('');
    }

    showStep(1);
}

document.addEventListener('DOMContentLoaded', async () => {
    // Editing a saved trip (?edit=<id>) — fetch it and seed the draft before
    // the normal loadDraft() flow reads localStorage, so every step opens
    // pre-filled with the trip's existing data instead of a blank form.
    if (window.travelixEditTripId && typeof window.travelixFetchEditTripDraft === 'function') {
        showStepLoader('Loading your saved trip...');
        const editDraft = await window.travelixFetchEditTripDraft();
        closeStepLoader();
        if (editDraft) {
            localStorage.setItem(storageKey, JSON.stringify(editDraft));
        }
    }

    loadDraft();

    const returnStep = restoreReturnFromHotel();

    await loadMergedCityPlaces();
    initMap();
    initDateLimits();
    bindStepNavigation();
    bindFieldListeners();
    bindMapPreviewActions();
    bindHotelHelpers();

    initialRender();

    if (returnStep === '3') {
        handleHotelOptionUI();
        showStep(3);
        localStorage.removeItem('travelix_return_to_step');
        localStorage.removeItem('travelix_step3_data');
    }
});
