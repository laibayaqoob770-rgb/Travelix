<div class="trip-step active" id="step-1">
    <div class="trip-step-header">
        <span class="trip-step-badge">Step 1</span>
        <h2 class="trip-form-title">Where do you want to go?</h2>
        <p class="trip-form-subtitle">
            Choose your destination city first. Then explore major places, preview details on the map,
            add the ones you want, and drag selected places to reorder your trip route.
        </p>
    </div>

    <div class="trip-input-box mb-4">
        <label class="trip-label-top" for="toCity">Destination City</label>
        <input type="text" class="trip-select" id="toCity" name="toCity" placeholder="Type to search a city..." autocomplete="off" required>
        <div class="helper-text" style="margin-top:8px;color:#64748b;font-size:13px;line-height:1.6;">
            Start typing to search — admin-added cities appear automatically.
        </div>
    </div>

    <div class="trip-info-note mb-4">
        <div class="trip-info-note-icon">i</div>
        <div class="trip-info-note-text">
            Select a city to load its major attractions. Click the <strong>+</strong> button to add a place
            to your trip. Hover or click numbered map markers to preview place details below the map. In
            <strong>Selected Places</strong>, drag cards to change the order of your trip.
        </div>
    </div>

    <div class="places-section-block">
        <div class="section-mini-header">
            <h4>Major Places</h4>
            <p>Popular attractions available for the selected destination.</p>
        </div>

        <div id="availablePlaces" class="places-grid" aria-live="polite">
            <div class="empty-state-box">
                <div class="empty-state-icon">📍</div>
                <h5>No city selected yet</h5>
                <p>Please choose a destination city to see major places and attractions here.</p>
            </div>
        </div>
    </div>

    <div class="places-section-block mt-4">
        <div class="section-mini-header">
            <h4>Selected Places</h4>
            <p>These are the places you have chosen for your trip. Drag them up or down to reorder your route.</p>
        </div>

        <div class="trip-info-note mb-3">
            <div class="trip-info-note-icon">↕</div>
            <div class="trip-info-note-text">
                Tip: press and drag a selected place card to change its position. The numbering on the map
                should update automatically based on this order.
            </div>
        </div>

        <div id="selectedPlaces" class="selected-places-list" aria-live="polite">
            <div class="empty-state-box compact">
                <div class="empty-state-icon">🧭</div>
                <h5>No places selected yet</h5>
                <p>Your selected places will appear here and will also be marked on the map.</p>
            </div>
        </div>
    </div>

    <div class="trip-nav-buttons">
        <button type="button" class="trip-next-btn" data-next="2">
            Next
            <span class="btn-arrow">→</span>
        </button>
    </div>
</div>