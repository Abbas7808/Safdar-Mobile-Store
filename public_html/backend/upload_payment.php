<?php
define('IS_API_ROUTE', true);
require_once __DIR__ . '/config.php';

// Public storefront & admin payment screenshot uploader
$currentUser = null;
if (isset($_SESSION['user'])) {
    $currentUser = $_SESSION['user'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/payments/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileTmpPath = $_FILES['payment_proof']['tmp_name'];
        $fileName = $_FILES['payment_proof']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        if (in_array($fileExtension, $allowedExtensions)) {
            // Verify MIME Type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $fileTmpPath);
            finfo_close($finfo);

            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
            if (!in_array($mimeType, $allowedMimes)) {
                json_response('error', 'Invalid file contents. Only valid image & PDF files allowed.');
            }

            $newFileName = 'pay_' . time() . '_' . rand(1000, 9999) . '.' . $fileExtension;
            $destPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $publicUrl = 'uploads/payments/' . $newFileName;
                $username = $currentUser['username'] ?? 'customer_online';
                $role = $currentUser['role'] ?? 'guest';
                SecurityLogger::logEvent($username, $role, 'PAYMENT_PROOF_UPLOADED', "Uploaded payment proof file {$newFileName}");
                json_response('success', 'Payment proof screenshot uploaded successfully', [
                    'url' => $publicUrl,
                    'fileName' => $newFileName
                ]);
            } else {
                json_response('error', 'Failed to move uploaded file');
            }
        } else {
            json_response('error', 'Invalid file type. Only JPG, PNG, WEBP & PDF allowed');
        }
    } else {
        json_response('error', 'No payment proof file uploaded');
    }
} else {
    json_response('error', 'Invalid request method');
}
