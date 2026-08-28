<?php
session_start();

/*
|--------------------------------------------------------------------------
| Login Check
|--------------------------------------------------------------------------
| Change these session keys if your project uses a different login session.
| Example:
|   $_SESSION['user_id']
|   $_SESSION['id']
|   $_SESSION['user']
|   $_SESSION['email']
*/
$isLoggedIn = isset($_SESSION['user_id']) || isset($_SESSION['id']) || isset($_SESSION['user']) || isset($_SESSION['email']);

$loggedInRole = strtolower((string)($_SESSION['user']['role'] ?? ''));
if ($loggedInRole === 'admin') {
    header('Location: /travelix/dashboard/admin_dashboard.php');
    exit;
}

$destinations = [
    [
        'name' => 'Lahore',
        'image' => 'lahore.jpg',
        'description' => 'Lahore is the cultural heart of Pakistan, famous for its historic forts, lively food streets, Mughal architecture, and vibrant city life.'
    ],
    [
        'name' => 'Karachi',
        'image' => 'karachi.jpg',
        'description' => 'Karachi is Pakistan’s largest coastal city, known for beaches, shopping, diverse food, fast-paced urban energy, and beautiful seaside views.'
    ],
    [
        'name' => 'Islamabad',
        'image' => 'islamabad.jpg',
        'description' => 'Islamabad offers scenic hills, peaceful roads, modern city planning, clean surroundings, and popular attractions like Faisal Mosque and Daman-e-Koh.'
    ],
    [
        'name' => 'Murree',
        'image' => 'murree.jpg',
        'description' => 'Murree is a charming hill station with cool weather, pine-covered mountains, family-friendly resorts, and breathtaking valley views.'
    ],
    [
        'name' => 'Hunza',
        'image' => 'hunza.jpg',
        'description' => 'Hunza is known for majestic mountains, crystal-clear lakes, historic forts, and unforgettable natural beauty in northern Pakistan.'
    ],
    [
        'name' => 'Skardu',
        'image' => 'skardu.jpg',
        'description' => 'Skardu is a dream destination for adventure lovers, surrounded by lakes, deserts, mountains, and stunning trekking routes.'
    ],
    [
        'name' => 'Swat',
        'image' => 'swat.jpg',
        'description' => 'Swat is often called the Switzerland of Pakistan, offering green valleys, rivers, waterfalls, and peaceful hill towns.'
    ],
    [
        'name' => 'Quetta',
        'image' => 'quetta.jpg',
        'description' => 'Quetta is surrounded by rugged mountains and is known for its cool climate, fruit orchards, and unique cultural heritage.'
    ],
    [
        'name' => 'Peshawar',
        'image' => 'peshawar.jpg',
        'description' => 'Peshawar is one of South Asia’s oldest cities, rich in history, traditional markets, cultural stories, and authentic cuisine.'
    ],
    [
        'name' => 'Naran',
        'image' => 'naran.jpg',
        'description' => 'Naran is a breathtaking valley destination famous for cool weather, green landscapes, river views, and nearby alpine lakes.'
    ],
    [
        'name' => 'Kaghan',
        'image' => 'kaghan.jpg',
        'description' => 'Kaghan Valley offers rivers, pine forests, mountain roads, and peaceful views that make it perfect for family trips.'
    ],
    [
        'name' => 'Neelum Valley',
        'image' => 'neelum_valley.jpg',
        'description' => 'Neelum Valley is loved for its lush greenery, flowing rivers, wooden villages, and postcard-worthy mountain scenery.'
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Travelix — plan smarter trips across Pakistan with guided city tours, hotel booking, and personalized itineraries for Lahore, Hunza, Skardu, Murree, and more.">
<title>Travelix Dashboard</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="image" fetchpriority="high" href="/travelix/images/slider1.webp">

<link href="/travelix/assets/vendor/bootstrap.min.css" rel="stylesheet">
<link href="/travelix/assets/css/travelix_3d_effects.css" rel="stylesheet">

<style>
body {
    margin: 0;
    font-family: 'Poppins', sans-serif;
    background: #ffffff;
    color: #1e293b;
}

.hero-navbar-wrap {
    position: absolute;
    top: 20px;
    width: 100%;
    z-index: 1000;
}

.hero-slider {
    position: relative;
    height: 100vh;
    overflow: hidden;
}

.hero-slider .carousel-inner img {
    width: 100%;
    height: 100vh;
    object-fit: cover;
}

.hero-overlay {
    position: absolute;
    top: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(to bottom, rgba(0,0,0,0.38), rgba(0,0,0,0.30));
}

.hero-content {
    position: absolute;
    top: 48%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    color: #fff;
    width: 90%;
    max-width: 900px;
    z-index: 2;
}

.hero-content h1 {
    font-size: 70px;
    font-weight: 800;
    margin-bottom: 14px;
    line-height: 1.1;
}

.hero-content p {
    font-size: 18px;
    margin-top: 0;
    margin-bottom: 22px;
}

.hero-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-top: 8px;
    background: #1484B4;
    padding: 15px 35px;
    border-radius: 40px;
    color: #fff;
    text-decoration: none;
    font-weight: 700;
    transition: 0.3s ease;
}

.hero-btn:hover {
    background: #0f6d95;
    color: #fff;
    transform: translateY(-2px);
}

.guides-box {
    position: absolute;
    bottom: 40px;
    left: 40px;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 15px;
    z-index: 2;
    background: rgba(255,255,255,0.12);
    padding: 10px 16px;
    border-radius: 40px;
    backdrop-filter: blur(6px);
}

.guides-box img {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    border: 3px solid #fff;
    margin-left: -10px;
    object-fit: cover;
}

.guides-box span {
    font-weight: 600;
}

.section {
    padding: 56px 0;
    text-align: center;
}

.section-title {
    font-size: 42px;
    font-weight: 800;
    margin-bottom: 14px;
    color: #0f172a;
}

.section-subtitle {
    font-size: 17px;
    color: #57667a;
    max-width: 800px;
    margin: 0 auto 20px;
}

.destinations-grid {
    margin-top: 40px;
}

.card-destination {
    background: #ffffff;
    border-radius: 24px;
    overflow: hidden;
    transition: 0.35s ease;
    box-shadow: 0 14px 40px rgba(15, 23, 42, 0.08);
    height: 100%;
    text-align: left;
    border: 1px solid rgba(15, 23, 42, 0.05);
}

/* Hover lift is now handled by the mousemove-driven .tilt-card transform
   (assets/css/travelix_3d_effects.css) — a static translateY here would
   fight with that transform on every hover. */
.card-destination:hover {
    box-shadow: 0 20px 50px rgba(15, 23, 42, 0.14);
}

.card-destination img {
    width: 100%;
    height: 250px;
    object-fit: cover;
}

.card-destination-body {
    padding: 22px 22px 24px;
}

.card-destination h3 {
    font-size: 24px;
    font-weight: 800;
    margin-bottom: 12px;
    color: #0f172a;
}

.card-destination p {
    font-size: 15px;
    line-height: 1.7;
    color: #475569;
    margin-bottom: 18px;
    min-height: 105px;
}

.destination-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #1484B4;
    color: #fff;
    padding: 11px 24px;
    border-radius: 40px;
    text-decoration: none;
    font-weight: 700;
    transition: 0.3s ease;
}

