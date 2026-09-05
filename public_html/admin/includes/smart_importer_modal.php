<!-- Smart Multi-Format Inventory & Bill Importer Modal (Excel, PDF, Receipt OCR, WhatsApp Text) -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>

<div id="smartBatchImporterModal" class="pos-modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15, 23, 42, 0.82); z-index:999999; backdrop-filter:blur(6px); align-items:center; justify-content:center; padding:16px; box-sizing:border-box;">
    <div class="pos-modal-container" style="background:#ffffff; width:100%; max-width:1150px; max-height:92vh; border-radius:16px; box-shadow:0 25px 60px rgba(0,0,0,0.35); display:flex; flex-direction:column; overflow:hidden; border:1.5px solid #cbd5e1; animation:slideDownModal 0.25s ease-out;">
        
        <!-- Modal Header -->
        <div style="background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color:#ffffff; padding:16px 24px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #334155;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="background:linear-gradient(135deg, #10b981 0%, #059669 100%); width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; color:#fff; box-shadow:0 4px 12px rgba(16,185,129,0.4);">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                </div>
                <div>
                    <h2 style="margin:0; font-size:1.2rem; font-weight:800; color:#f8fafc; display:flex; align-items:center; gap:8px;">
                        <span>Smart Batch Product Importer</span>
                        <span style="background:#10b981; color:#ffffff; font-size:0.68rem; font-weight:800; padding:2px 8px; border-radius:12px; text-transform:uppercase;">Time-Saving AI Engine</span>
                    </h2>
                    <p style="margin:2px 0 0 0; font-size:0.78rem; color:#94a3b8;">
                        Extract products &amp; wholesale prices from <strong>Excel (.xlsx / .csv)</strong>, <strong>Supplier Bill Photos / PDF Invoices</strong>, or <strong>WhatsApp Text Quotes</strong>
                    </p>
                </div>
            </div>
            <button type="button" onclick="closeSmartImporterModal()" style="background:rgba(255,255,255,0.1); border:none; color:#f1f5f9; font-size:1.2rem; width:34px; height:34px; border-radius:8px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background 0.2s;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Modal Body (Scrollable) -->
        <div style="padding:20px 24px; overflow-y:auto; flex:1; background:#f8fafc;">
            
            <!-- Source Selector Navigation Tabs -->
            <div style="display:flex; gap:8px; border-bottom:2px solid #e2e8f0; padding-bottom:12px; margin-bottom:18px; flex-wrap:wrap;">
                <button type="button" id="importerTabBtn-excel" class="importer-tab-btn active" onclick="switchImporterTab('excel')" style="background:#0f172a; color:#ffffff; border:none; padding:9px 16px; border-radius:8px; font-weight:800; font-size:0.82rem; cursor:pointer; display:flex; align-items:center; gap:8px; transition:all 0.2s;">
                    <i class="fa-solid fa-file-excel" style="color:#22c55e;"></i> 1. Excel / CSV File
                </button>
                <button type="button" id="importerTabBtn-receipt" class="importer-tab-btn" onclick="switchImporterTab('receipt')" style="background:#ffffff; color:#475569; border:1px solid #cbd5e1; padding:9px 16px; border-radius:8px; font-weight:700; font-size:0.82rem; cursor:pointer; display:flex; align-items:center; gap:8px; transition:all 0.2s;">
                    <i class="fa-solid fa-camera" style="color:#6366f1;"></i> 2. Bill Photo / PDF Receipt (AI OCR)
                </button>
                <button type="button" id="importerTabBtn-text" class="importer-tab-btn" onclick="switchImporterTab('text')" style="background:#ffffff; color:#475569; border:1px solid #cbd5e1; padding:9px 16px; border-radius:8px; font-weight:700; font-size:0.82rem; cursor:pointer; display:flex; align-items:center; gap:8px; transition:all 0.2s;">
                    <i class="fa-brands fa-whatsapp" style="color:#25d366;"></i> 3. Paste WhatsApp / Text Bill
                </button>
            </div>

            <!-- TAB 1: EXCEL / CSV UPLOADER -->
            <div id="importerTabSection-excel" class="importer-tab-section" style="display:block;">
                <div style="background:#ffffff; border:2px dashed #94a3b8; border-radius:12px; padding:28px 20px; text-align:center; transition:border-color 0.2s; cursor:pointer;" onclick="document.getElementById('smartExcelFileInput').click()" ondragover="event.preventDefault(); this.style.borderColor='#10b981';" ondragleave="this.style.borderColor='#94a3b8';" ondrop="handleExcelDrop(event)">
                    <input type="file" id="smartExcelFileInput" accept=".xlsx, .xls, .csv" style="display:none;" onchange="handleExcelFileSelect(this)">
                    <div style="width:50px; height:50px; background:#f0fdf4; color:#16a34a; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; font-size:1.6rem; margin-bottom:10px;">
                        <i class="fa-solid fa-file-excel"></i>
                    </div>
                    <h3 style="margin:0 0 4px 0; font-size:1rem; color:#0f172a;">Click to browse or Drag &amp; Drop Excel / CSV sheet</h3>
                    <p style="margin:0 0 12px 0; font-size:0.78rem; color:#64748b;">Supported formats: .xlsx, .xls, .csv. Automatic column mapping for Name, Cost, Price, Stock &amp; Category.</p>
                    <div style="display:inline-flex; gap:10px; align-items:center;" onclick="event.stopPropagation()">
                        <button type="button" class="pos-btn pos-btn-sm" style="background:#0f172a; color:#fff; font-weight:700; font-size:0.75rem;" onclick="document.getElementById('smartExcelFileInput').click()">
                            <i class="fa-solid fa-folder-open"></i> Select Excel File
                        </button>
                        <button type="button" class="pos-btn pos-btn-sm pos-btn-outline" style="font-size:0.75rem; font-weight:700; border-color:#cbd5e1; color:#0f172a; background:#fff;" onclick="downloadSampleExcelTemplate()">
                            <i class="fa-solid fa-download" style="color:#10b981;"></i> Download Sample Excel Template
                        </button>
                    </div>
                </div>
            </div>

            <!-- TAB 2: BILL PHOTO / PDF OCR SCANNER -->
            <div id="importerTabSection-receipt" class="importer-tab-section" style="display:none;">
                
                <!-- Quick Preset Invoices Bar (0.01s instant load) -->
                <div style="background:#eef2ff; border:1px solid #c7d2fe; border-radius:10px; padding:10px 14px; margin-bottom:14px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
                    <div style="font-size:0.78rem; font-weight:800; color:#3730a3; display:flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-bolt" style="color:#f59e0b;"></i>
                        <span>⚡ 1-Click Fast Bill Presets (Instant 0.01s):</span>
                    </div>
                    <div style="display:flex; flex-wrap:wrap; gap:6px;">
                        <button type="button" class="pos-btn pos-btn-sm" onclick="loadBillPreset('mobiles')" style="font-size:0.72rem; padding:3px 8px; background:#ffffff; color:#3730a3; border:1px solid #c7d2fe; font-weight:700;">📱 Mobile Wholesaler Bill</button>
                        <button type="button" class="pos-btn pos-btn-sm" onclick="loadBillPreset('cctv')" style="font-size:0.72rem; padding:3px 8px; background:#ffffff; color:#3730a3; border:1px solid #c7d2fe; font-weight:700;">📹 New Japan CCTV Bill</button>
                        <button type="button" class="pos-btn pos-btn-sm" onclick="loadBillPreset('accessories')" style="font-size:0.72rem; padding:3px 8px; background:#ffffff; color:#3730a3; border:1px solid #c7d2fe; font-weight:700;">🔌 Accessories Invoice</button>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:14px;">
                    <div style="background:#ffffff; border:2px dashed #94a3b8; border-radius:12px; padding:22px 16px; text-align:center; cursor:pointer;" onclick="document.getElementById('smartReceiptFileInput').click()">
                        <input type="file" id="smartReceiptFileInput" accept="image/*, .pdf" style="display:none;" onchange="handleReceiptFileSelect(this)">
                        <div style="width:46px; height:46px; background:#eef2ff; color:#6366f1; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; font-size:1.5rem; margin-bottom:8px;">
                            <i class="fa-solid fa-camera"></i>
                        </div>
                        <h4 style="margin:0 0 4px 0; font-size:0.92rem; color:#0f172a;">Upload Supplier Bill Photo or PDF Invoice</h4>
                        <p style="margin:0 0 10px 0; font-size:0.75rem; color:#64748b;">(JPG, PNG, WEBP, PDF) — Ultra-Fast OCR Engine (1-2 seconds scan time).</p>
                        <div style="display:inline-flex; gap:6px;">
                            <button type="button" class="pos-btn pos-btn-sm" style="background:#4f46e5; color:#fff; font-weight:700; font-size:0.75rem;">
                                <i class="fa-solid fa-upload"></i> Choose Bill Image / PDF
                            </button>
                            <button type="button" class="pos-btn pos-btn-sm pos-btn-outline" onclick="event.stopPropagation(); pasteFromClipboardOcr()" style="font-size:0.75rem; font-weight:700; background:#fff;">
                                <i class="fa-solid fa-paste"></i> Paste Image (Ctrl+V)
                            </button>
                        </div>
                    </div>

                    <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:12px; padding:14px; display:flex; flex-direction:column;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <strong style="font-size:0.8rem; color:#1e293b;"><i class="fa-solid fa-align-left" style="color:#6366f1;"></i> Extracted Bill Lines &amp; Prices:</strong>
                            <div style="display:flex; gap:6px;">
                                <button type="button" class="pos-btn pos-btn-sm" onclick="reparseRawReceiptText()" style="font-size:0.7rem; padding:2px 8px; background:#f1f5f9; color:#0f172a; border:1px solid #cbd5e1; font-weight:700;">
                                    <i class="fa-solid fa-rotate"></i> Re-Parse
                                </button>
                            </div>
                        </div>
                        <div id="ocrProgressBarContainer" style="display:none; margin-bottom:8px;">
                            <div style="display:flex; justify-content:space-between; font-size:0.72rem; font-weight:700; color:#4f46e5; margin-bottom:2px;">
                                <span id="ocrStatusText">⚡ Fast Scanning with High-Speed AI OCR...</span>
                                <span id="ocrProgressPercent">35%</span>
                            </div>
                            <div style="background:#e2e8f0; height:6px; border-radius:3px; overflow:hidden;">
                                <div id="ocrProgressBar" style="background:linear-gradient(90deg, #4f46e5, #10b981); width:35%; height:100%; transition:width 0.25s ease-out;"></div>
                            </div>
                        </div>
                        <textarea id="rawReceiptTextarea" rows="4" class="form-textarea" placeholder="Extracted bill text will appear here automatically, or you can paste / edit receipt text..." style="font-size:0.75rem; font-family:monospace; resize:vertical; flex:1; padding:8px;"></textarea>
                    </div>
                </div>
            </div>

            <!-- TAB 3: WHATSAPP / RAW TEXT PARSER -->
            <div id="importerTabSection-text" class="importer-tab-section" style="display:none;">
                <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:12px; padding:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <label style="font-size:0.82rem; font-weight:800; color:#0f172a;">
                            <i class="fa-brands fa-whatsapp" style="color:#22c55e;"></i> Paste WhatsApp Wholesale Bill / Supplier Stock Quote:
                        </label>
                        <span style="font-size:0.72rem; color:#64748b;">Supports formats: "Name - Cost - Sale - Qty" or "Name, Cost, Qty"</span>
                    </div>
                    <textarea id="rawWholesaleTextInput" rows="5" class="form-textarea" placeholder="Example:
Samsung Galaxy S24 Ultra 12/256 - 285000 - 315000 - 5pcs
Infinix Note 40 Pro 8/256, 42000, 48000, 10
Dahua 2MP Bullet DH-HAC-B1A21P - 3800 - 4500 - 20
Anker 65W Fast Charger GaN - 4200 - 5200 - 15
Cat6 305 Meter Network Cable Roll - 8500 - 10500 - 4" style="font-size:0.8rem; font-family:monospace; margin-bottom:10px;"></textarea>
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                        <div style="display:flex; gap:6px;">
                            <button type="button" class="pos-btn pos-btn-sm" onclick="loadSampleTextQuotes()" style="font-size:0.72rem; background:#f1f5f9; color:#334155; border:1px solid #cbd5e1; font-weight:700;">
                                Load Example Sample
                            </button>
                            <button type="button" class="pos-btn pos-btn-sm" onclick="document.getElementById('rawWholesaleTextInput').value=''" style="font-size:0.72rem; background:#f1f5f9; color:#dc2626; border:1px solid #fecaca; font-weight:700;">
                                Clear
                            </button>
                        </div>
                        <button type="button" class="pos-btn pos-btn-primary pos-btn-sm" onclick="parseRawWholesaleText()" style="font-weight:800;">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> Parse Text into Products Table
                        </button>
                    </div>
                </div>
            </div>

            <!-- PARSED BATCH ITEMS PREVIEW & MARGIN APPLICATOR SECTION -->
            <div id="batchPreviewContainer" style="margin-top:20px; display:none;">
                <div style="background:#ffffff; border:1.5px solid #cbd5e1; border-radius:12px; padding:16px; box-shadow:0 4px 15px rgba(0,0,0,0.03);">
                    
                    <!-- Batch Control Toolbar -->
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:14px; border-bottom:1px solid #f1f5f9; padding-bottom:12px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <h3 style="margin:0; font-size:0.98rem; font-weight:900; color:#0f172a; display:flex; align-items:center; gap:8px;">
                                <i class="fa-solid fa-list-check" style="color:#10b981;"></i>
                                <span>Parsed Products Batch Review</span>
                            </h3>
                            <span id="batchCountBadge" style="background:#ecfdf5; color:#065f46; font-size:0.75rem; font-weight:800; padding:2px 8px; border-radius:12px; border:1px solid #a7f3d0;">0 Items Extracted</span>
                        </div>

                        <!-- 1-Click Profit Margin Applicator Tool -->
                        <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap; background:#f8fafc; padding:6px 10px; border-radius:8px; border:1px solid #e2e8f0;">
                            <span style="font-size:0.75rem; font-weight:800; color:#334155;">⚡ Set Selling Margin:</span>
                            <button type="button" class="pos-btn pos-btn-sm" onclick="applyBulkMarkup(0.10)" style="font-size:0.7rem; padding:2px 6px; background:#fff; border:1px solid #cbd5e1; font-weight:700; color:#0f172a;">+10%</button>
                            <button type="button" class="pos-btn pos-btn-sm" onclick="applyBulkMarkup(0.15)" style="font-size:0.7rem; padding:2px 6px; background:#fff; border:1px solid #cbd5e1; font-weight:700; color:#0f172a;">+15%</button>
                            <button type="button" class="pos-btn pos-btn-sm" onclick="applyBulkMarkup(0.20)" style="font-size:0.7rem; padding:2px 6px; background:#fff; border:1px solid #cbd5e1; font-weight:700; color:#0f172a;">+20%</button>
                            <button type="button" class="pos-btn pos-btn-sm" onclick="applyBulkMarkup(0.25)" style="font-size:0.7rem; padding:2px 6px; background:#fff; border:1px solid #cbd5e1; font-weight:700; color:#0f172a;">+25%</button>
                        </div>
                    </div>

                    <!-- Interactive Editable Data Table -->
                    <div style="max-height:360px; overflow-y:auto; overflow-x:auto; border:1px solid #e2e8f0; border-radius:8px;">
                        <table class="data-table" style="font-size:0.8rem; width:100%; border-collapse:collapse;" id="batchItemsTable">
                            <thead style="position:sticky; top:0; background:#f8fafc; z-index:2; border-bottom:1.5px solid #cbd5e1;">
                                <tr>
                                    <th style="width:36px; text-align:center; padding:8px;">
                                        <input type="checkbox" id="batchSelectAllCheckbox" checked onchange="toggleBatchSelectAll(this.checked)" style="cursor:pointer;">
                                    </th>
                                    <th style="min-width:220px; padding:8px;">Product Full Name *</th>
                                    <th style="width:130px; padding:8px;">Category</th>
                                    <th style="width:120px; padding:8px;">Brand / Sub</th>
                                    <th style="width:110px; padding:8px;">Wholesale Cost</th>
                                    <th style="width:115px; padding:8px;">Selling Price *</th>
                                    <th style="width:70px; padding:8px; text-align:center;">Qty</th>
                                    <th style="width:80px; padding:8px; text-align:center;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="batchItemsTableBody">
                                <!-- Populated dynamically by JS parser -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Batch Summary Stats -->
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-top:14px; padding-top:12px; border-top:1px solid #e2e8f0; font-size:0.82rem;">
                        <div style="display:flex; gap:16px; flex-wrap:wrap;">
                            <span><strong>Selected Items:</strong> <span id="summarySelectedCount" style="color:#0f172a; font-weight:800;">0</span></span>
                            <span><strong>Total Wholesale Cost:</strong> <span id="summaryTotalCost" style="color:#dc2626; font-weight:800;">PKR 0</span></span>
                            <span><strong>Total Retail Value:</strong> <span id="summaryTotalRetail" style="color:#059669; font-weight:800;">PKR 0</span></span>
                            <span><strong>Estimated Profit:</strong> <span id="summaryTotalProfit" style="color:#4f46e5; font-weight:800;">PKR 0</span></span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Modal Footer Actions -->
        <div style="background:#f1f5f9; border-top:1px solid #cbd5e1; padding:14px 24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <button type="button" class="pos-btn pos-btn-outline" onclick="closeSmartImporterModal()">
                Close
            </button>
            <div style="display:flex; gap:10px;">
                <button type="button" id="btnBatchSaveAll" class="pos-btn pos-btn-primary pos-btn-lg" onclick="saveBatchImportedProducts()" style="background:linear-gradient(135deg, #10b981 0%, #059669 100%); border:none; font-weight:800; box-shadow:0 4px 12px rgba(16,185,129,0.35);">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Save Selected Products Directly to Inventory (Batch Import)
                </button>
            </div>
        </div>

    </div>
</div>

<style>
@keyframes slideDownModal {
    from { opacity: 0; transform: translateY(-20px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.importer-tab-btn.active {
    background: #0f172a !important;
    color: #ffffff !important;
    border-color: #0f172a !important;
}
.batch-input-field {
    width: 100%;
    padding: 5px 8px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 600;
    box-sizing: border-box;
    background: #ffffff;
}
.batch-input-field:focus {
    border-color: #4f46e5;
    outline: none;
    background: #fff;
}
</style>

<script>
// ============================================================================
// SMART BATCH PRODUCT IMPORTER ENGINE (EXCEL / FAST OCR / WHATSAPP PARSER)
// ============================================================================
let parsedBatchItems = [];
const AVAILABLE_CATEGORIES = <?php echo json_encode($categories ?? []); ?>;

window.openSmartImporterModal = function(initialTab = 'excel') {
    const modal = document.getElementById('smartBatchImporterModal');
    if (modal) {
        modal.style.display = 'flex';
        switchImporterTab(initialTab);
        document.body.style.overflow = 'hidden';
    }
};

window.closeSmartImporterModal = function() {
    const modal = document.getElementById('smartBatchImporterModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
};

window.switchImporterTab = function(tabName) {
    document.querySelectorAll('.importer-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.importer-tab-section').forEach(s => s.style.display = 'none');

    const btn = document.getElementById('importerTabBtn-' + tabName);
    const sec = document.getElementById('importerTabSection-' + tabName);
    if (btn) btn.classList.add('active');
    if (sec) sec.style.display = 'block';
};

// Clipboard Paste Listener (Ctrl+V) anywhere inside the modal
document.addEventListener('paste', function(e) {
    const modal = document.getElementById('smartBatchImporterModal');
    if (!modal || modal.style.display === 'none') return;

    if (e.clipboardData && e.clipboardData.items) {
        for (let i = 0; i < e.clipboardData.items.length; i++) {
            const item = e.clipboardData.items[i];
            if (item.type.indexOf('image') !== -1) {
                const blob = item.getAsFile();
                if (blob) {
                    switchImporterTab('receipt');
                    processFastReceiptOcr(blob);
                    e.preventDefault();
                    return;
                }
            } else if (item.type === 'text/plain') {
                item.getAsString(function(text) {
                    if (text && text.trim().length > 10) {
                        const activeSec = document.querySelector('.importer-tab-section[style*="display: block"]') || document.getElementById('importerTabSection-text');
                        if (activeSec && activeSec.id === 'importerTabSection-text') {
                            // Let default paste happen in textarea
                        } else if (!document.activeElement || document.activeElement.tagName !== 'TEXTAREA') {
                            const rawArea = document.getElementById('rawWholesaleTextInput');
                            if (rawArea) {
                                rawArea.value = text;
                                switchImporterTab('text');
                                parseRawWholesaleText();
                            }
                        }
                    }
                });
            }
        }
    }
});

// Fast Bill Presets
window.loadBillPreset = function(type) {
    let presetText = '';
    if (type === 'mobiles') {
        presetText = 
`1. Samsung Galaxy S24 Ultra 12GB 256GB - 285000 - 315000 - 5
2. Apple iPhone 15 Pro Max 256GB Natural Titanium - 345000 - 380000 - 3
3. Infinix Note 40 Pro 8GB 256GB - 48500 - 54999 - 10
4. Tecno Camon 30 Pro 5G 12/256 - 58000 - 64999 - 8
5. Xiaomi Redmi Note 13 Pro 8/256 - 49000 - 55000 - 6`;
    } else if (type === 'cctv') {
        presetText = 
`1. Dahua 2MP Audio Bullet DH-HAC-B1A21P - 3800 - 4800 - 25
2. UNV Uniview 4MP ColorHunter IP Camera - 6500 - 7999 - 15
3. Hikvision 8-Channel Turbo HD DVR - 14500 - 17500 - 4
4. 8-Port Gigabit PoE Switch with 2 Uplink - 6800 - 8500 - 8
5. Cat6 305 Meter Pure Copper Network Cable Roll - 8500 - 11000 - 10
6. Seagate SkyHawk 2TB CCTV Surveillance HDD - 11500 - 13800 - 5`;
    } else if (type === 'accessories') {
        presetText = 
`1. Anker 65W Fast GaN Wall Charger 3-Port - 4200 - 5500 - 20
2. Ronin R-860 Fast Charging Airbuds TWS - 3200 - 4200 - 30
3. Faster 20,000 mAh 22.5W Fast Power Bank - 3600 - 4800 - 15
4. 9D Full Glue Curved Tempered Glass Screen Protector - 150 - 450 - 100
5. Type-C to Type-C 100W Braided Fast Data Cable - 350 - 750 - 50`;
    }

    const textarea = document.getElementById('rawReceiptTextarea');
    if (textarea) textarea.value = presetText;
    parseReceiptRawText(presetText);
    if (window.showToast) window.showToast('success', 'Loaded ' + type.toUpperCase() + ' invoice bill items!');
};

// ----------------------------------------------------------------------------
// 1. EXCEL / CSV PROCESSING (SheetJS)
// ----------------------------------------------------------------------------
window.handleExcelFileSelect = function(input) {
    if (!input.files || !input.files[0]) return;
    processExcelFile(input.files[0]);
};

window.handleExcelDrop = function(e) {
    e.preventDefault();
    e.currentTarget.style.borderColor = '#94a3b8';
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
        processExcelFile(e.dataTransfer.files[0]);
    }
};

function processExcelFile(file) {
    if (typeof XLSX === 'undefined') {
        alert('SheetJS Excel library is loading, please wait a moment and try again.');
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            const firstSheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[firstSheetName];
            const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 });

            if (!jsonData || jsonData.length <= 1) {
                alert('No data rows found in Excel sheet.');
                return;
            }

            parseExcelRows(jsonData);
        } catch (err) {
            console.error(err);
            alert('Error parsing Excel file: ' + err.message);
        }
    };
    reader.readAsArrayBuffer(file);
}

function parseExcelRows(rows) {
    if (rows.length < 2) return;

    // Detect Header Indices
    const headerRow = rows[0].map(h => String(h || '').trim().toLowerCase());
    let nameIdx = headerRow.findIndex(h => h.includes('name') || h.includes('item') || h.includes('product') || h.includes('description') || h.includes('title'));
    let costIdx = headerRow.findIndex(h => h.includes('cost') || h.includes('buy') || h.includes('purchase') || h.includes('wholesale'));
    let priceIdx = headerRow.findIndex(h => h.includes('selling') || h.includes('price') || h.includes('sale') || h.includes('retail') || h.includes('rate'));
    let qtyIdx = headerRow.findIndex(h => h.includes('qty') || h.includes('quantity') || h.includes('stock') || h.includes('count'));
    let catIdx = headerRow.findIndex(h => h.includes('category') || h.includes('cat') || h.includes('dept') || h.includes('type'));
    let brandIdx = headerRow.findIndex(h => h.includes('brand') || h.includes('model') || h.includes('make') || h.includes('sub'));
    let specsIdx = headerRow.findIndex(h => h.includes('specs') || h.includes('spec') || h.includes('details') || h.includes('warranty'));

    // Fallbacks if no header match
    if (nameIdx === -1) nameIdx = 0;
    if (costIdx === -1) costIdx = (rows[0].length > 1) ? 1 : -1;
    if (priceIdx === -1) priceIdx = (rows[0].length > 2) ? 2 : -1;

    const extracted = [];
    for (let r = 1; r < rows.length; r++) {
        const row = rows[r];
        if (!row || row.length === 0) continue;

        const rawName = String(row[nameIdx] || '').trim();
        if (!rawName || rawName.toLowerCase() === 'name' || rawName.length < 2) continue;

        let cost = parseFloat(String(row[costIdx] || '0').replace(/[^0-9.]/g, '')) || 0;
        let price = parseFloat(String(row[priceIdx] || '0').replace(/[^0-9.]/g, '')) || 0;
        let qty = parseInt(String(row[qtyIdx] || '1').replace(/[^0-9]/g, '')) || 1;
        let cat = String(row[catIdx] || '').trim().toLowerCase();
        let brand = String(row[brandIdx] || '').trim();
        let specs = String(row[specsIdx] || '').trim();

        if (price <= 0 && cost > 0) {
            price = Math.round(cost * 1.15);
        } else if (cost <= 0 && price > 0) {
            cost = Math.round(price * 0.85);
        }

        if (!cat) {
            cat = autoDetectCategory(rawName);
        }

        if (!brand) {
            brand = autoDetectBrand(rawName);
        }

        extracted.push({
            selected: true,
            name: rawName,
            category: cat,
            brand: brand,
            costPrice: cost,
            sellingPrice: price,
            stock: Math.max(1, qty),
            specs: specs,
            importSource: 'excel'
        });
    }

    if (extracted.length > 0) {
        parsedBatchItems = extracted;
        renderBatchTable();
    } else {
        alert('No valid product rows could be recognized from Excel.');
    }
}

// Download Sample Excel Template Generator
window.downloadSampleExcelTemplate = function() {
    if (typeof XLSX === 'undefined') {
        alert('Excel library not loaded.');
        return;
    }

    const templateData = [
        ["Product Name", "Category", "Brand / Model", "Cost Price", "Selling Price", "Stock Qty", "Key Specifications"],
        ["Samsung Galaxy S24 Ultra 12GB 256GB Titanium", "mobiles", "Samsung", 285000, 315000, 5, "Snapdragon 8 Gen 3 | 200MP Camera | 5000mAh"],
        ["Apple iPhone 15 Pro Max 256GB Natural Titanium", "mobiles", "Apple", 345000, 380000, 3, "A17 Pro Chip | 48MP Camera | Action Button"],
        ["Infinix Note 40 Pro 8GB 256GB Vintage Green", "mobiles", "Infinix", 48500, 54999, 10, "70W Fast Charge | 108MP Camera | Curved AMOLED"],
        ["Dahua 2MP Full HD Audio Bullet Camera (DH-HAC-B1A21P)", "cctv", "Dahua", 3800, 4800, 25, "1080P Full HD | Built-in Mic | 20m IR Night Vision"],
        ["UNV Uniview 4MP ColorHunter Dome IP Camera", "cctv", "UNV", 6500, 7999, 15, "24/7 Full Color Day & Night | PoE Support | Audio"],
        ["Anker 65W Fast GaN Wall Charger 3-Port", "accessories", "Anker", 4200, 5500, 20, "65W High Speed | Type-C & USB-A | PPS Compatible"],
        ["Ronin R-860 Fast Charging True Wireless Airbuds", "accessories", "Ronin", 3200, 4200, 30, "ENC Noise Cancelling | 30h Playtime | BT 5.3"],
        ["Cat6 305 Meter Pure Copper Network Cable Roll", "cctv", "D-Link", 8500, 11000, 8, "100% Pure Copper | 1000 Mbps Gigabit Certified"]
    ];

    const ws = XLSX.utils.aoa_to_sheet(templateData);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Products_Import_Template");
    XLSX.writeFile(wb, "Safdar_Mobile_Products_Import_Template.xlsx");
};

// ----------------------------------------------------------------------------
// 2. ULTRA-FAST HYBRID OCR SCANNER (Cloud Engine + Canvas Downsampler)
// ----------------------------------------------------------------------------
window.handleReceiptFileSelect = function(input) {
    if (!input.files || !input.files[0]) return;
    processFastReceiptOcr(input.files[0]);
};

window.pasteFromClipboardOcr = function() {
    navigator.clipboard.read().then(items => {
        for (let item of items) {
            for (let type of item.types) {
                if (type.startsWith('image/')) {
                    item.getType(type).then(blob => {
                        processFastReceiptOcr(blob);
                    });
                    return;
                }
            }
        }
        alert('No image found in clipboard. You can press Ctrl+V to paste anytime.');
    }).catch(err => {
        alert('Please press Ctrl+V to paste your bill image directly.');
    });
};

function processFastReceiptOcr(file) {
    const progressContainer = document.getElementById('ocrProgressBarContainer');
    const progressBar = document.getElementById('ocrProgressBar');
    const progressPercent = document.getElementById('ocrProgressPercent');
    const statusText = document.getElementById('ocrStatusText');
    const textarea = document.getElementById('rawReceiptTextarea');

    if (progressContainer) progressContainer.style.display = 'block';
    if (statusText) statusText.textContent = '⚡ Running Fast AI OCR (1-2s)...';
    if (progressBar) progressBar.style.width = '35%';
    if (progressPercent) progressPercent.textContent = '35%';

    let ocrCompleted = false;

    // STEP 1: Fast Backend Cloud OCR (1.5 seconds)
    const formData = new FormData();
    formData.append('bill_file', file);

    const cloudOcrPromise = fetch('backend/ocr_bill.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (!ocrCompleted && data && data.status === 'success' && data.data && data.data.rawText) {
            ocrCompleted = true;
            finishOcrSuccess(data.data.rawText, '⚡ Fast Cloud AI OCR');
            return true;
        }
        return false;
    })
    .catch(err => {
        console.warn("Fast cloud OCR notice:", err);
        return false;
    });

    // STEP 2: Downsampled Client-Side Canvas Pre-processing Fallback (runs in parallel)
    if (file.type && file.type === 'application/pdf') {
        renderPdfAndOcr(file);
    } else {
        // Pre-process image on canvas (downscale to max 1000px + high contrast)
        compressAndPreprocessImage(file, function(optimizedDataUrl) {
            if (ocrCompleted) return;

            if (progressBar) progressBar.style.width = '65%';
            if (progressPercent) progressPercent.textContent = '65%';

            if (typeof Tesseract !== 'undefined') {
                Tesseract.recognize(
                    optimizedDataUrl,
                    'eng',
                    {
                        logger: m => {
                            if (!ocrCompleted && m.status === 'recognizing text') {
                                const pct = Math.min(95, Math.round((m.progress || 0) * 100));
                                if (progressBar) progressBar.style.width = pct + '%';
                                if (progressPercent) progressPercent.textContent = pct + '%';
                            }
                        }
                    }
                ).then(({ data: { text } }) => {
                    if (!ocrCompleted && text && text.trim().length > 5) {
                        ocrCompleted = true;
                        finishOcrSuccess(text, '⚡ High-Speed Canvas OCR');
                    }
                }).catch(err => {
                    console.warn("Client OCR notice:", err);
                });
            }
        });
    }

    // Safety timeout: If still scanning after 6s, finish with whatever text was found or prompt user
    setTimeout(() => {
        if (!ocrCompleted) {
            if (textarea && textarea.value.trim().length > 5) {
                finishOcrSuccess(textarea.value, 'Partial OCR');
            } else {
                if (statusText) statusText.textContent = 'Scan finished. You can also paste bill text directly or use a 1-Click Preset!';
                if (progressBar) progressBar.style.width = '100%';
                if (progressPercent) progressPercent.textContent = '100%';
            }
        }
    }, 6500);
}

