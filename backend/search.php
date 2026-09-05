<?php
// SMS (Safdar Mobile Store) - Universal Global Search API Engine across ALL Menus
define('IS_API_ROUTE', true);
require_once __DIR__ . '/config.php';

$user = require_auth();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method !== 'GET') {
    json_response('error', 'Only GET requests allowed');
}

$query = trim($_GET['q'] ?? '');
if (strlen($query) < 1) {
    json_response('success', 'Empty query', [
        'query' => '',
        'totalMatches' => 0,
        'sales' => [],
        'customers' => [],
        'debts' => [],
        'repairs' => [],
        'cctv' => [],
        'repairParts' => [],
        'services' => [],
        'bills' => [],
        'packages' => [],
        'nadra' => [],
        'expenses' => [],
        'products' => [],
        'suppliers' => [],
        'reports' => []
    ]);
}

$q = strtolower($query);
$qCleanNum = preg_replace('/[^0-9]/', '', $query);

// Load all system databases
$customers = get_json_file('customers') ?? [];
$products = get_json_file('products') ?? [];
$sales = get_json_file('sales') ?? [];
$repairs = get_json_file('mobile_repairs') ?? [];
$cctv = get_json_file('cctv') ?? [];
$services = get_json_file('services') ?? [];
$bills = get_json_file('bills') ?? [];
$packages = get_json_file('packages') ?? [];
$simPlans = get_json_file('sim_plans') ?? [];
$nadra = get_json_file('nadra_kiosk') ?? [];
$repairParts = get_json_file('repair_parts') ?? [];
$expenses = get_json_file('expenses') ?? [];
$suppliers = get_json_file('suppliers') ?? [];

if (!is_array($customers)) $customers = [];
if (!is_array($products)) $products = [];
if (!is_array($sales)) $sales = [];
if (!is_array($repairs)) $repairs = [];
if (!is_array($cctv)) $cctv = [];
if (!is_array($services)) $services = [];
if (!is_array($bills)) $bills = [];
if (!is_array($packages)) $packages = [];
if (!is_array($simPlans)) $simPlans = [];
if (!is_array($nadra)) $nadra = [];
if (!is_array($repairParts)) $repairParts = [];
if (!is_array($expenses)) $expenses = [];
if (!is_array($suppliers)) $suppliers = [];

// Helper customer matcher
function isCustomerMatch($name, $phone, $q, $qCleanNum) {
    $n = strtolower($name);
    $p = strtolower($phone);
    $pNum = preg_replace('/[^0-9]/', '', $p);
    return (strpos($n, $q) !== false) || 
           (strpos($p, $q) !== false) || 
           ($qCleanNum && $pNum && strpos($pNum, $qCleanNum) !== false);
}

// -------------------------------------------------------------
// 1. MENU: SALES & INVOICES (sales.json)
// -------------------------------------------------------------
$matchedSales = [];
foreach ($sales as $s) {
    $invNo = strtolower($s['invoiceNo'] ?? $s['id'] ?? '');
    $cName = strtolower($s['customerName'] ?? '');
    $cPhone = strtolower($s['customerPhone'] ?? '');
    $cPhoneNum = preg_replace('/[^0-9]/', '', $cPhone);
    $payMethod = strtolower($s['paymentMethod'] ?? '');
    $notes = strtolower($s['notes'] ?? '');
    $trxId = strtolower($s['trxId'] ?? '');

    // Match items inside invoice
    $itemsMatched = false;
    $itemNames = [];
    if (!empty($s['items']) && is_array($s['items'])) {
        foreach ($s['items'] as $it) {
            $itName = strtolower($it['name'] ?? '');
            $itCat = strtolower($it['category'] ?? '');
            $itemNames[] = $it['name'] ?? '';
            if (strpos($itName, $q) !== false || strpos($itCat, $q) !== false) {
                $itemsMatched = true;
            }
        }
    }

    $matches = (strpos($invNo, $q) !== false) ||
               (strpos($cName, $q) !== false) ||
               (strpos($cPhone, $q) !== false) ||
               ($qCleanNum && $cPhoneNum && strpos($cPhoneNum, $qCleanNum) !== false) ||
               (strpos($payMethod, $q) !== false) ||
               (strpos($trxId, $q) !== false) ||
               (strpos($notes, $q) !== false) ||
               $itemsMatched;

    if ($matches) {
        $matchedSales[] = [
            'id' => $s['id'],
            'invoiceNo' => $s['invoiceNo'] ?? $s['id'],
            'customerName' => $s['customerName'] ?? 'Walk-in Customer',
            'customerPhone' => $s['customerPhone'] ?? '',
            'total' => floatval($s['total'] ?? 0),
            'discount' => floatval($s['discount'] ?? 0),
            'paymentMethod' => strtoupper($s['paymentMethod'] ?? 'CASH'),
            'itemCount' => count($s['items'] ?? []),
            'itemsSummary' => !empty($itemNames) ? implode(', ', array_slice($itemNames, 0, 3)) : 'Store Items',
            'status' => $s['status'] ?? 'completed',
            'createdAt' => $s['createdAt'] ?? ''
        ];
    }
}

