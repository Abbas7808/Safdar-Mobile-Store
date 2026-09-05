<!-- Smart Multi-Format SIM Packages & Bundles Importer Modal (Excel, PDF, Receipt OCR, WhatsApp Text) -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>

<div id="smartPackagesBatchImporterModal" class="pos-modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15, 23, 42, 0.82); z-index:999999; backdrop-filter:blur(6px); align-items:center; justify-content:center; padding:16px; box-sizing:border-box;">
    <div class="pos-modal-container" style="background:#ffffff; width:100%; max-width:1150px; max-height:92vh; border-radius:16px; box-shadow:0 25px 60px rgba(0,0,0,0.35); display:flex; flex-direction:column; overflow:hidden; border:1.5px solid #cbd5e1; animation:slideDownModal 0.25s ease-out;">
        
        <!-- Modal Header -->
        <div style="background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color:#ffffff; padding:16px 24px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #334155;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="background:linear-gradient(135deg, #e11d48 0%, #be123c 100%); width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; color:#fff; box-shadow:0 4px 12px rgba(225,29,72,0.4);">
                    <i class="fa-solid fa-sim-card"></i>
                </div>
                <div>
                    <h2 style="margin:0; font-size:1.2rem; font-weight:800; color:#f8fafc; display:flex; align-items:center; gap:8px;">
                        <span>Smart SIM Packages Batch Importer</span>
                        <span style="background:#e11d48; color:#ffffff; font-size:0.68rem; font-weight:800; padding:2px 8px; border-radius:12px; text-transform:uppercase;">Time-Saving AI OCR &amp; Excel</span>
                    </h2>
                    <p style="margin:2px 0 0 0; font-size:0.78rem; color:#94a3b8;">
                        Extract plans &amp; retailer margins from <strong>Excel (.xlsx / .csv)</strong>, <strong>Telco Posters / PDF Flyers</strong>, <strong>WhatsApp Rate Lists</strong>, or <strong>1-Click Telco Presets</strong>
                    </p>
                </div>
            </div>
            <button type="button" onclick="closeSmartPackagesImporterModal()" style="background:rgba(255,255,255,0.1); border:none; color:#f1f5f9; font-size:1.2rem; width:34px; height:34px; border-radius:8px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background 0.2s;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Modal Body (Scrollable) -->
        <div style="padding:20px 24px; overflow-y:auto; flex:1; background:#f8fafc;">
            
            <!-- Source Selector Navigation Tabs -->
            <div style="display:flex; gap:8px; border-bottom:2px solid #e2e8f0; padding-bottom:12px; margin-bottom:18px; flex-wrap:wrap;">
                <button type="button" id="pkgImporterTabBtn-excel" class="pkg-importer-tab-btn active" onclick="switchPkgImporterTab('excel')" style="background:#0f172a; color:#ffffff; border:none; padding:9px 16px; border-radius:8px; font-weight:800; font-size:0.82rem; cursor:pointer; display:flex; align-items:center; gap:8px; transition:all 0.2s;">
                    <i class="fa-solid fa-file-excel" style="color:#22c55e;"></i> 1. Excel / CSV Sheet
                </button>
                <button type="button" id="pkgImporterTabBtn-ocr" class="pkg-importer-tab-btn" onclick="switchPkgImporterTab('ocr')" style="background:#ffffff; color:#475569; border:1px solid #cbd5e1; padding:9px 16px; border-radius:8px; font-weight:700; font-size:0.82rem; cursor:pointer; display:flex; align-items:center; gap:8px; transition:all 0.2s;">
                    <i class="fa-solid fa-camera" style="color:#e11d48;"></i> 2. Poster / PDF Flyer (AI OCR)
                </button>
                <button type="button" id="pkgImporterTabBtn-text" class="pkg-importer-tab-btn" onclick="switchPkgImporterTab('text')" style="background:#ffffff; color:#475569; border:1px solid #cbd5e1; padding:9px 16px; border-radius:8px; font-weight:700; font-size:0.82rem; cursor:pointer; display:flex; align-items:center; gap:8px; transition:all 0.2s;">
                    <i class="fa-brands fa-whatsapp" style="color:#25d366;"></i> 3. Paste WhatsApp / Text Rate List
                </button>
                <button type="button" id="pkgImporterTabBtn-presets" class="pkg-importer-tab-btn" onclick="switchPkgImporterTab('presets')" style="background:#ffffff; color:#475569; border:1px solid #cbd5e1; padding:9px 16px; border-radius:8px; font-weight:700; font-size:0.82rem; cursor:pointer; display:flex; align-items:center; gap:8px; transition:all 0.2s;">
                    <i class="fa-solid fa-bolt" style="color:#f59e0b;"></i> 4. 1-Click Official Telco Presets (2026)
                </button>
            </div>

            <!-- TAB 1: EXCEL / CSV UPLOADER -->
            <div id="pkgImporterTabSection-excel" class="pkg-importer-tab-section" style="display:block;">
                <div style="background:#ffffff; border:2px dashed #94a3b8; border-radius:12px; padding:26px 20px; text-align:center; transition:border-color 0.2s; cursor:pointer;" onclick="document.getElementById('smartPkgExcelFileInput').click()" ondragover="event.preventDefault(); this.style.borderColor='#e11d48';" ondragleave="this.style.borderColor='#94a3b8';" ondrop="handlePkgExcelDrop(event)">
                    <input type="file" id="smartPkgExcelFileInput" accept=".xlsx, .xls, .csv" style="display:none;" onchange="handlePkgExcelFileSelect(this)">
                    <div style="width:50px; height:50px; background:#fff1f2; color:#e11d48; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; font-size:1.6rem; margin-bottom:10px;">
                        <i class="fa-solid fa-file-excel"></i>
                    </div>
                    <h3 style="margin:0 0 4px 0; font-size:1rem; color:#0f172a;">Click to browse or Drag &amp; Drop Excel / CSV Package List</h3>
                    <p style="margin:0 0 12px 0; font-size:0.78rem; color:#64748b;">Supported formats: .xlsx, .xls, .csv. Automatic column detection for Network, Plan Name, Validity, Retail Price, Cost Price, Data GB, Mins &amp; USSD.</p>
                    <div style="display:inline-flex; gap:10px; align-items:center;" onclick="event.stopPropagation()">
                        <button type="button" class="pos-btn pos-btn-sm" style="background:#0f172a; color:#fff; font-weight:700; font-size:0.75rem;" onclick="document.getElementById('smartPkgExcelFileInput').click()">
                            <i class="fa-solid fa-folder-open"></i> Select Excel File
                        </button>
                        <button type="button" class="pos-btn pos-btn-sm pos-btn-outline" style="font-size:0.75rem; font-weight:700; border-color:#cbd5e1; color:#0f172a; background:#fff;" onclick="downloadSamplePkgExcelTemplate()">
                            <i class="fa-solid fa-download" style="color:#e11d48;"></i> Download Sample Package Excel Template
                        </button>
                    </div>
                </div>
            </div>

            <!-- TAB 2: POSTER / FLYER / PDF OCR SCANNER -->
            <div id="pkgImporterTabSection-ocr" class="pkg-importer-tab-section" style="display:none;">
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:14px;">
                    <div style="background:#ffffff; border:2px dashed #94a3b8; border-radius:12px; padding:22px 16px; text-align:center; cursor:pointer;" onclick="document.getElementById('smartPkgReceiptFileInput').click()">
                        <input type="file" id="smartPkgReceiptFileInput" accept="image/*, .pdf" style="display:none;" onchange="handlePkgReceiptFileSelect(this)">
                        <div style="width:46px; height:46px; background:#fff1f2; color:#e11d48; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; font-size:1.5rem; margin-bottom:8px;">
                            <i class="fa-solid fa-camera"></i>
                        </div>
                        <h4 style="margin:0 0 4px 0; font-size:0.92rem; color:#0f172a;">Upload Telco Package Banner, Poster or PDF Sheet</h4>
                        <p style="margin:0 0 10px 0; font-size:0.75rem; color:#64748b;">(JPG, PNG, WEBP, PDF) — OCR text extractor for Jazz, Zong, Telenor, Ufone rate cards.</p>
                        <div style="display:inline-flex; gap:6px;">
                            <button type="button" class="pos-btn pos-btn-sm" style="background:#e11d48; color:#fff; font-weight:700; font-size:0.75rem;">
                                <i class="fa-solid fa-upload"></i> Choose Poster Image / PDF
                            </button>
                            <button type="button" class="pos-btn pos-btn-sm pos-btn-outline" onclick="event.stopPropagation(); pastePkgFromClipboardOcr()" style="font-size:0.75rem; font-weight:700; background:#fff;">
                                <i class="fa-solid fa-paste"></i> Paste Image (Ctrl+V)
                            </button>
                        </div>
                    </div>

                    <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:12px; padding:14px; display:flex; flex-direction:column;">
                        <h4 style="margin:0 0 8px 0; font-size:0.85rem; color:#0f172a; display:flex; align-items:center; gap:6px;">
                            <i class="fa-solid fa-file-lines" style="color:#64748b;"></i> OCR Extracted Raw Text
                        </h4>
                        <textarea id="pkgOcrRawOutput" placeholder="Scanned text from poster or bill will appear here for verification..." style="width:100%; flex:1; min-height:90px; border:1px solid #cbd5e1; border-radius:8px; padding:8px; font-family:monospace; font-size:0.75rem; resize:none; box-sizing:border-box; background:#f8fafc;"></textarea>
                        <div style="margin-top:8px; display:flex; justify-content:space-between; align-items:center;">
                            <span id="pkgOcrStatusText" style="font-size:0.72rem; color:#64748b; font-weight:600;">Ready for upload.</span>
                            <button type="button" class="pos-btn pos-btn-sm" style="background:#0f172a; color:#fff; font-size:0.72rem; padding:3px 8px;" onclick="parseRawPkgText(document.getElementById('pkgOcrRawOutput').value)">
                                <i class="fa-solid fa-wand-magic-sparkles"></i> Re-Parse Text
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: PASTE WHATSAPP / TEXT RATE LIST -->
            <div id="pkgImporterTabSection-text" class="pkg-importer-tab-section" style="display:none;">
                <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:12px; padding:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:8px;">
                        <div>
                            <h4 style="margin:0; font-size:0.9rem; color:#0f172a; display:flex; align-items:center; gap:6px;">
                                <i class="fa-brands fa-whatsapp" style="color:#25d366; font-size:1.1rem;"></i> Paste WhatsApp Package List or Retailer Rate Card
                            </h4>
                            <p style="margin:2px 0 0 0; font-size:0.75rem; color:#64748b;">Paste lines copied from wholesale WhatsApp groups. The parser automatically detects network, validity, data, mins &amp; prices.</p>
                        </div>
                        <div style="display:flex; gap:6px;">
                            <button type="button" class="pos-btn pos-btn-sm pos-btn-outline" onclick="loadPkgSampleText()" style="font-size:0.72rem; padding:4px 8px; background:#fff;">
                                💡 Load Sample WhatsApp Text
                            </button>
                            <button type="button" class="pos-btn pos-btn-sm" onclick="clearPkgTextInput()" style="font-size:0.72rem; padding:4px 8px; background:#f1f5f9; color:#475569;">
                                Clear
                            </button>
                        </div>
                    </div>
                    
                    <textarea id="smartPkgPasteTextarea" class="form-textarea" rows="6" placeholder="Example format:&#10;Jazz Monthly Super Duper 10GB 3000 Mins Rs 950 cost 890 code *706#&#10;Zong Mega Weekly 100GB Rs 450 cost 410 code *6464#&#10;Telenor EasyCard 500 Rs 500 cost 460&#10;Ufone Super Card Gold 30GB Rs 1200 cost 1130" style="font-family:monospace; font-size:0.8rem; width:100%; box-sizing:border-box;"></textarea>
                    
                    <div style="margin-top:10px; display:flex; justify-content:flex-end;">
                        <button type="button" class="pos-btn" onclick="parseRawPkgText(document.getElementById('smartPkgPasteTextarea').value)" style="background:linear-gradient(135deg, #10b981 0%, #059669 100%); color:#fff; font-weight:800; font-size:0.82rem; padding:8px 18px; border-radius:8px;">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> Parse Packages &amp; Extract
                        </button>
                    </div>
                </div>
            </div>

            <!-- TAB 4: 1-CLICK OFFICIAL PAKISTAN TELCO PRESETS (2026) -->
            <div id="pkgImporterTabSection-presets" class="pkg-importer-tab-section" style="display:none;">
                <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:12px; padding:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:8px;">
                        <div>
                            <h4 style="margin:0; font-size:0.95rem; color:#0f172a; display:flex; align-items:center; gap:6px;">
                                <i class="fa-solid fa-bolt" style="color:#f59e0b;"></i> 1-Click Load Official Pakistan Telco Catalogs
                            </h4>
                            <p style="margin:2px 0 0 0; font-size:0.75rem; color:#64748b;">Instant ready-to-use popular packages with official retail rates, load cost prices, and retailer commissions.</p>
                        </div>
                        <button type="button" class="pos-btn pos-btn-sm" onclick="loadAllTelcoPresets()" style="background:#e11d48; color:#fff; font-weight:800; padding:6px 14px; border-radius:6px; font-size:0.75rem;">
                            <i class="fa-solid fa-plus-circle"></i> Load ALL Official Packages (24 Plans)
                        </button>
                    </div>

                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:10px;">
                        <!-- Jazz Box -->
                        <div style="background:#fff1f2; border:1.5px solid #fecaca; border-radius:10px; padding:12px; text-align:center;">
                            <div style="font-size:0.88rem; font-weight:900; color:#be123c; margin-bottom:4px;">🔴 Jazz / Warid 4G</div>
                            <div style="font-size:0.72rem; color:#64748b; margin-bottom:8px;">Super Duper, Monthly Bachat, Weekly Mega</div>
                            <button type="button" class="pos-btn pos-btn-sm" onclick="loadNetworkPreset('jazz')" style="background:#be123c; color:#fff; font-weight:800; width:100%; font-size:0.72rem;">
                                Load Jazz Plans (6)
                            </button>
                        </div>

                        <!-- Zong Box -->
                        <div style="background:#f0fdf4; border:1.5px solid #bbf7d0; border-radius:10px; padding:12px; text-align:center;">
                            <div style="font-size:0.88rem; font-weight:900; color:#15803d; margin-bottom:4px;">🟢 Zong 4G</div>
                            <div style="font-size:0.72rem; color:#64748b; margin-bottom:8px;">Super Star, Mega Weekly, Monthly Pro</div>
                            <button type="button" class="pos-btn pos-btn-sm" onclick="loadNetworkPreset('zong')" style="background:#15803d; color:#fff; font-weight:800; width:100%; font-size:0.72rem;">
                                Load Zong Plans (6)
                            </button>
                        </div>

                        <!-- Telenor Box -->
                        <div style="background:#eff6ff; border:1.5px solid #bfdbfe; border-radius:10px; padding:12px; text-align:center;">
                            <div style="font-size:0.88rem; font-weight:900; color:#1d4ed8; margin-bottom:4px;">🔵 Telenor 4G</div>
                            <div style="font-size:0.72rem; color:#64748b; margin-bottom:8px;">EasyCard Mega, Weekly Ultimate, Monthly Social</div>
                            <button type="button" class="pos-btn pos-btn-sm" onclick="loadNetworkPreset('telenor')" style="background:#1d4ed8; color:#fff; font-weight:800; width:100%; font-size:0.72rem;">
                                Load Telenor Plans (5)
                            </button>
                        </div>

                        <!-- Ufone Box -->
                        <div style="background:#fff7ed; border:1.5px solid #fed7aa; border-radius:10px; padding:12px; text-align:center;">
                            <div style="font-size:0.88rem; font-weight:900; color:#c2410c; margin-bottom:4px;">🟠 Ufone 4G</div>
                            <div style="font-size:0.72rem; color:#64748b; margin-bottom:8px;">Super Card Gold, Weekly Digital, Extreme Data</div>
                            <button type="button" class="pos-btn pos-btn-sm" onclick="loadNetworkPreset('ufone')" style="background:#c2410c; color:#fff; font-weight:800; width:100%; font-size:0.72rem;">
                                Load Ufone Plans (5)
                            </button>
                        </div>

                        <!-- Onic Box -->
                        <div style="background:#faf5ff; border:1.5px solid #e9d5ff; border-radius:10px; padding:12px; text-align:center;">
                            <div style="font-size:0.88rem; font-weight:900; color:#7e22ce; margin-bottom:4px;">🟣 Onic Digital</div>
                            <div style="font-size:0.72rem; color:#64748b; margin-bottom:8px;">Epic 30GB, Iconic 100GB, Unlimited Calling</div>
                            <button type="button" class="pos-btn pos-btn-sm" onclick="loadNetworkPreset('onic')" style="background:#7e22ce; color:#fff; font-weight:800; width:100%; font-size:0.72rem;">
                                Load Onic Plans (2)
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PARSED PACKAGES EDITABLE PREVIEW MATRIX TABLE -->
            <div id="pkgParsedResultsContainer" style="margin-top:20px; display:none;">
                <div style="background:#ffffff; border:1.5px solid #cbd5e1; border-radius:12px; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.05);">
                    
                    <!-- Table Header Controls -->
                    <div style="background:#f8fafc; padding:12px 18px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <span style="font-weight:800; color:#0f172a; font-size:0.92rem;">
                                <i class="fa-solid fa-list-check" style="color:#e11d48;"></i> Extracted Packages Matrix (<span id="pkgParsedCountBadge">0</span> Plans)
                            </span>
                            <span style="font-size:0.75rem; color:#64748b;">Review and edit package details before adding to catalog</span>
                        </div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <button type="button" class="pos-btn pos-btn-sm pos-btn-outline" onclick="addBlankPkgRow()" style="font-size:0.75rem; font-weight:700; background:#fff;">
                                <i class="fa-solid fa-plus"></i> + Add Empty Row
                            </button>
                            <button type="button" class="pos-btn pos-btn-sm" onclick="clearAllParsedPkgs()" style="font-size:0.75rem; font-weight:700; background:#f1f5f9; color:#dc2626; border:1px solid #fca5a5;">
                                <i class="fa-solid fa-trash-can"></i> Clear All
                            </button>
                        </div>
                    </div>

                    <!-- Scrollable Table -->
                    <div style="overflow-x:auto; max-height:360px;">
                        <table style="width:100%; border-collapse:collapse; font-size:0.8rem; text-align:left;" id="pkgParsedTable">
                            <thead>
                                <tr style="background:#f1f5f9; color:#475569; font-size:0.72rem; text-transform:uppercase; border-bottom:1px solid #cbd5e1; position:sticky; top:0; z-index:10;">
                                    <th style="padding:10px; width:35px; text-align:center;">
                                        <input type="checkbox" id="selectAllPkgCheckbox" checked onchange="toggleAllPkgCheckboxes(this.checked)">
                                    </th>
                                    <th style="padding:10px; min-width:90px;">Network</th>
                                    <th style="padding:10px; min-width:200px;">Package Plan Name</th>
                                    <th style="padding:10px; min-width:100px;">Validity</th>
                                    <th style="padding:10px; min-width:110px;">Retail Price (PKR)</th>
                                    <th style="padding:10px; min-width:110px;">Cost Price (PKR)</th>
                                    <th style="padding:10px; min-width:90px;">Profit</th>
                                    <th style="padding:10px; min-width:100px;">Data (GB)</th>
                                    <th style="padding:10px; min-width:100px;">Mins / SMS</th>
                                    <th style="padding:10px; min-width:90px;">USSD Code</th>
                                    <th style="padding:10px; width:45px; text-align:center;">Del</th>
                                </tr>
                            </thead>
                            <tbody id="pkgParsedTableBody">
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Table Summary Footer -->
                    <div style="background:#f8fafc; padding:12px 18px; border-top:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                        <div style="display:flex; gap:16px; font-size:0.8rem;">
                            <span>Selected: <strong id="pkgSelectedCount" style="color:#e11d48;">0</strong> / <span id="pkgTotalCount">0</span></span>
                            <span>Est. Retail Volume: <strong id="pkgTotalRetailValue" style="color:#0f172a;">PKR 0</strong></span>
                            <span>Total Expected Profit: <strong id="pkgTotalProfitValue" style="color:#059669;">+PKR 0</strong></span>
                        </div>
                        <div style="display:flex; gap:8px;">
                            <button type="button" class="pos-btn pos-btn-outline" onclick="closeSmartPackagesImporterModal()">Cancel</button>
                            <button type="button" id="btnSubmitPkgBatchImport" class="pos-btn" onclick="submitPkgBatchImport()" style="background:linear-gradient(135deg, #e11d48 0%, #be123c 100%); color:#fff; font-weight:800; font-size:0.85rem; padding:8px 22px; border-radius:8px; box-shadow:0 4px 12px rgba(225,29,72,0.35);">
                                <i class="fa-solid fa-cloud-arrow-up"></i> Import <span id="pkgBtnImportCount">0</span> Packages to Catalog
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
// Global container for parsed package records
window.parsedPkgBatchItems = [];

function openSmartPackagesImporterModal(defaultTab = 'excel') {
    const modal = document.getElementById('smartPackagesBatchImporterModal');
    if (modal) {
        modal.style.display = 'flex';
        switchPkgImporterTab(defaultTab);
    }
}

function closeSmartPackagesImporterModal() {
    const modal = document.getElementById('smartPackagesBatchImporterModal');
    if (modal) modal.style.display = 'none';
}

function switchPkgImporterTab(tabName) {
    document.querySelectorAll('.pkg-importer-tab-btn').forEach(btn => {
        btn.classList.remove('active');
        btn.style.background = '#ffffff';
        btn.style.color = '#475569';
        btn.style.border = '1px solid #cbd5e1';
    });
    document.querySelectorAll('.pkg-importer-tab-section').forEach(sec => sec.style.display = 'none');

    const activeBtn = document.getElementById('pkgImporterTabBtn-' + tabName);
    const activeSec = document.getElementById('pkgImporterTabSection-' + tabName);

    if (activeBtn) {
        activeBtn.classList.add('active');
        activeBtn.style.background = '#0f172a';
        activeBtn.style.color = '#ffffff';
        activeBtn.style.border = 'none';
    }
    if (activeSec) {
        activeSec.style.display = 'block';
    }
}

// -------------------------------------------------------------
// 1. EXCEL / CSV PARSING ENGINE
// -------------------------------------------------------------
function handlePkgExcelFileSelect(input) {
    if (!input.files || !input.files[0]) return;
    processPkgExcelFile(input.files[0]);
}

function handlePkgExcelDrop(e) {
    e.preventDefault();
    const box = e.currentTarget;
    if (box) box.style.borderColor = '#94a3b8';
    if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
        processPkgExcelFile(e.dataTransfer.files[0]);
    }
}

