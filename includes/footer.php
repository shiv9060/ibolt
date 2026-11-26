<?php
declare(strict_types=1);

/**
 * Global Footer Partial
 * iboltnetworks E-Commerce Platform
 */
$rootPath = $rootPath ?? "";
$phoneNumber = env("STORE_PHONE", "+91 99110 03865");
$whatsappNumber = env("CONTACT_WHATSAPP", "919911003865");
$cleanWhatsApp = preg_replace("/[^0-9]/", "", (string)$whatsappNumber);
if (empty($cleanWhatsApp) || str_contains((string)$whatsappNumber, "XXXXX")) {
    $cleanWhatsApp = "919911003865";
}
$cleanPhone = preg_replace("/[^0-9+]/", "", (string)$phoneNumber);
$salesEmail = env("CONTACT_EMAIL", env("STORE_EMAIL", "sales@iboltnetworks.com"));
$storeAddress = env("STORE_ADDRESS", "Ground Floor, Plot No-RZ-J-8, KH No.7/18/2, Tamilians Enclave, Tamil Enclave, Dabri Back Dwarka, South West Delhi, New Delhi, Delhi 110045");
?>

  <!-- Floating WhatsApp button -->
  <a class="whatsapp-float"
     href="https://wa.me/<?= e($cleanWhatsApp) ?>"
     target="_blank"
     rel="noopener noreferrer"
     aria-label="Chat with iboltnetworks engineers on WhatsApp">
    <span class="whatsapp-pulse"></span>
    <svg viewBox="0 0 32 32" aria-hidden="true" class="wa-icon">
      <path d="M16 3.2A12.1 12.1 0 0 0 5.6 21.5L4 28l6.7-1.7A12.2 12.2 0 1 0 16 3.2Z"
            fill="none" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/>
      <path d="M12 10.2c-.4-.8-.8-.8-1.2-.8h-1c-.4 0-.9.2-1.2.7-.4.5-1.6 1.6-1.6 3.8s1.6 4.3 1.8 4.6c.2.3 3.1 4.9 7.6 6.1 3.8 1 4.6-.3 5.4-1.1.7-.8.7-1.5.5-1.7-.2-.3-.4-.4-.9-.7-.5-.2-2.7-1.3-3.1-1.4-.4-.2-.7-.2-.9.2-.3.4-1.1 1.4-1.4 1.7-.3.3-.5.3-1 .1-.5-.2-1.9-.7-3.6-2.2-1.3-1.2-2.2-2.7-2.4-3.1-.3-.5 0-.7.2-.9l.7-.8c.2-.3.3-.5.5-.8.1-.3.1-.6 0-.8-.1-.2-1-2.5-1.4-3.4Z"
            fill="currentColor" transform="translate(3 1) scale(.8)"/>
    </svg>
    <span class="whatsapp-tooltip">24×7 Engineer Chat</span>
  </a>

  <!-- Redesigned Site Footer -->
  <footer class="site-footer" id="site-footer">

    <!-- Top Feature & Trust Strip -->
    <div class="footer-features-bar">
      <div class="footer-features-inner">
        <div class="footer-feature-item">
          <div class="footer-feature-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
          </div>
          <div class="footer-feature-text">
            <strong>Pan-India Express Dispatch</strong>
            <span>Verified tracked shipping on all servers</span>
          </div>
        </div>

        <div class="footer-feature-item">
          <div class="footer-feature-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="m9 12 2 2 4-4"></path></svg>
          </div>
          <div class="footer-feature-text">
            <strong>Certified &amp; Tested Hardware</strong>
            <span>1 to 3-Year comprehensive replacement warranty</span>
          </div>
        </div>

        <div class="footer-feature-item">
          <div class="footer-feature-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-6a9 9 0 0 1 18 0v6"></path><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path><path d="M12 19v3"></path></svg>
          </div>
          <div class="footer-feature-text">
            <strong>24×7 L2/L3 Tech Engineers</strong>
            <span>Direct consultation with average 15-min SLA</span>
          </div>
        </div>

        <div class="footer-feature-item">
          <div class="footer-feature-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
          </div>
          <div class="footer-feature-text">
            <strong>100% Secure Checkout</strong>
            <span>Razorpay encrypted &amp; verified transactions</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Footer Grid (4 balanced, aligned columns) -->
    <div class="footer-container">
      
      <!-- Column 1: Brand & Profile -->
      <div class="footer-column footer-brand-column">
        <a href="<?= e($rootPath) ?>index.php" class="footer-brand-logo" aria-label="iboltnetworks home">
          <span class="footer-brand-mark">iB</span>
          <span class="footer-brand-text">
            <strong>ibolt<span>networks</span></strong>
            <small>Powering your technology</small>
          </span>
        </a>

        <p class="footer-brand-desc">
          India&#39;s premier enterprise hardware platform. We supply certified rack &amp; tower servers, HPC AI clusters, enterprise firewalls, and verified network infrastructure for mission-critical deployments.
        </p>

        <p class="footer-office-loc">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#00e06d; flex-shrink:0; margin-top:2px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
          <span><?= e($storeAddress) ?></span>
        </p>

        <div class="footer-status-badge">
          <span class="status-pulse-dot"></span>
          <span>Enterprise Desk: <strong>Online 24×7</strong></span>
        </div>

        <div class="footer-social-wrap">
          <span class="footer-social-label">Connect with Us</span>
          <div class="footer-social-icons">
            <a href="https://linkedin.com" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn" class="social-btn">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.28 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.75M6.46 8.76a1.53 1.53 0 1 0 0-3.05 1.53 1.53 0 0 0 0 3.05m1.39 9.74v-8.37H5.07v8.37h2.78z"/></svg>
            </a>
            <a href="https://twitter.com" target="_blank" rel="noopener noreferrer" aria-label="X (Twitter)" class="social-btn">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
            </a>
            <a href="https://facebook.com" target="_blank" rel="noopener noreferrer" aria-label="Facebook" class="social-btn">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 .955.042 1.468.103a8.68 8.68 0 0 1 1.141.195v3.325a8.623 8.623 0 0 0-.653-.036 26.805 26.805 0 0 0-.733-.009c-.77 0-1.611.179-1.92.585-.308.404-.325 1.059-.325 2.053v1.347h4.089l-.545 3.667h-3.544v7.98h-4.813z"/></svg>
            </a>
            <a href="https://youtube.com" target="_blank" rel="noopener noreferrer" aria-label="YouTube" class="social-btn">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
            </a>
            <a href="https://instagram.com" target="_blank" rel="noopener noreferrer" aria-label="Instagram" class="social-btn">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
            </a>
          </div>
        </div>
      </div>

      <!-- Column 2: Hardware Categories -->
      <div class="footer-column">
        <h3 class="footer-heading">
          <span>Hardware Categories</span>
        </h3>
        <ul class="footer-links">
          <li><a href="<?= e($rootPath) ?>products.php?brand=dell">Dell PowerEdge Servers</a></li>
          <li><a href="<?= e($rootPath) ?>products.php?brand=hpe">HPE ProLiant Systems</a></li>
          <li><a href="<?= e($rootPath) ?>products.php?brand=cisco">Cisco Enterprise Switches</a></li>
          <li><a href="<?= e($rootPath) ?>products.php?brand=huawei">Huawei Hardware Solutions</a></li>
          <li><a href="<?= e($rootPath) ?>products.php?category=storage-servers">SAN &amp; NAS Storage Servers</a></li>
          <li><a href="<?= e($rootPath) ?>products.php?category=ai-servers">GPU &amp; High-Density Compute</a></li>
          <li><a href="<?= e($rootPath) ?>products.php?section=our-products">Server Spares &amp; Peripherals</a></li>
        </ul>
      </div>

      <!-- Column 3: Enterprise Solutions -->
      <div class="footer-column">
        <h3 class="footer-heading">
          <span>Enterprise Solutions</span>
        </h3>
        <ul class="footer-links">
          <li><a href="<?= e($rootPath) ?>products.php?category=servers">Dedicated Enterprise Servers</a></li>
          <li><a href="<?= e($rootPath) ?>products.php?category=ai-servers">AI &amp; HPC Infrastructure</a></li>
          <li><a href="<?= e($rootPath) ?>products.php?category=firewalls">Enterprise Firewalls &amp; VPN</a></li>
          <li><a href="<?= e($rootPath) ?>products.php?category=load-balancers">Hardware Load Balancers</a></li>
          <li><a href="<?= e($rootPath) ?>products.php?category=custom-server">Custom Server Architectures</a></li>
          <li><a href="<?= e($rootPath) ?>contact.html">Data Center Solutions</a></li>
          <li><a href="<?= e($rootPath) ?>contact.html">System Integrators &amp; Resellers</a></li>
        </ul>
      </div>

      <!-- Column 4: 24/7 Enterprise Assistance Card -->
      <div class="footer-column footer-contact-column">
        <h3 class="footer-heading">
          <span>Need Assistance?</span>
        </h3>

        <div class="footer-contact-card">
          <div class="footer-contact-badge">DIRECT ENGINEER ACCESS</div>

          <a href="tel:<?= e($cleanPhone) ?>" class="contact-pill contact-pill-phone">
            <div class="contact-pill-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
            </div>
            <div class="contact-pill-body">
              <small>Hotline &amp; Sales Call</small>
              <strong><?= e($phoneNumber) ?></strong>
            </div>
          </a>

          <a href="https://wa.me/<?= e($cleanWhatsApp) ?>" target="_blank" rel="noopener noreferrer" class="contact-pill contact-pill-wa">
            <div class="contact-pill-icon">
              <svg width="20" height="20" viewBox="0 0 32 32" fill="currentColor"><path d="M16 3.2A12.1 12.1 0 0 0 5.6 21.5L4 28l6.7-1.7A12.2 12.2 0 1 0 16 3.2Z" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/><path d="M12 10.2c-.4-.8-.8-.8-1.2-.8h-1c-.4 0-.9.2-1.2.7-.4.5-1.6 1.6-1.6 3.8s1.6 4.3 1.8 4.6c.2.3 3.1 4.9 7.6 6.1 3.8 1 4.6-.3 5.4-1.1.7-.8.7-1.5.5-1.7-.2-.3-.4-.4-.9-.7-.5-.2-2.7-1.3-3.1-1.4-.4-.2-.7-.2-.9.2-.3.4-1.1 1.4-1.4 1.7-.3.3-.5.3-1 .1-.5-.2-1.9-.7-3.6-2.2-1.3-1.2-2.2-2.7-2.4-3.1-.3-.5 0-.7.2-.9l.7-.8c.2-.3.3-.5.5-.8.1-.3.1-.6 0-.8-.1-.2-1-2.5-1.4-3.4Z"/></svg>
            </div>
            <div class="contact-pill-body">
              <small>24×7 WhatsApp Desk</small>
              <strong><?= e($phoneNumber) ?></strong>
            </div>
          </a>

          <a href="mailto:<?= e($salesEmail) ?>" class="contact-pill contact-pill-email">
            <div class="contact-pill-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
            </div>
            <div class="contact-pill-body">
              <small>Technical Sales Email</small>
              <strong><?= e($salesEmail) ?></strong>
            </div>
          </a>

          <div class="contact-pill contact-pill-address">
            <div class="contact-pill-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
            </div>
            <div class="contact-pill-body">
              <small>Corporate Office</small>
              <strong style="white-space: normal; line-height: 1.35; font-size: 13px;"><?= e($storeAddress) ?></strong>
            </div>
          </div>

          <a href="<?= e($rootPath) ?>contact.html" class="footer-cta-btn">
            <span>Request Custom Server Quote</span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
          </a>
        </div>
      </div>

    </div>

    <!-- Bottom Copyright & Legal Bar -->
    <div class="footer-bottom-bar">
      <div class="footer-bottom-inner">
        <div class="footer-bottom-copy">
          <p>© <span id="footer-year"><?= date("Y") ?></span> <strong>iboltnetworks Private Limited</strong>. All rights reserved.</p>
          <p class="footer-bottom-sub"><?= e($storeAddress) ?> • Enterprise Computer Hardware • High-Density Servers • Cloud Networking</p>
        </div>

        <div class="footer-bottom-links">
          <a href="<?= e($rootPath) ?>contact.html">About iboltnetworks</a>
          <span class="sep">•</span>
          <a href="<?= e($rootPath) ?>contact.html">Contact Us</a>
          <span class="sep">•</span>
          <a href="<?= e($rootPath) ?>contact.html">Privacy Policy</a>
          <span class="sep">•</span>
          <a href="<?= e($rootPath) ?>contact.html">Terms &amp; Conditions</a>
          <span class="sep">•</span>
          <a href="<?= e($rootPath) ?>contact.html">Warranty Terms</a>
          <span class="sep">•</span>
          <a href="<?= e($rootPath) ?>contact.html">Payment Security</a>
        </div>

        <div class="footer-security-chips">
          <span class="sec-chip"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg> 256-bit SSL</span>
          <span class="sec-chip"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg> Razorpay Secure</span>
          <span class="sec-chip">ISO 9001:2015</span>
        </div>
      </div>
    </div>
  </footer>

  <script src="<?= e($rootPath) ?>assets/js/script.js" defer></script>
  <?= $extraScripts ?? "" ?>
</body>
</html>