// -------------------------------------------------------------
// 2. MENU: CUSTOMERS DIRECTORY (Cross-System Aggregated)
// -------------------------------------------------------------
$matchedCustomers = [];
$seenCustomerKeys = [];

// A. From Registered Customers
foreach ($customers as $c) {
    $cName = strtolower($c['name'] ?? '');
    $cPhone = strtolower($c['phone'] ?? '');
    $cPhoneNum = preg_replace('/[^0-9]/', '', $cPhone);
    $cEmail = strtolower($c['email'] ?? '');
    $cCity = strtolower($c['city'] ?? '');

    $matches = (strpos($cName, $q) !== false) ||
               (strpos($cPhone, $q) !== false) ||
               ($qCleanNum && $cPhoneNum && strpos($cPhoneNum, $qCleanNum) !== false) ||
               (strpos($cEmail, $q) !== false) ||
               (strpos($cCity, $q) !== false);

    if ($matches) {
        $cKey = $cName . '_' . $cPhoneNum;
        $seenCustomerKeys[$cKey] = true;

        $spent = 0;
        $orderCount = 0;
        foreach ($sales as $s) {
            $sName = strtolower($s['customerName'] ?? '');
            $sPhone = preg_replace('/[^0-9]/', '', $s['customerPhone'] ?? '');
            if (($sName === $cName && $sName !== 'walk-in customer') || ($cPhoneNum && $sPhone === $cPhoneNum)) {
                if (($s['status'] ?? '') !== 'refunded') {
                    $spent += floatval($s['total'] ?? 0);
                }
                $orderCount++;
            }
        }

        $repCount = 0;
        foreach ($repairs as $r) {
            $rName = strtolower($r['customerName'] ?? '');
            $rPhone = preg_replace('/[^0-9]/', '', $r['customerPhone'] ?? '');
            if (($rName === $cName) || ($cPhoneNum && $rPhone === $cPhoneNum)) {
                $repCount++;
            }
        }

        $srvCount = 0;
        foreach ($services as $sv) {
            $svName = strtolower($sv['customerName'] ?? $sv['receiverName'] ?? '');
            $svPhone = preg_replace('/[^0-9]/', '', $sv['customerPhone'] ?? '');
            if (($svName === $cName) || ($cPhoneNum && $svPhone === $cPhoneNum)) {
                $srvCount++;
            }
        }

        $matchedCustomers[] = [
            'id' => $c['id'] ?? ('cust-' . md5($cKey)),
            'name' => $c['name'] ?? 'Customer',
            'phone' => $c['phone'] ?? '',
            'email' => $c['email'] ?? '',
            'city' => $c['city'] ?? 'Hangu',
            'totalSpent' => $spent > 0 ? $spent : floatval($c['totalSpent'] ?? 0),
            'totalPurchases' => $orderCount > 0 ? $orderCount : intval($c['totalPurchases'] ?? 0),
            'balance' => floatval($c['balance'] ?? 0),
            'repairsCount' => $repCount,
            'servicesCount' => $srvCount,
            'source' => 'directory'
        ];
    }
}

