<?php
declare(strict_types=1);

/**
 * Homepage Template
 * iboltnetworks E-Commerce Platform
 */

// Load core configurations and helpers before sending headers or output
require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/auth.php';
require_once __DIR__ . '/app/helpers/csrf.php';
require_once __DIR__ . '/app/helpers/sanitize.php';
require_once __DIR__ . '/app/helpers/currency.php';
require_once __DIR__ . '/app/helpers/homepage.php';

// Safe pre-rendering fetch of active homepage section products
$serversHomepage = [];
$networkingHomepage = [];
$componentsHomepage = [];
$ourProductsHomepage = [];
$featuredHomepage = [];

try {
    $serversHomepage = get_homepage_section_products('servers', 12);
    $networkingHomepage = get_homepage_section_products('networking', 8);
    $componentsHomepage = get_homepage_section_products('components', 8);
    $ourProductsHomepage = get_homepage_section_products('our-products', 8);
    $featuredHomepage = get_homepage_section_products('featured', 6);
} catch (Throwable $e) {
    error_log("[iboltnetworks] Homepage section initialization failed: " . $e->getMessage());
}

$pageTitle = 'iboltnetworks | Servers & Computer Components';
$pageDescription = 'Explore servers, computer components, memory, storage and networking products at iboltnetworks.';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navigation.php';
?>

<main>
    <section class="hero" id="servers">
      <div class="hero-inner">
        <div class="hero-copy">
          <div class="hero-image-wrap">
            <!-- Replace this with your own transparent server product image -->
            <img src="images/server-hero.png" alt="Enterprise rack and tower servers" class="hero-product-image">
          </div>
          <p class="hero-eyebrow">Dell &amp; HPE Rack and Tower Servers</p>
          <h1>Advanced Technology for<br class="desktop-break"> Heavy Workloads</h1>
        </div>

        <div class="hero-products" aria-label="Featured server models">
          <?php if (!empty($serversHomepage)): ?>
            <?php foreach (array_slice($serversHomepage, 0, 4) as $hs): ?>
              <a href="product.php?slug=<?= e($hs['slug']) ?>" class="hero-product-link"><?= e($hs['title']) ?></a>
            <?php endforeach; ?>
          <?php else: ?>
            <a href="product.php?slug=dell-r760-rack-server" class="hero-product-link">Dell PowerEdge R760 Server</a>
            <a href="product.php?slug=dell-r750-rack-server" class="hero-product-link">Dell PowerEdge R750 Server</a>
            <a href="product.php?slug=hpe-dl360-g11-rack-server" class="hero-product-link">HPE ProLiant DL360 Gen11 Server</a>
            <a href="product.php?slug=hpe-dl385-g10-plus-v2-server" class="hero-product-link">HPE ProLiant DL385 Gen11 Server</a>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </main>

  <!-- =========================
     IBOLT NETWORKS: SERVER OFFER BANNER
========================= -->
<section class="ibolt-offer">
  <div class="ibolt-offer-inner">

    <div class="ibolt-offer-content">
      <p class="ibolt-offer-tag">Limited-Time Offer!</p>
      <p class="ibolt-offer-subtitle">Amazing Deals On</p>
      <h1>Dedicated Server<br />Hosting</h1>
    </div>

    <div class="ibolt-trial-card">
      <span class="ibolt-trial-label">SPECIAL OFFERS</span>
      <strong>30 DAYS</strong>
      <span class="ibolt-trial-free">FREE TRIAL</span>
      <div class="ibolt-speed">
        <span class="ibolt-wifi">⌁</span>
        <strong>10</strong>
        <span>Gbps</span>
      </div>
    </div>

    <div class="ibolt-server-art" aria-label="Server racks illustration">
      <div class="ibolt-server-rack rack-one">
        <span></span><span></span><span></span><span></span>
      </div>
      <div class="ibolt-server-rack rack-two">
        <span></span><span></span><span></span><span></span>
      </div>
      <div class="ibolt-server-rack rack-three">
        <span></span><span></span><span></span><span></span>
      </div>
      <div class="ibolt-server-rack rack-four">
        <span></span><span></span><span></span><span></span>
      </div>
    </div>

    <div class="ibolt-offer-actions">
      <a class="ibolt-action ibolt-config" href="#featured-server">
        <span aria-hidden="true">⚙</span> Config Now
      </a>
      <a class="ibolt-action ibolt-chat" href="#contact">
        <span aria-hidden="true">☏</span> Chat Now
      </a>
      <a class="ibolt-action ibolt-whatsapp" href="https://wa.me/919911003865" target="_blank" rel="noopener noreferrer">
        <span aria-hidden="true">◉</span> WhatsApp
      </a>
    </div>

    <p class="ibolt-assistance">
      Need Assistance? Our Dedicated Technical Sales Support Team is Here for You!
      Call Us at <a href="tel:+919911003865">+91 99110 03865</a>.
    </p>

  </div>
</section>