function processPkgExcelFile(file) {
    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            const firstSheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[firstSheetName];
            const rawRows = XLSX.utils.sheet_to_json(worksheet, { header: 1 });

            if (!rawRows || rawRows.length < 2) {
                alert('Uploaded Excel file seems empty or missing header row.');
                return;
            }

            const header = rawRows[0].map(h => String(h || '').trim().toLowerCase());
            const rows = rawRows.slice(1);

            const colMap = {
                network: header.findIndex(h => h.includes('network') || h.includes('sim') || h.includes('telecom') || h.includes('operator')),
                name: header.findIndex(h => h.includes('package') || h.includes('name') || h.includes('plan') || h.includes('title') || h.includes('bundle')),
                validity: header.findIndex(h => h.includes('validity') || h.includes('days') || h.includes('duration') || h.includes('period') || h.includes('expiry')),
                retailPrice: header.findIndex(h => h.includes('retail') || h.includes('price') || h.includes('sale') || h.includes('rs') || h.includes('pkr') || h.includes('mrp')),
                costPrice: header.findIndex(h => h.includes('cost') || h.includes('wholesale') || h.includes('dealer') || h.includes('load')),
                dataGb: header.findIndex(h => h.includes('data') || h.includes('gb') || h.includes('internet') || h.includes('mb')),
                onNetMins: header.findIndex(h => h.includes('on net') || h.includes('same net') || h.includes('jazz min') || h.includes('zong min') || h.includes('mins')),
                offNetMins: header.findIndex(h => h.includes('off net') || h.includes('other net')),
                smsCount: header.findIndex(h => h.includes('sms') || h.includes('messages')),
                ussdCode: header.findIndex(h => h.includes('code') || h.includes('ussd') || h.includes('string') || h.includes('sub'))
            };

            const extracted = [];
            rows.forEach(r => {
                const name = (colMap.name !== -1 ? r[colMap.name] : r[0]) || '';
                if (!name || String(name).trim() === '') return;

                const nameStr = String(name).trim();
                let network = (colMap.network !== -1 ? r[colMap.network] : '') || '';
                network = detectNetworkFromName(nameStr, network);

                let retail = parseFloat(colMap.retailPrice !== -1 ? r[colMap.retailPrice] : 0) || 0;
                let cost = parseFloat(colMap.costPrice !== -1 ? r[colMap.costPrice] : 0) || 0;
                if (retail <= 0 && cost > 0) retail = cost + 50;
                if (cost <= 0 && retail > 0) cost = Math.round(retail * 0.94);

                let validity = String(colMap.validity !== -1 ? (r[colMap.validity] || '') : '').trim();
                if (!validity) {
                    if (nameStr.toLowerCase().includes('monthly')) validity = '30 Days';
                    else if (nameStr.toLowerCase().includes('weekly')) validity = '7 Days';
                    else if (nameStr.toLowerCase().includes('daily')) validity = '1 Day';
                    else validity = '30 Days';
                }

                extracted.push({
                    network: network,
                    name: nameStr,
                    category: 'all_in_one',
                    validity: validity,
                    retailPrice: retail,
                    costPrice: cost,
                    dataGb: String(colMap.dataGb !== -1 ? (r[colMap.dataGb] || '') : ''),
                    onNetMins: String(colMap.onNetMins !== -1 ? (r[colMap.onNetMins] || '') : ''),
                    offNetMins: String(colMap.offNetMins !== -1 ? (r[colMap.offNetMins] || '') : ''),
                    smsCount: String(colMap.smsCount !== -1 ? (r[colMap.smsCount] || '') : ''),
                    ussdCode: String(colMap.ussdCode !== -1 ? (r[colMap.ussdCode] || '') : ''),
                    importSource: 'excel'
                });
            });

            if (extracted.length === 0) {
                alert('No valid packages could be extracted from Excel file.');
                return;
            }

            appendParsedPkgItems(extracted);
        } catch (err) {
            alert('Error parsing Excel file: ' + err.message);
        }
    };
    reader.readAsArrayBuffer(file);
}