// B. From Services / Sales / Repairs / CCTV
foreach ($services as $sv) {
    $svName = trim($sv['customerName'] ?? $sv['receiverName'] ?? '');
    $svPhone = trim($sv['customerPhone'] ?? '');
    if (!$svName || strtolower($svName) === 'walk-in' || strtolower($svName) === 'walk-in customer') continue;

    $cKey = strtolower($svName) . '_' . preg_replace('/[^0-9]/', '', $svPhone);
    if (!isset($seenCustomerKeys[$cKey]) && isCustomerMatch($svName, $svPhone, $q, $qCleanNum)) {
        $seenCustomerKeys[$cKey] = true;
        $matchedCustomers[] = [
            'id' => 'cust-srv-' . md5($cKey),
            'name' => $svName,
            'phone' => $svPhone,
            'email' => '',
            'city' => 'Hangu',
            'totalSpent' => floatval($sv['amount'] ?? 0),
            'totalPurchases' => 1,
            'balance' => 0,
            'repairsCount' => 0,
            'servicesCount' => 1,
            'source' => 'digital_services'
        ];
    }
}

// -------------------------------------------------------------
// 3. MENU: DEBTS & LEDGERS (payments.php & debtors)
// -------------------------------------------------------------
$matchedDebts = [];
$isDebtSearch = (strpos($q, 'debt') !== false || strpos($q, 'udhaar') !== false || strpos($q, 'ledger') !== false || strpos($q, 'due') !== false || strpos($q, 'balance') !== false);

foreach ($customers as $c) {
    $bal = floatval($c['balance'] ?? 0);
    if ($bal <= 0 && !$isDebtSearch) continue;

    $cName = strtolower($c['name'] ?? '');
    $cPhone = strtolower($c['phone'] ?? '');
    $cPhoneNum = preg_replace('/[^0-9]/', '', $cPhone);

    if ($bal > 0 && ($isDebtSearch || isCustomerMatch($cName, $cPhone, $q, $qCleanNum) || (strpos((string)$bal, $q) !== false))) {
        $matchedDebts[] = [
            'id' => $c['id'] ?? ('debt-' . md5($cName)),
            'customerName' => $c['name'] ?? 'Debtor',
            'customerPhone' => $c['phone'] ?? '',
            'balance' => $bal,
            'totalPurchases' => intval($c['totalPurchases'] ?? 0),
            'city' => $c['city'] ?? 'Hangu'
        ];
    }
}

// -------------------------------------------------------------
// 4. MENU: MOBILE REPAIRS LAB (mobile_repairs.json)
// -------------------------------------------------------------
$matchedRepairs = [];
foreach ($repairs as $r) {
    $ticketNo = strtolower($r['ticketNo'] ?? $r['id'] ?? '');
    $cName = strtolower($r['customerName'] ?? '');
    $cPhone = strtolower($r['customerPhone'] ?? '');
    $cPhoneNum = preg_replace('/[^0-9]/', '', $cPhone);
    $brand = strtolower($r['deviceBrand'] ?? '');
    $model = strtolower($r['deviceModel'] ?? '');
    $fault = strtolower($r['reportedFault'] ?? '');
    $tech = strtolower($r['technician'] ?? '');
    $imei = strtolower($r['deviceImei'] ?? '');
    $status = strtolower($r['jobStatus'] ?? '');

    $matches = (strpos($ticketNo, $q) !== false) ||
               (strpos($cName, $q) !== false) ||
               (strpos($cPhone, $q) !== false) ||
               ($qCleanNum && $cPhoneNum && strpos($cPhoneNum, $qCleanNum) !== false) ||
               (strpos($brand, $q) !== false) ||
               (strpos($model, $q) !== false) ||
               (strpos($fault, $q) !== false) ||
               (strpos($tech, $q) !== false) ||
               (strpos($imei, $q) !== false) ||
               (strpos($status, $q) !== false);

    if ($matches) {
        $matchedRepairs[] = [
            'id' => $r['id'],
            'ticketNo' => $r['ticketNo'] ?? $r['id'],
            'customerName' => $r['customerName'] ?? 'Customer',
            'customerPhone' => $r['customerPhone'] ?? '',
            'deviceBrand' => $r['deviceBrand'] ?? '',
            'deviceModel' => $r['deviceModel'] ?? '',
            'reportedFault' => $r['reportedFault'] ?? '',
            'totalBill' => floatval($r['totalBill'] ?? 0),
            'advancePaid' => floatval($r['advancePaid'] ?? 0),
            'balanceDue' => floatval($r['balanceDue'] ?? ($r['remainingBalance'] ?? 0)),
            'jobStatus' => $r['jobStatus'] ?? 'received',
            'technician' => $r['technician'] ?? 'Master Munim',
            'createdAt' => $r['receivedDate'] ?? ($r['createdAt'] ?? '')
        ];
    }
}