<!-- =========================
     FEATURED SERVER PROMOTION
========================= -->
<section class="ibolt-featured-wrap" id="featured-server">
  <div class="ibolt-featured">

    <div class="ibolt-featured-copy">
      <p class="ibolt-featured-eyebrow">POWERFUL. RELIABLE. READY FOR BUSINESS.</p>
      <h2>HPE ProLiant DL385 Gen10<br />Plus v2</h2>

      <div class="ibolt-featured-rule"></div>

      <p class="ibolt-discount">SAVE UP TO 07% OFF</p>

      <p class="ibolt-specs">
        Server Configuration: 24-Core AMD EPYC 7F72 |
        64GB DDR4 RAM | 960GB SATA SSD (2 × 480GB)
      </p>

      <div class="ibolt-price-panel">
        <span class="ibolt-price-label">Starting at</span>
        <strong>INR 569,999</strong>
        <span class="ibolt-price-note">Contact us for the latest price and configuration.</span>
      </div>

      <div class="ibolt-featured-actions">
        <a href="#contact" class="ibolt-buy-button">Enquire Now <span aria-hidden="true">→</span></a>
        <a href="#contact" class="ibolt-outline-button">Request a Quote</a>
      </div>
    </div>

    <div class="ibolt-featured-visual">
      <div class="ibolt-visual-glow"></div>
      <div class="ibolt-visual-caption">ENTERPRISE SERVER</div>

      <!-- CSS server illustration. Replace this block with your product image
           when you have the correct server photo. -->
      <div class="ibolt-product-server server-top">
        <div class="ibolt-server-top-surface"></div>
        <div class="ibolt-server-front">
          <div class="ibolt-server-grille"></div>
          <div class="ibolt-server-brand">HPE</div>
          <div class="ibolt-server-status"><i></i><i></i><i></i></div>
        </div>
      </div>

      <div class="ibolt-product-server server-bottom">
        <div class="ibolt-server-top-surface"></div>
        <div class="ibolt-server-front">
          <div class="ibolt-server-grille"></div>
          <div class="ibolt-server-brand">HPE</div>
          <div class="ibolt-server-status"><i></i><i></i><i></i></div>
        </div>
      </div>
    </div>

  </div>
</section>



<!-- =========================
     IBOLTNETWORKS SERVER OFFERS
========================== -->
<section class="ibolt-server-offers">
  <!-- Dynamic Server Spotlight Offers from Database -->
  <div class="ibolt-product-grid">
    <?php
    $topOffers = !empty($serversHomepage) ? array_slice($serversHomepage, 0, 3) : [];
    ?>
    <?php if (!empty($topOffers)): ?>
      <?php foreach ($topOffers as $to): ?>
        <article class="ibolt-product-card">
          <p class="ibolt-brand"><?= e($to['brand_name'] ?? 'Enterprise Server') ?></p>
          <h3><?= e($to['title']) ?></h3>

          <div class="ibolt-product-image">
            <img
              src="<?= e($to['primary_image']) ?>"
              alt="<?= e($to['title']) ?>"
              loading="lazy"
            >
          </div>

          <p class="ibolt-starting">Starting at</p>
          <p class="ibolt-price"><?= format_inr((int)$to['price_paise']) ?>/-</p>
          <p class="ibolt-offer-label"><?= e($to['badge_text'] ?: 'Offer Price') ?></p>
          <a href="product.php?slug=<?= e($to['slug']) ?>" class="ibolt-order-btn">View Details</a>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<!-- Dell PowerEdge R640 Promotional Banner -->
<section class="ibolt-r640-section">
  <div class="ibolt-r640-banner">

    <!-- Left content -->
    <div class="ibolt-r640-content">
      <h2>Dell PowerEdge R640 Server</h2>

      <div class="ibolt-r640-underline"></div>

      <p class="ibolt-r640-discount">SAVE UP TO 12% OFF</p>

      <div class="ibolt-r640-specs">
        Server Configuration : 20-Core (2 x Intel Xeon Silver 4114)
        | 64GB DDR4 RAM | 1.2 TB SSD (2 x 600GB SATA)
      </div>

      <div class="ibolt-r640-price-box">
        <span>Starting @ INR 139,999/-</span>
      </div>

      <div class="ibolt-r640-actions">
        <a href="product.php?slug=dell-r640-rack-server" class="ibolt-r640-configure">
          <span class="ibolt-r640-gear" aria-hidden="true">⚙</span>
          Configure Now
        </a>

        <a
          href="https://wa.me/919911003865"
          class="ibolt-r640-whatsapp"
          aria-label="Contact us on WhatsApp"
          target="_blank"
          rel="noopener noreferrer"
        >
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M20.5 11.5a8.5 8.5 0 0 1-12.6 7.4L3 20l1.2-4.7a8.5 8.5 0 1 1 16.3-3.8Z"/>
            <path d="M8.5 7.8c.2-.5.5-.5.8-.5h.5c.2 0 .4.1.5.4l.8 1.8c.1.2.1.4-.1.6l-.6.7c-.2.2-.2.4 0 .6.6 1 1.4 1.7 2.4 2.2.2.1.4.1.6-.1l.8-.9c.2-.2.4-.2.6-.1l1.7.8c.2.1.3.3.3.5 0 .4-.2 1.1-.7 1.5-.5.5-1.1.7-1.8.6-1-.1-2.3-.7-3.7-1.9-1.6-1.5-2.7-3.3-2.8-4.5-.1-.8.3-1.4.7-1.7Z"/>
          </svg>
        </a>
      </div>
    </div>

    <!-- Right product panel -->
    <div class="ibolt-r640-product-panel">
      <div class="ibolt-r640-image-wrap">
        <img
          src="images/dell-poweredge-r640.png"
          alt="Dell PowerEdge R640 rack server"
          loading="lazy"
        >
      </div>

      <div class="ibolt-r640-benefits">
        <span>
          <span aria-hidden="true">✺</span>
          1 Year Warranty
        </span>

        <span>
          <span aria-hidden="true">🚚</span>
          Free Shipping
        </span>
      </div>
    </div>

  </div>