function downloadSamplePkgExcelTemplate() {
    const sampleData = [
        ["Network", "Package Plan Name", "Validity", "Retail Price", "Cost Price", "Data GB", "On Net Mins", "Off Net Mins", "SMS", "USSD Code"],
        ["Jazz", "Jazz Monthly Super Duper 10GB", "30 Days", 950, 890, "10 GB", "3000", "300", "3000", "*706#"],
        ["Jazz", "Jazz Weekly Mega 25GB", "7 Days", 380, 350, "25 GB", "1000", "50", "1000", "*117*77#"],
        ["Zong", "Zong Super Star Monthly 30GB", "30 Days", 1100, 1030, "30 GB", "5000", "500", "5000", "*6464#"],
        ["Zong", "Zong Mega Weekly 100GB", "7 Days", 450, 415, "100 GB (1AM-9AM)", "0", "0", "0", "*220#"],
        ["Telenor", "Telenor EasyCard Mega 15GB", "30 Days", 850, 795, "15 GB", "3000", "200", "3000", "*350#"],
        ["Ufone", "Ufone Super Card Gold 30GB", "30 Days", 1200, 1130, "30 GB", "Unlimited", "600", "Unlimited", "*900#"],
        ["Onic", "Onic Epic Monthly 30GB", "30 Days", 890, 840, "30 GB", "Unlimited", "500", "1000", "App"]
    ];

    const ws = XLSX.utils.aoa_to_sheet(sampleData);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "SIM Packages");
    XLSX.writeFile(wb, "Safdar_SIM_Packages_Sample_Template.xlsx");
}

