<?php
// SMS (Safdar Mobile Store) - Official Brand & Storefront Index
require_once __DIR__ . '/backend/config.php';
$siteCategories = get_json_file('categories') ?? [];
$allProducts = get_json_file('products') ?? [];
usort($allProducts, function($a, $b) {
    $tA = !empty($a['createdAt']) ? strtotime($a['createdAt']) : 0;
    $tB = !empty($b['createdAt']) ? strtotime($b['createdAt']) : 0;
    return $tB - $tA;
});
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Safdar Mobile Store | Mobiles, Laptops, Accessories, CCTV & Digital Kiosk</title>
    <meta name="description" content="Safdar Mobile Store - Premium Smartphones, Laptops, Mobile Accessories, CCTV Camera Security Systems, Easypaisa & JazzCash Services, and NADRA Citizen Kiosk in Hangu, KPK.">

    <!-- SEO Meta Tags -->
    <meta name="keywords" content="Safdar Mobile Store, Mobiles Hangu, Laptops, Smartphones KPK, Samsung, iPhone, Infinix, CCTV Installation, Easypaisa, JazzCash, NADRA Kiosk">
    <meta name="robots" content="index, follow">
    <meta name="author" content="Safdar Mobile Store">

    <!-- Open Graph & Social Media Sharing Meta Tags -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="Safdar Mobile Store | Mobiles, Laptops, Accessories, CCTV & Digital Kiosk">
    <meta property="og:description" content="Safdar Mobile Store - Premium Smartphones, Laptops, Mobile Accessories, CCTV Camera Security Systems, Easypaisa & JazzCash Services, and NADRA Citizen Kiosk in Hangu, KPK.">
    <meta property="og:image" content="assets/images/logo.jpg">
    <meta property="og:locale" content="en_US">

    <!-- Twitter Cards SEO -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Safdar Mobile Store | Mobiles, Laptops, Accessories & CCTV">
    <meta name="twitter:description" content="Official store for smartphones, laptops, genuine accessories, Hikvision CCTV systems & financial facilitation in Hangu.">
    <meta name="twitter:image" content="assets/images/logo.jpg">

    <!-- Schema.org JSON-LD Local Business Structured Data for Google Indexing -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "MobilePhoneStore",
      "name": "Safdar Mobile Store",
      "image": "assets/images/logo.jpg",
      "telephone": "+923339688007",
      "url": "https://safdarmobilestore.com",
      "address": {
        "@type": "PostalAddress",
        "addressLocality": "Hangu",
        "addressRegion": "Khyber Pakhtunkhwa",
        "addressCountry": "PK"
      },
      "openingHoursSpecification": {
        "@type": "OpeningHoursSpecification",
        "dayOfWeek": [
          "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"
        ],
        "opens": "08:00",
        "closes": "21:00"
      },
      "priceRange": "$$",
      "description": "Safdar Mobile Store offers smartphones, laptops, mobile accessories, CCTV camera security installation, Easypaisa, JazzCash & NADRA Citizen Facilitation Kiosk in Hangu."
    }
    </script>

    <script>
        (function() {
            var theme = localStorage.getItem('smz_theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>

    <!-- High Performance DNS Pre-connects & Fonts -->
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="https://fonts.gstatic.com">
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Outfit:wght@600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Stylesheets with Auto Cache-Buster for Live Hosting -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo @filemtime(__DIR__ . '/assets/css/style.css') ?: time(); ?>">
</head>
<body>

    <!-- Site Header -->
    <header class="site-header">
        <div class="header-container">
            <div class="brand-logo">
                <button class="nav-toggle-btn" id="navToggleBtn" onclick="window.toggleStorefrontNav()" aria-label="Toggle Navigation Menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <img src="assets/images/logo.jpg" alt="Safdar Mobile Store" onclick="document.querySelector('[data-tab=home]').click()" title="Safdar Mobile Store - Home">
            </div>

            <nav class="main-nav">
                <button class="nav-item active" data-tab="home"><i class="fa-solid fa-house"></i> Home</button>
                <?php foreach ($siteCategories as $sc): 
                    // Exclude categories that must not be shown in the menu bar
                    $showInMenu = $sc['show_in_menu'] ?? $sc['showInMenu'] ?? true;
                    if ($showInMenu === false) continue;
                    $scId = $sc['id'] ?? strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $sc['name']));
                    $cleanId = strtolower(str_replace(['-', '_', ' '], '', $scId));
                    if (in_array($cleanId, ['computeraccessories', 'networkaccessories'])) continue;

                    $scName = $sc['name'];
                    $scIcon = $sc['icon'] ?? 'fa-tag';
                    if ($scId === 'mobiles') $scIcon = 'fa-mobile-screen';
                    elseif ($scId === 'accessories') $scIcon = 'fa-headphones';
                    elseif ($scId === 'cctv') $scIcon = 'fa-video';
                    elseif (strpos($scId, 'laptop') !== false) $scIcon = 'fa-laptop';
                    elseif (strpos($scId, 'watch') !== false) $scIcon = 'fa-clock';
                    elseif (strpos($scId, 'tablet') !== false) $scIcon = 'fa-tablet-screen-button';
                    elseif (strpos($scId, 'audio') !== false || strpos($scId, 'speaker') !== false) $scIcon = 'fa-volume-high';
                ?>
                    <button class="nav-item" data-tab="<?php echo htmlspecialchars($scId); ?>">
                        <i class="fa-solid <?php echo htmlspecialchars($scIcon); ?>"></i> <?php echo htmlspecialchars($scName); ?>
                    </button>
                <?php endforeach; ?>
                <button class="nav-item" data-tab="financial"><i class="fa-solid fa-wallet"></i> Financial &amp; Load</button>
                <button class="nav-item" data-tab="citizen"><i class="fa-solid fa-id-card"></i> Citizen Kiosk</button>
                <button class="nav-item mobile-only-nav-item" data-tab="contact"><i class="fa-solid fa-address-book"></i> Contact Us</button>
            </nav>

            <div class="header-actions">
                <button id="themeToggleBtn" class="theme-toggle-btn" onclick="window.toggleStorefrontTheme()" title="Toggle Dark/Light Mode">
                    <i id="themeToggleIcon" class="fa-solid fa-moon"></i>
                    <span id="themeToggleText">Dark Mode</span>
                </button>

                <button class="nav-item header-contact-btn" data-tab="contact">
                    <i class="fa-solid fa-address-book"></i> Contact Us
                </button>
            </div>
        </div>
    </header>

    <!-- Floating Mobile Shopping Cart Button -->
    <button id="floatingCartBtn" class="floating-cart-btn" onclick="window.openCartDrawer()" title="View Shopping Cart">
        <i class="fa-solid fa-cart-shopping"></i>
        <span class="floating-cart-badge" id="floatingCartBadge">0</span>
    </button>

    <!-- Main Content Area -->
    <main class="main-content">

        <!-- TAB 1: HOME -->
        <section id="tab-home" class="page-tab" style="display: block;">
            <!-- Hero Section -->
            <div class="hero-section">
                <div class="hero-overlay"></div>
                <div class="hero-container">
                    <div class="hero-badge">
                        ⭐ Official Brand & Service Point ⭐
                    </div>
                    <h1 class="hero-title">
                        <span class="gradient-text-gold">Safdar Mobile Store</span>
                    </h1>
                    <p class="hero-subtitle">
                        Mobiles, accessories, CCTV solutions, digital payments, telecom services and essential digital facilitation — all under one roof. Contact us directly on WhatsApp (03339688007) for catalog inquiries and orders.
                    </p>
                    <div class="hero-cta-group">
                        <button class="btn btn-primary btn-lg circle-style-btn" data-tab-target="mobiles">
                            <i class="fa-solid fa-bag-shopping"></i> View Mobiles Catalog
                        </button>
                        <a href="https://wa.me/923339688007?text=Hello%20Safdar%20Mobile%20Store!%20I%20want%20to%20inquire%20about%20products" target="_blank" class="btn btn-whatsapp btn-lg circle-style-btn">
                            <i class="fa-brands fa-whatsapp"></i> Chat on WhatsApp
                        </a>
                    </div>

                    <div class="hero-stats">
                        <div class="stat-item">
                            <span class="stat-num">8+</span>
                            <span class="stat-label">Mobile Brands</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-num">100%</span>
                            <span class="stat-label">Genuine Accessories</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-num">24/7</span>
                            <span class="stat-label">CCTV Security Support</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-num">Instant</span>
                            <span class="stat-label">Easypaisa & JazzCash</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Featured & Latest Inventory Showcase (Laptops, Mobiles, Accessories, CCTV, Watches) -->
            <div class="section-padding" style="background:var(--bg-main); border-bottom:1px solid var(--smz-border);">
                <div class="container">
                    <div class="section-header" style="text-align:center; max-width:720px; margin:0 auto 28px auto;">
                        <span class="sub-heading" style="color:var(--smz-red); font-weight:800; letter-spacing:1px; display:inline-block; margin-bottom:6px;"><i class="fa-solid fa-fire"></i> NEW ARRIVALS &amp; FEATURED STOCK</span>
                        <h2 class="section-title" style="font-size:2.1rem; margin-bottom:10px;">Explore Latest Tech in Store</h2>
                        <p class="section-desc" style="margin:0 auto; font-size:0.98rem; color:var(--text-secondary);">Browse smartphones, laptops, genuine accessories &amp; security devices currently in stock with online offers.</p>
                    </div>

                    <!-- Live Home Category Filter Pills (Centered) -->
                    <div class="filter-bar" id="homeCategoryFilterBar" style="display:flex; justify-content:center; flex-wrap:wrap; gap:8px; margin-bottom:32px;">
                        <button class="filter-btn active" data-home-category="all"><i class="fa-solid fa-layer-group"></i> All Stock</button>
                        <?php foreach ($siteCategories as $sc): 
                            $scId = $sc['id'] ?? strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $sc['name']));
                            $scName = $sc['name'];
                            $scIcon = $sc['icon'] ?? 'fa-tag';
                            if ($scId === 'mobiles') $scIcon = 'fa-mobile-screen';
                            elseif ($scId === 'accessories') $scIcon = 'fa-headphones';
                            elseif ($scId === 'cctv') $scIcon = 'fa-video';
                            elseif (strpos($scId, 'laptop') !== false) $scIcon = 'fa-laptop';
                            elseif (strpos($scId, 'watch') !== false) $scIcon = 'fa-clock';
                            elseif (strpos($scId, 'tablet') !== false) $scIcon = 'fa-tablet-screen-button';
                            elseif (strpos($scId, 'computer') !== false) $scIcon = 'fa-computer';
                            elseif (strpos($scId, 'network') !== false) $scIcon = 'fa-network-wired';
                        ?>
                            <button class="filter-btn" data-home-category="<?php echo htmlspecialchars($scId); ?>">
                                <i class="fa-solid <?php echo htmlspecialchars($scIcon); ?>"></i> <?php echo htmlspecialchars($scName); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Products Grid (Pre-rendered for Instant Loading & Hydrated by JS) -->
                    <div class="products-grid" id="homeFeaturedProductsGrid">
                        <?php 
                        $homeProds = array_slice($allProducts, 0, 8);
                        foreach ($homeProds as $hp):
                            $hasOffer = !empty($hp['hasOnlineOffer']) && (!empty($hp['onlinePrice']) || !empty($hp['discountValue']));
                            $regularPrice = floatval($hp['sellingPrice'] ?? $hp['priceNumeric'] ?? 0);
                            $onlinePrice = ($hasOffer && !empty($hp['onlinePrice'])) ? floatval($hp['onlinePrice']) : $regularPrice;
                            $saving = max(0, $regularPrice - $onlinePrice);
                            $badge = $hp['badge'] ?? (!empty($hp['isNewArrival']) ? 'NEW ARRIVAL' : 'NEW LISTED');
                            $img = $hp['image'] ?? '';
                            if (strpos($img, '../') === 0) $img = substr($img, 3);
                            if (empty($img)) $img = 'https://images.unsplash.com/photo-1598327105666-5b89351aff97?w=600&auto=format&fit=crop&q=80';
                        ?>
                            <div class="product-card" style="position:relative;">
                                <span class="product-tag new-arrival-tag"><?php echo htmlspecialchars($badge); ?></span>
                                <?php if ($hasOffer): ?>
                                    <span class="online-offer-badge"><i class="fa-solid fa-tags"></i> <?php echo htmlspecialchars($hp['offerBadge'] ?? '10% OFF ONLINE'); ?></span>
                                <?php endif; ?>
                                <div class="product-img-box" onclick="window.openProductDetailsModal('<?php echo htmlspecialchars($hp['id']); ?>')" style="cursor:pointer;" title="Click to view details">
                                    <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($hp['name']); ?>" onerror="this.src='https://images.unsplash.com/photo-1598327105666-5b89351aff97?w=600&auto=format&fit=crop&q=80'">
                                </div>
                                <div class="product-info">
                                    <h4 onclick="window.openProductDetailsModal('<?php echo htmlspecialchars($hp['id']); ?>')" style="cursor:pointer;" title="<?php echo htmlspecialchars($hp['name']); ?>"><?php echo htmlspecialchars($hp['name']); ?></h4>
                                    
                                    <?php if ($hasOffer && $saving > 0): ?>
                                        <div class="product-pricing-wrap">
                                            <del class="regular-price-del">PKR <?php echo number_format($regularPrice); ?></del>
                                            <span class="online-price-highlight">PKR <?php echo number_format($onlinePrice); ?></span>
                                            <span class="online-saving-chip">SAVE PKR <?php echo number_format($saving); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="price-range">PKR <?php echo number_format($regularPrice); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="product-card-actions">
                                    <button type="button" class="btn btn-details-card" onclick="window.openProductDetailsModal('<?php echo htmlspecialchars($hp['id']); ?>')" title="View Full Details &amp; Specifications">
                                        <i class="fa-solid fa-circle-info"></i> Details
                                    </button>
                                    <button type="button" class="btn btn-add-cart" onclick="window.addToCart('<?php echo htmlspecialchars($hp['id']); ?>')" title="Add to Cart">
                                        <i class="fa-solid fa-cart-plus"></i> Cart
                                    </button>
                                    <button type="button" class="btn btn-primary open-inquiry-btn" data-product-id="<?php echo htmlspecialchars($hp['id']); ?>" title="Instant Buy Now">
                                        <i class="fa-brands fa-whatsapp"></i> Buy
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Our Services Section -->
            <div class="section-padding" style="background:var(--bg-light);">
                <div class="container">
                    <div class="section-header">
                        <span class="sub-heading">OUR SERVICES</span>
                        <h2 class="section-title">What We Offer at <span style="color:var(--smz-red);">Safdar Mobile Store</span></h2>
                        <p class="section-desc">Browse our core services below. For personalized assistance or quick inquiries, contact WhatsApp 03339688007 or visit our store in Hangu (8:00 AM - 9:00 PM).</p>
                    </div>

                    <div class="services-grid">
                        
                        <!-- Card 1 -->
                        <div class="service-card" data-tab-target="mobiles">
                            <div class="service-card-img-box">
                                <span class="card-badge">BRAND NEW</span>
                                <img src="https://images.unsplash.com/photo-1610945265064-0e34e5519bbf?w=600&auto=format&fit=crop&q=80" alt="Mobile Section">
                            </div>
                            <div class="service-card-body">
                                <h3>Mobile Section</h3>
                                <p>Latest smartphone models: Samsung, iPhone, Xiaomi, Infinix, Realme, Tecno, Vivo, and Redmi.</p>
                                <span class="card-link">View Catalog <i class="fa-solid fa-arrow-right"></i></span>
                            </div>
                        </div>

                        <!-- Card 2 -->
                        <div class="service-card" data-tab-target="accessories">
                            <div class="service-card-img-box">
                                <span class="card-badge">COMPLETE TECH</span>
                                <img src="assets/images/tws_airbuds_pro.png" alt="Mobile Accessories">
                            </div>
                            <div class="service-card-body">
                                <h3>Mobile Accessories</h3>
                                <p>Fast chargers, data cables, ear-phones, airbuds, powerbanks, smartwatch, tempered glass & covers.</p>
                                <span class="card-link">View Accessories <i class="fa-solid fa-arrow-right"></i></span>
                            </div>
                        </div>

                        <!-- Card 3 -->
                        <div class="service-card" data-tab-target="cctv">
                            <div class="service-card-img-box">
                                <span class="card-badge">SECURITY</span>
                                <img src="assets/images/cctv_hikvision_bullet.png" alt="CCTV Cameras">
                            </div>
                            <div class="service-card-body">
                                <h3>CCTV Accessories & Cameras</h3>
                                <p>HD indoor/outdoor CCTV cameras, night vision, PTZ smart cameras, DVRs, power supplies, cables.</p>
                                <span class="card-link">Explore Security <i class="fa-solid fa-arrow-right"></i></span>
                            </div>
                        </div>

                        <!-- Card 4 -->
                        <div class="service-card" data-tab-target="financial">
                            <div class="service-card-img-box">
                                <span class="card-badge">INSTANT PAY</span>
                                <img src="https://images.unsplash.com/photo-1563986768609-322da13575f3?w=600&auto=format&fit=crop&q=80" alt="Easypaisa & JazzCash">
                            </div>
                            <div class="service-card-body">
                                <h3>Easypaisa, JazzCash, Bills & Easyload</h3>
                                <p>Easypaisa/JazzCash money transfer, Utility Bill Payments (Electricity, Gas, PTCL), All SIM Easyload & Packages (Jazz, Zong, Telenor, Ufone).</p>
                                <span class="card-link">Explore Financial Kiosk <i class="fa-solid fa-arrow-right"></i></span>
                            </div>
                        </div>

                        <!-- Card 5 -->
                        <div class="service-card" data-tab-target="citizen">
                            <div class="service-card-img-box">
                                <span class="card-badge">GOVERNMENT GUIDE</span>
                                <img src="assets/images/nadra_cnic.png" alt="Nadra Citizen">
                            </div>
                            <div class="service-card-body">
                                <h3>Nadra, Police Clearance & Domicile</h3>
                                <p>Guidance for CNIC/FRC application, Police Clearance certificate & Domicile procedures.</p>
                                <span class="card-link">Citizen Guide <i class="fa-solid fa-arrow-right"></i></span>
                            </div>
                        </div>

                        <!-- Card 6: Mobile Repair -->
                        <div class="service-card" data-tab-target="repair">
                            <div class="service-card-img-box">
                                <span class="card-badge" style="background:#059669; color:#fff;">PROFESSIONAL LAB</span>
                                <img src="assets/images/pakistani_mobile_repair_expert.jpg" alt="Professional Pakistani Mobile Repair Technician">
                            </div>
                            <div class="service-card-body">
                                <h3>Expert Mobile Repairing</h3>
                                <p>All types of mobile repairing work done professionally: IC chip-level micro-soldering, touch LCD screen replacement, water damage & software unlocking.</p>
                                <span class="card-link">Explore Repair Lab <i class="fa-solid fa-arrow-right"></i></span>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- SEPARATE DEDICATED SECTION: Nadra, Police Clearance & Domicile -->
            <div class="section-padding citizen-section-wrapper" style="border-top:1px solid #e2e8f0; border-bottom:1px solid #e2e8f0;">
                <div class="container">
                    <div class="section-header">
                        <span class="sub-heading">GOVERNMENT & CITIZEN FACILITATION</span>
                        <h2 class="section-title">Nadra, Police Clearance & <span style="color:var(--smz-red);">Domicile Services</span></h2>
                        <p class="section-desc">Expert guidance, requirement checklists, document attestation, and application assistance for citizens of Hangu and KPK.</p>
                    </div>

                    <div class="citizen-services-grid">
                        
                        <!-- Citizen Card 1: NADRA -->
                        <div class="service-card" style="box-shadow:0 6px 25px rgba(0,0,0,0.06);">
                            <div class="service-card-img-box" style="height:210px;">
                                <span class="card-badge" style="background:var(--smz-red); color:#fff;">NADRA CNIC & FRC</span>
                                <img src="assets/images/nadra_cnic.png" alt="NADRA CNIC Assistance">
                            </div>
                            <div class="service-card-body">
                                <h3>NADRA Smart CNIC & FRC Kiosk</h3>
                                <p>Step-by-step guidance for new Smart CNIC, renewal, address modification, and Family Registration Certificate (FRC).</p>
                                <ul style="list-style:none; margin:12px 0 16px 0; font-size:0.85rem; color:#475569; display:flex; flex-direction:column; gap:6px;">
                                    <li><i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:6px;"></i> Smart CNIC Renewal & Address Change</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:6px;"></i> Family Registration Certificate (FRC)</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:6px;"></i> Child B-Form & CRC Requirement Check</li>
                                </ul>
                                <div style="background:#f1f5f9; padding:8px 12px; border-radius:8px; font-size:0.78rem; font-weight:700; color:#0f172a; margin-bottom:16px;">
                                    Processing: Executive 7 Days | Normal 30 Days
                                </div>
                                <a href="https://wa.me/923339688007?text=Inquiry%20about%20NADRA%20CNIC%20and%20FRC%20Guidance" target="_blank" class="btn btn-whatsapp btn-block" style="border-radius:20px; font-size:0.85rem;">
                                    <i class="fa-brands fa-whatsapp"></i> Contact on WhatsApp
                                </a>
                            </div>
                        </div>

                        <!-- Citizen Card 2: Police Clearance -->
                        <div class="service-card" style="box-shadow:0 6px 25px rgba(0,0,0,0.06);">
                            <div class="service-card-img-box" style="height:210px;">
                                <span class="card-badge" style="background:#0B0B0B; color:var(--smz-gold);">POLICE CLEARANCE</span>
                                <img src="assets/images/police_clearance.png" alt="Police Character Clearance">
                            </div>
                            <div class="service-card-body">
                                <h3>Police Character Clearance Certificate</h3>
                                <p>Comprehensive checklist and form preparation for Police Verification certificates required for job, study, or overseas visa.</p>
                                <ul style="list-style:none; margin:12px 0 16px 0; font-size:0.85rem; color:#475569; display:flex; flex-direction:column; gap:6px;">
                                    <li><i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:6px;"></i> Character Certificate Form Verification</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:6px;"></i> CNIC & Passport Copy Attestation</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:6px;"></i> Local Police Station Record Support</li>
                                </ul>
                                <div style="background:#f1f5f9; padding:8px 12px; border-radius:8px; font-size:0.78rem; font-weight:700; color:#0f172a; margin-bottom:16px;">
                                    Verification Time: 3 - 5 Working Days
                                </div>
                                <a href="https://wa.me/923339688007?text=Inquiry%20about%20Police%20Character%20Clearance" target="_blank" class="btn btn-whatsapp btn-block" style="border-radius:20px; font-size:0.85rem;">
                                    <i class="fa-brands fa-whatsapp"></i> Contact on WhatsApp
                                </a>
                            </div>
                        </div>

                        <!-- Citizen Card 3: Domicile -->
                        <div class="service-card" style="box-shadow:0 6px 25px rgba(0,0,0,0.06);">
                            <div class="service-card-img-box" style="height:210px;">
                                <span class="card-badge" style="background:#00a859; color:#fff;">DOMICILE KIOSK</span>
                                <img src="assets/images/domicile_cert.png" alt="Domicile Certificate">
                            </div>
                            <div class="service-card-body">
                                <h3>Domicile & District Residence Certificate</h3>
                                <p>Guidance for District Hangu Domicile application submission, PRC certificates, and education/job quota documentation.</p>
                                <ul style="list-style:none; margin:12px 0 16px 0; font-size:0.85rem; color:#475569; display:flex; flex-direction:column; gap:6px;">
                                    <li><i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:6px;"></i> District Hangu Domicile Application</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:6px;"></i> Educational Certificates Attestation</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:6px;"></i> Govt Jobs & University Quota Guidance</li>
                                </ul>
                                <div style="background:#f1f5f9; padding:8px 12px; border-radius:8px; font-size:0.78rem; font-weight:700; color:#0f172a; margin-bottom:16px;">
                                    Processing Time: 2 - 4 Working Days
                                </div>
                                <a href="https://wa.me/923339688007?text=Inquiry%20about%20Domicile%20Certificate%20Guidance" target="_blank" class="btn btn-whatsapp btn-block" style="border-radius:20px; font-size:0.85rem;">
                                    <i class="fa-brands fa-whatsapp"></i> Contact on WhatsApp
                                </a>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Eye-Catching Trust & Why Choose Us Section -->
            <div class="why-smz-strip" style="padding:40px 0; background:linear-gradient(135deg, rgba(244,196,48,0.12) 0%, rgba(220,38,38,0.06) 100%); border-top:1.5px solid var(--smz-border); border-bottom:1.5px solid var(--smz-border);">
                <div class="container">
                    <div class="why-smz-grid">
                        <div class="why-item" style="transition:transform 0.2s ease;">
                            <div class="why-icon" style="box-shadow:0 4px 12px rgba(220,38,38,0.3);"><i class="fa-solid fa-shield-halved"></i></div>
                            <div>
                                <strong>100% Genuine</strong>
                                <span>Original &amp; Authentic Products Only</span>
                            </div>
                        </div>
                        <div class="why-item" style="transition:transform 0.2s ease;">
                            <div class="why-icon" style="box-shadow:0 4px 12px rgba(220,38,38,0.3);"><i class="fa-solid fa-store"></i></div>
                            <div>
                                <strong>Walk-in Ready</strong>
                                <span>In-Store Services in Hangu</span>
                            </div>
                        </div>
                        <div class="why-item" style="transition:transform 0.2s ease;">
                            <div class="why-icon" style="background:#059669; box-shadow:0 4px 12px rgba(5,150,105,0.3);"><i class="fa-brands fa-whatsapp"></i></div>
                            <div>
                                <strong>WhatsApp Support</strong>
                                <span>Direct Helpline 03339688007</span>
                            </div>
                        </div>
                        <div class="why-item" style="transition:transform 0.2s ease;">
                            <div class="why-icon" style="box-shadow:0 4px 12px rgba(220,38,38,0.3);"><i class="fa-solid fa-tag"></i></div>
                            <div>
                                <strong>Best Prices</strong>
                                <span>Competitive Market Rates Guaranteed</span>
                            </div>
                        </div>
                        <div class="why-item" style="transition:transform 0.2s ease;">
                            <div class="why-icon" style="box-shadow:0 4px 12px rgba(220,38,38,0.3);"><i class="fa-solid fa-clock"></i></div>
                            <div>
                                <strong>Store Hours</strong>
                                <span>8:00 AM - 9:00 PM Every Day</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Eye-Catching Impact Numbers Grid & Proven Excellence -->
            <div class="impact-numbers-section" style="padding:60px 0; background:var(--bg-main);">
                <div class="container">
                    <div class="section-header" style="text-align:center; max-width:760px; margin:0 auto 36px auto;">
                        <span class="sub-heading" style="color:var(--smz-red); font-weight:900; letter-spacing:1.5px; text-transform:uppercase; display:inline-flex; align-items:center; gap:6px; margin-bottom:8px;">
                            <i class="fa-solid fa-award" style="color:#f59e0b; font-size:1.1rem;"></i> PROVEN TRACK RECORD OF EXCELLENCE
                        </span>
                        <h2 class="section-title" style="font-size:2.2rem; font-weight:900; letter-spacing:-0.5px; margin-bottom:12px;">
                            Numbers That Speak For Our <span style="color:var(--smz-red);">Trust &amp; Quality</span>
                        </h2>
                        <p class="section-desc" style="font-size:1rem; color:var(--text-secondary); max-width:640px; margin:0 auto; line-height:1.6;">
                            Proudly serving thousands of families, professionals and businesses across Hangu &amp; KPK with guaranteed genuine devices, expert repairs, and instant digital payments.
                        </p>
                    </div>

                    <div class="impact-numbers-grid">
                        <div class="impact-card" style="position:relative; transition:all 0.3s ease; border:1.5px solid var(--smz-border);">
                            <div class="impact-num" style="font-size:2.6rem; font-weight:900; color:var(--smz-red);">2,500<span class="impact-plus" style="color:var(--smz-gold-dark);">+</span></div>
                            <span class="impact-label" style="font-size:0.95rem; font-weight:800; color:var(--text-main);">Happy Customers</span>
                            <span style="display:inline-block; margin-top:8px; font-size:0.72rem; font-weight:800; color:#059669; background:rgba(5,150,105,0.1); padding:2px 8px; border-radius:10px;">⭐ 4.9/5 Rating</span>
                        </div>
                        <div class="impact-card" style="position:relative; transition:all 0.3s ease; border:1.5px solid var(--smz-border);">
                            <div class="impact-num" style="font-size:2.6rem; font-weight:900; color:var(--smz-red);">8<span class="impact-plus" style="color:var(--smz-gold-dark);">+</span></div>
                            <span class="impact-label" style="font-size:0.95rem; font-weight:800; color:var(--text-main);">Mobile Brands Available</span>
                            <span style="display:inline-block; margin-top:8px; font-size:0.72rem; font-weight:800; color:#0284c7; background:rgba(2,132,199,0.1); padding:2px 8px; border-radius:10px;">📱 Top Global Brands</span>
                        </div>
                        <div class="impact-card" style="position:relative; transition:all 0.3s ease; border:1.5px solid var(--smz-border);">
                            <div class="impact-num" style="font-size:2.6rem; font-weight:900; color:var(--smz-red);">500<span class="impact-plus" style="color:var(--smz-gold-dark);">+</span></div>
                            <span class="impact-label" style="font-size:0.95rem; font-weight:800; color:var(--text-main);">CCTV Systems Installed</span>
                            <span style="display:inline-block; margin-top:8px; font-size:0.72rem; font-weight:800; color:#7c3aed; background:rgba(124,58,237,0.1); padding:2px 8px; border-radius:10px;">🛡️ 24/7 Security</span>
                        </div>
                        <div class="impact-card" style="position:relative; transition:all 0.3s ease; border:1.5px solid var(--smz-border);">
                            <div class="impact-num" style="font-size:2.6rem; font-weight:900; color:var(--smz-red);">50K<span class="impact-plus" style="color:var(--smz-gold-dark);">+</span></div>
                            <span class="impact-label" style="font-size:0.95rem; font-weight:800; color:var(--text-main);">Financial Transactions</span>
                            <span style="display:inline-block; margin-top:8px; font-size:0.72rem; font-weight:800; color:#d97706; background:rgba(217,119,6,0.1); padding:2px 8px; border-radius:10px;">⚡ Instant &amp; Safe</span>
                        </div>
                        <div class="impact-card" style="position:relative; transition:all 0.3s ease; border:1.5px solid var(--smz-border);">
                            <div class="impact-num" style="font-size:2.6rem; font-weight:900; color:var(--smz-red);">1,200<span class="impact-plus" style="color:var(--smz-gold-dark);">+</span></div>
                            <span class="impact-label" style="font-size:0.95rem; font-weight:800; color:var(--text-main);">Citizens Assisted</span>
                            <span style="display:inline-block; margin-top:8px; font-size:0.72rem; font-weight:800; color:#dc2626; background:rgba(220,38,38,0.1); padding:2px 8px; border-radius:10px;">🪪 NADRA &amp; Domicile</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Brand Promise & Identity -->
            <div class="section-padding">
                <div class="container">
                    <div class="section-header">
                        <span class="sub-heading">BRAND PROMISE & IDENTITY</span>
                        <h2 class="section-title">Vision & Mission of <span style="color:var(--smz-red);">Safdar Mobile Store</span></h2>
                        <p class="section-desc">Driven by values of trust, quality, and community. Proud to serve Hangu and beyond.</p>
                    </div>

                    <div class="vm-vertical-layout">
                        <!-- Vision -->
                        <div class="vm-vertical-card">
                            <div class="vm-vertical-left">
                                <div class="vm-v-icon"><i class="fa-solid fa-eye"></i></div>
                                <span class="vm-v-label">OUR VISION</span>
                            </div>
                            <div class="vm-vertical-right">
                                <h3>To Be the Region's Most Trusted Digital Hub</h3>
                                <p>To be the most trusted store and digital facilitation center in the region, empowering families and businesses through authentic mobile technology, advanced CCTV security solutions, accessible financial services, and accessible government document guidance — all under one roof with uncompromised integrity and community care.</p>
                                <div class="vm-v-pillars">
                                    <span><i class="fa-solid fa-circle-check"></i> Authentic Mobile Technology</span>
                                    <span><i class="fa-solid fa-circle-check"></i> Advanced CCTV Security</span>
                                    <span><i class="fa-solid fa-circle-check"></i> Accessible Financial Services</span>
                                    <span><i class="fa-solid fa-circle-check"></i> Citizen Documentation Support</span>
                                </div>
                            </div>
                        </div>

                        <!-- Mission -->
                        <div class="vm-vertical-card">
                            <div class="vm-vertical-left">
                                <div class="vm-v-icon"><i class="fa-solid fa-bullseye"></i></div>
                                <span class="vm-v-label">OUR MISSION</span>
                            </div>
                            <div class="vm-vertical-right">
                                <h3>Deliver Excellence with Transparency & Speed</h3>
                                <p>At Safdar Mobile Store, our mission is to deliver genuine, high-quality mobile devices, real accessories, top-tier CCTV security systems, and fast financial/service desk assistance—backed by transparent support, fair pricing, speed, and customer-first dedication — every single day to every member of our community.</p>
                                <div class="vm-v-pillars">
                                    <span><i class="fa-solid fa-circle-check"></i> Genuine Quality Only</span>
                                    <span><i class="fa-solid fa-circle-check"></i> Absolute Transparency</span>
                                    <span><i class="fa-solid fa-circle-check"></i> Customer-First Experience</span>
                                    <span><i class="fa-solid fa-circle-check"></i> Community Empowerment</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Core Values Cards -->
                    <div class="vm-values-row" style="margin-top:30px;">
                        <div class="vm-value-item">
                            <i class="fa-solid fa-handshake" style="color:var(--smz-red);"></i>
                            <strong>Trust</strong>
                            <span>Every product and service we offer is backed by our promise of trust.</span>
                        </div>

                        <div class="vm-value-item">
                            <i class="fa-solid fa-star" style="color:var(--smz-gold);"></i>
                            <strong>Quality</strong>
                            <span>Rigorous selection to ensure peak quality across all categories.</span>
                        </div>

                        <div class="vm-value-item">
                            <i class="fa-solid fa-users" style="color:var(--smz-red);"></i>
                            <strong>Community</strong>
                            <span>Serving Hangu and its people with respect and dedication.</span>
                        </div>

                        <div class="vm-value-item">
                            <i class="fa-solid fa-bolt" style="color:var(--smz-gold);"></i>
                            <strong>Speed</strong>
                            <span>Fast service, rapid financial transactions, and quick turnarounds always.</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- TAB: ALL PRODUCTS & CATEGORIES CATALOG -->
        <section id="tab-catalog" class="page-tab" style="display: none;">
            <div class="section-padding">
                <div class="container">
                    <div class="section-header">
                        <span class="sub-heading">OFFICIAL STORE CATALOG</span>
                        <h2 class="section-title">All Products &amp; Categories</h2>
                        <p class="section-desc">Explore all authentic products across all categories. Filter by category, brand, or search in real-time. Contact us on WhatsApp (03339688007) for live stock inquiries.</p>
                    </div>

                    <!-- Search Box & Category Filters -->
                    <div style="margin-bottom:24px; max-width:560px; margin-left:auto; margin-right:auto; position:relative;">
                        <i class="fa-solid fa-magnifying-glass" style="position:absolute; left:16px; top:50%; transform:translateY(-50%); color:var(--smz-gold); font-size:1.1rem;"></i>
                        <input type="text" id="catalogSearchInput" placeholder="Search any product name, model, brand or category..." oninput="handleCatalogLiveSearch(this.value)" style="width:100%; height:48px; padding-left:46px; padding-right:16px; border-radius:30px; border:2px solid var(--smz-border); background:var(--card-bg); color:var(--text-main); font-weight:600; font-size:0.95rem; box-shadow:0 4px 15px rgba(0,0,0,0.06); outline:none;">
                    </div>

                    <!-- Dynamic Category Filter Bar (Populated from Categories API) -->
                    <div class="filter-bar" id="catalogCategoryFilterBar" style="justify-content:center; flex-wrap:wrap; margin-bottom:28px;">
                        <button class="filter-btn active" data-category="all">
                            <i class="fa-solid fa-layer-group"></i> All Products
                        </button>
                        <!-- Populated dynamically with all categories -->
                    </div>

                    <div class="products-grid" id="catalogProductGrid">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>
        </section>

        <!-- TAB 2: MOBILES -->
        <section id="tab-mobiles" class="page-tab" style="display: none;">
            <div class="section-padding">
                <div class="container">
                    <div class="section-header">
                        <span class="sub-heading">OFFICIAL MOBILE STORE</span>
                        <h2 class="section-title">Smartphones Catalog</h2>
                        <p class="section-desc">Browse authentic smartphones with official company PTA approval. Contact us on WhatsApp (03339688007) for orders and stock inquiries.</p>
                    </div>

                    <div class="filter-bar" id="mobilesFilterBar">
                        <button class="filter-btn active" data-filter="all"><i class="fa-solid fa-layer-group"></i> All Brands</button>
                        <button class="filter-btn" data-filter="samsung"><i class="fa-solid fa-mobile"></i> Samsung</button>
                        <button class="filter-btn" data-filter="apple"><i class="fa-brands fa-apple"></i> Apple</button>
                        <button class="filter-btn" data-filter="infinix"><i class="fa-solid fa-mobile"></i> Infinix</button>
                        <button class="filter-btn" data-filter="tecno"><i class="fa-solid fa-mobile"></i> Tecno</button>
                        <button class="filter-btn" data-filter="xiaomi"><i class="fa-solid fa-mobile"></i> Xiaomi</button>
                        <button class="filter-btn" data-filter="vivo"><i class="fa-solid fa-mobile"></i> Vivo</button>
                    </div>

                    <div class="products-grid" id="mobilesProductGrid">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>
        </section>

        <!-- TAB 3: ACCESSORIES -->
        <section id="tab-accessories" class="page-tab" style="display: none;">
            <div class="section-padding">
                <div class="container">
                    <div class="section-header">
                        <span class="sub-heading">PREMIUM ACCESSORIES</span>
                        <h2 class="section-title">Mobile Chargers, Audio & Accessories</h2>
                        <p class="section-desc">High quality fast chargers, true wireless airbuds, ceramic protectors, power banks, and car accessories.</p>
                    </div>

                    <div class="filter-bar" id="accessoriesFilterBar">
                        <button class="filter-btn active" data-filter="all"><i class="fa-solid fa-layer-group"></i> All Items</button>
                        <button class="filter-btn" data-filter="chargers"><i class="fa-solid fa-bolt"></i> Chargers</button>
                        <button class="filter-btn" data-filter="airbuds"><i class="fa-solid fa-headphones"></i> Airbuds</button>
                        <button class="filter-btn" data-filter="vehicle chargers"><i class="fa-solid fa-car"></i> Car Chargers</button>
                        <button class="filter-btn" data-filter="screen protectors"><i class="fa-solid fa-shield"></i> Protectors</button>
                        <button class="filter-btn" data-filter="powerbanks"><i class="fa-solid fa-battery-full"></i> Power Banks</button>
                        <button class="filter-btn" data-filter="smartwatches"><i class="fa-solid fa-clock"></i> Smart Watches</button>
                        <button class="filter-btn" data-filter="cables"><i class="fa-solid fa-plug"></i> Cables</button>
                    </div>

                    <div class="products-grid" id="accessoriesProductGrid">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>
        </section>

        <!-- TAB 4: CCTV SECURITY -->
        <section id="tab-cctv" class="page-tab" style="display: none;">
            <div class="section-padding">
                <div class="container">
                    <div class="section-header">
                        <span class="sub-heading">SECURITY & SURVEILLANCE</span>
                        <h2 class="section-title">CCTV Security Cameras & Systems</h2>
                        <p class="section-desc">Commercial grade 4K cameras, night vision, DVR recorders, and installation cables.</p>
                    </div>

                    <div class="filter-bar" id="cctvFilterBar">
                        <button class="filter-btn active" data-filter="all"><i class="fa-solid fa-layer-group"></i> All Systems</button>
                        <button class="filter-btn" data-filter="hikvision"><i class="fa-solid fa-video"></i> Hikvision</button>
                        <button class="filter-btn" data-filter="dahua"><i class="fa-solid fa-video"></i> Dahua</button>
                        <button class="filter-btn" data-filter="solar cctv"><i class="fa-solid fa-sun"></i> Solar 4G</button>
                        <button class="filter-btn" data-filter="dvr recorders"><i class="fa-solid fa-hard-drive"></i> DVR Recorders</button>
                    </div>

                    <div class="products-grid" id="cctvProductGrid">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>
        </section>

        <!-- TAB 5: FINANCIAL SERVICES & TELECOM -->
        <section id="tab-financial" class="page-tab" style="display: none;">
            <div class="section-padding">
                <div class="container">
                    <div class="section-header">
                        <span class="sub-heading">FINANCIAL & TELECOM SERVICES</span>
                        <h2 class="section-title">Easypaisa, JazzCash & <span style="color:var(--smz-red);">Online Payments</span></h2>
                        <p class="section-desc">Official merchant payment accounts for online transfers, mobile load recharges, and bill payments.</p>
                    </div>

                    <!-- OFFICIAL PAYMENT ACCOUNT & SCAN-TO-PAY QR BANNER -->
                    <div style="background:linear-gradient(135deg, #0B0B0B 0%, #171717 100%); border:2px solid var(--smz-gold); border-radius:18px; padding:28px; color:#fff; margin-bottom:32px; box-shadow:0 12px 35px rgba(0,0,0,0.35);">
                        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; border-bottom:1px solid rgba(255,255,255,0.12); padding-bottom:18px; margin-bottom:24px;">
                            <div>
                                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                    <span style="background:var(--smz-gold); color:#000; font-size:0.75rem; font-weight:900; padding:4px 12px; border-radius:20px; text-transform:uppercase; letter-spacing:1px;">OFFICIAL MERCHANT ACCOUNTS</span>
                                    <span style="background:#00a859; color:#fff; font-size:0.72rem; font-weight:800; padding:3px 10px; border-radius:14px;"><i class="fa-solid fa-bolt"></i> Raast Instant Pay</span>
                                </div>
                                <h3 style="font-family:var(--font-heading); font-size:1.7rem; margin:8px 0 0 0; color:#fff;">
                                    Zindigi (JS Bank), Raast QR & Digital Wallet Payments
                                </h3>
                                <p style="margin:4px 0 0 0; font-size:0.88rem; color:#cbd5e1;">Scan QR code or transfer directly to our official business merchant accounts.</p>
                            </div>
                            <a href="https://wa.me/923339688007?text=Payment%20Receipt%20Confirmation%20for%20Safdar%20Mobile%20Store" target="_blank" class="btn btn-whatsapp circle-style-btn" style="padding:10px 24px; font-weight:700;">
                                <i class="fa-brands fa-whatsapp"></i> Send Payment Receipt
                            </a>
                        </div>

                        <div class="bank-qr-grid">
                            <!-- QR Code Card -->
                            <div style="background:rgba(255,255,255,0.05); border:1.5px solid rgba(244,196,48,0.4); border-radius:14px; padding:20px; text-align:center; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                <div style="background:#00a859; color:#fff; font-size:0.72rem; font-weight:800; padding:3px 12px; border-radius:12px; margin-bottom:12px; text-transform:uppercase;">
                                    SCAN & PAY WITH ANY BANKING APP
                                </div>
                                <div style="background:#fff; padding:12px; border-radius:12px; box-shadow:0 8px 25px rgba(0,0,0,0.5); max-width:220px; width:100%; margin-bottom:12px;">
                                    <img src="assets/images/zindigi_payment_qr.jpg" alt="Zindigi JS Bank Raast Payment QR" style="width:100%; height:auto; display:block; border-radius:6px;">
                                </div>
                                <div style="font-size:0.8rem; color:#fde68a; font-weight:700; line-height:1.4;">
                                    <i class="fa-solid fa-qrcode" style="color:var(--smz-gold); margin-right:4px;"></i> Scan via Zindigi, Raast, Easypaisa, JazzCash, Nayapay, Sadapay, HBL, Meezan & 1Link Apps
                                </div>
                            </div>

                            <!-- Bank Details Cards -->
                            <div style="display:flex; flex-direction:column; gap:12px; justify-content:center;">
                                <!-- ZINDIGI / JS BANK & IBAN -->
                                <div style="background:rgba(255,255,255,0.06); padding:16px; border-radius:12px; border-left:4px solid #00a859;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                                        <strong style="color:var(--smz-gold); font-size:0.95rem;">ZINDIGI (Powered by JS BANK)</strong>
                                        <span style="background:rgba(0,168,89,0.2); color:#34d399; font-size:0.68rem; font-weight:800; padding:2px 6px; border-radius:4px;">VERIFIED MERCHANT</span>
                                    </div>
                                    <div style="font-size:0.82rem; color:#cbd5e1;">Merchant Name: <strong style="color:#fff;">Safdar Mobile Store</strong></div>
                                    <div style="font-size:0.82rem; color:#cbd5e1; margin-top:2px;">Account No / Raast ID: <strong style="color:#34d399; font-family:monospace; font-size:1rem;">0333 9688007</strong></div>
                                    <div style="display:flex; align-items:center; justify-content:space-between; margin-top:4px; background:rgba(0,0,0,0.3); padding:6px 10px; border-radius:6px;">
                                        <span style="font-size:0.75rem; color:#94a3b8; font-family:monospace;">IBAN: <strong style="color:#fff;">PK28JSBL9999903339688007</strong></span>
                                        <button type="button" class="pos-btn pos-btn-sm" style="background:#00a859; color:#fff; padding:2px 8px; font-size:0.7rem; border-radius:4px;" onclick="window.copyToClipboard('PK28JSBL9999903339688007', this)">
                                            <i class="fa-solid fa-copy"></i> Copy
                                        </button>
                                    </div>
                                    <div style="display:flex; align-items:center; justify-content:space-between; margin-top:4px; background:rgba(0,0,0,0.3); padding:6px 10px; border-radius:6px;">
                                        <span style="font-size:0.75rem; color:#94a3b8; font-family:monospace;">Till ID: <strong style="color:#fff;">946425125</strong></span>
                                        <button type="button" class="pos-btn pos-btn-sm" style="background:#00a859; color:#fff; padding:2px 8px; font-size:0.7rem; border-radius:4px;" onclick="window.copyToClipboard('946425125', this)">
                                            <i class="fa-solid fa-copy"></i> Copy
                                        </button>
                                    </div>
                                </div>

                                <!-- Easypaisa & JazzCash -->
                                <div class="ep-jc-grid">
                                    <div style="background:rgba(255,255,255,0.06); padding:12px; border-radius:10px; border-left:3px solid #00a859;">
                                        <div style="font-size:0.75rem; color:#a1a1aa;">Easypaisa Account</div>
                                        <div style="font-weight:900; color:#34d399; font-family:monospace; font-size:0.95rem;">03339688007</div>
                                        <div style="font-size:0.7rem; color:#cbd5e1;">Safdar Mobile Store</div>
                                    </div>
                                    <div style="background:rgba(255,255,255,0.06); padding:12px; border-radius:10px; border-left:3px solid #e30613;">
                                        <div style="font-size:0.75rem; color:#a1a1aa;">JazzCash Account</div>
                                        <div style="font-weight:900; color:#f87171; font-family:monospace; font-size:0.95rem;">03339688007</div>
                                        <div style="font-size:0.7rem; color:#cbd5e1;">Safdar Mobile Store</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Store Policy Banner -->
                        <div style="margin-top:22px; padding:16px 20px; background:rgba(239,68,68,0.18); border:1.5px solid rgba(239,68,68,0.5); border-radius:12px; font-size:0.88rem;">
                            <div style="font-weight:900; font-size:1.05rem; color:#FFFFFF; margin-bottom:6px;">
                                <i class="fa-solid fa-shield-halved" style="color:var(--smz-red); margin-right:8px;"></i> Store Ordering & Delivery Policy
                            </div>
                            <ul style="margin:0; padding-left:20px; line-height:1.6; color:#FEE2E2;">
                                <li><strong style="color:#FFFFFF;">100% Full Advance Payment Required:</strong> Products are dispatched only after receiving 100% advance payment via Zindigi / JS Bank, Raast QR, Easypaisa, or JazzCash (03339688007).</li>
                                <li><strong style="color:#FFFFFF;">Delivery Charges Paid by Customer:</strong> Nationwide courier delivery charges apply and must be paid by the customer as per standard TCS / Leopards courier rates.</li>
                            </ul>
                        </div>
                    </div>

                    <div class="financial-services-container">
                        <!-- Card 1: Easypaisa Kiosk -->
                        <div class="fin-card easypaisa-card">
                            <div>
                                <div class="fin-header">
                                    <div class="fin-icon" style="background:#e8f5e9; color:#00a859;"><i class="fa-solid fa-mobile-screen-button"></i></div>
                                    <div>
                                        <h3>Easypaisa Money Transfer</h3>
                                        <span class="fin-tag">Verified Merchant Kiosk</span>
                                    </div>
                                </div>
                                <ul class="fin-list">
                                    <li><i class="fa-solid fa-circle-check" style="color:#00a859;"></i> Nationwide CNIC Money Send & Receive</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#00a859;"></i> Bank Account & Raast Instant Money Transfer</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#00a859;"></i> Easypaisa Wallet Cash-In (Deposit) & Cash-Out</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#00a859;"></i> Instant Biometric Verification & Account Setup</li>
                                </ul>
                            </div>
                            <a href="https://wa.me/923339688007?text=Inquiry%20about%20Easypaisa%20Money%20Transfer" target="_blank" class="btn btn-whatsapp circle-style-btn" style="padding:8px 16px; font-size:0.82rem;">
                                <i class="fa-brands fa-whatsapp"></i> Easypaisa Inquiry
                            </a>
                        </div>

                        <!-- Card 2: JazzCash Kiosk -->
                        <div class="fin-card jazzcash-card">
                            <div>
                                <div class="fin-header">
                                    <div class="fin-icon" style="background:#ffebee; color:#e30613;"><i class="fa-solid fa-wallet"></i></div>
                                    <div>
                                        <h3>JazzCash Money Transfer</h3>
                                        <span class="fin-tag">Digital Money Hub</span>
                                    </div>
                                </div>
                                <ul class="fin-list">
                                    <li><i class="fa-solid fa-circle-check" style="color:#e30613;"></i> CNIC & JazzCash Account Money Transfer</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#e30613;"></i> Wallet Cash Deposit & Cash Withdrawal</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#e30613;"></i> School, College & University Fee Deposits</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#e30613;"></i> Government Tax & Vehicle Token Tax Payment</li>
                                </ul>
                            </div>
                            <a href="https://wa.me/923339688007?text=Inquiry%20about%20JazzCash%20Services" target="_blank" class="btn btn-whatsapp circle-style-btn" style="padding:8px 16px; font-size:0.82rem;">
                                <i class="fa-brands fa-whatsapp"></i> JazzCash Inquiry
                            </a>
                        </div>

                        <!-- Card 3: Utility Bill Payments Counter -->
                        <div class="fin-card bills-card">
                            <div>
                                <div class="fin-header">
                                    <div class="fin-icon" style="background:#e0f2fe; color:#0284c7;"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                                    <div>
                                        <h3>Utility Bill Payments</h3>
                                        <span class="fin-tag">Bills Paid Here Counter</span>
                                    </div>
                                </div>
                                <ul class="fin-list">
                                    <li><i class="fa-solid fa-circle-check" style="color:#0284c7;"></i> Electricity Bills (PESCO, WAPDA, K-Electric)</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#0284c7;"></i> Sui Northern & Southern Gas Bills (SNGPL, SSGC)</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#0284c7;"></i> Water & Sanitation Utility Bill Payment</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#0284c7;"></i> PTCL Landline, Broadband & Flash Fiber Bills</li>
                                </ul>
                            </div>
                            <a href="https://wa.me/923339688007?text=Inquiry%20about%20Utility%20Bill%20Payment" target="_blank" class="btn btn-whatsapp circle-style-btn" style="padding:8px 16px; font-size:0.82rem;">
                                <i class="fa-brands fa-whatsapp"></i> Pay Bill via WhatsApp
                            </a>
                        </div>

                        <!-- Card 4: All SIM Easyload & Recharges -->
                        <div class="fin-card easyload-card">
                            <div>
                                <div class="fin-header">
                                    <div class="fin-icon" style="background:#f3e8ff; color:#7c3aed;"><i class="fa-solid fa-bolt"></i></div>
                                    <div>
                                        <h3>All SIM Mobile Easyload</h3>
                                        <span class="fin-tag">Instant Balance Recharge</span>
                                    </div>
                                </div>
                                <ul class="fin-list">
                                    <li><i class="fa-solid fa-circle-check" style="color:#7c3aed;"></i> <strong>Jazz / Warid</strong> Instant Easyload & Balance</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#7c3aed;"></i> <strong>Zong 4G</strong> Instant Easyload & Balance</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#7c3aed;"></i> <strong>Telenor 4G</strong> Instant Easyload & Balance</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#7c3aed;"></i> <strong>Ufone 4G</strong> Instant Easyload & Balance</li>
                                </ul>
                                <div class="carrier-pill-group">
                                    <span class="carrier-pill jazz">Jazz</span>
                                    <span class="carrier-pill zong">Zong 4G</span>
                                    <span class="carrier-pill telenor">Telenor</span>
                                    <span class="carrier-pill ufone">Ufone</span>
                                </div>
                            </div>
                            <a href="https://wa.me/923339688007?text=I%20want%20to%20get%20Mobile%20Easyload" target="_blank" class="btn btn-whatsapp circle-style-btn" style="padding:8px 16px; font-size:0.82rem; margin-top:16px;">
                                <i class="fa-brands fa-whatsapp"></i> Get Easyload Now
                            </a>
                        </div>

                        <!-- Card 5: All SIM Mobile Packages & Internet Bundles -->
                        <div class="fin-card packages-card">
                            <div>
                                <div class="fin-header">
                                    <div class="fin-icon" style="background:#fef3c7; color:#d97706;"><i class="fa-solid fa-cubes"></i></div>
                                    <div>
                                        <h3>All SIM Mobile Packages</h3>
                                        <span class="fin-tag">Data, Call & SMS Bundles</span>
                                    </div>
                                </div>
                                <ul class="fin-list">
                                    <li><i class="fa-solid fa-circle-check" style="color:#d97706;"></i> <strong>Jazz:</strong> Super Duper & Weekly Mega 4G Internet</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#d97706;"></i> <strong>Zong:</strong> Monthly Super Cards & Unlimited 4G</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#d97706;"></i> <strong>Telenor:</strong> EasyCards, Hybrid & All-Net Minutes</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#d97706;"></i> <strong>Ufone:</strong> Super Cards, WhatsApp & TikTok Offers</li>
                                </ul>
                                <div class="carrier-pill-group">
                                    <span class="carrier-pill jazz">Jazz Bundles</span>
                                    <span class="carrier-pill zong">Zong Cards</span>
                                    <span class="carrier-pill telenor">Telenor Cards</span>
                                    <span class="carrier-pill ufone">Ufone Cards</span>
                                </div>
                            </div>
                            <a href="https://wa.me/923339688007?text=Inquiry%20about%20Mobile%20Packages%20Activation" target="_blank" class="btn btn-whatsapp circle-style-btn" style="padding:8px 16px; font-size:0.82rem; margin-top:16px;">
                                <i class="fa-brands fa-whatsapp"></i> Activate Package
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- TAB 6: CITIZEN KIOSK -->
        <section id="tab-citizen" class="page-tab" style="display: none;">
            <div class="section-padding">
                <div class="container">
                    <div class="section-header">
                        <span class="sub-heading">GOVERNMENT & CITIZEN FACILITATION</span>
                        <h2 class="section-title">Nadra, Police Clearance & Domicile</h2>
                        <p class="section-desc">Information and requirement guidelines for CNIC, FRC, Police Character Certificate & Domicile in Hangu, KPK.</p>
                    </div>

                    <div class="citizen-services-grid">
                        
                        <!-- NADRA -->
                        <div class="service-card" style="box-shadow:0 6px 25px rgba(0,0,0,0.06);">
                            <div class="service-card-img-box" style="height:220px;">
                                <span class="card-badge" style="background:var(--smz-red); color:#fff;">NADRA CNIC & FRC</span>
                                <img src="assets/images/nadra_cnic.png" alt="NADRA CNIC Kiosk">
                            </div>
                            <div class="service-card-body">
                                <h3>NADRA Smart CNIC & FRC Kiosk</h3>
                                <p>Requirement guidelines & online appointment preparation for Smart CNIC renewal and Family Registration Certificate (FRC).</p>
                                <ul style="list-style:none; margin:12px 0 16px 0; font-size:0.85rem; color:#475569; display:flex; flex-direction:column; gap:6px;">
                                    <li><i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:6px;"></i> Smart CNIC Renewal & Address Change</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:6px;"></i> Family Registration Certificate (FRC)</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:6px;"></i> Child B-Form & CRC Verification</li>
                                </ul>
                                <div style="background:#f1f5f9; padding:10px; border-radius:8px; font-size:0.8rem; font-weight:700; color:#0f172a; margin-bottom:16px;">
                                    Processing: Executive 7 Days | Normal 30 Days
                                </div>
                                <a href="https://wa.me/923339688007?text=Inquiry%20about%20NADRA%20CNIC%20and%20FRC%20Guidance" target="_blank" class="btn btn-whatsapp btn-block" style="border-radius:20px;">
                                    <i class="fa-brands fa-whatsapp"></i> Contact on WhatsApp
                                </a>
                            </div>
                        </div>

                        <!-- Police Clearance -->
                        <div class="service-card" style="box-shadow:0 6px 25px rgba(0,0,0,0.06);">
                            <div class="service-card-img-box" style="height:220px;">
                                <span class="card-badge" style="background:#0B0B0B; color:var(--smz-gold);">POLICE CLEARANCE</span>
                                <img src="assets/images/police_clearance_kpk.png" alt="Police Character Clearance">
                            </div>
                            <div class="service-card-body">
                                <h3>Police Character Clearance Certificate</h3>
                                <p>Guidance for Police Character Verification certificates required for job, university, or overseas visa application.</p>
                                
                                <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:10px; padding:12px; margin:12px 0;">
                                    <div style="font-size:0.8rem; font-weight:800; color:#b45309; text-transform:uppercase; margin-bottom:6px;">
                                        <i class="fa-solid fa-file-shield" style="margin-right:4px;"></i> Required Documents:
                                    </div>
                                    <ol style="margin:0; padding-left:18px; font-size:0.85rem; font-weight:700; color:#1e293b; line-height:1.6;">
                                        <li>Applicant CNIC Card</li>
                                        <li>2 Witness Persons (with CNIC copies)</li>
                                    </ol>
                                </div>

                                <ul style="list-style:none; margin:10px 0 14px 0; font-size:0.82rem; color:#475569; display:flex; flex-direction:column; gap:6px;">
                                    <li><i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:6px;"></i> Character Certificate Form Verification</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:6px;"></i> Witness & CNIC Document Preparation</li>
                                </ul>
                                <div style="background:#f1f5f9; padding:10px; border-radius:8px; font-size:0.8rem; font-weight:700; color:#0f172a; margin-bottom:16px;">
                                    Verification Time: 3 - 5 Working Days
                                </div>
                                <a href="https://wa.me/923339688007?text=Inquiry%20about%20Police%20Character%20Clearance" target="_blank" class="btn btn-whatsapp btn-block" style="border-radius:20px;">
                                    <i class="fa-brands fa-whatsapp"></i> Contact on WhatsApp
                                </a>
                            </div>
                        </div>

                        <!-- Domicile -->
                        <div class="service-card" style="box-shadow:0 6px 25px rgba(0,0,0,0.06);">
                            <div class="service-card-img-box" style="height:220px;">
                                <span class="card-badge" style="background:#00a859; color:#fff;">DOMICILE KIOSK</span>
                                <img src="assets/images/domicile_cert.png" alt="Domicile Certificate">
                            </div>
                            <div class="service-card-body">
                                <h3>Domicile & District Residence Certificate</h3>
                                <p>Guidance for District Hangu Domicile application submission, PRC certificates, and education/job quota documentation.</p>
                                
                                <div style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:10px; padding:12px; margin:12px 0;">
                                    <div style="font-size:0.8rem; font-weight:800; color:#047857; text-transform:uppercase; margin-bottom:6px;">
                                        <i class="fa-solid fa-file-lines" style="margin-right:4px;"></i> Required Documents:
                                    </div>
                                    <ol style="margin:0; padding-left:18px; font-size:0.85rem; font-weight:700; color:#1e293b; line-height:1.6;">
                                        <li>Birth Certificate</li>
                                        <li>Parents CNIC</li>
                                        <li>Form B (Child Registration Certificate)</li>
                                    </ol>
                                </div>

                                <ul style="list-style:none; margin:10px 0 14px 0; font-size:0.82rem; color:#475569; display:flex; flex-direction:column; gap:6px;">
                                    <li><i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:6px;"></i> District Hangu Domicile Application</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:6px;"></i> Govt Jobs & University Quota Guidance</li>
                                </ul>
                                <div style="background:#f1f5f9; padding:10px; border-radius:8px; font-size:0.8rem; font-weight:700; color:#0f172a; margin-bottom:16px;">
                                    Processing Time: 2 - 4 Working Days
                                </div>
                                <a href="https://wa.me/923339688007?text=Inquiry%20about%20Domicile%20Certificate%20Guidance" target="_blank" class="btn btn-whatsapp btn-block" style="border-radius:20px;">
                                    <i class="fa-brands fa-whatsapp"></i> Contact on WhatsApp
                                </a>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </section>

        <!-- TAB 6: MOBILE REPAIR SERVICE LAB -->
        <section id="tab-repair" class="page-tab" style="display: none;">
            <div class="section-padding">
                <div class="container">
                    <div class="section-header">
                        <span class="sub-heading">EXPERT HARDWARE & SOFTWARE SOLUTIONS</span>
                        <h2 class="section-title">Professional Mobile Repairing <span style="color:var(--smz-red);">Lab</span></h2>
                        <p class="section-desc">All types of mobile repairing work done professionally by certified Pakistani technicians using precision digital microscopes and micro-soldering workstations.</p>
                    </div>

                    <div class="repair-hero-box" style="background:linear-gradient(135deg, #0B0B0B 0%, #171717 100%); border:2px solid var(--smz-gold); border-radius:18px; padding:28px; color:#fff; margin-bottom:35px; box-shadow:0 12px 35px rgba(0,0,0,0.35);">
                        <div class="repair-hero-grid">
                            <!-- Photo of Pakistani Technician -->
                            <div style="position:relative; border-radius:14px; overflow:hidden; border:2px solid rgba(244,196,48,0.4); box-shadow:0 8px 25px rgba(0,0,0,0.6);">
                                <span style="position:absolute; top:12px; left:12px; background:#059669; color:#fff; font-size:0.75rem; font-weight:800; padding:4px 12px; border-radius:20px; z-index:2; box-shadow:0 2px 8px rgba(0,0,0,0.4);">
                                    <i class="fa-solid fa-shield-halved"></i> 100% Professional Lab
                                </span>
                                <img src="assets/images/pakistani_mobile_repair_expert.jpg" alt="Skilled Pakistani Mobile Repair Technician" style="width:100%; height:auto; display:block; object-fit:cover;">
                                <div style="position:absolute; bottom:0; left:0; right:0; background:linear-gradient(180deg, transparent 0%, rgba(0,0,0,0.85) 100%); padding:16px 14px 10px 14px; font-size:0.8rem; color:#fde68a;">
                                    <i class="fa-solid fa-microscope" style="color:var(--smz-gold); margin-right:6px;"></i> High-precision chip-level motherboard repair & digital microscope soldering in Hangu
                                </div>
                            </div>

                            <!-- Highlights & Values -->
                            <div>
                                <span style="background:var(--smz-red); color:#fff; font-size:0.72rem; font-weight:900; padding:4px 10px; border-radius:4px; text-transform:uppercase; letter-spacing:1px;">
                                    PREMIUM SMARTPHONE CLINIC
                                </span>
                                <h3 style="font-family:var(--font-heading); font-size:1.8rem; margin:10px 0 8px 0; color:#fff; line-height:1.2;">
                                    All Types of Mobile Repairing Done Professionally
                                </h3>
                                <p style="font-size:0.92rem; color:#cbd5e1; line-height:1.5; margin-bottom:18px;">
                                    At <strong>Safdar Mobile Store</strong>, we repair all major brands including <strong>Apple iPhone, Samsung, Xiaomi, Infinix, Tecno, Vivo, Realme, and Oppo</strong>. We use genuine original replacement parts, heat-controlled laminating machines, and digital micro-soldering stations.
                                </p>

                                <div class="repair-features-grid">
                                    <div style="background:rgba(255,255,255,0.06); padding:10px 12px; border-radius:8px; border-left:3px solid #10b981;">
                                        <i class="fa-solid fa-check" style="color:#10b981; margin-right:6px;"></i> Same-Day Quick Delivery
                                    </div>
                                    <div style="background:rgba(255,255,255,0.06); padding:10px 12px; border-radius:8px; border-left:3px solid var(--smz-gold);">
                                        <i class="fa-solid fa-check" style="color:var(--smz-gold); margin-right:6px;"></i> 100% Genuine Spare Parts
                                    </div>
                                    <div style="background:rgba(255,255,255,0.06); padding:10px 12px; border-radius:8px; border-left:3px solid #3b82f6;">
                                        <i class="fa-solid fa-check" style="color:#3b82f6; margin-right:6px;"></i> Chip-Level IC Repair
                                    </div>
                                    <div style="background:rgba(255,255,255,0.06); padding:10px 12px; border-radius:8px; border-left:3px solid var(--smz-red);">
                                        <i class="fa-solid fa-check" style="color:var(--smz-red); margin-right:6px;"></i> Complete Data Privacy
                                    </div>
                                </div>

                                <div style="display:flex; gap:12px; flex-wrap:wrap;">
                                    <a href="https://wa.me/923339688007?text=Hello%20Safdar%20Mobile%20Store!%20I%20need%20to%20get%20my%20mobile%20repaired.%20Model%20and%20Problem:" target="_blank" class="btn btn-whatsapp circle-style-btn" style="padding:12px 26px; font-weight:700;">
                                        <i class="fa-brands fa-whatsapp"></i> Chat with Repair Technician
                                    </a>
                                    <a href="tel:03339688007" class="btn btn-outline circle-style-btn" style="padding:12px 22px;">
                                        <i class="fa-solid fa-phone"></i> 03339688007
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 6 DETAILED REPAIR SERVICE CARDS -->
                    <div class="repair-cards-grid">
                        
                        <!-- 1. Screen Replacement -->
                        <div class="service-card" style="box-shadow:0 6px 20px rgba(0,0,0,0.06);">
                            <div class="service-card-body">
                                <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
                                    <span style="background:rgba(215,25,32,0.1); color:var(--smz-red); width:44px; height:44px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; font-size:1.3rem;">
                                        <i class="fa-solid fa-mobile-screen"></i>
                                    </span>
                                    <div>
                                        <h3 style="margin:0; font-size:1.15rem;">Screen & Touch LCD Replacement</h3>
                                        <span style="font-size:0.75rem; color:#059669; font-weight:800;">AMOLED, OLED & IPS Panels</span>
                                    </div>
                                </div>
                                <p style="font-size:0.88rem;">Precision replacement of shattered display glass, touch unresponsive screens, dead pixels, and black display panels with original factory fit.</p>
                                <ul style="list-style:none; padding:0; margin:12px 0; font-size:0.82rem; color:#475569; display:flex; flex-direction:column; gap:6px;">
                                    <li><i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:6px;"></i> Dust-free OCA Vacuum Lamination</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:6px;"></i> Original Color TrueTone Retained</li>
                                </ul>
                            </div>
                        </div>

                        <!-- 2. Motherboard & Chip Repair -->
                        <div class="service-card" style="box-shadow:0 6px 20px rgba(0,0,0,0.06);">
                            <div class="service-card-body">
                                <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
                                    <span style="background:rgba(245,158,11,0.1); color:#d97706; width:44px; height:44px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; font-size:1.3rem;">
                                        <i class="fa-solid fa-microchip"></i>
                                    </span>
                                    <div>
                                        <h3 style="margin:0; font-size:1.15rem;">Motherboard Chip-Level IC Repair</h3>
                                        <span style="font-size:0.75rem; color:#d97706; font-weight:800;">Micro-Soldering Workstation</span>
                                    </div>
                                </div>
                                <p style="font-size:0.88rem;">Advanced micro-soldering under digital stereo microscope. Power IC, Charging IC (U2/Tristar), Baseband Network IC & Audio IC replacement.</p>
                                <ul style="list-style:none; padding:0; margin:12px 0; font-size:0.82rem; color:#475569; display:flex; flex-direction:column; gap:6px;">
                                    <li><i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:6px;"></i> CPU & eMMC Memory Reballing</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:6px;"></i> Dead Phone Current Short Trace Removal</li>
                                </ul>
                            </div>
                        </div>

                        <!-- 3. Battery Replacement -->
                        <div class="service-card" style="box-shadow:0 6px 20px rgba(0,0,0,0.06);">
                            <div class="service-card-body">
                                <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
                                    <span style="background:rgba(16,185,129,0.1); color:#059669; width:44px; height:44px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; font-size:1.3rem;">
                                        <i class="fa-solid fa-battery-three-quarters"></i>
                                    </span>
                                    <div>
                                        <h3 style="margin:0; font-size:1.15rem;">Battery Replacement & Power Issues</h3>
                                        <span style="font-size:0.75rem; color:#059669; font-weight:800;">100% Health High-Capacity Cells</span>
                                    </div>
                                </div>
                                <p style="font-size:0.88rem;">Fix rapid battery drain, sudden power drop, bloated/swollen battery, and spontaneous reboot loops with premium grade replacement batteries.</p>
                                <ul style="list-style:none; padding:0; margin:12px 0; font-size:0.82rem; color:#475569; display:flex; flex-direction:column; gap:6px;">
                                    <li><i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:6px;"></i> iPhone 100% Battery Health Setup</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:6px;"></i> Safe Adhesive Strip Installation</li>
                                </ul>
                            </div>
                        </div>

                        <!-- 4. Charging & Audio Ports -->
                        <div class="service-card" style="box-shadow:0 6px 20px rgba(0,0,0,0.06);">
                            <div class="service-card-body">
                                <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
                                    <span style="background:rgba(59,130,246,0.1); color:#2563eb; width:44px; height:44px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; font-size:1.3rem;">
                                        <i class="fa-solid fa-plug"></i>
                                    </span>
                                    <div>
                                        <h3 style="margin:0; font-size:1.15rem;">Charging Port, Mic & Speaker Repair</h3>
                                        <span style="font-size:0.75rem; color:#2563eb; font-weight:800;">Type-C, Lightning & Micro-USB</span>
                                    </div>
                                </div>
                                <p style="font-size:0.88rem;">Replacement of loose or broken charging sockets, slow-charging faults, low ear-speaker sound, distorted ringtone buzzer, and caller mic noise.</p>
                                <ul style="list-style:none; padding:0; margin:12px 0; font-size:0.82rem; color:#475569; display:flex; flex-direction:column; gap:6px;">
                                    <li><i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:6px;"></i> Fast Charging Protocol Retained</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:6px;"></i> Crystal Clear Noise-Free Audio</li>
                                </ul>
                            </div>
                        </div>

                        <!-- 5. Water Damage -->
                        <div class="service-card" style="box-shadow:0 6px 20px rgba(0,0,0,0.06);">
                            <div class="service-card-body">
                                <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
                                    <span style="background:rgba(6,182,212,0.1); color:#0891b2; width:44px; height:44px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; font-size:1.3rem;">
                                        <i class="fa-solid fa-droplet"></i>
                                    </span>
                                    <div>
                                        <h3 style="margin:0; font-size:1.15rem;">Water & Liquid Damage Restoration</h3>
                                        <span style="font-size:0.75rem; color:#0891b2; font-weight:800;">Ultrasonic Motherboard Bath</span>
                                    </div>
                                </div>
                                <p style="font-size:0.88rem;">Immediate chemical wash to neutralize corrosion, board desoldering, trace micro-jumpering, and data recovery from liquid damaged phones.</p>
                                <ul style="list-style:none; padding:0; margin:12px 0; font-size:0.82rem; color:#475569; display:flex; flex-direction:column; gap:6px;">
                                    <li><i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:6px;"></i> Complete Corrosion Neutralization</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:6px;"></i> Urgent Emergency Phone Recovery</li>
                                </ul>
                            </div>
                        </div>

                        <!-- 6. Software & FRP -->
                        <div class="service-card" style="box-shadow:0 6px 20px rgba(0,0,0,0.06);">
                            <div class="service-card-body">
                                <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
                                    <span style="background:rgba(147,51,234,0.1); color:#7c3aed; width:44px; height:44px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; font-size:1.3rem;">
                                        <i class="fa-solid fa-unlock-keyhole"></i>
                                    </span>
                                    <div>
                                        <h3 style="margin:0; font-size:1.15rem;">Software Flashing, FRP & Unlocking</h3>
                                        <span style="font-size:0.75rem; color:#7c3aed; font-weight:800;">Official Stock Firmware</span>
                                    </div>
                                </div>
                                <p style="font-size:0.88rem;">Fix phones stuck on boot logo, corrupted firmware recovery, Google Account FRP lock bypass, forgotten PIN/Pattern unlock, and network baseband fix.</p>
                                <ul style="list-style:none; padding:0; margin:12px 0; font-size:0.82rem; color:#475569; display:flex; flex-direction:column; gap:6px;">
                                    <li><i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:6px;"></i> Android & iOS Official Firmware Upgrade</li>
                                    <li><i class="fa-solid fa-circle-check" style="color:#10b981; margin-right:6px;"></i> Secure & Confidential Process</li>
                                </ul>
                            </div>
                        </div>

                    </div>

                    <!-- DIRECT REPAIR CONSULTATION HELPER BOX -->
                    <div class="interactive-helper-box" style="background:linear-gradient(135deg, #fffdf5 0%, #fef3c7 100%); border:1.5px solid #fde68a; border-radius:16px; padding:24px; text-align:center;">
                        <span style="background:var(--smz-red); color:#fff; font-size:0.75rem; font-weight:800; padding:3px 12px; border-radius:20px; text-transform:uppercase;">
                            NEED A FAST ESTIMATE?
                        </span>
                        <h3 style="font-family:var(--font-heading); font-size:1.5rem; color:#78350f; margin:10px 0 6px 0;">
                            Get a Free Repair Diagnosis & Price Quote on WhatsApp
                        </h3>
                        <p style="font-size:0.9rem; color:#92400e; max-width:650px; margin:0 auto 18px auto;">
                            Tell us your smartphone model (e.g. Samsung A54, iPhone 13, Infinix Note 30) and the fault you are experiencing. Our certified master technician will provide an instant quote and repair turnaround time!
                        </p>
                        <a href="https://wa.me/923339688007?text=Hello%20Safdar%20Mobile%20Store!%20I%20need%20a%20quote%20for%20mobile%20repairing.%0AModel:%20%0AFault%20Description:%20" target="_blank" class="btn btn-whatsapp circle-style-btn" style="padding:12px 30px; font-weight:800; font-size:1rem;">
                            <i class="fa-brands fa-whatsapp"></i> Get Free Repair Quote on WhatsApp (03339688007)
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- TAB 7: CONTACT -->
        <section id="tab-contact" class="page-tab" style="display: none;">
            <div class="section-padding">
                <div class="container">
                    <div class="section-header">
                        <span class="sub-heading">GET IN TOUCH</span>
                        <h2 class="section-title">Contact Safdar Mobile Store</h2>
                        <p class="section-desc">Visit our store in Hangu, call our direct helpline, or send us a message via WhatsApp (03339688007).</p>
                    </div>

                    <div class="contact-grid">
                        <div class="interactive-helper-box">
                            <h3 style="font-family:var(--font-heading); font-size:1.3rem; margin-bottom:16px;">Store Location & Contact Details</h3>
                            <div style="display:flex; flex-direction:column; gap:16px; font-size:0.95rem;">
                                <div><i class="fa-solid fa-location-dot" style="color:var(--smz-red); margin-right:10px;"></i> <strong>Address:</strong> Opposite Patt Bazar Eidgah Road near Purdil Masjid Syedano Banda Road Main Bazar Hangu</div>
                                <div><i class="fa-solid fa-phone" style="color:var(--smz-red); margin-right:10px;"></i> <strong>WhatsApp Helpline:</strong> 03339688007</div>
                                <div><i class="fa-solid fa-clock" style="color:var(--smz-red); margin-right:10px;"></i> <strong>Store Hours:</strong> Mon – Sun: 8:00 AM – 9:00 PM</div>
                            </div>
                        </div>

                        <div class="interactive-helper-box">
                            <h3 style="font-family:var(--font-heading); font-size:1.3rem; margin-bottom:16px;">Direct Inquiry Form</h3>
                            <form onsubmit="event.preventDefault(); window.open('https://wa.me/923339688007?text=' + encodeURIComponent('General Inquiry from Website'), '_blank');">
                                <div style="display:flex; flex-direction:column; gap:12px;">
                                    <input type="text" class="form-select" placeholder="Your Name *" required>
                                    <input type="text" class="form-select" placeholder="Phone Number *" required>
                                    <textarea class="form-select" style="min-height:90px;" placeholder="Message or Product Inquiry..."></textarea>
                                    <button class="btn btn-whatsapp btn-block" type="submit"><i class="fa-brands fa-whatsapp"></i> Send Message on WhatsApp</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <!-- Site Footer -->
    <footer class="site-footer">
        <div class="container">
            <!-- Store Location & Interactive Google Map Card -->
            <div class="footer-map-box" style="margin-bottom:35px; padding:24px; background:rgba(255,255,255,0.03); border-radius:16px; border:1px solid rgba(255,255,255,0.08);">
                <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:28px;">
                    <!-- Left: Location Details -->
                    <div style="flex:1; min-width:280px;">
                        <span style="background:rgba(215,25,32,0.15); color:var(--smz-red); border:1px solid var(--smz-red); font-size:0.75rem; font-weight:800; padding:4px 10px; border-radius:12px; display:inline-block; margin-bottom:8px;">OFFICIAL STORE LOCATION</span>
                        <h3 style="font-family:var(--font-heading); font-size:1.35rem; font-weight:800; color:#fff; margin-bottom:8px;">
                            <i class="fa-solid fa-location-dot" style="color:var(--smz-red); margin-right:8px;"></i> Visit Our Physical Store
                        </h3>
                        <p style="font-size:0.92rem; color:var(--smz-gold); margin-bottom:10px; font-weight:700; line-height:1.5;">
                            📍 Opposite Patt Bazar Eidgah Road near Purdil Masjid, Syedano Banda Road, Main Bazar Hangu, KPK
                        </p>
                        <p style="font-size:0.85rem; color:rgba(255,255,255,0.75); margin-bottom:18px;">
                            🕒 <strong>Store Hours:</strong> 8:00 AM – 9:00 PM Daily &bull; 📞 <strong>Helpline:</strong> 03339688007
                        </p>
                        <a href="https://www.google.com/maps/place/33%C2%B031'55.9%22N+71%C2%B003'42.6%22E/@33.532186,71.0618388,17z" target="_blank" class="btn btn-primary circle-style-btn" style="padding:10px 22px; font-size:0.85rem; font-weight:700;">
                            <i class="fa-solid fa-diamond-turn-right"></i> Open Navigation in Google Maps
                        </a>
                    </div>

                    <!-- Right: Full-Width High-Res Interactive Map -->
                    <div style="flex:1; min-width:300px; max-width:500px; height:220px; border-radius:16px; overflow:hidden; border:2px solid var(--smz-gold); box-shadow:0 10px 25px rgba(0,0,0,0.5); position:relative; background:#0f172a;">
                        <iframe src="https://maps.google.com/maps?q=33.532186,71.0618388&t=m&z=17&ie=UTF8&iwloc=&output=embed" width="100%" height="100%" style="border:0; width:100%; height:100%; display:block;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                </div>
            </div>

            <!-- Footer 4 Columns -->
            <div class="footer-grid">
                <div>
                    <div class="brand-logo">
                        <img src="assets/images/logo.jpg" alt="Safdar Mobile Store Logo" style="height:60px; width:auto; border-radius:50%; box-shadow:0 0 12px rgba(244,196,48,0.4);">
                    </div>
                    <p class="footer-desc" style="color:rgba(255,255,255,0.7); font-size:0.88rem; margin:14px 0 20px 0;">
                        Your trusted mobile shop for smartphones, genuine accessories, CCTV security systems, financial load services, and digital citizen verification guides. Contact us directly on WhatsApp 03339688007.
                    </p>
                    <a href="https://wa.me/923339688007" target="_blank" class="btn btn-whatsapp" style="border-radius:30px; font-weight:700; background:#25D366; color:#fff; padding:10px 20px;">
                        <i class="fa-brands fa-whatsapp"></i> Chat: 03339688007
                    </a>
                </div>

                <div class="footer-col">
                    <h4>Quick Navigation</h4>
                    <ul class="footer-links">
                        <li><a href="#" data-tab-target="home"><i class="fa-solid fa-angle-right"></i> Home</a></li>
                        <li><a href="#" data-tab-target="mobiles"><i class="fa-solid fa-angle-right"></i> Brand Mobiles</a></li>
                        <li><a href="#" data-tab-target="accessories"><i class="fa-solid fa-angle-right"></i> Mobile Accessories</a></li>
                        <li><a href="#" data-tab-target="cctv"><i class="fa-solid fa-angle-right"></i> CCTV Cameras</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>Services & Kiosk</h4>
                    <ul class="footer-links">
                        <li><a href="#" data-tab-target="repair"><i class="fa-solid fa-angle-right"></i> Mobile Repair Lab</a></li>
                        <li><a href="#" data-tab-target="financial"><i class="fa-solid fa-angle-right"></i> Easypaisa & JazzCash</a></li>
                        <li><a href="#" data-tab-target="citizen"><i class="fa-solid fa-angle-right"></i> Nadra & Clearance</a></li>
                        <li><a href="#" data-tab-target="contact"><i class="fa-solid fa-angle-right"></i> Contact Us</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>Visit Our Store</h4>
                    <ul class="footer-links">
                        <li><span>📍 Opposite Patt Bazar Eidgah Road near Purdil Masjid Syedano Banda Road Main Bazar Hangu</span></li>
                        <li><a href="https://wa.me/923339688007" target="_blank"><i class="fa-brands fa-whatsapp"></i> WhatsApp: <strong>03339688007</strong></a></li>
                        <li><span>🕒 Mon – Sun: <strong>8:00 AM – 9:00 PM</strong></span></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="footer-bottom" id="secretAdminTrigger" onclick="handleSecretAdminClick(event)" style="padding: 16px 0; background: #080808; border-top: 1px solid rgba(255,255,255,0.08); user-select: none; cursor: pointer;">
            <div class="container footer-bottom-flex">
                <div style="font-size: 0.85rem; color: rgba(255,255,255,0.7); line-height: 1.4;">
                    &copy; <?php echo date('Y'); ?> <strong>Safdar Mobile Store</strong>. All Rights Reserved. Opposite Patt Bazar Eidgah Road near Purdil Masjid, Hangu. WhatsApp: <strong>03339688007</strong>
                </div>
                <div style="font-size: 0.85rem; font-weight: 700; color: #fff; white-space: nowrap; cursor: pointer;">
                    <i class="fa-solid fa-code" style="color:var(--smz-gold); margin-right: 4px;"></i> Created By <strong style="color:var(--smz-gold);">Munim Abbas</strong>
                </div>
            </div>
        </div>
    </footer>

    <!-- Floating WhatsApp Button -->
    <div class="floating-contact-bar">
        <a href="https://wa.me/923339688007" target="_blank" class="float-wa" id="floatingWhatsAppBtn" title="Chat on WhatsApp">
            <i class="fa-brands fa-whatsapp"></i>
        </a>
    </div>

    <!-- Inquiry Modal Dialog -->
    <div class="modal" id="inquiryModal" style="display: none;">
        <div class="modal-content">
            <button class="modal-close" onclick="closeInquiryModal()"><i class="fa-solid fa-xmark"></i></button>
            <div class="modal-header">
                <span class="modal-badge">PRODUCT ORDER & PAYMENT TERMS</span>
                <h3 id="modalProductName">Product Name</h3>
                <div style="font-weight:900; color:var(--smz-red); font-size:1.2rem; margin-top:4px;" id="modalProductPrice">PKR 0</div>
            </div>

            <!-- Online Discount Promo Notice (Dynamic) -->
            <div id="modalOnlineOfferNotice" style="display:none; background:#fffdf5; border:1.5px solid #fde68a; border-radius:8px; padding:10px 14px; margin-top:10px;">
                <div style="display:flex; align-items:center; gap:8px; color:#92400e; font-weight:800; font-size:0.85rem;">
                    <i class="fa-solid fa-tags" style="color:var(--smz-red);"></i>
                    <span id="modalOnlineOfferTitle">Special Online Purchase Offer Applied!</span>
                </div>
                <div id="modalOnlineOfferTagline" style="font-size:0.78rem; color:#78350f; margin-top:2px;"></div>
            </div>

            <!-- Mandatory Order Policy Box -->
            <div class="modal-notice" style="background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; border-radius:8px; padding:12px; font-size:0.82rem; margin-top:12px;">
                <div style="font-weight:800; font-size:0.88rem; margin-bottom:4px;">
                    <i class="fa-solid fa-shield-halved"></i> Store Order Policy
                </div>
                <ul style="margin:0; padding-left:18px; line-height:1.4;">
                    <li><strong>100% Advance Payment Required:</strong> Orders are dispatched only after receiving 100% advance payment.</li>
                    <li><strong>Delivery Charges on Customer:</strong> Delivery charges are extra and must be paid by the customer.</li>
                </ul>
            </div>

            <!-- Official Online Payment Accounts & QR Scanner Box -->
            <div class="payment-details-box" style="margin-top:14px; border:1.5px solid var(--smz-gold);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <div style="font-size:0.82rem; font-weight:800; text-transform:uppercase; color:var(--smz-gold-dark);">
                        <i class="fa-solid fa-qrcode" style="color:#00a859;"></i> Scan QR or Transfer Online (100% Advance)
                    </div>
                    <span style="background:#00a859; color:#fff; font-size:0.65rem; font-weight:800; padding:2px 6px; border-radius:4px;">
                        RAAST / ZINDIGI
                    </span>
                </div>

                <!-- Visual QR Code + Bank Details Flex Grid -->
                <div style="display:grid; grid-template-columns: 100px 1fr; gap:12px; align-items:center; background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:10px; margin-bottom:10px;">
                    <div style="text-align:center;">
                        <img src="assets/images/zindigi_payment_qr.jpg" alt="Payment QR" style="width:100%; height:auto; display:block; border-radius:6px; border:1px solid #e2e8f0; cursor:pointer;" onclick="window.open(this.src, '_blank')" title="Click to view full QR code">
                        <span style="font-size:0.65rem; color:#64748b; font-weight:700;">Tap to zoom</span>
                    </div>
                    <div style="font-size:0.76rem; color:#0f172a; line-height:1.4;">
                        <div><strong>Bank:</strong> ZINDIGI (JS Bank) / Raast</div>
                        <div><strong>Title:</strong> Safdar Mobile Store</div>
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-top:2px;">
                            <span><strong>Acct / Raast:</strong> 03339688007</span>
                            <button type="button" class="pos-btn pos-btn-sm" style="padding:1px 5px; font-size:0.65rem; background:#f1f5f9; color:#334155;" onclick="window.copyToClipboard('03339688007', this)">Copy</button>
                        </div>
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-top:2px;">
                            <span style="font-size:0.72rem; word-break:break-all;"><strong>IBAN:</strong> PK28JSBL9999903339688007</span>
                            <button type="button" class="pos-btn pos-btn-sm" style="padding:1px 5px; font-size:0.65rem; background:#00a859; color:#fff;" onclick="window.copyToClipboard('PK28JSBL9999903339688007', this)">Copy</button>
                        </div>
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-top:2px;">
                            <span><strong>Till ID:</strong> 946425125</span>
                            <button type="button" class="pos-btn pos-btn-sm" style="padding:1px 5px; font-size:0.65rem; background:#f1f5f9; color:#334155;" onclick="window.copyToClipboard('946425125', this)">Copy</button>
                        </div>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:6px;">
                    <div class="payment-method-row" style="margin:0; padding:6px 8px; font-size:0.75rem;">
                        <span><i class="fa-solid fa-mobile-retro" style="color:#00a859;"></i> <strong>Easypaisa:</strong> 03339688007</span>
                    </div>
                    <div class="payment-method-row" style="margin:0; padding:6px 8px; font-size:0.75rem;">
                        <span><i class="fa-solid fa-wallet" style="color:#ef4444;"></i> <strong>JazzCash:</strong> 03339688007</span>
                    </div>
                </div>
            </div>

            <div style="display:flex; flex-direction:column; gap:12px; margin-top:14px;">
                <div>
                    <label style="font-size:0.82rem; font-weight:700;">Your Full Name *</label>
                    <input type="text" id="inquiryCustomerName" class="form-select" placeholder="e.g. Ahmad Khan" required>
                </div>
                <div>
                    <label style="font-size:0.82rem; font-weight:700;">City / Delivery Address *</label>
                    <input type="text" id="inquiryCustomerCity" class="form-select" placeholder="e.g. Hangu / Peshawar / Islamabad" required>
                </div>

                <div>
                    <label style="font-size:0.82rem; font-weight:700;">Payment Method Used *</label>
                    <select id="inquiryPaymentMethod" class="form-select" style="font-size:0.85rem; font-weight:700;">
                        <option value="Zindigi / JS Bank QR Code">ZINDIGI (JS Bank) / Raast QR Code</option>
                        <option value="Direct IBAN Bank Transfer (JS Bank)">Direct IBAN Bank Transfer (PK28JSBL9999903339688007)</option>
                        <option value="Till ID (946425125)">Till ID (946425125)</option>
                        <option value="Easypaisa (03339688007)">Easypaisa (03339688007)</option>
                        <option value="JazzCash (03339688007)">JazzCash (03339688007)</option>
                        <option value="Other Bank Transfer">Other Bank Transfer</option>
                    </select>
                </div>
                
                <!-- Payment Screenshot Uploader Section -->
                <div style="background:#f8fafc; border:1px dashed #cbd5e1; border-radius:10px; padding:12px;">
                    <label style="font-size:0.82rem; font-weight:800; color:var(--smz-red); display:block; margin-bottom:4px;">
                        <i class="fa-solid fa-camera"></i> Upload Payment Proof Screenshot (Optional)
                    </label>
                    <input type="file" id="inquiryPaymentProof" class="form-select" accept="image/*" style="font-size:0.8rem;" onchange="handlePaymentScreenshotUpload(this)">
                    <div id="paymentUploadStatus" style="font-size:0.75rem; margin-top:6px; font-weight:700;"></div>
                    <div id="paymentScreenshotPreview" style="margin-top:8px; display:none; text-align:center;">
                        <img id="previewImage" src="" style="max-height:120px; border-radius:8px; border:1px solid #cbd5e1; box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                    </div>
                </div>

                <!-- Transaction ID Field -->
                <div>
                    <label style="font-size:0.82rem; font-weight:700;"><i class="fa-solid fa-receipt"></i> Bank / Wallet Transaction ID (TRX)</label>
                    <input type="text" id="inquiryTrxId" class="form-select" placeholder="e.g. TRX 94642512501">
                </div>

                <div>
                    <label style="font-size:0.82rem; font-weight:700;">Additional Notes or Questions</label>
                    <textarea id="inquiryCustomerNotes" class="form-select" style="min-height:55px;" placeholder="e.g. Please confirm courier tracking number."></textarea>
                </div>

                <div style="font-size:0.75rem; color:#065f46; font-weight:700; text-align:center; background:#ecfdf5; padding:8px; border-radius:6px; border:1px solid #a7f3d0;">
                    <i class="fa-solid fa-circle-check" style="color:#10b981;"></i> 100% Secure & Verified Payment Process
                </div>

                <button class="btn btn-whatsapp btn-block" onclick="sendWhatsAppInquiry()">
                    <i class="fa-brands fa-whatsapp"></i> Confirm & Send Order on WhatsApp
                </button>
            </div>
        </div>
    </div>

    <!-- PRODUCT DETAILS & FULL SPECIFICATIONS MODAL -->
    <div class="modal" id="productDetailsModal" style="display: none;" onclick="if(event.target === this) window.closeProductDetailsModal()">
        <div class="modal-content" style="max-width: 680px; width: 95%; max-height: 92vh; overflow-y: auto;">
            <!-- Top Header with Prominent Back Button -->
            <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--smz-border); padding-bottom: 10px; margin-bottom: 14px;">
                <button type="button" class="btn-modal-back" onclick="window.closeProductDetailsModal()" style="background: #f1f5f9; border: 1px solid #cbd5e1; font-size: 0.82rem; font-weight: 800; color: #1e293b; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; padding: 6px 12px; border-radius: 6px; transition: all 0.2s;">
                    <i class="fa-solid fa-arrow-left" style="color: var(--smz-red);"></i> Back to Products
                </button>
                <button type="button" class="modal-close" onclick="window.closeProductDetailsModal()" style="position: static; font-size: 1.4rem;">&times;</button>
            </div>
            
            <div class="modal-body" style="padding: 4px 4px 10px 4px;">
                <div class="product-details-grid" style="display: grid; grid-template-columns: 240px 1fr; gap: 20px;">
                    <!-- Left: Product Image & Quick Codes -->
                    <div style="text-align: center;">
                        <div style="background: #f8fafc; border-radius: 12px; padding: 12px; border: 1px solid var(--smz-border); position: relative;">
                            <span id="detailBadge" class="product-tag" style="top: 8px; left: 8px; position: absolute; font-size: 0.7rem;">NEW</span>
                            <img id="detailImage" src="" alt="Product" style="width: 100%; max-height: 220px; object-fit: contain; border-radius: 8px;" onerror="this.src='https://images.unsplash.com/photo-1598327105666-5b89351aff97?w=600&auto=format&fit=crop&q=80'">
                        </div>
                        <div style="margin-top: 10px; font-size: 0.74rem; color: var(--text-muted); display: flex; justify-content: space-around;">
                            <span id="detailSku">SKU: N/A</span>
                            <span id="detailBarcode">Barcode: N/A</span>
                        </div>
                    </div>

                    <!-- Right: Information, Pricing & Full Specs -->
                    <div>
                        <div style="display: flex; gap: 6px; align-items: center; margin-bottom: 6px; flex-wrap: wrap;">
                            <span id="detailCategory" class="status-badge" style="background: #ecfdf5; color: #065f46; font-size: 0.72rem; font-weight: 800; padding: 2px 8px; border-radius: 4px;">MOBILES</span>
                            <span id="detailBrand" class="status-badge" style="background: #f1f5f9; color: #334155; font-size: 0.72rem; font-weight: 800; padding: 2px 8px; border-radius: 4px;">Brand</span>
                            <span id="detailStock" class="status-badge" style="background: #dcfce7; color: #15803d; font-size: 0.72rem; font-weight: 800; padding: 2px 8px; border-radius: 4px;">IN STOCK</span>
                        </div>

                        <h3 id="detailTitle" style="font-size: 1.25rem; font-weight: 900; color: var(--text-main); margin-bottom: 8px; line-height: 1.3;">Product Details</h3>

                        <!-- Star Rating Preview -->
                        <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 12px;">
                            <div class="rating-stars" style="color: #f59e0b; font-size: 0.85rem;">
                                <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                            </div>
                            <span style="font-weight: 800; font-size: 0.82rem; color: var(--text-main);">5.0</span>
                            <button type="button" id="detailReviewsBtn" class="btn-reviews-toggle" style="background: none; border: none; font-size: 0.78rem; color: #2563eb; cursor: pointer; text-decoration: underline;">View Reviews</button>
                        </div>

                        <!-- Price Box -->
                        <div id="detailPriceBox" style="background: var(--box-bg-light); border: 1.5px solid var(--smz-border); border-radius: 10px; padding: 10px 14px; margin-bottom: 14px;">
                            <div style="font-size: 0.72rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Official Store Price</div>
                            <div id="detailPriceWrap" style="display: flex; align-items: baseline; gap: 10px; margin-top: 2px; flex-wrap: wrap;">
                                <span id="detailSellingPrice" style="font-size: 1.35rem; font-weight: 900; color: var(--smz-red);">PKR 0</span>
                                <del id="detailRegularPrice" style="font-size: 0.95rem; color: #94a3b8; display: none;">PKR 0</del>
                                <span id="detailSavingChip" class="online-saving-chip" style="display: none;">SAVE PKR 0</span>
                            </div>
                            <div id="detailOfferTagline" style="display: none; font-size: 0.78rem; color: #b45309; font-weight: 700; margin-top: 4px;">
                                <i class="fa-solid fa-gift"></i> <span id="detailOfferText"></span>
                            </div>
                        </div>

                        <!-- Full Specifications Section -->
                        <div style="margin-bottom: 14px;">
                            <h4 style="font-size: 0.88rem; font-weight: 800; color: var(--text-main); margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                                <i class="fa-solid fa-microchip" style="color: var(--smz-red);"></i> Specifications &amp; Features
                            </h4>
                            <ul id="detailSpecsList" style="margin: 0; padding: 0; list-style: none;">
                                <!-- Populated dynamically -->
                            </ul>
                        </div>

                        <!-- Store Assurance Banner -->
                        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 8px 10px; font-size: 0.75rem; color: #166534; margin-bottom: 16px; display: flex; flex-direction: column; gap: 4px;">
                            <div><i class="fa-solid fa-shield-check" style="color: #16a34a;"></i> <strong>100% Genuine:</strong> Official checking warranty / brand guarantee.</div>
                            <div><i class="fa-solid fa-truck-fast" style="color: #16a34a;"></i> <strong>Safe Delivery:</strong> 100% advance payment required for verified parcel dispatch.</div>
                        </div>

                        <!-- Action Buttons including Back Button -->
                        <div style="display: grid; grid-template-columns: auto 1fr 1.2fr; gap: 8px;">
                            <button type="button" class="btn btn-secondary" onclick="window.closeProductDetailsModal()" style="padding: 10px 14px; font-size: 0.85rem; font-weight: 800; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; gap: 6px;" title="Back to Products">
                                <i class="fa-solid fa-arrow-left"></i> Back
                            </button>
                            <button type="button" id="detailAddToCartBtn" class="btn btn-add-cart" style="padding: 10px 12px; font-size: 0.88rem; font-weight: 800; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                                <i class="fa-solid fa-cart-plus"></i> Add to Cart
                            </button>
                            <button type="button" id="detailBuyNowBtn" class="btn btn-primary" style="padding: 10px 12px; font-size: 0.88rem; font-weight: 800; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                                <i class="fa-brands fa-whatsapp"></i> Buy Now
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Reviews & Offers Modal Dialog -->
    <div class="modal" id="reviewsModal" style="display: none;">
        <div class="modal-content" style="max-width: 580px; max-height: 90vh; overflow-y: auto;">
            <button class="modal-close" onclick="closeReviewsModal()"><i class="fa-solid fa-xmark"></i></button>
            <div class="modal-header" style="border-bottom:1px solid var(--smz-border); padding-bottom:12px; margin-bottom:14px;">
                <span class="modal-badge"><i class="fa-solid fa-comments"></i> VERIFIED CUSTOMER REVIEWS</span>
                <h3 id="reviewsModalProductName" style="margin-top:4px;">Product Reviews</h3>
                <div style="display:flex; align-items:center; gap:8px; margin-top:6px;">
                    <div class="rating-stars" id="reviewsModalStarAverage" style="font-size:1.1rem;">
                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                    </div>
                    <strong style="font-size:0.95rem;" id="reviewsModalAvgScore">5.0 / 5.0</strong>
                    <span style="font-size:0.8rem; color:var(--text-muted);" id="reviewsModalCount">(0 Reviews)</span>
                </div>
            </div>

            <!-- Product Offer Alert in Reviews Modal if active -->
            <div id="reviewsModalOfferBox" style="display:none; background:#fffdf5; border:1px solid #fde68a; border-radius:8px; padding:10px 12px; margin-bottom:14px; font-size:0.82rem; color:#92400e;">
                <i class="fa-solid fa-tags" style="color:var(--smz-red); margin-right:4px;"></i>
                <strong id="reviewsModalOfferBadge">10% OFF ONLINE:</strong> <span id="reviewsModalOfferTagline"></span>
            </div>

            <!-- Existing Reviews List -->
            <div style="margin-bottom:18px;">
                <h4 style="font-size:0.92rem; font-weight:800; margin-bottom:10px; color:var(--text-main); display:flex; align-items:center; gap:6px;">
                    <i class="fa-solid fa-comment-dots" style="color:var(--smz-gold-dark);"></i> Customer Feedback
                </h4>
                <div id="reviewsModalList" class="reviews-list-box">
                    <!-- Populated dynamically via JS -->
                </div>
            </div>

            <!-- Write a Review Form -->
            <div style="background:var(--box-bg-light); border:1.5px solid var(--smz-border); border-radius:12px; padding:16px;">
                <h4 style="font-size:0.95rem; font-weight:800; margin-bottom:6px; color:var(--text-main); display:flex; align-items:center; gap:6px;">
                    <i class="fa-solid fa-pen-to-square" style="color:var(--smz-red);"></i> Write a Review for this Product
                </h4>
                <p style="font-size:0.76rem; color:var(--text-secondary); margin-bottom:12px;">Share your experience with Safdar Mobile Store to help other customers.</p>

                <form id="customerReviewForm" onsubmit="handleCustomerReviewSubmit(event)">
                    <input type="hidden" id="reviewProductId" value="">

                    <!-- Interactive Star Rating Selection -->
                    <div style="margin-bottom:12px;">
                        <label style="font-size:0.78rem; font-weight:800; color:var(--text-main); display:block;">Your Rating *</label>
                        <div class="interactive-stars-widget" id="reviewStarWidget">
                            <i class="fa-solid fa-star star-interactive active" data-rating="1" onclick="setReviewRating(1)"></i>
                            <i class="fa-solid fa-star star-interactive active" data-rating="2" onclick="setReviewRating(2)"></i>
                            <i class="fa-solid fa-star star-interactive active" data-rating="3" onclick="setReviewRating(3)"></i>
                            <i class="fa-solid fa-star star-interactive active" data-rating="4" onclick="setReviewRating(4)"></i>
                            <i class="fa-solid fa-star star-interactive active" data-rating="5" onclick="setReviewRating(5)"></i>
                        </div>
                        <input type="hidden" id="reviewRatingInput" value="5">
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:10px;">
                        <div>
                            <label style="font-size:0.78rem; font-weight:700;">Your Name *</label>
                            <input type="text" id="reviewCustomerName" class="form-select" placeholder="e.g. Asad Ullah" required style="font-size:0.85rem; padding:8px 10px;">
                        </div>
                        <div>
                            <label style="font-size:0.78rem; font-weight:700;">City / Area</label>
                            <input type="text" id="reviewCustomerCity" class="form-select" placeholder="e.g. Hangu / Kohat" style="font-size:0.85rem; padding:8px 10px;">
                        </div>
                    </div>

                    <div style="margin-bottom:12px;">
                        <label style="font-size:0.78rem; font-weight:700;">Your Review / Experience *</label>
                        <textarea id="reviewComment" class="form-select" rows="3" placeholder="Tell us about the product quality, performance, or customer service..." required style="font-size:0.85rem; padding:8px 10px; min-height:65px;"></textarea>
                    </div>

                    <div id="reviewSubmitAlert" style="display:none; font-size:0.8rem; padding:8px 12px; border-radius:6px; margin-bottom:10px; font-weight:700;"></div>

                    <button type="submit" id="btnSubmitReview" class="btn btn-primary btn-block" style="padding:10px; font-size:0.9rem;">
                        <i class="fa-solid fa-paper-plane"></i> Submit Customer Review
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- SHOPPING CART SLIDE-OVER DRAWER -->
    <div id="cartOverlay" class="cart-overlay" onclick="window.closeCartDrawer()"></div>
    <div id="cartDrawer" class="cart-drawer">
        <div class="cart-header">
            <div class="cart-header-title">
                <i class="fa-solid fa-bag-shopping" style="color:var(--smz-red); font-size:1.3rem;"></i>
                <h3>Shopping Cart (<span id="cartDrawerCount">0</span>)</h3>
            </div>
            <button type="button" class="cart-close-btn" onclick="window.closeCartDrawer()" aria-label="Close Cart">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="cart-body" id="cartItemsContainer">
            <!-- Dynamic Cart Items Rendered Here -->
            <div class="cart-empty-state" id="cartEmptyState">
                <div class="cart-empty-icon"><i class="fa-solid fa-cart-arrow-down"></i></div>
                <h4>Your Cart is Empty</h4>
                <p>Browse our smartphones, genuine accessories, and CCTV security devices.</p>
                <button type="button" class="btn btn-primary" onclick="window.closeCartDrawer(); if(window.switchTab) window.switchTab('mobiles');">
                    <i class="fa-solid fa-bag-shopping"></i> Browse Products
                </button>
            </div>
        </div>

        <div class="cart-footer" id="cartFooter" style="display:none;">
            <div class="cart-pricing-summary">
                <div class="cart-summary-row">
                    <span>Total Selected Items:</span>
                    <strong id="cartSummaryItems">0</strong>
                </div>
                <div class="cart-summary-row">
                    <span>Shipping / Courier:</span>
                    <span class="badge-shipping">Standard TCS/Leopards Rates (Pay on Delivery)</span>
                </div>
                <div class="cart-summary-row cart-total-row">
                    <span>Total Payment (PKR):</span>
                    <strong id="cartGrandTotal" class="cart-total-amount">PKR 0</strong>
                </div>
                <div class="cart-advance-notice">
                    <i class="fa-solid fa-circle-info"></i> 100% Advance Payment required for single combined order dispatch.
                </div>
            </div>
            <div class="cart-action-buttons">
                <button type="button" class="btn btn-primary btn-block btn-lg" onclick="window.openMultiCheckout()" id="btnProceedCheckout">
                    <i class="fa-solid fa-shield-check"></i> Proceed to Single Payment Checkout
                </button>
                <button type="button" class="btn btn-secondary btn-block" onclick="window.clearEntireCart()" style="margin-top:8px; font-size:0.85rem; padding:8px;">
                    <i class="fa-regular fa-trash-can"></i> Clear Cart
                </button>
            </div>
        </div>
    </div>

    <!-- UNIFIED MULTI-ITEM CHECKOUT MODAL WITH ADVANCE PAYMENT VERIFICATION -->
    <div id="multiCheckoutModal" class="modal" style="display: none;" onclick="if(event.target === this) window.closeMultiCheckout()">
        <div class="modal-content" style="max-width: 650px; width: 95%; max-height: 92vh; overflow-y: auto;">
            <button type="button" class="modal-close" onclick="window.closeMultiCheckout()">&times;</button>
            
            <div class="modal-header" style="border-bottom:1px solid var(--smz-border); padding-bottom:12px; margin-bottom:14px;">
                <span class="modal-badge"><i class="fa-solid fa-shield-check"></i> SECURE ONLINE CHECKOUT</span>
                <h3 style="margin-top:4px;">100% Advance Payment Order Booking</h3>
                <p style="font-size:0.83rem; color:var(--text-muted); margin-top:2px;">
                    Enter your delivery details and advance payment reference (TRX ID) to confirm and dispatch your parcel.
                </p>
            </div>

            <div class="modal-body" style="max-height: calc(88vh - 120px); overflow-y: auto; padding-right: 6px;">
                <!-- Order Items Summary Table -->
                <div class="checkout-items-summary">
                    <h4 style="font-size:0.92rem; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;">
                        <span><i class="fa-solid fa-list-check" style="color:var(--smz-red);"></i> Selected Products (<span id="checkoutItemCountBadge">0 Items</span>)</span>
                        <span class="badge-shipping">Pay Courier on Arrival</span>
                    </h4>
                    <div id="checkoutItemsList" class="checkout-items-list">
                        <!-- Dynamic mini items list -->
                    </div>
                    <div class="checkout-total-banner">
                        <span>Total Advance Payment:</span>
                        <strong id="checkoutGrandTotal" style="color:var(--smz-red); font-size:1.35rem; font-weight:900;">PKR 0</strong>
                    </div>
                </div>

                <!-- Customer Shipping & Mandatory Advance Payment Form -->
                <form id="multiCheckoutForm" onsubmit="window.handleMultiCheckoutSubmit(event)">
                    <h4 style="font-size:0.93rem; margin:16px 0 8px 0; color:var(--text-main); font-weight:800;">
                        <i class="fa-solid fa-truck-fast" style="color:var(--smz-red);"></i> 1. Customer &amp; Delivery Details
                    </h4>
                    <div class="form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div class="form-group" style="margin-bottom:10px;">
                            <label class="form-label" style="font-size:0.8rem; font-weight:700;">Full Name *</label>
                            <input type="text" id="multiCustomerName" class="form-select" placeholder="e.g. Muhammad Ali" required style="font-size:0.88rem; padding:8px 12px;">
                        </div>
                        <div class="form-group" style="margin-bottom:10px;">
                            <label class="form-label" style="font-size:0.8rem; font-weight:700;">WhatsApp / Mobile No *</label>
                            <input type="tel" id="multiCustomerPhone" class="form-select" placeholder="0333XXXXXXX" required style="font-size:0.88rem; padding:8px 12px;">
                        </div>
                    </div>

                    <div class="form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div class="form-group" style="margin-bottom:10px;">
                            <label class="form-label" style="font-size:0.8rem; font-weight:700;">City / District *</label>
                            <input type="text" id="multiCustomerCity" class="form-select" placeholder="e.g. Hangu, Kohat, Peshawar" required style="font-size:0.88rem; padding:8px 12px;">
                        </div>
                        <div class="form-group" style="margin-bottom:10px;">
                            <label class="form-label" style="font-size:0.8rem; font-weight:700;">Nearest Landmark</label>
                            <input type="text" id="multiCustomerLandmark" class="form-select" placeholder="e.g. Main Bazar, Near Hospital" style="font-size:0.88rem; padding:8px 12px;">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:12px;">
                        <label class="form-label" style="font-size:0.8rem; font-weight:700;">Full Delivery Address *</label>
                        <textarea id="multiCustomerAddress" class="form-select" rows="2" placeholder="House / Shop #, Street, Mohallah / Village..." required style="font-size:0.88rem; padding:8px 12px; min-height:50px;"></textarea>
                    </div>

                    <!-- Advance Payment Verification Box -->
                    <div class="advance-payment-section">
                        <div class="advance-payment-header">
                            <i class="fa-solid fa-building-columns" style="color:#dc2626; font-size:1.15rem;"></i>
                            <h4>2. Transfer 100% Advance Payment</h4>
                            <span class="advance-tag-badge">Mandatory</span>
                        </div>
                        
                        <div class="advance-payment-desc">
                            Without advance payment, orders cannot be dispatched. Please transfer total amount <strong id="checkoutPayAmount" style="color:#dc2626; font-size:0.95rem;">PKR 0</strong> to any official account below:
                        </div>

                        <!-- Official Store Accounts with 1-Click Copy -->
                        <div class="account-cards-grid">
                            <!-- Easypaisa Card -->
                            <div class="pay-account-card">
                                <div class="pay-account-title">
                                    <span><i class="fa-solid fa-mobile-screen-button" style="color:#10b981;"></i> Easypaisa</span>
                                    <span style="color:#10b981; font-weight:800; font-size:0.75rem;">Verified</span>
                                </div>
                                <div class="pay-account-number-box">
                                    <span class="pay-account-number">03339688007</span>
                                    <button type="button" class="btn-copy-acc" onclick="window.copyToClipboard('03339688007', this)">
                                        <i class="fa-regular fa-copy"></i> Copy
                                    </button>
                                </div>
                                <div class="pay-account-holder">Title: Safdar Mobile Store</div>
                            </div>

                            <!-- JazzCash Card -->
                            <div class="pay-account-card">
                                <div class="pay-account-title">
                                    <span><i class="fa-solid fa-wallet" style="color:#ef4444;"></i> JazzCash</span>
                                    <span style="color:#ef4444; font-weight:800; font-size:0.75rem;">Verified</span>
                                </div>
                                <div class="pay-account-number-box">
                                    <span class="pay-account-number">03339688007</span>
                                    <button type="button" class="btn-copy-acc" onclick="window.copyToClipboard('03339688007', this)">
                                        <i class="fa-regular fa-copy"></i> Copy
                                    </button>
                                </div>
                                <div class="pay-account-holder">Title: Safdar Mobile Store</div>
                            </div>

                            <!-- Raast & JS Bank / Zindigi Card -->
                            <div class="pay-account-card" style="grid-column: 1 / -1;">
                                <div class="pay-account-title">
                                    <span><i class="fa-solid fa-building-columns" style="color:#2563eb;"></i> JS Bank / Zindigi &amp; Raast ID</span>
                                    <span style="color:#2563eb; font-weight:800; font-size:0.75rem;">Instant 0% Fee</span>
                                </div>
                                <div class="pay-account-number-box">
                                    <div>
                                        <div style="font-size:0.75rem; color:var(--text-muted);">Raast ID (Instant Mobile):</div>
                                        <span class="pay-account-number">03339688007</span>
                                    </div>
                                    <button type="button" class="btn-copy-acc" onclick="window.copyToClipboard('03339688007', this)">
                                        <i class="fa-regular fa-copy"></i> Copy Raast
                                    </button>
                                </div>
                                <div class="pay-account-number-box" style="margin-top:4px;">
                                    <div>
                                        <div style="font-size:0.75rem; color:var(--text-muted);">JS Bank IBAN:</div>
                                        <span class="pay-account-number" style="font-size:0.82rem;">PK28JSBL9999903339688007</span>
                                    </div>
                                    <button type="button" class="btn-copy-acc" onclick="window.copyToClipboard('PK28JSBL9999903339688007', this)">
                                        <i class="fa-regular fa-copy"></i> Copy IBAN
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Enter Payment Proof Details -->
                        <h4 style="font-size:0.9rem; margin:14px 0 8px 0; color:var(--text-main); font-weight:800;">
                            <i class="fa-solid fa-receipt" style="color:var(--smz-red);"></i> 3. Enter Your Payment Confirmation
                        </h4>

                        <div class="form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
                            <div class="form-group">
                                <label class="form-label" style="font-size:0.8rem; font-weight:700;">Payment Method Used *</label>
                                <select id="multiPaymentMethod" class="form-select" required style="font-size:0.88rem; padding:8px 12px; font-weight:700;">
                                    <option value="">-- Select Payment Method --</option>
                                    <option value="Easypaisa">Easypaisa (03339688007)</option>
                                    <option value="JazzCash">JazzCash (03339688007)</option>
                                    <option value="Zindigi / JS Bank Raast">Zindigi / JS Bank Raast (03339688007)</option>
                                    <option value="JS Bank Direct IBAN">JS Bank Direct IBAN Transfer</option>
                                    <option value="Other Bank Transfer">Other Bank Transfer</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="font-size:0.8rem; font-weight:700;">Sender Account / Mobile *</label>
                                <input type="text" id="multiSenderAccount" class="form-select" placeholder="e.g. Ali Khan - 03001234567" required style="font-size:0.88rem; padding:8px 12px;">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom:10px;">
                            <label class="form-label" style="font-size:0.8rem; font-weight:700; color:#dc2626;">
                                <i class="fa-solid fa-barcode"></i> Transaction ID / Reference (TRX ID) *
                            </label>
                            <input type="text" id="multiTrxId" class="form-select" placeholder="Enter SMS or App TRX ID (e.g. TRX 9876543210)" required style="font-size:0.9rem; padding:9px 12px; font-weight:800; border:1.5px solid #dc2626;">
                            <small style="color:var(--text-muted); font-size:0.75rem;">Received in your SMS or Banking App after payment.</small>
                        </div>

                        <!-- Upload Screenshot Section -->
                        <div style="margin-bottom:10px;">
                            <label class="form-label" style="font-size:0.8rem; font-weight:700;">
                                <i class="fa-solid fa-camera"></i> Upload Payment Screenshot / Receipt (Recommended)
                            </label>
                            <div class="upload-dropzone">
                                <i class="fa-solid fa-cloud-arrow-up" style="font-size:1.5rem; color:#dc2626; margin-bottom:4px;"></i>
                                <div style="font-size:0.82rem; font-weight:700; color:var(--text-main);">Click or Drag Payment Receipt Screenshot</div>
                                <div style="font-size:0.72rem; color:var(--text-muted);">JPG, PNG, WEBP or PDF receipt</div>
                                <input type="file" id="multiPaymentProofFile" accept="image/*,.pdf" onchange="window.handleMultiPaymentScreenshotUpload(this)">
                            </div>
                            <div id="multiPaymentUploadStatus" style="font-size:0.78rem; margin-top:6px; font-weight:700;"></div>
                            <div id="multiPaymentScreenshotPreview" style="margin-top:8px; display:none; text-align:center;">
                                <img id="multiPreviewImage" src="" style="max-height:130px; border-radius:8px; border:1px solid #cbd5e1; box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                            </div>
                        </div>

                        <!-- Advance Payment Mandatory Confirmation Checkbox -->
                        <div class="advance-confirm-box">
                            <input type="checkbox" id="chkConfirmAdvancePayment" required>
                            <label for="chkConfirmAdvancePayment">
                                I confirm that I have transferred 100% advance payment (<span id="chkPayAmountText" style="color:#dc2626; font-weight:900;">PKR 0</span>) and provided my valid Transaction ID (TRX). I understand fake TRX numbers result in automatic order cancellation.
                            </label>
                        </div>
                    </div>

                    <div id="multiOrderAlert" style="display:none; font-size:0.85rem; padding:10px 14px; border-radius:8px; margin-top:12px; font-weight:700;"></div>

                    <div class="modal-footer-actions" style="margin-top:14px; display:flex; flex-direction:column; gap:8px;">
                        <button type="submit" id="btnSubmitMultiOrder" class="btn btn-whatsapp btn-block btn-lg" style="padding:13px; font-size:0.98rem; font-weight:900;">
                            <i class="fa-brands fa-whatsapp"></i> Verify Advance Payment &amp; Submit Order
                        </button>
                        <button type="button" class="btn btn-secondary btn-block" onclick="window.closeMultiCheckout()">
                            Continue Shopping / Edit Cart
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Silent 5-Click Secret Admin Gateway Handler -->
    <script>
        let secretClickCount = 0;
        let secretLastClickTime = 0;

        function handleSecretAdminClick(e) {
            if (e && e.target && e.target.tagName === 'A') return;
            
            const now = Date.now();
            if (now - secretLastClickTime > 3000) {
                secretClickCount = 1;
            } else {
                secretClickCount++;
            }
            secretLastClickTime = now;

            if (secretClickCount >= 5) {
                secretClickCount = 0;
                window.location.href = 'secure-portal.php?path=secure-management-portal';
            }
        }
    </script>

    <!-- Instant Client-Side Hydration for Zero-Latency Rendering -->
    <script>
        window.INITIAL_PRODUCTS = <?php echo json_encode($allProducts, JSON_UNESCAPED_UNICODE); ?>;
        window.INITIAL_CATEGORIES = <?php echo json_encode($siteCategories, JSON_UNESCAPED_UNICODE); ?>;
    </script>

    <!-- Application JavaScript with Cache Buster -->
    <script src="assets/js/app.js?v=<?php echo filemtime(__DIR__ . '/assets/js/app.js'); ?>"></script>
</body>
</html>