</section>
<!-- DDR3 RAM Promotional Banner -->
<section class="ram-promo">
    <div class="ram-promo-content">

        <div class="ram-promo-left">
            <h2>DDR3 RAMs at Low Prices!</h2>

            <div class="ram-features">
                <span>Fast Delivery</span>
                <span>Compatibility Assistance</span>
                <span>Assured Warranty</span>
            </div>

            <div class="ram-offer-details">
                <div class="ram-description">
                    Reliable DDR3 memory for servers and workstations,
                    built for smooth multitasking and stable performance.
                </div>

                <div class="ram-price-card">
                    <p class="ram-old-price">INR 1,798/-</p>
                    <h3>INR 1,619/-</h3>
                    <span class="ram-saving">Save INR 179</span>
                </div>
            </div>

            <div class="ram-actions">
                <a
                    class="ram-whatsapp"
                    href="https://wa.me/919911003865"
                    aria-label="Contact us on WhatsApp"
                >
                    ☎
                </a>

                <a class="ram-order-btn" href="products.php">
                    🛒 &nbsp; ORDER NOW
                </a>
            </div>
        </div>

        <div class="ram-promo-right">
            <div class="ram-discount">10%<small>OFF</small></div>

            <!-- Replace this with your own RAM product image -->
            <img
                src="images/ddr3-ram.png"
                alt="DDR3 server RAM memory module"
                class="ram-product-image"
            >

            <div class="ram-capacities">
                <span>16GB</span>
                <span>8GB</span>
                <span>32GB</span>
            </div>

            <span class="ram-product-label">SERVER RAM</span>
        </div>

    </div>
</section>
<!-- Enterprise DDR4 RAM Promotion -->
<section class="ddr4-promo">
    <div class="ddr4-layout">

        <!-- Left content -->
        <div class="ddr4-left">
            <h2>Limited-Time Discount on<br>Enterprise DDR4 RAM</h2>

            <div class="ddr4-features">
                <span>Bulk Order Savings</span>
                <span>High-Speed Memory</span>
                <span>Quick Shipping</span>
            </div>

            <div class="ddr4-offer-box">
                <div class="ddr4-description">
                    Designed for datacenters, AI workloads,
                    virtualization, and business-critical
                    server performance.
                </div>

                <div class="ddr4-price-box">
                    <p class="ddr4-old-price">INR 5,215/-</p>
                    <h3>INR 4,699/-</h3>
                    <span class="ddr4-saving">Save INR 516</span>
                </div>
            </div>

            <div class="ddr4-buttons">
                <a class="ddr4-whatsapp"
                   href="https://wa.me/919911003865"
                   aria-label="Contact us on WhatsApp">☏</a>

                <a class="ddr4-order" href="products.php">
                    🛒 &nbsp; ORDER NOW
                </a>
            </div>
        </div>

        <!-- Right product visual -->
        <div class="ddr4-right">
            <div class="ddr4-discount">
                <strong>10%</strong>
                <span>OFF</span>
            </div>

            <div class="ddr4-image-heading">SERVER RAM</div>

            <img
                class="ddr4-product-image"
                src="images/ddr4-server-ram.png"
                alt="Enterprise DDR4 server memory"
            >

            <div class="ddr4-capacities">
                <span>16GB</span>
                <span>32GB</span>
                <span>64GB</span>
                <span>128GB</span>
                <span>UP TO 512GB</span>
            </div>

            <div class="ddr4-extra-offer">
                <small>SAVE</small>
                <strong>₹10,000*</strong>
                <span>OFF</span>
            </div>
        </div>

    </div>
</section>

<!-- ===============================
     IBOLT NETWORKS - SERVERS SECTION
================================ -->

