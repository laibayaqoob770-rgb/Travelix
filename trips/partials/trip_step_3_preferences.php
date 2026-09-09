<div class="trip-step" id="step-3">
    <div class="trip-step-header">
        <span class="trip-step-badge">Step 3</span>
        <h2 class="trip-form-title">Trip Preferences</h2>
        <p class="trip-form-subtitle">
            Tell Travelix a little more about your trip style so we can suggest better transport,
            budget expectations, hotel level, and a more practical travel plan.
        </p>
    </div>

    <div class="trip-info-note mb-4">
        <div class="trip-info-note-icon">i</div>
        <div class="trip-info-note-text">
            These preferences help generate smarter route, hotel, and budget suggestions. You can also
            add notes for food choices, kids, senior citizens, or any special travel needs.
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="trip-input-box">
                <label class="trip-label-top">Travellers</label>
                <input type="number" class="trip-input" id="travelers" min="1" placeholder="How many people?" required>
            </div>
        </div>

        <div class="col-md-6">
            <div class="trip-input-box">
                <label class="trip-label-top">Budget</label>
                <select class="trip-select" id="budget" required>
                    <option value="">Select budget range</option>
                    <option value="Under PKR 20,000">Under PKR 20,000</option>
                    <option value="PKR 20,000 - 50,000">PKR 20,000 - 50,000</option>
                    <option value="PKR 50,000 - 100,000">PKR 50,000 - 100,000</option>
                    <option value="Above PKR 100,000">Above PKR 100,000</option>
                </select>
            </div>
        </div>

        <div class="col-md-6">
            <div class="trip-input-box">
                <label class="trip-label-top">Private Car</label>
                <select class="trip-select" id="privateCar" required>
                    <option value="">Do you have a private car?</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                </select>
            </div>
        </div>

        <div class="col-md-6">
            <div class="trip-input-box hotel-input-box">
                <label class="trip-label-top">Hotel?</label>
                <select class="trip-select" id="hotelOption" required>
                    <option value="">Select an option</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                </select>
            </div>
        </div>

        <div class="col-12" id="hotelHelperWrap" style="display:none;">
            <div class="hotel-helper-card">
                <div class="hotel-helper-card-left">
                    <h5>Need a hotel?</h5>
                    <p>
                        Travelix can take you to the hotel search page with your city, dates,
                        travellers, and budget already saved.
                    </p>
                </div>
                <div class="hotel-helper-card-right">
                    <button type="button" class="trip-secondary-btn" id="addHotelBtn">
                        Add Hotel
                    </button>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="trip-input-box">
                <label class="trip-label-top">Trip Notes</label>
                <textarea
                    class="trip-textarea"
                    id="tripNotes"
                    placeholder="Add family needs, food preferences, kids, senior citizens, adventure plans, accessibility needs, or any important notes..."
                ></textarea>
            </div>
        </div>
    </div>

    <div class="preferences-help-grid mt-4">
        <div class="preferences-help-card">
            <h5>Budget Planning</h5>
            <p>Your selected budget helps Travelix suggest a realistic hotel category, travel mode, and minimum trip estimate.</p>
        </div>

        <div class="preferences-help-card">
            <h5>Transport Suggestion</h5>
            <p>If you have a private car, Travelix can lean more toward road-trip routes and flexible place selection.</p>
        </div>

        <div class="preferences-help-card full">
            <h5>Special Notes</h5>
            <p>
                Use trip notes to mention food requirements, elderly travelers, children, health concerns,
                comfort preferences, or adventure interests.
            </p>
        </div>
    </div>

    <div class="trip-nav-buttons">
        <button type="button" class="trip-back-btn" data-back="2">
            <span class="btn-arrow">←</span>
            Back
        </button>

        <button type="button" class="trip-next-btn" data-next="4">
            Next
            <span class="btn-arrow">→</span>
        </button>
    </div>
</div>

<script type="module">
const hotelOption = document.getElementById("hotelOption");
const hotelHelperWrap = document.getElementById("hotelHelperWrap");
const addHotelBtn = document.getElementById("addHotelBtn");

function handleHotelOptionChange() {
    const value = hotelOption?.value || "";

    if (hotelHelperWrap) {
        hotelHelperWrap.style.display = value === "No" ? "block" : "none";
    }
}

hotelOption?.addEventListener("change", handleHotelOptionChange);

addHotelBtn?.addEventListener("click", () => {
    const toCity = document.getElementById("toCity")?.value || "";
    const arrivalDate = document.getElementById("arrivalDate")?.value || "";
    const departureDate = document.getElementById("departureDate")?.value || "";
    const travelers = document.getElementById("travelers")?.value || "";
    const budget = document.getElementById("budget")?.value || "";
    const privateCar = document.getElementById("privateCar")?.value || "";
    const hotelOptionValue = document.getElementById("hotelOption")?.value || "";
    const tripNotes = document.getElementById("tripNotes")?.value || "";

    localStorage.setItem("travelix_return_to_step", "3");

    localStorage.setItem("travelix_step3_data", JSON.stringify({
        travelers,
        budget,
        privateCar,
        hotelOption: hotelOptionValue,
        tripNotes
    }));

    const params = new URLSearchParams({
        toCity,
        arrivalDate,
        departureDate,
        travelers,
        budget,
        source: "create_trip",
        returnStep: "3",
        returnUrl: "/travelix/trips/create_trip.php"
    });

    window.location.href = `/travelix/hotel/hotels.php?${params.toString()}`;
});

handleHotelOptionChange();
</script>