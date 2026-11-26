<?php
declare(strict_types=1);

/**
 * Admin Panel Footer Partial
 * iboltnetworks E-Commerce Platform
 */
?>
  <footer style="text-align: center; padding: 24px 16px max(24px, env(safe-area-inset-bottom)); color: #94a3b8; font-size: 13px; border-top: 1px solid #e2e8f0; margin-top: 40px;">
    &copy; <?= date('Y') ?> iboltnetworks Admin Control Panel &bull; Enterprise Hardware Management
  </footer>

  <!-- Admin Responsive Drawer & Interactive Enhancements -->
  <script>
  (function() {
    'use strict';

    const menuToggle = document.getElementById('adminMenuToggle');
    const drawerClose = document.getElementById('adminDrawerClose');
    const backdrop = document.getElementById('adminDrawerBackdrop');
    const drawer = document.getElementById('adminMobileDrawer');

    function openDrawer() {
      document.body.classList.add('drawer-open');
      if (menuToggle) {
        menuToggle.setAttribute('aria-expanded', 'true');
      }
      if (drawer) {
        drawer.setAttribute('aria-hidden', 'false');
        // Set focus to the close button for keyboard/screen reader accessibility
        if (drawerClose) {
          setTimeout(function() { drawerClose.focus(); }, 50);
        }
      }
      if (backdrop) {
        backdrop.setAttribute('aria-hidden', 'false');
      }
    }

    function closeDrawer() {
      document.body.classList.remove('drawer-open');
      if (menuToggle) {
        menuToggle.setAttribute('aria-expanded', 'false');
        menuToggle.focus();
      }
      if (drawer) {
        drawer.setAttribute('aria-hidden', 'true');
      }
      if (backdrop) {
        backdrop.setAttribute('aria-hidden', 'true');
      }
    }

    if (menuToggle) {
      menuToggle.addEventListener('click', function(e) {
        e.preventDefault();
        if (document.body.classList.contains('drawer-open')) {
          closeDrawer();
        } else {
          openDrawer();
        }
      });
    }

    if (drawerClose) {
      drawerClose.addEventListener('click', function(e) {
        e.preventDefault();
        closeDrawer();
      });
    }

    if (backdrop) {
      backdrop.addEventListener('click', function(e) {
        e.preventDefault();
        closeDrawer();
      });
    }

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && document.body.classList.contains('drawer-open')) {
        closeDrawer();
      }
    });

    // Close when navigating via links in drawer
    if (drawer) {
      const links = drawer.querySelectorAll('a');
      links.forEach(function(link) {
        link.addEventListener('click', function() {
          document.body.classList.remove('drawer-open');
        });
      });
    }

    // Enhance all table wrappers with accessible scrolling
    const tableWraps = document.querySelectorAll('.admin-table-wrap');
    tableWraps.forEach(function(wrap) {
      if (!wrap.hasAttribute('tabindex')) {
        wrap.setAttribute('tabindex', '0');
      }
      if (!wrap.hasAttribute('role')) {
        wrap.setAttribute('role', 'region');
      }
      if (!wrap.hasAttribute('aria-label')) {
        wrap.setAttribute('aria-label', 'Scrollable tabular data');
      }
    });
  })();
  </script>
</body>
</html>