// -------------------------------------------------------------
// 5. MENU: CCTV SURVEILLANCE (cctv.json)
// -------------------------------------------------------------
$matchedCctv = [];
foreach ($cctv as $proj) {
    $projNo = strtolower($proj['projectNo'] ?? $proj['id'] ?? '');
    $cName = strtolower($proj['clientName'] ?? '');
    $cPhone = strtolower($proj['clientPhone'] ?? '');
    $cPhoneNum = preg_replace('/[^0-9]/', '', $cPhone);
    $address = strtolower($proj['siteAddress'] ?? '');
    $brand = strtolower($proj['cameraBrand'] ?? '');
    $pkg = strtolower($proj['systemPackage'] ?? '');
    $tech = strtolower($proj['technician'] ?? '');
    $payMethod = strtolower($proj['paymentMethod'] ?? '');
    $notes = strtolower($proj['notes'] ?? '');

    $customMatched = false;
    if (!empty($proj['customItems']) && is_array($proj['customItems'])) {
        foreach ($proj['customItems'] as $ci) {
            if (strpos(strtolower($ci['name'] ?? ''), $q) !== false) {
                $customMatched = true;
                break;
            }
        }
    }

    $matches = (strpos($projNo, $q) !== false) ||
               (strpos($cName, $q) !== false) ||
               (strpos($cPhone, $q) !== false) ||
               ($qCleanNum && $cPhoneNum && strpos($cPhoneNum, $qCleanNum) !== false) ||
               (strpos($address, $q) !== false) ||
               (strpos($brand, $q) !== false) ||
               (strpos($pkg, $q) !== false) ||
               (strpos($tech, $q) !== false) ||
               (strpos($payMethod, $q) !== false) ||
               (strpos($notes, $q) !== false) ||
               $customMatched;

    if ($matches) {
        $matchedCctv[] = [
            'id' => $proj['id'],
            'projectNo' => $proj['projectNo'] ?? $proj['id'],
            'clientName' => $proj['clientName'] ?? 'Client',
            'clientPhone' => $proj['clientPhone'] ?? '',
            'siteAddress' => $proj['siteAddress'] ?? 'Hangu',
            'cameraBrand' => $proj['cameraBrand'] ?? 'Hikvision / Dahua',
            'systemPackage' => $proj['systemPackage'] ?? 'CCTV Setup',
            'paymentMethod' => strtoupper($proj['paymentMethod'] ?? 'CASH'),
            'totalBill' => floatval($proj['totalBill'] ?? 0),
            'advancePayment' => floatval($proj['advancePayment'] ?? 0),
            'remainingPayment' => floatval($proj['remainingPayment'] ?? 0),
            'status' => $proj['status'] ?? 'installed',
            'createdAt' => $proj['createdAt'] ?? ''
        ];
    }
}

