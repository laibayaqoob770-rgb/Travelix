<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$baseUrl = '/travelix';

if (!isset($_SESSION['user']) || empty($_SESSION['user']['uid'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in.'
    ]);
    exit;
}

$currentUser = $_SESSION['user'] ?? [];
$userRole = strtolower((string)($currentUser['role'] ?? 'user'));

if ($userRole !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Only admin can delete users.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}

$uid = trim((string)($_POST['uid'] ?? ''));
$currentAdminUid = (string)($currentUser['uid'] ?? '');

if ($uid === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'User UID is required.'
    ]);
    exit;
}

if ($uid === $currentAdminUid) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'You cannot delete your own admin account.'
    ]);
    exit;
}

$serviceAccountPath = dirname(__DIR__) . '/config/firebase-service-account.json';

if (!file_exists($serviceAccountPath)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Firebase service account file not found in /config/firebase-service-account.json'
    ]);
    exit;
}

class DeleteUserAuthNotFound extends Exception {}

try {
    $serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);

    if (!$serviceAccount || empty($serviceAccount['project_id'])) {
        throw new Exception('Invalid Firebase service account JSON.');
    }

    $projectId = $serviceAccount['project_id'];

    function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    function getGoogleAccessToken(string $serviceAccountPath): string
    {
        $serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);
        $now = time();

        $header = base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claimSet = base64UrlEncode(json_encode([
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/datastore https://www.googleapis.com/auth/identitytoolkit',
            'aud' => $serviceAccount['token_uri'],
            'exp' => $now + 3600,
            'iat' => $now
        ]));

        $signature = '';
        openssl_sign("$header.$claimSet", $signature, $serviceAccount['private_key'], 'SHA256');
        $jwt = "$header.$claimSet." . base64UrlEncode($signature);

        $ch = curl_init($serviceAccount['token_uri']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 20
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $tokenData = json_decode($response, true);

        if (empty($tokenData['access_token'])) {
            throw new Exception('Unable to fetch Google access token.');
        }

        return $tokenData['access_token'];
    }

    function firebaseAuthDeleteUser(string $projectId, string $uid, string $accessToken): void
    {
        $url = "https://identitytoolkit.googleapis.com/v1/projects/{$projectId}/accounts:delete";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode(['localId' => $uid]),
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception('Firebase Auth delete cURL error: ' . $curlError);
        }

        if ($httpCode === 200) {
            return;
        }

        $decoded = json_decode($response, true);
        $errorMessage = (string) ($decoded['error']['message'] ?? '');

        if ($errorMessage === 'USER_NOT_FOUND') {
            throw new DeleteUserAuthNotFound('User already missing from Firebase Authentication.');
        }

        throw new Exception('Firebase Auth delete failed with HTTP ' . $httpCode . '. Response: ' . $response);
    }

    function firestoreDeleteDocument(string $projectId, string $collection, string $documentId, string $accessToken): void
    {
        $encodedCollection = rawurlencode($collection);
        $encodedDocId = rawurlencode($documentId);

        $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$encodedCollection}/{$encodedDocId}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception('Firestore DELETE cURL error: ' . $curlError);
        }

        if (!in_array($httpCode, [200, 404], true)) {
            throw new Exception('Firestore delete failed for collection "' . $collection . '" with HTTP ' . $httpCode . '. Response: ' . $response);
        }
    }

    function firestoreRunQuery(string $projectId, string $collection, string $fieldName, string $fieldValue, string $accessToken): array
    {
        $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents:runQuery";

        $payload = [
            'structuredQuery' => [
                'from' => [
                    ['collectionId' => $collection]
                ],
                'where' => [
                    'fieldFilter' => [
                        'field' => ['fieldPath' => $fieldName],
                        'op' => 'EQUAL',
                        'value' => ['stringValue' => $fieldValue]
                    ]
                ]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception('Firestore query cURL error: ' . $curlError);
        }

        if ($httpCode !== 200) {
            throw new Exception('Firestore query failed for collection "' . $collection . '" with HTTP ' . $httpCode . '. Response: ' . $response);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return [];
        }

        $documentNames = [];

        foreach ($decoded as $row) {
            if (!empty($row['document']['name'])) {
                $documentNames[] = $row['document']['name'];
            }
        }

        return $documentNames;
    }

    function firestoreDeleteByDocumentName(string $documentName, string $accessToken): void
    {
        $url = 'https://firestore.googleapis.com/v1/' . $documentName;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception('Firestore related delete cURL error: ' . $curlError);
        }

        if (!in_array($httpCode, [200, 404], true)) {
            throw new Exception('Firestore related document delete failed with HTTP ' . $httpCode . '. Response: ' . $response);
        }
    }

    $accessToken = getGoogleAccessToken($serviceAccountPath);

    // Delete user document from Firestore users collection
    firestoreDeleteDocument($projectId, 'users', $uid, $accessToken);

    // Delete related trips where uid == user uid
    $tripDocs = firestoreRunQuery($projectId, 'trips', 'uid', $uid, $accessToken);
    foreach ($tripDocs as $docName) {
        firestoreDeleteByDocumentName($docName, $accessToken);
    }

    // Delete related hotel bookings where uid == user uid
    $hotelDocs = firestoreRunQuery($projectId, 'hotel_bookings', 'uid', $uid, $accessToken);
    foreach ($hotelDocs as $docName) {
        firestoreDeleteByDocumentName($docName, $accessToken);
    }

    // Delete from Firebase Authentication
    try {
        firebaseAuthDeleteUser($projectId, $uid, $accessToken);
    } catch (DeleteUserAuthNotFound $e) {
        // Ignore if already missing in Auth
    }

    echo json_encode([
        'success' => true,
        'message' => 'User deleted successfully from Firestore and Authentication.'
    ]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Delete failed: ' . $e->getMessage()
    ]);
    exit;
}