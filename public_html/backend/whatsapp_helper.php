<?php
// SMS (Safdar Mobile Store) - WhatsApp & SMS Auto-Messenger Engine
// Supports 1-Click WhatsApp Direct Dispatch & Background Webhook API

function normalize_pakistan_phone($phone) {
    $clean = preg_replace('/[^0-9]/', '', $phone);
    if (empty($clean)) return '';
    if (strpos($clean, '0') === 0) {
        $clean = '92' . substr($clean, 1);
    } elseif (strpos($clean, '92') !== 0 && strlen($clean) === 10) {
        $clean = '92' . $clean;
    }
    return $clean;
}

function build_pos_sale_whatsapp_text($sale) {
    $inv = $sale['invoiceNo'] ?? $sale['id'] ?? 'INV';
    $cust = $sale['customerName'] ?? 'Valued Customer';
    $phone = $sale['customerPhone'] ?? '';
    $date = !empty($sale['createdAt']) ? date('d-M-Y h:i A', strtotime($sale['createdAt'])) : date('d-M-Y h:i A');
    $method = strtoupper($sale['paymentMethod'] ?? 'CASH');
    $total = number_format($sale['total'] ?? 0);
    $discount = floatval($sale['discount'] ?? 0);
    
    $itemsText = "";
    $items = $sale['items'] ?? [];
    foreach ($items as $it) {
        $qty = $it['qty'] ?? 1;
        $name = $it['name'] ?? 'Item';
        $price = number_format($it['lineTotal'] ?? ($it['price'] * $qty));
        $itemsText .= "• {$qty}x {$name} = PKR {$price}\n";
    }

    $msg = "🧾 *SAFDAR MOBILE STORE - OFFICIAL RECEIPT*\n";
    $msg .= "📍 Opp. Patt Bazar, Eidgah Road, Hangu\n";
    $msg .= "📞 Helpline / WhatsApp: 03339688007\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "👤 *Customer:* {$cust}\n";
    if ($phone) $msg .= "📱 *Phone:* {$phone}\n";
    $msg .= "📄 *Invoice #:* `{$inv}`\n";
    $msg .= "📅 *Date:* {$date}\n";
    $msg .= "💳 *Payment:* {$method}\n";
    if (!empty($sale['trxId'])) $msg .= "🔢 *TRX ID:* `{$sale['trxId']}`\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "*PURCHASED ITEMS:*\n{$itemsText}";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
    if ($discount > 0) {
        $msg .= "🏷️ *Discount Given:* -PKR " . number_format($discount) . "\n";
    }
    $msg .= "💰 *NET TOTAL PAID:* PKR {$total}\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "✅ *Warranty & Support:*\n";
    $msg .= "• 3 Days Checking Warranty on applicable items.\n";
    $msg .= "• Original digital/thermal slip required for claims.\n\n";
    $msg .= "🙏 *Thank you for choosing Safdar Mobile Store!*\n";
    $msg .= "🌐 Visit Website: http://localhost/sms/\n";

    return $msg;
}

function build_repair_ticket_whatsapp_text($repair) {
    $ticket = $repair['ticketNo'] ?? $repair['id'] ?? 'TICK';
    $cust = $repair['customerName'] ?? 'Customer';
    $phone = $repair['customerPhone'] ?? '';
    $device = trim(($repair['deviceBrand'] ?? '') . ' ' . ($repair['deviceModel'] ?? ''));
    $fault = $repair['reportedFault'] ?? 'General Diagnostic';
    $bill = number_format($repair['totalBill'] ?? 0);
    $advance = number_format($repair['advancePaid'] ?? 0);
    $balance = number_format(max(0, ($repair['totalBill'] ?? 0) - ($repair['advancePaid'] ?? 0)));
    $status = strtoupper($repair['jobStatus'] ?? 'RECEIVED IN LAB');

    $msg = "🔧 *SAFDAR MOBILE LAB - REPAIR TICKET*\n";
    $msg .= "📍 Opp. Patt Bazar, Eidgah Road, Hangu\n";
    $msg .= "📞 Technician Helpline: 03339688007\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "👤 *Customer:* {$cust}\n";
    $msg .= "🎫 *Ticket #:* `{$ticket}`\n";
    $msg .= "📱 *Device:* {$device}\n";
    if (!empty($repair['deviceImei'])) $msg .= "🔢 *IMEI:* `{$repair['deviceImei']}`\n";
    $msg .= "⚠️ *Fault Reported:* {$fault}\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "💰 *Total Estimated Bill:* PKR {$bill}\n";
    $msg .= "💵 *Advance Paid:* PKR {$advance}\n";
    $msg .= "📌 *Balance Due:* PKR {$balance}\n";
    $msg .= "📊 *Current Status:* *{$status}*\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "ℹ️ *Important Note:*\n";
    $msg .= "Please keep this Ticket # safe and present it when collecting your device.\n\n";
    $msg .= "🙏 *Safdar Mobile Lab - Professional Chip-Level Solutions!*\n";

    return $msg;
}