<section class="ibolt-servers">
  <div class="servers-heading">
    <div class="servers-heading-title">
      <span class="server-icon">▤</span>
      <h2>Servers</h2>
    </div>

    <nav class="server-brands">
      <a href="products.php?brand=dell">Dell Servers</a>
      <a href="products.php?brand=hpe">HP Servers</a>
      <a href="products.php?brand=cisco">Cisco Servers</a>
    </nav>
  </div>

  <div class="servers-layout">

    <!-- LEFT: Main content -->
    <div class="servers-main">

      <!-- Main promotional banner -->
      <div class="server-hero">
        <div class="server-hero-content">
          <span class="hero-brand">Hewlett Packard Enterprise</span>
          <span class="hero-amd">AMD</span>

          <p class="hero-eyebrow">Featuring AMD EPYC processors</p>
          <p class="hero-category">HP ProLiant</p>

          <h1>DL365 Gen10 Plus</h1>

          <p class="hero-description">
            Powerful server performance for virtualization,
            high-performance computing and data analytics.
          </p>

          <a class="hero-buy-button" href="#featured-servers">
            Explore Servers
          </a>
        </div>

        <div class="server-hero-image">
          <img
            src="https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1000&q=85"
            alt="Enterprise server hardware"
          >
          <span class="hero-image-label">ENTERPRISE SERVERS</span>
        </div>
      </div>

      <!-- Featured products heading -->
      <div class="featured-heading" id="featured-servers">
        <div class="featured-tabs">
          <span class="active-tab">Featured</span>
          <span>Best Selling</span>
        </div>

        <div class="featured-arrows" aria-hidden="true">
          ‹ &nbsp; ›
        </div>
      </div>

      <!-- Dynamic Database Products for Servers Section -->
      <div class="server-product-grid">
        <?php
        $mainServers = !empty($serversHomepage) ? array_slice($serversHomepage, 0, 4) : [];
        ?>
        <?php if (!empty($mainServers)): ?>
          <?php foreach ($mainServers as $ms): ?>
            <article class="server-product-card">
              <a class="server-product-image" href="product.php?slug=<?= e($ms['slug']) ?>">
                <img
                  src="<?= e($ms['primary_image']) ?>"
                  alt="<?= e($ms['title']) ?>"
                  loading="lazy"
                >
              </a>
              <div class="server-product-info">
                <span class="product-brand"><?= e($ms['brand_name'] ?? 'Dell &amp; HPE Servers') ?></span>
                <h3><?= e($ms['title']) ?></h3>
                <p class="product-specs"><?= e($ms['short_description'] ?? 'Certified enterprise datacenter hardware') ?></p>
                <p class="product-price"><?= format_inr((int)$ms['price_paise']) ?></p>
                <a class="product-button" href="product.php?slug=<?= e($ms['slug']) ?>">View Details</a>
              </div>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- RIGHT: Limited-time offers from database -->
    <aside class="server-offers">
      <h2>Offer Limited Time</h2>
      <?php
      $sidebarServers = !empty($serversHomepage) ? array_slice($serversHomepage, 4, 6) : [];
      ?>
      <?php if (!empty($sidebarServers)): ?>
        <?php foreach ($sidebarServers as $ss): ?>
          <a class="server-offer-item" href="product.php?slug=<?= e($ss['slug']) ?>">
            <div class="offer-image">
              <img
                src="<?= e($ss['primary_image']) ?>"
                alt="<?= e($ss['title']) ?>"
                loading="lazy"
              >
            </div>
            <div class="offer-details">
              <h3><?= e($ss['title']) ?></h3>
              <p class="offer-price"><?= format_inr((int)$ss['price_paise']) ?></p>
              <?php if (!empty($ss['compare_price_paise'])): ?>
                <del><?= format_inr((int)$ss['compare_price_paise']) ?></del>
              <?php endif; ?>
            </div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </aside>
  </div>
</section>

