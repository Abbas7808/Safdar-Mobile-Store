<?php
$currentPage = 'pos';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$categories = get_json_file('categories') ?? [];
$products = get_json_file('products') ?? [];
?>

<div class="pos-main">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <!-- POS Billing 3-Column Layout Inside Admin Panel -->
    <div class="pos-billing" id="posBillingArea">
        
        <!-- Column 1: Categories Sidebar (Products + Store Services) -->
        <div class="pos-categories">
            <div style="font-size:0.68rem; font-weight:800; text-transform:uppercase; color:#94a3b8; padding:6px 12px 2px 12px; letter-spacing:0.5px;">Products</div>
            <button class="pos-cat-btn active" data-category="all">
                <i class="fa-solid fa-layer-group"></i> All Items
            </button>
            <?php foreach ($categories as $cat): 
                $catId = $cat['id'] ?? strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $cat['name']));
                $catName = $cat['name'];
                $catIcon = $cat['icon'] ?? 'fa-tag';
                if ($catId === 'mobiles') $catIcon = 'fa-mobile-screen';
                elseif ($catId === 'accessories') $catIcon = 'fa-headphones';
                elseif ($catId === 'cctv') $catIcon = 'fa-video';
                elseif (strpos($catId, 'laptop') !== false) $catIcon = 'fa-laptop';
                elseif (strpos($catId, 'watch') !== false) $catIcon = 'fa-clock';
                elseif (strpos($catId, 'computer') !== false) $catIcon = 'fa-computer';
                elseif (strpos($catId, 'network') !== false) $catIcon = 'fa-network-wired';
                elseif (strpos($catId, 'audio') !== false || strpos($catId, 'speaker') !== false) $catIcon = 'fa-volume-high';
            ?>
                <button class="pos-cat-btn" data-category="<?php echo htmlspecialchars($catId); ?>">
                    <i class="fa-solid <?php echo htmlspecialchars($catIcon); ?>"></i> <?php echo htmlspecialchars($catName); ?>
                </button>
            <?php endforeach; ?>

            <div style="font-size:0.68rem; font-weight:800; text-transform:uppercase; color:var(--pos-gold); padding:10px 12px 2px 12px; letter-spacing:0.5px; border-top:1px solid rgba(255,255,255,0.1); margin-top:6px;">
                Store Services
            </div>
            <button class="pos-cat-btn" data-category="service-repairs" style="color:#fef08a;">
                <i class="fa-solid fa-screwdriver-wrench"></i> Mobile Repairs
            </button>
            <button class="pos-cat-btn" data-category="service-easypaisa-jazzcash">
                <i class="fa-solid fa-wallet"></i> Easypaisa / JazzCash
            </button>
            <button class="pos-cat-btn" data-category="service-bills">
                <i class="fa-solid fa-file-invoice-dollar"></i> Utility Bills
            </button>
            <button class="pos-cat-btn" data-category="service-packages">
                <i class="fa-solid fa-mobile-screen-button"></i> Packages & Load
            </button>
            <button class="pos-cat-btn" data-category="service-nadra">
                <i class="fa-solid fa-id-card"></i> NADRA & Citizen
            </button>
        </div>

        <!-- Column 2: Product Grid & Quick Service Bar -->
        <div class="pos-products-area">
            <div class="pos-search-bar" style="margin-bottom:8px;">
                <input type="text" id="posSearchInput" class="pos-search-input" placeholder="Scan Barcode or Search products / services..." autofocus>
                <button class="pos-btn pos-btn-secondary" onclick="document.getElementById('posSearchInput').focus()">
                    <i class="fa-solid fa-barcode"></i> Scan
                </button>
            </div>

            <!-- Quick Service Actions Shortcut Bar -->
            <div class="pos-service-quickbar" style="display:flex; gap:6px; overflow-x:auto; padding:2px 0 8px 0; margin-bottom:6px;">
                <button type="button" class="pos-btn pos-btn-sm" style="background:#fff1f2; border:1px solid #fecaca; color:#b91c1c; font-size:0.73rem; font-weight:800; white-space:nowrap; padding:4px 8px;" onclick="window.openPOSCustomRepairModal()">
                    <i class="fa-solid fa-screwdriver-wrench" style="color:var(--pos-red);"></i> + Custom Repair Bill / Slip
                </button>
                <button type="button" class="pos-btn pos-btn-sm" style="background:#ffffff; border:1px solid #cbd5e1; color:#0f172a; font-size:0.73rem; font-weight:700; white-space:nowrap; padding:4px 8px;" onclick="window.openPOSServiceModal('bill')">
                    <i class="fa-solid fa-file-invoice-dollar" style="color:#2563eb;"></i> + Pay Utility Bill
                </button>
                <button type="button" class="pos-btn pos-btn-sm" style="background:#ffffff; border:1px solid #cbd5e1; color:#0f172a; font-size:0.73rem; font-weight:700; white-space:nowrap; padding:4px 8px;" onclick="window.openPOSServiceModal('package')">
                    <i class="fa-solid fa-mobile-screen-button" style="color:#d97706;"></i> + Mobile Load / Pkg
                </button>
                <button type="button" class="pos-btn pos-btn-sm" style="background:#ffffff; border:1px solid #cbd5e1; color:#0f172a; font-size:0.73rem; font-weight:700; white-space:nowrap; padding:4px 8px;" onclick="window.openPOSServiceModal('transfer')">
                    <i class="fa-solid fa-wallet" style="color:#059669;"></i> + Money Transfer
                </button>
                <button type="button" class="pos-btn pos-btn-sm" style="background:#ffffff; border:1px solid #cbd5e1; color:#0f172a; font-size:0.73rem; font-weight:700; white-space:nowrap; padding:4px 8px;" onclick="window.openPOSServiceModal('nadra')">
                    <i class="fa-solid fa-id-card" style="color:#7c3aed;"></i> + NADRA / Citizen Fee
                </button>
            </div>

            <div class="pos-product-grid" id="posProductGrid">
                <!-- Rendered dynamically by assets/js/admin.js -->
            </div>
        </div>

        <!-- Column 3: Cart Panel -->
        <div class="pos-cart">
            <div class="pos-cart-header">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="pos-cart-title"><i class="fa-solid fa-cart-shopping" style="color:var(--pos-red);"></i> Billing Cart</span>
                    <span id="posCartItemCountBadge" style="background:#fef2f2; color:#dc2626; font-size:0.72rem; font-weight:800; padding:2px 8px; border-radius:10px; border:1px solid #fecaca;">0 Items</span>
                </div>
                <button class="pos-btn pos-btn-outline pos-btn-sm" style="padding:2px 8px; font-size:0.75rem;" onclick="window.clearPOSCart()">
                    <i class="fa-solid fa-trash-can"></i> Clear
                </button>
            </div>

            <!-- Cart Table Header for Clear Quantity Checking -->
            <div class="pos-cart-col-header" style="display:flex; justify-content:space-between; align-items:center; padding:6px 12px; background:#f8fafc; border-bottom:1px solid var(--pos-border); font-size:0.7rem; font-weight:800; text-transform:uppercase; color:#64748b; letter-spacing:0.5px;">
                <span style="flex:1;">Product & Unit Price</span>
                <span style="width:115px; text-align:center;">Order Qty</span>
                <span style="width:75px; text-align:right;">Subtotal</span>
            </div>

            <div class="pos-cart-items" id="posCartItemsContainer">
                <div style="text-align:center; padding:35px 10px; color:#9ca3af;">
                    <i class="fa-solid fa-cart-shopping" style="font-size:2rem; margin-bottom:8px; opacity:0.5;"></i>
                    <p style="font-weight:600; font-size:0.88rem;">No items in cart</p>
                    <span style="font-size:0.75rem;">Click product tiles or scan barcode to add</span>
                </div>
            </div>

            <!-- Cart Summary & Checkout -->
            <div class="pos-cart-footer">
                <!-- Customer Details & Existing Customer Search Row -->
                <div style="position:relative; margin:0 0 5px 0;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px;">
                        <span style="font-size:0.68rem; font-weight:800; color:#64748b; text-transform:uppercase;">
                            <i class="fa-solid fa-user-tag" style="color:var(--pos-red);"></i> Customer Info:
                        </span>
                        <div style="display:flex; gap:4px;">
                            <button type="button" class="pos-btn pos-btn-sm" style="padding:1px 5px; font-size:0.65rem; background:#f1f5f9; color:#334155; border:1px solid #cbd5e1;" onclick="window.openExistingCustomerPickerModal()" title="Browse Existing Customers">
                                <i class="fa-solid fa-users"></i> Existing
                            </button>
                            <button type="button" class="pos-btn pos-btn-sm" style="padding:1px 5px; font-size:0.65rem; background:#f8fafc; color:#64748b;" onclick="window.resetToWalkInCustomer()" title="Reset to Walk-in">
                                Walk-in
                            </button>
                        </div>
                    </div>

                    <div class="cart-customer-select" style="display:flex; gap:5px; position:relative;">
                        <div style="position:relative; flex:1;">
                            <input type="text" id="posCustomerNameInput" class="form-input" style="padding:3px 6px; font-size:0.75rem; width:100%; height:26px;" placeholder="Customer Name (Type to search existing...)" autocomplete="off" oninput="window.handlePOSCustomerSearchInput(this.value)" onfocus="window.handlePOSCustomerSearchInput(this.value)">
                        </div>
                        <div style="position:relative; width:115px;">
                            <input type="text" id="posCustomerPhoneInput" class="form-input" style="padding:3px 6px; font-size:0.75rem; width:100%; height:26px;" placeholder="Phone (03xx...)" autocomplete="off" oninput="window.handlePOSCustomerSearchInput(this.value)" onfocus="window.handlePOSCustomerSearchInput(this.value)">
                        </div>
                    </div>

                    <!-- Existing Customer Autocomplete Suggestions Popup -->
                    <div id="posCustomerSuggestionsPopup" style="display:none; position:absolute; bottom:calc(100% + 4px); left:0; right:0; background:#1e293b; border:1.5px solid #3b82f6; border-radius:8px; box-shadow:0 -10px 25px rgba(0,0,0,0.4); z-index:99999; max-height:220px; overflow-y:auto; padding:6px; color:#f8fafc;">
                        <div style="font-size:0.68rem; font-weight:800; color:#93c5fd; padding:3px 6px; border-bottom:1px solid #334155; margin-bottom:4px; display:flex; justify-content:space-between;">
                            <span>EXISTING CUSTOMERS FOUND</span>
                            <span onclick="window.closePOSCustomerSuggestions()" style="cursor:pointer; color:#94a3b8;">&times; Close</span>
                        </div>
                        <div id="posCustomerSuggestionsList"></div>
                    </div>

                    <!-- Customer Profile Pill Badge (Shown when an existing customer is selected) -->
                    <div id="posCustomerSelectedBadge" style="display:none; background:#ecfdf5; border:1px solid #a7f3d0; border-radius:4px; padding:2px 6px; margin-top:3px; font-size:0.68rem; color:#065f46; display:flex; justify-content:space-between; align-items:center;">
                        <span id="posCustomerBadgeText"><i class="fa-solid fa-circle-check"></i> <strong>Existing Customer</strong></span>
                        <span id="posCustomerBadgeSpent" style="font-weight:800; color:#059669;">PKR 0</span>
                    </div>
                </div>

                <!-- 4-Button Payment Method Row with Prominent Icons -->
                <div class="cart-payment-select">
                    <div class="payment-opt active" data-method="cash" title="Cash Payment">
                        <i class="fa-solid fa-money-bill-wave" style="color:#10b981;"></i>
                        <span>Cash</span>
                    </div>
                    <div class="payment-opt" data-method="easypaisa" title="Easypaisa Transfer">
                        <i class="fa-solid fa-mobile-screen-button" style="color:#00a859;"></i>
                        <span>Easypaisa</span>
                    </div>
                    <div class="payment-opt" data-method="jazzcash" title="JazzCash Transfer">
                        <i class="fa-solid fa-wallet" style="color:#e30613;"></i>
                        <span>JazzCash</span>
                    </div>
                    <div class="payment-opt" data-method="bank" title="Direct Bank / Raast">
                        <i class="fa-solid fa-building-columns" style="color:#2563eb;"></i>
                        <span>Bank</span>
                    </div>
                </div>

                <!-- Digital Payment Confirmation Box (Easypaisa / JazzCash / Bank) -->
                <div id="posDigitalPaymentConfirmBox" style="display:none; background:#f0fdf4; border:1px solid #86efac; border-radius:6px; padding:4px 6px; margin-bottom:4px; font-size:0.72rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:3px;">
                        <div style="font-weight:800; color:#166534; display:flex; align-items:center; gap:4px; font-size:0.72rem;">
                            <i id="posDigitalMethodIcon" class="fa-solid fa-mobile-screen-button" style="color:#00a859; font-size:0.85rem;"></i>
                            <span id="posDigitalMethodTitle">Easypaisa Verification</span>
                        </div>
                        <button type="button" class="pos-btn pos-btn-sm" style="padding:1px 5px; font-size:0.65rem; background:#ffffff; color:#166534; border:1px solid #86efac; border-radius:4px;" onclick="window.openPOSQrModal()">
                            <i class="fa-solid fa-qrcode"></i> Show QR
                        </button>
                    </div>

                    <!-- Official Account Info Pill -->
                    <div style="background:#ffffff; border:1px solid #bbf7d0; border-radius:4px; padding:2px 5px; margin-bottom:3px; font-size:0.68rem; color:#1e293b; display:flex; justify-content:space-between; align-items:center;">
                        <span id="posDigitalAccountLabel">Official: <strong>0333 9688007</strong></span>
                        <span style="color:#059669; font-weight:800;">Safdar Mobile</span>
                    </div>

                    <!-- Inputs Row: Transaction ID & Sender Phone -->
                    <div style="display:grid; grid-template-columns: 1.1fr 0.9fr; gap:4px; margin-bottom:3px;">
                        <div>
                            <input type="text" id="posTrxIdInput" class="form-input" style="padding:2px 5px; font-size:0.72rem; background:#ffffff; width:100%; border-color:#86efac; height:24px;" placeholder="TRX / TID ID *">
                        </div>
                        <div>
                            <input type="text" id="posSenderPhoneInput" class="form-input" style="padding:2px 5px; font-size:0.72rem; background:#ffffff; width:100%; border-color:#86efac; height:24px;" placeholder="Sender Phone">
                        </div>
                    </div>

                    <!-- Verification Confirmation Checkbox -->
                    <label style="display:flex; align-items:center; gap:4px; font-size:0.68rem; font-weight:700; color:#166534; cursor:pointer; background:#dcfce7; padding:2px 5px; border-radius:4px; margin:0;">
                        <input type="checkbox" id="posPaymentVerifiedCheck" style="accent-color:#16a34a; width:12px; height:12px; margin:0;" checked>
                        <span>Verified SMS / App alert received</span>
                    </label>
                </div>

                <!-- 2-Column Condensed Summary Grid -->
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:3px 8px; background:#fff; padding:4px 8px; border-radius:6px; border:1px solid #e2e8f0; margin-bottom:4px; font-size:0.74rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#64748b;">Subtotal:</span>
                        <strong id="posSubtotalDisplay" style="color:#0f172a;">PKR 0</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#64748b;">Discount:</span>
                        <input type="number" id="posDiscountInput" class="form-input" style="width:60px; padding:1px 3px; font-size:0.72rem; text-align:right; height:22px;" placeholder="0">
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#64748b;">Received:</span>
                        <input type="number" id="posAmountReceivedInput" class="form-input" style="width:60px; padding:1px 3px; font-size:0.72rem; text-align:right; height:22px;" placeholder="0">
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#64748b;">Change:</span>
                        <strong id="posChangeDisplay" style="color:var(--pos-success); font-weight:800;">PKR 0</strong>
                    </div>
                </div>

                <div style="display:none;" id="posDiscountDisplay">-PKR 0</div>

                <!-- TOTAL PAYABLE ROW -->
                <div style="display:flex; justify-content:space-between; align-items:center; background:#fee2e2; border:1px solid #fca5a5; border-radius:6px; padding:4px 8px; margin-bottom:5px;">
                    <span style="font-weight:900; font-size:0.78rem; color:#991b1b;">TOTAL PAYABLE:</span>
                    <span id="posTotalDisplay" style="font-weight:900; font-size:1.05rem; color:#b91c1c;">PKR 0</span>
                </div>

                <button class="complete-sale-btn" id="completeSaleBtn" style="padding:7px 10px; font-size:0.8rem; font-weight:800; border-radius:6px; width:100%;" disabled>
                    <i class="fa-solid fa-check-circle"></i> COMPLETE SALE & PRINT RECEIPT
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div class="pos-modal-overlay" id="receiptModal" style="display: none;">
    <div class="pos-modal">
        <div class="pos-modal-header">
            <h3 class="pos-modal-title"><i class="fa-solid fa-receipt" style="color:var(--pos-red);"></i> Thermal Receipt Preview</h3>
            <button class="pos-modal-close" onclick="closeReceiptModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div id="receiptModalContainer">
            <!-- Rendered by JS -->
        </div>

        <div class="form-actions" style="margin-top:20px; display:flex; flex-wrap:wrap; gap:8px; justify-content:flex-end;">
            <button class="pos-btn pos-btn-outline" onclick="closeReceiptModal()">Close</button>
            <button class="pos-btn" onclick="window.sendPOSWhatsAppReceipt()" style="background:#059669; color:#fff; font-weight:800; border:none; padding:7px 12px;">
                <i class="fa-brands fa-whatsapp"></i> Send WhatsApp Receipt
            </button>
            <button class="pos-btn pos-btn-outline" onclick="if (window.POSState.completedSale) { closeReceiptModal(); window.openCustomerBillModal(window.POSState.completedSale); }" style="color:var(--pos-red); border-color:var(--pos-red);">
                <i class="fa-solid fa-file-invoice"></i> Customer Bill (A4)
            </button>
            <button class="pos-btn pos-btn-primary" onclick="window.printReceipt()">
                <i class="fa-solid fa-print"></i> Print Receipt (80mm)
            </button>
        </div>
    </div>