function build_cctv_project_whatsapp_text($project) {
    $pNo = $project['projectNo'] ?? $project['id'] ?? 'CCTV';
    $client = $project['clientName'] ?? 'Valued Client';
    $address = $project['siteAddress'] ?? 'Hangu';
    $brand = $project['cameraBrand'] ?? 'Hikvision';
    $package = $project['systemPackage'] ?? 'Full HD Security System';
    $bill = number_format($project['totalBill'] ?? 0);
    $advance = number_format($project['advancePaid'] ?? 0);
    $balance = number_format(max(0, ($project['totalBill'] ?? 0) - ($project['advancePaid'] ?? 0)));
    $status = strtoupper($project['status'] ?? 'BOOKED / IN PROGRESS');

    $msg = "📹 *SAFDAR CCTV SOLUTIONS - PROJECT BOOKING*\n";
    $msg .= "📍 Opp. Patt Bazar, Eidgah Road, Hangu\n";
    $msg .= "📞 Security Desk: 03339688007\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "👤 *Client:* {$client}\n";
    $msg .= "🏢 *Site Address:* {$address}\n";
    $msg .= "📁 *Project #:* `{$pNo}`\n";
    $msg .= "📹 *System:* {$brand} ({$package})\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "💰 *Total Project Cost:* PKR {$bill}\n";
    $msg .= "💵 *Advance Paid:* PKR {$advance}\n";
    $msg .= "📌 *Balance Remaining:* PKR {$balance}\n";
    $msg .= "📊 *Installation Status:* *{$status}*\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "🛡️ *Warranty & Guarantee:*\n";
    $msg .= "• 1-Year Official Hardware & DVR Warranty.\n";
    $msg .= "• 24/7 Mobile Remote View Support Setup Included.\n\n";
    $msg .= "🙏 *Thank you for securing your premises with Safdar CCTV Solutions!*\n";

    return $msg;
}

function build_citizen_service_whatsapp_text($service) {
    $ref = $service['referenceNo'] ?? $service['id'] ?? 'REF';
    $citizen = $service['citizenName'] ?? $service['customerName'] ?? 'Citizen';
    $type = $service['serviceType'] ?? 'NADRA / Citizen Kiosk Facilitation';
    $fee = number_format($service['feePaid'] ?? $service['amount'] ?? 0);
    $date = date('d-M-Y h:i A');

    $msg = "🪪 *SAFDAR CITIZEN KIOSK - SERVICE SLIP*\n";
    $msg .= "📍 Opp. Patt Bazar, Eidgah Road, Hangu\n";
    $msg .= "📞 Helpline: 03339688007\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "👤 *Citizen Name:* {$citizen}\n";
    $msg .= "📑 *Service Provided:* {$type}\n";
    $msg .= "🔢 *Reference / Tracking #:* `{$ref}`\n";
    $msg .= "📅 *Date & Time:* {$date}\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "💰 *Service Fee Paid:* PKR {$fee}\n";
    $msg .= "✅ *Status:* PROCESSED & VERIFIED\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "🙏 *Safdar Mobile Store - Your Trusted Digital Services Partner!*\n";

    return $msg;
}