<!-- WORKSTATION SECTION -->
<section class="workstation-section">

    <!-- Section Header -->
    <div class="workstation-header">
        <div class="workstation-heading">
            <span class="workstation-icon">▣</span>
            <h2>Workstation</h2>
        </div>

        <nav class="workstation-nav">
            <a href="products.php?brand=dell">Dell Workstations</a>
            <a href="products.php?brand=hpe">HP Workstations</a>
        </nav>
    </div>

    <div class="workstation-layout">

        <!-- Main Content -->
        <div class="workstation-main">

            <!-- Promotional Banner -->
            <div class="workstation-banner">
                <div class="banner-copy">
                    <span class="banner-eyebrow">PROFESSIONAL COMPUTING</span>
                    <h3>Dell Precision T5810 Workstation</h3>
                    <p class="banner-subtitle">
                        Advanced Users &amp; Applications
                    </p>
                    <p class="banner-price">
                        Starting at <strong>₹35,999</strong>
                    </p>
                    <p class="banner-specs">
                        Basic Workstation &nbsp;|&nbsp; 12 Cores
                        &nbsp;|&nbsp; 32GB DDR4 Memory &nbsp;|&nbsp; 480GB SSD
                    </p>
                    <a class="workstation-buy-button" href="products.php?category=servers">Explore Workstations</a>
                </div>

                <div class="banner-image">
                    <img
                        src="images/dell-precision-t5810.png"
                        alt="Dell Precision T5810 workstation"
                    >
                </div>
            </div>

            <!-- Product Tabs (visual only for now) -->
            <div class="workstation-products-heading">
                <div class="workstation-tabs">
                    <span class="active">Featured</span>
                    <span>Best Selling</span>
                </div>

                <div class="workstation-arrows">
                    <span>‹</span>
                    <span>›</span>
                </div>
            </div>

            <!-- Product Cards -->
            <div class="workstation-products">

                <article class="workstation-product">
                    <a href="#" class="workstation-product-image">
                        <img src="images/dell-workstations.png"
                             alt="Dell AI workstations">
                    </a>
                    <a href="#" class="workstation-product-name">
                        Dell AI Workstations
                    </a>
                    <p class="workstation-product-price">Explore models</p>
                </article>

                <article class="workstation-product">
                    <a href="#" class="workstation-product-image">
                        <img src="images/dell-t5860.png"
                             alt="Dell Precision T5860">
                    </a>
                    <a href="#" class="workstation-product-name">
                        Dell Precision T5860
                    </a>
                    <p class="workstation-product-price">Explore models</p>
                </article>

                <article class="workstation-product">
                    <a href="#" class="workstation-product-image">
                        <img src="images/hp-workstation.png"
                             alt="HP AI workstations">
                    </a>
                    <a href="#" class="workstation-product-name">
                        HP AI Workstations
                    </a>
                    <p class="workstation-product-price">Explore models</p>
                </article>

                <article class="workstation-product">
                    <a href="#" class="workstation-product-image">
                        <img src="images/hp-zgx-nano.png"
                             alt="HP ZGX Nano AI workstation">
                    </a>
                    <a href="#" class="workstation-product-name">
                        HP ZGX Nano G1n AI
                    </a>
                    <p class="workstation-product-price">Explore models</p>
                </article>

            </div>
        </div>

        <!-- Right Sidebar -->
        <aside class="workstation-sidebar">
            <h3>Offer Limited Time</h3>

            <article class="offer-product">
                <a href="#" class="offer-image">
                    <img src="images/hp-z6-g4.png" alt="HP Z6 G4 workstation">
                </a>
                <div class="offer-details">
                    <a href="#">HP Z6 G4 Workstation</a>
                    <p class="offer-price">₹149,999.00</p>
                    <del>₹160,500.00</del>
                </div>
            </article>

            <article class="offer-product">
                <a href="#" class="offer-image">
                    <img src="images/dell-t5810.png"
                         alt="Dell Precision T5810 workstation">
                </a>
                <div class="offer-details">
                    <a href="#">Dell Precision T5810 Workstation</a>
                    <p class="offer-price">₹35,999.00</p>
                    <del>₹40,485.00</del>
                </div>
            </article>

            <article class="offer-product">
                <a href="#" class="offer-image">
                    <img src="images/dell-7810.png"
                         alt="Dell Precision Tower 7810">
                </a>
                <div class="offer-details">
                    <a href="#">Dell Precision Tower 7810 Workstation</a>
                    <p class="offer-price">₹138,999.00</p>
                    <del>₹154,288.00</del>
                </div>
            </article>

            <article class="offer-product">
                <a href="#" class="offer-image">
                    <img src="images/hp-z620.png" alt="HP Z620 workstation">
                </a>
                <div class="offer-details">
                    <a href="#">HP Z620 Tower Workstation</a>
                    <p class="offer-price">₹26,899.00</p>
                    <del>₹32,985.00</del>
                </div>
            </article>

            <article class="offer-product">
                <a href="#" class="offer-image">
                    <img src="images/dell-t3610.png"
                         alt="Dell Precision T3610 workstation">
                </a>
                <div class="offer-details">
                    <a href="#">Dell Precision T3610 Workstation</a>
                    <p class="offer-price">₹54,999.00</p>
                    <del>₹61,048.00</del>
                </div>
            </article>

            <article class="offer-product">
                <a href="#" class="offer-image">
                    <img src="images/hp-z440.png" alt="HP Z440 workstation">
                </a>
                <div class="offer-details">
                    <a href="#">HP Z440 Workstation</a>
                    <p class="offer-price">₹35,999.00</p>
                    <del>₹38,087.00</del>
                </div>
            </article>
        </aside>

    </div>
</section>

<!-- ===============================
     IBOLT NETWORKS - NETWORKING SECTION
