/* ==========================================================================
   SMS POS — Admin Panel Application Engine
   Pure JavaScript AJAX & State Controller
   ========================================================================== */

(function () {
    'use strict';

    // Global POS Cart State
    window.POSState = {
        products: [],
        categories: [],
        cart: [],
        selectedCategory: 'all',
        searchTerm: '',
        discount: 0,
        paymentMethod: 'cash',
        customerName: '',
        customerPhone: '',
        amountReceived: 0,
        completedSale: null
    };

    document.addEventListener('DOMContentLoaded', function () {
        initPOSBilling();
    });

    // POS Billing System Controller
    function initPOSBilling() {
        const posArea = document.getElementById('posBillingArea');
        if (!posArea) return;

        loadPOSProducts();

        // Bind Category Filters
        document.querySelectorAll('.pos-cat-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.pos-cat-btn').forEach(function (b) { b.classList.remove('active'); });
                this.classList.add('active');
                window.POSState.selectedCategory = this.getAttribute('data-category');
                renderPOSProducts();
            });
        });

        // Search Input
        const searchInput = document.getElementById('posSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                window.POSState.searchTerm = this.value.trim().toLowerCase();
                renderPOSProducts();
            });
        }

        // Barcode Scanner Event Listener (Keyboard wedge listener)
        let barcodeBuffer = '';
        let lastKeyTime = Date.now();
        document.addEventListener('keydown', function (e) {
            if (document.activeElement.tagName === 'INPUT' && document.activeElement.id !== 'posSearchInput') {
                return;
            }

            const now = Date.now();
            if (now - lastKeyTime > 100) {
                barcodeBuffer = '';
            }
            lastKeyTime = now;

            if (e.key === 'Enter') {
                if (barcodeBuffer.length > 3) {
                    handleBarcodeScan(barcodeBuffer);
                    barcodeBuffer = '';
                }
            } else if (e.key.length === 1) {
                barcodeBuffer += e.key;
            }
        });

        // Payment Method Selectors with Digital Verification Toggle
        document.querySelectorAll('.payment-opt').forEach(function (opt) {
            opt.addEventListener('click', function () {
                document.querySelectorAll('.payment-opt').forEach(function (o) { o.classList.remove('active'); });
                this.classList.add('active');
                const method = this.getAttribute('data-method');
                window.POSState.paymentMethod = method;

                const confirmBox = document.getElementById('posDigitalPaymentConfirmBox');
                const titleEl = document.getElementById('posDigitalMethodTitle');
                const iconEl = document.getElementById('posDigitalMethodIcon');
                const accountLabelEl = document.getElementById('posDigitalAccountLabel');

                if (!confirmBox) return;

                if (method === 'cash') {
                    confirmBox.style.display = 'none';
                } else if (method === 'easypaisa') {
                    confirmBox.style.display = 'block';
                    if (titleEl) titleEl.textContent = 'Easypaisa Payment Verification';
                    if (iconEl) { iconEl.className = 'fa-solid fa-mobile-screen-button'; iconEl.style.color = '#00a859'; }
                    if (accountLabelEl) accountLabelEl.innerHTML = 'Easypaisa Mobile: <strong>0333 9688007</strong>';
                } else if (method === 'jazzcash') {
                    confirmBox.style.display = 'block';
                    if (titleEl) titleEl.textContent = 'JazzCash Payment Verification';
                    if (iconEl) { iconEl.className = 'fa-solid fa-wallet'; iconEl.style.color = '#e30613'; }
                    if (accountLabelEl) accountLabelEl.innerHTML = 'JazzCash Mobile: <strong>0333 9688007</strong>';
                } else if (method === 'bank') {
                    confirmBox.style.display = 'block';
                    if (titleEl) titleEl.textContent = 'JS Bank / Raast / Zindigi Verification';
                    if (iconEl) { iconEl.className = 'fa-solid fa-building-columns'; iconEl.style.color = '#2563eb'; }
                    if (accountLabelEl) accountLabelEl.innerHTML = 'Raast ID: <strong>0333 9688007</strong> | Till: <strong>946425125</strong>';
                }
            });
        });

        // Discount Input
        const discInput = document.getElementById('posDiscountInput');
        if (discInput) {
            discInput.addEventListener('input', function () {
                window.POSState.discount = parseFloat(this.value) || 0;
                updateCartTotals();
            });
        }

        // Amount Received Input
        const recInput = document.getElementById('posAmountReceivedInput');
        if (recInput) {
            recInput.addEventListener('input', function () {
                window.POSState.amountReceived = parseFloat(this.value) || 0;
                updateCartTotals();
            });
        }

        // Complete Sale Button
        const checkoutBtn = document.getElementById('completeSaleBtn');
        if (checkoutBtn) {
            checkoutBtn.addEventListener('click', processPOSCheckout);
        }
    }

    // QR Modal Controls
    window.openPOSQrModal = function () {
        const modal = document.getElementById('posQrModal');
        if (modal) modal.style.display = 'flex';
    };

    window.closePOSQrModal = function () {
        const modal = document.getElementById('posQrModal');
        if (modal) modal.style.display = 'none';
    };

    // Store Services Pre-defined Catalog for POS
    const STORE_SERVICES_CATALOG = [
        // 1. MOBILE REPAIRS LAB
        { id: 'srv-rep-custom-bill', category: 'service-repairs', serviceType: 'repair_custom', name: 'Create Custom Repair Bill & Claim Slip', sellingPrice: 0, costPrice: 0, icon: 'fa-file-invoice-dollar', color: '#d71920', badge: 'CUSTOM BILL', isService: true, isCustomRepair: true },
        { id: 'srv-rep-screen', category: 'service-repairs', serviceType: 'repair', name: 'Screen / Touch LCD Replacement Labor', sellingPrice: 3500, costPrice: 2000, icon: 'fa-mobile-screen', color: '#d71920', badge: 'REPAIR LAB', isService: true },
        { id: 'srv-rep-battery', category: 'service-repairs', serviceType: 'repair', name: 'Battery Replacement & BMS Setup', sellingPrice: 2500, costPrice: 1500, icon: 'fa-battery-full', color: '#059669', badge: 'REPAIR LAB', isService: true },
        { id: 'srv-rep-ic', category: 'service-repairs', serviceType: 'repair', name: 'Motherboard IC Micro-Soldering', sellingPrice: 4000, costPrice: 1800, icon: 'fa-microchip', color: '#d97706', badge: 'REPAIR LAB', isService: true },
        { id: 'srv-rep-water', category: 'service-repairs', serviceType: 'repair', name: 'Ultrasonic Chemical PCB Cleaning', sellingPrice: 2500, costPrice: 800, icon: 'fa-droplet', color: '#2563eb', badge: 'REPAIR LAB', isService: true },
        { id: 'srv-rep-port', category: 'service-repairs', serviceType: 'repair', name: 'Charging Port Sub-board Replacement', sellingPrice: 1500, costPrice: 600, icon: 'fa-plug', color: '#7c3aed', badge: 'REPAIR LAB', isService: true },
        { id: 'srv-rep-claim', category: 'service-repairs', serviceType: 'repair_claim', name: 'Claim / Pay Active Repair Job (REP-...)', sellingPrice: 0, costPrice: 0, icon: 'fa-receipt', color: '#b91c1c', badge: 'TICKET CLAIM', isService: true, requiresInput: true },

        // 2. EASYPAISA / JAZZCASH / MONEY TRANSFER
        { id: 'srv-ep-cashin', category: 'service-easypaisa-jazzcash', serviceType: 'easypaisa_in', name: 'Easypaisa Cash-In (Deposit)', sellingPrice: 0, costPrice: 0, icon: 'fa-mobile-screen-button', color: '#00a859', badge: 'EASYPAISA', isService: true, requiresInput: true, defaultFee: 50 },
        { id: 'srv-ep-cashout', category: 'service-easypaisa-jazzcash', serviceType: 'easypaisa_out', name: 'Easypaisa Cash-Out (Withdrawal)', sellingPrice: 0, costPrice: 0, icon: 'fa-hand-holding-dollar', color: '#00a859', badge: 'EASYPAISA', isService: true, requiresInput: true, defaultFee: 50 },
        { id: 'srv-jc-cashin', category: 'service-easypaisa-jazzcash', serviceType: 'jazzcash_in', name: 'JazzCash Cash-In (Deposit)', sellingPrice: 0, costPrice: 0, icon: 'fa-wallet', color: '#e30613', badge: 'JAZZCASH', isService: true, requiresInput: true, defaultFee: 50 },
        { id: 'srv-jc-cashout', category: 'service-easypaisa-jazzcash', serviceType: 'jazzcash_out', name: 'JazzCash Cash-Out (Withdrawal)', sellingPrice: 0, costPrice: 0, icon: 'fa-money-bill-transfer', color: '#e30613', badge: 'JAZZCASH', isService: true, requiresInput: true, defaultFee: 50 },
        { id: 'srv-raast-tx', category: 'service-easypaisa-jazzcash', serviceType: 'raast_bank', name: 'Raast / JS Bank Instant Transfer', sellingPrice: 0, costPrice: 0, icon: 'fa-building-columns', color: '#2563eb', badge: 'RAAST BANK', isService: true, requiresInput: true, defaultFee: 30 },

        // 3. UTILITY BILLS PAYMENT
        { id: 'srv-bill-pesco', category: 'service-bills', serviceType: 'bill', name: 'PESCO Electricity Bill Payment', sellingPrice: 0, costPrice: 0, icon: 'fa-bolt', color: '#eab308', badge: 'PESCO POWER', isService: true, requiresInput: true, defaultFee: 30 },
        { id: 'srv-bill-sngpl', category: 'service-bills', serviceType: 'bill', name: 'SNGPL Sui Northern Gas Bill', sellingPrice: 0, costPrice: 0, icon: 'fa-fire-flame-curved', color: '#f97316', badge: 'SUI GAS', isService: true, requiresInput: true, defaultFee: 30 },
        { id: 'srv-bill-wssp', category: 'service-bills', serviceType: 'bill', name: 'WSSP Water & Sanitation Bill', sellingPrice: 0, costPrice: 0, icon: 'fa-faucet-drip', color: '#06b6d4', badge: 'WATER BILL', isService: true, requiresInput: true, defaultFee: 30 },
        { id: 'srv-bill-ptcl', category: 'service-bills', serviceType: 'bill', name: 'PTCL / Flash Fiber Broadband Bill', sellingPrice: 0, costPrice: 0, icon: 'fa-globe', color: '#10b981', badge: 'INTERNET', isService: true, requiresInput: true, defaultFee: 30 },
        { id: 'srv-bill-custom', category: 'service-bills', serviceType: 'bill', name: 'Custom Utility Bill Payment', sellingPrice: 0, costPrice: 0, icon: 'fa-file-invoice-dollar', color: '#3b82f6', badge: 'UTILITY BILL', isService: true, requiresInput: true, defaultFee: 30 },

        // 4. MOBILE PACKAGES & LOAD (Dynamic from Admin Packages Catalog)
        { id: 'srv-pkg-load-custom', category: 'service-packages', serviceType: 'package', name: 'Instant All-Network SIM Package & Easyload Recharge', sellingPrice: 0, costPrice: 0, icon: 'fa-mobile-screen', color: '#f59e0b', badge: 'ALL SIM LOAD', isService: true, requiresInput: true, defaultFee: 0 },

        // 5. NADRA & CITIZEN KIOSK
        { id: 'srv-ndr-cnic', category: 'service-nadra', serviceType: 'nadra', name: 'Smart CNIC / NICOP Online Filing Fee', sellingPrice: 1500, costPrice: 500, icon: 'fa-id-card', color: '#7c3aed', badge: 'NADRA CNIC', isService: true },
        { id: 'srv-ndr-frc', category: 'service-nadra', serviceType: 'nadra', name: 'Family Registration Certificate (FRC)', sellingPrice: 1000, costPrice: 300, icon: 'fa-people-roof', color: '#6366f1', badge: 'NADRA FRC', isService: true },
        { id: 'srv-ndr-domicile', category: 'service-nadra', serviceType: 'nadra', name: 'Domicile Application & Document Prep', sellingPrice: 800, costPrice: 200, icon: 'fa-file-signature', color: '#059669', badge: 'CITIZEN', isService: true },
        { id: 'srv-ndr-police', category: 'service-nadra', serviceType: 'nadra', name: 'Police Character Clearance Token', sellingPrice: 700, costPrice: 200, icon: 'fa-shield-halved', color: '#dc2626', badge: 'POLICE CLEAR', isService: true },
        { id: 'srv-ndr-bform', category: 'service-nadra', serviceType: 'nadra', name: 'Child Birth Registration (B-Form)', sellingPrice: 600, costPrice: 150, icon: 'fa-baby', color: '#0891b2', badge: 'B-FORM', isService: true }
    ];

    window.POSActiveRepairTickets = [];

    // Load Products & Active Repair Tickets for POS
    function loadPOSProducts() {
        if (window.initialPOSProducts && Array.isArray(window.initialPOSProducts) && window.initialPOSProducts.length > 0) {
            window.POSState.products = window.initialPOSProducts;
            renderPOSProducts();
        }

        fetch('../backend/products.php?status=active')
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res && res.status === 'success') {
                    window.POSState.products = res.data;
                    renderPOSProducts();
                }
            })
            .catch(function () {});

        // Also fetch active repair tickets for 1-click pickup payment
        fetch('../backend/repairs.php')
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res && res.status === 'success') {
                    window.POSActiveRepairTickets = res.data || [];
                    populatePOSRepairSelect();
                }
            });

        // Load Dynamic Admin SIM Packages Catalog into POS
        fetch('../backend/packages.php?action=plans')
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res && res.status === 'success' && Array.isArray(res.data)) {
                    STORE_SERVICES_CATALOG = STORE_SERVICES_CATALOG.filter(function (s) {
                        return !s.isDynamicSimPlan;
                    });
                    res.data.forEach(function (plan) {
                        const netColors = { jazz: '#e30613', zong: '#16a34a', telenor: '#0284c7', ufone: '#ea580c', onic: '#7c3aed' };
                        const netColor = netColors[(plan.network || '').toLowerCase()] || '#3b82f6';
                        STORE_SERVICES_CATALOG.push({
                            id: 'sim-plan-' + plan.id,
                            category: 'service-packages',
                            serviceType: 'package',
                            name: plan.name + (plan.dataGb ? ' (' + plan.dataGb + ')' : ''),
                            sellingPrice: Number(plan.retailPrice || 0),
                            costPrice: Number(plan.costPrice || 0),
                            icon: 'fa-signal',
                            color: netColor,
                            badge: (plan.network || 'SIM').toUpperCase() + ' ' + (plan.validity || ''),
                            isService: true,
                            isDynamicSimPlan: true,
                            planData: plan
                        });
                    });
                    if (window.POSState.selectedCategory === 'service-packages') {
                        renderPOSProducts();
                    }
                }
            })
            .catch(function () {});
    }

    function populatePOSRepairSelect() {
        const sel = document.getElementById('posServiceRepairSelect');
        if (!sel) return;
        sel.innerHTML = '<option value="">-- Choose active repair ticket or type custom --</option>' +
            window.POSActiveRepairTickets.map(function (t) {
                const bal = Number(t.balanceDue || t.totalBill || 0).toLocaleString();
                return `<option value="${t.id}" data-due="${t.balanceDue || 0}" data-model="${escapeHtml(t.deviceModel || '')}" data-name="${escapeHtml(t.customerName || '')}" data-phone="${escapeHtml(t.customerPhone || '')}">${t.id} - ${escapeHtml(t.customerName)} (${escapeHtml(t.deviceBrand)} ${escapeHtml(t.deviceModel)}) [Due: PKR ${bal}]</option>`;
            }).join('');
    }

    function renderPOSProducts() {
        const grid = document.getElementById('posProductGrid');
        if (!grid) return;

        const cat = window.POSState.selectedCategory;
        const q = (window.POSState.searchTerm || '').toLowerCase();

        let html = '';

        // Check if category is a Service Category
        if (cat.startsWith('service-')) {
            const services = STORE_SERVICES_CATALOG.filter(function (s) {
                const matchCat = s.category === cat;
                const matchQ = !q || s.name.toLowerCase().includes(q) || s.badge.toLowerCase().includes(q);
                return matchCat && matchQ;
            });

            if (services.length === 0) {
                grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:40px; color:#9ca3af;"><i class="fa-solid fa-screwdriver-wrench" style="font-size:2rem; margin-bottom:8px;"></i><p>No service items found</p></div>';
                return;
            }

            grid.innerHTML = services.map(function (s) {
                const priceText = s.sellingPrice > 0 ? ('PKR ' + s.sellingPrice.toLocaleString()) : 'Variable Amount';
                return `
                    <div class="pos-product-tile service-tile" style="border-top:3px solid ${s.color}; cursor:pointer;" onclick="window.onServiceTileClick('${s.id}')">
                        <div style="background:${s.color}15; color:${s.color}; width:48px; height:48px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.4rem; margin:0 auto 6px auto;">
                            <i class="fa-solid ${s.icon}"></i>
                        </div>
                        <span style="background:${s.color}20; color:${s.color}; font-size:0.62rem; font-weight:800; padding:1px 5px; border-radius:4px; text-transform:uppercase; margin-bottom:3px; display:inline-block;">${s.badge}</span>
                        <div class="pos-tile-name" style="font-size:0.75rem; font-weight:700;">${escapeHtml(s.name)}</div>
                        <div class="pos-tile-price" style="font-size:0.82rem; color:${s.color}; font-weight:900;">${priceText}</div>
                        <div class="pos-tile-stock" style="color:#059669; font-weight:700;">Instant Activation / Service</div>
                    </div>
                `;
            }).join('');
            return;
        }

        // Standard Product List Filtering
        let filtered = window.POSState.products;

        if (cat !== 'all') {
            filtered = filtered.filter(function (p) {
                const pCat = (p.category || p.categoryId || '').toLowerCase();
                const selCat = cat.toLowerCase();
                return pCat === selCat || pCat.startsWith(selCat) || selCat.startsWith(pCat) || pCat.includes(selCat);
            });
        }

        if (q) {
            filtered = filtered.filter(function (p) {
                const name = (p.name || '').toLowerCase();
                const sku = (p.sku || '').toLowerCase();
                const barcode = (p.barcode || '').toLowerCase();
                const brand = (p.brand || '').toLowerCase();
                const subCat = (p.subCategory || p.sub_category || '').toLowerCase();
                const unit = (p.unit || p.unitLabel || '').toLowerCase();
                return name.includes(q) || sku.includes(q) || barcode.includes(q) || brand.includes(q) || subCat.includes(q) || unit.includes(q);
            });
        }

        if (filtered.length === 0) {
            grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:40px; color:#9ca3af;"><i class="fa-solid fa-box-open" style="font-size:2rem; margin-bottom:8px;"></i><p>No products found</p></div>';
            return;
        }

        grid.innerHTML = filtered.map(function (p) {
            const outOfStock = p.stock <= 0 ? 'out-of-stock' : '';
            const price = Number(p.sellingPrice || p.priceNumeric || 0).toLocaleString();
            const unitSuffix = (p.unit && p.unit !== 'pcs' && p.unit !== 'piece') ? (' / ' + (p.unitLabel || p.unit)) : '';
            const stockUnitSuffix = (p.unit && p.unit !== 'pcs' && p.unit !== 'piece') ? (' ' + (p.unitLabel || p.unit)) : ' in stock';

            let imgUrl = p.image || '';
            if (imgUrl && !imgUrl.startsWith('http://') && !imgUrl.startsWith('https://') && !imgUrl.startsWith('/') && !imgUrl.startsWith('../')) {
                imgUrl = '../' + imgUrl;
            }

            return `
                <div class="pos-product-tile ${outOfStock}" onclick="window.addToPOSCart('${p.id}')">
                    <img src="${escapeHtml(imgUrl)}" class="pos-tile-img" loading="lazy" decoding="async" onerror="this.src='https://images.unsplash.com/photo-1598327105666-5b89351aff97?w=600&auto=format&fit=crop&q=80'">
                    <div class="pos-tile-name">${escapeHtml(p.name)}</div>
                    <div class="pos-tile-price">PKR ${price}${unitSuffix}</div>
                    <div class="pos-tile-stock">${p.stock > 0 ? (p.stock + ' ' + stockUnitSuffix) : 'OUT OF STOCK'}</div>
                </div>
            `;
        }).join('');
    }

    // Handle clicking a service tile
    window.onServiceTileClick = function (serviceId) {
        const srv = STORE_SERVICES_CATALOG.find(function (s) { return s.id === serviceId; });
        if (!srv) return;

        if (srv.id === 'srv-rep-custom-bill' || srv.isCustomRepair) {
            window.openPOSCustomRepairModal();
            return;
        }

        if (srv.requiresInput || srv.sellingPrice <= 0) {
            window.openPOSServiceModal(srv.serviceType, srv);
        } else {
            window.addServiceToPOSCart(srv);
        }
    };

    // --- POS CUSTOM MOBILE REPAIR BILLING & RECEIPT CONTROLLER ---
    window.POSRepairPartsCatalog = [];

    function loadPOSRepairParts() {
        fetch('../backend/repair_parts.php')
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res && res.status === 'success') {
                    window.POSRepairPartsCatalog = res.data || [];
                    populatePOSRepairPartsDropdown();
                }
            })
            .catch(function(err) {
                console.error('Error fetching spare parts catalog:', err);
            });
    }

    function populatePOSRepairPartsDropdown() {
        const sel = document.getElementById('posRepairSparePartSelect');
        if (!sel) return;

        let html = '<option value="">-- Choose Spare Part (e.g. OLED Display, Battery, Port) or type custom --</option>';
        window.POSRepairPartsCatalog.forEach(function(p) {
            const stockText = p.stock > 0 ? `(${p.stock} in stock)` : '(Out of Stock)';
            html += `<option value="${p.id}" data-cost="${p.costPrice}" data-sell="${p.sellingPrice}" data-brand="${escapeHtml(p.deviceBrand)}" data-model="${escapeHtml(p.deviceModel)}" data-name="${escapeHtml(p.name)}" data-warranty="${escapeHtml(p.warranty)}">[${escapeHtml(p.deviceBrand)}] ${escapeHtml(p.deviceModel)} - ${escapeHtml(p.name)} - PKR ${Number(p.sellingPrice).toLocaleString()} ${stockText}</option>`;
        });

        sel.innerHTML = html;
    }

    window.openPOSCustomRepairModal = function (prefillTicket = null) {
        const modal = document.getElementById('posCustomRepairBillModal');
        const form = document.getElementById('posCustomRepairForm');
        if (!modal || !form) return;

        form.reset();
        
        // Auto generate Ticket ID (sequential or random high 3-digit)
        const y = new Date().getFullYear();
        const rand = Math.floor(100 + Math.random() * 900);
        const ticketId = prefillTicket ? prefillTicket.id : `REP-${y}-${rand}`;
        document.getElementById('posRepairTicketId').value = ticketId;

        // Refresh Spare Parts dropdown
        loadPOSRepairParts();

        if (document.getElementById('posRepairPartCostDisplay')) document.getElementById('posRepairPartCostDisplay').value = '0';
        if (document.getElementById('posRepairLaborFee')) document.getElementById('posRepairLaborFee').value = '0';
        if (document.getElementById('posRepairTotalBill')) document.getElementById('posRepairTotalBill').value = '0';
        if (document.getElementById('posRepairAdvancePaid')) document.getElementById('posRepairAdvancePaid').value = '0';
        const badge = document.getElementById('posRepairSelectedPartBadge');
        if (badge) badge.textContent = '';

        // Pre-fill from POS customer inputs if present
        const posCustName = document.getElementById('posCustomerNameInput');
        const posCustPhone = document.getElementById('posCustomerPhoneInput');
        if (posCustName && posCustName.value) {
            document.getElementById('posRepairCustName').value = posCustName.value;
        }
        if (posCustPhone && posCustPhone.value) {
            document.getElementById('posRepairCustPhone').value = posCustPhone.value;
        }

        if (prefillTicket) {
            document.getElementById('posRepairCustName').value = prefillTicket.customerName || '';
            document.getElementById('posRepairCustPhone').value = prefillTicket.customerPhone || '';
            document.getElementById('posRepairDeviceBrand').value = prefillTicket.deviceBrand || 'Samsung';
            document.getElementById('posRepairDeviceModel').value = prefillTicket.deviceModel || '';
            document.getElementById('posRepairFaultDesc').value = prefillTicket.issueDescription || '';
            document.getElementById('posRepairWorkDone').value = prefillTicket.workDone || '';
            document.getElementById('posRepairTotalBill').value = prefillTicket.totalBill || '';
            document.getElementById('posRepairAdvancePaid').value = prefillTicket.advancePaid || 0;
        }

        window.calcPOSRepairBalance();
        modal.style.display = 'flex';
        setTimeout(function() {
            const faultInput = document.getElementById('posRepairFaultDesc');
            if (faultInput) faultInput.focus();
        }, 100);
    };

    window.closePOSCustomRepairModal = function () {
        const modal = document.getElementById('posCustomRepairBillModal');
        if (modal) modal.style.display = 'none';
    };

    window.onPOSRepairPartSelected = function () {
        const sel = document.getElementById('posRepairSparePartSelect');
        if (!sel || !sel.value) return;

        const opt = sel.options[sel.selectedIndex];
        const sellPrice = parseFloat(opt.getAttribute('data-sell')) || 0;
        const brand = opt.getAttribute('data-brand') || '';
        const model = opt.getAttribute('data-model') || '';
        const name = opt.getAttribute('data-name') || '';
        const warranty = opt.getAttribute('data-warranty') || '';

        // Auto fill Brand & Model
        const brandSelect = document.getElementById('posRepairDeviceBrand');
        if (brand && brandSelect) {
            for (let i = 0; i < brandSelect.options.length; i++) {
                if (brandSelect.options[i].value.toLowerCase() === brand.toLowerCase() || brandSelect.options[i].text.toLowerCase().includes(brand.toLowerCase())) {
                    brandSelect.selectedIndex = i;
                    break;
                }
            }
        }

        if (model && !document.getElementById('posRepairDeviceModel').value) {
            document.getElementById('posRepairDeviceModel').value = model;
        }

        // Auto append Work Done / Part note
        const workDoneInput = document.getElementById('posRepairWorkDone');
        if (workDoneInput) {
            const currentWork = workDoneInput.value.trim();
            const partNote = `Replaced ${model} ${name} [${warranty}]`;
            workDoneInput.value = currentWork ? (currentWork + ' + ' + partNote) : partNote;
        }

        // Set Part Price
        const partCostEl = document.getElementById('posRepairPartCostDisplay');
        if (partCostEl) partCostEl.value = sellPrice;

        const badge = document.getElementById('posRepairSelectedPartBadge');
        if (badge) {
            badge.textContent = `✓ Part: PKR ${sellPrice.toLocaleString()}`;
        }

        window.calcPOSRepairBalance();
    };

    window.calcPOSRepairBalance = function (isManualTotal = false) {
        const partPrice = parseFloat(document.getElementById('posRepairPartCostDisplay')?.value) || 0;
        const labor = parseFloat(document.getElementById('posRepairLaborFee')?.value) || 0;
        const totalBillInput = document.getElementById('posRepairTotalBill');
        
        if (!isManualTotal && totalBillInput) {
            const calcTotal = partPrice + labor;
            if (calcTotal > 0) totalBillInput.value = calcTotal;
        }

        const total = parseFloat(totalBillInput ? totalBillInput.value : 0) || 0;
        const advance = parseFloat(document.getElementById('posRepairAdvancePaid')?.value) || 0;
        const balance = Math.max(0, total - advance);
        const balanceEl = document.getElementById('posRepairBalanceDue');
        if (balanceEl) {
            balanceEl.value = 'PKR ' + balance.toLocaleString();
        }
    };

    window.syncPOSCustomerInfo = function () {
        const custName = document.getElementById('posRepairCustName').value;
        const custPhone = document.getElementById('posRepairCustPhone').value;
        const posCustName = document.getElementById('posCustomerNameInput');
        const posCustPhone = document.getElementById('posCustomerPhoneInput');
        if (posCustName && custName) posCustName.value = custName;
        if (posCustPhone && custPhone) posCustPhone.value = custPhone;
    };

    window.handlePOSRepairFormSubmit = function (e) {
        e.preventDefault();
        const ticketId = document.getElementById('posRepairTicketId').value;
        const custName = document.getElementById('posRepairCustName').value.trim();
        const custPhone = document.getElementById('posRepairCustPhone').value.trim();
        const brand = document.getElementById('posRepairDeviceBrand').value;
        const model = document.getElementById('posRepairDeviceModel').value.trim();
        const fault = document.getElementById('posRepairFaultDesc').value.trim();
        const workDone = document.getElementById('posRepairWorkDone').value.trim();
        const totalBill = parseFloat(document.getElementById('posRepairTotalBill').value) || 0;
        const advance = parseFloat(document.getElementById('posRepairAdvancePaid').value) || 0;
        const balance = Math.max(0, totalBill - advance);

        if (!custName || !custPhone || !model || !fault || totalBill <= 0) {
            alert('Please fill all required fields: Customer Name, Mobile No, Device Model, Fault Description, and Total Repair Bill.');
            return;
        }

        // Sync POS customer inputs
        const posCustName = document.getElementById('posCustomerNameInput');
        const posCustPhone = document.getElementById('posCustomerPhoneInput');
        if (posCustName) posCustName.value = custName;
        if (posCustPhone) posCustPhone.value = custPhone;

        const repairData = {
            ticketId: ticketId,
            customerName: custName,
            customerPhone: custPhone,
            deviceBrand: brand,
            deviceModel: model,
            issueDescription: fault,
            workDone: workDone,
            totalBill: totalBill,
            advancePaid: advance,
            balanceDue: balance,
            receivedDate: new Date().toISOString().replace('T', ' ').substring(0, 16)
        };

        // Asynchronously log to repair database
        fetch('../backend/repairs.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'create',
                id: ticketId,
                customerName: custName,
                customerPhone: custPhone,
                customerCity: 'Hangu',
                deviceBrand: brand,
                deviceModel: model,
                issueDescription: fault,
                workDone: workDone,
                totalBill: totalBill,
                advancePaid: advance,
                balanceDue: balance,
                jobStatus: advance >= totalBill ? 'Completed' : 'Received',
                paymentStatus: advance >= totalBill ? 'Paid in Full' : (advance > 0 ? 'Advance Paid' : 'Unpaid'),
                technicianNotes: workDone || fault
            })
        }).catch(function (err) { console.error('Error saving repair ticket:', err); });

        // Add to POS Cart
        const cartItem = {
            id: 'srv-rep-' + ticketId + '-' + Date.now(),
            name: `Repair [${ticketId}]: ${brand} ${model} - Fault: ${fault}`,
            sellingPrice: totalBill,
            costPrice: 0,
            stock: 9999,
            qty: 1,
            isService: true,
            serviceType: 'repair_custom',
            serviceRef: ticketId,
            repairData: repairData
        };

        window.POSState.cart.push(cartItem);
        window.closePOSCustomRepairModal();
        renderCartItems();

        if (confirm(`Repair Ticket ${ticketId} added to cart & saved!\n\nDo you want to print the 80mm Thermal Repair Claim Slip now?`)) {
            window.printPOSRepairSlipDirect(repairData);
        }
    };

    window.printPOSRepairSlipDirect = function (customData = null) {
        let data = customData;
        if (!data) {
            const ticketId = document.getElementById('posRepairTicketId').value;
            const custName = document.getElementById('posRepairCustName').value.trim();
            const custPhone = document.getElementById('posRepairCustPhone').value.trim();
            const brand = document.getElementById('posRepairDeviceBrand').value;
            const model = document.getElementById('posRepairDeviceModel').value.trim();
            const fault = document.getElementById('posRepairFaultDesc').value.trim();
            const workDone = document.getElementById('posRepairWorkDone').value.trim();
            const totalBill = parseFloat(document.getElementById('posRepairTotalBill').value) || 0;
            const advance = parseFloat(document.getElementById('posRepairAdvancePaid').value) || 0;
            const balance = Math.max(0, totalBill - advance);

            if (!custName || !model || !fault) {
                alert('Please fill Customer Name, Model, and Fault before printing.');
                return;
            }

            data = {
                ticketId: ticketId,
                customerName: custName,
                customerPhone: custPhone,
                deviceBrand: brand,
                deviceModel: model,
                issueDescription: fault,
                workDone: workDone,
                totalBill: totalBill,
                advancePaid: advance,
                balanceDue: balance,
                receivedDate: new Date().toISOString().replace('T', ' ').substring(0, 16)
            };
        }

        const printWin = window.open('', '_blank', 'width=420,height=600');
        if (!printWin) {
            alert('Please allow popups to print repair claim receipt.');
            return;
        }

        printWin.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Repair Claim Slip - ${data.ticketId}</title>
                <style>
                    * { box-sizing: border-box; margin: 0; padding: 0; }
                    body {
                        font-family: 'Courier New', Courier, monospace;
                        font-size: 11px;
                        line-height: 1.35;
                        color: #000;
                        background: #fff;
                        padding: 6px 4px;
                        width: 72mm;
                        max-width: 72mm;
                        margin: 0 auto;
                    }
                    .text-center { text-align: center; }
                    .bold { font-weight: bold; }
                    .divider { border-top: 1px dashed #000; margin: 5px 0; }
                    .double-divider { border-top: 2px solid #000; margin: 6px 0; }
                    table.info-table {
                        width: 100%;
                        border-collapse: collapse;
                        font-size: 11px;
                        table-layout: fixed;
                    }
                    table.info-table td { padding: 1.5px 0; vertical-align: top; }
                    .label-col { width: 38%; font-weight: bold; }
                    .val-col { width: 62%; text-align: right; word-break: break-word; }
                    .box-panel {
                        border: 1px solid #000;
                        padding: 5px 6px;
                        margin: 5px 0;
                        background: #fff;
                    }
                    @media print {
                        html, body {
                            width: 72mm !important;
                            max-width: 72mm !important;
                            margin: 0 auto !important;
                            padding: 2px 2px !important;
                        }
                    }
                </style>
            </head>
            <body>
                <div class="text-center bold" style="font-size:15px; letter-spacing:0.5px;">SAFDAR MOBILE STORE</div>
                <div class="text-center" style="font-size:10px;">Opp. Patt Bazar, Eidgah Road, Main Bazar Hangu</div>
                <div class="text-center bold" style="font-size:11px;">Helpline / WhatsApp: 03339688007</div>
                
                <div class="divider"></div>
                <div class="text-center bold" style="font-size:12px; letter-spacing:1px;">*** REPAIR CLAIM SLIP ***</div>
                <div class="text-center bold" style="font-size:13px; letter-spacing:3px; margin:2px 0;">*${data.ticketId}*</div>
                <div class="divider"></div>

                <table class="info-table">
                    <tr>
                        <td class="label-col">Ticket ID:</td>
                        <td class="val-col bold" style="font-size:12px;">${data.ticketId}</td>
                    </tr>
                    <tr>
                        <td class="label-col">Date / Time:</td>
                        <td class="val-col">${data.receivedDate || new Date().toLocaleString()}</td>
                    </tr>
                    <tr>
                        <td class="label-col">Customer:</td>
                        <td class="val-col bold">${escapeHtml(data.customerName)}</td>
                    </tr>
                    <tr>
                        <td class="label-col">Mobile No:</td>
                        <td class="val-col bold">${escapeHtml(data.customerPhone)}</td>
                    </tr>
                    <tr>
                        <td class="label-col">Device:</td>
                        <td class="val-col bold">${escapeHtml(data.deviceBrand)} ${escapeHtml(data.deviceModel)}</td>
                    </tr>
                </table>

                <div class="divider"></div>
                <div style="font-size:10px; font-weight:bold; margin-bottom:2px;">REPORTED FAULT / ISSUE (TECHNICIAN):</div>
                <div style="font-size:10.5px; border-left:2px solid #000; padding-left:5px; margin-bottom:4px; word-break:break-word;">
                    ${escapeHtml(data.issueDescription)}
                </div>

                ${data.workDone ? `
                <div style="font-size:10px; font-weight:bold; margin:4px 0 2px 0;">WORK DONE / PARTS REPLACED:</div>
                <div style="font-size:10.5px; border-left:2px solid #000; padding-left:5px; margin-bottom:4px; word-break:break-word;">
                    ${escapeHtml(data.workDone)}
                </div>` : ''}

                <div class="divider"></div>
                <div class="box-panel">
                    <div style="display:flex; justify-content:space-between; font-size:11px;">
                        <span>Total Repair Bill:</span>
                        <span class="bold">PKR ${Number(data.totalBill).toLocaleString()}</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:11px; margin-top:2px;">
                        <span>Advance Received:</span>
                        <span class="bold">PKR ${Number(data.advancePaid).toLocaleString()}</span>
                    </div>
                    <div style="border-top:1px dashed #000; margin-top:3px; padding-top:3px; display:flex; justify-content:space-between; font-size:13px; font-weight:bold;">
                        <span>BALANCE DUE:</span>
                        <span>PKR ${Number(data.balanceDue).toLocaleString()}</span>
                    </div>
                </div>

                <div class="divider"></div>
                <div style="font-size:9px; line-height:1.25; margin-top:4px;">
                    <strong>TERMS & CONDITIONS:</strong><br>
                    1. Please bring this original receipt when claiming your mobile.<br>
                    2. No warranty on water damage, physical screen breakage, or board burns.<br>
                    3. Safdar Mobile Store is not responsible for devices not collected within 30 days.
                </div>

                <div class="double-divider"></div>
                <div class="text-center bold" style="font-size:11px;">THANK YOU FOR CHOOSING</div>
                <div class="text-center bold" style="font-size:12px; letter-spacing:1px;">SAFDAR MOBILE STORE</div>
            </body>
            </html>
        `);

        printWin.document.close();
        printWin.focus();
        setTimeout(function () {
            printWin.print();
        }, 350);
    };

    // Quick Service Entry Modal Functions
    window.openPOSServiceModal = function (type, prefillService = null) {
        const modal = document.getElementById('posServiceEntryModal');
        const form = document.getElementById('posServiceEntryForm');
        const titleEl = document.getElementById('posServiceModalTitle');
        const repairRow = document.getElementById('posServiceSelectRepairRow');
        if (!modal) return;

        form.reset();
        document.getElementById('posServiceType').value = type || 'custom';
        document.getElementById('posServiceShopFee').value = '0';
        document.getElementById('posServiceBaseAmount').value = '';
        if (repairRow) repairRow.style.display = (type === 'repair' || type === 'repair_claim') ? 'block' : 'none';

        if (prefillService) {
            document.getElementById('posServiceName').value = prefillService.name;
            if (prefillService.defaultFee) {
                document.getElementById('posServiceShopFee').value = prefillService.defaultFee;
            }
            if (titleEl) titleEl.innerHTML = `<i class="fa-solid ${prefillService.icon}" style="color:${prefillService.color};"></i> ${prefillService.name}`;
        } else {
            if (type === 'repair') {
                if (titleEl) titleEl.innerHTML = '<i class="fa-solid fa-screwdriver-wrench" style="color:var(--pos-red);"></i> Mobile Repair Payment / Fee';
                document.getElementById('posServiceName').value = 'Mobile Repair Labor / Delivery';
            } else if (type === 'bill') {
                if (titleEl) titleEl.innerHTML = '<i class="fa-solid fa-file-invoice-dollar" style="color:#2563eb;"></i> Utility Bill Payment';
                document.getElementById('posServiceName').value = 'PESCO Electricity Bill';
                document.getElementById('posServiceShopFee').value = '30';
            } else if (type === 'package') {
                if (titleEl) titleEl.innerHTML = '<i class="fa-solid fa-mobile-screen-button" style="color:#d97706;"></i> Mobile Load / Bundle Activation';
                document.getElementById('posServiceName').value = 'Mobile Easyload / Bundle';
            } else if (type === 'transfer') {
                if (titleEl) titleEl.innerHTML = '<i class="fa-solid fa-wallet" style="color:#059669;"></i> Easypaisa / JazzCash / Transfer';
                document.getElementById('posServiceName').value = 'Easypaisa Cash-In Transfer';
                document.getElementById('posServiceShopFee').value = '50';
            } else if (type === 'nadra') {
                if (titleEl) titleEl.innerHTML = '<i class="fa-solid fa-id-card" style="color:#7c3aed;"></i> NADRA / Citizen Kiosk Service';
                document.getElementById('posServiceName').value = 'Smart CNIC Application Fee';
                document.getElementById('posServiceBaseAmount').value = '1500';
            } else {
                if (titleEl) titleEl.innerHTML = '<i class="fa-solid fa-plus-circle" style="color:var(--pos-gold-dark);"></i> Add Custom Store Service';
                document.getElementById('posServiceName').value = 'General Service Charge';
            }
        }

        calcPOSServiceTotal();
        modal.style.display = 'flex';
    };

    window.closePOSServiceModal = function () {
        const modal = document.getElementById('posServiceEntryModal');
        if (modal) modal.style.display = 'none';
    };

    window.onPOSRepairSelectChange = function () {
        const sel = document.getElementById('posServiceRepairSelect');
        if (!sel || !sel.value) return;

        const opt = sel.options[sel.selectedIndex];
        const ticketId = sel.value;
        const due = parseFloat(opt.getAttribute('data-due')) || 0;
        const model = opt.getAttribute('data-model') || '';
        const name = opt.getAttribute('data-name') || '';
        const phone = opt.getAttribute('data-phone') || '';

        document.getElementById('posServiceName').value = `Repair Claim: ${ticketId} (${model})`;
        document.getElementById('posServiceCustomerPhone').value = phone;
        document.getElementById('posServiceRefNumber').value = ticketId;
        document.getElementById('posServiceBaseAmount').value = due;
        document.getElementById('posServiceShopFee').value = '0';

        const custNameInput = document.getElementById('posCustomerNameInput');
        const custPhoneInput = document.getElementById('posCustomerPhoneInput');
        if (custNameInput && name) custNameInput.value = name;
        if (custPhoneInput && phone) custPhoneInput.value = phone;

        calcPOSServiceTotal();
    };

    window.calcPOSServiceTotal = function () {
        const base = parseFloat(document.getElementById('posServiceBaseAmount').value) || 0;
        const fee = parseFloat(document.getElementById('posServiceShopFee').value) || 0;
        const total = base + fee;
        const disp = document.getElementById('posServiceTotalDisplay');
        if (disp) disp.textContent = 'PKR ' + total.toLocaleString();
    };

    window.submitPOSServiceToCart = function (e) {
        e.preventDefault();
        const name = document.getElementById('posServiceName').value.trim();
        const base = parseFloat(document.getElementById('posServiceBaseAmount').value) || 0;
        const fee = parseFloat(document.getElementById('posServiceShopFee').value) || 0;
        const phone = document.getElementById('posServiceCustomerPhone').value.trim();
        const ref = document.getElementById('posServiceRefNumber').value.trim();
        const type = document.getElementById('posServiceType').value;

        if (!name || base <= 0) {
            alert('Please provide a valid service name and amount greater than 0.');
            return;
        }

        const totalSelling = base + fee;
        const totalCost = base; // Shop keeps the fee as profit

        let displayName = name;
        if (ref && !name.includes(ref)) displayName += ` (Ref: ${ref})`;
        if (phone && !name.includes(phone)) displayName += ` [${phone}]`;

        const serviceCartItem = {
            id: 'srv-' + Date.now() + '-' + Math.floor(Math.random() * 1000),
            name: displayName,
            sellingPrice: totalSelling,
            costPrice: totalCost,
            stock: 9999,
            qty: 1,
            isService: true,
            serviceType: type,
            serviceRef: ref
        };

        window.POSState.cart.push(serviceCartItem);
        window.closePOSServiceModal();
        renderCartItems();
    };

    window.addServiceToPOSCart = function (service) {
        const cartId = 'srv-' + service.id + '-' + Date.now();
        window.POSState.cart.push({
            id: cartId,
            serviceId: service.id,
            name: service.name,
            sellingPrice: service.sellingPrice,
            costPrice: service.costPrice,
            stock: 9999,
            qty: 1,
            isService: true,
            serviceType: service.serviceType,
            serviceRef: ''
        });

        renderCartItems();
    };

    // Add to Cart for Products
    window.addToPOSCart = function (productId) {
        const prod = window.POSState.products.find(function (p) { return p.id === productId; });
        if (!prod) return;

        if (prod.stock <= 0) {
            alert('Product is out of stock!');
            return;
        }

        const cartItem = window.POSState.cart.find(function (item) { return item.id === productId; });
        if (cartItem) {
            if (cartItem.qty >= prod.stock) {
                alert('Cannot exceed available stock level (' + prod.stock + ')');
                return;
            }
            cartItem.qty += 1;
        } else {
            window.POSState.cart.push({
                id: prod.id,
                name: prod.name,
                sellingPrice: floatVal(prod.sellingPrice || prod.priceNumeric),
                costPrice: floatVal(prod.costPrice),
                stock: prod.stock,
                unit: prod.unit || 'pcs',
                unitLabel: prod.unitLabel || 'Piece',
                brand: prod.brand || '',
                sku: prod.sku || '',
                barcode: prod.barcode || '',
                qty: 1,
                isService: false
            });
        }

        renderCartItems();
    };

    window.updateCartItemQty = function (productId, delta) {
        const cartItem = window.POSState.cart.find(function (item) { return item.id === productId; });
        if (!cartItem) return;

        const newQty = cartItem.qty + delta;
        if (newQty <= 0) {
            window.removeFromPOSCart(productId);
            return;
        }

        if (!cartItem.isService && newQty > cartItem.stock) {
            alert('Cannot exceed available stock level (' + cartItem.stock + ')');
            return;
        }

        cartItem.qty = newQty;
        renderCartItems();
    };

    window.removeFromPOSCart = function (productId) {
        window.POSState.cart = window.POSState.cart.filter(function (item) { return item.id !== productId; });
        renderCartItems();
    };

    window.clearPOSCart = function () {
        window.POSState.cart = [];
        window.POSState.discount = 0;
        window.POSState.amountReceived = 0;
        const discInput = document.getElementById('posDiscountInput');
        const recInput = document.getElementById('posAmountReceivedInput');
        const trxInput = document.getElementById('posTrxIdInput');
        const senderInput = document.getElementById('posSenderPhoneInput');
        if (discInput) discInput.value = '';
        if (recInput) recInput.value = '';
        if (trxInput) trxInput.value = '';
        if (senderInput) senderInput.value = '';
        renderCartItems();
    };

    function renderCartItems() {
        const container = document.getElementById('posCartItemsContainer');
        const countBadge = document.getElementById('posCartItemCountBadge');
        if (!container) return;

        if (window.POSState.cart.length === 0) {
            container.innerHTML = '<div style="text-align:center; padding:35px 10px; color:#9ca3af;"><i class="fa-solid fa-cart-shopping" style="font-size:2rem; margin-bottom:8px; opacity:0.5;"></i><p style="font-weight:600; font-size:0.88rem;">No items in cart</p><span style="font-size:0.75rem;">Click product tiles or scan barcode to add</span></div>';
            if (countBadge) countBadge.textContent = '0 Items';
            updateCartTotals();
            return;
        }

        const totalQty = window.POSState.cart.reduce(function (sum, it) { return sum + it.qty; }, 0);
        if (countBadge) {
            countBadge.textContent = totalQty + ' Qty (' + window.POSState.cart.length + ' Items)';
        }

        container.innerHTML = window.POSState.cart.map(function (item) {
            const lineTotal = item.sellingPrice * item.qty;
            const uLabel = (item.unit && item.unit !== 'pcs' && item.unit !== 'piece') ? (' / ' + (item.unitLabel || item.unit)) : ' / item';
            return `
                <div class="cart-item">
                    <div class="cart-item-info">
                        <div class="cart-item-name" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</div>
                        <div class="cart-item-price">PKR ${item.sellingPrice.toLocaleString()} <span style="color:#059669; font-size:0.7rem; font-weight:700;">${escapeHtml(uLabel)}</span></div>
                    </div>
                    <div class="cart-qty-ctrl">
                        <button type="button" class="cart-qty-btn" onclick="window.updateCartItemQty('${item.id}', -1)" title="Decrease Qty">-</button>
                        <span class="cart-qty-num">${item.qty}</span>
                        <button type="button" class="cart-qty-btn" onclick="window.updateCartItemQty('${item.id}', 1)" title="Increase Qty">+</button>
                    </div>
                    <div class="cart-item-total">PKR ${lineTotal.toLocaleString()}</div>
                    <div class="cart-item-remove" onclick="window.removeFromPOSCart('${item.id}')" title="Remove Item"><i class="fa-solid fa-xmark"></i></div>
                </div>
            `;
        }).join('');

        updateCartTotals();
    }

    function updateCartTotals() {
        const subtotal = window.POSState.cart.reduce(function (acc, item) {
            return acc + (item.sellingPrice * item.qty);
        }, 0);

        const disc = window.POSState.discount;
        const discountAmt = disc > 0 ? (disc <= 100 ? (subtotal * disc / 100) : disc) : 0;
        const total = Math.max(0, subtotal - discountAmt);
        const change = window.POSState.amountReceived ? Math.max(0, window.POSState.amountReceived - total) : 0;

        document.getElementById('posSubtotalDisplay').textContent = 'PKR ' + subtotal.toLocaleString();
        document.getElementById('posDiscountDisplay').textContent = '-PKR ' + discountAmt.toLocaleString();
        document.getElementById('posTotalDisplay').textContent = 'PKR ' + total.toLocaleString();
        document.getElementById('posChangeDisplay').textContent = 'PKR ' + change.toLocaleString();

        const btn = document.getElementById('completeSaleBtn');
        if (btn) {
            btn.disabled = window.POSState.cart.length === 0;
        }
    }

    function handleBarcodeScan(barcode) {
        const prod = window.POSState.products.find(function (p) {
            return (p.barcode || '').trim() === barcode.trim() || (p.sku || '').trim() === barcode.trim();
        });

        if (prod) {
            window.addToPOSCart(prod.id);
        } else {
            console.log('Barcode not found: ' + barcode);
        }
    }

    // Process POS Checkout
    function processPOSCheckout() {
        if (window.POSState.cart.length === 0) return;

        const method = window.POSState.paymentMethod || 'cash';
        let trxId = '';
        let senderPhone = '';

        if (method !== 'cash') {
            const trxInput = document.getElementById('posTrxIdInput');
            const phoneInput = document.getElementById('posSenderPhoneInput');
            const verifiedCheck = document.getElementById('posPaymentVerifiedCheck');

            trxId = trxInput ? trxInput.value.trim() : '';
            senderPhone = phoneInput ? phoneInput.value.trim() : '';

            if (!trxId) {
                const proceed = confirm('You selected ' + method.toUpperCase() + ' without entering a Transaction ID (TRX/TID).\n\nHave you verified the payment in your SMS or App? Click OK to proceed or Cancel to enter TRX ID.');
                if (!proceed) {
                    if (trxInput) trxInput.focus();
                    return;
                }
            }

            if (verifiedCheck && !verifiedCheck.checked) {
                alert('Please check the verification confirmation box indicating you have confirmed receipt of funds.');
                return;
            }
        }

        const customerName = document.getElementById('posCustomerNameInput')?.value || 'Walk-in Customer';
        const customerPhone = document.getElementById('posCustomerPhoneInput')?.value || '';

        const payload = {
            items: window.POSState.cart,
            discount: window.POSState.discount,
            paymentMethod: method,
            trxId: trxId,
            senderPhone: senderPhone,
            paymentConfirmed: true,
            customerName: customerName,
            customerPhone: customerPhone,
            amountReceived: window.POSState.amountReceived
        };

        const btn = document.getElementById('completeSaleBtn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing Sale...';
        }

        fetch('../backend/sales.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-check-circle"></i> COMPLETE SALE & PRINT RECEIPT';
                }
                if (res && res.status === 'success') {
                    window.POSState.completedSale = res.data;
                    openReceiptModal(res.data);
                    window.clearPOSCart();
                    loadPOSProducts();
                } else {
                    alert(res.message || 'Transaction failed');
                }
            })
            .catch(function (err) {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-check-circle"></i> COMPLETE SALE & PRINT RECEIPT';
                }
                alert('Error completing sale: ' + err.message);
            });
    }

    // Thermal Receipt Modal & Printing (Optimized for 80mm/58mm Thermal Printers)
    function openReceiptModal(sale) {
        const modal = document.getElementById('receiptModal');
        if (!modal) return;

        const itemsHtml = sale.items.map(function (item) {
            const hasCustomUnit = item.unit && item.unit !== 'pcs' && item.unit !== 'piece';
            const unitQtyText = hasCustomUnit ? `${item.qty} ${escapeHtml(item.unitLabel || item.unit)}` : `${item.qty}x`;
            return `
                <tr>
                    <td style="padding:2px 0; word-break:break-word;">${unitQtyText} ${escapeHtml(item.name)}</td>
                    <td style="text-align:right; padding:2px 0; white-space:nowrap;">PKR ${item.lineTotal.toLocaleString()}</td>
                </tr>
            `;
        }).join('');

        const isDigital = sale.paymentMethod && sale.paymentMethod.toLowerCase() !== 'cash';
        const methodDisplay = (sale.paymentMethod || 'CASH').toUpperCase() + (isDigital ? ' (VERIFIED)' : '');

        const content = `
            <div class="receipt-preview" style="font-family:'Courier New', Courier, monospace; width:72mm; max-width:72mm; margin:0 auto; padding:6px 2px; color:#000; font-size:11px; line-height:1.35;">
                <div class="receipt-header" style="text-align:center; margin-bottom:6px;">
                    <div style="font-weight:bold; font-size:15px; letter-spacing:0.5px;">SAFDAR MOBILE STORE</div>
                    <div style="font-size:10px;">Opp. Patt Bazar, Eidgah Road, Hangu</div>
                    <div style="font-size:11px; font-weight:bold;">WhatsApp / Call: 03339688007</div>
                </div>

                <div style="border-top:1px dashed #000; margin:5px 0;"></div>
                <div style="text-align:center; font-weight:bold; font-size:12px; letter-spacing:1px;">*** SALES RECEIPT ***</div>
                <div style="text-align:center; font-size:13px; letter-spacing:3px; font-weight:bold; margin:3px 0;">*${sale.invoiceNo}*</div>
                <div style="border-top:1px dashed #000; margin:5px 0;"></div>

                <table style="width:100%; border-collapse:collapse; font-size:11px; table-layout:fixed;">
                    <tr>
                        <td style="width:38%; font-weight:bold;">Invoice #:</td>
                        <td style="width:62%; text-align:right; font-weight:bold; word-break:break-all;">${sale.invoiceNo}</td>
                    </tr>
                    <tr>
                        <td>Date:</td>
                        <td style="text-align:right;">${new Date(sale.createdAt).toLocaleString()}</td>
                    </tr>
                    <tr>
                        <td>Customer:</td>
                        <td style="text-align:right; font-weight:bold;">${escapeHtml(sale.customerName || 'Walk-in Customer')}</td>
                    </tr>
                    <tr>
                        <td style="font-weight:bold;">Payment:</td>
                        <td style="text-align:right; font-weight:bold;">${methodDisplay}</td>
                    </tr>
                    ${sale.trxId ? `
                    <tr>
                        <td style="font-weight:bold;">TRX ID:</td>
                        <td style="text-align:right; font-weight:bold; font-family:monospace;">${escapeHtml(sale.trxId)}</td>
                    </tr>` : ''}
                    ${sale.senderPhone ? `
                    <tr>
                        <td>Sender No:</td>
                        <td style="text-align:right;">${escapeHtml(sale.senderPhone)}</td>
                    </tr>` : ''}
                </table>

                <div style="border-top:1px dashed #000; margin:5px 0;"></div>

                <table class="receipt-items" style="width:100%; font-size:11px; border-collapse:collapse; table-layout:fixed;">
                    <thead>
                        <tr style="border-bottom:1px solid #000; font-weight:bold;">
                            <th style="text-align:left; width:65%; padding-bottom:2px;">ITEM</th>
                            <th style="text-align:right; width:35%; padding-bottom:2px;">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemsHtml}
                    </tbody>
                </table>

                <div style="border-top:1px dashed #000; margin:5px 0;"></div>

                <div style="border:1.5px solid #000; padding:5px 6px; margin:6px 0; background:#fff;">
                    <div style="display:flex; justify-content:space-between; font-size:11px;">
                        <span>Subtotal:</span>
                        <span>PKR ${sale.subtotal.toLocaleString()}</span>
                    </div>
                    ${sale.discount > 0 ? `
                    <div style="display:flex; justify-content:space-between; font-size:11px; color:#000;">
                        <span>Discount:</span>
                        <span>-PKR ${sale.discount.toLocaleString()}</span>
                    </div>` : ''}
                    <div style="border-top:1px dashed #000; margin:3px 0;"></div>
                    <div style="display:flex; justify-content:space-between; font-size:14px; font-weight:900;">
                        <span>TOTAL PAID:</span>
                        <span>PKR ${sale.total.toLocaleString()}</span>
                    </div>
                    ${sale.amountReceived > 0 ? `
                    <div style="display:flex; justify-content:space-between; font-size:11px; margin-top:2px;">
                        <span>Received:</span>
                        <span>PKR ${sale.amountReceived.toLocaleString()}</span>
                    </div>` : ''}
                    ${sale.change > 0 ? `
                    <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;">
                        <span>Change Due:</span>
                        <span>PKR ${sale.change.toLocaleString()}</span>
                    </div>` : ''}
                </div>

                <div style="border-top:1px dashed #000; margin:5px 0;"></div>

                <div style="text-align:center; font-size:9.5px; line-height:1.25; margin-top:4px;">
                    * Goods once sold can be exchanged within 3 days.<br>
                    * Warranty claims require this original thermal receipt.<br>
                    * No warranty on burnt or physically damaged items.
                </div>

                <div style="border-top:2px solid #000; margin:6px 0;"></div>
                <div style="text-align:center; font-weight:bold; font-size:11px;">
                    THANK YOU FOR SHOPPING!
                </div>
                <div style="text-align:center; font-size:9px; color:#555; margin-top:2px;">
                    Software by Munim Abbas
                </div>
            </div>
        `;

        document.getElementById('receiptModalContainer').innerHTML = content;
        modal.style.display = 'flex';
    }

    window.closeReceiptModal = function () {
        const modal = document.getElementById('receiptModal');
        if (modal) modal.style.display = 'none';
    };

    window.printReceipt = function () {
        const container = document.getElementById('receiptModalContainer');
        if (!container) {
            window.print();
            return;
        }

        const printFrame = document.createElement('iframe');
        printFrame.style.position = 'fixed';
        printFrame.style.right = '0';
        printFrame.style.bottom = '0';
        printFrame.style.width = '0';
        printFrame.style.height = '0';
        printFrame.style.border = '0';
        document.body.appendChild(printFrame);

        const doc = printFrame.contentWindow.document;
        doc.open();
        doc.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <title>Receipt Print</title>
                <style>
                    @page { size: 80mm auto; margin: 0mm; }
                    * { box-sizing: border-box; margin: 0; padding: 0; }
                    html, body {
                        width: 72mm !important;
                        max-width: 72mm !important;
                        margin: 0 auto !important;
                        padding: 4px 2px !important;
                        background: #ffffff !important;
                        color: #000000 !important;
                        font-family: 'Courier New', Courier, monospace, sans-serif !important;
                    }
                    .receipt-preview {
                        width: 72mm !important;
                        max-width: 72mm !important;
                        padding: 0 !important;
                        margin: 0 auto !important;
                    }
                    @media print {
                        html, body {
                            width: 72mm !important;
                            max-width: 72mm !important;
                            margin: 0 auto !important;
                            padding: 2px 1px !important;
                        }
                    }
                </style>
            </head>
            <body>
                ${container.innerHTML}
            </body>
            </html>
        `);
        doc.close();

        setTimeout(function () {
            printFrame.contentWindow.focus();
            printFrame.contentWindow.print();
            setTimeout(function () {
                if (document.body.contains(printFrame)) {
                    document.body.removeChild(printFrame);
                }
            }, 1000);
        }, 250);
    };

    // Global Customer Bill Modal & Printable Invoice Engine
    window.openCustomerBillModal = function (sale) {
        if (!sale) return;

        let modal = document.getElementById('customerBillModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'customerBillModal';
            modal.className = 'pos-modal-overlay';
            modal.style.zIndex = '10000';
            modal.style.display = 'none';
            modal.innerHTML = `
                <div class="pos-modal" style="max-width:850px; width:95%; max-height:90vh; overflow-y:auto; background:#fff; padding:24px;">
                    <div class="pos-modal-header no-print" style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:12px; margin-bottom:16px;">
                        <h3 class="pos-modal-title" style="margin:0; font-family:var(--pos-font-heading); color:var(--pos-red);">
                            <i class="fa-solid fa-file-invoice-dollar"></i> Official Customer Bill & Invoice Preview
                        </h3>
                        <button class="pos-modal-close" onclick="window.closeCustomerBillModal()" style="background:none; border:none; font-size:1.2rem; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <div id="customerBillModalContent"></div>
                    <div class="form-actions no-print" style="display:flex; justify-content:flex-end; gap:12px; margin-top:20px; border-top:1px solid #e2e8f0; padding-top:16px;">
                        <button class="pos-btn pos-btn-outline" onclick="window.closeCustomerBillModal()">Close Window</button>
                        <button class="pos-btn pos-btn-primary" onclick="window.printCustomerBillModal()">
                            <i class="fa-solid fa-print"></i> Print Official Customer Bill
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }

        const itemsRows = (sale.items || []).map(function (item, idx) {
            const selling = floatVal(item.sellingPrice || item.price);
            const lineTotal = floatVal(item.lineTotal || (selling * item.qty));
            return `
                <tr style="border-bottom:1px solid #e2e8f0;">
                    <td style="padding:10px 12px; text-align:center; font-weight:700; color:#64748b;">${idx + 1}</td>
                    <td style="padding:10px 12px; font-weight:700; color:#1e293b;">${escapeHtml(item.name)}</td>
                    <td style="padding:10px 12px; text-align:right; color:#475569;">PKR ${selling.toLocaleString()}</td>
                    <td style="padding:10px 12px; text-align:center; font-weight:800; color:#0f172a;">${item.qty}</td>
                    <td style="padding:10px 12px; text-align:right; font-weight:800; color:#1e293b;">PKR ${lineTotal.toLocaleString()}</td>
                </tr>
            `;
        }).join('');

        const subtotal = floatVal(sale.subtotal || sale.total);
        const discount = floatVal(sale.discount || 0);
        const total = floatVal(sale.total);
        const amountReceived = floatVal(sale.amountReceived || total);
        const change = floatVal(sale.change || Math.max(0, amountReceived - total));

        const logoUrl = '../assets/images/logo.jpg';

        const billHtml = `
            <div class="customer-bill-wrap" style="background:#ffffff; color:#0f172a; font-family:'Inter', system-ui, sans-serif; padding:10px;">
                <!-- Header with Official Logo -->
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:3.5px solid #D71920; padding-bottom:14px; margin-bottom:18px;">
                    <div style="display:flex; align-items:center; gap:16px;">
                        <img src="${logoUrl}" alt="Safdar Mobile Store Logo" style="width:78px; height:78px; border-radius:50%; border:2px solid #F4C430; box-shadow:0 4px 12px rgba(215,25,32,0.25); object-fit:cover;" onerror="this.style.display='none'">
                        <div>
                            <h1 style="font-family:'Outfit', sans-serif; font-size:1.65rem; font-weight:900; color:#D71920; margin:0; line-height:1.1; letter-spacing:-0.5px;">SAFDAR MOBILE STORE</h1>
                            <div style="font-size:0.82rem; font-weight:800; color:#334155; margin-top:3px;">Mobiles, Accessories, CCTV Security & Digital Kiosk Services</div>
                            <div style="font-size:0.76rem; color:#64748b; margin-top:2px;">Opposite Patt Bazar Eidgah Road near Purdil Masjid Syedano Banda Road Main Bazar Hangu</div>
                            <div style="font-size:0.76rem; font-weight:700; color:#0f172a; margin-top:2px;">Phone / WhatsApp: <strong style="color:#D71920;">03339688007</strong> | Email: info@safdarmobile.com</div>
                        </div>
                    </div>
                    <div style="text-align:right; background:#fff5f5; padding:12px 18px; border-radius:10px; border:1.5px solid #fecaca; min-width:170px;">
                        <div style="font-family:'Outfit', sans-serif; font-size:1.15rem; font-weight:900; color:#D71920; text-transform:uppercase; letter-spacing:0.5px;">CUSTOMER BILL</div>
                        <div style="font-size:0.85rem; font-weight:800; color:#0f172a; margin-top:3px;"># ${escapeHtml(sale.invoiceNo)}</div>
                        <div style="font-size:0.74rem; color:#64748b; margin-top:2px;">${new Date(sale.createdAt || Date.now()).toLocaleString()}</div>
                    </div>
                </div>

                <!-- Customer Details & Payment Banner -->
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; background:#f8fafc; padding:14px 18px; border-radius:10px; border:1px solid #e2e8f0; margin-bottom:20px; font-size:0.85rem;">
                    <div>
                        <div style="font-size:0.72rem; font-weight:800; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">CUSTOMER DETAILS</div>
                        <div style="font-size:1rem; font-weight:800; color:#0f172a;">${escapeHtml(sale.customerName || 'Walk-in Customer')}</div>
                        <div style="color:#475569; margin-top:2px;">Contact Phone: ${escapeHtml(sale.customerPhone || 'N/A')}</div>
                        <div style="color:#475569;">Location: Main Bazar Commercial Market, Hangu</div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:0.72rem; font-weight:800; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">BILLING INFORMATION</div>
                        <div>Payment Method: <strong style="color:#059669;">${(sale.paymentMethod || 'cash').toUpperCase()}</strong></div>
                        <div>Billed By: <strong>${escapeHtml(sale.cashier || 'admin')}</strong></div>
                        <div style="margin-top:4px;"><span style="background:#dcfce7; color:#15803d; padding:3px 10px; border-radius:12px; font-weight:800; font-size:0.72rem;">PAID IN FULL</span></div>
                    </div>
                </div>

                <!-- Itemized Products Table -->
                <table style="width:100%; border-collapse:collapse; margin-bottom:20px; font-size:0.88rem;">
                    <thead>
                        <tr style="background:#0f172a; color:#ffffff;">
                            <th style="padding:10px 12px; text-align:center; width:50px; border-radius:6px 0 0 0;">#</th>
                            <th style="padding:10px 12px; text-align:left;">Item / Product Description</th>
                            <th style="padding:10px 12px; text-align:right; width:130px;">Rate (PKR)</th>
                            <th style="padding:10px 12px; text-align:center; width:70px;">Qty</th>
                            <th style="padding:10px 12px; text-align:right; width:140px; border-radius:0 6px 0 0;">Total (PKR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemsRows}
                    </tbody>
                </table>

                <!-- Summary & Terms Footer -->
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:20px; margin-bottom:24px;">
                    <div style="flex:1; background:#fffdf5; border:1px solid #fde68a; padding:14px; border-radius:10px;">
                        <div style="font-size:0.8rem; font-weight:800; color:#92400e; margin-bottom:4px;">
                            <i class="fa-solid fa-circle-info"></i> Terms & Warranty Conditions:
                        </div>
                        <ul style="font-size:0.75rem; color:#78350f; margin:0; padding-left:16px; line-height:1.4;">
                            <li>Mobile smartphones & accessories carry official brand warranty as per manufacturer policy.</li>
                            <li>Please keep this original Customer Bill safe for any warranty or replacement requests.</li>
                            <li>Thank you for shopping at Safdar Mobile Store! Contact 03339688007 for any support.</li>
                        </ul>
                    </div>

                    <div style="width:280px; background:#f8fafc; border:1.5px solid #cbd5e1; border-radius:10px; padding:14px; font-size:0.88rem;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:6px; color:#475569;">
                            <span>Subtotal:</span>
                            <strong>PKR ${subtotal.toLocaleString()}</strong>
                        </div>
                        ${discount > 0 ? `
                        <div style="display:flex; justify-content:space-between; margin-bottom:6px; color:#dc2626;">
                            <span>Discount:</span>
                            <strong>-PKR ${discount.toLocaleString()}</strong>
                        </div>` : ''}
                        <div style="display:flex; justify-content:space-between; border-top:2px solid #0f172a; padding-top:8px; margin-top:8px; font-size:1.15rem; font-weight:900; color:#D71920;">
                            <span>GRAND TOTAL:</span>
                            <span>PKR ${total.toLocaleString()}</span>
                        </div>
                        ${amountReceived > 0 ? `
                        <div style="display:flex; justify-content:space-between; margin-top:6px; font-size:0.82rem; color:#475569;">
                            <span>Amount Received:</span>
                            <span>PKR ${amountReceived.toLocaleString()}</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-top:2px; font-size:0.82rem; color:#059669; font-weight:700;">
                            <span>Change Returned:</span>
                            <span>PKR ${change.toLocaleString()}</span>
                        </div>` : ''}
                    </div>
                </div>

                <!-- Signatures & Stamp Lines -->
                <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-top:35px; padding-top:16px; border-top:1px dashed #cbd5e1; font-size:0.78rem; color:#64748b;">
                    <div style="text-align:center; width:180px;">
                        <div style="height:30px;"></div>
                        <div style="border-top:1px solid #94a3b8; padding-top:4px; font-weight:700;">Customer Signature</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-weight:800; color:#D71920; font-size:0.85rem;">SAFDAR MOBILE STORE</div>
                        <div style="font-size:0.7rem; color:#94a3b8;">Software System by Munim Abbas</div>
                    </div>
                    <div style="text-align:center; width:180px;">
                        <div style="height:30px;"></div>
                        <div style="border-top:1px solid #94a3b8; padding-top:4px; font-weight:700;">Authorized Stamp & Seal</div>
                    </div>
                </div>
            </div>
        `;

        document.getElementById('customerBillModalContent').innerHTML = billHtml;
        modal.style.display = 'flex';
    };

    window.closeCustomerBillModal = function () {
        const modal = document.getElementById('customerBillModal');
        if (modal) modal.style.display = 'none';
    };

    window.printCustomerBillModal = function () {
        const content = document.getElementById('customerBillModalContent');
        if (!content) return;

        const printFrame = document.createElement('iframe');
        printFrame.style.position = 'fixed';
        printFrame.style.right = '0';
        printFrame.style.bottom = '0';
        printFrame.style.width = '0';
        printFrame.style.height = '0';
        printFrame.style.border = '0';
        document.body.appendChild(printFrame);

        const doc = printFrame.contentWindow.document;
        doc.open();
        doc.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Customer Bill - Safdar Mobile Store</title>
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
                <style>
                    @page { size: A4 portrait; margin: 10mm 12mm; }
                    html, body { background: #ffffff !important; color: #0f172a !important; font-family: 'Inter', system-ui, sans-serif !important; margin:0; padding:0; }
                    * { box-sizing: border-box; }
                </style>
            </head>
            <body>
                ${content.innerHTML}
            </body>
            </html>
        `);
        doc.close();

        setTimeout(function () {
            printFrame.contentWindow.focus();
            printFrame.contentWindow.print();
            setTimeout(function () {
                if (document.body.contains(printFrame)) {
                    document.body.removeChild(printFrame);
                }
            }, 1000);
        }, 250);
    };

    window.viewInvoice = function (saleId) {
        fetch('../backend/sales.php?id=' + saleId)
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res && res.status === 'success') {
                    window.openCustomerBillModal(res.data);
                } else {
                    alert('Sale details not found!');
                }
            })
            .catch(function(err) {
                alert('Error loading invoice: ' + err.message);
            });
    };

    // Topbar Notification Bell Toggle
    window.toggleNotifDropdown = function () {
        const menu = document.getElementById('notifDropdownMenu');
        if (!menu) return;
        menu.style.display = (menu.style.display === 'none' || !menu.style.display) ? 'block' : 'none';
    };

    document.addEventListener('click', function (e) {
        const wrapper = document.querySelector('.notif-wrapper');
        const menu = document.getElementById('notifDropdownMenu');
        if (wrapper && menu && !wrapper.contains(e.target)) {
            menu.style.display = 'none';
        }
    });

    // Real-Time Notification & Sound Engine
    let lastNotifTimestamp = null;
    let notifAudioContext = null;

    function playPOSChime() {
        try {
            if (!notifAudioContext) {
                notifAudioContext = new (window.AudioContext || window.webkitAudioContext)();
            }
            if (notifAudioContext.state === 'suspended') {
                notifAudioContext.resume();
            }
            const osc = notifAudioContext.createOscillator();
            const gain = notifAudioContext.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(587.33, notifAudioContext.currentTime); // D5
            osc.frequency.setValueAtTime(880, notifAudioContext.currentTime + 0.12); // A5
            gain.gain.setValueAtTime(0.15, notifAudioContext.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.0001, notifAudioContext.currentTime + 0.45);
            osc.connect(gain);
            gain.connect(notifAudioContext.destination);
            osc.start();
            osc.stop(notifAudioContext.currentTime + 0.45);
        } catch (e) {
            console.warn('Audio chime failed:', e);
        }
    }

    function showPOSToastNotification(title, message, type) {
        let container = document.getElementById('posToastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'posToastContainer';
            container.style.cssText = 'position:fixed; top:20px; right:20px; z-index:99999; display:flex; flex-direction:column; gap:10px; max-width:380px; width:90%; pointer-events:none;';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        const bgColor = type === 'payment' ? '#065f46' : (type === 'sale' ? '#1e1b4b' : '#7f1d1d');
        const borderColor = type === 'payment' ? '#10b981' : (type === 'sale' ? '#6366f1' : '#f43f5e');

        toast.style.cssText = `background:${bgColor}; border:1px solid ${borderColor}; color:#ffffff; padding:14px 18px; border-radius:12px; box-shadow:0 20px 40px rgba(0,0,0,0.6); pointer-events:auto; font-family:var(--pos-font-main); transition:all 0.3s ease; transform:translateY(-20px); opacity:0;`;
        toast.innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px;">
                <div style="flex:1;">
                    <div style="font-weight:800; font-size:0.92rem; margin-bottom:4px; display:flex; align-items:center; gap:6px;">
                        ${escapeHtml(title)}
                    </div>
                    <div style="font-size:0.8rem; color:#e2e8f0; line-height:1.3;">
                        ${escapeHtml(message)}
                    </div>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" style="background:none; border:none; color:#94a3b8; cursor:pointer; font-size:1rem; padding:0; line-height:1;">&times;</button>
            </div>
        `;

        container.appendChild(toast);
        setTimeout(function () {
            toast.style.transform = 'translateY(0)';
            toast.style.opacity = '1';
        }, 50);

        playPOSChime();

        setTimeout(function () {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-10px)';
            setTimeout(function () { toast.remove(); }, 350);
        }, 6000);
    }

    function checkRealtimeNotifications() {
        let url = '../backend/notifications.php';
        if (lastNotifTimestamp) {
            url += '?since=' + encodeURIComponent(lastNotifTimestamp);
        }

        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res && res.status === 'success') {
                    const data = res.data;

                    if (data.serverTime) {
                        if (!lastNotifTimestamp) {
                            lastNotifTimestamp = data.serverTime;
                        }
                    }

                    // Update Topbar badge count
                    const badge = document.getElementById('topbarNotifBadge');
                    const bellBtn = document.getElementById('notifBellBtn');
                    const totalAlerts = (data.unreadCount || 0) + (data.lowStockCount || 0);

                    if (badge) {
                        if (totalAlerts > 0) {
                            badge.innerText = totalAlerts;
                            badge.style.display = 'inline-block';
                        } else {
                            badge.style.display = 'none';
                        }
                    } else if (bellBtn && totalAlerts > 0) {
                        const newBadge = document.createElement('span');
                        newBadge.id = 'topbarNotifBadge';
                        newBadge.style.cssText = 'position:absolute; top:-5px; right:-5px; background:#ef4444; color:#fff; font-size:0.68rem; font-weight:800; padding:2px 6px; border-radius:10px; border:2px solid #1e293b;';
                        newBadge.innerText = totalAlerts;
                        bellBtn.appendChild(newBadge);
                    }

                    // Popup toasts for new unread notifications
                    if (data.notifications && data.notifications.length > 0) {
                        data.notifications.forEach(function (n) {
                            if (lastNotifTimestamp && new Date(n.timestamp) > new Date(lastNotifTimestamp)) {
                                showPOSToastNotification(n.title, n.message, n.type);
                            }
                        });
                        lastNotifTimestamp = data.serverTime;
                    }
                }
            })
            .catch(function (e) {
                console.warn('Failed notification check:', e);
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        checkRealtimeNotifications();
        setInterval(checkRealtimeNotifications, 5000);
    });

    // Utilities
    function floatVal(val) {
        return parseFloat(val) || 0;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // Topbar Scroll Listener
    document.addEventListener('DOMContentLoaded', function () {
        const topbar = document.querySelector('.pos-topbar');
        if (topbar) {
            window.addEventListener('scroll', function() {
                if (window.scrollY > 15) {
                    topbar.classList.add('is-scrolled');
                } else {
                    topbar.classList.remove('is-scrolled');
                }
            }, { passive: true });
        }
    });

    // Global Mobile Sidebar Navigation Controller
    window.toggleMobileSidebar = function () {
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (!sidebar) return;

        const isOpen = sidebar.classList.contains('mobile-open');
        if (isOpen) {
            window.closeMobileSidebar();
        } else {
            sidebar.classList.add('mobile-open');
            if (overlay) overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeMobileSidebar = function () {
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar) sidebar.classList.remove('mobile-open');
        if (overlay) overlay.classList.remove('active');
        document.body.style.overflow = '';
    };

    // Close sidebar on window resize to laptop/desktop
    window.addEventListener('resize', function () {
        if (window.innerWidth > 768) {
            window.closeMobileSidebar();
        }
    });

    // Auto close mobile sidebar when clicking any sidebar link & maintain active scroll position
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.sidebar-link').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 768) {
                    window.closeMobileSidebar();
                }
            });
        });

        // Ensure active sidebar link stays visible in the sidebar without jumping to the top
        const activeLink = document.querySelector('.pos-sidebar .sidebar-link.active');
        if (activeLink) {
            activeLink.scrollIntoView({ block: 'nearest', behavior: 'instant' });
        }
    });

    // =========================================================================
    // GLOBAL REAL-TIME AUTOCOMPLETE SEARCH ENGINE (Customers, Products, Invoices, Repairs, CCTV)
    // =========================================================================
    let globalSearchDebounce = null;

    function getAdminBackendApiUrl(endpoint) {
        // Universal relative path resolution for all pages inside /admin/
        if (window.location.pathname.toLowerCase().includes('/admin')) {
            return '../backend/' + endpoint;
        }
        const loc = window.location.pathname;
        const adminIdx = loc.toLowerCase().indexOf('/admin');
        const base = adminIdx !== -1 ? loc.substring(0, adminIdx) : '';
        return (base ? base : '') + '/backend/' + endpoint;
    }

    let activeGlobalSearchData = null;
    let activeGlobalSearchTab = 'all';

    window.handleGlobalSearchInput = function (query) {
        const popup = document.getElementById('topbarSearchResults');
        const content = document.getElementById('topbarSearchResultsContent');
        const searchInput = document.getElementById('topbarSearchInput');
        if (!popup || !content) return;

        clearTimeout(globalSearchDebounce);
        const q = (query !== undefined && query !== null ? query : (searchInput ? searchInput.value : '')).trim();

        if (q.length < 1) {
            popup.style.display = 'none';
            content.innerHTML = '';
            activeGlobalSearchData = null;
            if (searchInput) searchInput.focus();
            return;
        }

        popup.style.display = 'block';
        content.innerHTML = `
            <div style="text-align:center; padding:18px 12px; color:#94a3b8; font-size:0.85rem;">
                <i class="fa-solid fa-spinner fa-spin" style="font-size:1.4rem; color:var(--pos-gold); margin-bottom:8px; display:block;"></i>
                Searching all store menus (Invoices, Customers, Debts, Repairs, CCTV, Bills, Easypaisa, NADRA, Parts, Expenses)...
            </div>
        `;

        globalSearchDebounce = setTimeout(function () {
            const url = getAdminBackendApiUrl('search.php') + `?q=${encodeURIComponent(q)}`;
            fetch(url, { 
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
                .then(r => {
                    if (!r.ok && r.status === 401) {
                        throw new Error('UNAUTHORIZED');
                    }
                    return r.json();
                })
                .then(res => {
                    if (res && res.status === 'success' && res.data) {
                        activeGlobalSearchData = res.data;
                        renderGlobalSearchResults(res.data, q);
                    } else if (res && res.message && res.message.toLowerCase().includes('unauthorized')) {
                        content.innerHTML = `
                            <div style="text-align:center; padding:20px 12px; color:#f87171; font-size:0.85rem;">
                                <i class="fa-solid fa-lock" style="font-size:1.6rem; margin-bottom:8px; display:block;"></i>
                                Session expired. Please refresh the page or log in again.
                            </div>
                        `;
                    } else {
                        content.innerHTML = `
                            <div style="text-align:center; padding:20px 12px; color:#94a3b8; font-size:0.85rem;">
                                <i class="fa-solid fa-magnifying-glass" style="font-size:1.6rem; opacity:0.4; margin-bottom:8px; display:block;"></i>
                                No record found matching "<strong>${escapeHtml(q)}</strong>"
                            </div>
                        `;
                    }
                })
                .catch(err => {
                    if (err && err.message === 'UNAUTHORIZED') {
                        content.innerHTML = `<div style="text-align:center; padding:15px; color:#f87171; font-size:0.82rem;"><i class="fa-solid fa-lock"></i> Session expired. Please refresh the page.</div>`;
                    } else {
                        content.innerHTML = `<div style="text-align:center; padding:15px; color:#f87171; font-size:0.82rem;"><i class="fa-solid fa-triangle-exclamation"></i> Error searching store database.</div>`;
                    }
                });
        }, 160);
    };

    window.filterGlobalSearchTab = function(tabName) {
        activeGlobalSearchTab = tabName;
        const qInput = document.getElementById('topbarSearchInput');
        const q = qInput ? qInput.value.trim() : '';
        if (activeGlobalSearchData) {
            renderGlobalSearchResults(activeGlobalSearchData, q);
        }
    };

    function renderGlobalSearchResults(data, q) {
        const popup = document.getElementById('topbarSearchResults');
        const content = document.getElementById('topbarSearchResultsContent');
        if (!popup || !content) return;

        const sales = data.sales || [];
        const customers = data.customers || [];
        const debts = data.debts || [];
        const repairs = data.repairs || [];
        const cctv = data.cctv || [];
        const repairParts = data.repairParts || [];
        const services = data.services || [];
        const bills = data.bills || [];
        const packages = data.packages || [];
        const nadra = data.nadra || [];
        const expenses = data.expenses || [];
        const products = data.products || [];
        const suppliers = data.suppliers || [];
        const reports = data.reports || [];

        const total = sales.length + customers.length + debts.length + repairs.length + cctv.length + 
                      repairParts.length + services.length + bills.length + packages.length + 
                      nadra.length + expenses.length + products.length + suppliers.length + reports.length;

        if (total === 0) {
            content.innerHTML = `
                <div style="text-align:center; padding:24px 12px; color:#94a3b8; font-size:0.88rem;">
                    <i class="fa-solid fa-magnifying-glass-chart" style="font-size:2.2rem; opacity:0.4; margin-bottom:10px; display:block; color:#f87171;"></i>
                    No matches found across all store menus for "<strong>${escapeHtml(q)}</strong>"
                    <div style="margin-top:14px; display:flex; gap:8px; justify-content:center; flex-wrap:wrap;">
                        <a href="pos.php?cust_name=${encodeURIComponent(q)}" class="pos-btn pos-btn-sm" style="background:#dc2626; color:#fff; font-weight:800; text-decoration:none; padding:5px 12px; border-radius:6px; display:inline-flex; align-items:center; gap:5px;">
                            <i class="fa-solid fa-cart-plus"></i> Open POS for "${escapeHtml(q)}"
                        </a>
                        <a href="services.php?search=${encodeURIComponent(q)}" class="pos-btn pos-btn-sm" style="background:#059669; color:#fff; font-weight:800; text-decoration:none; padding:5px 12px; border-radius:6px; display:inline-flex; align-items:center; gap:5px;">
                            <i class="fa-solid fa-wallet"></i> Easypaisa / JazzCash
                        </a>
                    </div>
                </div>
            `;
            popup.style.display = 'block';
            return;
        }

        let html = '';

        // Category Filter Tabs Navigation
        const tabs = [
            { id: 'all', label: 'All Results', count: total, icon: 'fa-layer-group', color: '#f4c430' },
            { id: 'sales', label: 'Invoices', count: sales.length, icon: 'fa-receipt', color: '#f43f5e' },
            { id: 'customers', label: 'Customers', count: customers.length, icon: 'fa-users', color: '#38bdf8' },
            { id: 'debts', label: 'Debts', count: debts.length, icon: 'fa-money-check-dollar', color: '#f59e0b' },
            { id: 'repairs', label: 'Repairs Lab', count: repairs.length, icon: 'fa-screwdriver-wrench', color: '#fb923c' },
            { id: 'cctv', label: 'CCTV', count: cctv.length, icon: 'fa-video', color: '#c084fc' },
            { id: 'bills', label: 'Utility Bills', count: bills.length, icon: 'fa-file-invoice-dollar', color: '#34d399' },
            { id: 'services', label: 'Easypaisa/Jazz', count: services.length, icon: 'fa-wallet', color: '#10b981' },
            { id: 'nadra', label: 'NADRA Kiosk', count: nadra.length, icon: 'fa-id-card', color: '#818cf8' },
            { id: 'repairParts', label: 'Spare Parts', count: repairParts.length, icon: 'fa-microchip', color: '#2dd4bf' },
            { id: 'packages', label: 'Packages', count: packages.length, icon: 'fa-mobile-screen-button', color: '#06b6d4' },
            { id: 'expenses', label: 'Expenses', count: expenses.length, icon: 'fa-chart-pie', color: '#f472b6' },
            { id: 'products', label: 'Products', count: products.length, icon: 'fa-boxes-stacked', color: '#60a5fa' },
            { id: 'suppliers', label: 'Suppliers', count: suppliers.length, icon: 'fa-building', color: '#94a3b8' }
        ];

        html += `
            <div style="display:flex; gap:5px; overflow-x:auto; padding-bottom:8px; margin-bottom:10px; border-bottom:1px solid #334155; scrollbar-width:thin;">
        `;
        tabs.forEach(t => {
            if (t.id === 'all' || t.count > 0) {
                const isActive = activeGlobalSearchTab === t.id;
                html += `
                    <button type="button" onclick="window.filterGlobalSearchTab('${t.id}')" style="white-space:nowrap; background:${isActive ? t.color : '#0f172a'}; color:${isActive ? '#000' : '#cbd5e1'}; border:1px solid ${isActive ? t.color : '#334155'}; font-size:0.72rem; font-weight:800; padding:3px 8px; border-radius:14px; cursor:pointer; display:flex; align-items:center; gap:4px;">
                        <i class="fa-solid ${t.icon}" style="font-size:0.7rem; color:${isActive ? '#000' : t.color};"></i>
                        <span>${t.label}</span>
                        <span style="background:${isActive ? 'rgba(0,0,0,0.2)' : 'rgba(255,255,255,0.1)'}; padding:0 5px; border-radius:8px; font-size:0.65rem;">${t.count}</span>
                    </button>
                `;
            }
        });
        html += `</div>`;

        // 1. SMART REPORT LINK
        if ((activeGlobalSearchTab === 'all' || activeGlobalSearchTab === 'reports') && reports.length > 0) {
            reports.forEach(rep => {
                html += `
                    <div style="background:linear-gradient(135deg, #064e3b, #0f172a); border:1px solid #059669; padding:10px 12px; border-radius:10px; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center; cursor:pointer;" onclick="window.location.href='${rep.url}'">
                        <div>
                            <div style="font-weight:900; color:#34d399; font-size:0.88rem; display:flex; align-items:center; gap:6px;">
                                <i class="fa-solid fa-chart-line"></i> ${escapeHtml(rep.title)}
                            </div>
                            <div style="font-size:0.72rem; color:#cbd5e1; margin-top:2px;">${escapeHtml(rep.description)}</div>
                        </div>
                        <a href="${rep.url}" style="background:#10b981; color:#000; font-weight:800; font-size:0.72rem; padding:4px 10px; border-radius:6px; text-decoration:none;">Open Reports &rarr;</a>
                    </div>
                `;
            });
        }

        // 2. SALES & INVOICES (MENU: Sales & Invoices)
        if ((activeGlobalSearchTab === 'all' || activeGlobalSearchTab === 'sales') && sales.length > 0) {
            html += `
                <div style="font-size:0.72rem; font-weight:900; color:#f43f5e; text-transform:uppercase; margin:8px 0 6px 0; letter-spacing:0.5px; border-bottom:1px solid #334155; padding-bottom:3px; display:flex; justify-content:space-between; align-items:center;">
                    <span><i class="fa-solid fa-receipt" style="color:#f43f5e;"></i> Sales &amp; Invoices (${sales.length})</span>
                    <a href="sales.php" style="font-size:0.68rem; color:#f43f5e; text-decoration:none; font-weight:700;">Sales Register &rarr;</a>
                </div>
            `;
            sales.slice(0, activeGlobalSearchTab === 'sales' ? 20 : 4).forEach(s => {
                const isRef = s.status === 'refunded';
                const invUrl = `sales.php?invoice=${encodeURIComponent(s.invoiceNo || s.id)}`;
                const pm = (s.paymentMethod || 'CASH').toUpperCase();
                let pmBadge = `<span style="background:rgba(59,130,246,0.2); color:#60a5fa; font-weight:800; font-size:0.65rem; padding:1px 6px; border-radius:4px;"><i class="fa-solid fa-money-bill-wave"></i> CASH</span>`;
                if (pm.includes('EASYPAISA')) {
                    pmBadge = `<span style="background:rgba(16,185,129,0.2); color:#34d399; font-weight:800; font-size:0.65rem; padding:1px 6px; border-radius:4px;"><i class="fa-solid fa-mobile-screen"></i> EASYPAISA</span>`;
                } else if (pm.includes('JAZZCASH')) {
                    pmBadge = `<span style="background:rgba(239,68,68,0.2); color:#f87171; font-weight:800; font-size:0.65rem; padding:1px 6px; border-radius:4px;"><i class="fa-solid fa-bolt"></i> JAZZCASH</span>`;
                } else if (pm.includes('CREDIT') || pm.includes('UDHAAR')) {
                    pmBadge = `<span style="background:rgba(245,158,11,0.2); color:#fbbf24; font-weight:800; font-size:0.65rem; padding:1px 6px; border-radius:4px;"><i class="fa-solid fa-scale-balanced"></i> CREDIT / UDHAAR</span>`;
                }

                html += `
                    <div style="display:flex; justify-content:space-between; align-items:center; background:#0f172a; border:1px solid #334155; padding:8px 10px; border-radius:8px; margin-bottom:6px; cursor:pointer; transition:background 0.15s ease;" onmouseover="this.style.background='#1e293b'" onmouseout="this.style.background='#0f172a'" onclick="window.location.href='${invUrl}'">
                        <div>
                            <div style="font-weight:800; color:var(--pos-red); font-family:monospace; font-size:0.85rem; display:flex; align-items:center; gap:6px;">
                                <span>${escapeHtml(s.invoiceNo)}</span>
                                ${pmBadge}
                                ${isRef ? '<span style="background:#fee2e2; color:#dc2626; font-size:0.62rem; padding:1px 5px; border-radius:3px; font-weight:800;">REFUNDED</span>' : ''}
                            </div>
                            <div style="font-size:0.72rem; color:#94a3b8; margin-top:2px;">
                                Customer: <strong style="color:#fff;">${escapeHtml(s.customerName)}</strong>
                                ${s.itemsSummary ? ` &bull; <span style="color:#cbd5e1;">${escapeHtml(s.itemsSummary)}</span>` : ''}
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:900; color:${isRef ? '#f87171' : '#fff'}; font-size:0.85rem; ${isRef ? 'text-decoration:line-through;' : ''}">
                                PKR ${Number(s.total || 0).toLocaleString()}
                            </div>
                            <a href="${invUrl}" onclick="event.stopPropagation();" style="font-size:0.68rem; color:var(--pos-gold); text-decoration:none; font-weight:700; background:rgba(217,119,6,0.15); padding:2px 8px; border-radius:4px; display:inline-block; margin-top:2px;">
                                <i class="fa-solid fa-receipt"></i> View Receipt &rarr;
                            </a>
                        </div>
                    </div>
                `;
            });
        }

        // 3. UTILITY BILLS PAYMENT (MENU: Utility Bills Payment)
        if ((activeGlobalSearchTab === 'all' || activeGlobalSearchTab === 'bills') && bills.length > 0) {
            html += `
                <div style="font-size:0.72rem; font-weight:900; color:#34d399; text-transform:uppercase; margin:10px 0 6px 0; letter-spacing:0.5px; border-bottom:1px solid #334155; padding-bottom:3px; display:flex; justify-content:space-between; align-items:center;">
                    <span><i class="fa-solid fa-file-invoice-dollar" style="color:#34d399;"></i> Utility Bills Payment (${bills.length})</span>
                    <a href="bills.php" style="font-size:0.68rem; color:#34d399; text-decoration:none; font-weight:700;">Bills Desk &rarr;</a>
                </div>
            `;
            bills.slice(0, activeGlobalSearchTab === 'bills' ? 20 : 4).forEach(b => {
                const billUrl = `bills.php?consumer=${encodeURIComponent(b.consumerNo)}&bill_id=${encodeURIComponent(b.id)}`;
                html += `
                    <div style="display:flex; justify-content:space-between; align-items:center; background:#0f172a; border:1px solid #334155; padding:8px 10px; border-radius:8px; margin-bottom:6px; cursor:pointer; transition:background 0.15s ease;" onmouseover="this.style.background='#1e293b'" onmouseout="this.style.background='#0f172a'" onclick="window.location.href='${billUrl}'">
                        <div>
                            <div style="font-weight:800; color:#34d399; font-family:monospace; font-size:0.85rem; display:flex; align-items:center; gap:6px;">
                                <span>${escapeHtml(b.consumerNo)}</span>
                                <span style="background:rgba(52,211,153,0.2); color:#34d399; font-weight:800; font-size:0.65rem; padding:1px 5px; border-radius:4px;">${escapeHtml(b.billType)}</span>
                                <span style="font-size:0.68rem; color:#94a3b8; font-family:sans-serif;">${escapeHtml(b.billingMonth)}</span>
                            </div>
                            <div style="font-size:0.72rem; color:#94a3b8; margin-top:2px;">
                                Consumer: <strong style="color:#fff;">${escapeHtml(b.customerName)}</strong> &bull; ${escapeHtml(b.companyName)}
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:900; color:#4ade80; font-size:0.85rem;">
                                PKR ${Number(b.totalCollected || b.billAmount || 0).toLocaleString()}
                            </div>
                            <a href="${billUrl}" onclick="event.stopPropagation();" style="font-size:0.68rem; color:#34d399; text-decoration:none; font-weight:700; background:rgba(52,211,153,0.15); padding:2px 8px; border-radius:4px; display:inline-block; margin-top:2px;">
                                <i class="fa-solid fa-stamp"></i> Print Stamped Slip &rarr;
                            </a>
                        </div>
                    </div>
                `;
            });
        }

        // 4. MOBILE REPAIRS LAB (MENU: Mobile Repairs Lab)
        if ((activeGlobalSearchTab === 'all' || activeGlobalSearchTab === 'repairs') && repairs.length > 0) {
            html += `
                <div style="font-size:0.72rem; font-weight:900; color:#fb923c; text-transform:uppercase; margin:10px 0 6px 0; letter-spacing:0.5px; border-bottom:1px solid #334155; padding-bottom:3px; display:flex; justify-content:space-between; align-items:center;">
                    <span><i class="fa-solid fa-screwdriver-wrench" style="color:#fb923c;"></i> Mobile Repairs Lab (${repairs.length})</span>
                    <a href="mobile-repairs.php" style="font-size:0.68rem; color:#fb923c; text-decoration:none; font-weight:700;">Repair Lab &rarr;</a>
                </div>
            `;
            repairs.slice(0, activeGlobalSearchTab === 'repairs' ? 20 : 4).forEach(r => {
                const repUrl = `mobile-repairs.php?ticket=${encodeURIComponent(r.ticketNo || r.id)}`;
                html += `
                    <div style="display:flex; justify-content:space-between; align-items:center; background:#0f172a; border:1px solid #334155; padding:8px 10px; border-radius:8px; margin-bottom:6px; cursor:pointer; transition:background 0.15s ease;" onmouseover="this.style.background='#1e293b'" onmouseout="this.style.background='#0f172a'" onclick="window.location.href='${repUrl}'">
                        <div>
                            <div style="font-weight:800; color:#fb923c; font-size:0.82rem; font-family:monospace; display:flex; align-items:center; gap:6px;">
                                <span>${escapeHtml(r.ticketNo)}</span>
                                <span style="background:#334155; color:#f8fafc; font-size:0.65rem; padding:1px 5px; border-radius:3px;">${escapeHtml(r.deviceBrand)} ${escapeHtml(r.deviceModel)}</span>
                                <span style="font-size:0.62rem; color:#cbd5e1; text-transform:uppercase;">[${escapeHtml(r.jobStatus)}]</span>
                            </div>
                            <div style="font-size:0.72rem; color:#94a3b8; margin-top:2px;">
                                Customer: <strong style="color:#fff;">${escapeHtml(r.customerName)}</strong> &bull; Fault: <span style="color:#f87171;">${escapeHtml(r.reportedFault)}</span>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:900; color:#4ade80; font-size:0.82rem;">PKR ${Number(r.totalBill || 0).toLocaleString()}</div>
                            <a href="${repUrl}" onclick="event.stopPropagation();" style="font-size:0.68rem; color:#fb923c; text-decoration:none; font-weight:700; background:rgba(251,146,60,0.15); padding:2px 8px; border-radius:4px; display:inline-block; margin-top:2px;">
                                <i class="fa-solid fa-ticket"></i> Print Slip &rarr;
                            </a>
                        </div>
                    </div>
                `;
            });
        }

        // 5. CCTV SURVEILLANCE (MENU: CCTV Surveillance)
        if ((activeGlobalSearchTab === 'all' || activeGlobalSearchTab === 'cctv') && cctv.length > 0) {
            html += `
                <div style="font-size:0.72rem; font-weight:900; color:#c084fc; text-transform:uppercase; margin:10px 0 6px 0; letter-spacing:0.5px; border-bottom:1px solid #334155; padding-bottom:3px; display:flex; justify-content:space-between; align-items:center;">
                    <span><i class="fa-solid fa-video" style="color:#c084fc;"></i> CCTV Surveillance &amp; Projects (${cctv.length})</span>
                    <a href="cctv.php" style="font-size:0.68rem; color:#c084fc; text-decoration:none; font-weight:700;">CCTV Desk &rarr;</a>
                </div>
            `;
            cctv.slice(0, activeGlobalSearchTab === 'cctv' ? 20 : 4).forEach(cp => {
                const cctvUrl = `cctv.php?invoice=${encodeURIComponent(cp.projectNo || cp.id)}`;
                html += `
                    <div style="display:flex; justify-content:space-between; align-items:center; background:#0f172a; border:1px solid #334155; padding:8px 10px; border-radius:8px; margin-bottom:6px; cursor:pointer; transition:background 0.15s ease;" onmouseover="this.style.background='#1e293b'" onmouseout="this.style.background='#0f172a'" onclick="window.location.href='${cctvUrl}'">
                        <div>
                            <div style="font-weight:800; color:#c084fc; font-size:0.82rem; display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                <span>${escapeHtml(cp.projectNo)} &bull; ${escapeHtml(cp.clientName)}</span>
                                ${cp.systemPackage ? `<span style="background:rgba(192,132,252,0.2); color:#c084fc; font-weight:700; font-size:0.65rem; padding:1px 5px; border-radius:4px;">${escapeHtml(cp.systemPackage)}</span>` : ''}
                            </div>
                            <div style="font-size:0.72rem; color:#94a3b8; margin-top:2px;">
                                Site: ${escapeHtml(cp.siteAddress)} &bull; Brand: <strong style="color:#cbd5e1;">${escapeHtml(cp.cameraBrand)}</strong>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:900; color:#4ade80; font-size:0.82rem;">PKR ${Number(cp.totalBill || 0).toLocaleString()}</div>
                            <a href="${cctvUrl}" onclick="event.stopPropagation();" style="font-size:0.68rem; color:#c084fc; text-decoration:none; font-weight:700; background:rgba(192,132,252,0.15); padding:2px 8px; border-radius:4px; display:inline-block; margin-top:2px;">
                                <i class="fa-solid fa-file-invoice"></i> View Receipt &rarr;
                            </a>
                        </div>
                    </div>
                `;
            });
        }

        // 6. EASYPAISA / JAZZCASH (MENU: Easypaisa / JazzCash)
        if ((activeGlobalSearchTab === 'all' || activeGlobalSearchTab === 'services') && services.length > 0) {
            html += `
                <div style="font-size:0.72rem; font-weight:900; color:#10b981; text-transform:uppercase; margin:10px 0 6px 0; letter-spacing:0.5px; border-bottom:1px solid #334155; padding-bottom:3px; display:flex; justify-content:space-between; align-items:center;">
                    <span><i class="fa-solid fa-wallet" style="color:#10b981;"></i> Easypaisa / JazzCash (${services.length})</span>
                    <a href="services.php" style="font-size:0.68rem; color:#10b981; text-decoration:none; font-weight:700;">Services Register &rarr;</a>
                </div>
            `;
            services.slice(0, activeGlobalSearchTab === 'services' ? 20 : 4).forEach(sv => {
                const srvUrl = `services.php?trx=${encodeURIComponent(sv.trxId || sv.id)}`;
                const prov = (sv.serviceProvider || 'EASYPAISA').toUpperCase();
                let provBadge = `<span style="background:rgba(16,185,129,0.2); color:#34d399; font-weight:800; font-size:0.65rem; padding:1px 6px; border-radius:4px;"><i class="fa-solid fa-mobile-screen"></i> EASYPAISA</span>`;
                if (prov.includes('JAZZ')) {
                    provBadge = `<span style="background:rgba(239,68,68,0.2); color:#f87171; font-weight:800; font-size:0.65rem; padding:1px 6px; border-radius:4px;"><i class="fa-solid fa-bolt"></i> JAZZCASH</span>`;
                }

                html += `
                    <div style="display:flex; justify-content:space-between; align-items:center; background:#0f172a; border:1px solid #334155; padding:8px 10px; border-radius:8px; margin-bottom:6px; cursor:pointer; transition:background 0.15s ease;" onmouseover="this.style.background='#1e293b'" onmouseout="this.style.background='#0f172a'" onclick="window.location.href='${srvUrl}'">
                        <div>
                            <div style="font-weight:800; color:#10b981; font-family:monospace; font-size:0.85rem; display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                <span>${escapeHtml(sv.trxId)}</span>
                                ${provBadge}
                                <span style="background:#334155; color:#cbd5e1; font-size:0.62rem; font-weight:800; padding:1px 5px; border-radius:3px;">${escapeHtml(sv.txType)}</span>
                            </div>
                            <div style="font-size:0.72rem; color:#94a3b8; margin-top:2px;">
                                Client: <strong style="color:#fff;">${escapeHtml(sv.customerName)}</strong> &bull; Receiver: <strong style="color:#cbd5e1;">${escapeHtml(sv.receiverName)}</strong>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:900; color:#4ade80; font-size:0.85rem;">
                                PKR ${Number(sv.amount || 0).toLocaleString()}
                            </div>
                            <a href="${srvUrl}" onclick="event.stopPropagation();" style="font-size:0.68rem; color:#10b981; text-decoration:none; font-weight:700; background:rgba(16,185,129,0.15); padding:2px 8px; border-radius:4px; display:inline-block; margin-top:2px;">
                                <i class="fa-solid fa-receipt"></i> View Slip &rarr;
                            </a>
                        </div>
                    </div>
                `;
            });
        }

        // 7. DEBTS & LEDGERS (MENU: Debts & Ledgers)
        if ((activeGlobalSearchTab === 'all' || activeGlobalSearchTab === 'debts') && debts.length > 0) {
            html += `
                <div style="font-size:0.72rem; font-weight:900; color:#f59e0b; text-transform:uppercase; margin:10px 0 6px 0; letter-spacing:0.5px; border-bottom:1px solid #334155; padding-bottom:3px; display:flex; justify-content:space-between; align-items:center;">
                    <span><i class="fa-solid fa-money-check-dollar" style="color:#f59e0b;"></i> Debts &amp; Customer Ledgers (${debts.length})</span>
                    <a href="payments.php" style="font-size:0.68rem; color:#f59e0b; text-decoration:none; font-weight:700;">Debts Register &rarr;</a>
                </div>
            `;
            debts.slice(0, activeGlobalSearchTab === 'debts' ? 20 : 4).forEach(d => {
                const payUrl = `payments.php?customer=${encodeURIComponent(d.customerName)}&search=${encodeURIComponent(d.customerName)}`;
                html += `
                    <div style="display:flex; justify-content:space-between; align-items:center; background:#0f172a; border:1px solid #334155; padding:8px 10px; border-radius:8px; margin-bottom:6px; cursor:pointer;" onclick="window.location.href='${payUrl}'">
                        <div>
                            <div style="font-weight:900; color:#fff; font-size:0.88rem; display:flex; align-items:center; gap:6px;">
                                <i class="fa-solid fa-user-clock" style="color:#f59e0b;"></i>
                                <span>${escapeHtml(d.customerName)}</span>
                                <span style="background:rgba(239,68,68,0.2); color:#f87171; font-weight:800; font-size:0.62rem; padding:1px 5px; border-radius:4px;">OUTSTANDING DUE</span>
                            </div>
                            <div style="font-size:0.72rem; color:#94a3b8; margin-top:2px;">
                                Phone: <strong style="color:#cbd5e1;">${escapeHtml(d.customerPhone || 'N/A')}</strong> &bull; Past Orders: ${d.totalPurchases}
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:900; color:#f87171; font-size:0.92rem;">
                                Due: PKR ${Number(d.balance || 0).toLocaleString()}
                            </div>
                            <a href="${payUrl}" onclick="event.stopPropagation();" style="font-size:0.68rem; color:#f59e0b; text-decoration:none; font-weight:700; background:rgba(245,158,11,0.15); padding:2px 8px; border-radius:4px; display:inline-block; margin-top:2px;">
                                <i class="fa-solid fa-hand-holding-dollar"></i> Collect / Settle &rarr;
                            </a>
                        </div>
                    </div>
                `;
            });
        }

        // 8. NADRA & CITIZEN KIOSK (MENU: NADRA & Citizen Kiosk)
        if ((activeGlobalSearchTab === 'all' || activeGlobalSearchTab === 'nadra') && nadra.length > 0) {
            html += `
                <div style="font-size:0.72rem; font-weight:900; color:#818cf8; text-transform:uppercase; margin:10px 0 6px 0; letter-spacing:0.5px; border-bottom:1px solid #334155; padding-bottom:3px; display:flex; justify-content:space-between; align-items:center;">
                    <span><i class="fa-solid fa-id-card" style="color:#818cf8;"></i> NADRA &amp; Citizen Kiosk (${nadra.length})</span>
                    <a href="nadra-kiosk.php" style="font-size:0.68rem; color:#818cf8; text-decoration:none; font-weight:700;">Citizen Desk &rarr;</a>
                </div>
            `;
            nadra.slice(0, activeGlobalSearchTab === 'nadra' ? 20 : 4).forEach(nk => {
                const nkUrl = `nadra-kiosk.php?tracking=${encodeURIComponent(nk.trackingNo || nk.id)}`;
                html += `
                    <div style="display:flex; justify-content:space-between; align-items:center; background:#0f172a; border:1px solid #334155; padding:8px 10px; border-radius:8px; margin-bottom:6px; cursor:pointer;" onclick="window.location.href='${nkUrl}'">
                        <div>
                            <div style="font-weight:800; color:#818cf8; font-family:monospace; font-size:0.85rem; display:flex; align-items:center; gap:6px;">
                                <span>${escapeHtml(nk.trackingNo)}</span>
                                <span style="background:rgba(129,140,248,0.2); color:#a5b4fc; font-weight:700; font-size:0.65rem; padding:1px 5px; border-radius:3px;">${escapeHtml(nk.status)}</span>
                            </div>
                            <div style="font-size:0.72rem; color:#94a3b8; margin-top:2px;">
                                Citizen: <strong style="color:#fff;">${escapeHtml(nk.citizenName)}</strong> &bull; ${escapeHtml(nk.serviceName)}
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:900; color:#4ade80; font-size:0.82rem;">PKR ${Number(nk.totalFee || 0).toLocaleString()}</div>
                            <a href="${nkUrl}" onclick="event.stopPropagation();" style="font-size:0.68rem; color:#818cf8; text-decoration:none; font-weight:700; background:rgba(129,140,248,0.15); padding:2px 8px; border-radius:4px; display:inline-block; margin-top:2px;">
                                <i class="fa-solid fa-id-card"></i> Print Token Slip &rarr;
                            </a>
                        </div>
                    </div>
                `;
            });
        }

        // 9. SPARE PARTS & PRICING (MENU: Spare Parts & Pricing)
        if ((activeGlobalSearchTab === 'all' || activeGlobalSearchTab === 'repairParts') && repairParts.length > 0) {
            html += `
                <div style="font-size:0.72rem; font-weight:900; color:#2dd4bf; text-transform:uppercase; margin:10px 0 6px 0; letter-spacing:0.5px; border-bottom:1px solid #334155; padding-bottom:3px; display:flex; justify-content:space-between; align-items:center;">
                    <span><i class="fa-solid fa-microchip" style="color:#2dd4bf;"></i> Spare Parts &amp; Pricing (${repairParts.length})</span>
                    <a href="repair-parts.php" style="font-size:0.68rem; color:#2dd4bf; text-decoration:none; font-weight:700;">Parts Catalog &rarr;</a>
                </div>
            `;
            repairParts.slice(0, activeGlobalSearchTab === 'repairParts' ? 20 : 4).forEach(rp => {
                const rpUrl = `repair-parts.php?search=${encodeURIComponent(rp.name)}`;
                html += `
                    <div style="display:flex; justify-content:space-between; align-items:center; background:#0f172a; border:1px solid #334155; padding:8px 10px; border-radius:8px; margin-bottom:6px; cursor:pointer;" onclick="window.location.href='${rpUrl}'">
                        <div>
                            <div style="font-weight:800; color:#fff; font-size:0.85rem;">${escapeHtml(rp.name)}</div>
                            <div style="font-size:0.72rem; color:#94a3b8; margin-top:2px;">
                                Model: <strong style="color:#cbd5e1;">${escapeHtml(rp.deviceBrand)} ${escapeHtml(rp.deviceModel)}</strong> &bull; Stock: <strong style="color:#34d399;">${rp.stock} pcs</strong>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:900; color:#4ade80; font-size:0.85rem;">PKR ${Number(rp.sellingPrice || 0).toLocaleString()}</div>
                            <a href="${rpUrl}" onclick="event.stopPropagation();" style="font-size:0.68rem; color:#2dd4bf; text-decoration:none; font-weight:700; background:rgba(45,212,191,0.15); padding:2px 8px; border-radius:4px; display:inline-block; margin-top:2px;">
                                View Part &rarr;
                            </a>
                        </div>
                    </div>
                `;
            });
        }

        // 10. CUSTOMERS DIRECTORY (MENU: Customers)
        if ((activeGlobalSearchTab === 'all' || activeGlobalSearchTab === 'customers') && customers.length > 0) {
            html += `
                <div style="font-size:0.72rem; font-weight:900; color:#38bdf8; text-transform:uppercase; margin:10px 0 6px 0; letter-spacing:0.5px; border-bottom:1px solid #334155; padding-bottom:3px; display:flex; justify-content:space-between; align-items:center;">
                    <span><i class="fa-solid fa-users" style="color:#38bdf8;"></i> Customers (${customers.length})</span>
                    <a href="customers.php?search=${encodeURIComponent(q)}" style="font-size:0.68rem; color:#38bdf8; text-decoration:none; font-weight:700;">All Directory &rarr;</a>
                </div>
            `;
            customers.slice(0, activeGlobalSearchTab === 'customers' ? 20 : 4).forEach(c => {
                const spent = Number(c.totalSpent || 0).toLocaleString();
                const ords = Number(c.totalPurchases || 0);
                const bal = Number(c.balance || 0);
                let cleanPhone = (c.phone || '').replace(/[^0-9]/g, '');
                if (cleanPhone.startsWith('0')) cleanPhone = '92' + cleanPhone.substring(1);

                html += `
                    <div style="background:#0f172a; border:1px solid #334155; padding:10px 12px; border-radius:10px; margin-bottom:6px;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                            <div>
                                <div style="font-weight:900; color:#fff; font-size:0.92rem; display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                    <i class="fa-solid fa-circle-user" style="color:var(--pos-gold); font-size:1.05rem;"></i>
                                    <span>${escapeHtml(c.name)}</span>
                                    ${bal > 0 ? '<span style="background:rgba(239,68,68,0.2); color:#f87171; font-size:0.62rem; font-weight:800; padding:1px 5px; border-radius:4px;">Udhaar Due</span>' : ''}
                                </div>
                                <div style="font-size:0.75rem; color:#94a3b8; margin-top:2px;">
                                    ${c.phone ? `<span style="color:#cbd5e1;"><i class="fa-solid fa-phone" style="color:#10b981;"></i> ${escapeHtml(c.phone)}</span>` : '<span style="color:#64748b;">No phone</span>'}
                                    ${c.city ? ` &bull; <span>${escapeHtml(c.city)}</span>` : ''}
                                </div>
                                <div style="font-size:0.72rem; color:#94a3b8; margin-top:2px;">
                                    Orders: <strong style="color:#38bdf8;">${ords}</strong> | Spent: <strong style="color:#4ade80;">PKR ${spent}</strong>
                                    ${bal > 0 ? ` | <span style="color:#f87171; font-weight:800;">Balance: PKR ${bal.toLocaleString()}</span>` : ''}
                                </div>
                            </div>
                            <div style="display:flex; flex-direction:column; gap:4px; align-items:flex-end;">
                                <a href="pos.php?cust_name=${encodeURIComponent(c.name)}&cust_phone=${encodeURIComponent(c.phone || '')}" class="pos-btn pos-btn-sm" style="background:#dc2626; color:#fff; padding:3px 9px; font-size:0.72rem; text-decoration:none; font-weight:800; border-radius:6px; display:inline-flex; align-items:center; gap:4px;">
                                    <i class="fa-solid fa-cart-plus"></i> + POS
                                </a>
                                <div style="display:flex; gap:4px;">
                                    ${cleanPhone ? `
                                        <a href="https://wa.me/${cleanPhone}" target="_blank" class="pos-btn pos-btn-sm" style="background:#059669; color:#fff; padding:2px 6px; font-size:0.68rem; border-radius:4px; text-decoration:none;" title="WhatsApp">
                                            <i class="fa-brands fa-whatsapp"></i>
                                        </a>
                                    ` : ''}
                                    <a href="customers.php?search=${encodeURIComponent(c.name)}" class="pos-btn pos-btn-sm" style="background:#334155; color:#cbd5e1; padding:2px 6px; font-size:0.68rem; border-radius:4px; text-decoration:none;">
                                        <i class="fa-solid fa-book"></i> Ledger
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
        }

        // 11. OPERATING EXPENSES (MENU: Operating Expenses)
        if ((activeGlobalSearchTab === 'all' || activeGlobalSearchTab === 'expenses') && expenses.length > 0) {
            html += `
                <div style="font-size:0.72rem; font-weight:900; color:#f472b6; text-transform:uppercase; margin:10px 0 6px 0; letter-spacing:0.5px; border-bottom:1px solid #334155; padding-bottom:3px; display:flex; justify-content:space-between; align-items:center;">
                    <span><i class="fa-solid fa-file-invoice-dollar" style="color:#f472b6;"></i> Operating Expenses (${expenses.length})</span>
                    <a href="expenses.php" style="font-size:0.68rem; color:#f472b6; text-decoration:none; font-weight:700;">Expenses Register &rarr;</a>
                </div>
            `;
            expenses.slice(0, activeGlobalSearchTab === 'expenses' ? 20 : 4).forEach(exp => {
                const expUrl = `expenses.php?search=${encodeURIComponent(exp.category)}`;
                html += `
                    <div style="display:flex; justify-content:space-between; align-items:center; background:#0f172a; border:1px solid #334155; padding:8px 10px; border-radius:8px; margin-bottom:6px; cursor:pointer;" onclick="window.location.href='${expUrl}'">
                        <div>
                            <div style="font-weight:800; color:#f472b6; font-size:0.85rem;">${escapeHtml(exp.category)}</div>
                            <div style="font-size:0.72rem; color:#94a3b8; margin-top:2px;">
                                ${exp.vendor_shop ? `Vendor: <strong>${escapeHtml(exp.vendor_shop)}</strong> &bull; ` : ''}${escapeHtml(exp.item_details || exp.notes || 'Store Expense')}
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:900; color:#f87171; font-size:0.85rem;">PKR ${Number(exp.amount || 0).toLocaleString()}</div>
                            <span style="font-size:0.68rem; color:#94a3b8;">${escapeHtml(exp.date)}</span>
                        </div>
                    </div>
                `;
            });
        }

        // 12. PACKAGES & SIM BUNDLES (MENU: Mobile Packages & Load)
        if ((activeGlobalSearchTab === 'all' || activeGlobalSearchTab === 'packages') && packages.length > 0) {
            html += `
                <div style="font-size:0.72rem; font-weight:900; color:#06b6d4; text-transform:uppercase; margin:10px 0 6px 0; letter-spacing:0.5px; border-bottom:1px solid #334155; padding-bottom:3px; display:flex; justify-content:space-between; align-items:center;">
                    <span><i class="fa-solid fa-mobile-screen-button" style="color:#06b6d4;"></i> Mobile Packages &amp; Load (${packages.length})</span>
                    <a href="packages.php" style="font-size:0.68rem; color:#06b6d4; text-decoration:none; font-weight:700;">Packages Hub &rarr;</a>
                </div>
            `;
            packages.slice(0, activeGlobalSearchTab === 'packages' ? 20 : 3).forEach(pkg => {
                html += `
                    <div style="display:flex; justify-content:space-between; align-items:center; background:#0f172a; border:1px solid #334155; padding:8px 10px; border-radius:8px; margin-bottom:6px;">
                        <div>
                            <div style="font-weight:800; color:#06b6d4; font-size:0.85rem;">${escapeHtml(pkg.name)}</div>
                            <div style="font-size:0.72rem; color:#94a3b8; margin-top:2px;">
                                Network: <strong style="color:#cbd5e1;">${escapeHtml(pkg.network)}</strong> &bull; Validity: ${escapeHtml(pkg.validity)}
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:900; color:#4ade80; font-size:0.85rem;">PKR ${Number(pkg.price || 0).toLocaleString()}</div>
                            <a href="packages.php?search=${encodeURIComponent(pkg.name)}" style="font-size:0.68rem; color:#06b6d4; text-decoration:none; font-weight:700;">Subscribe &rarr;</a>
                        </div>
                    </div>
                `;
            });
        }

        // 13. PRODUCTS & INVENTORY (MENU: All Products)
        if ((activeGlobalSearchTab === 'all' || activeGlobalSearchTab === 'products') && products.length > 0) {
            html += `
                <div style="font-size:0.72rem; font-weight:900; color:#60a5fa; text-transform:uppercase; margin:10px 0 6px 0; letter-spacing:0.5px; border-bottom:1px solid #334155; padding-bottom:3px; display:flex; justify-content:space-between; align-items:center;">
                    <span><i class="fa-solid fa-boxes-stacked" style="color:#60a5fa;"></i> Products &amp; Inventory (${products.length})</span>
                    <a href="products.php?search=${encodeURIComponent(q)}" style="font-size:0.68rem; color:#60a5fa; text-decoration:none; font-weight:700;">All Products &rarr;</a>
                </div>
            `;
            products.slice(0, activeGlobalSearchTab === 'products' ? 20 : 4).forEach(p => {
                const price = Number(p.sellingPrice || 0).toLocaleString();
                const isOut = p.stock <= 0;
                const isLow = p.stock <= (p.minStock || 2);
                html += `
                    <div style="display:flex; justify-content:space-between; align-items:center; background:#0f172a; border:1px solid #334155; padding:8px 10px; border-radius:8px; margin-bottom:6px;">
                        <div>
                            <div style="font-weight:800; color:#fff; font-size:0.85rem;">${escapeHtml(p.name)}</div>
                            <div style="font-size:0.72rem; color:#94a3b8;">
                                SKU: <strong style="color:#cbd5e1;">${escapeHtml(p.sku)}</strong> &bull; Category: <span style="text-transform:uppercase;">${escapeHtml(p.category)}</span> &bull; Stock: <span style="font-weight:800; color:${isOut ? '#f87171' : (isLow ? '#f59e0b' : '#4ade80')}">${isOut ? 'OUT OF STOCK' : p.stock + ' units'}</span>
                            </div>
                        </div>
                        <div style="text-align:right; flex-shrink:0;">
                            <div style="font-weight:900; color:#4ade80; font-size:0.88rem;">PKR ${price}</div>
                            <div style="display:flex; gap:4px; justify-content:flex-end; margin-top:2px;">
                                <a href="product-add.php?id=${encodeURIComponent(p.id)}" style="font-size:0.68rem; color:#38bdf8; text-decoration:none; font-weight:700;">Edit &rarr;</a>
                            </div>
                        </div>
                    </div>
                `;
            });
        }

        // 14. SUPPLIERS DIRECTORY (MENU: Suppliers Directory)
        if ((activeGlobalSearchTab === 'all' || activeGlobalSearchTab === 'suppliers') && suppliers.length > 0) {
            html += `
                <div style="font-size:0.72rem; font-weight:900; color:#94a3b8; text-transform:uppercase; margin:10px 0 6px 0; letter-spacing:0.5px; border-bottom:1px solid #334155; padding-bottom:3px; display:flex; justify-content:space-between; align-items:center;">
                    <span><i class="fa-solid fa-building" style="color:#94a3b8;"></i> Suppliers Directory (${suppliers.length})</span>
                    <a href="suppliers.php" style="font-size:0.68rem; color:#94a3b8; text-decoration:none; font-weight:700;">Suppliers &rarr;</a>
                </div>
            `;
            suppliers.slice(0, 3).forEach(sup => {
                html += `
                    <div style="display:flex; justify-content:space-between; align-items:center; background:#0f172a; border:1px solid #334155; padding:8px 10px; border-radius:8px; margin-bottom:6px;">
                        <div>
                            <div style="font-weight:800; color:#fff; font-size:0.85rem;">${escapeHtml(sup.name)}</div>
                            <div style="font-size:0.72rem; color:#94a3b8;">${escapeHtml(sup.company)} &bull; ${escapeHtml(sup.phone || sup.address)}</div>
                        </div>
                        <div>
                            <a href="suppliers.php?search=${encodeURIComponent(sup.name)}" style="font-size:0.68rem; color:var(--pos-gold); text-decoration:none; font-weight:700;">Supplier Profile &rarr;</a>
                        </div>
                    </div>
                `;
            });
        }

        content.innerHTML = html;
        popup.style.display = 'block';
    }

    window.submitGlobalSearch = function (query) {
        const q = (query || '').trim();
        if (!q) return;
        window.handleGlobalSearchInput(q);
    };

    // Close topbar search on outside click
    document.addEventListener('click', function (e) {
        const topbarSearch = document.querySelector('.topbar-search');
        const popup = document.getElementById('topbarSearchResults');
        if (popup && topbarSearch && !topbarSearch.contains(e.target)) {
            popup.style.display = 'none';
        }
    });

    // =========================================================================
    // POS TERMINAL EXISTING CUSTOMER SEARCH & AUTOCOMPLETE
    // =========================================================================
    let posCustSearchDebounce = null;
    let posAllCustomersCache = null;

    function fetchPOSCustomers(cb) {
        if (posAllCustomersCache) {
            cb(posAllCustomersCache);
            return;
        }
        fetch('../backend/customers.php')
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    posAllCustomersCache = res.data || [];
                    cb(posAllCustomersCache);
                }
            })
            .catch(() => cb([]));
    }

    window.handlePOSCustomerSearchInput = function (query) {
        const popup = document.getElementById('posCustomerSuggestionsPopup');
        const list = document.getElementById('posCustomerSuggestionsList');
        if (!popup || !list) return;

        clearTimeout(posCustSearchDebounce);
        const q = (query || '').toLowerCase().trim();

        if (q.length < 1) {
            popup.style.display = 'none';
            return;
        }

        posCustSearchDebounce = setTimeout(function () {
            fetchPOSCustomers(function (customers) {
                const matches = customers.filter(c => {
                    return (c.name || '').toLowerCase().includes(q) || (c.phone || '').includes(q);
                });

                if (matches.length === 0) {
                    popup.style.display = 'none';
                    return;
                }

                let html = '';
                matches.slice(0, 5).forEach(c => {
                    const spent = Number(c.totalSpent || 0).toLocaleString();
                    const orders = c.totalPurchases || 0;
                    html += `
                        <div onclick='window.selectExistingPOSCustomer(${JSON.stringify(c)})' style="padding:6px 8px; border-radius:6px; cursor:pointer; background:#0f172a; border:1px solid #334155; margin-bottom:4px; display:flex; justify-content:space-between; align-items:center; font-size:0.75rem;" onmouseover="this.style.background='#1e293b'" onmouseout="this.style.background='#0f172a'">
                            <div>
                                <strong style="color:#fff;">${escapeHtml(c.name)}</strong>
                                <div style="font-size:0.68rem; color:#94a3b8;">📞 ${escapeHtml(c.phone || 'No phone')} | Orders: ${orders}</div>
                            </div>
                            <div style="text-align:right;">
                                <span style="color:#4ade80; font-weight:800; font-size:0.72rem;">PKR ${spent}</span>
                                <span style="display:block; font-size:0.65rem; color:#38bdf8;">Select &rarr;</span>
                            </div>
                        </div>
                    `;
                });

                list.innerHTML = html;
                popup.style.display = 'block';
            });
        }, 120);
    };

    window.closePOSCustomerSuggestions = function () {
        const popup = document.getElementById('posCustomerSuggestionsPopup');
        if (popup) popup.style.display = 'none';
    };

    window.selectExistingPOSCustomer = function (cust) {
        const nameInput = document.getElementById('posCustomerNameInput');
        const phoneInput = document.getElementById('posCustomerPhoneInput');
        const badge = document.getElementById('posCustomerSelectedBadge');
        const badgeText = document.getElementById('posCustomerBadgeText');
        const badgeSpent = document.getElementById('posCustomerBadgeSpent');

        if (nameInput) nameInput.value = cust.name || '';
        if (phoneInput) phoneInput.value = cust.phone || '';

        if (badge && badgeText && badgeSpent) {
            const spent = Number(cust.totalSpent || 0).toLocaleString();
            const orders = cust.totalPurchases || 0;
            badgeText.innerHTML = `<i class="fa-solid fa-user-check"></i> <strong>${escapeHtml(cust.name)}</strong> (${orders} Orders)`;
            badgeSpent.innerHTML = `Spent: PKR ${spent}`;
            badge.style.display = 'flex';
        }

        window.closePOSCustomerSuggestions();
        window.closeExistingCustomerPickerModal();
    };

    window.resetToWalkInCustomer = function () {
        const nameInput = document.getElementById('posCustomerNameInput');
        const phoneInput = document.getElementById('posCustomerPhoneInput');
        const badge = document.getElementById('posCustomerSelectedBadge');

        if (nameInput) nameInput.value = 'Walk-in Customer';
        if (phoneInput) phoneInput.value = '';
        if (badge) badge.style.display = 'none';
        window.closePOSCustomerSuggestions();
    };

    window.openExistingCustomerPickerModal = function () {
        const modal = document.getElementById('posExistingCustomerModal');
        const searchInput = document.getElementById('posCustModalSearchInput');
        if (!modal) return;

        if (searchInput) searchInput.value = '';
        window.filterPOSCustomerModalList('');
        modal.style.display = 'flex';
    };

    window.closeExistingCustomerPickerModal = function () {
        const modal = document.getElementById('posExistingCustomerModal');
        if (modal) modal.style.display = 'none';
    };

    window.filterPOSCustomerModalList = function (query) {
        const container = document.getElementById('posCustModalList');
        if (!container) return;

        container.innerHTML = '<div style="text-align:center; padding:20px; color:#64748b;"><i class="fa-solid fa-spinner fa-spin"></i> Loading customers...</div>';

        fetchPOSCustomers(function (customers) {
            const q = (query || '').toLowerCase().trim();
            const filtered = customers.filter(c => {
                if (!q) return true;
                return (c.name || '').toLowerCase().includes(q) || (c.phone || '').includes(q) || (c.email || '').toLowerCase().includes(q);
            });

            if (filtered.length === 0) {
                container.innerHTML = '<div style="text-align:center; padding:25px; color:#94a3b8; font-size:0.85rem;">No customer records found matching search.</div>';
                return;
            }

            let html = '<div style="display:flex; flex-direction:column;">';
            filtered.forEach(c => {
                const spent = Number(c.totalSpent || 0).toLocaleString();
                const orders = c.totalPurchases || 0;
                const bal = Number(c.balance || 0);

                html += `
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 14px; border-bottom:1px solid #f1f5f9; background:#fff;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'">
                        <div>
                            <div style="font-weight:800; font-size:0.9rem; color:#0f172a;">${escapeHtml(c.name)}</div>
                            <div style="font-size:0.75rem; color:#64748b;">
                                📞 <strong>${escapeHtml(c.phone || 'No phone')}</strong> | Total Purchases: <strong style="color:#0284c7;">${orders}</strong>
                                ${bal > 0 ? ` | <span style="color:#dc2626; font-weight:800;">Balance Due: PKR ${bal.toLocaleString()}</span>` : ''}
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="text-align:right;">
                                <div style="font-size:0.72rem; color:#64748b;">Lifetime Spent:</div>
                                <div style="font-weight:900; color:#059669; font-size:0.92rem;">PKR ${spent}</div>
                            </div>
                            <button type="button" class="pos-btn pos-btn-primary pos-btn-sm" onclick='window.selectExistingPOSCustomer(${JSON.stringify(c)})' style="font-weight:800; font-size:0.75rem; padding:4px 10px;">
                                <i class="fa-solid fa-check"></i> Select
                            </button>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        });
    };

    // Auto-detect URL query params on POS load (e.g. pos.php?cust_name=Ahmad&cust_phone=0300...)
    document.addEventListener('DOMContentLoaded', function () {
        const urlParams = new URLSearchParams(window.location.search);
        const custNameParam = urlParams.get('cust_name');
        const custPhoneParam = urlParams.get('cust_phone');

        if (custNameParam) {
            window.selectExistingPOSCustomer({
                name: custNameParam,
                phone: custPhoneParam || '',
                totalSpent: 0,
                totalPurchases: 1
            });
        }
    });

    // =========================================================================
    // AUTOMATED WHATSAPP RECEIPT & NOTIFICATION DISPATCH ENGINE
    // =========================================================================
    window.sendPOSWhatsAppReceipt = function () {
        const sale = window.POSState?.completedSale;
        if (!sale) {
            alert('No active completed sale found to send receipt for.');
            return;
        }

        let phone = (sale.customerPhone || '').replace(/[^0-9]/g, '');
        if (!phone) {
            phone = prompt('Enter customer WhatsApp mobile number (e.g. 03339688007):');
            if (!phone) return;
        }

        fetch(getAdminBackendApiUrl('whatsapp.php') + `?type=sale&id=${encodeURIComponent(sale.id)}&phone=${encodeURIComponent(phone)}`)
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success' && res.data && res.data.whatsappUrl) {
                    window.open(res.data.whatsappUrl, '_blank');
                } else {
                    alert(res.message || 'Failed to generate WhatsApp receipt');
                }
            })
            .catch(err => {
                alert('Error connecting to WhatsApp dispatcher: ' + err.message);
            });
    };

    window.sendInvoiceWhatsApp = function (saleId, custPhone) {
        let phone = (custPhone || '').replace(/[^0-9]/g, '');
        if (!phone) {
            phone = prompt('Enter customer WhatsApp mobile number (e.g. 03339688007):');
            if (!phone) return;
        }

        fetch(getAdminBackendApiUrl('whatsapp.php') + `?type=sale&id=${encodeURIComponent(saleId)}&phone=${encodeURIComponent(phone)}`)
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success' && res.data && res.data.whatsappUrl) {
                    window.open(res.data.whatsappUrl, '_blank');
                } else {
                    alert(res.message || 'Failed to generate WhatsApp receipt');
                }
            })
            .catch(err => {
                alert('Error connecting to WhatsApp dispatcher: ' + err.message);
            });
    };

    window.sendActiveInvoiceWhatsAppModal = function () {
        if (window.currentActiveInvoiceId) {
            window.sendInvoiceWhatsApp(window.currentActiveInvoiceId, window.currentActiveInvoicePhone || '');
        } else {
            alert('No active invoice selected');
        }
    };

    window.sendRepairWhatsApp = function (repairId, custPhone) {
        let phone = (custPhone || '').replace(/[^0-9]/g, '');
        if (!phone) {
            phone = prompt('Enter customer WhatsApp mobile number (e.g. 03339688007):');
            if (!phone) return;
        }

        fetch(getAdminBackendApiUrl('whatsapp.php') + `?type=repair&id=${encodeURIComponent(repairId)}&phone=${encodeURIComponent(phone)}`)
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success' && res.data && res.data.whatsappUrl) {
                    window.open(res.data.whatsappUrl, '_blank');
                } else {
                    alert(res.message || 'Failed to generate WhatsApp ticket');
                }
            })
            .catch(err => {
                alert('Error connecting to WhatsApp dispatcher: ' + err.message);
            });
    };

    window.sendCctvWhatsApp = function (projectId, clientPhone) {
        let phone = (clientPhone || '').replace(/[^0-9]/g, '');
        if (!phone) {
            phone = prompt('Enter client WhatsApp mobile number (e.g. 03339688007):');
            if (!phone) return;
        }

        fetch(getAdminBackendApiUrl('whatsapp.php') + `?type=cctv&id=${encodeURIComponent(projectId)}&phone=${encodeURIComponent(phone)}`)
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success' && res.data && res.data.whatsappUrl) {
                    window.open(res.data.whatsappUrl, '_blank');
                } else {
                    alert(res.message || 'Failed to generate WhatsApp booking receipt');
                }
            })
            .catch(err => {
                alert('Error connecting to WhatsApp dispatcher: ' + err.message);
            });
    };

    window.sendCitizenWhatsApp = function (serviceId, citizenPhone) {
        let phone = (citizenPhone || '').replace(/[^0-9]/g, '');
        if (!phone) {
            phone = prompt('Enter citizen WhatsApp mobile number (e.g. 03339688007):');
            if (!phone) return;
        }

        fetch(getAdminBackendApiUrl('whatsapp.php') + `?type=citizen&id=${encodeURIComponent(serviceId)}&phone=${encodeURIComponent(phone)}`)
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success' && res.data && res.data.whatsappUrl) {
                    window.open(res.data.whatsappUrl, '_blank');
                } else {
                    alert(res.message || 'Failed to generate WhatsApp receipt');
                }
            })
            .catch(err => {
                alert('Error connecting to WhatsApp dispatcher: ' + err.message);
            });
    };

    // =========================================================================
    // MOBILE SIDEBAR DRAWER CONTROLLER
    // =========================================================================
    window.toggleMobileSidebar = function () {
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar) {
            sidebar.classList.toggle('active');
            sidebar.classList.toggle('mobile-open');
        }
        if (overlay) {
            overlay.classList.toggle('active');
        }
    };

    window.closeMobileSidebar = function () {
        const sidebar = document.getElementById('adminSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar) {
            sidebar.classList.remove('active');
            sidebar.classList.remove('mobile-open');
        }
        if (overlay) {
            overlay.classList.remove('active');
        }
    };

})();