</div>

<!-- POS Customer Scan QR Modal -->
<div class="pos-modal-overlay" id="posQrModal" style="display:none; z-index:99999;">
    <div class="pos-modal" style="max-width:330px; text-align:center; padding:18px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
            <h4 style="margin:0; font-size:1rem; color:#0f172a;" id="posQrModalTitle">
                <i class="fa-solid fa-qrcode" style="color:var(--pos-red);"></i> Scan to Pay
            </h4>
            <button class="pos-modal-close" onclick="window.closePOSQrModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="background:#f8fafc; border:2px dashed #cbd5e1; border-radius:10px; padding:12px; margin-bottom:10px;">
            <img src="../assets/images/zindigi_payment_qr.jpg" alt="Official Payment QR" style="width:100%; max-width:230px; height:auto; border-radius:8px; display:block; margin:0 auto;" onerror="this.src='../assets/images/payment_qr.jpg'">
        </div>
        <div style="font-size:0.85rem; font-weight:900; color:#0f172a;">SAFDAR MOBILE STORE</div>
        <div style="font-size:0.75rem; color:#059669; font-weight:800; margin-top:2px;">Account / Raast ID: 0333 9688007</div>
        <div style="font-size:0.7rem; color:#64748b; margin-top:1px;">Till ID: 946425125 | JS Bank / Zindigi</div>
        <button type="button" class="pos-btn pos-btn-primary pos-btn-block" style="margin-top:12px; font-size:0.82rem; padding:7px;" onclick="window.closePOSQrModal()">
            Done / Close QR
        </button>
    </div>