.destination-btn:hover {
    background: #0f6d95;
    color: #fff;
}

.journey-card {
    background: #ffffff;
    border-radius: 24px;
    padding: 18px 18px 24px;
    box-shadow: 0 14px 40px rgba(15, 23, 42, 0.08);
    transition: 0.3s ease;
    height: 100%;
}

.journey-card:hover {
    transform: translateY(-8px);
}

.journey-card img {
    border-radius: 20px;
    width: 100%;
    height: 260px;
    object-fit: cover;
}

.journey-card h3 {
    margin-top: 18px;
    font-weight: 800;
    color: #0f172a;
}

.journey-card p {
    margin-top: 10px;
    color: #57667a;
    line-height: 1.7;
}

.main-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #1484B4;
    color: #fff;
    padding: 15px 40px;
    border-radius: 40px;
    text-decoration: none;
    font-weight: bold;
    transition: 0.3s ease;
}

.main-btn:hover {
    background: #0f6d95;
    color: #fff;
    transform: translateY(-2px);
}

.feedback-carousel-wrap {
    max-width: 1200px;
    margin: 40px auto 0;
    border-radius: 28px;
    overflow: hidden;
    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12);
}

.feedback-slide-img {
    width: 100%;
    height: 480px;
    object-fit: cover;
}