================================ -->
<section class="ibolt-servers" id="networking">
  <div class="servers-heading">
    <div class="servers-heading-title">
      <span class="server-icon" style="background: #ede9fe; color: #6d28d9;">🖧</span>
      <h2>Networking &amp; Infrastructure</h2>
    </div>

    <nav class="server-brands">
      <a href="products.php?section=networking&category=network-switches">Managed Switches</a>
      <a href="products.php?section=networking&category=routers">Core Routers</a>
      <a href="products.php?section=networking&category=firewalls">Firewalls</a>
      <a href="products.php?section=networking">View All Networking →</a>
    </nav>
  </div>

  <div class="server-product-grid" style="margin-top: 24px;">
    <?php if (!empty($networkingHomepage)): ?>
      <?php foreach ($networkingHomepage as $np): ?>
        <article class="server-product-card">
          <a class="server-product-image" href="product.php?slug=<?= e($np['slug']) ?>">
            <img
              src="<?= e($np['primary_image']) ?>"
              alt="<?= e($np['title']) ?>"
              loading="lazy"
            >
          </a>
          <div class="server-product-info">
            <span class="product-brand"><?= e($np['brand_name'] ?? 'Enterprise Networking') ?></span>
            <h3><?= e($np['title']) ?></h3>
            <p class="product-specs"><?= e($np['short_description'] ?? 'Datacenter switches, routers &amp; connectivity') ?></p>
            <p class="product-price"><?= format_inr((int)$np['price_paise']) ?></p>
            <a class="product-button" href="product.php?slug=<?= e($np['slug']) ?>">View Details</a>
          </div>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<!-- iboltnetworks: Hosting & Server Promotions -->
<section class="ibolt-hosting-section">

  <!-- Main promotional banner -->
  <div class="ibolt-hosting-hero">
    <div class="ibolt-hero-content">
      <span class="ibolt-eyebrow">ENTERPRISE SOLUTIONS</span>
      <h1>Power Your Business with Enterprise Hardware</h1>
      <p>
        High-performance servers, workstations, storage and
        networking solutions for modern businesses.
      </p>

      <ul class="ibolt-benefits">
        <li>✓ Business-class hardware</li>
        <li>✓ Expert compatibility assistance</li>
        <li>✓ Delivery across India</li>
      </ul>

      <a href="products.php?category=servers" class="ibolt-primary-btn">
        Explore Servers →
      </a>
    </div>

    <div class="ibolt-hero-visual">
      <div class="ibolt-server-glow"></div>
      <img
        src="assets/images/server-rack.png"
        alt="Enterprise server rack"
        loading="lazy"
      >
      <div class="ibolt-image-label">
        <span>ENTERPRISE GRADE</span>
        <strong>SERVER SOLUTIONS</strong>
      </div>
    </div>
  </div>

  <!-- Promotional category banners -->
  <div class="ibolt-promo-grid">

    <a href="products.php?category=servers"
       class="ibolt-promo-card ibolt-promo-blue">
      <div>
        <span>PERFORMANCE COMPUTING</span>
        <h2>Dedicated Servers</h2>
        <p>Powerful hardware for demanding workloads.</p>
        <span class="ibolt-promo-link">Explore Products →</span>
      </div>
      <img src="assets/images/dedicated-server.png"
           alt="Dedicated server" loading="lazy">
    </a>

    <a href="products.php?category=workstations"
       class="ibolt-promo-card ibolt-promo-green">
      <div>
        <span>PROFESSIONAL COMPUTING</span>
        <h2>Workstations</h2>
        <p>Reliable systems for engineering and creative work.</p>
        <span class="ibolt-promo-link">Shop Workstations →</span>
      </div>
      <img src="assets/images/workstation.png"
           alt="Professional workstation" loading="lazy">
    </a>

    <a href="products.php?category=memory"
       class="ibolt-promo-card ibolt-promo-purple">
      <div>
        <span>UPGRADE YOUR SYSTEM</span>
        <h2>Server RAM</h2>
        <p>Memory upgrades for compatible systems.</p>
        <span class="ibolt-promo-link">Shop Memory →</span>
      </div>
      <img src="assets/images/server-ram.png"
           alt="Server memory module" loading="lazy">
    </a>

    <a href="products.php?category=storage"
       class="ibolt-promo-card ibolt-promo-dark">
      <div>
        <span>STORAGE SOLUTIONS</span>
        <h2>Enterprise SSDs</h2>
        <p>Storage options for servers and workstations.</p>
        <span class="ibolt-promo-link">Explore Storage →</span>
      </div>
      <img src="assets/images/enterprise-ssd.png"
           alt="Enterprise solid state drive" loading="lazy">
    </a>

  </div>

  <!-- Hosting category heading -->
  <div class="ibolt-section-heading">
    <div>
      <span class="ibolt-section-kicker">EXPLORE OUR RANGE</span>
      <h2>Servers & Enterprise Hardware</h2>
    </div>
    <a href="products.php?category=servers">View all products →</a>
  </div>

  <!-- Product area and sidebar -->
  <div class="ibolt-product-layout">

    <div class="ibolt-product-main">

      <div class="ibolt-security-banner">
        <div>
          <span>BUILT FOR BUSINESS</span>
          <h2>Reliable Hardware. Smarter IT.</h2>
          <p>
            Discover servers, components and accessories
            selected for your business needs.
          </p>
          <a href="products.php" class="ibolt-orange-btn">
            Explore Products
          </a>
        </div>
        <div class="ibolt-security-art" aria-hidden="true">
          <span>01</span>
          <span>10</span>
          <span>11</span>
          <span>01</span>
        </div>
      </div>

      <div class="ibolt-tabs-heading">
        <h3>Featured Products</h3>
        <a href="products.php">View all →</a>
      </div>

      <!-- Dynamic Database Featured Products Grid -->
      <div class="ibolt-featured-grid">
        <?php if (!empty($featuredHomepage)): ?>
          <?php foreach ($featuredHomepage as $fp): ?>
            <a class="ibolt-featured-card" href="product.php?slug=<?= e($fp['slug']) ?>">
              <div class="ibolt-product-image">
                <img src="<?= e($fp['primary_image']) ?>" alt="<?= e($fp['title']) ?>" loading="lazy">
              </div>
              <span class="ibolt-product-category"><?= e($fp['category_name'] ?? 'Featured') ?></span>
              <h4><?= e($fp['title']) ?></h4>
              <p class="ibolt-price"><?= format_inr((int)$fp['price_paise']) ?></p>
              <span class="ibolt-view-product">View Product →</span>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Featured products sidebar -->
    <aside class="ibolt-offer-sidebar">
      <h3>Popular Products</h3>
      <p class="ibolt-sidebar-subtitle">Explore our hardware range</p>

      <a href="products.php?category=servers" class="ibolt-sidebar-product">
        <img src="assets/images/server-product-1.png"
             alt="Server" loading="lazy">
        <div>
          <strong>Enterprise Rack Servers</strong>
          <span>Explore options</span>
        </div>
      </a>

      <a href="products.php?category=workstations" class="ibolt-sidebar-product">
        <img src="assets/images/workstation.png"
             alt="Workstation" loading="lazy">
        <div>
          <strong>Professional Workstations</strong>
          <span>Explore options</span>
        </div>
      </a>

      <a href="products.php?category=memory" class="ibolt-sidebar-product">
        <img src="assets/images/server-ram.png"
             alt="Server RAM" loading="lazy">
        <div>
          <strong>Server Memory</strong>
          <span>Explore options</span>
        </div>
      </a>

      <a href="products.php?category=storage" class="ibolt-sidebar-product">
        <img src="assets/images/enterprise-ssd.png"
             alt="Enterprise SSD" loading="lazy">
        <div>
          <strong>Enterprise Storage</strong>
          <span>Explore options</span>
        </div>
      </a>

      <div class="ibolt-help-card">
        <h4>Need help choosing?</h4>
        <p>Contact our team for product compatibility assistance.</p>
        <a href="contact.html">Contact Us →</a>
      </div>
    </aside>

  </div>