// -------------------------------------------------------------
// 6. MENU: SPARE PARTS & PRICING (repair_parts.json)
// -------------------------------------------------------------
$matchedRepairParts = [];
foreach ($repairParts as $rp) {
    $rpName = strtolower($rp['name'] ?? '');
    $rpCat = strtolower($rp['category'] ?? '');
    $rpBrand = strtolower($rp['deviceBrand'] ?? '');
    $rpModel = strtolower($rp['deviceModel'] ?? '');
    $rpId = strtolower($rp['id'] ?? '');
    $rpNotes = strtolower($rp['notes'] ?? '');

    $matches = (strpos($rpName, $q) !== false) ||
               (strpos($rpCat, $q) !== false) ||
               (strpos($rpBrand, $q) !== false) ||
               (strpos($rpModel, $q) !== false) ||
               (strpos($rpId, $q) !== false) ||
               (strpos($rpNotes, $q) !== false);

    if ($matches) {
        $matchedRepairParts[] = [
            'id' => $rp['id'],
            'name' => $rp['name'],
            'category' => $rp['category'] ?? 'Parts',
            'deviceBrand' => $rp['deviceBrand'] ?? 'Universal',
            'deviceModel' => $rp['deviceModel'] ?? 'All Models',
            'sellingPrice' => floatval($rp['sellingPrice'] ?? 0),
            'costPrice' => ($user['role'] ?? '') === 'salesman' ? null : floatval($rp['costPrice'] ?? 0),
            'stock' => intval($rp['stock'] ?? 0),
            'warranty' => $rp['warranty'] ?? 'Checking Warranty'
        ];
    }
}

// -------------------------------------------------------------
// 7. MENU: EASYPAISA / JAZZCASH & DIGITAL SERVICES (services.json)
// -------------------------------------------------------------
$matchedServices = [];
foreach ($services as $sv) {
    $trxId = strtolower($sv['trxId'] ?? $sv['id'] ?? '');
    $cName = strtolower($sv['customerName'] ?? '');
    $rName = strtolower($sv['receiverName'] ?? '');
    $cPhone = strtolower($sv['customerPhone'] ?? '');
    $cPhoneNum = preg_replace('/[^0-9]/', '', $cPhone);
    $rAcc = strtolower($sv['receiverAccount'] ?? '');
    $prov = strtolower($sv['serviceProvider'] ?? '');
    $txType = strtolower($sv['txType'] ?? '');
    $notes = strtolower($sv['notes'] ?? '');
    $cnic = strtolower($sv['customerCnic'] ?? '');

    $matches = (strpos($trxId, $q) !== false) ||
               (strpos($cName, $q) !== false) ||
               (strpos($rName, $q) !== false) ||
               (strpos($cPhone, $q) !== false) ||
               ($qCleanNum && $cPhoneNum && strpos($cPhoneNum, $qCleanNum) !== false) ||
               (strpos($rAcc, $q) !== false) ||
               (strpos($prov, $q) !== false) ||
               (strpos($txType, $q) !== false) ||
               (strpos($notes, $q) !== false) ||
               (strpos($cnic, $q) !== false);

    if ($matches) {
        $matchedServices[] = [
            'id' => $sv['id'],
            'trxId' => $sv['trxId'] ?? $sv['id'],
            'serviceProvider' => strtoupper($sv['serviceProvider'] ?? 'EASYPAISA'),
            'txType' => strtoupper(str_replace('_', ' ', $sv['txType'] ?? 'CASH IN')),
            'amount' => floatval($sv['amount'] ?? 0),
            'commission' => floatval($sv['commission'] ?? 0),
            'customerName' => $sv['customerName'] ?? 'Walk-in',
            'customerPhone' => $sv['customerPhone'] ?? '',
            'receiverName' => $sv['receiverName'] ?? 'Beneficiary',
            'receiverAccount' => $sv['receiverAccount'] ?? '',
            'status' => $sv['status'] ?? 'completed',
            'notes' => $sv['notes'] ?? '',
            'timestamp' => $sv['timestamp'] ?? ($sv['createdAt'] ?? '')
        ];
    }
}

