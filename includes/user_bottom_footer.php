<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentYear = date('Y');
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
    :root {
        --footer-bg: #055f73;
        --footer-bg-dark: #044f60;
        --footer-text: #f4f8fb;
        --footer-muted: rgba(255, 255, 255, 0.86);
        --footer-border: rgba(255, 255, 255, 0.16);
        --footer-hover: #9fe7ff;
    }

    .travelix-footer {
        background: linear-gradient(180deg, var(--footer-bg) 0%, var(--footer-bg-dark) 100%);
        color: var(--footer-text);
        padding: 80px 0 28px;
        margin-top: 80px;
        position: relative;
        overflow: hidden;
        font-family: 'Poppins', sans-serif;
    }

    .travelix-footer::before,
    .travelix-footer::after {
        content: "";
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.04);
        z-index: 0;
    }

    .travelix-footer::before {
        width: 280px;
        height: 280px;
        top: -90px;
        left: -70px;
    }

    .travelix-footer::after {
        width: 220px;
        height: 220px;
        right: -50px;
        bottom: -60px;
    }

    .travelix-footer .container {
        position: relative;
        z-index: 1;
        max-width: 1500px;
    }

    .travelix-footer-main {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr;
        gap: 70px;
        align-items: flex-start;
    }

    .travelix-footer-brand h2 {
        font-size: 3rem;
        font-weight: 500;
        margin-bottom: 28px;
        color: #ffffff;
        letter-spacing: 0.3px;
    }

    .travelix-footer-brand p {
        font-size: 1.05rem;
        line-height: 1.85;
        color: var(--footer-muted);
        max-width: 760px;
        margin: 0;
    }

    .travelix-footer-title {
        font-size: 2rem;
        font-weight: 500;
        margin-bottom: 26px;
        color: #ffffff;
    }

    .travelix-footer-links {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .travelix-footer-links li {
        margin-bottom: 18px;
    }

    .travelix-footer-links a {
        color: var(--footer-muted);
        text-decoration: none;
        font-size: 1.05rem;
        font-weight: 400;
        transition: all 0.28s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        position: relative;
    }

    .travelix-footer-links a::after {
        content: '';
        position: absolute;
        left: 0;
        bottom: -3px;
        width: 0;
        height: 2px;
        background: var(--footer-hover);
        transition: width 0.28s ease;
    }

    .travelix-footer-links a:hover {
        color: #ffffff;
        transform: translateX(4px);
    }

    .travelix-footer-links a:hover::after {
        width: 100%;
    }

    .travelix-footer-socials {
        display: flex;
        align-items: center;
        gap: 18px;
        flex-wrap: wrap;
        margin-top: 6px;
    }

    .travelix-footer-socials a {
        width: 54px;
        height: 54px;
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.09);
        border: 1px solid rgba(255, 255, 255, 0.12);
        transition: all 0.3s ease;
        text-decoration: none;
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.10);
    }

    .travelix-footer-socials a:hover {
        transform: translateY(-4px) scale(1.04);
        background: rgba(255, 255, 255, 0.16);
        border-color: rgba(255, 255, 255, 0.22);
        box-shadow: 0 14px 28px rgba(0, 0, 0, 0.18);
    }

    .travelix-footer-socials img {
        width: 26px;
        height: 26px;
        object-fit: contain;
        display: block;
    }

    .travelix-footer-divider {
        border: none;
        border-top: 1px solid var(--footer-border);
        margin: 70px 0 28px;
    }

    .travelix-footer-bottom {
        text-align: center;
    }

    .travelix-footer-bottom p {
        margin: 0;
        font-size: 1rem;
        color: var(--footer-muted);
        font-weight: 400;
    }

    @media (max-width: 1199px) {
        .travelix-footer-main {
            grid-template-columns: 1.8fr 1fr 1fr;
            gap: 45px;
        }

        .travelix-footer-brand h2,
        .travelix-footer-title {
            font-size: 2.3rem;
        }
    }

    @media (max-width: 991px) {
        .travelix-footer {
            padding: 65px 0 26px;
        }

        .travelix-footer-main {
            grid-template-columns: 1fr;
            gap: 38px;
        }

        .travelix-footer-brand h2,
        .travelix-footer-title {
            font-size: 2rem;
        }

        .travelix-footer-divider {
            margin: 45px 0 24px;
        }
    }

    @media (max-width: 576px) {
        .travelix-footer {
            padding: 55px 0 24px;
        }

        .travelix-footer-brand h2,
        .travelix-footer-title {
            font-size: 1.8rem;
        }

        .travelix-footer-brand p,
        .travelix-footer-links a,
        .travelix-footer-bottom p {
            font-size: 0.97rem;
        }

        .travelix-footer-socials a {
            width: 48px;
            height: 48px;
        }

        .travelix-footer-socials img {
            width: 22px;
            height: 22px;
        }
    }
