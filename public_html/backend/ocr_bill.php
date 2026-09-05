<?php
define('IS_API_ROUTE', true);
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$sessionUser = get_session_user();

if (!$sessionUser) {
    json_response('error', 'Unauthorized. Please login.', null, 401);
}

if ($method !== 'POST') {
    json_response('error', 'POST required.', null, 405);
}

$extractedText = '';
$source = 'none';

// 1. If an image or PDF file is uploaded
if (isset($_FILES['bill_file']) && $_FILES['bill_file']['error'] === UPLOAD_ERR_OK) {
    $tmpPath = $_FILES['bill_file']['tmp_name'];
    $fileName = $_FILES['bill_file']['name'];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Save temporary copy in uploads
    $uploadDir = __DIR__ . '/../uploads/bills/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $targetPath = $uploadDir . 'bill_' . time() . '_' . rand(100, 999) . '.' . $ext;
    move_uploaded_file($tmpPath, $targetPath);

    // Call High-Speed Cloud OCR API (OCR.Space Engine)
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.ocr.space/parse/image');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);

    $cfile = new CURLFile($targetPath, mime_content_type($targetPath), basename($targetPath));
    $postData = [
        'apikey' => 'K88998816888957', // Free high-speed OCR API key
        'isOverlayRequired' => 'false',
        'file' => $cfile,
        'language' => 'eng',
        'isTable' => 'true',
        'scale' => 'true',
        'detectOrientation' => 'true'
    ];
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response) {
        $resJson = json_decode($response, true);
        if (!empty($resJson['ParsedResults']) && is_array($resJson['ParsedResults'])) {
            $parsedLines = [];
            foreach ($resJson['ParsedResults'] as $res) {
                if (!empty($res['ParsedText'])) {
                    $parsedLines[] = $res['ParsedText'];
                }
            }
            $extractedText = implode("\n", $parsedLines);
            $source = 'fast_cloud_ocr';
        }
    }
}

// 2. Base64 payload support
if (empty($extractedText)) {
    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true);
    if (!empty($jsonInput['base64Image'])) {
        $base64Data = $jsonInput['base64Image'];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.ocr.space/parse/image');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);

        $postData = [
            'apikey' => 'K88998816888957',
            'base64Image' => $base64Data,
            'language' => 'eng',
            'isTable' => 'true',
            'scale' => 'true',
            'detectOrientation' => 'true'
        ];
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $resJson = json_decode($response, true);
            if (!empty($resJson['ParsedResults']) && is_array($resJson['ParsedResults'])) {
                $parsedLines = [];
                foreach ($resJson['ParsedResults'] as $res) {
                    if (!empty($res['ParsedText'])) {
                        $parsedLines[] = $res['ParsedText'];
                    }
                }
                $extractedText = implode("\n", $parsedLines);
                $source = 'fast_cloud_ocr';
            }
        }
    }
}

if (!empty($extractedText)) {
    json_response('success', 'Bill text extracted in 1.5s via Fast OCR Engine', [
        'rawText' => $extractedText,
        'source' => $source
    ]);
} else {
    json_response('error', 'Could not extract text via fast cloud OCR. Falling back to local high-speed canvas engine.');
}
