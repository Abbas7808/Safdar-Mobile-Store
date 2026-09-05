/* ==========================================================================
   SMZ (Safdar Mobile Zone) - Customer Storefront Application Engine
   Pure JavaScript (Vanilla HTML5 / CSS3 / JS)
   ========================================================================== */

(function () {
    'use strict';

    // App State - Instant Server-Side Hydration for 0ms Startup
    let products = (window.INITIAL_PRODUCTS && Array.isArray(window.INITIAL_PRODUCTS)) ? window.INITIAL_PRODUCTS : [];
    let categories = (window.INITIAL_CATEGORIES && Array.isArray(window.INITIAL_CATEGORIES)) ? window.INITIAL_CATEGORIES : [];
    let currentTab = 'home';
    let activeCategoryFilter = 'all';
    let activeBrandFilter = 'all';
    let catalogSearchQuery = '';
    let selectedModalProduct = null;

    // DOM Elements
    document.addEventListener('DOMContentLoaded', function () {
        initTheme();
        initNavigation();
        initHeaderScrollListener();
        initShoppingCart();

        // Immediate 0ms local render from hydrated data
        if (products.length > 0) {
            sortProductsNewestFirst();
            renderTabContent(currentTab);
            renderHomeProductsGrid();
        }

        // Silent background sync
        fetchProducts(true);
        fetchCategories();
        initWhatsAppButton();
        initScrollAnimations();
        initSecretAdminTrigger();
    });

    function initHeaderScrollListener() {
        const header = document.querySelector('.site-header');
        if (!header) return;

        let ticking = false;
        function checkScroll() {
            if (!ticking) {
                window.requestAnimationFrame(function () {
                    const scrolled = (window.pageYOffset || document.documentElement.scrollTop || 0) > 15;
                    if (scrolled) {
                        header.classList.add('is-scrolled');
                    } else {
                        header.classList.remove('is-scrolled');
                    }
                    ticking = false;
                });
                ticking = true;
            }
        }

        window.addEventListener('scroll', checkScroll, { passive: true });
        checkScroll();
    }

    // Global Theme Manager & Controller
    window.toggleStorefrontTheme = function () {
        const current = document.documentElement.getAttribute('data-theme') || 'light';
        const next = current === 'light' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', next);
        document.body.setAttribute('data-theme', next);
        if (next === 'dark') {
            document.documentElement.classList.add('dark');
            document.body.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
            document.body.classList.remove('dark');
        }
        localStorage.setItem('smz_theme', next);
        updateThemeBtnUI(next);
    };

    function initTheme() {
        const savedTheme = localStorage.getItem('smz_theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        document.body.setAttribute('data-theme', savedTheme);
        if (savedTheme === 'dark') {
            document.documentElement.classList.add('dark');
            document.body.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
            document.body.classList.remove('dark');
        }
        updateThemeBtnUI(savedTheme);

        const themeBtn = document.getElementById('themeToggleBtn');
        if (themeBtn) {
            themeBtn.onclick = function (e) {
                if (e) e.preventDefault();
                window.toggleStorefrontTheme();
            };
        }
    }

    function updateThemeBtnUI(theme) {
        const icon = document.getElementById('themeToggleIcon');
        const text = document.getElementById('themeToggleText');
        if (icon) {
            icon.className = theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
        }
        if (text) {
            text.textContent = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
        }
    }

    // Global Mobile Storefront Navigation Controller
    window.toggleStorefrontNav = function () {
        const nav = document.querySelector('.main-nav');
        if (nav) {
            nav.classList.toggle('mobile-active');
        }
    };

    // Navigation Tabs Handler
    function initNavigation() {
        window.switchTab = switchTab;
        const navButtons = document.querySelectorAll('.nav-item');
        navButtons.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const tab = this.getAttribute('data-tab');
                switchTab(tab);
            });
        });

        // Event delegation for tab links (e.g. from hero CTA or footer)
        document.addEventListener('click', function (e) {
            const targetLink = e.target.closest('[data-tab-target]');
            if (targetLink) {
                e.preventDefault();
                const tab = targetLink.getAttribute('data-tab-target');
                switchTab(tab);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    }

    function switchTab(tabName) {
        window.switchTab = switchTab;
        currentTab = tabName;
        activeBrandFilter = 'all';

        // Auto close mobile storefront navigation menu
        const nav = document.querySelector('.main-nav');
        if (nav) {
            nav.classList.remove('mobile-active');
        }

        // Update nav active classes
        document.querySelectorAll('.nav-item').forEach(function (btn) {
            if (btn.getAttribute('data-tab') === tabName) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        // Hide all tab views
        document.querySelectorAll('.page-tab').forEach(function (sec) {
            sec.style.display = 'none';
        });

        let targetSec = document.getElementById('tab-' + tabName);
        if (!targetSec) {
            // Target is a custom dynamic category (like laptops, smartwatches, tablets, etc.)
            targetSec = document.getElementById('tab-catalog');
            activeCategoryFilter = tabName;
        }

        if (targetSec) {
            targetSec.style.display = 'block';
        }

        // Render contents
        renderTabContent(tabName);
    }

    // Fetch Categories from Backend API
    function fetchCategories() {
        fetch('backend/categories.php')
            .then(function (res) { return res.json(); })
            .then(function (res) {
                if (res && res.status === 'success' && Array.isArray(res.data)) {
                    categories = res.data;
                } else {
                    categories = [];
                }
                renderCategoryFilterBar();
            })
            .catch(function () {
                categories = [];
                renderCategoryFilterBar();
            });
    }

    // Dynamic Category Filter Pills Builder on All Products Catalog
    function renderCategoryFilterBar() {
        const bar = document.getElementById('catalogCategoryFilterBar');
        if (!bar) return;

        let html = `
            <button class="filter-btn ${activeCategoryFilter === 'all' ? 'active' : ''}" data-category="all">
                <i class="fa-solid fa-layer-group"></i> All Products
            </button>
        `;

        categories.forEach(function (cat) {
            const cid = cat.id || cat.name.toLowerCase().replace(/[^a-z0-9]/g, '');
            const cName = cat.name;
            const cIcon = cat.icon || 'fa-tag';
            html += `
                <button class="filter-btn ${activeCategoryFilter === cid ? 'active' : ''}" data-category="${escapeHtml(cid)}">
                    <i class="fa-solid ${escapeHtml(cIcon)}"></i> ${escapeHtml(cName)}
                </button>
            `;
        });

        bar.innerHTML = html;

        // Attach Click Listener to Filter Buttons
        bar.querySelectorAll('.filter-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                bar.querySelectorAll('.filter-btn').forEach(function (b) { b.classList.remove('active'); });
                this.classList.add('active');
                activeCategoryFilter = this.getAttribute('data-category') || 'all';
                renderCatalogGrid();
            });
        });
    }

    // Live Search on All Products Catalog
    window.handleCatalogLiveSearch = function (query) {
        catalogSearchQuery = (query || '').trim().toLowerCase();
        renderCatalogGrid();
    };

    // Render All Products & Categories Grid
    function renderCatalogGrid() {
        const grid = document.getElementById('catalogProductGrid');
        if (!grid) return;

        let filtered = products.slice();

        // 1. Category Filter
        if (activeCategoryFilter !== 'all') {
            filtered = filtered.filter(function (p) {
                const pCat = (p.category || p.categoryId || '').toLowerCase();
                if (activeCategoryFilter === 'mobiles') {
                    return pCat === 'mobiles' || pCat === 'phones' || pCat === 'smartphones';
                } else if (activeCategoryFilter === 'accessories') {
                    return pCat === 'accessories' || pCat === 'chargers' || pCat === 'airbuds' || pCat === 'protectors';
                } else if (activeCategoryFilter === 'cctv') {
                    return pCat === 'cctv' || pCat === 'cameras' || pCat === 'security';
                } else {
                    return pCat === activeCategoryFilter.toLowerCase();
                }
            });
        }

        // 2. Search Query Filter
        if (catalogSearchQuery) {
            filtered = filtered.filter(function (p) {
                const name = (p.name || '').toLowerCase();
                const brand = (p.brand || '').toLowerCase();
                const cat = (p.category || p.categoryId || '').toLowerCase();
                const specs = (p.specs || []).join(' ').toLowerCase();
                return name.includes(catalogSearchQuery) || brand.includes(catalogSearchQuery) || cat.includes(catalogSearchQuery) || specs.includes(catalogSearchQuery);
            });
        }

        if (filtered.length === 0) {
            grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:60px 20px; background:var(--card-bg); border:1.5px dashed var(--smz-border); border-radius:16px;"><i class="fa-solid fa-box-open" style="font-size:3rem; color:#94a3b8; margin-bottom:14px;"></i><h3 style="font-size:1.25rem; margin-bottom:8px; color:var(--text-main);">No Matching Products Found</h3><p style="color:var(--text-secondary); max-width:480px; margin:0 auto 16px auto; font-size:0.92rem;">Try changing your category filter or search term. You can also contact us directly on WhatsApp for any product availability.</p><a href="https://wa.me/923339688007?text=Hello%20Safdar%20Mobile%20Store!%20I%20want%20to%20inquire%20about%20products" target="_blank" class="btn btn-whatsapp circle-style-btn" style="padding:10px 24px; font-weight:700;"><i class="fa-brands fa-whatsapp"></i> Chat on WhatsApp (03339688007)</a></div>';
            return;
        }

        grid.innerHTML = filtered.map(function (p) {
            return generateProductCardHtml(p);
        }).join('');

        attachProductCardHandlers(grid);
    }

    // Home Page Featured & Latest Products Grid Renderer
    let activeHomeCategoryFilter = 'all';

    function renderHomeProductsGrid() {
        const grid = document.getElementById('homeFeaturedProductsGrid');
        if (!grid) return;

        let filtered = products.slice();

        if (activeHomeCategoryFilter !== 'all') {
            filtered = filtered.filter(function (p) {
                const pCat = (p.category || p.categoryId || '').toLowerCase();
                if (activeHomeCategoryFilter === 'mobiles') {
                    return pCat === 'mobiles' || pCat === 'phones' || pCat === 'smartphones';
                } else if (activeHomeCategoryFilter === 'accessories') {
                    return pCat === 'accessories' || pCat === 'chargers' || pCat === 'airbuds' || pCat === 'protectors';
                } else if (activeHomeCategoryFilter === 'cctv') {
                    return pCat === 'cctv' || pCat === 'cameras' || pCat === 'security';
                } else {
                    return pCat === activeHomeCategoryFilter.toLowerCase();
                }
            });
        }

        if (filtered.length === 0) {
            grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:40px 20px; background:var(--card-bg); border:1.5px dashed var(--smz-border); border-radius:16px;"><i class="fa-solid fa-box-open" style="font-size:2.5rem; color:#94a3b8; margin-bottom:10px;"></i><h3 style="font-size:1.15rem; color:var(--text-main); margin-bottom:6px;">No Products in this Category</h3><p style="color:var(--text-secondary); font-size:0.88rem;">Products for this section are being updated. Contact WhatsApp (03339688007) for live stock quotes.</p></div>';
            return;
        }

        // Display up to 8 newest products
        grid.innerHTML = filtered.slice(0, 8).map(function (p) {
            return generateProductCardHtml(p);
        }).join('');

        attachProductCardHandlers(grid);

        // Bind home category filter buttons
        const homeFilterBar = document.getElementById('homeCategoryFilterBar');
        if (homeFilterBar) {
            homeFilterBar.querySelectorAll('.filter-btn').forEach(function (btn) {
                btn.onclick = function () {
                    homeFilterBar.querySelectorAll('.filter-btn').forEach(function (b) { b.classList.remove('active'); });
                    this.classList.add('active');
                    activeHomeCategoryFilter = this.getAttribute('data-home-category') || 'all';
                    renderHomeProductsGrid();
                };
            });
        }
    }

    // Fetch Products from Backend API
    function fetchProducts() {
        fetch('backend/products.php')
            .then(function (res) { return res.json(); })
            .then(function (res) {
                if (res && res.status === 'success' && Array.isArray(res.data)) {
                    products = res.data;
                } else {
                    products = [];
                }
                sortProductsNewestFirst();
                renderTabContent(currentTab);
                renderHomeProductsGrid();
            })
            .catch(function () {
                products = [];
                sortProductsNewestFirst();
                renderTabContent(currentTab);
                renderHomeProductsGrid();
            });
    }

    function sortProductsNewestFirst() {
        if (!Array.isArray(products)) return;
        products.sort(function(a, b) {
            var timeA = a.createdAt ? new Date(a.createdAt).getTime() : 0;
            var timeB = b.createdAt ? new Date(b.createdAt).getTime() : 0;
            if (timeA && timeB && timeA !== timeB) return timeB - timeA;

            var numA = parseInt((a.id || '').replace(/\D/g, '')) || 0;
            var numB = parseInt((b.id || '').replace(/\D/g, '')) || 0;
            return numB - numA;
        });
    }

    // Fallback Initial Products Data
    function getFallbackProducts() {
        return [];
    }

    // Render Tab Content
    function renderTabContent(tab) {
        if (tab === 'home') {
            renderHomeProductsGrid();
        } else if (tab === 'catalog') {
            renderCategoryFilterBar();
            renderCatalogGrid();
        } else if (tab === 'mobiles') {
            renderProductCatalog('mobiles', 'mobilesProductGrid', 'mobilesFilterBar');
        } else if (tab === 'accessories') {
            renderProductCatalog('accessories', 'accessoriesProductGrid', 'accessoriesFilterBar');
        } else if (tab === 'cctv') {
            renderProductCatalog('cctv', 'cctvProductGrid', 'cctvFilterBar');
        } else {
            // Custom category (e.g. laptops, smartwatches, etc.)
            activeCategoryFilter = tab;
            renderCategoryFilterBar();
            renderCatalogGrid();
        }
    }

    function generateProductCardHtml(p) {
        const hasOffer = !!(p.hasOnlineOffer && (p.onlinePrice > 0 || p.discountValue > 0));
        const badgeText = p.badge || (p.isNewArrival ? 'NEW ARRIVAL' : 'NEW LISTED');
        const tag = `<span class="product-tag new-arrival-tag">${escapeHtml(badgeText)}</span>`;
        
        // Online Offer Glowing Badge
        const offerBadgeHtml = hasOffer 
            ? `<span class="online-offer-badge"><i class="fa-solid fa-tags"></i> ${escapeHtml(p.offerBadge || '10% OFF ONLINE')}</span>`
            : '';

        let imgUrl = p.image || '';
        if (imgUrl.startsWith('../')) {
            imgUrl = imgUrl.substring(3);
        }

        const regularPrice = Number(p.sellingPrice || p.priceNumeric || 0);
        const onlinePrice = hasOffer && Number(p.onlinePrice) > 0 ? Number(p.onlinePrice) : regularPrice;
        const savingAmount = Math.max(0, regularPrice - onlinePrice);

        // Pricing HTML with Online Discount Highlight
        let priceHtml = '';
        if (hasOffer && savingAmount > 0) {
            priceHtml = `
                <div class="product-pricing-wrap">
                    <del class="regular-price-del">PKR ${regularPrice.toLocaleString()}</del>
                    <span class="online-price-highlight">PKR ${onlinePrice.toLocaleString()}</span>
                    <span class="online-saving-chip">SAVE PKR ${savingAmount.toLocaleString()}</span>
                </div>
            `;
        } else {
            priceHtml = `
                <div class="price-range">${escapeHtml(p.priceRange || ('PKR ' + regularPrice.toLocaleString()))}</div>
            `;
        }

        return `
            <div class="product-card" style="position:relative;">
                ${tag}
                ${offerBadgeHtml}
                <div class="product-img-box" onclick="window.openProductDetailsModal('${escapeHtml(p.id)}')" style="cursor:pointer;" title="Click to view details">
                    <img src="${escapeHtml(imgUrl)}" alt="${escapeHtml(p.name)}" loading="lazy" decoding="async" onerror="this.src='https://images.unsplash.com/photo-1598327105666-5b89351aff97?w=600&auto=format&fit=crop&q=80'">
                </div>
                <div class="product-info">
                    <h4 onclick="window.openProductDetailsModal('${escapeHtml(p.id)}')" style="cursor:pointer;" title="${escapeHtml(p.name)}">${escapeHtml(p.name)}</h4>
                    ${priceHtml}
                </div>
                <div class="product-card-actions">
                    <button type="button" class="btn btn-details-card" onclick="window.openProductDetailsModal('${escapeHtml(p.id)}')" title="View Full Details &amp; Specifications">
                        <i class="fa-solid fa-circle-info"></i> Details
                    </button>
                    <button type="button" class="btn btn-add-cart" data-cart-id="${escapeHtml(p.id)}" title="Add to Cart">
                        <i class="fa-solid fa-cart-plus"></i> Cart
                    </button>
                    <button type="button" class="btn btn-primary open-inquiry-btn" data-product-id="${escapeHtml(p.id)}" title="Instant Buy Now">
                        <i class="fa-brands fa-whatsapp"></i> Buy
                    </button>
                </div>
            </div>
        `;
    }

    // PRODUCT DETAILS MODAL CONTROLLER
    window.openProductDetailsModal = function (prodId) {
        let allProds = (typeof products !== 'undefined' && products && products.length) ? products : (window.INITIAL_PRODUCTS || []);
        let p = allProds.find(function (item) { return String(item.id) === String(prodId); });
        if (!p) return;

        const modal = document.getElementById('productDetailsModal');
        if (!modal) return;

        let imgUrl = p.image || '';
        if (imgUrl.startsWith('../')) imgUrl = imgUrl.substring(3);
        if (!imgUrl) imgUrl = 'https://images.unsplash.com/photo-1598327105666-5b89351aff97?w=600&auto=format&fit=crop&q=80';

        const regularPrice = Number(p.sellingPrice || p.priceNumeric || 0);
        const hasOffer = !!(p.hasOnlineOffer && (p.onlinePrice > 0 || p.discountValue > 0));
        const onlinePrice = hasOffer && Number(p.onlinePrice) > 0 ? Number(p.onlinePrice) : regularPrice;
        const savingAmount = Math.max(0, regularPrice - onlinePrice);
        const unitSuffix = (p.unit && p.unit !== 'pcs' && p.unit !== 'piece') ? (' / ' + (p.unitLabel || p.unit)) : '';

        // Populate modal fields
        const imgEl = document.getElementById('detailImage');
        if (imgEl) {
            imgEl.src = imgUrl;
            imgEl.alt = p.name || 'Product';
        }

        const badgeEl = document.getElementById('detailBadge');
        if (badgeEl) {
            badgeEl.textContent = p.badge || (p.isNewArrival ? 'NEW ARRIVAL' : (hasOffer ? (p.offerBadge || 'SPECIAL OFFER') : 'OFFICIAL'));
        }

        const skuEl = document.getElementById('detailSku');
        if (skuEl) skuEl.textContent = 'SKU: ' + (p.sku || 'N/A');

        const barEl = document.getElementById('detailBarcode');
        if (barEl) barEl.textContent = 'Barcode: ' + (p.barcode || 'N/A');

        const catEl = document.getElementById('detailCategory');
        if (catEl) catEl.textContent = (p.category || p.categoryId || 'General').toUpperCase();

        const brandEl = document.getElementById('detailBrand');
        if (brandEl) brandEl.textContent = p.brand || 'Official';

        const stockEl = document.getElementById('detailStock');
        if (stockEl) {
            const st = Number(p.stock || 0);
            if (st <= 0) {
                stockEl.textContent = 'OUT OF STOCK';
                stockEl.style.background = '#fee2e2';
                stockEl.style.color = '#dc2626';
            } else {
                stockEl.textContent = `IN STOCK (${st}${unitSuffix})`;
                stockEl.style.background = '#dcfce7';
                stockEl.style.color = '#15803d';
            }
        }

        const titleEl = document.getElementById('detailTitle');
        if (titleEl) titleEl.textContent = p.name || 'Product Details';

        const sellPriceEl = document.getElementById('detailSellingPrice');
        if (sellPriceEl) {
            sellPriceEl.textContent = 'PKR ' + (hasOffer ? onlinePrice.toLocaleString() : regularPrice.toLocaleString()) + unitSuffix;
        }

        const regPriceEl = document.getElementById('detailRegularPrice');
        const saveChipEl = document.getElementById('detailSavingChip');
        if (hasOffer && savingAmount > 0) {
            if (regPriceEl) {
                regPriceEl.textContent = 'PKR ' + regularPrice.toLocaleString() + unitSuffix;
                regPriceEl.style.display = 'inline';
            }
            if (saveChipEl) {
                saveChipEl.textContent = 'SAVE PKR ' + savingAmount.toLocaleString();
                saveChipEl.style.display = 'inline-block';
            }
        } else {
            if (regPriceEl) regPriceEl.style.display = 'none';
            if (saveChipEl) saveChipEl.style.display = 'none';
        }

        const offerTagEl = document.getElementById('detailOfferTagline');
        const offerTextEl = document.getElementById('detailOfferText');
        if (hasOffer && (p.offerTagline || p.offerBadge)) {
            if (offerTextEl) offerTextEl.textContent = p.offerTagline || 'Pay 100% advance online to claim special store discount!';
            if (offerTagEl) offerTagEl.style.display = 'block';
        } else {
            if (offerTagEl) offerTagEl.style.display = 'none';
        }

        // Specs List
        const specsListEl = document.getElementById('detailSpecsList');
        if (specsListEl) {
            let specsArr = Array.isArray(p.specs) ? p.specs.filter(Boolean) : [];
            if (specsArr.length === 0) {
                specsArr = [
                    '100% Brand New & Genuine Sealed Pack',
                    'Official Brand Checking Warranty Included',
                    'Same-Day Fast Dispatch across Pakistan',
                    'All Original Accessories in Box'
                ];
            }
            specsListEl.innerHTML = specsArr.map(function (s) {
                return '<li style="padding: 4px 0; font-size: 0.82rem; color: var(--text-secondary); display: flex; align-items: center; gap: 8px;"><i class="fa-solid fa-circle-check" style="color: #10b981;"></i> ' + escapeHtml(s) + '</li>';
            }).join('');
        }

        // Reviews Link
        const revBtn = document.getElementById('detailReviewsBtn');
        if (revBtn) {
            revBtn.onclick = function () {
                window.closeProductDetailsModal();
                if (typeof openReviewsModal === 'function') openReviewsModal(p);
            };
        }

        // Action Buttons inside Modal
        const addCartBtn = document.getElementById('detailAddToCartBtn');
        if (addCartBtn) {
            addCartBtn.onclick = function () {
                if (typeof addToCart === 'function') {
                    addToCart(p.id, 1);
                    addCartBtn.innerHTML = '<i class="fa-solid fa-check"></i> Added to Cart!';
                    setTimeout(function () {
                        addCartBtn.innerHTML = '<i class="fa-solid fa-cart-plus"></i> Add to Cart';
                    }, 1200);
                }
            };
        }

        const buyNowBtn = document.getElementById('detailBuyNowBtn');
        if (buyNowBtn) {
            buyNowBtn.onclick = function () {
                window.closeProductDetailsModal();
                if (typeof addToCart === 'function') addToCart(p.id, 1);
                if (typeof openMultiCheckout === 'function') openMultiCheckout();
            };
        }

        modal.style.display = 'flex';
    };

    window.closeProductDetailsModal = function () {
        const modal = document.getElementById('productDetailsModal');
        if (modal) modal.style.display = 'none';
    };

    function attachProductCardHandlers(grid) {
        // Attach add to cart button handlers
        grid.querySelectorAll('.btn-add-cart').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const prodId = this.getAttribute('data-cart-id');
                if (prodId) {
                    addToCart(prodId);
                    btn.classList.add('added-pulse');
                    const origHtml = btn.innerHTML;
                    btn.innerHTML = '<i class="fa-solid fa-check"></i> Added!';
                    setTimeout(function () {
                        btn.classList.remove('added-pulse');
                        btn.innerHTML = origHtml;
                    }, 1200);
                }
            });
        });

        // Attach Buy Now button handlers (Direct Advance Payment Checkout)
        grid.querySelectorAll('.open-inquiry-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const prodId = this.getAttribute('data-product-id');
                const prod = products.find(function (item) { return item.id === prodId; });
                if (prod) {
                    addToCart(prodId, 1);
                    openMultiCheckout();
                }
            });
        });

        // Attach customer reviews modal trigger handlers
        grid.querySelectorAll('.open-reviews-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const prodId = this.getAttribute('data-product-id');
                const prod = products.find(function (item) { return item.id === prodId; });
                if (prod) openReviewsModal(prod);
            });
        });
    }

    // Generic Product Catalog Renderer with Brand Filtering & Online Offers
    function renderProductCatalog(category, gridId, filterBarId) {
        const grid = document.getElementById(gridId);
        if (!grid) return;

        let filtered = products.filter(function (p) {
            const pCat = (p.category || p.categoryId || '').toLowerCase();
            if (category === 'mobiles') {
                return pCat === 'mobiles' || pCat === 'phones' || pCat === 'smartphones' || pCat === 'mobile';
            } else if (category === 'accessories') {
                return pCat === 'accessories' || pCat === 'chargers' || pCat === 'airbuds' || pCat === 'protectors' || pCat === 'powerbanks' || pCat === 'cables' || pCat === 'covers' || pCat === 'smartwatches';
            } else if (category === 'cctv') {
                return pCat === 'cctv' || pCat === 'cameras' || pCat === 'security';
            } else {
                return pCat === category.toLowerCase();
            }
        });

        if (activeBrandFilter !== 'all') {
            filtered = filtered.filter(function (p) {
                const target = activeBrandFilter.toLowerCase();
                const b = (p.brand || '').toLowerCase();
                const sub = (p.subCategory || p.sub_category || '').toLowerCase();
                const cat = (p.category || p.categoryId || '').toLowerCase();
                const name = (p.name || '').toLowerCase();

                if (target === 'chargers') {
                    return b === 'chargers' || sub === 'chargers' || cat === 'chargers' || name.includes('charger') || name.includes('adapter');
                } else if (target === 'airbuds') {
                    return b === 'airbuds' || sub === 'airbuds' || cat === 'airbuds' || name.includes('airbud') || name.includes('earphone') || name.includes('earbud') || name.includes('headphone') || name.includes('tws');
                } else if (target === 'vehicle chargers' || target === 'car chargers') {
                    return b.includes('vehicle') || b.includes('car') || sub.includes('vehicle') || sub.includes('car') || name.includes('car charger') || name.includes('vehicle');
                } else if (target === 'screen protectors' || target === 'protectors') {
                    return b.includes('protector') || sub.includes('protector') || cat.includes('protector') || name.includes('protector') || name.includes('glass') || name.includes('membrane');
                } else if (target === 'powerbanks' || target === 'power banks') {
                    return b.includes('power') || sub.includes('power') || name.includes('power bank') || name.includes('powerbank') || name.includes('battery');
                } else if (target === 'smartwatches' || target === 'smart watches') {
                    return b.includes('watch') || sub.includes('watch') || name.includes('watch') || name.includes('band');
                } else if (target === 'cables') {
                    return b.includes('cable') || sub.includes('cable') || name.includes('cable') || name.includes('otg') || name.includes('type-c') || name.includes('cord');
                } else if (target === 'samsung') {
                    return b.includes('samsung') || sub.includes('samsung') || name.includes('samsung') || name.includes('galaxy');
                } else if (target === 'apple') {
                    return b.includes('apple') || sub.includes('apple') || name.includes('apple') || name.includes('iphone') || name.includes('ipad');
                } else if (target === 'infinix') {
                    return b.includes('infinix') || sub.includes('infinix') || name.includes('infinix');
                } else if (target === 'tecno') {
                    return b.includes('tecno') || sub.includes('tecno') || name.includes('tecno');
                } else if (target === 'xiaomi') {
                    return b.includes('xiaomi') || b.includes('redmi') || b.includes('poco') || sub.includes('xiaomi') || name.includes('xiaomi') || name.includes('redmi') || name.includes('poco');
                } else if (target === 'vivo') {
                    return b.includes('vivo') || b.includes('oppo') || sub.includes('vivo') || name.includes('vivo') || name.includes('oppo');
                } else if (target === 'hikvision') {
                    return b.includes('hikvision') || sub.includes('hikvision') || name.includes('hikvision') || name.includes('hik');
                } else if (target === 'dahua') {
                    return b.includes('dahua') || sub.includes('dahua') || name.includes('dahua');
                } else if (target === 'solar cctv' || target === 'solar') {
                    return b.includes('solar') || sub.includes('solar') || name.includes('solar') || name.includes('4g');
                } else if (target === 'dvr recorders' || target === 'dvr') {
                    return b.includes('dvr') || b.includes('nvr') || sub.includes('dvr') || sub.includes('nvr') || name.includes('dvr') || name.includes('nvr') || name.includes('recorder');
                }

                return b === target || sub === target || cat === target || name.includes(target);
            });
        }

        if (filtered.length === 0) {
            grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:60px 20px; background:var(--card-bg); border:1.5px dashed var(--smz-border); border-radius:16px;"><i class="fa-solid fa-box-open" style="font-size:3rem; color:#94a3b8; margin-bottom:14px;"></i><h3 style="font-size:1.25rem; margin-bottom:8px; color:var(--text-main);">No Products Added Yet</h3><p style="color:var(--text-secondary); max-width:480px; margin:0 auto 16px auto; font-size:0.92rem;">Our inventory catalog is currently being updated with brand-new stock. Contact us directly on WhatsApp for live price quotes and availability.</p><a href="https://wa.me/923339688007?text=Hello%20Safdar%20Mobile%20Store!%20I%20want%20to%20inquire%20about%20products" target="_blank" class="btn btn-whatsapp circle-style-btn" style="padding:10px 24px; font-weight:700;"><i class="fa-brands fa-whatsapp"></i> Chat on WhatsApp (03339688007)</a></div>';
            return;
        }

        grid.innerHTML = filtered.map(function (p) {
            return generateProductCardHtml(p);
        }).join('');

        attachProductCardHandlers(grid);

        // Bind filter bar buttons if present
        const filterBar = document.getElementById(filterBarId);
        if (filterBar) {
            filterBar.querySelectorAll('.filter-btn').forEach(function (fBtn) {
                fBtn.addEventListener('click', function () {
                    filterBar.querySelectorAll('.filter-btn').forEach(function (b) { b.classList.remove('active'); });
                    this.classList.add('active');
                    activeBrandFilter = this.getAttribute('data-filter');
                    renderProductCatalog(category, gridId, filterBarId);
                });
            });
        }
    }

    let uploadedPaymentProofUrl = '';

    // Inquiry & Order Modal (With Online Offer Support)
    function openInquiryModal(product) {
        selectedModalProduct = product;
        uploadedPaymentProofUrl = '';
        const modal = document.getElementById('inquiryModal');
        if (!modal) return;

        const hasOffer = !!(product.hasOnlineOffer && (product.onlinePrice > 0 || product.discountValue > 0));
        const regularPrice = Number(product.sellingPrice || product.priceNumeric || 0);
        const onlinePrice = hasOffer && Number(product.onlinePrice) > 0 ? Number(product.onlinePrice) : regularPrice;
        const saving = Math.max(0, regularPrice - onlinePrice);

        document.getElementById('modalProductName').textContent = product.name;

        // Display discounted online price
        const priceEl = document.getElementById('modalProductPrice');
        if (hasOffer && saving > 0) {
            priceEl.innerHTML = `
                <del style="color:#94a3b8; font-size:0.9rem; font-weight:600; margin-right:8px;">PKR ${regularPrice.toLocaleString()}</del>
                <span style="color:#D71920; font-size:1.35rem; font-weight:900;">PKR ${onlinePrice.toLocaleString()}</span>
                <span style="background:#ecfdf5; color:#065f46; font-size:0.75rem; font-weight:800; padding:2px 8px; border-radius:4px; border:1px solid #a7f3d0; margin-left:8px;">SAVE PKR ${saving.toLocaleString()}</span>
            `;
        } else {
            priceEl.textContent = product.priceRange || ('PKR ' + regularPrice.toLocaleString());
        }

        // Show/hide Online Offer Alert in Order modal
        const offerBox = document.getElementById('modalOnlineOfferNotice');
        const offerTitle = document.getElementById('modalOnlineOfferTitle');
        const offerTagline = document.getElementById('modalOnlineOfferTagline');

        if (hasOffer && offerBox) {
            offerBox.style.display = 'block';
            if (offerTitle) offerTitle.textContent = 'Special Online Offer: ' + (product.offerBadge || 'DISCOUNT APPLIED');
            if (offerTagline) offerTagline.textContent = product.offerTagline || 'Pay 100% advance online & get special discount + priority dispatch!';
        } else if (offerBox) {
            offerBox.style.display = 'none';
        }

        const statusEl = document.getElementById('paymentUploadStatus');
        if (statusEl) statusEl.innerHTML = '';

        const previewContainer = document.getElementById('paymentScreenshotPreview');
        if (previewContainer) previewContainer.style.display = 'none';

        const fileInput = document.getElementById('inquiryPaymentProof');
        if (fileInput) fileInput.value = '';

        const trxInput = document.getElementById('inquiryTrxId');
        if (trxInput) trxInput.value = '';

        modal.style.display = 'flex';
    }

    window.closeInquiryModal = function () {
        const modal = document.getElementById('inquiryModal');
        if (modal) modal.style.display = 'none';
        selectedModalProduct = null;
        uploadedPaymentProofUrl = '';
    };

    window.handlePaymentScreenshotUpload = function (input) {
        if (!input.files || !input.files[0]) return;

        const file = input.files[0];
        const statusEl = document.getElementById('paymentUploadStatus');
        const previewContainer = document.getElementById('paymentScreenshotPreview');
        const previewImg = document.getElementById('previewImage');

        if (statusEl) {
            statusEl.style.color = '#3b82f6';
            statusEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading payment screenshot...';
        }

        const formData = new FormData();
        formData.append('payment_proof', file);

        fetch('backend/upload_payment.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data && data.status === 'success') {
                uploadedPaymentProofUrl = window.location.origin + '/' + data.data.url;
                if (statusEl) {
                    statusEl.style.color = '#10b981';
                    statusEl.innerHTML = '<i class="fa-solid fa-circle-check"></i> Screenshot uploaded successfully!';
                }
                if (previewImg && previewContainer) {
                    previewImg.src = data.data.url;
                    previewContainer.style.display = 'block';
                }
            } else {
                if (statusEl) {
                    statusEl.style.color = '#ef4444';
                    statusEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Upload failed: ' + (data.message || 'Error uploading file');
                }
            }
        })
        .catch(err => {
            console.error(err);
            if (statusEl) {
                statusEl.style.color = '#ef4444';
                statusEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Upload error. You can also send screenshot via WhatsApp.';
            }
        });
    };

    // Global 1-Click Clipboard Copy Helper
    window.copyToClipboard = function (text, btn) {
        if (!text) return;
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(onSuccess, fallback);
        } else {
            fallback();
        }

        function onSuccess() {
            if (btn) {
                const prev = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
                setTimeout(() => { btn.innerHTML = prev; }, 2000);
            }
        }

        function fallback() {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            try {
                document.execCommand('copy');
                onSuccess();
            } catch (err) {
                console.error('Failed to copy', err);
            }
            document.body.removeChild(ta);
        }
    };

    window.sendWhatsAppInquiry = function () {
        if (!selectedModalProduct) return;

        const customerName = document.getElementById('inquiryCustomerName')?.value || 'Valued Customer';
        const city = document.getElementById('inquiryCustomerCity')?.value || 'Pakistan';
        const paymentMethod = document.getElementById('inquiryPaymentMethod')?.value || 'ZINDIGI (JS Bank) / Raast QR';
        const trxId = document.getElementById('inquiryTrxId')?.value || '';
        const note = document.getElementById('inquiryCustomerNotes')?.value || 'Please share stock availability and payment confirmation.';

        let proofLine = '';
        if (uploadedPaymentProofUrl) {
            proofLine = `🖼️ *Payment Screenshot URL:* ${uploadedPaymentProofUrl}\n`;
        } else {
            proofLine = `📸 *Payment Screenshot:* (Will attach screenshot directly in this chat)\n`;
        }

        let trxLine = '';
        if (trxId) {
            trxLine = `💳 *Transaction ID / TRX:* ${trxId}\n`;
        }

        const hasOffer = !!(selectedModalProduct.hasOnlineOffer && (selectedModalProduct.onlinePrice > 0 || selectedModalProduct.discountValue > 0));
        const regularPrice = Number(selectedModalProduct.sellingPrice || selectedModalProduct.priceNumeric || 0);
        const onlinePrice = hasOffer && Number(selectedModalProduct.onlinePrice) > 0 ? Number(selectedModalProduct.onlinePrice) : regularPrice;
        const saving = Math.max(0, regularPrice - onlinePrice);

        let offerText = '';
        if (hasOffer && saving > 0) {
            offerText = `🎉 *Online Purchase Offer Applied:* ${selectedModalProduct.offerBadge || '10% OFF'}\n` +
                        `🏷️ *Regular Price:* PKR ${regularPrice.toLocaleString()}\n` +
                        `💰 *Discounted Online Price:* PKR ${onlinePrice.toLocaleString()} (Saved PKR ${saving.toLocaleString()})\n`;
        } else {
            offerText = `*Price:* ${selectedModalProduct.priceRange || ('PKR ' + regularPrice.toLocaleString())}\n`;
        }

        const currentDateStr = new Date().toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' });

        const message = 
`🧾 ═══════════════════════ 🧾
   *SAFDAR MOBILE STORE — HANGU*
   _Direct Product Order Slip_
🧾 ═══════════════════════ 🧾

📅 *DATE:* ${currentDateStr}
🏷️ *PRODUCT:* *${selectedModalProduct.name}*
${offerText}
━━━━━━━━━━━━━━━━━━━━━━━━━
👤 *CUSTOMER & DELIVERY PROFILE*
━━━━━━━━━━━━━━━━━━━━━━━━━
▪ *Customer Name:* ${customerName}
▪ *Delivery City:* ${city}
${note ? `▪ *Special Note / Model:* ${note}\n` : ''}
━━━━━━━━━━━━━━━━━━━━━━━━━
💳 *1-TIME ADVANCE PAYMENT DETAILS*
━━━━━━━━━━━━━━━━━━━━━━━━━
▪ *Method Selected:* ${paymentMethod}
▪ *Merchant Account:* Safdar Mobile Store
▪ *Raast ID & Mobile:* *03339688007*
▪ *Official IBAN:* *PK28JSBL9999903339688007*
${trxLine}${proofLine}▪ *Courier / Shipping:* _Payable to TCS / Leopards on Arrival_

━━━━━━━━━━━━━━━━━━━━━━━━━
📌 *ORDER CONFIRMATION:*
Please confirm stock availability & dispatch time!

🛡️ _Official Helpline: 0333-9688007 | safdarmobilestore.com_`;

        const encoded = encodeURIComponent(message);
        window.open(`https://wa.me/923339688007?text=${encoded}`, '_blank');
        closeInquiryModal();
    };

    // =========================================================================
    // CUSTOMER REVIEWS MODAL ENGINE & SUBMISSION
    // =========================================================================
    let currentReviewProduct = null;

    function openReviewsModal(product) {
        currentReviewProduct = product;
        const modal = document.getElementById('reviewsModal');
        if (!modal) return;

        document.getElementById('reviewsModalProductName').textContent = product.name;
        document.getElementById('reviewProductId').value = product.id;

        // Reset form
        const form = document.getElementById('customerReviewForm');
        if (form) form.reset();
        setReviewRating(5);

        const alertEl = document.getElementById('reviewSubmitAlert');
        if (alertEl) alertEl.style.display = 'none';

        // Show offer if active
        const offerBox = document.getElementById('reviewsModalOfferBox');
        if (product.hasOnlineOffer && offerBox) {
            offerBox.style.display = 'block';
            document.getElementById('reviewsModalOfferBadge').textContent = product.offerBadge || 'ONLINE OFFER:';
            document.getElementById('reviewsModalOfferTagline').textContent = product.offerTagline || 'Pay online & get special discounted price!';
        } else if (offerBox) {
            offerBox.style.display = 'none';
        }

        // Fetch and display reviews
        loadProductReviews(product.id);

        modal.style.display = 'flex';
    }

    window.closeReviewsModal = function() {
        const modal = document.getElementById('reviewsModal');
        if (modal) modal.style.display = 'none';
        currentReviewProduct = null;
    };

    function loadProductReviews(productId) {
        const listEl = document.getElementById('reviewsModalList');
        const avgScoreEl = document.getElementById('reviewsModalAvgScore');
        const countEl = document.getElementById('reviewsModalCount');
        const starAvgEl = document.getElementById('reviewsModalStarAverage');

        if (listEl) listEl.innerHTML = '<div style="text-align:center; padding:20px; color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> Loading reviews...</div>';

        fetch(`backend/reviews.php?productId=${encodeURIComponent(productId)}`)
            .then(res => res.json())
            .then(data => {
                if (data && data.status === 'success') {
                    const revs = data.data.reviews || [];
                    const avg = data.data.averageRating || 5.0;
                    const count = data.data.count || 0;

                    if (avgScoreEl) avgScoreEl.textContent = `${avg} / 5.0`;
                    if (countEl) countEl.textContent = `(${count} Review${count !== 1 ? 's' : ''})`;

                    // Render Stars
                    if (starAvgEl) {
                        let starsHtml = '';
                        for (let i = 1; i <= 5; i++) {
                            starsHtml += i <= Math.round(avg) ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>';
                        }
                        starAvgEl.innerHTML = starsHtml;
                    }

                    if (revs.length === 0) {
                        listEl.innerHTML = `
                            <div style="text-align:center; padding:24px; color:var(--text-muted); background:var(--box-bg-light); border-radius:8px;">
                                <i class="fa-regular fa-comment-dots" style="font-size:1.8rem; margin-bottom:6px; display:block; color:#cbd5e1;"></i>
                                No customer reviews yet. Be the first to review this product below!
                            </div>
                        `;
                    } else {
                        listEl.innerHTML = revs.map(r => {
                            let stars = '';
                            const rVal = intval(r.rating || 5);
                            for (let i = 1; i <= 5; i++) {
                                stars += i <= rVal ? '<i class="fa-solid fa-star"></i> ' : '<i class="fa-regular fa-star"></i> ';
                            }
                            return `
                                <div class="review-item-card">
                                    <div class="review-author-row">
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <span class="review-author-name">${escapeHtml(r.customerName)}</span>
                                            <span style="font-size:0.75rem; color:var(--text-muted);"><i class="fa-solid fa-location-dot"></i> ${escapeHtml(r.customerCity || 'Pakistan')}</span>
                                        </div>
                                        <span class="review-verified-tag"><i class="fa-solid fa-circle-check"></i> Verified Buyer</span>
                                    </div>
                                    <div style="color:#eab308; font-size:0.78rem; margin-bottom:4px;">
                                        ${stars}
                                        <span style="color:var(--text-muted); font-size:0.72rem; margin-left:6px;">${escapeHtml(r.date || 'Recent')}</span>
                                    </div>
                                    <div class="review-comment-text">
                                        "${escapeHtml(r.comment)}"
                                    </div>
                                </div>
                            `;
                        }).join('');
                    }
                }
            })
            .catch(err => {
                console.error(err);
                if (listEl) listEl.innerHTML = '<div style="color:#ef4444; font-size:0.85rem; padding:10px;">Failed to load reviews.</div>';
            });
    }

    // Set Interactive Star Rating
    window.setReviewRating = function(rating) {
        document.getElementById('reviewRatingInput').value = rating;
        const stars = document.querySelectorAll('#reviewStarWidget .star-interactive');
        stars.forEach((s, idx) => {
            if (idx < rating) {
                s.classList.add('active');
                s.className = 'fa-solid fa-star star-interactive active';
            } else {
                s.classList.remove('active');
                s.className = 'fa-regular fa-star star-interactive';
            }
        });
    };

    // Submit Customer Review
    window.handleCustomerReviewSubmit = function(e) {
        e.preventDefault();
        const productId = document.getElementById('reviewProductId').value;
        const customerName = document.getElementById('reviewCustomerName').value.trim();
        const customerCity = document.getElementById('reviewCustomerCity').value.trim() || 'Pakistan';
        const rating = parseInt(document.getElementById('reviewRatingInput').value) || 5;
        const comment = document.getElementById('reviewComment').value.trim();
        const alertEl = document.getElementById('reviewSubmitAlert');
        const btn = document.getElementById('btnSubmitReview');

        if (!customerName || !comment) {
            if (alertEl) {
                alertEl.style.display = 'block';
                alertEl.style.background = '#fef2f2';
                alertEl.style.color = '#dc2626';
                alertEl.style.border = '1px solid #fecaca';
                alertEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Please fill in your name and review comment.';
            }
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting Review...';

        fetch('backend/reviews.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                productId: productId,
                customerName: customerName,
                customerCity: customerCity,
                rating: rating,
                comment: comment
            })
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Submit Customer Review';

            if (data && data.status === 'success') {
                if (alertEl) {
                    alertEl.style.display = 'block';
                    alertEl.style.background = '#ecfdf5';
                    alertEl.style.color = '#065f46';
                    alertEl.style.border = '1px solid #a7f3d0';
                    alertEl.innerHTML = '<i class="fa-solid fa-circle-check"></i> ' + (data.message || 'Review submitted successfully!');
                }
                // Reset form fields
                document.getElementById('reviewComment').value = '';
                // Reload list
                loadProductReviews(productId);
            } else {
                if (alertEl) {
                    alertEl.style.display = 'block';
                    alertEl.style.background = '#fef2f2';
                    alertEl.style.color = '#dc2626';
                    alertEl.style.border = '1px solid #fecaca';
                    alertEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' + (data.message || 'Failed to submit review.');
                }
            }
        })
        .catch(err => {
            console.error(err);
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Submit Customer Review';
            if (alertEl) {
                alertEl.style.display = 'block';
                alertEl.style.background = '#fef2f2';
                alertEl.style.color = '#dc2626';
                alertEl.style.border = '1px solid #fecaca';
                alertEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Network error submitting review.';
            }
        });
    };

    function intval(val) {
        const n = parseInt(val, 10);
        return isNaN(n) ? 0 : n;
    }

    // WhatsApp Floating Button
    function initWhatsAppButton() {
        const floatBtn = document.getElementById('floatingWhatsAppBtn');
        if (floatBtn) {
            floatBtn.addEventListener('click', function (e) {
                e.preventDefault();
                window.open('https://wa.me/923339688007?text=Hello%20SMZ!%20I%20want%20to%20inquire%20about%20mobile%20phones%20and%20cctv%20services.', '_blank');
            });
        }
    }

    // Utility: Escape HTML
    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // High-Performance Subtle Scroll Observer
    function initScrollAnimations() {
        if (typeof window === 'undefined' || !('IntersectionObserver' in window)) return;
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        // 1. Header Scroll Shadow
        const header = document.querySelector('.site-header');
        if (header) {
            const handleScroll = function() {
                if (window.scrollY > 15) {
                    header.classList.add('is-scrolled');
                } else {
                    header.classList.remove('is-scrolled');
                }
            };
            window.addEventListener('scroll', handleScroll, { passive: true });
            handleScroll();
        }

        // 2. Intersection Observer for Scroll Reveals
        const observer = new IntersectionObserver(function(entries, obs) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    obs.unobserve(entry.target);
                }
            });
        }, { rootMargin: '0px 0px -40px 0px', threshold: 0.08 });

        function attachObservers() {
            const selectors = [
                '.section-header',
                '.hero-section',
                '.service-card',
                '.fin-card',
                '.vm-value-item',
                '.stat-item',
                '.product-card',
                '.products-grid',
                '.financial-services-container',
                '.vm-vertical-left',
                '.vm-vertical-right'
            ];

            const targets = document.querySelectorAll(selectors.join(', '));
            targets.forEach(function(el) {
                if (!el.classList.contains('reveal-on-scroll') && !el.classList.contains('is-visible')) {
                    el.classList.add('reveal-on-scroll');
                    if (el.classList.contains('products-grid') || el.classList.contains('financial-services-container')) {
                        el.classList.add('reveal-stagger');
                    }
                    observer.observe(el);
                }
            });
        }

        attachObservers();
        window.refreshAppAnimations = attachObservers;
    }

    // =========================================================================
    // MULTI-ITEM SHOPPING CART & UNIFIED CHECKOUT ENGINE
    // =========================================================================
    let cart = [];

    function initShoppingCart() {
        try {
            const savedCart = localStorage.getItem('sms_shopping_cart');
            if (savedCart) {
                cart = JSON.parse(savedCart);
                if (!Array.isArray(cart)) cart = [];
            }
        } catch (e) {
            cart = [];
        }

        updateCartBadges();
        renderCartUI();

        // Register window accessible functions
        window.addToCart = addToCart;
        window.removeFromCart = removeFromCart;
        window.updateCartQuantity = updateCartQuantity;
        window.clearEntireCart = clearEntireCart;
        window.openCartDrawer = openCartDrawer;
        window.closeCartDrawer = closeCartDrawer;
        window.openMultiCheckout = openMultiCheckout;
        window.closeMultiCheckout = closeMultiCheckout;
        window.handleMultiCheckoutSubmit = handleMultiCheckoutSubmit;
        window.handleMultiPaymentScreenshotUpload = handleMultiPaymentScreenshotUpload;
    }

    function saveCart() {
        try {
            localStorage.setItem('sms_shopping_cart', JSON.stringify(cart));
        } catch (e) {}
        updateCartBadges();
        renderCartUI();
    }

    function getEffectivePrice(p) {
        if (p.hasOnlineOffer && p.onlinePrice && p.onlinePrice > 0) {
            return parseFloat(p.onlinePrice);
        }
        return parseFloat(p.sellingPrice || p.priceNumeric || 0);
    }

    function addToCart(prodId, qty) {
        if (typeof qty !== 'number' || qty <= 0) qty = 1;
        const prod = products.find(function (item) { return item.id === prodId; });
        if (!prod) return;

        const effectivePrice = getEffectivePrice(prod);
        let existingIndex = cart.findIndex(function (item) { return item.id === prodId; });

        if (existingIndex > -1) {
            cart[existingIndex].qty += qty;
        } else {
            cart.push({
                id: prod.id,
                name: prod.name,
                brand: prod.brand || '',
                category: prod.category || '',
                price: effectivePrice,
                regularPrice: parseFloat(prod.sellingPrice || prod.priceNumeric || 0),
                image: prod.image || 'assets/images/logo.jpg',
                hasOffer: !!prod.hasOnlineOffer,
                offerBadge: prod.offerBadge || '',
                qty: qty
            });
        }

        saveCart();
        showCartToast(`Added <strong>${escapeHtml(prod.name)}</strong> to cart!`);
    }

    function removeFromCart(prodId) {
        cart = cart.filter(function (item) { return item.id !== prodId; });
        saveCart();
        if (cart.length === 0) {
            closeCartDrawer();
            showCartToast("Cart is now empty");
        }
    }

    function updateCartQuantity(prodId, delta) {
        let item = cart.find(function (i) { return i.id === prodId; });
        if (!item) return;

        item.qty += delta;
        if (item.qty <= 0) {
            removeFromCart(prodId);
        } else {
            saveCart();
        }
    }

    function clearEntireCart() {
        if (cart.length === 0) return;
        cart = [];
        saveCart();
        closeCartDrawer();
        showCartToast("Cart cleared");
    }

    function getTotalCartCount() {
        return cart.reduce(function (sum, item) { return sum + (item.qty || 1); }, 0);
    }

    function getCartGrandTotal() {
        return cart.reduce(function (sum, item) { return sum + ((item.price || 0) * (item.qty || 1)); }, 0);
    }

    function updateCartBadges() {
        const totalItems = getTotalCartCount();
        const headerBadge = document.getElementById('headerCartBadge');
        const floatingBadge = document.getElementById('floatingCartBadge');
        const floatingBtn = document.getElementById('floatingCartBtn');

        if (headerBadge) headerBadge.textContent = totalItems;
        if (floatingBadge) floatingBadge.textContent = totalItems;

        if (floatingBtn) {
            floatingBtn.style.display = totalItems > 0 ? 'flex' : 'none';
        }
    }

    function openCartDrawer() {
        if (cart.length === 0) {
            showCartToast("Your cart is currently empty");
            return;
        }
        renderCartUI();
        const drawer = document.getElementById('cartDrawer');
        const overlay = document.getElementById('cartOverlay');
        if (drawer) drawer.classList.add('active');
        if (overlay) overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeCartDrawer() {
        const drawer = document.getElementById('cartDrawer');
        const overlay = document.getElementById('cartOverlay');
        if (drawer) drawer.classList.remove('active');
        if (overlay) overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    function renderCartUI() {
        const container = document.getElementById('cartItemsContainer');
        const emptyState = document.getElementById('cartEmptyState');
        const footer = document.getElementById('cartFooter');
        const countSpan = document.getElementById('cartDrawerCount');
        const summaryItems = document.getElementById('cartSummaryItems');
        const grandTotal = document.getElementById('cartGrandTotal');

        const totalItems = getTotalCartCount();
        const totalAmount = getCartGrandTotal();

        if (countSpan) countSpan.textContent = totalItems;
        if (summaryItems) summaryItems.textContent = totalItems;
        if (grandTotal) grandTotal.textContent = 'PKR ' + totalAmount.toLocaleString();

        if (!container) return;

        if (cart.length === 0) {
            container.innerHTML = '';
            if (emptyState) {
                container.appendChild(emptyState);
                emptyState.style.display = 'flex';
            }
            if (footer) footer.style.display = 'none';
            return;
        }

        if (emptyState) emptyState.style.display = 'none';
        if (footer) footer.style.display = 'block';

        let html = '';
        cart.forEach(function (item) {
            let imgUrl = item.image;
            if (imgUrl.indexOf('../') === 0) imgUrl = imgUrl.substring(3);
            if (!imgUrl) imgUrl = 'https://images.unsplash.com/photo-1598327105666-5b89351aff97?w=600&auto=format&fit=crop&q=80';

            const lineTotal = (item.price || 0) * (item.qty || 1);

            html += `
                <div class="cart-item-card" data-cart-id="${escapeHtml(item.id)}">
                    <img src="${escapeHtml(imgUrl)}" class="cart-item-img" alt="${escapeHtml(item.name)}" onerror="this.src='https://images.unsplash.com/photo-1598327105666-5b89351aff97?w=600&auto=format&fit=crop&q=80'">
                    <div class="cart-item-info">
                        <div class="cart-item-title" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</div>
                        <div class="cart-item-price">
                            PKR ${lineTotal.toLocaleString()}
                            ${item.qty > 1 ? `<small style="color:var(--text-muted); font-weight:400;">(PKR ${item.price.toLocaleString()} ea)</small>` : ''}
                        </div>
                        <div class="cart-item-actions">
                            <div class="cart-qty-ctrl">
                                <button type="button" class="cart-qty-btn" onclick="window.updateCartQuantity('${escapeHtml(item.id)}', -1)">
                                    <i class="fa-solid fa-minus"></i>
                                </button>
                                <span class="cart-qty-val">${item.qty}</span>
                                <button type="button" class="cart-qty-btn" onclick="window.updateCartQuantity('${escapeHtml(item.id)}', 1)">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>
                            <button type="button" class="cart-item-delete-btn" onclick="window.removeFromCart('${escapeHtml(item.id)}')" title="Remove item">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    let uploadedMultiPaymentProofUrl = '';

    window.handleMultiPaymentScreenshotUpload = function (input) {
        if (!input.files || !input.files[0]) return;

        const file = input.files[0];
        const statusEl = document.getElementById('multiPaymentUploadStatus');
        const previewContainer = document.getElementById('multiPaymentScreenshotPreview');
        const previewImg = document.getElementById('multiPreviewImage');

        if (statusEl) {
            statusEl.style.color = '#3b82f6';
            statusEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading payment receipt...';
        }

        const formData = new FormData();
        formData.append('payment_proof', file);

        fetch('backend/upload_payment.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data && data.status === 'success') {
                uploadedMultiPaymentProofUrl = window.location.origin + '/' + data.data.url;
                if (statusEl) {
                    statusEl.style.color = '#10b981';
                    statusEl.innerHTML = '<i class="fa-solid fa-circle-check"></i> Receipt uploaded successfully!';
                }
                if (previewImg && previewContainer) {
                    previewImg.src = data.data.url;
                    previewContainer.style.display = 'block';
                }
            } else {
                if (statusEl) {
                    statusEl.style.color = '#ef4444';
                    statusEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' + (data.message || 'Upload error');
                }
            }
        })
        .catch(err => {
            console.error(err);
            if (statusEl) {
                statusEl.style.color = '#ef4444';
                statusEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Upload failed. You can also send screenshot via WhatsApp.';
            }
        });
    };

    function openMultiCheckout() {
        if (cart.length === 0) {
            alert('Your cart is empty. Please add some products first.');
            return;
        }

        closeCartDrawer();

        const modal = document.getElementById('multiCheckoutModal');
        const itemsList = document.getElementById('checkoutItemsList');
        const grandTotal = document.getElementById('checkoutGrandTotal');
        const payAmount = document.getElementById('checkoutPayAmount');
        const chkPayAmountText = document.getElementById('chkPayAmountText');
        const countBadge = document.getElementById('checkoutItemCountBadge');
        const alertBox = document.getElementById('multiOrderAlert');

        const totalItems = getTotalCartCount();
        const totalAmount = getCartGrandTotal();

        if (countBadge) countBadge.textContent = totalItems + (totalItems === 1 ? ' Item' : ' Items');
        if (grandTotal) grandTotal.textContent = 'PKR ' + totalAmount.toLocaleString();
        if (payAmount) payAmount.textContent = 'PKR ' + totalAmount.toLocaleString();
        if (chkPayAmountText) chkPayAmountText.textContent = 'PKR ' + totalAmount.toLocaleString();

        // Reset payment fields
        uploadedMultiPaymentProofUrl = '';
        const proofFileInput = document.getElementById('multiPaymentProofFile');
        if (proofFileInput) proofFileInput.value = '';
        const uploadStatus = document.getElementById('multiPaymentUploadStatus');
        if (uploadStatus) uploadStatus.innerHTML = '';
        const previewContainer = document.getElementById('multiPaymentScreenshotPreview');
        if (previewContainer) previewContainer.style.display = 'none';

        const trxInput = document.getElementById('multiTrxId');
        if (trxInput) trxInput.value = '';
        const confirmChk = document.getElementById('chkConfirmAdvancePayment');
        if (confirmChk) confirmChk.checked = false;

        if (alertBox) {
            alertBox.style.display = 'none';
        }

        if (itemsList) {
            let listHtml = '';
            cart.forEach(function (item, idx) {
                const lineTotal = (item.price || 0) * (item.qty || 1);
                listHtml += `
                    <div class="checkout-mini-item">
                        <div class="checkout-mini-title">${idx + 1}. ${escapeHtml(item.name)} <span class="checkout-mini-qty">x${item.qty}</span></div>
                        <div class="checkout-mini-price">PKR ${lineTotal.toLocaleString()}</div>
                    </div>
                `;
            });
            itemsList.innerHTML = listHtml;
        }

        if (modal) {
            modal.style.display = 'flex';
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeMultiCheckout() {
        const modal = document.getElementById('multiCheckoutModal');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    async function handleMultiCheckoutSubmit(e) {
        if (e) e.preventDefault();

        if (cart.length === 0) {
            alert('Your cart is empty.');
            return;
        }

        const name = (document.getElementById('multiCustomerName')?.value || '').trim();
        const phone = (document.getElementById('multiCustomerPhone')?.value || '').trim();
        const city = (document.getElementById('multiCustomerCity')?.value || '').trim();
        const landmark = (document.getElementById('multiCustomerLandmark')?.value || '').trim();
        const address = (document.getElementById('multiCustomerAddress')?.value || '').trim();
        const paymentMethod = (document.getElementById('multiPaymentMethod')?.value || '').trim();
        const senderAccount = (document.getElementById('multiSenderAccount')?.value || '').trim();
        const trxId = (document.getElementById('multiTrxId')?.value || '').trim();
        const isConfirmed = document.getElementById('chkConfirmAdvancePayment')?.checked;

        const submitBtn = document.getElementById('btnSubmitMultiOrder');
        const alertBox = document.getElementById('multiOrderAlert');

        // 1. Mandatory Customer Fields Validation
        if (!name || !phone || !city || !address) {
            if (alertBox) {
                alertBox.style.display = 'block';
                alertBox.style.background = '#fee2e2';
                alertBox.style.color = '#dc2626';
                alertBox.style.border = '1.5px solid #f87171';
                alertBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Please fill out all required customer contact & delivery fields marked with *.';
            }
            alert('Please fill out all required customer fields marked with *.');
            return;
        }

        // 2. Strict Mandatory Advance Payment Details Validation
        if (!paymentMethod) {
            if (alertBox) {
                alertBox.style.display = 'block';
                alertBox.style.background = '#fee2e2';
                alertBox.style.color = '#dc2626';
                alertBox.style.border = '1.5px solid #f87171';
                alertBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> <strong>Advance Payment Required:</strong> Please select the Payment Method you used.';
            }
            document.getElementById('multiPaymentMethod')?.focus();
            return;
        }

        if (!senderAccount) {
            if (alertBox) {
                alertBox.style.display = 'block';
                alertBox.style.background = '#fee2e2';
                alertBox.style.color = '#dc2626';
                alertBox.style.border = '1.5px solid #f87171';
                alertBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Please enter your Sender Account Name or Mobile Number.';
            }
            document.getElementById('multiSenderAccount')?.focus();
            return;
        }

        if (!trxId || trxId.length < 3) {
            if (alertBox) {
                alertBox.style.display = 'block';
                alertBox.style.background = '#fee2e2';
                alertBox.style.color = '#dc2626';
                alertBox.style.border = '1.5px solid #f87171';
                alertBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> <strong>Advance Payment Verification:</strong> You must enter your Transaction ID (TRX) before submitting the order.';
            }
            document.getElementById('multiTrxId')?.focus();
            alert('Advance Payment is strictly required! Please transfer the payment and enter your valid Transaction ID (TRX) from your SMS or banking app.');
            return;
        }

        if (!isConfirmed) {
            if (alertBox) {
                alertBox.style.display = 'block';
                alertBox.style.background = '#fee2e2';
                alertBox.style.color = '#dc2626';
                alertBox.style.border = '1.5px solid #f87171';
                alertBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Please check the confirmation box confirming your advance payment transfer.';
            }
            document.getElementById('chkConfirmAdvancePayment')?.focus();
            return;
        }

        const totalItems = getTotalCartCount();
        const totalAmount = getCartGrandTotal();

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Verifying Advance Payment & Recording Order...';
        }

        if (alertBox) {
            alertBox.style.display = 'block';
            alertBox.style.background = '#e0f2fe';
            alertBox.style.color = '#0369a1';
            alertBox.style.border = '1px solid #bae6fd';
            alertBox.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting advance-paid order to POS & WhatsApp...';
        }

        let assignedInvoiceNo = 'ONL-' + new Date().toISOString().slice(0,10).replace(/-/g,'') + '-' + Math.floor(1000 + Math.random() * 9000);

        try {
            const response = await fetch('backend/sales.php?action=online_order', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'online_order',
                    orderType: 'online',
                    customerName: name,
                    customerPhone: phone,
                    customerCity: city,
                    customerLandmark: landmark,
                    customerAddress: address,
                    paymentMethod: paymentMethod,
                    senderAccount: senderAccount,
                    trxId: trxId,
                    paymentProof: uploadedMultiPaymentProofUrl,
                    items: cart
                })
            });

            const resData = await response.json();
            if (resData && resData.status === 'success' && resData.data && resData.data.invoiceNo) {
                assignedInvoiceNo = resData.data.invoiceNo;
            } else if (resData && resData.status === 'error') {
                if (alertBox) {
                    alertBox.style.background = '#fee2e2';
                    alertBox.style.color = '#dc2626';
                    alertBox.style.border = '1px solid #f87171';
                    alertBox.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> ' + (resData.message || 'Error processing order');
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fa-brands fa-whatsapp"></i> Verify Advance Payment & Submit Order';
                }
                return;
            }
        } catch (err) {
            console.warn("Backend order sync notice:", err);
        }

        const currentDateStr = new Date().toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' });

        let orderItemsText = '';
        cart.forEach(function (item, idx) {
            const lineTotal = (item.price || 0) * (item.qty || 1);
            orderItemsText += `  ${idx + 1}. *${item.name}*\n` +
                              `     ↳ Qty: *${item.qty}* × PKR ${item.price.toLocaleString()} ➜ *PKR ${lineTotal.toLocaleString()}*\n`;
        });

        let proofLine = '';
        if (uploadedMultiPaymentProofUrl) {
            proofLine = `🖼️ *Payment Receipt URL:* ${uploadedMultiPaymentProofUrl}\n`;
        }

        const fullMessage = 
`🧾 ═══════════════════════════ 🧾
   *SAFDAR MOBILE STORE — HANGU*
   _Official Digital Tax & Order Invoice_
🧾 ═══════════════════════════ 🧾

🔖 *INVOICE NO:* *#${assignedInvoiceNo}*
📅 *ORDER DATE:* ${currentDateStr}
🏷️ *SOURCE:* Online Web Storefront
📦 *TOTAL ITEMS:* ${totalItems} Selected Product(s)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━
👤 *CUSTOMER & DELIVERY PROFILE*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━
▪ *Full Name:* ${name}
▪ *WhatsApp / Phone:* ${phone}
▪ *Destination City:* ${city}
${landmark ? `▪ *Nearest Landmark:* ${landmark}\n` : ''}▪ *Delivery Address:* ${address}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📦 *ORDERED PRODUCTS SPECIFICATION*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━
${orderItemsText}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━
💰 *BILLING & TOTAL DUE*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━
▪ *Items Subtotal:* PKR ${totalAmount.toLocaleString()}
▪ *100% ADVANCE TOTAL:* *PKR ${totalAmount.toLocaleString()}*
▪ *Courier Rates:* _Payable to TCS / Leopards on Arrival_

━━━━━━━━━━━━━━━━━━━━━━━━━━━━
💳 *VERIFIED 100% ADVANCE PAYMENT PROOF*
━━━━━━━━━━━━━━━━━━━━━━━━━━━━
▪ *Payment Channel:* *${paymentMethod}*
▪ *Sender Account/Mobile:* *${senderAccount}*
▪ *TRANSACTION ID (TRX):* *${trxId}*
${proofLine}▪ *Payment Status:* *PAID VIA ADVANCE TRANSFER*

━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📌 *NEXT STEPS FOR DISPATCH:*
1. Payment TRX ID #${trxId} is verified by store management.
2. Your parcel will be packed and handed over to TCS / Leopards.
3. Live parcel tracking number will be sent to your WhatsApp immediately!

🛡️ _Recorded in Official POS System under Invoice #${assignedInvoiceNo}_
🌐 *safdarmobilestore.com* | 📞 *0333-9688007*`;

        const waUrl = 'https://wa.me/923339688007?text=' + encodeURIComponent(fullMessage);
        window.open(waUrl, '_blank');

        if (alertBox) {
            alertBox.style.background = '#dcfce7';
            alertBox.style.color = '#15803d';
            alertBox.style.border = '1px solid #bbf7d0';
            alertBox.innerHTML = `<i class="fa-solid fa-circle-check"></i> Order <strong>#${assignedInvoiceNo}</strong> recorded with Advance TRX <strong>${escapeHtml(trxId)}</strong>!`;
        }

        setTimeout(function () {
            // Clear cart after successful order creation
            cart = [];
            saveCart();
            closeMultiCheckout();
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-brands fa-whatsapp"></i> Verify Advance Payment &amp; Submit Order';
            }
            if (alertBox) {
                alertBox.style.display = 'none';
            }
            showCartToast(`Order <strong>#${assignedInvoiceNo}</strong> placed with Advance Payment verification!`);
        }, 1500);
    }

    function showCartToast(msg) {
        let toast = document.getElementById('smsCartToast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'smsCartToast';
            toast.style.cssText = `
                position: fixed;
                bottom: 140px;
                right: 24px;
                background: #0f172a;
                color: #ffffff;
                padding: 12px 18px;
                border-radius: 30px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.35);
                font-size: 0.88rem;
                z-index: 99999;
                display: flex;
                align-items: center;
                gap: 12px;
                border: 1.5px solid rgba(244,196,48,0.3);
                transition: all 0.3s ease;
                transform: translateY(20px);
                opacity: 0;
            `;
            document.body.appendChild(toast);
        }

        toast.innerHTML = `
            <i class="fa-solid fa-circle-check" style="color:#10b981; font-size:1.15rem;"></i> 
            <span>${msg}</span>
            <button type="button" onclick="window.openCartDrawer()" style="background:var(--smz-red); color:#fff; border:none; padding:4px 10px; border-radius:20px; font-size:0.75rem; font-weight:800; cursor:pointer; margin-left:6px;">
                View Cart &rarr;
            </button>
        `;
        toast.style.transform = 'translateY(0)';
        toast.style.opacity = '1';

        setTimeout(function () {
            toast.style.transform = 'translateY(20px)';
            toast.style.opacity = '0';
        }, 3200);
    }

    // 5-Click Secret Admin Portal Trigger in Footer
    function initSecretAdminTrigger() {
        const triggerElement = document.getElementById('secretAdminTrigger') || document.querySelector('.footer-bottom');
        if (!triggerElement) return;

        let clickCount = 0;
        let lastClickTime = 0;

        triggerElement.addEventListener('click', function (e) {
            const currentTime = new Date().getTime();

            // Reset counter if more than 2.5 seconds elapsed since last click
            if (currentTime - lastClickTime > 2500) {
                clickCount = 1;
            } else {
                clickCount++;
            }

            lastClickTime = currentTime;

            if (clickCount >= 5) {
                clickCount = 0; // Reset
                window.location.href = 'secure-portal.php?path=secure-management-portal';
            }
        });
    }

})();