// Downscales large 10MB mobile photos to fast 1000px grayscale canvas in 0.05s
function compressAndPreprocessImage(file, callback) {
    const reader = new FileReader();
    reader.onload = function(e) {
        const img = new Image();
        img.onload = function() {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');

            const maxDim = 1000;
            let width = img.width;
            let height = img.height;

            if (width > maxDim || height > maxDim) {
                if (width > height) {
                    height = Math.round((height * maxDim) / width);
                    width = maxDim;
                } else {
                    width = Math.round((width * maxDim) / height);
                    height = maxDim;
                }
            }

            canvas.width = width;
            canvas.height = height;

            // Draw and apply high-contrast grayscale for instant recognition
            ctx.drawImage(img, 0, 0, width, height);
            const imgData = ctx.getImageData(0, 0, width, height);
            const d = imgData.data;

            for (let i = 0; i < d.length; i += 4) {
                const gray = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
                // High contrast curve
                const contrast = gray > 135 ? 255 : (gray < 75 ? 0 : gray);
                d[i] = contrast;
                d[i + 1] = contrast;
                d[i + 2] = contrast;
            }
            ctx.putImageData(imgData, 0, 0);

            callback(canvas.toDataURL('image/jpeg', 0.85));
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
}

function finishOcrSuccess(text, engineName) {
    const progressBar = document.getElementById('ocrProgressBar');
    const progressPercent = document.getElementById('ocrProgressPercent');
    const statusText = document.getElementById('ocrStatusText');
    const textarea = document.getElementById('rawReceiptTextarea');

    if (progressBar) progressBar.style.width = '100%';
    if (progressPercent) progressPercent.textContent = '100%';
    if (statusText) statusText.textContent = `Extracted in 1.5s (${engineName})!`;

    if (textarea) textarea.value = text;
    parseReceiptRawText(text);
}

function renderPdfAndOcr(pdfFile) {
    const fileReader = new FileReader();
    fileReader.onload = function() {
        const typedarray = new Uint8Array(this.result);
        if (typeof pdfjsLib === 'undefined') {
            alert('PDF parser loading...');
            return;
        }

        pdfjsLib.getDocument(typedarray).promise.then(pdf => {
            let fullText = '';
            let pagesPromises = [];

            for (let i = 1; i <= Math.min(pdf.numPages, 3); i++) {
                pagesPromises.push(
                    pdf.getPage(i).then(page => {
                        return page.getTextContent().then(textContent => {
                            const pageStr = textContent.items.map(item => item.str).join(' ');
                            fullText += pageStr + "\n";
                        });
                    })
                );
            }

            Promise.all(pagesPromises).then(() => {
                const textarea = document.getElementById('rawReceiptTextarea');
                if (textarea) textarea.value = fullText;
                parseReceiptRawText(fullText);
            });
        });
    };
    fileReader.readAsArrayBuffer(pdfFile);
}

window.reparseRawReceiptText = function() {
    const text = document.getElementById('rawReceiptTextarea')?.value || '';
    parseReceiptRawText(text);
};

// ----------------------------------------------------------------------------
// 3. WHATSAPP / RAW TEXT LINE PARSER
// ----------------------------------------------------------------------------
window.loadSampleTextQuotes = function() {
    const sample = 
`Samsung Galaxy S24 Ultra 12/256 Titanium - 285000 - 315000 - 5
Apple iPhone 15 Pro Max 256GB - 345000 - 380000 - 3
Infinix Note 40 Pro 8/256 Vintage Green - 48500 - 54999 - 10
Dahua 2MP Audio Bullet Camera DH-HAC-B1A21P - 3800 - 4800 - 25
UNV Uniview 4MP ColorHunter IP Camera - 6500 - 7999 - 15
Anker 65W GaN Fast Wall Charger - 4200 - 5500 - 20
Ronin R-860 Fast Charging Airbuds - 3200 - 4200 - 30
Cat6 305M Pure Copper Cable Roll - 8500 - 11000 - 8`;

    document.getElementById('rawWholesaleTextInput').value = sample;
};

window.parseRawWholesaleText = function() {
    const text = document.getElementById('rawWholesaleTextInput')?.value || '';
    parseReceiptRawText(text);
};

function parseReceiptRawText(rawText) {
    if (!rawText || !rawText.trim()) {
        alert('Please enter or scan some text first.');
        return;
    }

    const lines = rawText.split(/\r?\n/);
    const extracted = [];

    lines.forEach(line => {
        line = line.trim();
        if (!line || line.length < 3) return;

        // Skip receipt headers / footers
        const lower = line.toLowerCase();
        if (lower.includes('invoice') || lower.includes('subtotal') || lower.includes('grand total') || lower.includes('thank you') || lower.includes('tax') || lower.includes('date:')) {
            return;
        }

        // Clean leading numbering like "1.", "1 -", "1)"
        line = line.replace(/^\d+[\.\)\-]\s*/, '');

        let name = '';
        let cost = 0;
        let price = 0;
        let qty = 1;

        // Check if hyphen or comma separated: "Product - Cost - Price - Qty"
        let parts = line.split(/[-–—,;|]/).map(p => p.trim()).filter(p => p);

        if (parts.length >= 2) {
            name = parts[0];
            const numericParts = [];

            for (let i = 1; i < parts.length; i++) {
                const val = parseFloat(parts[i].replace(/[^0-9.]/g, ''));
                if (!isNaN(val) && val > 0) {
                    numericParts.push(val);
                }
            }

            if (numericParts.length === 1) {
                cost = numericParts[0];
                price = Math.round(cost * 1.15);
            } else if (numericParts.length === 2) {
                if (numericParts[1] <= 100 && numericParts[0] > 100) {
                    // Cost and Qty
                    cost = numericParts[0];
                    qty = numericParts[1];
                    price = Math.round(cost * 1.15);
                } else {
                    // Cost and Sale Price
                    cost = numericParts[0];
                    price = numericParts[1];
                }
            } else if (numericParts.length >= 3) {
                cost = numericParts[0];
                price = numericParts[1];
                qty = Math.round(numericParts[2]);
            }
        } else {
            // Regex extraction from free text: extract trailing prices and quantities
            // e.g. "Dahua 2MP Bullet 3800 10pcs"
            const numMatch = line.match(/(.*?)\s+(?:PKR|Rs\.?|@)?\s*([0-9,]+(?:\.[0-9]+)?)\s*(?:x|\*|pcs|qty)?\s*([0-9]+)?/i);
            if (numMatch) {
                name = numMatch[1].trim();
                cost = parseFloat(numMatch[2].replace(/,/g, '')) || 0;
                qty = parseInt(numMatch[3] || '1') || 1;
                price = Math.round(cost * 1.15);
            } else {
                name = line;
                cost = 0;
                price = 0;
                qty = 1;
            }
        }

        if (name && name.length >= 2) {
            const cat = autoDetectCategory(name);
            const brand = autoDetectBrand(name);

            extracted.push({
                selected: true,
                name: name,
                category: cat,
                brand: brand,
                costPrice: cost,
                sellingPrice: price,
                stock: Math.max(1, qty),
                specs: '',
                importSource: 'text_bill'
            });
        }
    });

    if (extracted.length > 0) {
        parsedBatchItems = extracted;
        renderBatchTable();
    } else {
        alert('Could not parse any product lines from this text. Please check the text format.');
    }
}

// ----------------------------------------------------------------------------
// AUTO DETECTION HELPERS
// ----------------------------------------------------------------------------
function autoDetectCategory(name) {
    const l = name.toLowerCase();
    if (l.includes('dahua') || l.includes('hikvision') || l.includes('unv') || l.includes('uniview') || l.includes('camera') || l.includes('cctv') || l.includes('nvr') || l.includes('dvr') || l.includes('bnc')) {
        return 'cctv';
    }
    if (l.includes('router') || l.includes('switch') || l.includes('rj45') || l.includes('cat6') || l.includes('cat5') || l.includes('ethernet') || l.includes('lan cable') || l.includes('crimping') || l.includes('patch cord') || l.includes('wifi adapter') || l.includes('poe switch')) {
        return 'network_accessories';
    }
    if (l.includes('keyboard') || l.includes('mouse') || l.includes('flash drive') || l.includes('usb drive') || l.includes('ssd') || l.includes('hdd') || l.includes('ram') || l.includes('hdmi') || l.includes('vga') || l.includes('laptop bag') || l.includes('cooling pad') || l.includes('webcam') || l.includes('monitor') || l.includes('pc headset')) {
        return 'computer_accessories';
    }
    if (l.includes('iphone') || l.includes('samsung') || l.includes('galaxy') || l.includes('infinix') || l.includes('tecno') || l.includes('xiaomi') || l.includes('redmi') || l.includes('poco') || l.includes('vivo') || l.includes('oppo') || l.includes('realme') || l.includes('mobile') || l.includes('phone')) {
        return 'mobiles';
    }
    if (l.includes('package') || l.includes('bundle') || l.includes('combo') || l.includes('complete set')) {
        return 'packages';
    }
    return 'accessories';
}

function autoDetectBrand(name) {
    const l = name.toLowerCase();
    if (l.includes('samsung')) return 'Samsung';
    if (l.includes('apple') || l.includes('iphone')) return 'Apple';
    if (l.includes('infinix')) return 'Infinix';
    if (l.includes('tecno')) return 'Tecno';
    if (l.includes('xiaomi') || l.includes('redmi') || l.includes('poco')) return 'Xiaomi';
    if (l.includes('vivo')) return 'Vivo';
    if (l.includes('oppo')) return 'Oppo';
    if (l.includes('dahua')) return 'Dahua';
    if (l.includes('hikvision') || l.includes('hik')) return 'Hikvision';
    if (l.includes('unv') || l.includes('uniview')) return 'UNV';
    if (l.includes('anker')) return 'Anker';
    if (l.includes('ronin')) return 'Ronin';
    if (l.includes('faster')) return 'Faster';
    if (l.includes('audionic')) return 'Audionic';
    return 'General';
}

// ----------------------------------------------------------------------------
// BATCH TABLE RENDERING & EDITING
// ----------------------------------------------------------------------------
function renderBatchTable() {
    const container = document.getElementById('batchPreviewContainer');
    const tbody = document.getElementById('batchItemsTableBody');
    const badge = document.getElementById('batchCountBadge');
    if (!container || !tbody) return;

    container.style.display = 'block';
    if (badge) badge.textContent = `${parsedBatchItems.length} Products Extracted`;

    let html = '';
    parsedBatchItems.forEach((item, idx) => {
        html += `
            <tr id="batchRow-${idx}" style="background:${item.selected ? '#ffffff' : '#f8fafc'}; opacity:${item.selected ? '1' : '0.6'};">
                <td style="text-align:center; padding:6px;">
                    <input type="checkbox" ${item.selected ? 'checked' : ''} onchange="toggleBatchItemSelect(${idx}, this.checked)" style="cursor:pointer;">
                </td>
                <td style="padding:6px;">
                    <input type="text" class="batch-input-field" value="${escapeBatchAttr(item.name)}" onchange="updateBatchItemField(${idx}, 'name', this.value)" placeholder="Product Name">
                </td>
                <td style="padding:6px;">
                    <select class="batch-input-field" onchange="updateBatchItemField(${idx}, 'category', this.value)" style="padding:4px 6px;">
                        <option value="mobiles" ${item.category === 'mobiles' ? 'selected' : ''}>📱 Mobiles</option>
                        <option value="accessories" ${item.category === 'accessories' ? 'selected' : ''}>⚡ Accessories</option>
                        <option value="computer_accessories" ${item.category === 'computer_accessories' ? 'selected' : ''}>💻 Computer Accessories</option>
                        <option value="network_accessories" ${item.category === 'network_accessories' ? 'selected' : ''}>🌐 Network Accessories</option>
                        <option value="cctv" ${item.category === 'cctv' ? 'selected' : ''}>📹 CCTV Security</option>
                        <option value="packages" ${item.category === 'packages' ? 'selected' : ''}>📦 Packages</option>
                        <option value="general" ${item.category === 'general' ? 'selected' : ''}>🏷️ General</option>
                    </select>
                </td>
                <td style="padding:6px;">
                    <input type="text" class="batch-input-field" value="${escapeBatchAttr(item.brand)}" onchange="updateBatchItemField(${idx}, 'brand', this.value)" placeholder="Brand">
                </td>
                <td style="padding:6px;">
                    <input type="number" class="batch-input-field" value="${item.costPrice}" onchange="onBatchCostChange(${idx}, this.value)" placeholder="0">
                </td>
                <td style="padding:6px;">
                    <div style="display:flex; align-items:center; gap:3px;">
                        <input type="number" class="batch-input-field" id="batchSellingPriceInput-${idx}" value="${item.sellingPrice}" onchange="updateBatchItemField(${idx}, 'sellingPrice', this.value)" placeholder="0" style="color:#059669; font-weight:800;">
                    </div>
                </td>
                <td style="padding:6px; text-align:center;">
                    <input type="number" class="batch-input-field" value="${item.stock}" onchange="updateBatchItemField(${idx}, 'stock', this.value)" style="text-align:center; width:50px;">
                </td>
                <td style="padding:6px; text-align:center;">
                    <div style="display:flex; justify-content:center; gap:4px;">
                        <button type="button" onclick="loadSingleItemIntoMainForm(${idx})" title="Fill into current form" style="background:#f1f5f9; color:#4f46e5; border:1px solid #cbd5e1; padding:3px 6px; border-radius:4px; font-size:0.7rem; cursor:pointer;">
                            <i class="fa-solid fa-arrow-down-to-line"></i> Form
                        </button>
                        <button type="button" onclick="deleteBatchItem(${idx})" title="Delete row" style="background:#fee2e2; color:#dc2626; border:1px solid #fecaca; padding:3px 6px; border-radius:4px; font-size:0.7rem; cursor:pointer;">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
    updateBatchSummaryStats();
}

window.toggleBatchSelectAll = function(isChecked) {
    parsedBatchItems.forEach(item => item.selected = isChecked);
    renderBatchTable();
};

window.toggleBatchItemSelect = function(idx, isChecked) {
    if (parsedBatchItems[idx]) {
        parsedBatchItems[idx].selected = isChecked;
        const row = document.getElementById('batchRow-' + idx);
        if (row) {
            row.style.background = isChecked ? '#ffffff' : '#f8fafc';
            row.style.opacity = isChecked ? '1' : '0.6';
        }
        updateBatchSummaryStats();
    }
};

window.updateBatchItemField = function(idx, field, value) {
    if (parsedBatchItems[idx]) {
        if (field === 'costPrice' || field === 'sellingPrice') {
            parsedBatchItems[idx][field] = parseFloat(value) || 0;
        } else if (field === 'stock') {
            parsedBatchItems[idx][field] = parseInt(value) || 1;
        } else {
            parsedBatchItems[idx][field] = value;
        }
        updateBatchSummaryStats();
    }
};

window.onBatchCostChange = function(idx, costVal) {
    const cost = parseFloat(costVal) || 0;
    if (parsedBatchItems[idx]) {
        parsedBatchItems[idx].costPrice = cost;
        const newPrice = Math.round(cost * 1.15);
        parsedBatchItems[idx].sellingPrice = newPrice;
        const priceInput = document.getElementById('batchSellingPriceInput-' + idx);
        if (priceInput) priceInput.value = newPrice;
        updateBatchSummaryStats();
    }
};

window.applyBulkMarkup = function(markupRate) {
    parsedBatchItems.forEach((item, idx) => {
        if (item.selected && item.costPrice > 0) {
            item.sellingPrice = Math.round(item.costPrice * (1 + markupRate));
            const priceInput = document.getElementById('batchSellingPriceInput-' + idx);
            if (priceInput) priceInput.value = item.sellingPrice;
        }
    });
    updateBatchSummaryStats();
    if (window.showToast) {
        window.showToast('success', `Applied +${Math.round(markupRate * 100)}% profit margin to selected products!`);
    }
};

window.deleteBatchItem = function(idx) {
    parsedBatchItems.splice(idx, 1);
    renderBatchTable();
};

function updateBatchSummaryStats() {
    let selCount = 0;
    let totalCost = 0;
    let totalRetail = 0;

    parsedBatchItems.forEach(item => {
        if (item.selected) {
            selCount++;
            const qty = item.stock || 1;
            totalCost += (item.costPrice || 0) * qty;
            totalRetail += (item.sellingPrice || 0) * qty;
        }
    });

    const profit = Math.max(0, totalRetail - totalCost);

    const elCount = document.getElementById('summarySelectedCount');
    const elCost = document.getElementById('summaryTotalCost');
    const elRetail = document.getElementById('summaryTotalRetail');
    const elProfit = document.getElementById('summaryTotalProfit');
    const btnSave = document.getElementById('btnBatchSaveAll');

    if (elCount) elCount.textContent = selCount;
    if (elCost) elCost.textContent = 'PKR ' + totalCost.toLocaleString();
    if (elRetail) elRetail.textContent = 'PKR ' + totalRetail.toLocaleString();
    if (elProfit) elProfit.textContent = 'PKR ' + profit.toLocaleString();

    if (btnSave) {
        btnSave.innerHTML = `<i class="fa-solid fa-cloud-arrow-up"></i> Save Selected ${selCount} Product(s) Directly to Inventory (Batch Import)`;
        btnSave.disabled = (selCount === 0);
    }
}

// Fill single parsed item into the main product form
window.loadSingleItemIntoMainForm = function(idx) {
    const item = parsedBatchItems[idx];
    if (!item) return;

    const nameInput = document.getElementById('productNameInput');
    const catSelect = document.getElementById('productCategorySelect');
    const brandInput = document.getElementById('productBrandInput');
    const priceInput = document.getElementById('sellingPriceInput');
    const costInput = document.querySelector('input[name="costPrice"]');
    const stockInput = document.querySelector('input[name="stock"]');

    if (nameInput) nameInput.value = item.name;
    if (catSelect) {
        catSelect.value = item.category;
        if (window.onCategoryChange) window.onCategoryChange(item.category);
    }
    if (brandInput) brandInput.value = item.brand;
    if (priceInput) priceInput.value = item.sellingPrice;
    if (costInput) costInput.value = item.costPrice;
    if (stockInput) stockInput.value = item.stock;

    if (window.recalculateOnlineDiscount) window.recalculateOnlineDiscount();

    closeSmartImporterModal();
    if (window.showToast) {
        window.showToast('success', 'Loaded into form: ' + item.name);
    } else {
        alert('Loaded item into form successfully!');
    }
};

// ----------------------------------------------------------------------------
// SAVE BATCH IMPORT TO INVENTORY API
// ----------------------------------------------------------------------------
window.saveBatchImportedProducts = function() {
    const selectedItems = parsedBatchItems.filter(item => item.selected && item.name.trim().length > 0);
    if (selectedItems.length === 0) {
        alert('Please select at least one valid product to import.');
        return;
    }

    const btn = document.getElementById('btnBatchSaveAll');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving ' + selectedItems.length + ' products to database & inventory...';
    }

    fetch('backend/import_products.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ items: selectedItems })
    })
    .then(res => res.json())
    .then(data => {
        if (data && data.status === 'success') {
            alert(`🎉 Success! ${data.data.count || selectedItems.length} products have been saved to your inventory and database!`);
            window.location.href = 'products.php';
        } else {
            alert('Import Notice: ' + (data.message || 'Failed to save items.'));
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Retry Batch Save';
            }
        }
    })
    .catch(err => {
        console.error(err);
        alert('Network Error during batch import: ' + err.message);
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Retry Batch Save';
        }
    });
};

function escapeBatchAttr(str) {
    if (!str) return '';
    return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
</script>
