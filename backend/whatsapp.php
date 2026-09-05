<?php
// SMS (Safdar Mobile Store) - WhatsApp Dispatch & Notification Endpoint
define('IS_API_ROUTE', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/whatsapp_helper.php';

$user = require_auth();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $type = $_GET['type'] ?? 'sale'; // sale, repair, cctv, citizen
    $id = $_GET['id'] ?? '';
    $phone = $_GET['phone'] ?? '';

    $sales = get_json_file('sales') ?? [];
    $repairs = get_json_file('mobile_repairs') ?? [];
    $cctv = get_json_file('cctv') ?? [];
    $services = get_json_file('nadra_kiosk') ?? [];

    $text = "";
    $targetPhone = "";

    if ($type === 'sale') {
        foreach ($sales as $s) {
            if ($s['id'] === $id || ($s['invoiceNo'] ?? '') === $id) {
                $text = build_pos_sale_whatsapp_text($s);
                $targetPhone = $s['customerPhone'] ?? $phone;
                break;
            }
        }
    } elseif ($type === 'repair') {
        foreach ($repairs as $r) {
            if ($r['id'] === $id || ($r['ticketNo'] ?? '') === $id) {
                $text = build_repair_ticket_whatsapp_text($r);
                $targetPhone = $r['customerPhone'] ?? $phone;
                break;
            }
        }
    } elseif ($type === 'cctv') {
        foreach ($cctv as $cp) {
            if ($cp['id'] === $id || ($cp['projectNo'] ?? '') === $id) {
                $text = build_cctv_project_whatsapp_text($cp);
                $targetPhone = $cp['clientPhone'] ?? $phone;
                break;
            }
        }
    } elseif ($type === 'citizen') {
        foreach ($services as $srv) {
            if ($srv['id'] === $id || ($srv['referenceNo'] ?? '') === $id) {
                $text = build_citizen_service_whatsapp_text($srv);
                $targetPhone = $srv['citizenPhone'] ?? $phone;
                break;
            }
        }
    }

    if (empty($text)) {
        json_response('error', 'Record not found for generating WhatsApp receipt');
    }

    $cleanPhone = normalize_pakistan_phone($targetPhone ?: $phone);
    $waUrl = "https://wa.me/{$cleanPhone}?text=" . urlencode($text);

    json_response('success', 'WhatsApp message generated', [
        'type' => $type,
        'phone' => $cleanPhone,
        'message' => $text,
        'whatsappUrl' => $waUrl
    ]);
}

json_response('error', 'Invalid HTTP method');