</section>

<!-- Components Section -->
<section class="components-section" id="components">
    <div class="components-container">
        <h2 class="components-title">Components</h2>

        <div class="components-grid">

            <a href="products.php?category=processors" class="component-card">
                <span class="component-icon">▦</span>
                <h3>Processors</h3>
            </a>

            <a href="products.php?category=memory" class="component-card">
                <span class="component-icon">▤</span>
                <h3>Memory</h3>
            </a>

            <a href="products.php?category=storage-drives" class="component-card">
                <span class="component-icon">▰</span>
                <h3>Storage Drives</h3>
            </a>

            <a href="products.php?category=graphic-cards" class="component-card">
                <span class="component-icon">▱</span>
                <h3>Graphic Cards</h3>
            </a>

            <a href="products.php?category=ethernet-cards" class="component-card">
                <span class="component-icon">▧</span>
                <h3>Ethernet Cards</h3>
            </a>

            <a href="products.php?category=hba-cards" class="component-card">
                <span class="component-icon">▤</span>
                <h3>HBA Cards</h3>
            </a>

            <a href="products.php?category=sfp-modules" class="component-card">
                <span class="component-icon">▬</span>
                <h3>SFP Modules</h3>
            </a>

            <a href="products.php?category=raid-controllers" class="component-card">
                <span class="component-icon">▧</span>
                <h3>RAID Controllers</h3>
            </a>

            <a href="products.php?category=power-supply" class="component-card">
                <span class="component-icon">⎓</span>
                <h3>Power Supply</h3>
            </a>

            <a href="products.php?category=fans" class="component-card">
                <span class="component-icon">✥</span>
                <h3>Fans</h3>
            </a>

            <a href="products.php?category=cpu-heatsinks" class="component-card">
                <span class="component-icon">♨</span>
                <h3>CPU Heatsinks</h3>
            </a>

            <a href="products.php?category=caddy" class="component-card">
                <span class="component-icon">◇</span>
                <h3>Caddy</h3>
            </a>

        </div>

        <?php if (!empty($componentsHomepage)): ?>
          <div style="margin-top: 40px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
              <div>
                <span style="font-size: 13px; font-weight: 700; color: #078e49; text-transform: uppercase; letter-spacing: 0.5px;">OEM &amp; Certified Hardware</span>
                <h3 style="font-size: 24px; font-weight: 800; color: #1e293b; margin: 4px 0 0;">Highlighted Enterprise Components</h3>
              </div>
              <a href="products.php?section=components" style="color: #078e49; font-weight: 700; font-size: 14px; text-decoration: none;">
                Explore All Components →
              </a>
            </div>

            <div class="server-product-grid">
              <?php foreach (array_slice($componentsHomepage, 0, 4) as $cp): ?>
                <article class="server-product-card">
                  <a class="server-product-image" href="product.php?slug=<?= e($cp['slug']) ?>">
                    <img
                      src="<?= e($cp['primary_image']) ?>"
                      alt="<?= e($cp['title']) ?>"
                      loading="lazy"
                    >
                  </a>
                  <div class="server-product-info">
                    <span class="product-brand"><?= e($cp['brand_name'] ?? 'Components') ?></span>
                    <h3><?= e($cp['title']) ?></h3>
                    <p class="product-specs"><?= e($cp['short_description'] ?? 'Datacenter grade compute spares') ?></p>
                    <p class="product-price"><?= format_inr((int)$cp['price_paise']) ?></p>
                    <a class="product-button" href="product.php?slug=<?= e($cp['slug']) ?>">View Details</a>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
    </div>