// -------------------------------------------------------------
// 2. OCR / IMAGE SCANNING ENGINE
// -------------------------------------------------------------
function handlePkgReceiptFileSelect(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    runPkgOcrOnFile(file);
}

function pastePkgFromClipboardOcr() {
    navigator.clipboard.read().then(items => {
        for (let item of items) {
            for (let type of item.types) {
                if (type.startsWith('image/')) {
                    item.getType(type).then(blob => runPkgOcrOnFile(blob));
                    return;
                }
            }
        }
        alert('No image found on clipboard. Please copy an image first.');
    }).catch(err => alert('Clipboard access error: ' + err.message));
}

function runPkgOcrOnFile(file) {
    const statusText = document.getElementById('pkgOcrStatusText');
    const rawTextarea = document.getElementById('pkgOcrRawOutput');
    if (statusText) statusText.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="color:#e11d48;"></i> Scanning image with AI OCR engine...';

    if (file.type === 'application/pdf') {
        const fileReader = new FileReader();
        fileReader.onload = function() {
            const typedarray = new Uint8Array(this.result);
            pdfjsLib.getDocument(typedarray).promise.then(pdf => {
                let textPromises = [];
                for (let i = 1; i <= pdf.numPages; i++) {
                    textPromises.push(pdf.getPage(i).then(page => page.getTextContent().then(c => c.items.map(s => s.str).join(' '))));
                }
                Promise.all(textPromises).then(texts => {
                    const fullText = texts.join("\n");
                    if (rawTextarea) rawTextarea.value = fullText;
                    if (statusText) statusText.innerHTML = '✅ PDF scanned successfully!';
                    parseRawPkgText(fullText);
                });
            }).catch(err => {
                if (statusText) statusText.innerText = 'PDF read error: ' + err.message;
            });
        };
        fileReader.readAsArrayBuffer(file);
    } else {
        Tesseract.recognize(file, 'eng', {
            logger: m => {
                if (m.status === 'recognizing text' && statusText) {
                    statusText.innerHTML = `<i class="fa-solid fa-spinner fa-spin" style="color:#e11d48;"></i> Scanning: ${Math.round(m.progress * 100)}%`;
                }
            }
        }).then(({ data: { text } }) => {
            if (rawTextarea) rawTextarea.value = text;
            if (statusText) statusText.innerHTML = '✅ Image scanned successfully!';
            parseRawPkgText(text);
        }).catch(err => {
            if (statusText) statusText.innerText = 'OCR Error: ' + err.message;
        });
    }
}

