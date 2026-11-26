/**
 * iboltnetworks - Primary Navigation & Mega Menu Controller
 */
(function () {
  'use strict';

  function initMegaMenus() {
    const triggerItems = document.querySelectorAll('.nav-item.has-mega-menu');
    if (!triggerItems.length) return;

    triggerItems.forEach((triggerItem) => {
      const triggerLink = triggerItem.querySelector(':scope > a');
      const megaMenu = triggerItem.querySelector('.mega-menu');

      if (!triggerLink || !megaMenu) return;

      let closeTimer = null;
      const CLOSE_DELAY = 150; // ms buffer to prevent accidental closing on cursor transition

      function openMenu() {
        if (closeTimer) {
          clearTimeout(closeTimer);
          closeTimer = null;
        }
        // Close other open mega menus to prevent overlap
        triggerItems.forEach((other) => {
          if (other !== triggerItem) {
            other.classList.remove('is-open');
            const otherLink = other.querySelector(':scope > a');
            if (otherLink) otherLink.setAttribute('aria-expanded', 'false');
          }
        });
        triggerItem.classList.add('is-open');
        triggerLink.setAttribute('aria-expanded', 'true');
      }

      function closeMenu(immediate = false) {
        if (closeTimer) {
          clearTimeout(closeTimer);
          closeTimer = null;
        }
        if (immediate) {
          triggerItem.classList.remove('is-open');
          triggerLink.setAttribute('aria-expanded', 'false');
        } else {
          closeTimer = setTimeout(() => {
            triggerItem.classList.remove('is-open');
            triggerLink.setAttribute('aria-expanded', 'false');
            closeTimer = null;
          }, CLOSE_DELAY);
        }
      }

      // Desktop hover events with debounce buffer
      triggerItem.addEventListener('mouseenter', openMenu);
      triggerItem.addEventListener('mouseleave', () => closeMenu(false));

      // Focus inside trigger or dropdown keeps it open
      triggerItem.addEventListener('focusin', openMenu);
      triggerItem.addEventListener('focusout', (e) => {
        // If focus moves outside this triggerItem, close immediately
        if (!triggerItem.contains(e.relatedTarget)) {
          closeMenu(true);
        }
      });

      // Click/Touch handling for tablets & mobile
      triggerLink.addEventListener('click', (e) => {
        const isExpanded = triggerLink.getAttribute('aria-expanded') === 'true';
        // On touch devices or small screens, toggle
        if (window.matchMedia('(hover: none)').matches || window.innerWidth <= 1024) {
          e.preventDefault();
          if (isExpanded) {
            closeMenu(true);
          } else {
            openMenu();
          }
        }
      });

      // Mobile close button inside dropdown
      const closeButtons = triggerItem.querySelectorAll('.mega-menu-close-btn');
      closeButtons.forEach((btn) => {
        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          e.preventDefault();
          closeMenu(true);
          triggerLink.focus();
        });
      });
    });

    // Keyboard navigation: Escape key closes all open menus and returns focus
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' || e.key === 'Esc') {
        triggerItems.forEach((item) => {
          if (item.classList.contains('is-open')) {
            item.classList.remove('is-open');
            const link = item.querySelector(':scope > a');
            if (link) {
              link.setAttribute('aria-expanded', 'false');
              link.focus();
            }
          }
        });
      }
    });

    // Outside click closes any open mega menu
    document.addEventListener('click', (e) => {
      triggerItems.forEach((item) => {
        if (!item.contains(e.target)) {
          item.classList.remove('is-open');
          const link = item.querySelector(':scope > a');
          if (link) link.setAttribute('aria-expanded', 'false');
        }
      });
    });
  }

  // Global Demo Cart & Toast Controller (Frontend Demonstration Only)
  function initDemoCart() {
    const cartLinks = document.querySelectorAll('.cart-link');

    function updateBadgeCount() {
      const demoCount = sessionStorage.getItem('ibolt_demo_cart_count');
      if (demoCount !== null) {
        cartLinks.forEach((link) => {
          const href = link.getAttribute('href');
          // Only override badge on static/demo pages, never on live PHP pages linking to cart.php
          if (!href || href === '#' || href.startsWith('javascript:')) {
            const badge = link.querySelector('b');
            if (badge) badge.textContent = demoCount;
          }
        });
      }
    }

    updateBadgeCount();

    cartLinks.forEach((link) => {
      link.addEventListener('click', (e) => {
        const href = link.getAttribute('href');
        if (!href || href === '#' || href.startsWith('javascript:')) {
          e.preventDefault();
          const demoCount = parseInt(sessionStorage.getItem('ibolt_demo_cart_count') || '0', 10);
          showDemoToast(
            'Demo Cart (Frontend Simulation)',
            demoCount > 0
              ? `Your demo cart has ${demoCount} item(s) in this session. (Frontend demonstration only — no real checkout or database persistence).`
              : 'Your demo cart is currently empty. Click "+ Add to Cart (Demo)" on any demonstration product to test the frontend interaction!'
          );
        }
      });
    });

    window.iboltAddDemoCartItem = function (productName) {
      let demoCount = parseInt(sessionStorage.getItem('ibolt_demo_cart_count') || '0', 10);
      demoCount += 1;
      sessionStorage.setItem('ibolt_demo_cart_count', String(demoCount));
      updateBadgeCount();

      cartLinks.forEach((link) => {
        link.classList.remove('cart-pulse');
        void link.offsetWidth; // trigger reflow
        link.classList.add('cart-pulse');
      });

      showDemoToast(
        'Added to Demo Cart!',
        `"${productName}" was added to your demo session. (Frontend demonstration only — not saved to database).`
      );
    };
  }

  // Demo Toast Notification Helper
  function showDemoToast(title, message) {
    let toast = document.querySelector('.demo-toast');
    if (!toast) {
      toast = document.createElement('div');
      toast.className = 'demo-toast';
      toast.setAttribute('role', 'status');
      toast.setAttribute('aria-live', 'polite');
      document.body.appendChild(toast);
    }

    toast.innerHTML = `
      <div class="demo-toast-content">
        <h4 class="demo-toast-title">${title}</h4>
        <p class="demo-toast-desc">${message}</p>
      </div>
      <button type="button" class="demo-toast-close" aria-label="Dismiss notification">✕</button>
    `;

    toast.classList.add('is-visible');

    const closeBtn = toast.querySelector('.demo-toast-close');
    if (closeBtn) {
      closeBtn.onclick = () => {
        toast.classList.remove('is-visible');
      };
    }

    if (window._demoToastTimer) clearTimeout(window._demoToastTimer);
    window._demoToastTimer = setTimeout(() => {
      toast.classList.remove('is-visible');
    }, 4500);
  }

  window.showDemoToast = showDemoToast;

  // Announcement bar dismiss
  function initAnnouncement() {
    const closeBtn = document.querySelector('.announcement-close');
    const bar = document.querySelector('.announcement');
    if (closeBtn && bar) {
      closeBtn.addEventListener('click', (e) => {
        e.preventDefault();
        bar.style.display = 'none';
      });
    }
  }

  // Initialize on DOMContentLoaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      initMegaMenus();
      initDemoCart();
      initAnnouncement();
    });
  } else {
    initMegaMenus();
    initDemoCart();
    initAnnouncement();
  }
})();

