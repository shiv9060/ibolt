/**
 * iboltnetworks - OUR PRODUCTS Demonstration Data Layer
 * 
 * NOTE: This file contains temporary frontend-only demonstration data.
 * No MySQL database or PHP backend is connected in this phase.
 * The data structure and query methods are designed to mirror future REST/JSON
 * API responses from a PHP/MySQL backend (e.g. GET /api/products.php).
 */
(function (root) {
  'use strict';

  // 1. Column & Group Definitions
  const GROUPS = [
    {
      slug: 'computer-accessories',
      title: 'Computer Accessories',
      column: 1,
      description: 'Essential desktop input peripherals, hubs, webcams, and accessories.'
    },
    {
      slug: 'laptop-accessories',
      title: 'Laptop Accessories',
      column: 2,
      description: 'Ergonomic stands, power adapters, bags, and multi-display docking stations.'
    },
    {
      slug: 'monitors-displays',
      title: 'Monitors & Displays',
      column: 3,
      description: 'High-resolution business, gaming, and creator LED displays and mounting arms.'
    },
    {
      slug: 'storage-components',
      title: 'Storage & Components',
      column: 4,
      description: 'High-speed NVMe/SATA internal and external SSDs, hard drives, RAM, and graphics cards.'
    },
    {
      slug: 'cables-networking',
      title: 'Cables & Networking',
      column: 5,
      description: 'Certified 8K HDMI, Cat6/7 Ethernet, USB-C, and high-speed network/Wi-Fi adapters.'
    }
  ];

  // 2. Demonstration Categories (25 Subcategories across the 5 Columns)
  const CATEGORIES = {
    'our-products': {
      slug: 'our-products',
      title: 'Our Products (Demonstration)',
      group: null,
      groupTitle: 'All Categories',
      description: 'Browse all demonstration accessories, displays, storage, and networking hardware.'
    },
    // Column 1 — Computer Accessories
    'computer-accessories': {
      slug: 'computer-accessories',
      title: 'Computer Accessories',
      group: 'computer-accessories',
      groupTitle: 'Computer Accessories',
      description: 'Keyboards, precision mice, mouse pads, high-definition webcams, and USB hubs.'
    },
    'keyboards': {
      slug: 'keyboards',
      title: 'Keyboards',
      group: 'computer-accessories',
      groupTitle: 'Computer Accessories',
      description: 'Mechanical, wireless, and ergonomic keyboards for typing and productivity.'
    },
    'computer-mice': {
      slug: 'computer-mice',
      title: 'Computer Mice',
      group: 'computer-accessories',
      groupTitle: 'Computer Accessories',
      description: 'Ergonomic optical, high-DPI laser, and wireless productivity mice.'
    },
    'mouse-pads': {
      slug: 'mouse-pads',
      title: 'Mouse Pads',
      group: 'computer-accessories',
      groupTitle: 'Computer Accessories',
      description: 'Precision cloth, extended desk mats, and anti-slip stitched mouse surfaces.'
    },
    'webcams': {
      slug: 'webcams',
      title: 'Webcams',
      group: 'computer-accessories',
      groupTitle: 'Computer Accessories',
      description: 'Full HD and 4K conference webcams with noise-cancelling dual microphones.'
    },
    'usb-hubs': {
      slug: 'usb-hubs',
      title: 'USB Hubs',
      group: 'computer-accessories',
      groupTitle: 'Computer Accessories',
      description: 'Multi-port USB 3.2 and Type-C powered expansion hubs.'
    },

    // Column 2 — Laptop Accessories
    'laptop-accessories': {
      slug: 'laptop-accessories',
      title: 'Laptop Accessories',
      group: 'laptop-accessories',
      groupTitle: 'Laptop Accessories',
      description: 'Chargers, travel bags, cooling pads, ergonomic risers, and docking solutions.'
    },
    'laptop-chargers': {
      slug: 'laptop-chargers',
      title: 'Laptop Chargers',
      group: 'laptop-accessories',
      groupTitle: 'Laptop Accessories',
      description: 'GaN fast charging USB-C and universal OEM-compatible power adapters.'
    },
    'laptop-bags': {
      slug: 'laptop-bags',
      title: 'Laptop Bags',
      group: 'laptop-accessories',
      groupTitle: 'Laptop Accessories',
      description: 'Padded shockproof sleeves, executive briefcases, and travel backpacks.'
    },
    'cooling-pads': {
      slug: 'cooling-pads',
      title: 'Cooling Pads',
      group: 'laptop-accessories',
      groupTitle: 'Laptop Accessories',
      description: 'Multi-fan aluminum cooling pads for thermal regulation and heat dissipation.'
    },
    'laptop-stands': {
      slug: 'laptop-stands',
      title: 'Laptop Stands',
      group: 'laptop-accessories',
      groupTitle: 'Laptop Accessories',
      description: 'Adjustable aluminum ergonomic desk risers and folding portable stands.'
    },
    'docking-stations': {
      slug: 'docking-stations',
      title: 'Docking Stations',
      group: 'laptop-accessories',
      groupTitle: 'Laptop Accessories',
      description: 'Thunderbolt 4 and USB-C triple display docking hubs with Power Delivery.'
    },

    // Column 3 — Monitors & Displays
    'monitors-displays': {
      slug: 'monitors-displays',
      title: 'Monitors & Displays',
      group: 'monitors-displays',
      groupTitle: 'Monitors & Displays',
      description: 'High-refresh monitors, desk arm mounts, active display converters, and cables.'
    },
    'led-monitors': {
      slug: 'led-monitors',
      title: 'LED Monitors',
      group: 'monitors-displays',
      groupTitle: 'Monitors & Displays',
      description: 'IPS panel Full HD and 4K UHD monitors for office and design productivity.'
    },
    'gaming-monitors': {
      slug: 'gaming-monitors',
      title: 'Gaming Monitors',
      group: 'monitors-displays',
      groupTitle: 'Monitors & Displays',
      description: 'High refresh rate 165Hz–240Hz curved and flat gaming panels with 1ms response.'
    },
    'monitor-stands': {
      slug: 'monitor-stands',
      title: 'Monitor Stands',
      group: 'monitors-displays',
      groupTitle: 'Monitors & Displays',
      description: 'Gas spring single and dual monitor desk arms with VESA compatibility.'
    },
    'display-adapters': {
      slug: 'display-adapters',
      title: 'Display Adapters',
      group: 'monitors-displays',
      groupTitle: 'Monitors & Displays',
      description: 'Type-C to DisplayPort, HDMI, VGA, and active video signal converters.'
    },
    'hdmi-cables-display': {
      slug: 'hdmi-cables-display',
      title: 'HDMI Cables',
      group: 'monitors-displays',
      groupTitle: 'Monitors & Displays',
      description: 'Ultra high speed 8K/4K HDMI cables with gold-plated connectors.'
    },

    // Column 4 — Storage & Components
    'storage-components': {
      slug: 'storage-components',
      title: 'Storage & Components',
      group: 'storage-components',
      groupTitle: 'Storage & Components',
      description: 'Internal & external solid state drives, mechanical drives, RAM kits, and GPUs.'
    },
    'internal-ssds': {
      slug: 'internal-ssds',
      title: 'Internal SSDs',
      group: 'storage-components',
      groupTitle: 'Storage & Components',
      description: 'M.2 NVMe PCIe Gen4/Gen5 and 2.5-inch SATA internal SSDs.'
    },
    'external-ssds': {
      slug: 'external-ssds',
      title: 'External SSDs',
      group: 'storage-components',
      groupTitle: 'Storage & Components',
      description: 'Rugged portable high-speed USB 3.2 Gen2 solid state backup drives.'
    },
    'hard-drives': {
      slug: 'hard-drives',
      title: 'Hard Drives',
      group: 'storage-components',
      groupTitle: 'Storage & Components',
      description: 'High-capacity 3.5-inch SATA desktop and NAS hard disk drives.'
    },
    'ram-modules': {
      slug: 'ram-modules',
      title: 'RAM Modules',
      group: 'storage-components',
      groupTitle: 'Storage & Components',
      description: 'High-frequency DDR4 and DDR5 desktop memory kits with heatsinks.'
    },
    'graphics-cards': {
      slug: 'graphics-cards',
      title: 'Graphics Cards',
      group: 'storage-components',
      groupTitle: 'Storage & Components',
      description: 'Dedicated workstation and gaming GPUs for rendering and 3D computation.'
    },

    // Column 5 — Cables & Networking
    'cables-networking': {
      slug: 'cables-networking',
      title: 'Cables & Networking',
      group: 'cables-networking',
      groupTitle: 'Cables & Networking',
      description: 'Network patch cords, USB-C sync cables, and high-speed Wi-Fi/NIC cards.'
    },
    'usb-cables': {
      slug: 'usb-cables',
      title: 'USB Cables',
      group: 'cables-networking',
      groupTitle: 'Cables & Networking',
      description: 'Durable nylon-braided USB-C to USB-C 100W PD and USB 3.0 cables.'
    },
    'hdmi-cables': {
      slug: 'hdmi-cables',
      title: 'HDMI Cables',
      group: 'cables-networking',
      groupTitle: 'Cables & Networking',
      description: 'Certified 8K 60Hz / 4K 120Hz HDMI 2.1 premium braided audio/video cables.'
    },
    'ethernet-cables': {
      slug: 'ethernet-cables',
      title: 'Ethernet Cables',
      group: 'cables-networking',
      groupTitle: 'Cables & Networking',
      description: 'Shielded Cat 6 and Cat 7 RJ45 gigabit and 10-gigabit patch cords.'
    },
    'network-adapters': {
      slug: 'network-adapters',
      title: 'Network Adapters',
      group: 'cables-networking',
      groupTitle: 'Cables & Networking',
      description: 'USB-to-Gigabit Ethernet and PCIe multi-gigabit LAN interface adapters.'
    },
    'wifi-adapters': {
      slug: 'wifi-adapters',
      title: 'Wi-Fi Adapters',
      group: 'cables-networking',
      groupTitle: 'Cables & Networking',
      description: 'Wi-Fi 6 and 6E dual-band USB and PCIe desktop adapters with high-gain antennas.'
    }
  };

  // 3. Demonstration Product Items (36 Sample Items)
  // NOTE: mouse-pads and cooling-pads are intentionally left with 0 items
  // to satisfy the Acceptance Criteria for empty-category state demonstration!
  const SAMPLE_PRODUCTS = [
    // --- Column 1: Keyboards ---
    {
      id: 'demo-kbd-01',
      name: 'Pro Mechanical RGB Gaming Keyboard [Demo]',
      slug: 'pro-mechanical-rgb-gaming-keyboard',
      category: 'keyboards',
      categoryName: 'Keyboards',
      group: 'computer-accessories',
      groupName: 'Computer Accessories',
      price: 2999,
      demoPriceText: '₹2,999',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: 'Tactile Blue Switches • Per-Key RGB Lighting • Detachable USB-C Braided Cable • Anti-Ghosting',
      image: 'assets/images/placeholder-keyboard.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },
    {
      id: 'demo-kbd-02',
      name: 'Ultra-Slim Wireless Office Keyboard [Demo]',
      slug: 'ultra-slim-wireless-office-keyboard',
      category: 'keyboards',
      categoryName: 'Keyboards',
      group: 'computer-accessories',
      groupName: 'Computer Accessories',
      price: 1899,
      demoPriceText: '₹1,899',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: 'Scissor-Switch Silent Keys • Dual Bluetooth 5.0 + 2.4GHz • Rechargeable 500mAh Battery • Multi-OS',
      image: 'assets/images/placeholder-keyboard.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },
    {
      id: 'demo-kbd-03',
      name: 'Tenkeyless Custom Hot-Swap Keyboard [Demo]',
      slug: 'tenkeyless-custom-hot-swap-keyboard',
      category: 'keyboards',
      categoryName: 'Keyboards',
      group: 'computer-accessories',
      groupName: 'Computer Accessories',
      price: 3499,
      demoPriceText: '₹3,499',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: 'Hot-Swappable 5-Pin PCB • Sound Dampening Foam • Double-Shot PBT Keycaps • Mac/Win Layout',
      image: 'assets/images/placeholder-keyboard.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },

    // --- Column 1: Computer Mice ---
    {
      id: 'demo-mse-01',
      name: 'Precision Wireless Optical Mouse [Demo]',
      slug: 'precision-wireless-optical-mouse',
      category: 'computer-mice',
      categoryName: 'Computer Mice',
      group: 'computer-accessories',
      groupName: 'Computer Accessories',
      price: 899,
      demoPriceText: '₹899',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: '2400 DPI Adjustable Sensor • 2.4GHz Nano Receiver • Silent Click Buttons • 18-Month Battery Life',
      image: 'assets/images/placeholder-mouse.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },
    {
      id: 'demo-mse-02',
      name: 'Ergonomic Vertical Wireless Mouse [Demo]',
      slug: 'ergonomic-vertical-wireless-mouse',
      category: 'computer-mice',
      categoryName: 'Computer Mice',
      group: 'computer-accessories',
      groupName: 'Computer Accessories',
      price: 1499,
      demoPriceText: '₹1,499',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: '57° Natural Handshake Angle • Prevents Wrist Strain • Thumb Forward/Back Buttons • USB-C Charging',
      image: 'assets/images/placeholder-mouse.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },
    {
      id: 'demo-mse-03',
      name: 'Pro Esports RGB 16000-DPI Mouse [Demo]',
      slug: 'pro-esports-rgb-16000-dpi-mouse',
      category: 'computer-mice',
      categoryName: 'Computer Mice',
      group: 'computer-accessories',
      groupName: 'Computer Accessories',
      price: 2199,
      demoPriceText: '₹2,199',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: 'PixArt PMW3389 Sensor • 68g Ultralight Honeycomb Shell • 50M Click Optical Switches • Paracord Cable',
      image: 'assets/images/placeholder-mouse.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },

    // --- Column 1: Webcams ---
    {
      id: 'demo-cam-01',
      name: 'Full HD 1080p Streaming Webcam [Demo]',
      slug: 'full-hd-1080p-streaming-webcam',
      category: 'webcams',
      categoryName: 'Webcams',
      group: 'computer-accessories',
      groupName: 'Computer Accessories',
      price: 2499,
      demoPriceText: '₹2,499',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: '1080p @ 60FPS • Built-in Privacy Cover • Dual Noise-Cancelling Mics • Auto Low-Light Correction',
      image: 'assets/images/placeholder-webcam.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },
    {
      id: 'demo-cam-02',
      name: '4K Ultra HD Conference Webcam [Demo]',
      slug: '4k-ultra-hd-conference-webcam',
      category: 'webcams',
      categoryName: 'Webcams',
      group: 'computer-accessories',
      groupName: 'Computer Accessories',
      price: 6999,
      demoPriceText: '₹6,999',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: 'Sony 4K Sensor • AI Auto-Framing & Face Tracking • Stereo Beamforming Microphones • Tripod Included',
      image: 'assets/images/placeholder-webcam.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },

    // --- Column 1: USB Hubs ---
    {
      id: 'demo-hub-01',
      name: '7-Port Powered USB 3.0 Hub [Demo]',
      slug: '7-port-powered-usb-3-0-hub',
      category: 'usb-hubs',
      categoryName: 'USB Hubs',
      group: 'computer-accessories',
      groupName: 'Computer Accessories',
      price: 1799,
      demoPriceText: '₹1,799',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: '7x USB 3.0 5Gbps Ports • Individual LED Power Switches • 12V External Power Adapter • Surge Protection',
      image: 'assets/images/placeholder-generic.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },
    {
      id: 'demo-hub-02',
      name: 'Aluminum 4-Port USB-C to USB-A Hub [Demo]',
      slug: 'aluminum-4-port-usb-c-to-usb-a-hub',
      category: 'usb-hubs',
      categoryName: 'USB Hubs',
      group: 'computer-accessories',
      groupName: 'Computer Accessories',
      price: 1199,
      demoPriceText: '₹1,199',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: '4x USB 3.1 Gen 1 Ports • Compact Pocket Design • Heat-Dissipating Aluminum Shell • Plug & Play',
      image: 'assets/images/placeholder-generic.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },

    // --- Column 2: Laptop Chargers ---
    {
      id: 'demo-chg-01',
      name: '65W USB-C GaN Fast Laptop Charger [Demo]',
      slug: '65w-usb-c-gan-fast-laptop-charger',
      category: 'laptop-chargers',
      categoryName: 'Laptop Chargers',
      group: 'laptop-accessories',
      groupName: 'Laptop Accessories',
      price: 1999,
      demoPriceText: '₹1,999',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: 'Gallium Nitride (GaN III) Tech • Power Delivery 3.0 • Compact Folding Plug • Universal Dell/HP/MacBook',
      image: 'assets/images/placeholder-laptop.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },
    {
      id: 'demo-chg-02',
      name: '100W Dual USB-C + USB-A GaN Charger [Demo]',
      slug: '100w-dual-usb-c-gan-charger',
      category: 'laptop-chargers',
      categoryName: 'Laptop Chargers',
      group: 'laptop-accessories',
      groupName: 'Laptop Accessories',
      price: 2899,
      demoPriceText: '₹2,899',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: '100W Max Single Port Output • 3-Port Multi-Device Simultaneous Fast Charging • Smart Temp Monitoring',
      image: 'assets/images/placeholder-laptop.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },

    // --- Column 2: Laptop Bags ---
    {
      id: 'demo-bag-01',
      name: 'Water-Resistant 15.6" Executive Backpack [Demo]',
      slug: 'water-resistant-15-6-executive-backpack',
      category: 'laptop-bags',
      categoryName: 'Laptop Bags',
      group: 'laptop-accessories',
      groupName: 'Laptop Accessories',
      price: 2299,
      demoPriceText: '₹2,299',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: 'High-Density Oxford Fabric • Shockproof 360° Padded Laptop Sleeve • Anti-Theft Hidden Pocket • USB Port',
      image: 'assets/images/placeholder-laptop.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },
    {
      id: 'demo-bag-02',
      name: 'Slim Shockproof 14" Laptop Sleeve [Demo]',
      slug: 'slim-shockproof-14-laptop-sleeve',
      category: 'laptop-bags',
      categoryName: 'Laptop Bags',
      group: 'laptop-accessories',
      groupName: 'Laptop Accessories',
      price: 999,
      demoPriceText: '₹999',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: 'Spill-Resistant Neoprene Shell • Soft Velvet Scratch Protection Lining • Front Accessory Zipper Pocket',
      image: 'assets/images/placeholder-laptop.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },

    // --- Column 2: Laptop Stands ---
    {
      id: 'demo-stn-01',
      name: 'Ergonomic Adjustable Aluminum Laptop Stand [Demo]',
      slug: 'ergonomic-adjustable-aluminum-laptop-stand',
      category: 'laptop-stands',
      categoryName: 'Laptop Stands',
      group: 'laptop-accessories',
      groupName: 'Laptop Accessories',
      price: 1499,
      demoPriceText: '₹1,499',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: '6-Level Height & Angle Adjustment • Solid Anodized Aluminum Alloy • Silicone Anti-Scratch Pads',
      image: 'assets/images/placeholder-laptop.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },
    {
      id: 'demo-stn-02',
      name: '360° Swivel Heavy-Duty Laptop Desk Riser [Demo]',
      slug: '360-swivel-heavy-duty-laptop-desk-riser',
      category: 'laptop-stands',
      categoryName: 'Laptop Stands',
      group: 'laptop-accessories',
      groupName: 'Laptop Accessories',
      price: 1899,
      demoPriceText: '₹1,899',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: 'Smooth 360° Rotating Turntable Base • Holds up to 17" Laptops • Open Ventilation Cooling Cutout',
      image: 'assets/images/placeholder-laptop.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },

    // --- Column 2: Docking Stations ---
    {
      id: 'demo-dck-01',
      name: '12-in-1 Triple Display USB-C Docking Station [Demo]',
      slug: '12-in-1-triple-display-usb-c-docking-station',
      category: 'docking-stations',
      categoryName: 'Docking Stations',
      group: 'laptop-accessories',
      groupName: 'Laptop Accessories',
      price: 8499,
      demoPriceText: '₹8,499',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: 'Dual 4K HDMI + DisplayPort • 100W Power Delivery Pass-through • Gigabit LAN • SD/TF Card Reader',
      image: 'assets/images/placeholder-laptop.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },
    {
      id: 'demo-dck-02',
      name: 'Thunderbolt 4 Quad-Display Workstation Dock [Demo]',
      slug: 'thunderbolt-4-quad-display-workstation-dock',
      category: 'docking-stations',
      categoryName: 'Docking Stations',
      group: 'laptop-accessories',
      groupName: 'Laptop Accessories',
      price: 18999,
      demoPriceText: '₹18,999',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: '40Gbps High-Speed Bandwidth • 4x 4K 60Hz or 1x 8K Display • 96W Host Charging • 2.5G Ethernet Port',
      image: 'assets/images/placeholder-laptop.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },

    // --- Column 3: LED Monitors ---
    {
      id: 'demo-mon-01',
      name: '27" IPS Full HD Ultra-Slim Bezel Monitor [Demo]',
      slug: '27-ips-full-hd-ultra-slim-bezel-monitor',
      category: 'led-monitors',
      categoryName: 'LED Monitors',
      group: 'monitors-displays',
      groupName: 'Monitors & Displays',
      price: 11499,
      demoPriceText: '₹11,499',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: '1920x1080 Full HD IPS • 75Hz Refresh • 99% sRGB Color Gamut • Eye Care Flicker-Free • HDMI & VGA',
      image: 'assets/images/placeholder-monitor.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },
    {
      id: 'demo-mon-02',
      name: '24" Business Eye-Care LED Desktop Monitor [Demo]',
      slug: '24-business-eye-care-led-desktop-monitor',
      category: 'led-monitors',
      categoryName: 'LED Monitors',
      group: 'monitors-displays',
      groupName: 'Monitors & Displays',
      price: 8999,
      demoPriceText: '₹8,999',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: 'Anti-Glare Matt Display • Low Blue Light Filter • Built-in Speakers • VESA 100x100 Wall Mountable',
      image: 'assets/images/placeholder-monitor.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },

    // --- Column 3: Gaming Monitors ---
    {
      id: 'demo-mon-03',
      name: '32" Curved QHD 165Hz HDR Gaming Monitor [Demo]',
      slug: '32-curved-qhd-165hz-hdr-gaming-monitor',
      category: 'gaming-monitors',
      categoryName: 'Gaming Monitors',
      group: 'monitors-displays',
      groupName: 'Monitors & Displays',
      price: 24999,
      demoPriceText: '₹24,999',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: '2560x1440 2K Resolution • 1500R Curvature • 165Hz Refresh Rate • 1ms MPRT • FreeSync & G-Sync',
      image: 'assets/images/placeholder-monitor.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },
    {
      id: 'demo-mon-04',
      name: '27" 240Hz Fast-IPS Esports Gaming Monitor [Demo]',
      slug: '27-240hz-fast-ips-esports-gaming-monitor',
      category: 'gaming-monitors',
      categoryName: 'Gaming Monitors',
      group: 'monitors-displays',
      groupName: 'Monitors & Displays',
      price: 27499,
      demoPriceText: '₹27,499',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: '0.5ms GtG Response Time • HDR400 Display • Ergonomic Height Swivel Tilt Stand • RGB Backglow',
      image: 'assets/images/placeholder-monitor.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },

    // --- Column 3: Monitor Stands ---
    {
      id: 'demo-mst-01',
      name: 'Gas Spring Single Monitor Desk Arm Mount [Demo]',
      slug: 'gas-spring-single-monitor-desk-arm-mount',
      category: 'monitor-stands',
      categoryName: 'Monitor Stands',
      group: 'monitors-displays',
      groupName: 'Monitors & Displays',
      price: 2799,
      demoPriceText: '₹2,799',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: 'Supports 17"–32" Screens (Up to 9kg) • 360° Rotation • C-Clamp & Grommet Base • Cable Channel',
      image: 'assets/images/placeholder-generic.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },
    {
      id: 'demo-mst-02',
      name: 'Heavy-Duty Dual Monitor Desk Arm [Demo]',
      slug: 'heavy-duty-dual-monitor-desk-arm',
      category: 'monitor-stands',
      categoryName: 'Monitor Stands',
      group: 'monitors-displays',
      groupName: 'Monitors & Displays',
      price: 4499,
      demoPriceText: '₹4,499',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: 'Holds Two 32" Monitors Independently • Aerospace Aluminum Joints • VESA 75/100 Quick-Release',
      image: 'assets/images/placeholder-generic.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },

    // --- Column 3: Display Adapters ---
    {
      id: 'demo-dad-01',
      name: 'USB-C to 4K 60Hz DisplayPort Adapter [Demo]',
      slug: 'usb-c-to-4k-60hz-displayport-adapter',
      category: 'display-adapters',
      categoryName: 'Display Adapters',
      group: 'monitors-displays',
      groupName: 'Monitors & Displays',
      price: 999,
      demoPriceText: '₹999',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: 'Supports 4K @ 60Hz / 2K @ 144Hz • Unidirectional USB-C Host to DP • Braided Cable • Aluminum Shell',
      image: 'assets/images/placeholder-cable.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },
    {
      id: 'demo-dad-02',
      name: 'Active DisplayPort to HDMI 2.0 Adapter [Demo]',
      slug: 'active-displayport-to-hdmi-2-0-adapter',
      category: 'display-adapters',
      categoryName: 'Display Adapters',
      group: 'monitors-displays',
      groupName: 'Monitors & Displays',
      price: 799,
      demoPriceText: '₹799',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: 'Active Signal Conversion • 4K @ 60Hz with Audio • Eyefinity Multi-Screen Ready • Gold Plated',
      image: 'assets/images/placeholder-cable.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },

    // --- Column 3 & 5: HDMI Cables ---
    {
      id: 'demo-hdm-01',
      name: 'Certified Ultra High Speed 8K HDMI 2.1 Cable 2m [Demo]',
      slug: 'certified-ultra-high-speed-8k-hdmi-2-1-cable',
      category: 'hdmi-cables',
      categoryName: 'HDMI Cables',
      group: 'cables-networking',
      groupName: 'Cables & Networking',
      price: 699,
      demoPriceText: '₹699',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: '48Gbps Bandwidth • 8K @ 60Hz / 4K @ 120Hz • eARC, VRR, ALLM • 24K Gold Plated Contacts',
      image: 'assets/images/placeholder-cable.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },
    {
      id: 'demo-hdm-02',
      name: 'Premium High-Speed 4K HDMI 2.0 Cable 3m [Demo]',
      slug: 'premium-high-speed-4k-hdmi-2-0-cable',
      category: 'hdmi-cables-display',
      categoryName: 'HDMI Cables',
      group: 'monitors-displays',
      groupName: 'Monitors & Displays',
      price: 499,
      demoPriceText: '₹499',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: '18Gbps Speed • 4K @ 60Hz HDR • Triple Shielded Interference Protection • Durable Cotton Braid',
      image: 'assets/images/placeholder-cable.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },

    // --- Column 4: Internal SSDs ---
    {
      id: 'demo-ssd-01',
      name: 'PCIe 4.0 NVMe M.2 1TB High-Speed Internal SSD [Demo]',
      slug: 'pcie-4-0-nvme-m-2-1tb-high-speed-internal-ssd',
      category: 'internal-ssds',
      categoryName: 'Internal SSDs',
      group: 'storage-components',
      groupName: 'Storage & Components',
      price: 6499,
      demoPriceText: '₹6,499',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: 'Up to 7,450 MB/s Read / 6,900 MB/s Write • 3D TLC NAND • DRAM Cache Buffer • 5-Year Endurance',
      image: 'assets/images/placeholder-ssd.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },
    {
      id: 'demo-ssd-02',
      name: '2.5" SATA III 500GB Internal Solid State Drive [Demo]',
      slug: '2-5-sata-iii-500gb-internal-ssd',
      category: 'internal-ssds',
      categoryName: 'Internal SSDs',
      group: 'storage-components',
      groupName: 'Storage & Components',
      price: 3199,
      demoPriceText: '₹3,199',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: '560 MB/s Read / 530 MB/s Write • 7mm Aluminum Casing • TRIM & S.M.A.R.T. Supported • SATA 6Gb/s',
      image: 'assets/images/placeholder-ssd.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },

    // --- Column 4: External SSDs ---
    {
      id: 'demo-esd-01',
      name: 'Rugged Portable USB 3.2 1TB External SSD [Demo]',
      slug: 'rugged-portable-usb-3-2-1tb-external-ssd',
      category: 'external-ssds',
      categoryName: 'External SSDs',
      group: 'storage-components',
      groupName: 'Storage & Components',
      price: 7999,
      demoPriceText: '₹7,999',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: '1,050 MB/s High Speed • IP65 Dust and Water Resistant • Drop-resistant Rubber Bumper • Type-C',
      image: 'assets/images/placeholder-ssd.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },

    // --- Column 4: Hard Drives ---
    {
      id: 'demo-hdd-01',
      name: '4TB 5400RPM 3.5" SATA Desktop Hard Drive [Demo]',
      slug: '4tb-5400rpm-3-5-sata-desktop-hard-drive',
      category: 'hard-drives',
      categoryName: 'Hard Drives',
      group: 'storage-components',
      groupName: 'Storage & Components',
      price: 7499,
      demoPriceText: '₹7,499',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: '4TB Storage Capacity • 256MB Cache • SATA 6Gb/s Interface • Whisper-Quiet Operation • 1M Hours MTBF',
      image: 'assets/images/placeholder-generic.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },

    // --- Column 4: RAM Modules ---
    {
      id: 'demo-ram-01',
      name: '16GB DDR4 3200MHz Desktop RAM Module [Demo]',
      slug: '16gb-ddr4-3200mhz-desktop-ram-module',
      category: 'ram-modules',
      categoryName: 'RAM Modules',
      group: 'storage-components',
      groupName: 'Storage & Components',
      price: 3299,
      demoPriceText: '₹3,299',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: 'CL16 Latency • 1.35V XMP 2.0 Auto Overclocking • Black Aluminum Heat Spreader • 288-Pin UDIMM',
      image: 'images/ddr4-server-ram.png',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },
    {
      id: 'demo-ram-02',
      name: '32GB (2x16GB) DDR5 5600MHz High-Performance RAM Kit [Demo]',
      slug: '32gb-ddr5-5600mhz-ram-kit',
      category: 'ram-modules',
      categoryName: 'RAM Modules',
      group: 'storage-components',
      groupName: 'Storage & Components',
      price: 8999,
      demoPriceText: '₹8,999',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: 'Intel XMP 3.0 & AMD EXPO Ready • On-Die ECC Error Correction • PMIC Power Efficiency • CL36',
      image: 'images/ddr4-server-ram.png',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },

    // --- Column 4: Graphics Cards ---
    {
      id: 'demo-gpu-01',
      name: 'GeForce RTX 4060 8GB GDDR6 Graphics Card [Demo]',
      slug: 'geforce-rtx-4060-8gb-gddr6-graphics-card',
      category: 'graphics-cards',
      categoryName: 'Graphics Cards',
      group: 'storage-components',
      groupName: 'Storage & Components',
      price: 28999,
      demoPriceText: '₹28,999',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: 'Ada Lovelace Architecture • DLSS 3 Frame Generation • 3x DisplayPort 1.4a + 1x HDMI 2.1a • Dual Fan',
      image: 'assets/images/placeholder-generic.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },

    // --- Column 5: USB Cables ---
    {
      id: 'demo-usb-01',
      name: 'Braided USB-C to USB-C 100W PD Cable 2m [Demo]',
      slug: 'braided-usb-c-to-usb-c-100w-pd-cable',
      category: 'usb-cables',
      categoryName: 'USB Cables',
      group: 'cables-networking',
      groupName: 'Cables & Networking',
      price: 499,
      demoPriceText: '₹499',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: 'E-Marker Chip for 100W (20V/5A) Fast Charging • 480Mbps Data Sync • 20,000+ Bend Tested • Nylon Braided',
      image: 'assets/images/placeholder-cable.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },
    {
      id: 'demo-usb-02',
      name: 'Heavy-Duty USB 3.0 to Type-C Sync Cable 1.5m [Demo]',
      slug: 'heavy-duty-usb-3-0-to-type-c-cable',
      category: 'usb-cables',
      categoryName: 'USB Cables',
      group: 'cables-networking',
      groupName: 'Cables & Networking',
      price: 349,
      demoPriceText: '₹349',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: '5Gbps SuperSpeed Data Transfer • 3A Fast Charging Support • Aluminum Shielding • Tangle-Free',
      image: 'assets/images/placeholder-cable.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },

    // --- Column 5: Ethernet Cables ---
    {
      id: 'demo-eth-01',
      name: 'Cat 6 Shielded RJ45 Gigabit Ethernet Patch Cord 5m [Demo]',
      slug: 'cat-6-shielded-gigabit-ethernet-cable-5m',
      category: 'ethernet-cables',
      categoryName: 'Ethernet Cables',
      group: 'cables-networking',
      groupName: 'Cables & Networking',
      price: 399,
      demoPriceText: '₹399',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: '1000BASE-T Gigabit Bandwidth • 250MHz Frequency • 26AWG 100% Bare Copper • Snagless Strain Relief',
      image: 'assets/images/placeholder-cable.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },
    {
      id: 'demo-eth-02',
      name: 'Cat 7 High-Speed 10Gbps Flat Network Cable 10m [Demo]',
      slug: 'cat-7-high-speed-10gbps-flat-cable-10m',
      category: 'ethernet-cables',
      categoryName: 'Ethernet Cables',
      group: 'cables-networking',
      groupName: 'Cables & Networking',
      price: 799,
      demoPriceText: '₹799',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: '10Gbps Data Speed • 600MHz Frequency • Shielded Twisted Pair (STP) • Ultra-Flat Under-Carpet Design',
      image: 'assets/images/placeholder-cable.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },

    // --- Column 5: Network Adapters ---
    {
      id: 'demo-nic-01',
      name: 'USB 3.0 to Gigabit Ethernet LAN Adapter [Demo]',
      slug: 'usb-3-0-to-gigabit-ethernet-lan-adapter',
      category: 'network-adapters',
      categoryName: 'Network Adapters',
      group: 'cables-networking',
      groupName: 'Cables & Networking',
      price: 899,
      demoPriceText: '₹899',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: 'Realtek RTL8153 Chipset • 10/100/1000 Mbps Auto Sensing • Wake-on-LAN • Driverless Mac/Windows/Linux',
      image: 'assets/images/placeholder-generic.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },

    // --- Column 5: Wi-Fi Adapters ---
    {
      id: 'demo-wfi-01',
      name: 'Wi-Fi 6 AX1800 Dual Band USB 3.0 Wi-Fi Adapter [Demo]',
      slug: 'wi-fi-6-ax1800-dual-band-usb-adapter',
      category: 'wifi-adapters',
      categoryName: 'Wi-Fi Adapters',
      group: 'cables-networking',
      groupName: 'Cables & Networking',
      price: 1699,
      demoPriceText: '₹1,699',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: '1201 Mbps (5GHz) + 574 Mbps (2.4GHz) • Dual 5dBi High-Gain Antennas • WPA3 Security • OFDMA & MU-MIMO',
      image: 'assets/images/placeholder-generic.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    },
    {
      id: 'demo-wfi-02',
      name: 'PCIe Wi-Fi 6E + Bluetooth 5.3 Desktop Card [Demo]',
      slug: 'pcie-wi-fi-6e-bluetooth-5-3-desktop-card',
      category: 'wifi-adapters',
      categoryName: 'Wi-Fi Adapters',
      group: 'cables-networking',
      groupName: 'Cables & Networking',
      price: 2899,
      demoPriceText: '₹2,899',
      priceNote: 'Sample Demo Price (Simulation)',
      specs: 'Intel AX210 Chipset • Tri-Band 2.4G/5G/6GHz (Up to 2400Mbps) • Magnetic Antenna Base • Heatsink Included',
      image: 'assets/images/placeholder-generic.svg',
      badge: 'Demo Item',
      stockNote: 'Sample demonstration data only • Not live inventory'
    }
  ];

  // 4. Public API Interface
  const IBOLT_OUR_PRODUCTS = {
    isDemoData: true,
    GROUPS: GROUPS,
    CATEGORIES: CATEGORIES,
    SAMPLE_PRODUCTS: SAMPLE_PRODUCTS,

    /**
     * Check if a given category slug belongs to Our Products
     */
    isOurProductsCategory: function (slug) {
      if (!slug) return false;
      const normalized = String(slug).toLowerCase().trim();
      return normalized === 'our-products' || Object.prototype.hasOwnProperty.call(CATEGORIES, normalized);
    },

    /**
     * Get details for a category slug
     */
    getCategory: function (slug) {
      if (!slug) return CATEGORIES['our-products'];
      const normalized = String(slug).toLowerCase().trim();
      if (CATEGORIES[normalized]) return CATEGORIES[normalized];

      // Handle display-hdmi alias
      if (normalized === 'hdmi-cables' || normalized === 'hdmi-cables-display') {
        return CATEGORIES['hdmi-cables'];
      }

      return {
        slug: normalized,
        title: normalized.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase()),
        group: null,
        groupTitle: 'Demonstration Category',
        description: 'Demonstration hardware category for layout and filtering demonstration.'
      };
    },

    /**
     * Query and filter sample products
     * @param {Object} options - { category, query, sortBy, page, pageSize }
     */
    queryProducts: function (options) {
      options = options || {};
      const category = options.category ? String(options.category).toLowerCase().trim() : '';
      const query = options.query ? String(options.query).toLowerCase().trim() : '';
      const sortBy = options.sortBy || 'featured';
      const page = Math.max(1, parseInt(options.page, 10) || 1);
      const pageSize = Math.max(1, parseInt(options.pageSize, 10) || 8);

      let results = SAMPLE_PRODUCTS.slice();

      // Category filter (if not "our-products" or empty)
      if (category && category !== 'our-products' && category !== 'all') {
        results = results.filter(function (p) {
          if (p.category === category) return true;
          if (p.group === category) return true;
          // Aliases
          if ((category === 'hdmi-cables' || category === 'hdmi-cables-display') &&
              (p.category === 'hdmi-cables' || p.category === 'hdmi-cables-display')) {
            return true;
          }
          return false;
        });
      }

      // Keyword search
      if (query) {
        const terms = query.split(/\s+/).filter(Boolean);
        results = results.filter(function (p) {
          const haystack = (p.name + ' ' + p.specs + ' ' + p.categoryName + ' ' + p.groupName).toLowerCase();
          return terms.every(function (t) {
            return haystack.indexOf(t) !== -1;
          });
        });
      }

      // Sorting
      if (sortBy === 'price-low-high') {
        results.sort((a, b) => a.price - b.price);
      } else if (sortBy === 'price-high-low') {
        results.sort((a, b) => b.price - a.price);
      } else if (sortBy === 'name-asc') {
        results.sort((a, b) => a.name.localeCompare(b.name));
      } else if (sortBy === 'name-desc') {
        results.sort((a, b) => b.name.localeCompare(a.name));
      }

      const totalItems = results.length;
      const totalPages = Math.ceil(totalItems / pageSize) || 1;
      const startIndex = (page - 1) * pageSize;
      const pagedItems = results.slice(startIndex, startIndex + pageSize);

      return {
        items: pagedItems,
        totalItems: totalItems,
        page: page,
        totalPages: totalPages,
        pageSize: pageSize,
        startIndex: totalItems > 0 ? startIndex + 1 : 0,
        endIndex: Math.min(startIndex + pageSize, totalItems)
      };
    }
  };

  root.IBOLT_OUR_PRODUCTS = IBOLT_OUR_PRODUCTS;
})(typeof window !== 'undefined' ? window : this);
