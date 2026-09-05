<?php
// SMS Mobile Repair Spare Parts & Pricing API
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/config.php';

$dataFile = __DIR__ . '/data/repair_parts.json';

function getRepairParts() {
    global $dataFile;
    if (!file_exists($dataFile)) {
        file_put_contents($dataFile, json_encode([], JSON_PRETTY_PRINT));
        return [];
    }
    $content = file_get_contents($dataFile);
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

function saveRepairParts($parts) {
    global $dataFile;
    return file_put_contents($dataFile, json_encode(array_values($parts), JSON_PRETTY_PRINT));
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $parts = getRepairParts();
    $brand = isset($_GET['brand']) ? strtolower(trim($_GET['brand'])) : '';
    $cat = isset($_GET['category']) ? strtolower(trim($_GET['category'])) : '';
    $q = isset($_GET['q']) ? strtolower(trim($_GET['q'])) : '';
    $id = isset($_GET['id']) ? trim($_GET['id']) : '';

    if (!empty($id)) {
        foreach ($parts as $p) {
            if ($p['id'] === $id) {
                echo json_encode(['status' => 'success', 'data' => $p]);
                exit;
            }
        }
        echo json_encode(['status' => 'error', 'message' => 'Part not found']);
        exit;
    }

    $filtered = $parts;

    if (!empty($brand) && $brand !== 'all') {
        $filtered = array_filter($filtered, function ($p) use ($brand) {
            return strtolower($p['deviceBrand'] ?? '') === $brand || strtolower($p['deviceBrand'] ?? '') === 'universal / all';
        });
    }

    if (!empty($cat) && $cat !== 'all') {
        $filtered = array_filter($filtered, function ($p) use ($cat) {
            return strtolower($p['category'] ?? '') === $cat;
        });
    }

    if (!empty($q)) {
        $filtered = array_filter($filtered, function ($p) use ($q) {
            return str_contains(strtolower($p['name'] ?? ''), $q) ||
                   str_contains(strtolower($p['deviceModel'] ?? ''), $q) ||
                   str_contains(strtolower($p['id'] ?? ''), $q) ||
                   str_contains(strtolower($p['deviceBrand'] ?? ''), $q);
        });
    }

    echo json_encode([
        'status' => 'success',
        'count' => count($filtered),
        'data' => array_values($filtered)
    ]);
    exit;
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);

    if (!$input) {
        $input = $_POST;
    }

    $action = $input['action'] ?? 'create';
    $parts = getRepairParts();

    if ($action === 'create') {
        $name = trim($input['name'] ?? '');
        $deviceModel = trim($input['deviceModel'] ?? '');
        $deviceBrand = trim($input['deviceBrand'] ?? 'Universal / All');
        $category = trim($input['category'] ?? 'Screen & Display');
        $costPrice = floatval($input['costPrice'] ?? 0);
        $sellingPrice = floatval($input['sellingPrice'] ?? 0);
        $stock = intval($input['stock'] ?? 0);
        $warranty = trim($input['warranty'] ?? '7 Days Checking Warranty');
        $notes = trim($input['notes'] ?? '');

        if (empty($name) || empty($deviceModel) || $sellingPrice <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Please provide Part Name, Device Model, and Selling Price.']);
            exit;
        }

        // Generate clean ID
        $newId = 'PART-' . (1000 + count($parts) + 1);
        // Ensure unique
        $existingIds = array_column($parts, 'id');
        while (in_array($newId, $existingIds)) {
            $newId = 'PART-' . rand(1100, 9999);
        }

        $newPart = [
            'id' => $newId,
            'name' => $name,
            'category' => $category,
            'deviceBrand' => $deviceBrand,
            'deviceModel' => $deviceModel,
            'costPrice' => $costPrice,
            'sellingPrice' => $sellingPrice,
            'stock' => $stock,
            'warranty' => $warranty,
            'notes' => $notes,
            'createdAt' => date('Y-m-d H:i:s'),
            'updatedAt' => date('Y-m-d H:i:s')
        ];

        array_unshift($parts, $newPart);
        saveRepairParts($parts);

        echo json_encode(['status' => 'success', 'message' => 'Spare part added successfully', 'data' => $newPart]);
        exit;
    }

    if ($action === 'update') {
        $id = trim($input['id'] ?? '');
        if (empty($id)) {
            echo json_encode(['status' => 'error', 'message' => 'Part ID is required.']);
            exit;
        }

        $found = false;
        foreach ($parts as &$p) {
            if ($p['id'] === $id) {
                $p['name'] = trim($input['name'] ?? $p['name']);
                $p['category'] = trim($input['category'] ?? $p['category']);
                $p['deviceBrand'] = trim($input['deviceBrand'] ?? $p['deviceBrand']);
                $p['deviceModel'] = trim($input['deviceModel'] ?? $p['deviceModel']);
                $p['costPrice'] = isset($input['costPrice']) ? floatval($input['costPrice']) : $p['costPrice'];
                $p['sellingPrice'] = isset($input['sellingPrice']) ? floatval($input['sellingPrice']) : $p['sellingPrice'];
                $p['stock'] = isset($input['stock']) ? intval($input['stock']) : $p['stock'];
                $p['warranty'] = trim($input['warranty'] ?? $p['warranty']);
                $p['notes'] = trim($input['notes'] ?? $p['notes']);
                $p['updatedAt'] = date('Y-m-d H:i:s');
                $found = true;
                break;
            }
        }

        if (!$found) {
            echo json_encode(['status' => 'error', 'message' => 'Part not found.']);
            exit;
        }

        saveRepairParts($parts);
        echo json_encode(['status' => 'success', 'message' => 'Spare part updated successfully']);
        exit;
    }

    if ($action === 'delete') {
        $id = trim($input['id'] ?? '');
        $initialCount = count($parts);
        $parts = array_filter($parts, function ($p) use ($id) {
            return $p['id'] !== $id;
        });

        if (count($parts) === $initialCount) {
            echo json_encode(['status' => 'error', 'message' => 'Part not found.']);
            exit;
        }

        saveRepairParts($parts);
        echo json_encode(['status' => 'success', 'message' => 'Spare part deleted successfully']);
        exit;
    }

    if ($action === 'update_stock') {
        $id = trim($input['id'] ?? '');
        $qtyChange = intval($input['qtyChange'] ?? 0); // e.g. -1 when used in repair
        $found = false;

        foreach ($parts as &$p) {
            if ($p['id'] === $id) {
                $p['stock'] = max(0, $p['stock'] + $qtyChange);
                $p['updatedAt'] = date('Y-m-d H:i:s');
                $found = true;
                break;
            }
        }

        if (!$found) {
            echo json_encode(['status' => 'error', 'message' => 'Part not found.']);
            exit;
        }

        saveRepairParts($parts);
        echo json_encode(['status' => 'success', 'message' => 'Stock updated']);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
    exit;
}