// -------------------------------------------------------------
// 8. MENU: UTILITY BILLS PAYMENT (bills.json)
// -------------------------------------------------------------
$matchedBills = [];
foreach ($bills as $b) {
    $consNo = strtolower($b['consumerNo'] ?? '');
    $compName = strtolower($b['companyName'] ?? '');
    $billType = strtolower($b['billType'] ?? '');
    $cName = strtolower($b['customerName'] ?? '');
    $cPhone = strtolower($b['customerPhone'] ?? '');
    $cPhoneNum = preg_replace('/[^0-9]/', '', $cPhone);
    $bMonth = strtolower($b['billingMonth'] ?? '');
    $trx = strtolower($b['channelTrxId'] ?? $b['id'] ?? '');
    $notes = strtolower($b['notes'] ?? '');

    $matches = (strpos($consNo, $q) !== false) ||
               (strpos($compName, $q) !== false) ||
               (strpos($billType, $q) !== false) ||
               (strpos($cName, $q) !== false) ||
               (strpos($cPhone, $q) !== false) ||
               ($qCleanNum && $cPhoneNum && strpos($cPhoneNum, $qCleanNum) !== false) ||
               (strpos($bMonth, $q) !== false) ||
               (strpos($trx, $q) !== false) ||
               (strpos($notes, $q) !== false);

    if ($matches) {
        $matchedBills[] = [
            'id' => $b['id'],
            'consumerNo' => $b['consumerNo'] ?? 'N/A',
            'companyName' => $b['companyName'] ?? 'PESCO Electricity',
            'billType' => strtoupper($b['billType'] ?? 'PESCO'),
            'customerName' => $b['customerName'] ?? 'Consumer',
            'customerPhone' => $b['customerPhone'] ?? '',
            'billingMonth' => $b['billingMonth'] ?? '',
            'billAmount' => floatval($b['billAmount'] ?? 0),
            'shopFee' => floatval($b['shopFee'] ?? 50),
            'totalCollected' => floatval($b['totalCollected'] ?? ($b['billAmount'] ?? 0)),
            'paymentChannel' => $b['paymentChannel'] ?? 'Cash Payment',
            'channelTrxId' => $b['channelTrxId'] ?? '',
            'paymentStatus' => $b['paymentStatus'] ?? 'paid',
            'paidAt' => $b['paidAt'] ?? ''
        ];
    }
}

// -------------------------------------------------------------
// 9. MENU: MOBILE PACKAGES & LOAD (packages.json + sim_plans.json)
// -------------------------------------------------------------
$matchedPackages = [];
$allPkgs = array_merge($packages, $simPlans);
foreach ($allPkgs as $pkg) {
    $pkgName = strtolower($pkg['name'] ?? $pkg['title'] ?? '');
    $pkgNet = strtolower($pkg['network'] ?? $pkg['provider'] ?? '');
    $pkgType = strtolower($pkg['type'] ?? $pkg['category'] ?? '');
    $pkgDesc = strtolower($pkg['description'] ?? $pkg['specs'] ?? '');

    $matches = (strpos($pkgName, $q) !== false) ||
               (strpos($pkgNet, $q) !== false) ||
               (strpos($pkgType, $q) !== false) ||
               (strpos($pkgDesc, $q) !== false);

    if ($matches) {
        $matchedPackages[] = [
            'id' => $pkg['id'] ?? ('pkg-' . rand(100, 999)),
            'name' => $pkg['name'] ?? $pkg['title'] ?? 'Network Package',
            'network' => strtoupper($pkg['network'] ?? $pkg['provider'] ?? 'Jazz / Zong / Telenor / Ufone'),
            'price' => floatval($pkg['price'] ?? 0),
            'validity' => $pkg['validity'] ?? '30 Days',
            'description' => $pkg['description'] ?? $pkg['specs'] ?? ''
        ];
    }
}

