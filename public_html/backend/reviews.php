<?php
define('IS_API_ROUTE', true);
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

// GET: Fetch reviews for a specific product or all reviews
if ($method === 'GET') {
    $reviews = get_json_file('reviews') ?? [];
    $productId = $_GET['productId'] ?? '';

    if (!empty($productId)) {
        $filtered = array_values(array_filter($reviews, function($r) use ($productId) {
            return ($r['productId'] ?? '') === $productId;
        }));

        // Calculate average rating
        $avgRating = 5.0;
        $count = count($filtered);
        if ($count > 0) {
            $sum = array_sum(array_column($filtered, 'rating'));
            $avgRating = round($sum / $count, 1);
        }

        json_response('success', 'Product reviews retrieved', [
            'reviews' => array_reverse($filtered),
            'count' => $count,
            'averageRating' => $avgRating
        ]);
    }

    json_response('success', 'All reviews retrieved', array_reverse($reviews));
}

// POST: Submit a new customer review (Public / Customer Facing)
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $productId = trim($input['productId'] ?? '');
    $customerName = trim($input['customerName'] ?? '');
    $customerCity = trim($input['customerCity'] ?? 'Pakistan');
    $comment = trim($input['comment'] ?? '');
    $rating = intval($input['rating'] ?? 5);

    if (empty($productId)) {
        json_response('error', 'Product ID is required');
    }
    if (empty($customerName)) {
        json_response('error', 'Please enter your name');
    }
    if (empty($comment)) {
        json_response('error', 'Please enter your review comment');
    }

    if ($rating < 1) $rating = 1;
    if ($rating > 5) $rating = 5;

    $products = get_json_file('products') ?? [];
    $prodName = 'Product';
    foreach ($products as $p) {
        if ($p['id'] === $productId) {
            $prodName = $p['name'];
            break;
        }
    }

    $reviews = get_json_file('reviews') ?? [];

    $newReview = [
        'id' => 'rev-' . time() . '-' . rand(100, 999),
        'productId' => $productId,
        'productName' => $prodName,
        'customerName' => htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8'),
        'customerCity' => htmlspecialchars($customerCity, ENT_QUOTES, 'UTF-8'),
        'rating' => $rating,
        'comment' => htmlspecialchars($comment, ENT_QUOTES, 'UTF-8'),
        'date' => date('Y-m-d'),
        'verified' => true,
        'createdAt' => date('c')
    ];

    array_unshift($reviews, $newReview);
    save_json_file('reviews', $reviews);

    if (class_exists('SecurityLogger')) {
        SecurityLogger::logEvent($customerName, 'customer', 'PRODUCT_REVIEW_SUBMITTED', "New {$rating}-star review on {$prodName}");
    }

    // Recalculate stats for this product
    $prodReviews = array_values(array_filter($reviews, function($r) use ($productId) {
        return ($r['productId'] ?? '') === $productId;
    }));
    $count = count($prodReviews);
    $avgRating = round(array_sum(array_column($prodReviews, 'rating')) / $count, 1);

    json_response('success', 'Thank you! Your review has been published successfully.', [
        'review' => $newReview,
        'count' => $count,
        'averageRating' => $avgRating
    ]);
}

// DELETE: Remove a review (Admin Only)
if ($method === 'DELETE') {
    $user = require_role('super_admin');
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        json_response('error', 'Review ID required for deletion');
    }

    $reviews = get_json_file('reviews') ?? [];
    $filtered = array_values(array_filter($reviews, function($r) use ($id) {
        return ($r['id'] ?? '') !== $id;
    }));

    save_json_file('reviews', $filtered);
    json_response('success', 'Review deleted successfully');
}

json_response('error', 'Invalid HTTP method');