// -------------------------------------------------------------
// 3. WHATSAPP & RAW TEXT PARSER ENGINE
// -------------------------------------------------------------
function loadPkgSampleText() {
    const sample = `*🔥 JAZZ 4G LATEST WHOLESALE RATES 🔥*
1. Jazz Monthly Super Duper (10GB Data + 3000 Jazz Mins + 300 Other Net) Retail: Rs 950 | Cost: 890 | Code: *706#
2. Jazz Weekly Mega 25GB (25GB Data + 1000 Mins) Retail: Rs 380 | Cost: 350 | Code: *117*77#
3. Jazz Monthly Bachat 6GB (6GB + 300 Mins) Retail: Rs 350 | Cost: 320 | Code: *614#

*🟢 ZONG 4G SPECIAL BUNDLES 🟢*
4. Zong Super Star Monthly 30GB (30GB + 5000 Mins) Retail: Rs 1100 | Cost: 1030 | Code: *6464#
5. Zong Mega Weekly 100GB (100GB 1AM-9AM) Retail: Rs 450 | Cost: 415 | Code: *220#

*🔵 TELENOR 4G EASYCARDS 🔵*
6. Telenor EasyCard Mega 15GB (15GB + 3000 Mins) Retail: Rs 850 | Cost: 795 | Code: *350#
7. Telenor Weekly Ultimate 100GB Retail: Rs 420 | Cost: 390

*🟠 UFONE 4G SUPER CARDS 🟠*
8. Ufone Super Card Gold 30GB (30GB + 600 Other Net) Retail: Rs 1200 | Cost: 1130 | Code: *900#
9. Ufone Weekly Digital 25GB Retail: Rs 390 | Cost: 360 | Code: *222#`;

    const textarea = document.getElementById('smartPkgPasteTextarea');
    if (textarea) textarea.value = sample;
}

function clearPkgTextInput() {
    const textarea = document.getElementById('smartPkgPasteTextarea');
    if (textarea) textarea.value = '';
}