</section>

<!-- ===============================
     IBOLT NETWORKS - OUR PRODUCTS SECTION
================================ -->
<section class="ibolt-servers" id="our-products">
  <div class="servers-heading">
    <div class="servers-heading-title">
      <span class="server-icon" style="background: #fee2e2; color: #991b1b;">🛍️</span>
      <h2>Our Products &amp; Peripherals</h2>
    </div>

    <nav class="server-brands">
      <a href="products.php?section=our-products&category=computer-accessories">Accessories</a>
      <a href="products.php?section=our-products&category=monitors-displays">Monitors</a>
      <a href="products.php?section=our-products&category=laptop-accessories">Laptop Gear</a>
      <a href="products.php?section=our-products">Browse All Catalog →</a>
    </nav>
  </div>

  <div class="server-product-grid" style="margin-top: 24px;">
    <?php if (!empty($ourProductsHomepage)): ?>
      <?php foreach ($ourProductsHomepage as $op): ?>
        <article class="server-product-card">
          <a class="server-product-image" href="product.php?slug=<?= e($op['slug']) ?>">
            <img
              src="<?= e($op['primary_image']) ?>"
              alt="<?= e($op['title']) ?>"
              loading="lazy"
            >
          </a>
          <div class="server-product-info">
            <span class="product-brand"><?= e($op['brand_name'] ?? 'Peripherals') ?></span>
            <h3><?= e($op['title']) ?></h3>
            <p class="product-specs"><?= e($op['short_description'] ?? 'Workplace essentials &amp; hardware accessories') ?></p>
            <p class="product-price"><?= format_inr((int)$op['price_paise']) ?></p>
            <a class="product-button" href="product.php?slug=<?= e($op['slug']) ?>">View Details</a>
          </div>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>


<section class="server-seo-section">
    <div class="server-seo-container">

        <h1 class="server-seo-title">
            Buy Server Online India: HP, DELL, IBM Servers At Lowest Price
        </h1>

        <h2 class="server-seo-heading">Servers:</h2>

        <div class="server-seo-content">
            <h3>Latest Servers</h3>

            <p>
                Need feature-rich, power-packed, and highly secure best
                servers for diverse business environments and demanding
                workloads? Explore Server Basket’s extensive range of the
                latest servers from
                <a href="#">HP</a>,
                <a href="#">Dell</a>,
                <a href="#">Lenovo</a>,
                <a href="#">IBM</a>,
                <a href="#">SuperMicro</a>,
                and other popular brands to select the best new server’s
                for your organization. Our brand new buy servers online
                span from racks to towers, blades, high-density, storage,
                mission-critical, Edge, and application-based servers,
                the solutions that can steer your business forward by
                matching your needs perfectly. These buying servers
                online are built on the newest technologies, high-end
                CPUs and GPUs, and the most upgraded memory, operating
                systems, and drive configurations. That’s not all!
                Get excellent deals even on new servers online,
                exclusively at Server Basket along with a bunch of
                add-on services.
            </p>

            <h3>Refurbished Servers</h3>

            <p>
                Need high-quality servers at low prices? Server Basket
                offers a huge variety of refurb servers that are
                engineered with industry-certified spares. Our
                refurbished <a href="#">Dell servers</a> and
                <a href="#">HP servers</a> are known for their superior
                performance, and we give you no reason to worry about
                investing in our <a href="#">used servers</a> as each
                of them is refurbished under technical supervision
                and is tested in burn-in conditions before being
                stocked in the inventory.
            </p>

            <a class="server-read-more" href="#">Read More</a>
        </div>

    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