</div>

<!-- POS Quick Service Item Entry Modal -->
<div class="pos-modal-overlay" id="posServiceEntryModal" style="display:none; z-index:99999;">
    <div class="pos-modal" style="max-width:480px; padding:20px;">
        <div class="pos-modal-header" style="border-bottom:1.5px solid var(--pos-border); padding-bottom:10px; margin-bottom:14px;">
            <h3 class="pos-modal-title" style="margin:0; font-size:1.15rem;" id="posServiceModalTitle">
                <i class="fa-solid fa-plus-circle" style="color:var(--pos-red);"></i> Add Store Service to Cart
            </h3>
            <button class="pos-modal-close" onclick="window.closePOSServiceModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form id="posServiceEntryForm" onsubmit="window.submitPOSServiceToCart(event)">
            <input type="hidden" id="posServiceType" value="custom">

            <div style="margin-bottom:10px; display:none;" id="posServiceSelectRepairRow">
                <label style="font-size:0.75rem; font-weight:700; color:#0f172a; display:block; margin-bottom:3px;">
                    Select Existing Repair Job Ticket (Optional)
                </label>
                <select id="posServiceRepairSelect" class="form-select" style="padding:6px 8px; font-size:0.8rem;" onchange="window.onPOSRepairSelectChange()">
                    <option value="">-- Choose active repair ticket or type custom --</option>
                </select>
            </div>

            <div style="margin-bottom:10px;">
                <label style="font-size:0.75rem; font-weight:700; color:#0f172a; display:block; margin-bottom:3px;">
                    Service Item Description / Name *
                </label>
                <input type="text" id="posServiceName" class="form-input" style="padding:6px 10px; font-size:0.82rem;" required placeholder="e.g. PESCO Electricity Bill / Repair Labor">
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:10px;">
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:#0f172a; display:block; margin-bottom:3px;">
                        Customer Phone / WhatsApp
                    </label>
                    <input type="text" id="posServiceCustomerPhone" class="form-input" style="padding:6px 10px; font-size:0.82rem;" placeholder="e.g. 03001234567">
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:#0f172a; display:block; margin-bottom:3px;">
                        Reference / Consumer No
                    </label>
                    <input type="text" id="posServiceRefNumber" class="form-input" style="padding:6px 10px; font-size:0.82rem;" placeholder="e.g. Bill Consumer ID / Trx">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:14px;">
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:#0f172a; display:block; margin-bottom:3px;">
                        Principal / Base Amount (PKR) *
                    </label>
                    <input type="number" id="posServiceBaseAmount" class="form-input" style="padding:6px 10px; font-size:0.85rem; font-weight:800; text-align:right;" required placeholder="0" oninput="window.calcPOSServiceTotal()">
                </div>
                <div>
                    <label style="font-size:0.75rem; font-weight:700; color:#059669; display:block; margin-bottom:3px;">
                        Store Fee / Commission (PKR)
                    </label>
                    <input type="number" id="posServiceShopFee" class="form-input" style="padding:6px 10px; font-size:0.85rem; font-weight:800; color:#059669; text-align:right;" placeholder="30" value="0" oninput="window.calcPOSServiceTotal()">
                </div>
            </div>

            <!-- Total Price Badge -->
            <div style="background:#f1f5f9; border:1px solid #cbd5e1; border-radius:6px; padding:8px 12px; display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <span style="font-size:0.78rem; font-weight:800; color:#334155;">TOTAL CART PRICE:</span>
                <span id="posServiceTotalDisplay" style="font-size:1.15rem; font-weight:900; color:var(--pos-red);">PKR 0</span>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="pos-btn pos-btn-outline" onclick="window.closePOSServiceModal()">Cancel</button>
                <button type="submit" class="pos-btn pos-btn-primary" style="font-weight:700;">
                    <i class="fa-solid fa-cart-plus"></i> Add Service to Cart
                </button>
            </div>
        </form>
    </div>
