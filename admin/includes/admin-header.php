<?php
declare(strict_types=1);

if (ob_get_level() === 0) {
    ob_start();
}

/**
 * Admin Panel Header Partial
 * iboltnetworks E-Commerce Platform
 */

$adminRoot = $adminRoot ?? '';

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/auth.php';
require_once __DIR__ . '/../../app/helpers/csrf.php';
require_once __DIR__ . '/../../app/helpers/sanitize.php';
require_once __DIR__ . '/../../app/helpers/currency.php';
require_once __DIR__ . '/../../app/helpers/homepage.php';

init_session();
require_admin();

$admin = current_admin();
$adminPageTitle = $adminPageTitle ?? 'Admin Dashboard | iboltnetworks';
$currentScript = $_SERVER['SCRIPT_NAME'] ?? '';

if (!function_exists('is_admin_active')) {
    function is_admin_active(string $path): bool {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        return str_contains($script, $path);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title><?= e($adminPageTitle) ?></title>
  <link rel="icon" type="image/png" href="<?= e($adminRoot) ?>../assets/images/favicon.png">
  <style>
    :root {
      --admin-green: #078e49;
      --admin-green-dark: #056633;
      --admin-green-light: #e8f7ee;
      --admin-dark: #0f172a;
      --admin-dark-hover: #1e293b;
      --admin-border: #e2e8f0;
      --admin-bg: #f8fafc;
      --admin-card: #ffffff;
      --admin-muted: #64748b;
      --font-stack: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: var(--font-stack);
      background: var(--admin-bg);
      color: #1e293b;
      -webkit-font-smoothing: antialiased;
      line-height: 1.5;
    }
    a { color: var(--admin-green); text-decoration: none; }
    a:hover { text-decoration: underline; }

    /* Top Navigation Bar */
    .admin-navbar {
      background: var(--admin-dark);
      color: #ffffff;
      padding: 0 24px;
      height: 64px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 200;
      box-shadow: 0 2px 10px rgba(0,0,0,0.15);
    }
    .admin-brand {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 18px;
      font-weight: 800;
      color: #ffffff;
      text-decoration: none;
    }
    .admin-brand span { color: var(--admin-green); }
    .admin-badge-role {
      background: rgba(7,142,73,0.25);
      color: #4ade80;
      font-size: 11px;
      font-weight: 700;
      padding: 2px 8px;
      border-radius: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .admin-nav-links {
      display: flex;
      align-items: center;
      gap: 4px;
      list-style: none;
      margin: 0;
      padding: 0;
    }
    .admin-nav-links > li {
      position: relative;
    }
    .admin-nav-links a {
      color: #cbd5e1;
      padding: 8px 13px;
      border-radius: 6px;
      font-size: 13.5px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 6px;
      transition: background 0.15s, color 0.15s;
      text-decoration: none;
    }
    .admin-nav-links a:hover,
    .admin-nav-links a.active,
    .admin-nav-links li:hover > a {
      background: rgba(255,255,255,0.1);
      color: #ffffff;
    }

    /* Navigation Dropdowns */
    .admin-dropdown-menu {
      display: none;
      position: absolute;
      top: 100%;
      left: 0;
      background: #1e293b;
      min-width: 240px;
      border-radius: 8px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.3);
      padding: 8px 0;
      z-index: 300;
      border: 1px solid rgba(255,255,255,0.1);
    }
    .admin-nav-links li:hover .admin-dropdown-menu {
      display: block;
    }
    .admin-dropdown-menu a {
      padding: 9px 16px;
      color: #cbd5e1;
      font-size: 13px;
      border-radius: 0;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .admin-dropdown-menu a:hover {
      background: var(--admin-green);
      color: #ffffff;
    }
    .admin-dropdown-divider {
      height: 1px;
      background: rgba(255,255,255,0.08);
      margin: 6px 0;
    }

    .admin-user-menu {
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .admin-user-meta {
      font-size: 13px;
      color: #94a3b8;
      text-align: right;
    }
    .admin-user-meta strong {
      display: block;
      color: #f1f5f9;
    }
    .btn-admin-logout {
      background: rgba(239, 68, 68, 0.15);
      color: #f87171;
      border: 1px solid rgba(239, 68, 68, 0.3);
      padding: 6px 14px;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 700;
      transition: background 0.15s;
    }
    .btn-admin-logout:hover {
      background: #ef4444;
      color: #ffffff;
      text-decoration: none;
    }

    /* Responsive display utilities */
    .mobile-only { display: none !important; }
    .mobile-only-flex { display: none !important; }
    .desktop-only { display: block !important; }
    .desktop-only-wrap { display: block !important; }
    .desktop-only-table { display: table !important; }

    /* Layout Containers */
    .admin-container {
      max-width: 1360px;
      margin: 0 auto;
      padding: 30px 24px 60px;
    }
    .admin-page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
      flex-wrap: wrap;
      gap: 16px;
    }
    .admin-page-header h1 {
      font-size: 26px;
      font-weight: 800;
      color: var(--admin-dark);
      margin: 0;
    }
    .admin-page-header p {
      font-size: 14px;
      color: var(--admin-muted);
      margin: 4px 0 0;
    }

    /* Cards */
    .admin-card {
      background: var(--admin-card);
      border: 1px solid var(--admin-border);
      border-radius: 12px;
      padding: 24px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.03);
      margin-bottom: 24px;
    }
    .admin-card h2 {
      font-size: 18px;
      font-weight: 800;
      color: var(--admin-dark);
      margin: 0 0 18px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .admin-card h3 {
      font-size: 15px;
      font-weight: 700;
      color: #334155;
      margin: 18px 0 10px;
    }

    /* Navigation Sections Grid (Cards on Dashboard) */
    .admin-sections-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    .section-nav-card {
      background: #ffffff;
      border: 1px solid var(--admin-border);
      border-radius: 12px;
      padding: 22px;
      display: flex;
      flex-direction: column;
      text-decoration: none;
      color: inherit;
      transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
      position: relative;
      overflow: hidden;
    }
    .section-nav-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 24px rgba(0,0,0,0.07);
      border-color: var(--admin-green);
      text-decoration: none;
    }
    .section-nav-card .card-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 12px;
    }
    .section-nav-card .card-icon {
      width: 46px;
      height: 46px;
      border-radius: 10px;
      background: var(--admin-green-light);
      color: var(--admin-green);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      font-weight: 700;
    }
    .section-nav-card .card-badge {
      font-size: 12px;
      font-weight: 700;
      background: #f1f5f9;
      color: #475569;
      padding: 4px 10px;
      border-radius: 20px;
    }
    .section-nav-card h3 {
      margin: 0 0 6px;
      font-size: 17px;
      font-weight: 800;
      color: #0f172a;
    }
    .section-nav-card p {
      margin: 0 0 14px;
      font-size: 13px;
      color: #64748b;
      line-height: 1.45;
      flex-grow: 1;
    }
    .section-nav-card .card-action {
      font-size: 13px;
      font-weight: 700;
      color: var(--admin-green);
      display: flex;
      align-items: center;
      gap: 4px;
    }

    /* Stats Grid */
    .admin-stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
      gap: 20px;
      margin-bottom: 28px;
    }
    .stat-box {
      background: var(--admin-card);
      border: 1px solid var(--admin-border);
      border-radius: 12px;
      padding: 22px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.03);
      display: flex;
      flex-direction: column;
    }
    .stat-box .label { font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--admin-muted); letter-spacing: 0.5px; display: flex; align-items: center; justify-content: space-between; gap: 6px; }
    .stat-box .number { font-size: 26px; font-weight: 800; color: var(--admin-dark); margin: 6px 0 2px; }
    .stat-box .sub { font-size: 12.5px; color: var(--admin-green); font-weight: 600; }
    a.stat-box {
      text-decoration: none;
      color: inherit;
      cursor: pointer;
      position: relative;
      transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
    }
    a.stat-box:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 18px rgba(0,0,0,0.07);
      border-color: #94a3b8;
      text-decoration: none;
    }
    a.stat-box:focus-visible {
      outline: 2px solid var(--admin-green);
      outline-offset: 2px;
    }
    a.stat-box .card-click-hint {
      margin-top: 10px;
      font-size: 11.5px;
      font-weight: 700;
      color: var(--admin-green);
      display: inline-flex;
      align-items: center;
      gap: 4px;
      opacity: 0.9;
    }
    a.stat-box:hover .card-click-hint {
      color: var(--admin-green-dark);
      text-decoration: underline;
    }

    /* Tables */
    .admin-table-wrap { width: 100%; overflow-x: auto; }
    .admin-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13.5px;
      text-align: left;
    }
    .admin-table th {
      background: #f1f5f9;
      padding: 12px 14px;
      border-bottom: 2px solid var(--admin-border);
      color: #475569;
      font-weight: 700;
      white-space: nowrap;
    }
    .admin-table td {
      padding: 14px;
      border-bottom: 1px solid var(--admin-border);
      vertical-align: middle;
    }
    .admin-table tr:hover td { background: #f8fafc; }

    /* Badges */
    .badge {
      display: inline-block;
      padding: 3px 8px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }
    .badge-green  { background: #dcfce7; color: #166534; }
    .badge-blue   { background: #e0f2fe; color: #0369a1; }
    .badge-yellow { background: #fef3c7; color: #92400e; }
    .badge-red    { background: #fee2e2; color: #991b1b; }
    .badge-purple { background: #ede9fe; color: #6d28d9; }
    .badge-gray   { background: #f1f5f9; color: #475569; }

    /* Buttons */
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 9px 18px;
      border-radius: 6px;
      font-size: 13.5px;
      font-weight: 700;
      cursor: pointer;
      border: none;
      transition: background 0.15s, transform 0.1s;
      text-decoration: none;
      white-space: nowrap;
    }
    .btn-primary { background: var(--admin-green); color: #ffffff; }
    .btn-primary:hover { background: var(--admin-green-dark); text-decoration: none; color: #ffffff; }
    .btn-secondary { background: #e2e8f0; color: #334155; }
    .btn-secondary:hover { background: #cbd5e1; text-decoration: none; }
    .btn-danger { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
    .btn-danger:hover { background: #fca5a5; color: #7f1d1d; }
    .btn-warning { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
    .btn-warning:hover { background: #fde68a; }
    .btn-sm { padding: 5px 11px; font-size: 12px; }

    /* Forms */
    .form-group { margin-bottom: 18px; }
    .form-group label { display: block; font-size: 13.5px; font-weight: 700; color: #334155; margin-bottom: 6px; }
    .form-control {
      width: 100%;
      padding: 10px 14px;
      border: 1px solid #cbd5e1;
      border-radius: 6px;
      font-size: 14px;
      color: #1e293b;
      background: #ffffff;
      outline: none;
      box-sizing: border-box;
      transition: border-color 0.15s, box-shadow 0.15s;
    }
    .form-control:focus { border-color: var(--admin-green); box-shadow: 0 0 0 3px rgba(7,142,73,0.15); }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }

    .alert { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; line-height: 1.45; }
    .alert-success { background: #f0fdf4; border: 1px solid #dcfce7; color: #166534; }
    .alert-error { background: #fef2f2; border: 1px solid #fee2e2; color: #991b1b; }

    /* Image Gallery & Uploader Grid */
    .image-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
      gap: 16px;
      margin-top: 14px;
    }
    .image-card {
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      padding: 10px;
      background: #f8fafc;
      position: relative;
      display: flex;
      flex-direction: column;
      align-items: center;
      transition: border-color 0.15s, box-shadow 0.15s;
    }
    .image-card.is-primary {
      border-color: var(--admin-green);
      background: var(--admin-green-light);
    }
    .image-card img {
      width: 130px;
      height: 110px;
      object-fit: contain;
      background: #ffffff;
      border-radius: 6px;
      border: 1px solid #e2e8f0;
      padding: 4px;
      margin-bottom: 10px;
    }
    .image-card .img-actions {
      display: flex;
      gap: 6px;
      margin-top: 8px;
      width: 100%;
      justify-content: center;
    }

    /* Modal dialog */
    .admin-modal {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.65);
      z-index: 500;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .admin-modal.is-open { display: flex; }
    .admin-modal-content {
      background: #ffffff;
      border-radius: 12px;
      max-width: 600px;
      width: 100%;
      max-height: 90vh;
      overflow-y: auto;
      padding: 28px;
      box-shadow: 0 20px 40px rgba(0,0,0,0.25);
    }

    /* Mobile Hamburger Menu Toggle Button */
    .admin-menu-toggle {
      display: none;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      width: 44px;
      height: 44px;
      padding: 10px;
      background: transparent;
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 8px;
      cursor: pointer;
      color: #ffffff;
      gap: 5px;
      transition: background 0.15s, border-color 0.15s;
      flex-shrink: 0;
    }
    .admin-menu-toggle:hover,
    .admin-menu-toggle:focus-visible {
      background: rgba(255, 255, 255, 0.12);
      border-color: var(--admin-green);
      outline: none;
    }
    .admin-menu-toggle .menu-bar {
      display: block;
      width: 22px;
      height: 2px;
      background-color: #ffffff;
      border-radius: 2px;
      transition: transform 0.2s, opacity 0.2s;
    }

    /* Mobile Quick Action Buttons in Navbar */
    .admin-mobile-quick-actions {
      display: none;
      align-items: center;
      gap: 8px;
    }
    .admin-mobile-quick-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 40px;
      height: 40px;
      border-radius: 8px;
      background: rgba(255, 255, 255, 0.08);
      color: #f1f5f9;
      text-decoration: none;
      font-size: 16px;
      border: 1px solid rgba(255, 255, 255, 0.15);
      transition: background 0.15s, border-color 0.15s;
    }
    .admin-mobile-quick-btn:hover {
      background: rgba(255, 255, 255, 0.18);
      text-decoration: none;
    }
    .admin-mobile-quick-logout {
      background: rgba(239, 68, 68, 0.18);
      color: #fca5a5;
      border-color: rgba(239, 68, 68, 0.35);
    }
    .admin-mobile-quick-logout:hover {
      background: #ef4444;
      color: #ffffff;
    }

    /* Mobile Slide-Out Navigation Drawer */
    .admin-drawer-backdrop {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.7);
      backdrop-filter: blur(3px);
      -webkit-backdrop-filter: blur(3px);
      z-index: 998;
      opacity: 0;
      transition: opacity 0.25s ease;
    }
    body.drawer-open .admin-drawer-backdrop {
      display: block;
      opacity: 1;
    }
    .admin-mobile-drawer {
      position: fixed;
      top: 0;
      left: 0;
      bottom: 0;
      width: min(340px, 86vw);
      background: #0f172a;
      color: #ffffff;
      z-index: 999;
      transform: translateX(-100%);
      transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
      display: flex;
      flex-direction: column;
      box-shadow: 6px 0 32px rgba(0, 0, 0, 0.45);
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
      padding-bottom: max(20px, env(safe-area-inset-bottom));
    }
    body.drawer-open .admin-mobile-drawer {
      transform: translateX(0);
    }
    body.drawer-open {
      overflow: hidden !important;
      touch-action: none;
    }

    .drawer-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 16px 20px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      background: #1e293b;
      flex-shrink: 0;
    }
    .drawer-brand {
      font-size: 17px;
      font-weight: 800;
      color: #ffffff;
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .drawer-brand span { color: var(--admin-green); }
    .drawer-close-btn {
      width: 44px;
      height: 44px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: transparent;
      border: 1px solid rgba(255, 255, 255, 0.18);
      border-radius: 8px;
      color: #cbd5e1;
      font-size: 18px;
      cursor: pointer;
      line-height: 1;
      transition: background 0.15s, color 0.15s, border-color 0.15s;
    }
    .drawer-close-btn:hover,
    .drawer-close-btn:focus-visible {
      background: rgba(239, 68, 68, 0.2);
      border-color: #ef4444;
      color: #ffffff;
      outline: none;
    }

    .drawer-user-card {
      margin: 14px 16px;
      padding: 12px 14px;
      background: #1e293b;
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 10px;
      display: flex;
      align-items: center;
      gap: 12px;
      flex-shrink: 0;
    }
    .drawer-user-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: rgba(7, 142, 73, 0.25);
      color: #4ade80;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      flex-shrink: 0;
    }
    .drawer-user-meta strong {
      display: block;
      color: #f8fafc;
      font-size: 14px;
      line-height: 1.2;
    }

    .drawer-nav {
      flex: 1;
      padding: 8px 12px;
      overflow-y: auto;
    }
    .drawer-nav-group-title {
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: #64748b;
      padding: 14px 12px 6px;
    }
    .drawer-nav-link {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 14px;
      min-height: 44px;
      border-radius: 8px;
      color: #cbd5e1;
      text-decoration: none;
      font-size: 14.5px;
      font-weight: 600;
      transition: background 0.15s, color 0.15s;
      margin-bottom: 2px;
    }
    .drawer-nav-link:hover {
      background: rgba(255, 255, 255, 0.08);
      color: #ffffff;
      text-decoration: none;
    }
    .drawer-nav-link.active {
      background: rgba(7, 142, 73, 0.22);
      color: #4ade80;
      border-left: 3px solid var(--admin-green);
    }
    .drawer-nav-link .drawer-icon {
      font-size: 18px;
      width: 22px;
      text-align: center;
      flex-shrink: 0;
    }
    .drawer-footer {
      padding: 14px 16px;
      border-top: 1px solid rgba(255, 255, 255, 0.08);
      background: #1e293b;
      flex-shrink: 0;
    }
    .drawer-logout-btn {
      width: 100%;
      min-height: 44px;
      justify-content: center;
      font-size: 14.5px;
      font-weight: 700;
      display: flex;
    }

    /* Responsive Breakpoints & Viewport Adaptation */
    @media (max-width: 1023px) {
      .admin-navbar {
        height: 60px;
        padding: 0 16px;
      }
      .admin-menu-toggle {
        display: inline-flex;
      }
      .admin-nav-links {
        display: none !important;
      }
      .admin-user-menu {
        display: none !important;
      }
      .admin-mobile-quick-actions {
        display: flex;
      }
    }

    @media (max-width: 768px) {
      .mobile-only { display: block !important; }
      .mobile-only-flex { display: flex !important; }
      .desktop-only, .desktop-only-wrap, .desktop-only-table { display: none !important; }

      .admin-container {
        padding: 18px 14px max(32px, env(safe-area-inset-bottom));
      }
      .admin-page-header {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
        margin-bottom: 20px;
      }
      .admin-page-header h1 {
        font-size: 21px;
        line-height: 1.3;
      }
      .admin-page-header > div:last-child {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        width: 100%;
      }
      .admin-page-header .btn {
        flex: 1 1 auto;
        justify-content: center;
        min-height: 44px;
      }
      .admin-card {
        padding: 16px 14px;
        border-radius: 10px;
        margin-bottom: 16px;
      }
      .admin-card h2 {
        font-size: 16px;
        flex-wrap: wrap;
        gap: 8px;
      }
      .form-row, .form-row-3 {
        grid-template-columns: 1fr !important;
        gap: 12px;
      }
      .form-control {
        font-size: 16px !important; /* Prevents auto-zoom in iOS Safari */
        min-height: 44px;
        padding: 10px 12px;
      }
      .btn {
        min-height: 44px;
        padding: 10px 16px;
        font-size: 14px;
      }
      .btn-sm {
        min-height: 38px;
        padding: 8px 12px;
        font-size: 13px;
      }
      .admin-stats-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 12px;
        margin-bottom: 20px;
      }
      .stat-box {
        padding: 16px 12px;
      }
      .stat-box .number {
        font-size: 22px;
      }
      .admin-sections-grid {
        grid-template-columns: 1fr !important;
        gap: 14px;
      }
      .section-nav-card {
        padding: 16px;
      }
      .admin-modal-content {
        padding: 20px 16px;
        max-height: 92vh;
        border-radius: 10px;
        width: 95vw;
        max-width: 95vw;
      }
      .image-grid {
        grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
        gap: 10px;
      }
      .image-card img {
        width: 100%;
        height: 90px;
      }
    }

    @media (max-width: 480px) {
      .admin-brand {
        font-size: 15px;
      }
      .admin-badge-role {
        display: none; /* Keep navbar clean on small phones */
      }
      .stat-box .label {
        font-size: 11px;
      }
      .stat-box .sub {
        font-size: 11.5px;
      }
    }

    @media (max-width: 360px) {
      .admin-container {
        padding: 12px 10px 24px;
      }
      .admin-stats-grid {
        grid-template-columns: 1fr !important;
      }
      .admin-navbar {
        padding: 0 10px;
      }
    }
  </style>
</head>
<body>

  <header class="admin-navbar">
    <div style="display: flex; align-items: center; gap: 10px;">
      <!-- Mobile Menu Toggle Button -->
      <button type="button" class="admin-menu-toggle" id="adminMenuToggle" aria-label="Open Navigation Menu" aria-expanded="false" aria-controls="adminMobileDrawer">
        <span class="menu-bar"></span>
        <span class="menu-bar"></span>
        <span class="menu-bar"></span>
      </button>

      <a href="<?= e($adminRoot) ?>index.php" class="admin-brand">
        ibolt<span>networks</span> Admin
        <span class="admin-badge-role"><?= e($admin['role'] ?? 'Staff') ?></span>
      </a>
    </div>

    <!-- Desktop Navigation Links -->
    <ul class="admin-nav-links">
      <li><a href="<?= e($adminRoot) ?>index.php" class="<?= (is_admin_active('admin/index.php') || !is_admin_active('/')) ? 'active' : '' ?>">📊 Dashboard</a></li>

      <!-- Homepage & Sections Dropdown -->
      <li>
        <a href="<?= e($adminRoot) ?>homepage/index.php" class="<?= (is_admin_active('homepage') || is_admin_active('sections')) ? 'active' : '' ?>">
          🏠 Homepage &amp; Sections <span style="font-size: 10px;">▼</span>
        </a>
        <div class="admin-dropdown-menu">
          <a href="<?= e($adminRoot) ?>homepage/index.php">
            <span>🏠</span> <strong>All Homepage Products</strong>
          </a>
          <div class="admin-dropdown-divider"></div>
          <a href="<?= e($adminRoot) ?>sections/servers.php">
            <span>▤</span> Servers Section
          </a>
          <a href="<?= e($adminRoot) ?>sections/networking.php">
            <span>🖧</span> Networking Section
          </a>
          <a href="<?= e($adminRoot) ?>sections/components.php">
            <span>▦</span> Components Section
          </a>
          <a href="<?= e($adminRoot) ?>sections/our-products.php">
            <span>🛍️</span> Our Products Section
          </a>
          <a href="<?= e($adminRoot) ?>sections/promotions.php">
            <span>🏷️</span> Featured &amp; Promotions
          </a>
        </div>
      </li>

      <li><a href="<?= e($adminRoot) ?>products/index.php" class="<?= is_admin_active('products') ? 'active' : '' ?>">📦 Products</a></li>
      <li><a href="<?= e($adminRoot) ?>categories/index.php" class="<?= is_admin_active('categories') ? 'active' : '' ?>">📁 Categories</a></li>
      <li><a href="<?= e($adminRoot) ?>brands/index.php" class="<?= is_admin_active('brands') ? 'active' : '' ?>">🏷️ Brands</a></li>
      <li><a href="<?= e($adminRoot) ?>images/index.php" class="<?= is_admin_active('images') ? 'active' : '' ?>">🖼️ Images</a></li>
      <li><a href="<?= e($adminRoot) ?>orders/index.php" class="<?= is_admin_active('orders') ? 'active' : '' ?>">🛒 Orders</a></li>
      <li><a href="<?= e($adminRoot) ?>reports/index.php" class="<?= is_admin_active('reports') ? 'active' : '' ?>">📈 Reports</a></li>
      <li><a href="<?= e($adminRoot) ?>activity/index.php" class="<?= is_admin_active('activity') ? 'active' : '' ?>">🛡️ Audit Logs</a></li>
      <li><a href="<?= e($adminRoot) ?>../index.php" target="_blank" title="View Public Store">Storefront ↗</a></li>
    </ul>

    <!-- Desktop User Menu -->
    <div class="admin-user-menu">
      <div class="admin-user-meta" style="text-align: right;">
        <strong style="display: block; line-height: 1.2;">👤 <?= e($admin['username'] ?? $admin['name'] ?? 'Admin') ?></strong>
        <span style="font-size: 11.5px; opacity: 0.85; display: block; margin-top: 2px;">
          <a href="<?= e($adminRoot) ?>profile.php" style="color: #93c5fd; text-decoration: none;">⚙️ Username &amp; Password</a>
        </span>
      </div>
      <a href="<?= e($adminRoot) ?>logout.php" class="btn-admin-logout">Sign Out</a>
    </div>

    <!-- Mobile Quick Actions in Navbar -->
    <div class="admin-mobile-quick-actions">
      <a href="<?= e($adminRoot) ?>profile.php" class="admin-mobile-quick-btn" title="Staff Profile &amp; Password" aria-label="Staff Profile">⚙️</a>
      <a href="<?= e($adminRoot) ?>logout.php" class="admin-mobile-quick-btn admin-mobile-quick-logout" title="Sign Out" aria-label="Sign Out">🚪</a>
    </div>
  </header>

  <!-- Mobile Drawer Backdrop Overlay -->
  <div class="admin-drawer-backdrop" id="adminDrawerBackdrop" aria-hidden="true"></div>

  <!-- Mobile Navigation Slide-out Drawer -->
  <aside class="admin-mobile-drawer" id="adminMobileDrawer" aria-label="Mobile Navigation Drawer" aria-hidden="true" tabindex="-1">
    <div class="drawer-header">
      <a href="<?= e($adminRoot) ?>index.php" class="drawer-brand">
        ibolt<span>networks</span> Admin
      </a>
      <button type="button" class="drawer-close-btn" id="adminDrawerClose" aria-label="Close navigation menu">
        ✕
      </button>
    </div>

    <div class="drawer-user-card">
      <div class="drawer-user-avatar">👤</div>
      <div class="drawer-user-meta">
        <strong><?= e($admin['username'] ?? $admin['name'] ?? 'Administrator') ?></strong>
        <div style="display: flex; gap: 8px; align-items: center; margin-top: 4px;">
          <span class="admin-badge-role"><?= e($admin['role'] ?? 'Staff') ?></span>
          <a href="<?= e($adminRoot) ?>profile.php" style="font-size: 12px; color: #93c5fd; text-decoration: underline;">Settings ⚙️</a>
        </div>
      </div>
    </div>

    <nav class="drawer-nav">
      <div class="drawer-nav-group-title">Overview</div>
      <a href="<?= e($adminRoot) ?>index.php" class="drawer-nav-link <?= (is_admin_active('admin/index.php') || !is_admin_active('/')) ? 'active' : '' ?>">
        <span class="drawer-icon">📊</span>
        <span>Dashboard</span>
      </a>

      <div class="drawer-nav-group-title">Homepage &amp; Merchandising</div>
      <a href="<?= e($adminRoot) ?>homepage/index.php" class="drawer-nav-link <?= is_admin_active('homepage/index.php') ? 'active' : '' ?>">
        <span class="drawer-icon">🏠</span>
        <span>All Homepage Products</span>
      </a>
      <a href="<?= e($adminRoot) ?>sections/servers.php" class="drawer-nav-link <?= is_admin_active('sections/servers.php') ? 'active' : '' ?>">
        <span class="drawer-icon">▤</span>
        <span>Servers Section</span>
      </a>
      <a href="<?= e($adminRoot) ?>sections/networking.php" class="drawer-nav-link <?= is_admin_active('sections/networking.php') ? 'active' : '' ?>">
        <span class="drawer-icon">🖧</span>
        <span>Networking Section</span>
      </a>
      <a href="<?= e($adminRoot) ?>sections/components.php" class="drawer-nav-link <?= is_admin_active('sections/components.php') ? 'active' : '' ?>">
        <span class="drawer-icon">▦</span>
        <span>Components Section</span>
      </a>
      <a href="<?= e($adminRoot) ?>sections/our-products.php" class="drawer-nav-link <?= is_admin_active('sections/our-products.php') ? 'active' : '' ?>">
        <span class="drawer-icon">🛍️</span>
        <span>Our Products Section</span>
      </a>
      <a href="<?= e($adminRoot) ?>sections/promotions.php" class="drawer-nav-link <?= is_admin_active('sections/promotions.php') ? 'active' : '' ?>">
        <span class="drawer-icon">🏷️</span>
        <span>Featured &amp; Promotions</span>
      </a>

      <div class="drawer-nav-group-title">Catalog &amp; Inventory</div>
      <a href="<?= e($adminRoot) ?>products/index.php" class="drawer-nav-link <?= is_admin_active('products') ? 'active' : '' ?>">
        <span class="drawer-icon">📦</span>
        <span>Products Catalog</span>
      </a>
      <a href="<?= e($adminRoot) ?>categories/index.php" class="drawer-nav-link <?= is_admin_active('categories') ? 'active' : '' ?>">
        <span class="drawer-icon">📁</span>
        <span>Product Categories</span>
      </a>
      <a href="<?= e($adminRoot) ?>brands/index.php" class="drawer-nav-link <?= is_admin_active('brands') ? 'active' : '' ?>">
        <span class="drawer-icon">🏷️</span>
        <span>Hardware Brands</span>
      </a>
      <a href="<?= e($adminRoot) ?>images/index.php" class="drawer-nav-link <?= is_admin_active('images') ? 'active' : '' ?>">
        <span class="drawer-icon">🖼️</span>
        <span>Product Images &amp; Gallery</span>
      </a>

      <div class="drawer-nav-group-title">Operations &amp; Intelligence</div>
      <a href="<?= e($adminRoot) ?>orders/index.php" class="drawer-nav-link <?= is_admin_active('orders') ? 'active' : '' ?>">
        <span class="drawer-icon">🛒</span>
        <span>Customer Orders</span>
      </a>
      <a href="<?= e($adminRoot) ?>reports/index.php" class="drawer-nav-link <?= is_admin_active('reports') ? 'active' : '' ?>">
        <span class="drawer-icon">📈</span>
        <span>Reports &amp; Analytics</span>
      </a>
      <a href="<?= e($adminRoot) ?>activity/index.php" class="drawer-nav-link <?= is_admin_active('activity') ? 'active' : '' ?>">
        <span class="drawer-icon">🛡️</span>
        <span>Security Audit Logs</span>
      </a>

      <div class="drawer-nav-group-title">Public Storefront</div>
      <a href="<?= e($adminRoot) ?>../index.php" target="_blank" class="drawer-nav-link">
        <span class="drawer-icon">🏪</span>
        <span>View Public Storefront ↗</span>
      </a>
    </nav>

    <div class="drawer-footer">
      <a href="<?= e($adminRoot) ?>logout.php" class="btn btn-danger drawer-logout-btn">
        🚪 Sign Out of Admin
      </a>
    </div>
  </aside>
