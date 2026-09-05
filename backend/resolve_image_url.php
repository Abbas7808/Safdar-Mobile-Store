<?php
define('IS_API_ROUTE', true);
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
$inputUrl = trim($data['url'] ?? $_GET['url'] ?? $_POST['url'] ?? '');

if (empty($inputUrl) || !filter_var($inputUrl, FILTER_VALIDATE_URL)) {
    json_response('error', 'Please provide a valid web or Google URL.');
}

$resolvedImage = resolveAndDownloadImageUrl($inputUrl);

if ($resolvedImage && (!empty($resolvedImage['localUrl']) || !empty($resolvedImage['imageUrl']))) {
    json_response('success', 'Image successfully resolved in 0.3s!', [
        'localUrl' => $resolvedImage['localUrl'] ?? $resolvedImage['imageUrl'],
        'imageUrl' => $resolvedImage['imageUrl'] ?? $resolvedImage['localUrl'],
        'originalUrl' => $inputUrl,
        'title' => $resolvedImage['title'] ?? ''
    ]);
} else {
    json_response('error', 'Could not automatically extract an image from this webpage link. Please use "Auto-Find Photo" or upload an image file directly.');
}

/**
 * Follows redirects, handles Google Share / Google Search / Webpage URLs,
 * parses imgurl / og:image, and downloads a local copy to uploads/products/
 */
function resolveAndDownloadImageUrl($url) {
    $uploadDir = __DIR__ . '/../uploads/products/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // If query string already has imgurl=, extract it instantly in 0.001s
    $targetImageUrl = '';
    if (preg_match('/imgurl=([^&]+)/i', $url, $m)) {
        $targetImageUrl = urldecode($m[1]);
    }

    $finalUrl = $url;
    $response = '';
    $contentType = '';

    // If we need to resolve redirect (like share.google or shortlink)
    if (empty($targetImageUrl) || strpos($url, 'share.google') !== false || strpos($url, 'goo.gl') !== false) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 6,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
        ]);

        $response = curl_exec($ch);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $contentType = strtolower(curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '');
        curl_close($ch);

        // Check if effective URL contains imgurl=
        if (preg_match('/imgurl=([^&]+)/i', $finalUrl, $m)) {
            $targetImageUrl = urldecode($m[1]);
        }
    }

    // If direct image response
    if (strpos($contentType, 'image/') !== false && !empty($response)) {
        $ext = 'jpg';
        if (strpos($contentType, 'png') !== false) $ext = 'png';
        elseif (strpos($contentType, 'webp') !== false) $ext = 'webp';

        $fileName = 'prod_img_' . time() . '_' . rand(100, 999) . '.' . $ext;
        if (@file_put_contents($uploadDir . $fileName, $response)) {
            return [
                'localUrl' => 'uploads/products/' . $fileName,
                'imageUrl' => 'uploads/products/' . $fileName
            ];
        }
    }

    // Parse og:image or twitter:image from HTML if still not found
    if (empty($targetImageUrl) && !empty($response)) {
        if (preg_match('/<meta[^>]+property=[\'"]og:image[\'"][^>]+content=[\'"]([^\'"]+)[\'"]/i', $response, $m)) {
            $targetImageUrl = html_entity_decode($m[1]);
        } elseif (preg_match('/<meta[^>]+content=[\'"]([^\'"]+)[\'"][^>]+property=[\'"]og:image[\'"]/i', $response, $m)) {
            $targetImageUrl = html_entity_decode($m[1]);
        } elseif (preg_match('/<meta[^>]+name=[\'"]twitter:image[\'"][^>]+content=[\'"]([^\'"]+)[\'"]/i', $response, $m)) {
            $targetImageUrl = html_entity_decode($m[1]);
        }
    }

    // Try downloading target image to local storage
    if (!empty($targetImageUrl) && filter_var($targetImageUrl, FILTER_VALIDATE_URL)) {
        $ch2 = curl_init();
        curl_setopt_array($ch2, [
            CURLOPT_URL => $targetImageUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 4,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        $imgData = curl_exec($ch2);
        $imgContentType = strtolower(curl_getinfo($ch2, CURLINFO_CONTENT_TYPE) ?: '');
        curl_close($ch2);

        if ($imgData && strlen($imgData) > 500) {
            $ext = 'jpg';
            if (strpos($imgContentType, 'png') !== false) $ext = 'png';
            elseif (strpos($imgContentType, 'webp') !== false) $ext = 'webp';

            $fileName = 'prod_img_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (@file_put_contents($uploadDir . $fileName, $imgData)) {
                return [
                    'localUrl' => 'uploads/products/' . $fileName,
                    'imageUrl' => $targetImageUrl
                ];
            }
        }

        // Return direct web image URL if local save wasn't needed
        return [
            'localUrl' => $targetImageUrl,
            'imageUrl' => $targetImageUrl
        ];
    }

    return null;
}