function parseRawPkgText(rawText) {
    if (!rawText || !rawText.trim()) {
        alert('Please provide text or scan an image first.');
        return;
    }

    const lines = rawText.split('\n').map(l => l.trim()).filter(l => l.length > 3);
    const extracted = [];
    let currentNetworkContext = 'jazz';

    lines.forEach(line => {
        const lineLower = line.toLowerCase();
        
        // Detect network header
        if (lineLower.includes('jazz') || lineLower.includes('warid')) currentNetworkContext = 'jazz';
        else if (lineLower.includes('zong')) currentNetworkContext = 'zong';
        else if (lineLower.includes('telenor')) currentNetworkContext = 'telenor';
        else if (lineLower.includes('ufone')) currentNetworkContext = 'ufone';
        else if (lineLower.includes('onic')) currentNetworkContext = 'onic';

        // Check if line contains package info or price
        const priceMatches = line.match(/(?:rs\.?|pkr|retail:?|cost:?|price:?|rate:?)\s*(\d{2,5})/gi) || line.match(/(\d{3,5})\s*(?:rs|pkr|\/)/gi);
        
        if (priceMatches || lineLower.includes('gb') || lineLower.includes('card') || lineLower.includes('monthly') || lineLower.includes('weekly') || lineLower.includes('*')) {
            // Clean up name
            let cleanName = line.replace(/^[0-9]+[\.\)\-]\s*/, '').replace(/\*+/g, '').trim();
            
            // Extract prices
            let retailPrice = 0;
            let costPrice = 0;

            const allNums = line.match(/\b\d{2,5}\b/g) || [];
            const parsedNums = allNums.map(n => parseInt(n)).filter(n => n >= 50 && n <= 5000);

            if (parsedNums.length >= 2) {
                retailPrice = Math.max(parsedNums[0], parsedNums[1]);
                costPrice = Math.min(parsedNums[0], parsedNums[1]);
            } else if (parsedNums.length === 1) {
                retailPrice = parsedNums[0];
                costPrice = Math.round(retailPrice * 0.94);
            }

            // Extract Data
            let dataGb = '';
            const dataMatch = line.match(/(\d+)\s*(?:gb|mb)/i);
            if (dataMatch) dataGb = dataMatch[0].toUpperCase();

            // Extract USSD
            let ussdCode = '';
            const codeMatch = line.match(/(\*[\d\*#]+)/);
            if (codeMatch) ussdCode = codeMatch[0];

            // Extract Validity
            let validity = '30 Days';
            if (lineLower.includes('monthly') || lineLower.includes('month') || lineLower.includes('30 day')) validity = '30 Days';
            else if (lineLower.includes('weekly') || lineLower.includes('week') || lineLower.includes('7 day')) validity = '7 Days';
            else if (lineLower.includes('daily') || lineLower.includes('1 day') || lineLower.includes('24 hour')) validity = '1 Day';
            else if (lineLower.includes('15 day')) validity = '15 Days';

            // Network detection
            const net = detectNetworkFromName(cleanName, currentNetworkContext);

            // Shorten name if too long
            if (cleanName.includes('Retail:')) {
                cleanName = cleanName.split('Retail:')[0].trim();
            } else if (cleanName.includes('Rs')) {
                cleanName = cleanName.split(/Rs\s*\d+/)[0].trim();
            }
            if (cleanName.endsWith('|')) cleanName = cleanName.slice(0, -1).trim();

            if (cleanName.length > 3 && (retailPrice > 0 || dataGb || ussdCode)) {
                extracted.push({
                    network: net,
                    name: cleanName,
                    category: 'all_in_one',
                    validity: validity,
                    retailPrice: retailPrice,
                    costPrice: costPrice,
                    dataGb: dataGb,
                    onNetMins: '',
                    offNetMins: '',
                    smsCount: '',
                    ussdCode: ussdCode,
                    importSource: 'text_ocr'
                });
            }
        }
    });

    if (extracted.length === 0) {
        alert('Could not automatically parse packages from text. Please verify the format or use the 1-Click Telco Presets tab.');
        return;
    }

    appendParsedPkgItems(extracted);
}

function detectNetworkFromName(name, fallback = 'jazz') {
    const n = (name || '').toLowerCase();
    if (n.includes('jazz') || n.includes('warid') || n.includes('super duper') || n.includes('bachat')) return 'jazz';
    if (n.includes('zong') || n.includes('super star') || n.includes('shandar')) return 'zong';
    if (n.includes('telenor') || n.includes('easycard') || n.includes('djuice')) return 'telenor';
    if (n.includes('ufone') || n.includes('super card') || n.includes('sab se')) return 'ufone';
    if (n.includes('onic')) return 'onic';
    return fallback || 'jazz';
}

// -------------------------------------------------------------
// 4. 1-CLICK OFFICIAL TELCO PRESETS CATALOG
// -------------------------------------------------------------
window.TELCO_PRESETS_DATA = {
    jazz: [
        { network: 'jazz', name: 'Jazz Monthly Super Duper 10GB', category: 'all_in_one', validity: '30 Days', retailPrice: 950, costPrice: 890, dataGb: '10 GB', onNetMins: '3000', offNetMins: '300', smsCount: '3000', ussdCode: '*706#' },
        { network: 'jazz', name: 'Jazz Monthly Max 30GB', category: 'all_in_one', validity: '30 Days', retailPrice: 1400, costPrice: 1315, dataGb: '30 GB', onNetMins: '5000', offNetMins: '500', smsCount: '5000', ussdCode: '*708#' },
        { network: 'jazz', name: 'Jazz Weekly Mega 25GB', category: 'data', validity: '7 Days', retailPrice: 380, costPrice: 350, dataGb: '25 GB (10GB 2AM-2PM)', onNetMins: '0', offNetMins: '0', smsCount: '0', ussdCode: '*117*77#' },
        { network: 'jazz', name: 'Jazz Monthly Bachat 6GB', category: 'all_in_one', validity: '30 Days', retailPrice: 350, costPrice: 320, dataGb: '6 GB', onNetMins: '300', offNetMins: '50', smsCount: '1000', ussdCode: '*614#' },
        { network: 'jazz', name: 'Jazz Weekly All-In-One 5GB', category: 'all_in_one', validity: '7 Days', retailPrice: 300, costPrice: 275, dataGb: '5 GB', onNetMins: '1000', offNetMins: '60', smsCount: '1000', ussdCode: '*117*4#' },
        { network: 'jazz', name: 'Jazz Monthly Social Plus 10GB', category: 'social', validity: '30 Days', retailPrice: 330, costPrice: 300, dataGb: '10 GB (WhatsApp/FB)', onNetMins: '300', offNetMins: '50', smsCount: '1000', ussdCode: '*661#' }
    ],
    zong: [
        { network: 'zong', name: 'Zong Super Star Monthly 30GB', category: 'all_in_one', validity: '30 Days', retailPrice: 1100, costPrice: 1030, dataGb: '30 GB', onNetMins: '5000', offNetMins: '500', smsCount: '5000', ussdCode: '*6464#' },
        { network: 'zong', name: 'Zong Mega Weekly 100GB', category: 'data', validity: '7 Days', retailPrice: 450, costPrice: 415, dataGb: '100 GB (1AM-9AM)', onNetMins: '0', offNetMins: '0', smsCount: '0', ussdCode: '*220#' },
        { network: 'zong', name: 'Zong Monthly Pro 60GB', category: 'all_in_one', validity: '30 Days', retailPrice: 1550, costPrice: 1450, dataGb: '60 GB', onNetMins: '10000', offNetMins: '1000', smsCount: '10000', ussdCode: '*1500#' },
        { network: 'zong', name: 'Zong Weekly Super 30GB', category: 'all_in_one', validity: '7 Days', retailPrice: 410, costPrice: 380, dataGb: '30 GB', onNetMins: '3000', offNetMins: '100', smsCount: '3000', ussdCode: '*70#' },
        { network: 'zong', name: 'Zong Monthly WhatsApp 5GB', category: 'social', validity: '30 Days', retailPrice: 120, costPrice: 100, dataGb: '5 GB WhatsApp', onNetMins: '0', offNetMins: '0', smsCount: '0', ussdCode: '*247#' },
        { network: 'zong', name: 'Zong Daily Shandar 50MB', category: 'voice', validity: '1 Day', retailPrice: 22, costPrice: 19, dataGb: '50 MB', onNetMins: 'Unlimited', offNetMins: '0', smsCount: '800', ussdCode: '*999#' }
    ],
    telenor: [
        { network: 'telenor', name: 'Telenor EasyCard Mega 15GB', category: 'all_in_one', validity: '30 Days', retailPrice: 850, costPrice: 795, dataGb: '15 GB', onNetMins: '3000', offNetMins: '200', smsCount: '3000', ussdCode: '*350#' },
        { network: 'telenor', name: 'Telenor EasyCard 500 5GB', category: 'all_in_one', validity: '30 Days', retailPrice: 500, costPrice: 460, dataGb: '5 GB', onNetMins: '1500', offNetMins: '100', smsCount: '1500', ussdCode: '*530#' },
        { network: 'telenor', name: 'Telenor Weekly Ultimate 100GB', category: 'data', validity: '7 Days', retailPrice: 420, costPrice: 390, dataGb: '100 GB (12AM-9AM)', onNetMins: '0', offNetMins: '0', smsCount: '0', ussdCode: '*345*7#' },
        { network: 'telenor', name: 'Telenor Monthly Social Plus 6GB', category: 'social', validity: '30 Days', retailPrice: 180, costPrice: 160, dataGb: '6 GB WhatsApp/FB', onNetMins: '0', offNetMins: '0', smsCount: '0', ussdCode: '*911#' },
        { network: 'telenor', name: 'Telenor Weekly EasyCard 3GB', category: 'all_in_one', validity: '7 Days', retailPrice: 280, costPrice: 255, dataGb: '3 GB', onNetMins: '1000', offNetMins: '50', smsCount: '1000', ussdCode: '*963#' }
    ],
    ufone: [
        { network: 'ufone', name: 'Ufone Super Card Gold 30GB', category: 'all_in_one', validity: '30 Days', retailPrice: 1200, costPrice: 1130, dataGb: '30 GB', onNetMins: 'Unlimited', offNetMins: '600', smsCount: 'Unlimited', ussdCode: '*900#' },
        { network: 'ufone', name: 'Ufone Super Card Max 20GB', category: 'all_in_one', validity: '30 Days', retailPrice: 950, costPrice: 890, dataGb: '20 GB', onNetMins: '3000', offNetMins: '350', smsCount: '3000', ussdCode: '*629#' },
        { network: 'ufone', name: 'Ufone Weekly Digital 25GB', category: 'all_in_one', validity: '7 Days', retailPrice: 390, costPrice: 360, dataGb: '25 GB', onNetMins: '1000', offNetMins: '100', smsCount: '1000', ussdCode: '*222#' },
        { network: 'ufone', name: 'Ufone Monthly Heavy Internet 30GB', category: 'data', validity: '30 Days', retailPrice: 890, costPrice: 830, dataGb: '30 GB (15GB 1AM-9AM)', onNetMins: '0', offNetMins: '0', smsCount: '0', ussdCode: '*290#' },
        { network: 'ufone', name: 'Ufone Social Monthly 6GB', category: 'social', validity: '30 Days', retailPrice: 150, costPrice: 130, dataGb: '6 GB WhatsApp/FB', onNetMins: '0', offNetMins: '0', smsCount: '0', ussdCode: '*5858#' }
    ],
    onic: [
        { network: 'onic', name: 'Onic Epic Monthly Plan 30GB', category: 'all_in_one', validity: '30 Days', retailPrice: 890, costPrice: 840, dataGb: '30 GB Fast 4G', onNetMins: 'Unlimited', offNetMins: '500', smsCount: '1000', ussdCode: 'Onic App' },
        { network: 'onic', name: 'Onic Iconic Monthly Plan 100GB', category: 'all_in_one', validity: '30 Days', retailPrice: 1490, costPrice: 1390, dataGb: '100 GB Data', onNetMins: 'Unlimited', offNetMins: '1000', smsCount: '2000', ussdCode: 'Onic App' }
    ]
};

function loadNetworkPreset(networkKey) {
    const list = window.TELCO_PRESETS_DATA[networkKey] || [];
    if (list.length === 0) return;
    appendParsedPkgItems(list);
    if (window.showToast) window.showToast('success', `Loaded ${list.length} ${networkKey.toUpperCase()} plans into preview!`);
}

function loadAllTelcoPresets() {
    let combined = [];
    Object.values(window.TELCO_PRESETS_DATA).forEach(arr => {
        combined = combined.concat(arr);
    });
    appendParsedPkgItems(combined);
    if (window.showToast) window.showToast('success', `Loaded all ${combined.length} Pakistan Telco official packages!`);
}

// -------------------------------------------------------------
// 5. MATRIX TABLE RENDERING & BATCH IMPORT SUBMISSION
// -------------------------------------------------------------
function appendParsedPkgItems(items) {
    if (!items || items.length === 0) return;
    window.parsedPkgBatchItems = window.parsedPkgBatchItems.concat(items);
    renderParsedPkgTable();
}

function renderParsedPkgTable() {
    const container = document.getElementById('pkgParsedResultsContainer');
    const tbody = document.getElementById('pkgParsedTableBody');
    if (!container || !tbody) return;

    if (window.parsedPkgBatchItems.length === 0) {
        container.style.display = 'none';
        return;
    }

    container.style.display = 'block';
    let html = '';

    const netColors = {
        jazz: { bg: '#fef2f2', border: '#fca5a5', text: '#dc2626' },
        zong: { bg: '#f0fdf4', border: '#86efac', text: '#16a34a' },
        telenor: { bg: '#eff6ff', border: '#93c5fd', text: '#2563eb' },
        ufone: { bg: '#fff7ed', border: '#fdba74', text: '#ea580c' },
        onic: { bg: '#faf5ff', border: '#d8b4fe', text: '#9333ea' }
    };

    window.parsedPkgBatchItems.forEach((p, idx) => {
        const net = (p.network || 'jazz').toLowerCase();
        const col = netColors[net] || netColors.jazz;
        const retail = parseFloat(p.retailPrice || 0);
        const cost = parseFloat(p.costPrice || 0);
        const profit = Math.max(0, retail - cost);

        html += `
            <tr id="pkgRow_${idx}" style="border-bottom:1px solid #f1f5f9; transition:background 0.15s ease;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                <td style="padding:8px 10px; text-align:center;">
                    <input type="checkbox" class="pkg-row-chk" data-index="${idx}" checked onchange="updatePkgBatchTotals()">
                </td>
                <td style="padding:8px 10px;">
                    <select class="form-select" onchange="updatePkgField(${idx}, 'network', this.value)" style="padding:4px 6px; font-size:0.75rem; font-weight:800; border-radius:6px; background:${col.bg}; border-color:${col.border}; color:${col.text};">
                        <option value="jazz" ${net === 'jazz' ? 'selected' : ''}>🔴 Jazz</option>
                        <option value="zong" ${net === 'zong' ? 'selected' : ''}>🟢 Zong</option>
                        <option value="telenor" ${net === 'telenor' ? 'selected' : ''}>🔵 Telenor</option>
                        <option value="ufone" ${net === 'ufone' ? 'selected' : ''}>🟠 Ufone</option>
                        <option value="onic" ${net === 'onic' ? 'selected' : ''}>🟣 Onic</option>
                    </select>
                </td>
                <td style="padding:8px 10px;">
                    <input type="text" class="form-input" value="${escapeHtmlPkg(p.name)}" oninput="updatePkgField(${idx}, 'name', this.value)" placeholder="Package Name" style="padding:4px 8px; font-size:0.78rem; font-weight:700;">
                </td>
                <td style="padding:8px 10px;">
                    <select class="form-select" onchange="updatePkgField(${idx}, 'validity', this.value)" style="padding:4px 6px; font-size:0.75rem; font-weight:600;">
                        <option value="30 Days" ${p.validity === '30 Days' ? 'selected' : ''}>30 Days (Monthly)</option>
                        <option value="7 Days" ${p.validity === '7 Days' ? 'selected' : ''}>7 Days (Weekly)</option>
                        <option value="1 Day" ${p.validity === '1 Day' ? 'selected' : ''}>1 Day (Daily)</option>
                        <option value="15 Days" ${p.validity === '15 Days' ? 'selected' : ''}>15 Days</option>
                        <option value="3 Days" ${p.validity === '3 Days' ? 'selected' : ''}>3 Days</option>
                    </select>
                </td>
                <td style="padding:8px 10px;">
                    <input type="number" class="form-input" value="${retail}" oninput="updatePkgField(${idx}, 'retailPrice', this.value)" placeholder="950" style="padding:4px 8px; font-size:0.78rem; font-weight:800; color:#0f172a;">
                </td>
                <td style="padding:8px 10px;">
                    <input type="number" class="form-input" value="${cost}" oninput="updatePkgField(${idx}, 'costPrice', this.value)" placeholder="890" style="padding:4px 8px; font-size:0.78rem; font-weight:600; color:#64748b;">
                </td>
                <td style="padding:8px 10px;">
                    <strong id="pkgProfitDisplay_${idx}" style="color:#059669; font-size:0.8rem; font-weight:800;">+PKR ${profit}</strong>
                </td>
                <td style="padding:8px 10px;">
                    <input type="text" class="form-input" value="${escapeHtmlPkg(p.dataGb || '')}" oninput="updatePkgField(${idx}, 'dataGb', this.value)" placeholder="e.g. 10 GB" style="padding:4px 6px; font-size:0.75rem;">
                </td>
                <td style="padding:8px 10px;">
                    <input type="text" class="form-input" value="${escapeHtmlPkg(p.onNetMins || '')}" oninput="updatePkgField(${idx}, 'onNetMins', this.value)" placeholder="3000 Mins" style="padding:4px 6px; font-size:0.75rem;">
                </td>
                <td style="padding:8px 10px;">
                    <input type="text" class="form-input" value="${escapeHtmlPkg(p.ussdCode || '')}" oninput="updatePkgField(${idx}, 'ussdCode', this.value)" placeholder="*706#" style="padding:4px 6px; font-size:0.75rem; font-weight:700; color:#e11d48;">
                </td>
                <td style="padding:8px 10px; text-align:center;">
                    <button type="button" onclick="deletePkgParsedRow(${idx})" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:0.85rem;" title="Delete row">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
    updatePkgBatchTotals();
}

function updatePkgField(index, field, value) {
    if (!window.parsedPkgBatchItems[index]) return;
    if (field === 'retailPrice' || field === 'costPrice') {
        window.parsedPkgBatchItems[index][field] = parseFloat(value) || 0;
        const retail = parseFloat(window.parsedPkgBatchItems[index].retailPrice || 0);
        const cost = parseFloat(window.parsedPkgBatchItems[index].costPrice || 0);
        const profit = Math.max(0, retail - cost);
        const profitEl = document.getElementById('pkgProfitDisplay_' + index);
        if (profitEl) profitEl.innerText = '+PKR ' + profit.toLocaleString();
    } else {
        window.parsedPkgBatchItems[index][field] = value;
    }
    updatePkgBatchTotals();
}

function deletePkgParsedRow(index) {
    window.parsedPkgBatchItems.splice(index, 1);
    renderParsedPkgTable();
}

function addBlankPkgRow() {
    window.parsedPkgBatchItems.unshift({
        network: 'jazz',
        name: 'New Custom Package Plan',
        category: 'all_in_one',
        validity: '30 Days',
        retailPrice: 500,
        costPrice: 460,
        dataGb: '5 GB',
        onNetMins: '1000',
        offNetMins: '50',
        smsCount: '1000',
        ussdCode: '*123#',
        importSource: 'manual'
    });
    renderParsedPkgTable();
}

function clearAllParsedPkgs() {
    if (confirm('Are you sure you want to clear all extracted packages?')) {
        window.parsedPkgBatchItems = [];
        renderParsedPkgTable();
    }
}

function toggleAllPkgCheckboxes(checked) {
    document.querySelectorAll('.pkg-row-chk').forEach(chk => chk.checked = checked);
    updatePkgBatchTotals();
}

function updatePkgBatchTotals() {
    const countBadge = document.getElementById('pkgParsedCountBadge');
    const selCountEl = document.getElementById('pkgSelectedCount');
    const totCountEl = document.getElementById('pkgTotalCount');
    const btnCountEl = document.getElementById('pkgBtnImportCount');
    const retValEl = document.getElementById('pkgTotalRetailValue');
    const profValEl = document.getElementById('pkgTotalProfitValue');

    let selectedCount = 0;
    let totalRetail = 0;
    let totalProfit = 0;

    const checkboxes = document.querySelectorAll('.pkg-row-chk');
    checkboxes.forEach(chk => {
        if (chk.checked) {
            const idx = parseInt(chk.getAttribute('data-index'));
            const p = window.parsedPkgBatchItems[idx];
            if (p) {
                selectedCount++;
                const ret = parseFloat(p.retailPrice || 0);
                const cost = parseFloat(p.costPrice || 0);
                totalRetail += ret;
                totalProfit += Math.max(0, ret - cost);
            }
        }
    });

    const totalRows = window.parsedPkgBatchItems.length;
    if (countBadge) countBadge.innerText = totalRows;
    if (totCountEl) totCountEl.innerText = totalRows;
    if (selCountEl) selCountEl.innerText = selectedCount;
    if (btnCountEl) btnCountEl.innerText = selectedCount;
    if (retValEl) retValEl.innerText = 'PKR ' + Math.round(totalRetail).toLocaleString();
    if (profValEl) profValEl.innerText = '+PKR ' + Math.round(totalProfit).toLocaleString();
}

function submitPkgBatchImport() {
    const selected = [];
    const checkboxes = document.querySelectorAll('.pkg-row-chk');
    checkboxes.forEach(chk => {
        if (chk.checked) {
            const idx = parseInt(chk.getAttribute('data-index'));
            const p = window.parsedPkgBatchItems[idx];
            if (p && p.name && p.name.trim()) {
                selected.push(p);
            }
        }
    });

    if (selected.length === 0) {
        alert('Please select at least 1 package to import.');
        return;
    }

    const btn = document.getElementById('btnSubmitPkgBatchImport');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Importing Packages...';
    }

    fetch('../backend/import_packages.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ packages: selected })
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            alert(res.message || `Successfully imported ${selected.length} SIM package plans!`);
            window.location.reload();
        } else {
            alert(res.message || 'Failed to import packages.');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Import Packages to Catalog';
            }
        }
    })
    .catch(err => {
        alert('Network error while importing: ' + err.message);
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Import Packages to Catalog';
        }
    });
}

function escapeHtmlPkg(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>
