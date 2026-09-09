<div class="trip-step" id="step-4">
    <div class="trip-step-header">
        <span class="trip-step-badge">Step 4</span>
        <h2 class="trip-form-title">Review Your Trip</h2>
        <p class="trip-form-subtitle">
            Review your trip details before saving.
        </p>
    </div>

    <div class="review-section-title mt-4">
        <h4>Quick Suggestions</h4>
        <p>Travelix estimates hotel, transport, and minimum budget based on your choices.</p>
    </div>

    <div class="suggestion-group suggestion-group-clean">
        <span class="suggestion-pill" id="suggestedHotel">Hotel: -</span>
        <span class="suggestion-pill" id="suggestedTransport">Transport: -</span>
        <span class="suggestion-pill" id="suggestedMinBudget">Min Budget: -</span>
    </div>

    <div class="weather-box mt-4" id="weatherBox" style="display:none;">
        <div class="ai-plan-topbar">
            <h5>Weather For Selected Dates</h5>
            <span class="trip-mini-status">Live preview</span>
        </div>
        <div class="weather-days" id="weatherDays"></div>
    </div>

    <div class="review-section-title mt-4">
        <h4>Final Review</h4>
        <p>Please confirm these details before saving your trip.</p>
    </div>

    <div class="review-summary-grid mt-3">
        <div class="review-card">
            <div class="review-icon">📍</div>
            <h6>Destination</h6>
            <p id="reviewDestination">-</p>
        </div>

        <div class="review-card">
            <div class="review-icon">📅</div>
            <h6>Dates</h6>
            <p id="reviewDates">-</p>
        </div>

        <div class="review-card">
            <div class="review-icon">👥</div>
            <h6>Travellers</h6>
            <p id="reviewTravelers">-</p>
        </div>

        <div class="review-card">
            <div class="review-icon">💰</div>
            <h6>Budget</h6>
            <p id="reviewBudget">-</p>
        </div>

        <div class="review-card">
            <div class="review-icon">🏨</div>
            <h6>Hotel Preference</h6>
            <p id="reviewBookedHotel">-</p>
        </div>

        <div class="review-card">
            <div class="review-icon">🧾</div>
            <h6>Estimated Budget</h6>
            <p id="reviewEstimatedBudget">-</p>
        </div>
    </div>

    <div class="trip-save-note mt-4">
        <div class="trip-save-note-icon">🔔</div>
        <div>
            <strong>Notification will be added after saving.</strong>
            <p>
                Once this trip is saved, it will appear in your saved trips and a notification will show in your navbar.
            </p>
        </div>
    </div>

    <div class="trip-nav-buttons">
        <button type="button" class="trip-back-btn" data-back="3">
            <span class="btn-arrow">←</span>
            Back
        </button>

        <button type="submit" class="trip-submit-btn" id="saveTripBtn">
            <span class="btn-text"><?= (!empty($editTripId)) ? 'Update Trip' : 'Save Trip' ?></span>
        </button>
    </div>
</div>

<style>
.trip-mini-status {
    display: inline-flex;
    align-items: center;
    background: #eaf7fc;
    color: #087c91;
    padding: 9px 16px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 900;
}

.suggestion-group-clean {
    background: #fff;
    border-radius: 22px;
    padding: 18px;
    border: 1px solid rgba(20, 132, 180, 0.12);
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.05);
}
</style>