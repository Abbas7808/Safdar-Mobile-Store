<?php
$pageTitle = 'Mobile Repair Spare Parts & Pricing';
$currentPage = 'repair-parts';
require_once __DIR__ . '/includes/header.php';

$partsFile = __DIR__ . '/../backend/data/repair_parts.json';
$parts = [];
if (file_exists($partsFile)) {
    $parts = json_decode(file_get_contents($partsFile), true) ?: [];
}

// Calculate Statistics
$totalParts = count($parts);
$totalStock = 0;
$lowStockCount = 0;
$totalCostVal = 0;
$totalRetailVal = 0;

foreach ($parts as $p) {
    $stk = intval($p['stock'] ?? 0);
    $totalStock += $stk;
    if ($stk <= 3) $lowStockCount++;
    $totalCostVal += ($stk * floatval($p['costPrice'] ?? 0));
    $totalRetailVal += ($stk * floatval($p['sellingPrice'] ?? 0));
}
?>

<div class="pos-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="pos-main">
        <!-- Top Stats Row -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:14px; margin-bottom:18px;">
            <div style="background:#fff; border:1px solid var(--pos-border); border-radius:12px; padding:16px; box-shadow:0 2px 6px rgba(0,0,0,0.03); display:flex; align-items:center; gap:14px;">
                <div style="background:#fee2e2; color:var(--pos-red); width:46px; height:46px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
                    <i class="fa-solid fa-microchip"></i>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:#64748b; font-weight:700; text-transform:uppercase;">Catalog Parts</div>
                    <div style="font-size:1.4rem; font-weight:900; color:#0f172a;"><?php echo number_format($totalParts); ?></div>
                </div>
            </div>

            <div style="background:#fff; border:1px solid var(--pos-border); border-radius:12px; padding:16px; box-shadow:0 2px 6px rgba(0,0,0,0.03); display:flex; align-items:center; gap:14px;">
                <div style="background:#eff6ff; color:#2563eb; width:46px; height:46px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
                    <i class="fa-solid fa-boxes-stacked"></i>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:#64748b; font-weight:700; text-transform:uppercase;">Total In-Stock Units</div>
                    <div style="font-size:1.4rem; font-weight:900; color:#0f172a;"><?php echo number_format($totalStock); ?> <span style="font-size:0.8rem; font-weight:600; color:#64748b;">pcs</span></div>
                </div>
            </div>

            <div style="background:#fff; border:1px solid var(--pos-border); border-radius:12px; padding:16px; box-shadow:0 2px 6px rgba(0,0,0,0.03); display:flex; align-items:center; gap:14px;">
                <div style="background:#fef3c7; color:#d97706; width:46px; height:46px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:#64748b; font-weight:700; text-transform:uppercase;">Low Stock (≤ 3 pcs)</div>
                    <div style="font-size:1.4rem; font-weight:900; color:<?php echo $lowStockCount > 0 ? '#dc2626' : '#059669'; ?>;"><?php echo number_format($lowStockCount); ?></div>
                </div>
            </div>

            <div style="background:#fff; border:1px solid var(--pos-border); border-radius:12px; padding:16px; box-shadow:0 2px 6px rgba(0,0,0,0.03); display:flex; align-items:center; gap:14px;">
                <div style="background:#ecfdf5; color:#059669; width:46px; height:46px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
                    <i class="fa-solid fa-coins"></i>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:#64748b; font-weight:700; text-transform:uppercase;">Stock Valuation (Retail)</div>
                    <div style="font-size:1.35rem; font-weight:900; color:#059669;">PKR <?php echo number_format($totalRetailVal); ?></div>
                </div>
            </div>
        </div>

        <!-- Main Card with Filters and Table -->
        <div style="background:#fff; border:1px solid var(--pos-border); border-radius:14px; padding:20px; box-shadow:0 4px 14px rgba(0,0,0,0.04);">
            <!-- Header Row -->
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
                <div>
                    <h2 style="font-size:1.3rem; font-weight:800; color:#0f172a; margin:0; display:flex; align-items:center; gap:8px;">
                        <i class="fa-solid fa-microchip" style="color:var(--pos-red);"></i> Mobile Repair Spare Parts & Component Pricing
                    </h2>
                    <p style="font-size:0.82rem; color:#64748b; margin:3px 0 0 0;">Manage mobile LCD displays, replacement batteries, charging ports, IC chips, and customer repair prices.</p>
                </div>
                <div style="display:flex; gap:8px;">
                    <a href="pos.php" class="pos-btn pos-btn-secondary" style="font-size:0.82rem;">
                        <i class="fa-solid fa-cash-register"></i> Open POS Terminal
                    </a>
                    <button class="pos-btn pos-btn-primary" onclick="openPartModal()" style="font-size:0.82rem; font-weight:800;">
                        <i class="fa-solid fa-plus"></i> Add New Spare Part
                    </button>
                </div>
            </div>

            <!-- Filters Bar -->
            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; background:#f8fafc; padding:12px; border-radius:10px; border:1px solid #e2e8f0;">
                <div style="flex:1; min-width:200px;">
                    <input type="text" id="partSearchInput" class="form-input" placeholder="Search by Part Name, Model (e.g. A54, iPhone 13) or ID..." oninput="filterPartsTable()" style="padding:7px 10px; font-size:0.82rem; width:100%;">
                </div>

                <div style="width:160px;">
                    <select id="partBrandFilter" class="form-select" onchange="filterPartsTable()" style="padding:7px 8px; font-size:0.82rem; width:100%;">
                        <option value="all">All Brands</option>
                        <option value="samsung">Samsung</option>
                        <option value="apple iphone">Apple iPhone</option>
                        <option value="infinix">Infinix</option>
                        <option value="tecno">Tecno</option>
                        <option value="xiaomi / redmi">Xiaomi / Redmi</option>
                        <option value="vivo">Vivo</option>
                        <option value="realme">Realme</option>
                        <option value="oppo">Oppo</option>
                        <option value="universal / all">Universal / All</option>
                    </select>
                </div>

                <div style="width:180px;">
                    <select id="partCategoryFilter" class="form-select" onchange="filterPartsTable()" style="padding:7px 8px; font-size:0.82rem; width:100%;">
                        <option value="all">All Categories</option>
                        <option value="screen & display">Screen & Display</option>
                        <option value="batteries">Batteries</option>
                        <option value="charging & ports">Charging & Ports</option>
                        <option value="cameras & lenses">Cameras & Lenses</option>
                        <option value="housing & glass">Housing & Glass</option>
                        <option value="motherboard & ic chips">Motherboard & IC Chips</option>
                        <option value="audio & speakers">Audio & Speakers</option>
                        <option value="other parts">Other Parts</option>
                    </select>
                </div>
            </div>

            <!-- Parts Table -->
            <div style="width:100%; overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; text-align:left; font-size:0.82rem; min-width:850px;">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:1.5px solid var(--pos-border); color:#475569; font-weight:800; text-transform:uppercase; font-size:0.72rem; letter-spacing:0.5px;">
                            <th style="padding:10px 12px; width:12%;">Part ID & Cat</th>
                            <th style="padding:10px 8px; width:18%;">Brand & Model</th>
                            <th style="padding:10px 8px; width:26%;">Part Description & Warranty</th>
                            <th style="padding:10px 8px; width:12%; text-align:right;">Cost (PKR)</th>
                            <th style="padding:10px 8px; width:16%; text-align:right;">Repair Price (PKR)</th>
                            <th style="padding:10px 8px; width:8%; text-align:center;">Stock</th>
                            <th style="padding:10px 8px; width:8%; text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="partsTableBody">
                        <?php if (empty($parts)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding:40px; color:#9ca3af;">
                                    <i class="fa-solid fa-microchip" style="font-size:2.2rem; margin-bottom:8px; opacity:0.4;"></i>
                                    <p style="font-weight:700; margin:0;">No mobile spare parts found in catalog.</p>
                                    <button class="pos-btn pos-btn-primary pos-btn-sm" style="margin-top:10px;" onclick="openPartModal()">+ Add First Spare Part</button>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($parts as $p): ?>
                                <?php
                                    $cost = floatval($p['costPrice'] ?? 0);
                                    $price = floatval($p['sellingPrice'] ?? 0);
                                    $profit = $price - $cost;
                                    $stk = intval($p['stock'] ?? 0);
                                    $stkColor = $stk <= 3 ? '#dc2626' : '#059669';
                                    $stkBg = $stk <= 3 ? '#fee2e2' : '#ecfdf5';
                                ?>
                                <tr class="part-row" data-id="<?php echo htmlspecialchars($p['id']); ?>" data-brand="<?php echo strtolower($p['deviceBrand'] ?? ''); ?>" data-cat="<?php echo strtolower($p['category'] ?? ''); ?>" data-name="<?php echo strtolower($p['name'] ?? ''); ?>" data-model="<?php echo strtolower($p['deviceModel'] ?? ''); ?>" style="border-bottom:1px solid #f1f5f9; transition:background 0.15s;">
                                    
                                    <!-- Part ID & Category -->
                                    <td style="padding:10px 12px;">
                                        <strong style="color:var(--pos-red); font-family:monospace; font-size:0.85rem;"><?php echo htmlspecialchars($p['id']); ?></strong>
                                        <div style="font-size:0.7rem; color:#64748b; margin-top:2px;">
                                            <span style="background:#f1f5f9; color:#475569; padding:1px 5px; border-radius:4px; font-weight:700;"><?php echo htmlspecialchars($p['category'] ?? 'General'); ?></span>
                                        </div>
                                    </td>

                                    <!-- Brand & Model -->
                                    <td style="padding:10px 8px;">
                                        <span style="background:#e0f2fe; color:#0369a1; font-size:0.68rem; font-weight:800; padding:1px 6px; border-radius:4px; text-transform:uppercase;">
                                            <?php echo htmlspecialchars($p['deviceBrand']); ?>
                                        </span>
                                        <div style="font-weight:700; color:#0f172a; font-size:0.85rem; margin-top:3px;">
                                            <?php echo htmlspecialchars($p['deviceModel']); ?>
                                        </div>
                                    </td>

                                    <!-- Description & Warranty -->
                                    <td style="padding:10px 8px;">
                                        <div style="font-weight:700; color:#0f172a; font-size:0.85rem;"><?php echo htmlspecialchars($p['name']); ?></div>
                                        <?php if (!empty($p['notes'])): ?>
                                            <div style="font-size:0.72rem; color:#64748b; margin-top:2px;"><?php echo htmlspecialchars($p['notes']); ?></div>
                                        <?php endif; ?>
                                        <div style="font-size:0.7rem; color:#059669; font-weight:700; margin-top:2px;">
                                            <i class="fa-solid fa-shield-check"></i> <?php echo htmlspecialchars($p['warranty'] ?? 'No Warranty'); ?>
                                        </div>
                                    </td>

                                    <!-- Cost Price -->
                                    <td style="padding:10px 8px; text-align:right; font-family:monospace; color:#64748b;">
                                        PKR <?php echo number_format($cost); ?>
                                    </td>

                                    <!-- Selling / Repair Price -->
                                    <td style="padding:10px 8px; text-align:right;">
                                        <div style="font-weight:900; font-size:0.95rem; color:#0f172a; font-family:monospace;">
                                            PKR <?php echo number_format($price); ?>
                                        </div>
                                        <div style="font-size:0.68rem; color:#059669; font-weight:800;">
                                            Profit: +PKR <?php echo number_format($profit); ?>
                                        </div>
                                    </td>

                                    <!-- Stock -->
                                    <td style="padding:10px 8px; text-align:center;">
                                        <span style="background:<?php echo $stkBg; ?>; color:<?php echo $stkColor; ?>; font-weight:900; padding:2px 8px; border-radius:12px; font-size:0.75rem;">
                                            <?php echo $stk; ?> pcs
                                        </span>
                                    </td>

                                    <!-- Actions -->
                                    <td style="padding:10px 8px; text-align:center;">
                                        <div style="display:flex; justify-content:center; gap:4px;">
                                            <button type="button" class="pos-btn pos-btn-sm" style="padding:3px 6px; font-size:0.75rem; background:#f1f5f9; color:#334155;" title="Edit Part & Price" onclick="editPart('<?php echo htmlspecialchars($p['id'], ENT_QUOTES); ?>')">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                            <button type="button" class="pos-btn pos-btn-sm" style="padding:3px 6px; font-size:0.75rem; background:#fee2e2; color:#dc2626;" title="Delete Part" onclick="deletePart('<?php echo $p['id']; ?>')">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Modal: Add / Edit Spare Part -->
<div class="pos-modal-overlay" id="partModal" style="display:none; z-index:99999;">
    <div class="pos-modal" style="max-width:540px; padding:20px; max-height:92vh; overflow-y:auto;">
        <div class="pos-modal-header" style="border-bottom:1.5px solid var(--pos-border); padding-bottom:10px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center;">
            <h3 class="pos-modal-title" style="margin:0; font-size:1.1rem; color:#0f172a; display:flex; align-items:center; gap:8px;" id="partModalTitle">
                <span style="background:#fee2e2; color:#dc2626; width:30px; height:30px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; font-size:0.9rem;">
                    <i class="fa-solid fa-microchip"></i>
                </span>
                <span>Add New Mobile Spare Part & Price</span>
            </h3>
            <button class="pos-modal-close" onclick="closePartModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form id="partForm" onsubmit="handlePartFormSubmit(event)">
            <input type="hidden" id="partAction" value="create">
            <input type="hidden" id="partId" value="">

            <!-- Category & Brand -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:10px;">
                <div>
                    <label style="font-size:0.74rem; font-weight:800; color:#0f172a; display:block; margin-bottom:3px;">
                        Part Category *
                    </label>
                    <select id="modalPartCategory" class="form-select" style="padding:6px 8px; font-size:0.82rem;" required>
                        <option value="Screen & Display">Screen & Display (LCD/OLED)</option>
                        <option value="Batteries">Replacement Battery</option>
                        <option value="Charging & Ports">Charging Port & Flex PCB</option>
                        <option value="Cameras & Lenses">Camera & Lens Glass</option>
                        <option value="Housing & Glass">Back Cover / Housing Glass</option>
                        <option value="Motherboard & IC Chips">Motherboard & IC Chips</option>
                        <option value="Audio & Speakers">Earpiece / Loudspeaker</option>
                        <option value="Other Parts">Other Hardware / Flex</option>
                    </select>
                </div>

                <div>
                    <label style="font-size:0.74rem; font-weight:800; color:#0f172a; display:block; margin-bottom:3px;">
                        Device Brand *
                    </label>
                    <select id="modalPartBrand" class="form-select" style="padding:6px 8px; font-size:0.82rem;" required>
                        <option value="Samsung">Samsung</option>
                        <option value="Apple iPhone">Apple iPhone</option>
                        <option value="Infinix">Infinix</option>
                        <option value="Tecno">Tecno</option>
                        <option value="Xiaomi / Redmi">Xiaomi / Redmi</option>
                        <option value="Vivo">Vivo</option>
                        <option value="Realme">Realme</option>
                        <option value="Oppo">Oppo</option>
                        <option value="Universal / All">Universal / All Models</option>
                    </select>
                </div>
            </div>

            <!-- Compatible Model & Part Name -->
            <div style="margin-bottom:10px;">
                <label style="font-size:0.74rem; font-weight:800; color:#0f172a; display:block; margin-bottom:3px;">
                    Compatible Mobile Model *
                </label>
                <input type="text" id="modalPartModel" class="form-input" style="padding:6px 10px; font-size:0.82rem;" required placeholder="e.g. Galaxy A54 5G / iPhone 13 Pro / Spark 10">
            </div>

            <div style="margin-bottom:10px;">
                <label style="font-size:0.74rem; font-weight:800; color:#0f172a; display:block; margin-bottom:3px;">
                    Part Name / Description *
                </label>
                <input type="text" id="modalPartName" class="form-input" style="padding:6px 10px; font-size:0.82rem;" required placeholder="e.g. 120Hz Super AMOLED OLED Display with Frame">
            </div>

            <!-- Cost Price, Selling Price & In-Stock -->
            <div style="background:#fffdf5; border:1.5px solid #fde68a; border-radius:8px; padding:10px 12px; margin-bottom:12px;">
                <div style="font-size:0.75rem; font-weight:900; color:#92400e; text-transform:uppercase; margin-bottom:6px;">
                    <i class="fa-solid fa-coins"></i> Pricing & Stock Quantities
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr 0.8fr; gap:8px;">
                    <div>
                        <label style="font-size:0.7rem; font-weight:800; color:#64748b; display:block; margin-bottom:2px;">
                            Shop Cost (PKR)
                        </label>
                        <input type="number" id="modalPartCost" class="form-input" style="padding:5px 8px; font-size:0.85rem; font-weight:800; text-align:right;" placeholder="3500" oninput="calcModalProfit()">
                    </div>

                    <div>
                        <label style="font-size:0.7rem; font-weight:800; color:#059669; display:block; margin-bottom:2px;">
                            Repair Price (PKR) *
                        </label>
                        <input type="number" id="modalPartSelling" class="form-input" style="padding:5px 8px; font-size:0.85rem; font-weight:900; color:#059669; text-align:right;" required placeholder="5500" oninput="calcModalProfit()">
                    </div>

                    <div>
                        <label style="font-size:0.7rem; font-weight:800; color:#0f172a; display:block; margin-bottom:2px;">
                            Stock (Pcs) *
                        </label>
                        <input type="number" id="modalPartStock" class="form-input" style="padding:5px 8px; font-size:0.85rem; font-weight:800; text-align:center;" required placeholder="5" value="5">
                    </div>
                </div>

                <div id="modalProfitBadge" style="font-size:0.75rem; color:#059669; font-weight:800; margin-top:6px; text-align:right;">
                    Estimated Profit: +PKR 0 (0%)
                </div>
            </div>

            <!-- Warranty & Extra Notes -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:14px;">
                <div>
                    <label style="font-size:0.74rem; font-weight:800; color:#0f172a; display:block; margin-bottom:3px;">
                        Warranty Provided
                    </label>
                    <select id="modalPartWarranty" class="form-select" style="padding:6px 8px; font-size:0.82rem;">
                        <option value="7 Days Touch Checking Warranty">7 Days Touch Checking Warranty</option>
                        <option value="15 Days Replacement Warranty">15 Days Replacement Warranty</option>
                        <option value="1 Month Replacement Warranty">1 Month Replacement Warranty</option>
                        <option value="3 Months Service Warranty">3 Months Service Warranty</option>
                        <option value="Testing on Bench Only">Testing on Bench Only</option>
                        <option value="No Warranty">No Warranty</option>
                    </select>
                </div>

                <div>
                    <label style="font-size:0.74rem; font-weight:800; color:#0f172a; display:block; margin-bottom:3px;">
                        Technician Specs / Notes
                    </label>
                    <input type="text" id="modalPartNotes" class="form-input" style="padding:6px 10px; font-size:0.82rem;" placeholder="e.g. Original Service Pack Grade A+">
                </div>
            </div>

            <!-- Form Action Buttons -->
            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="pos-btn pos-btn-outline" onclick="closePartModal()">Cancel</button>
                <button type="submit" class="pos-btn pos-btn-primary" style="font-weight:800;" id="btnSubmitPart">
                    <i class="fa-solid fa-check"></i> Save Spare Part
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function filterPartsTable() {
        const q = (document.getElementById('partSearchInput').value || '').toLowerCase().trim();
        const brand = (document.getElementById('partBrandFilter').value || 'all').toLowerCase();
        const cat = (document.getElementById('partCategoryFilter').value || 'all').toLowerCase();

        const rows = document.querySelectorAll('.part-row');
        rows.forEach(function(row) {
            const rowBrand = row.getAttribute('data-brand') || '';
            const rowCat = row.getAttribute('data-cat') || '';
            const rowName = row.getAttribute('data-name') || '';
            const rowModel = row.getAttribute('data-model') || '';
            const rowId = row.getAttribute('data-id') || '';

            const matchBrand = (brand === 'all' || rowBrand === brand || rowBrand.includes('universal'));
            const matchCat = (cat === 'all' || rowCat === cat);
            const matchQuery = !q || rowName.includes(q) || rowModel.includes(q) || rowId.toLowerCase().includes(q) || rowBrand.includes(q);

            if (matchBrand && matchCat && matchQuery) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    function openPartModal() {
        const modal = document.getElementById('partModal');
        const form = document.getElementById('partForm');
        if (!modal || !form) return;
        form.reset();
        document.getElementById('partAction').value = 'create';
        document.getElementById('partId').value = '';
        document.getElementById('partModalTitle').innerHTML = '<span style="background:#fee2e2; color:#dc2626; width:30px; height:30px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; font-size:0.9rem;"><i class="fa-solid fa-microchip"></i></span> <span>Add New Mobile Spare Part & Price</span>';
        calcModalProfit();
        modal.style.display = 'flex';
    }

    function closePartModal() {
        const modal = document.getElementById('partModal');
        if (modal) modal.style.display = 'none';
    }

    window.allPartsList = <?php echo json_encode($parts); ?> || [];

    function editPart(part) {
        if (typeof part === 'string') {
            part = (window.allPartsList || []).find(p => String(p.id) === String(part)) || null;
        }
        if (!part) return;
        openPartModal();
        document.getElementById('partAction').value = 'update';
        document.getElementById('partId').value = part.id;
        document.getElementById('modalPartCategory').value = part.category || 'Screen & Display';
        document.getElementById('modalPartBrand').value = part.deviceBrand || 'Samsung';
        document.getElementById('modalPartModel').value = part.deviceModel || '';
        document.getElementById('modalPartName').value = part.name || '';
        document.getElementById('modalPartCost').value = part.costPrice || '';
        document.getElementById('modalPartSelling').value = part.sellingPrice || '';
        document.getElementById('modalPartStock').value = part.stock ?? 5;
        document.getElementById('modalPartWarranty').value = part.warranty || '7 Days Touch Checking Warranty';
        document.getElementById('modalPartNotes').value = part.notes || '';
        document.getElementById('partModalTitle').innerHTML = `<span style="background:#fee2e2; color:#dc2626; width:30px; height:30px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; font-size:0.9rem;"><i class="fa-solid fa-pen-to-square"></i></span> <span>Edit Part (${part.id})</span>`;
        calcModalProfit();
    }

    function calcModalProfit() {
        const cost = parseFloat(document.getElementById('modalPartCost').value) || 0;
        const sell = parseFloat(document.getElementById('modalPartSelling').value) || 0;
        const profit = sell - cost;
        const margin = cost > 0 ? Math.round((profit / cost) * 100) : 0;
        const badge = document.getElementById('modalProfitBadge');
        if (badge) {
            badge.textContent = `Estimated Profit: +PKR ${profit.toLocaleString()} (${margin}%)`;
            badge.style.color = profit >= 0 ? '#059669' : '#dc2626';
        }
    }

    function handlePartFormSubmit(e) {
        e.preventDefault();
        const action = document.getElementById('partAction').value;
        const id = document.getElementById('partId').value;
        const payload = {
            action: action,
            id: id,
            category: document.getElementById('modalPartCategory').value,
            deviceBrand: document.getElementById('modalPartBrand').value,
            deviceModel: document.getElementById('modalPartModel').value.trim(),
            name: document.getElementById('modalPartName').value.trim(),
            costPrice: parseFloat(document.getElementById('modalPartCost').value) || 0,
            sellingPrice: parseFloat(document.getElementById('modalPartSelling').value) || 0,
            stock: parseInt(document.getElementById('modalPartStock').value) || 0,
            warranty: document.getElementById('modalPartWarranty').value,
            notes: document.getElementById('modalPartNotes').value.trim()
        };

        fetch('../backend/repair_parts.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.status === 'success') {
                alert(res.message || 'Saved successfully!');
                window.location.reload();
            } else {
                alert('Error: ' + (res.message || 'Failed to save part.'));
            }
        })
        .catch(function(err) {
            alert('Server error saving spare part: ' + err);
        });
    }

    function deletePart(partId) {
        if (!confirm(`Are you sure you want to delete spare part ${partId}?`)) return;
        fetch('../backend/repair_parts.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id: partId })
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.status === 'success') {
                alert('Part deleted successfully.');
                window.location.reload();
            } else {
                alert('Error: ' + res.message);
            }
        });
    }

    // Auto-filter spare parts if URL has ?search=... or ?id=...
    window.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const search = urlParams.get('search') || urlParams.get('id') || urlParams.get('q');
        if (search && document.getElementById('partSearchInput')) {
            document.getElementById('partSearchInput').value = search;
            filterPartsTable();
        }
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
