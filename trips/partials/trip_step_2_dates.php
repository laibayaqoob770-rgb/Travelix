<div class="trip-step" id="step-2">
    <div class="trip-step-header">
        <span class="trip-step-badge">Step 2</span>
        <h2 class="trip-form-title">Choose Your Dates</h2>
        <p class="trip-form-subtitle">
            Select your arrival and departure dates. You can only choose today or future dates,
            so your trip planning stays valid and practical.
        </p>
    </div>

    <div class="trip-info-note mb-4">
        <div class="trip-info-note-icon">i</div>
        <div class="trip-info-note-text">
            <?php if (!empty($editTripId)): ?>
                Dates can't be changed once a trip is saved — they may already be tied to a hotel booking.
                Cancel and create a new trip if your dates have changed.
            <?php else: ?>
                Your departure date must be after your arrival date. Weather and planning suggestions
                will be generated based on these dates.
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="trip-input-box">
                <label class="trip-label-top">Arrival Date</label>
                <input
                    type="text"
                    class="trip-input"
                    id="arrivalDate"
                    placeholder="Select arrival date"
                    readonly
                    required
                    autocomplete="off"
                >
            </div>
        </div>

        <div class="col-md-6">
            <div class="trip-input-box">
                <label class="trip-label-top">Departure Date</label>
                <input
                    type="text"
                    class="trip-input"
                    id="departureDate"
                    placeholder="Select departure date"
                    readonly
                    required
                    autocomplete="off"
                >
            </div>
        </div>
    </div>

    <input
    type="text"
    id="tripDateRange"
    style="opacity: 0; position: absolute; z-index: -1; pointer-events: none;"
    readonly
>

    <div class="date-help-cards mt-4">
        <div class="date-help-card">
            <h5>Arrival</h5>
            <p>Choose the day you plan to reach your destination and begin your trip.</p>
        </div>

        <div class="date-help-card">
            <h5>Departure</h5>
            <p>Choose the day you plan to return or move onward from your destination.</p>
        </div>
    </div>

    <div class="weather-box mt-4" id="weatherBox" style="display:none;">
        <h5>Weather For Selected Dates</h5>
        <div class="weather-days" id="weatherDays"></div>
    </div>

    <div class="trip-nav-buttons">
        <button type="button" class="trip-back-btn" data-back="1">
            <span class="btn-arrow">←</span>
            Back
        </button>

        <button type="button" class="trip-next-btn" data-next="3">
            Next
            <span class="btn-arrow">→</span>
        </button>
    </div>
</div>