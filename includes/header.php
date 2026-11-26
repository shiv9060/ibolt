<?php
declare(strict_types=1);

/**
 * Global Header Partial
 * iboltnetworks E-Commerce Platform
 */

// Load core configurations and helpers
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/csrf.php';
require_once __DIR__ . '/../app/helpers/sanitize.php';
require_once __DIR__ . '/../app/helpers/currency.php';

// Ensure secure session is active
init_session();

// Relative root path for assets and page links (defaults to current directory)
$rootPath = $rootPath ?? '';

// Page metadata defaults
$pageTitle = $pageTitle ?? 'iboltnetworks | Servers & Computer Components';
$pageDescription = $pageDescription ?? 'Explore servers, computer components, memory, storage and networking products at iboltnetworks.';

// Compute active cart item count from session
$cartBadgeCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartBadgeCount += (int)($item['quantity'] ?? 1);
    }
}

// User authentication state
$userLoggedIn = is_logged_in();
$currentUser = $userLoggedIn ? current_user() : null;
$userFirstName = $currentUser ? explode(' ', (string)($currentUser['name'] ?? 'Account'))[0] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="<?= e($pageDescription) ?>">
  <link rel="stylesheet" href="<?= e($rootPath) ?>assets/css/style.css">
  <?= $extraHead ?? '' ?>
</head>
<body>

  <!-- Announcement bar -->
  <div class="announcement">
    <p>For Quick Assistance on a Product, Message Us in the Live Chat (24x7).</p>
    <a class="announcement-close" href="#" aria-label="Close announcement">×</a>
  </div>

  <!-- Main header -->
  <header class="main-header">
    <div class="header-inner">
      <a class="brand" href="<?= e($rootPath) ?>index.php" aria-label="iboltnetworks home">
        <span class="brand-mark">iB</span>
        <span class="brand-copy">
          <strong>ibolt<span>networks</span></strong>
          <small>Powering your technology</small>
        </span>
      </a>

      <form class="search-form" action="<?= e($rootPath) ?>products.php" method="get">
        <label class="visually-hidden" for="search-category">Product category</label>
        <select id="search-category" name="category">
          <option value="">All</option>
          <option value="servers" <?= (($_GET['category'] ?? '') === 'servers') ? 'selected' : '' ?>>Servers</option>
          <option value="networking" <?= (($_GET['category'] ?? '') === 'networking') ? 'selected' : '' ?>>Networking</option>
          <option value="components" <?= (($_GET['category'] ?? '') === 'components') ? 'selected' : '' ?>>Components</option>
          <option value="storage-components" <?= (($_GET['category'] ?? '') === 'storage-components') ? 'selected' : '' ?>>Storage</option>
        </select>
        <label class="visually-hidden" for="site-search">Search products</label>
        <input id="site-search" type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="I'm shopping for...">
        <button type="submit">Search</button>
      </form>

      <a class="header-contact" href="tel:+919911003865" style="text-decoration:none; color:inherit;" title="Call iboltnetworks at +91 99110 03865">
        <span class="phone-icon" aria-hidden="true">♧</span>
        <span><small>Hotline / WhatsApp</small><strong>+91 99110 03865</strong></span>
      </a>

      <a class="header-icon cart-link" href="<?= e($rootPath) ?>cart.php" aria-label="Shopping cart">
        <span aria-hidden="true">♧</span><b id="cart-count"><?= $cartBadgeCount ?></b>
      </a>

      <?php if ($userLoggedIn): ?>
        <a class="account-link" href="<?= e($rootPath) ?>account/index.php" title="My Account">
          <span class="user-icon" aria-hidden="true">♙</span>
          <strong>My Account<br><small style="color: #c9eed7; font-size: 11px; font-weight: normal;"><?= e($userFirstName) ?></small></strong>
        </a>
      <?php else: ?>
        <a class="account-link" href="<?= e($rootPath) ?>login.php">
          <span class="user-icon" aria-hidden="true">♙</span>
          <strong>Log in<br>Register</strong>
        </a>
      <?php endif; ?>
    </div>
  </header>