.final-cta {
    background: linear-gradient(135deg, #f8fbff, #eef7fb);
    padding: 90px 20px;
    border-radius: 30px;
    margin: 0 auto 40px;
    max-width: 1280px;
}

.final-cta h2 {
    font-size: 44px;
    font-weight: 800;
    margin-bottom: 20px;
    color: #0f172a;
}

@media (max-width: 992px) {
    .hero-content h1 {
        font-size: 52px;
    }

    .card-destination p {
        min-height: auto;
    }

    .feedback-slide-img {
        height: 360px;
    }
}

@media (max-width: 768px) {
    .hero-slider,
    .hero-slider .carousel-inner img {
        height: 90vh;
    }

    .hero-content h1 {
        font-size: 40px;
    }

    .hero-content p {
        font-size: 16px;
    }

    .guides-box {
        left: 18px;
        right: 18px;
        bottom: 22px;
        justify-content: center;
        flex-wrap: wrap;
    }

    .section {
        padding: 44px 0;
    }

    .section-title {
        font-size: 32px;
    }

    .final-cta h2 {
        font-size: 32px;
    }

    .feedback-slide-img {
        height: 280px;
    }
}

@media (max-width: 576px) {
    .hero-content h1 {
        font-size: 32px;
    }

    .hero-btn,
    .main-btn,
    .destination-btn {
        width: 100%;
        max-width: 280px;
    }

    .card-destination img {
        height: 220px;
    }
}
</style>
</head>

<body>

<div class="hero-navbar-wrap">
    <?php include __DIR__ . '/../includes/user_top_navbar.php'; ?>
</div>

<?php if ($isLoggedIn): ?>
<div id="payoutAccountBanner" style="display:none;position:absolute;top:150px;left:0;width:100%;z-index:500;box-sizing:border-box;padding:0 40px;">
    <div style="max-width:1280px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;border-radius:16px;padding:14px 18px;font-size:14px;box-shadow:0 8px 24px rgba(0,0,0,0.08);">
        <span><i class="fa-solid fa-triangle-exclamation" style="margin-right:8px;"></i>Add your payout account details so refunds can be sent to you if you ever cancel a booking.</span>
        <a href="/travelix/profile/manage_user_profile.php" style="background:#c2410c;color:#fff;padding:8px 16px;border-radius:10px;font-weight:700;text-decoration:none;white-space:nowrap;">Add Now</a>
    </div>
</div>
<?php endif; ?>

<div id="heroCarousel" class="carousel slide hero-slider parallax-hero" data-bs-ride="carousel">
    <div class="carousel-inner">
        <?php for ($i = 1; $i <= 5; $i++): ?>
            <div class="carousel-item <?= $i == 1 ? 'active' : '' ?>">
                <img src="/travelix/images/slider<?= $i ?>.webp" alt="Slider <?= $i ?>" width="1920" height="1280"
                     <?= $i == 1 ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
            </div>
        <?php endfor; ?>
    </div>

    <div class="hero-overlay"></div>

    <div class="hero-content">
        <div class="parallax-layer" data-depth="14">
            <h1>Travel Without Stress</h1>
            <p>Explore Pakistan with ease, comfort, smart planning, and unforgettable memories.</p>

            <a href="<?= $isLoggedIn ? '/travelix/trips/create_trip.php' : '#' ?>"
               class="hero-btn <?= $isLoggedIn ? 'loader-link' : 'login-required-btn' ?>"
               data-href="<?= $isLoggedIn ? '/travelix/trips/create_trip.php' : '' ?>">
                Plan Your Trip
            </a>
        </div>
    </div>

    <div class="guides-box parallax-layer" data-depth="6">
        <img src="/travelix/images/user1.png" alt="Guide 1" width="45" height="45">
        <img src="/travelix/images/user2.png" alt="Guide 2" width="45" height="45">
        <img src="/travelix/images/user3.png" alt="Guide 3" width="45" height="45">
        <span>10+ Guides Available</span>
    </div>
</div>

<div class="section">
    <div class="container">
        <h2 class="section-title">Explore Exotic Destinations</h2>
        <p class="section-subtitle">
            Discover some of Pakistan’s most loved travel destinations, each offering its own charm,
            scenery, culture, and unforgettable experiences.
        </p>

        <div class="row destinations-grid g-4">
            <?php foreach ($destinations as $destination): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="card-destination tilt-card">
                        <img src="/travelix/images/<?= htmlspecialchars($destination['image']); ?>" alt="<?= htmlspecialchars($destination['name']); ?>" width="400" height="250" loading="lazy">
                        <div class="card-destination-body">
                            <h3><?= htmlspecialchars($destination['name']); ?></h3>
                            <p><?= htmlspecialchars($destination['description']); ?></p>
                            <a href="/travelix/plan_guides/geoCategory.php?city=<?= urlencode($destination['name']); ?>"
                            class="destination-btn loader-link"
                            data-href="/travelix/plan_guides/geoCategory.php?city=<?= urlencode($destination['name']); ?>">
                                View Details
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="section journey">
    <div class="container">
        <h2 class="section-title">Journey Solutions</h2>
        <p class="section-subtitle">We help you organize, manage, and enjoy your travel experience in a smarter way.</p>

        <div class="row mt-5 g-4">
            <div class="col-md-4">
                <div class="journey-card">
                    <img src="/travelix/images/j1.jpg" alt="Day Planning" width="400" height="260" loading="lazy">
                    <h3>Day Planning</h3>
                    <p>Organize each day of your journey with better route planning, place selection, and activity scheduling.</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="journey-card">
                    <img src="/travelix/images/j2.jpg" alt="Hotel Booking" width="400" height="260" loading="lazy">
                    <h3>Hotel Booking</h3>
                    <p>Book verified hotels directly through Travelix with real-time room availability and secure payment confirmation.</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="journey-card">
                    <img src="/travelix/images/j3.jpg" alt="Trip Planning" width="400" height="260" loading="lazy">
                    <h3>Trip Planning</h3>
                    <p>Set your dates, budget, and travelers, then let Travelix suggest destinations, weather insights, and a ready itinerary.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="section">
    <div class="container">
        <h2 class="section-title">User Travel Moments</h2>
        <p class="section-subtitle">Beautiful memories shared by travelers after completing their journeys with Travelix.</p>

        <div class="feedback-carousel-wrap">
            <div id="feedbackCarousel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-inner">
                    <?php for ($i = 1; $i <= 3; $i++): ?>
                        <div class="carousel-item <?= $i == 1 ? 'active' : '' ?>">
                            <img src="/travelix/images/feedback<?= $i ?>.jpg" class="feedback-slide-img" alt="Feedback <?= $i ?>" width="800" height="480" loading="lazy">
                        </div>
                    <?php endfor; ?>
                </div>

                <button class="carousel-control-prev" type="button" data-bs-target="#feedbackCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#feedbackCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <div class="final-cta text-center">
        <h2>Explore Pakistan With Travelix</h2>

        <a href="<?= $isLoggedIn ? '/travelix/trips/create_trip.php' : '#' ?>"
           class="main-btn <?= $isLoggedIn ? 'loader-link' : 'login-required-btn' ?>"
           data-href="<?= $isLoggedIn ? '/travelix/trips/create_trip.php' : '' ?>">
            Start Your Journey
        </a>
    </div>
</div>

<?php include __DIR__ . '/../includes/user_bottom_footer.php'; ?>

<script>
document.querySelectorAll('.loader-link').forEach(link => {
    link.addEventListener('click', function(e) {
        const targetUrl = this.dataset.href;

        if (!targetUrl || targetUrl === '#') {
            return;
        }

        e.preventDefault();

        Swal.fire({
            title: 'Loading...',
            text: 'Please wait while we take you there',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });

        setTimeout(() => {
            window.location.href = targetUrl;
        }, 700);
    });
});

document.querySelectorAll('.login-required-btn').forEach(button => {
    button.addEventListener('click', function(e) {
        e.preventDefault();

        Swal.fire({
            icon: 'warning',
            title: 'Login Required',
            text: 'Please log in first to continue your trip planning.',
            confirmButtonText: 'Go to Login',
            confirmButtonColor: '#1484B4'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Loading...',
                    text: 'Please wait while we take you to login',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading()
                });

                setTimeout(() => {
                    window.location.href = '/travelix/auth/login.php';
                }, 700);
            }
        });
    });
});
</script>

<script src="/travelix/assets/vendor/bootstrap.bundle.min.js"></script>

<?php if ($isLoggedIn): ?>
<script type="module">
    import { firebaseConfig } from "/travelix/config/firebase-config.js";
    import { initializeApp, getApp, getApps } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-app.js";
    import { getAuth, onAuthStateChanged } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-auth.js";
    import { getFirestore, doc, getDoc } from "https://www.gstatic.com/firebasejs/10.12.5/firebase-firestore.js";

    const app = getApps().length ? getApp() : initializeApp(firebaseConfig);
    const auth = getAuth(app);
    const db = getFirestore(app);

    onAuthStateChanged(auth, async (user) => {
        if (!user) return;
        try {
            const snap = await getDoc(doc(db, "users", user.uid));
            const data = snap.exists() ? snap.data() : {};
            if (!data.paymentAccountNumber) {
                const banner = document.getElementById("payoutAccountBanner");
                if (banner) banner.style.display = "block";
            }
        } catch (error) {
            console.warn("Could not check payout account details:", error);
        }
    });
</script>
<?php endif; ?>

<script src="/travelix/assets/js/travelix_3d_effects.js"></script>
</body>
</html>