</style>

<footer class="travelix-footer">
    <div class="container">
        <div class="travelix-footer-main">

            <div class="travelix-footer-brand">
                <h2>Travelix</h2>
                <p>
                    Discover the beauty of Pakistan with smart trip planning, guided tours,
                    hotel booking, and breathtaking destinations crafted for every traveler.
                    From the misty valleys of the north to the vibrant streets of the south,
                    we help you plan every step of your journey with ease and confidence.
                </p>
            </div>

            <div class="travelix-footer-column">
                <h3 class="travelix-footer-title">Quick Links</h3>
                <ul class="travelix-footer-links">
                    <li><a href="/travelix/dashboard/user_dashboard.php" class="footer-loader-link" data-href="/travelix/dashboard/user_dashboard.php" data-type="internal">Home</a></li>
                    <li><a href="/travelix/plan_guides/guiders.php" class="footer-loader-link" data-href="/travelix/plan_guides/guiders.php" data-type="internal">Guiders</a></li>
                    <li><a href="/travelix/hotel/hotels.php" class="footer-loader-link" data-href="/travelix/hotel/hotels.php" data-type="internal">Hotels</a></li>
                    <li><a href="/travelix/contact/contactus.php" class="footer-loader-link" data-href="/travelix/contact/contactus.php" data-type="internal">Contact Us</a></li>
                </ul>
            </div>

            <div class="travelix-footer-column">
                <h3 class="travelix-footer-title">Follow Us</h3>
                <div class="travelix-footer-socials">
                    <a href="https://www.facebook.com/traavelix/" class="footer-loader-link" data-href="https://www.facebook.com/traavelix/" data-type="external" aria-label="Facebook">
                        <img src="/travelix/images/fb_logo.png" alt="Facebook">
                    </a>

                    <a href="https://www.instagram.com/travelxglobal/" class="footer-loader-link" data-href="https://www.instagram.com/travelxglobal/" data-type="external" aria-label="Instagram">
                        <img src="/travelix/images/insta_logo.png" alt="Instagram">
                    </a>

                    <a href="https://x.com/travelex4" class="footer-loader-link" data-href="https://x.com/travelex4" data-type="external" aria-label="Twitter">
                        <img src="/travelix/images/twitter_logo.png" alt="Twitter">
                    </a>

                    <a href="https://www.youtube.com/channel/UCbWZYnRfcZ8bhnKrP2oFO2Q" class="footer-loader-link" data-href="https://www.youtube.com/channel/UCbWZYnRfcZ8bhnKrP2oFO2Q" data-type="external" aria-label="YouTube">
                        <img src="/travelix/images/youtube_logo.png" alt="YouTube">
                    </a>
                </div>
            </div>

        </div>

        <hr class="travelix-footer-divider">

        <div class="travelix-footer-bottom">
            <p>&copy; <?= $currentYear; ?> Travelix | All Rights Reserved.</p>
        </div>
    </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const footerLinks = document.querySelectorAll('.footer-loader-link');

    footerLinks.forEach(link => {
        link.addEventListener('click', function (e) {
            const targetUrl = this.getAttribute('data-href');
            const targetType = this.getAttribute('data-type');

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
                background: '#ffffff',
                color: '#1f2937',
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            setTimeout(() => {
                if (targetType === 'external') {
                    window.open(targetUrl, '_blank');
                    Swal.close();
                } else {
                    window.location.href = targetUrl;
                }
            }, 700);
        });
    });
});
</script>