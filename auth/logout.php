<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Clear session data
|--------------------------------------------------------------------------
*/
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

/*
|--------------------------------------------------------------------------
| Redirect target after logout
|--------------------------------------------------------------------------
*/
$redirectUrl = '/travelix/auth/login.php'; // change if you want homepage instead
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="../images/favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out...</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/travelix/assets/js/travelix_swal_autoclose.js"></script>
</head>
<body>

<script>
    const redirectUrl = <?php echo json_encode($redirectUrl); ?>;

    Swal.fire({
        title: 'Logging out...',
        text: 'Please wait while we securely sign you out.',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    setTimeout(() => {
        Swal.fire({
            icon: 'success',
            title: 'Logged out successfully',
            text: 'You have been signed out of your account.',
            confirmButtonText: 'OK',
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then(() => {
            window.location.href = redirectUrl;
        });
    }, 1200);
</script>

</body>
</html>