// -------------------------------------------------------------
// 10. MENU: NADRA & CITIZEN KIOSK (nadra_kiosk.json)
// -------------------------------------------------------------
$matchedNadra = [];
foreach ($nadra as $nk) {
    $trackNo = strtolower($nk['trackingNo'] ?? $nk['id'] ?? '');
    $citName = strtolower($nk['citizenName'] ?? '');
    $fName = strtolower($nk['fatherName'] ?? '');
    $cnic = strtolower($nk['citizenCnic'] ?? '');
    $phone = strtolower($nk['contactPhone'] ?? '');
    $phoneNum = preg_replace('/[^0-9]/', '', $phone);
    $srvName = strtolower($nk['serviceName'] ?? $nk['serviceType'] ?? '');
    $status = strtolower($nk['status'] ?? '');
    $notes = strtolower($nk['notes'] ?? '');

    $matches = (strpos($trackNo, $q) !== false) ||
               (strpos($citName, $q) !== false) ||
               (strpos($fName, $q) !== false) ||
               (strpos($cnic, $q) !== false) ||
               (strpos($phone, $q) !== false) ||
               ($qCleanNum && $phoneNum && strpos($phoneNum, $qCleanNum) !== false) ||
               (strpos($srvName, $q) !== false) ||
               (strpos($status, $q) !== false) ||
               (strpos($notes, $q) !== false);

    if ($matches) {
        $matchedNadra[] = [
            'id' => $nk['id'],
            'trackingNo' => $nk['trackingNo'] ?? $nk['id'],
            'citizenName' => $nk['citizenName'] ?? 'Citizen',
            'fatherName' => $nk['fatherName'] ?? '',
            'citizenCnic' => $nk['citizenCnic'] ?? 'N/A',
            'contactPhone' => $nk['contactPhone'] ?? '',
            'serviceName' => $nk['serviceName'] ?? 'Citizen Facilitation',
            'serviceType' => $nk['serviceType'] ?? 'nadra_cnic',
            'totalFee' => floatval($nk['totalFee'] ?? 0),
            'govtFee' => floatval($nk['govtFee'] ?? 0),
            'shopFee' => floatval($nk['shopFee'] ?? 0),
            'status' => $nk['status'] ?? 'in_process',
            'deliveryDate' => $nk['deliveryDate'] ?? '',
            'createdAt' => $nk['createdAt'] ?? ''
        ];
    }
}

// -------------------------------------------------------------
// 11. MENU: OPERATING EXPENSES (expenses.json)
// -------------------------------------------------------------
$matchedExpenses = [];
foreach ($expenses as $exp) {
    $cat = strtolower($exp['category'] ?? '');
    $vShop = strtolower($exp['vendor_shop'] ?? $exp['vendor'] ?? '');
    $details = strtolower($exp['item_details'] ?? $exp['details'] ?? '');
    $notes = strtolower($exp['notes'] ?? '');
    $recBy = strtolower($exp['recordedBy'] ?? '');
    $date = strtolower($exp['date'] ?? '');

    $matches = (strpos($cat, $q) !== false) ||
               (strpos($vShop, $q) !== false) ||
               (strpos($details, $q) !== false) ||
               (strpos($notes, $q) !== false) ||
               (strpos($recBy, $q) !== false) ||
               (strpos($date, $q) !== false);

    if ($matches) {
        $matchedExpenses[] = [
            'id' => $exp['id'],
            'category' => $exp['category'] ?? 'Expense',
            'vendor_shop' => $exp['vendor_shop'] ?? $exp['vendor'] ?? '',
            'item_details' => $exp['item_details'] ?? $exp['details'] ?? '',
            'amount' => floatval($exp['amount'] ?? 0),
            'date' => $exp['date'] ?? date('Y-m-d'),
            'notes' => $exp['notes'] ?? ''
        ];
    }
}