</div>

<!-- POS Custom Mobile Repair Bill & Claim Receipt Modal -->
<div class="pos-modal-overlay" id="posCustomRepairBillModal" style="display:none; z-index:99999;">
    <div class="pos-modal" style="max-width:540px; padding:18px 20px; max-height:92vh; overflow-y:auto;">
        <div class="pos-modal-header" style="border-bottom:1.5px solid var(--pos-border); padding-bottom:10px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;">
            <h3 class="pos-modal-title" style="margin:0; font-size:1.1rem; color:#0f172a; display:flex; align-items:center; gap:8px;">
                <span style="background:#fee2e2; color:#dc2626; width:30px; height:30px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; font-size:0.9rem;">
                    <i class="fa-solid fa-screwdriver-wrench"></i>
                </span>
                <span>Mobile Repair Custom Bill & Receipt</span>
            </h3>
            <button class="pos-modal-close" onclick="window.closePOSCustomRepairModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form id="posCustomRepairForm" onsubmit="window.handlePOSRepairFormSubmit(event)">
            <!-- Ticket No & Date Header -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:6px 12px; display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <div>
                    <span style="font-size:0.7rem; color:#64748b; font-weight:700; text-transform:uppercase;">Ticket ID:</span>
                    <input type="text" id="posRepairTicketId" readonly style="border:none; background:transparent; font-family:monospace; font-weight:900; color:var(--pos-red); font-size:0.9rem; width:130px; outline:none;" value="">
                </div>
                <div style="font-size:0.75rem; color:#475569; font-weight:700;">
                    <i class="fa-solid fa-calendar-day" style="color:var(--pos-gold-dark);"></i> <?php echo date('Y-m-d H:i'); ?>
                </div>
            </div>

            <!-- Quick Select Mobile Spare Part from Catalog -->
            <div style="background:#f1f5f9; border:1.5px solid #cbd5e1; border-radius:8px; padding:8px 12px; margin-bottom:10px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                    <label style="font-size:0.74rem; font-weight:800; color:#1e293b; display:flex; align-items:center; gap:5px;">
                        <i class="fa-solid fa-microchip" style="color:var(--pos-red);"></i> Select Spare Part from Catalog (Auto-fill Price & Model)
                    </label>
                    <a href="repair-parts.php" target="_blank" style="font-size:0.68rem; color:#2563eb; font-weight:700; text-decoration:none;">
                        <i class="fa-solid fa-plus"></i> Manage Parts
                    </a>
                </div>
                <select id="posRepairSparePartSelect" class="form-select" style="padding:6px 8px; font-size:0.8rem; font-weight:700; width:100%;" onchange="window.onPOSRepairPartSelected()">
                    <option value="">-- Choose Spare Part (e.g. OLED Display, Battery, Port) or type custom --</option>
                </select>
            </div>

            <!-- Customer Name & Mobile No -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:10px;">
                <div>
                    <label style="font-size:0.74rem; font-weight:800; color:#0f172a; display:block; margin-bottom:3px;">
                        Customer Name *
                    </label>
                    <input type="text" id="posRepairCustName" class="form-input" style="padding:6px 10px; font-size:0.82rem; width:100%;" required placeholder="e.g. Muhammad Tariq" oninput="window.syncPOSCustomerInfo()">
                </div>
                <div>
                    <label style="font-size:0.74rem; font-weight:800; color:#0f172a; display:block; margin-bottom:3px;">
                        Customer Mobile No. *
                    </label>
                    <input type="text" id="posRepairCustPhone" class="form-input" style="padding:6px 10px; font-size:0.82rem; width:100%;" required placeholder="e.g. 0333 9688007" oninput="window.syncPOSCustomerInfo()">
                </div>
            </div>

            <!-- Device Brand & Model -->
            <div style="display:grid; grid-template-columns: 1fr 1.2fr; gap:10px; margin-bottom:10px;">
                <div>
                    <label style="font-size:0.74rem; font-weight:800; color:#0f172a; display:block; margin-bottom:3px;">
                        Device Brand *
                    </label>
                    <select id="posRepairDeviceBrand" class="form-select" style="padding:6px 8px; font-size:0.82rem;" required>
                        <option value="Samsung">Samsung</option>
                        <option value="Apple iPhone">Apple iPhone</option>
                        <option value="Infinix">Infinix</option>
                        <option value="Tecno">Tecno</option>
                        <option value="Xiaomi / Redmi">Xiaomi / Redmi</option>
                        <option value="Vivo">Vivo</option>
                        <option value="Realme">Realme</option>
                        <option value="Oppo">Oppo</option>
                        <option value="Other">Other / Feature Phone</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:0.74rem; font-weight:800; color:#0f172a; display:block; margin-bottom:3px;">
                        Mobile Model Name / Number *
                    </label>
                    <input type="text" id="posRepairDeviceModel" class="form-input" style="padding:6px 10px; font-size:0.82rem;" required placeholder="e.g. Galaxy A54 5G / iPhone 13 Pro">
                </div>
            </div>

            <!-- Issue in Mobile (Blank - Technician writes by own) -->
            <div style="margin-bottom:10px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:3px;">
                    <label style="font-size:0.74rem; font-weight:800; color:#b91c1c; display:block;">
                        <i class="fa-solid fa-triangle-exclamation"></i> Issue / Fault in Mobile (Technician writes by own) *
                    </label>
                    <span style="font-size:0.68rem; color:#64748b;">Blank - Type exact fault</span>
                </div>
                <textarea id="posRepairFaultDesc" class="form-input" style="padding:7px 10px; font-size:0.82rem; min-height:50px; width:100%; resize:vertical;" required placeholder="e.g. Touch screen glass cracked, internal display flickering, water damage on charging port, power on issue"></textarea>
            </div>

            <!-- Work Done / Resolved Issue / Parts Replaced (Optional Note) -->
            <div style="margin-bottom:10px;">
                <label style="font-size:0.74rem; font-weight:800; color:#059669; display:block; margin-bottom:3px;">
                    <i class="fa-solid fa-screwdriver"></i> Work Done / Parts Used / Resolved Issue (Optional)
                </label>
                <textarea id="posRepairWorkDone" class="form-input" style="padding:7px 10px; font-size:0.82rem; min-height:45px; width:100%; resize:vertical;" placeholder="e.g. Replaced original AMOLED display + OCA lamination done + charging IC trace jumpered"></textarea>
            </div>

            <!-- Financials: Part Price, Labor Fee, Advance Paid & Balance Due -->
            <div style="background:#fffdf5; border:1.5px solid #fde68a; border-radius:8px; padding:10px 12px; margin-bottom:14px;">
                <div style="font-size:0.75rem; font-weight:900; color:#92400e; text-transform:uppercase; margin-bottom:6px; letter-spacing:0.5px; display:flex; justify-content:space-between; align-items:center;">
                    <span><i class="fa-solid fa-coins"></i> Billing & Pricing Breakdown</span>
                    <span id="posRepairSelectedPartBadge" style="font-size:0.68rem; color:#059669; font-weight:800;"></span>
                </div>
                <div style="display:grid; grid-template-columns: 1.1fr 1fr 1.1fr 1.1fr; gap:6px;">
                    <div>
                        <label style="font-size:0.68rem; font-weight:800; color:#64748b; display:block; margin-bottom:2px;">
                            Part Price (PKR)
                        </label>
                        <input type="number" id="posRepairPartCostDisplay" class="form-input" style="padding:5px 6px; font-size:0.82rem; font-weight:800; text-align:right;" placeholder="0" value="0" oninput="window.calcPOSRepairBalance()">
                    </div>
                    <div>
                        <label style="font-size:0.68rem; font-weight:800; color:#059669; display:block; margin-bottom:2px;">
                            Labor Fee (PKR)
                        </label>
                        <input type="number" id="posRepairLaborFee" class="form-input" style="padding:5px 6px; font-size:0.82rem; font-weight:800; color:#059669; text-align:right;" placeholder="0" value="0" oninput="window.calcPOSRepairBalance()">
                    </div>
                    <div>
                        <label style="font-size:0.68rem; font-weight:800; color:#0f172a; display:block; margin-bottom:2px;">
                            Total Bill (PKR) *
                        </label>
                        <input type="number" id="posRepairTotalBill" class="form-input" style="padding:5px 6px; font-size:0.85rem; font-weight:900; text-align:right; color:#0f172a;" required placeholder="0" oninput="window.calcPOSRepairBalance(true)">
                    </div>
                    <div>
                        <label style="font-size:0.68rem; font-weight:800; color:#dc2626; display:block; margin-bottom:2px;">
                            Balance (PKR)
                        </label>
                        <input type="text" id="posRepairBalanceDue" class="form-input" readonly style="padding:5px 6px; font-size:0.82rem; font-weight:900; text-align:right; color:#dc2626; background:#fee2e2; border-color:#fca5a5;" value="PKR 0">
                    </div>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px; padding-top:6px; border-top:1px dashed #fde68a;">
                    <label style="font-size:0.72rem; font-weight:800; color:#059669;">Advance Received (PKR):</label>
                    <input type="number" id="posRepairAdvancePaid" class="form-input" style="width:130px; padding:4px 8px; font-size:0.82rem; font-weight:800; text-align:right; color:#059669;" placeholder="0" value="0" oninput="window.calcPOSRepairBalance()">
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                <button type="button" class="pos-btn pos-btn-outline" onclick="window.closePOSCustomRepairModal()">
                    Cancel
                </button>
                <div style="display:flex; gap:6px;">
                    <button type="button" class="pos-btn pos-btn-outline" style="color:var(--pos-red); border-color:var(--pos-red); font-weight:700;" onclick="window.printPOSRepairSlipDirect()">
                        <i class="fa-solid fa-print"></i> Print 80mm Slip
                    </button>
                    <button type="submit" class="pos-btn pos-btn-primary" style="font-weight:800; background:linear-gradient(135deg, #8B0000 0%, #D71920 100%);">
                        <i class="fa-solid fa-cart-plus"></i> Add to POS Cart & Save
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- POS Existing Customer Picker Modal -->
<div class="pos-modal-overlay" id="posExistingCustomerModal" style="display:none; z-index:99999;">
    <div class="pos-modal" style="max-width:560px; padding:20px; max-height:90vh; overflow-y:auto;">
        <div class="pos-modal-header" style="border-bottom:1.5px solid var(--pos-border); padding-bottom:10px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;">
            <h3 class="pos-modal-title" style="margin:0; font-size:1.15rem; color:var(--pos-text); display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-users" style="color:var(--pos-red);"></i> Select Existing Customer
            </h3>
            <button class="pos-modal-close" onclick="window.closeExistingCustomerPickerModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div style="margin-bottom:12px;">
            <input type="text" id="posCustModalSearchInput" class="form-input" placeholder="Search customer by name, phone, or balance..." oninput="window.filterPOSCustomerModalList(this.value)" style="padding:8px 12px; font-size:0.85rem; width:100%;">
        </div>

        <div id="posCustModalList" style="max-height:360px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:8px;">
            <!-- Rendered dynamically by JS -->
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:14px;">
            <a href="customers.php" target="_blank" style="font-size:0.78rem; color:#2563eb; font-weight:700; text-decoration:none;">
                <i class="fa-solid fa-user-plus"></i> Manage / Add New in Customer Directory &rarr;
            </a>
            <button type="button" class="pos-btn pos-btn-outline" onclick="window.closeExistingCustomerPickerModal()">Close</button>
        </div>
    </div>
</div>

<script>
window.initialPOSProducts = <?php echo json_encode(array_values($products), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