// -------------------------------------------------------------
// 12. MENU: PRODUCTS & INVENTORY (products.json)
// -------------------------------------------------------------
$matchedProducts = [];
foreach ($products as $p) {
    $pName = strtolower($p['name'] ?? '');
    $pSku = strtolower($p['sku'] ?? '');
    $pBarcode = strtolower($p['barcode'] ?? '');
    $pBrand = strtolower($p['brand'] ?? '');
    $pCat = strtolower($p['category'] ?? $p['categoryId'] ?? '');

    $matches = (strpos($pName, $q) !== false) ||
               (strpos($pSku, $q) !== false) ||
               (strpos($pBarcode, $q) !== false) ||
               (strpos($pBrand, $q) !== false) ||
               (strpos($pCat, $q) !== false);

    if ($matches) {
        $matchedProducts[] = [
            'id' => $p['id'],
            'name' => $p['name'],
            'sku' => $p['sku'] ?? 'N/A',
            'barcode' => $p['barcode'] ?? 'N/A',
            'brand' => $p['brand'] ?? 'Generic',
            'category' => $p['category'] ?? $p['categoryId'] ?? 'General',
            'sellingPrice' => floatval($p['sellingPrice'] ?? $p['priceNumeric'] ?? 0),
            'costPrice' => ($user['role'] ?? '') === 'salesman' ? null : floatval($p['costPrice'] ?? 0),
            'stock' => intval($p['stock'] ?? 0),
            'minStock' => intval($p['minStock'] ?? 2),
            'image' => $p['image'] ?? ''
        ];
    }
}

// -------------------------------------------------------------
// 13. MENU: SUPPLIERS DIRECTORY (suppliers.json)
// -------------------------------------------------------------
$matchedSuppliers = [];
foreach ($suppliers as $sup) {
    $sName = strtolower($sup['name'] ?? '');
    $sComp = strtolower($sup['company'] ?? '');
    $sPhone = strtolower($sup['phone'] ?? '');
    $sAddr = strtolower($sup['address'] ?? '');

    $matches = (strpos($sName, $q) !== false) ||
               (strpos($sComp, $q) !== false) ||
               (strpos($sPhone, $q) !== false) ||
               (strpos($sAddr, $q) !== false);

    if ($matches) {
        $matchedSuppliers[] = [
            'id' => $sup['id'],
            'name' => $sup['name'] ?? 'Supplier',
            'company' => $sup['company'] ?? '',
            'phone' => $sup['phone'] ?? '',
            'address' => $sup['address'] ?? 'Pakistan'
        ];
    }
}

// -------------------------------------------------------------
// 14. MENU: P&L REPORTS & ANALYTICS (Smart Jump)
// -------------------------------------------------------------
$matchedReports = [];
$reportKeywords = ['report', 'reports', 'profit', 'loss', 'p&l', 'analytics', 'revenue', 'income', 'monthly', 'sales summary'];
foreach ($reportKeywords as $rk) {
    if (strpos($q, $rk) !== false) {
        $matchedReports[] = [
            'title' => 'Profit & Loss (P&L) Reports & Business Analytics',
            'description' => 'View total sales volume, net gross profit, operating expense breakdowns, and monthly margins.',
            'url' => 'reports.php'
        ];
        break;
    }
}

$totalMatches = count($matchedSales) + count($matchedCustomers) + count($matchedDebts) + 
                count($matchedRepairs) + count($matchedCctv) + count($matchedRepairParts) + 
                count($matchedServices) + count($matchedBills) + count($matchedPackages) + 
                count($matchedNadra) + count($matchedExpenses) + count($matchedProducts) + 
                count($matchedSuppliers) + count($matchedReports);

// Send comprehensive response with all indexed menus
json_response('success', 'Search results retrieved', [
    'query' => $query,
    'totalMatches' => $totalMatches,
    'sales' => array_slice($matchedSales, 0, 10),
    'customers' => array_slice($matchedCustomers, 0, 10),
    'debts' => array_slice($matchedDebts, 0, 8),
    'repairs' => array_slice($matchedRepairs, 0, 8),
    'cctv' => array_slice($matchedCctv, 0, 8),
    'repairParts' => array_slice($matchedRepairParts, 0, 8),
    'services' => array_slice($matchedServices, 0, 10),
    'bills' => array_slice($matchedBills, 0, 8),
    'packages' => array_slice($matchedPackages, 0, 8),
    'nadra' => array_slice($matchedNadra, 0, 8),
    'expenses' => array_slice($matchedExpenses, 0, 8),
    'products' => array_slice($matchedProducts, 0, 10),
    'suppliers' => array_slice($matchedSuppliers, 0, 6),
    'reports' => $matchedReports
]);
