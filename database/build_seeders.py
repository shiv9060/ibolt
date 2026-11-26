#!/usr/bin/env python3
"""
Seeders Generator for iboltnetworks E-Commerce Database
Extracts all real hardware items from products.html and our-products-data.js,
normalizes prices into integer paise, generates SKU codes, specifications,
image paths, and produces database/seeders.sql.
"""

import re
import os

def sql_escape(val):
    if val is None:
        return 'NULL'
    if isinstance(val, bool):
        return '1' if val else '0'
    if isinstance(val, (int, float)):
        return str(val)
    val = str(val).replace('\\', '\\\\').replace("'", "''")
    return f"'{val}'"

def slugify(text):
    text = re.sub(r'[^\w\s-]', '', text).strip().lower()
    return re.sub(r'[-\s]+', '-', text)

def extract_field(body, key):
    m = re.search(rf"{key}:\s*['\"]([^'\"]*)['\"]", body)
    return m.group(1).strip() if m else ''

def main():
    root_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    products_html_path = os.path.join(root_dir, 'products.html')
    our_products_js_path = os.path.join(root_dir, 'our-products-data.js')
    output_sql_path = os.path.join(root_dir, 'database', 'seeders.sql')

    with open(products_html_path, 'r', encoding='utf-8') as f:
        products_html = f.read()

    with open(our_products_js_path, 'r', encoding='utf-8') as f:
        our_products_js = f.read()

    brands_set = {
        'Dell': 'dell',
        'HPE': 'hpe',
        'Cisco': 'cisco',
        'Huawei': 'huawei',
        'Intel': 'intel',
        'AMD': 'amd',
        'NVIDIA': 'nvidia',
        'Seagate': 'seagate',
        'Western Digital': 'western-digital',
        'Toshiba': 'toshiba',
        'HGST': 'hgst',
        'Fortinet': 'fortinet',
        'Radware': 'radware',
        'A10 Networks': 'a10-networks',
        'F5 Networks': 'f5-networks',
        'Juniper': 'juniper',
        'MikroTik': 'mikrotik',
        'Kemp': 'kemp',
        'Citrix': 'citrix',
        'Kingston': 'kingston',
        'Samsung': 'samsung',
        'Crucial': 'crucial',
        'Logitech': 'logitech',
        'TP-Link': 'tp-link',
        'ASUS': 'asus',
        'Lenovo': 'lenovo',
        'Apple': 'apple',
        'HP': 'hp',
        'iboltnetworks': 'iboltnetworks'
    }

    # Categories definitions: (name, slug, section, parent_slug, description)
    categories_def = [
        # Top-level sections
        ('Servers', 'servers', 'servers', None, 'High-performance enterprise rack, tower, and blade servers with multi-socket configurations.'),
        ('Networking', 'networking', 'networking', None, 'Enterprise firewalls, switches, hardware load balancers, and fiber modules.'),
        ('Components', 'components', 'components', None, 'Genuine processors, DDR4/DDR5 ECC RAM, SAS/NVMe drives, and AI GPUs.'),
        ('Our Products', 'our-products', 'our-products', None, 'Premium computer accessories, displays, docking stations, and peripherals.'),

        # Servers subcategories
        ('Dell Refurbished Servers', 'dell-refurbished-servers', 'servers', 'servers', 'Tested and certified refurbished Dell PowerEdge rack servers with warranty.'),
        ('HPE Refurbished Servers', 'hpe-refurbished-servers', 'servers', 'servers', 'Enterprise refurbished HPE ProLiant Gen9 and Gen10 servers.'),
        ('Dell New Servers', 'dell-new-servers', 'servers', 'servers', 'Factory-sealed 15th & 16th generation Dell PowerEdge servers.'),
        ('HPE New Servers', 'hpe-new-servers', 'servers', 'servers', 'Brand new HPE ProLiant Gen11 and Gen12 compute platforms.'),
        ('Cisco Servers', 'cisco-servers', 'servers', 'servers', 'Cisco UCS C-Series high density compute nodes.'),
        ('Huawei Refurbished Servers', 'huawei-refurbished-servers', 'servers', 'servers', 'Huawei FusionServer multi-node architectures.'),
        ('Storage Servers', 'storage-servers', 'servers', 'servers', 'High density SAN and NAS storage server appliances.'),
        ('AI & GPU Servers', 'ai-servers', 'servers', 'servers', 'Multi-GPU accelerated platforms for deep learning and high-performance computing.'),
        ('Customized Servers', 'custom-server', 'servers', 'servers', 'Configured-to-order enterprise server solutions.'),

        # Networking subcategories
        ('Firewalls', 'firewalls', 'networking', 'networking', 'Next-Generation threat prevention and enterprise firewalls.'),
        ('Basic Firewalls', 'basic-firewalls', 'networking', 'firewalls', 'Branch and SMB next-gen firewalls.'),
        ('Enterprise Firewalls', 'enterprise-firewalls', 'networking', 'firewalls', 'High-throughput datacenter and enterprise perimeter firewalls.'),
        ('FortiGate Firewalls', 'fortigate-firewall', 'networking', 'firewalls', 'Industry-leading Fortinet FortiGate security appliances.'),
        ('Network Switches', 'network-switches', 'networking', 'networking', 'Managed Layer 2/3 enterprise and datacenter switches.'),
        ('Cisco Switches', 'cisco-switches', 'networking', 'network-switches', 'Cisco Catalyst and Nexus enterprise switches.'),
        ('Huawei Switches', 'huawei-switches', 'networking', 'network-switches', 'Huawei CloudEngine enterprise switches.'),
        ('Juniper Switches', 'juniper-switches', 'networking', 'network-switches', 'Juniper EX-series datacenter switches.'),
        ('KVM Switches', 'kvm-switches', 'networking', 'network-switches', 'Rackmount remote KVM console switches.'),
        ('MikroTik Switches', 'mikrotik-switches', 'networking', 'network-switches', 'MikroTik high-density cloud router switches.'),
        ('Load Balancers', 'load-balancers', 'networking', 'networking', 'Application delivery controllers and hardware traffic balancers.'),
        ('SFP Modules', 'sfp-modules', 'networking', 'networking', '1G, 10G, 40G, and 100G optical transceivers.'),
        ('DAC Cables', 'dac-cables', 'networking', 'networking', 'Direct attach copper high-speed interconnect cables.'),
        ('Routers', 'routers', 'networking', 'networking', 'Carrier-grade and enterprise edge routing systems.'),

        # Components subcategories
        ('Processors', 'processors', 'components', 'components', 'Intel Xeon and multi-core server processors.'),
        ('AMD EPYC Processors', 'amd-epyc-processors', 'components', 'components', 'High-density AMD EPYC server CPUs up to 64 cores.'),
        ('DDR5 Server RAM', 'ddr5-server-ram', 'components', 'components', 'Next-generation registered ECC DDR5 memory modules.'),
        ('DDR4 Server RAM', 'ddr4-server-ram', 'components', 'components', 'Registered and load-reduced DDR4 2666/2933/3200MHz RAM.'),
        ('DDR3 Server RAM', 'ddr3-server-ram', 'components', 'components', 'Legacy DDR3 ECC memory for Gen8/R720 servers.'),
        ('Hard Disk Drives', 'hard-disk-drives', 'components', 'components', 'Enterprise 12Gbps SAS and SATA 7.2K RPM HDDs up to 24TB.'),
        ('Solid State Drives', 'solid-state-drives', 'components', 'components', 'Datacenter grade SAS, SATA, and NVMe SSD drives.'),
        ('AI & Enterprise GPUs', 'enterprise-gpus', 'components', 'components', 'NVIDIA RTX Ada and Tensor Core datacenter accelerators.'),
        ('Other Spares', 'other-spares', 'components', 'components', 'Redundant power supplies, RAID controllers, motherboards, fans, and caddies.'),

        # Our Products Groups
        ('Computer Accessories', 'computer-accessories', 'our-products', 'our-products', 'Input peripherals, hubs, webcams, and accessories.'),
        ('Keyboards', 'keyboards', 'our-products', 'computer-accessories', 'Mechanical, tactile, and silent office keyboards.'),
        ('Computer Mice', 'computer-mice', 'our-products', 'computer-accessories', 'Ergonomic, gaming, and precision wireless mice.'),
        ('Mouse Pads', 'mouse-pads', 'our-products', 'computer-accessories', 'Extended desk mats and micro-weave tracking surfaces.'),
        ('Webcams', 'webcams', 'our-products', 'computer-accessories', 'Full HD and 4K conferencing cameras with stereo microphones.'),
        ('USB Hubs', 'usb-hubs', 'our-products', 'computer-accessories', 'Powered multi-port USB 3.2 hubs.'),

        ('Laptop Accessories', 'laptop-accessories', 'our-products', 'our-products', 'Stands, chargers, bags, and multi-display docking hubs.'),
        ('Laptop Chargers', 'laptop-chargers', 'our-products', 'laptop-accessories', 'USB-C GaN fast chargers and universal power adapters.'),
        ('Laptop Bags', 'laptop-bags', 'our-products', 'laptop-accessories', 'Water-resistant padded laptop backpacks and sleeves.'),
        ('Cooling Pads', 'cooling-pads', 'our-products', 'laptop-accessories', 'Aluminum active cooling stands with high-flow fans.'),
        ('Laptop Stands', 'laptop-stands', 'our-products', 'laptop-accessories', 'Ergonomic folding aluminum laptop risers.'),
        ('Docking Stations', 'docking-stations', 'our-products', 'laptop-accessories', 'Thunderbolt 4 and multi-display USB-C docks.'),

        ('Monitors & Displays', 'monitors-displays', 'our-products', 'our-products', 'Business monitors, gaming screens, and VESA arms.'),
        ('LED Monitors', 'led-monitors', 'our-products', 'monitors-displays', 'IPS Full HD and 4K productivity monitors.'),
        ('Gaming Monitors', 'gaming-monitors', 'our-products', 'monitors-displays', '165Hz–240Hz fast response gaming displays.'),
        ('Monitor Stands', 'monitor-stands', 'our-products', 'monitors-displays', 'Gas spring single and dual monitor desk arms.'),
        ('Display Adapters', 'display-adapters', 'our-products', 'monitors-displays', 'USB-C to HDMI, DisplayPort, and multi-stream adapters.'),
        ('HDMI Cables', 'hdmi-cables-display', 'our-products', 'monitors-displays', 'High-speed 4K/8K certified HDMI cables.'),

        ('Storage & Components', 'storage-components', 'our-products', 'our-products', 'NVMe SSDs, external storage drives, RAM, and GPUs.'),
        ('Internal SSDs', 'internal-ssds', 'our-products', 'storage-components', 'PCIe Gen4/Gen5 high speed internal NVMe SSDs.'),
        ('External SSDs', 'external-ssds', 'our-products', 'storage-components', 'Rugged portable SSDs with USB-C 3.2 Gen2x2.'),
        ('Hard Drives', 'hard-drives', 'our-products', 'storage-components', 'SATA and external USB desktop hard disk drives.'),
        ('RAM Modules', 'ram-modules', 'our-products', 'storage-components', 'Desktop DDR4 and DDR5 gaming and productivity RAM.'),
        ('Graphics Cards', 'graphics-cards', 'our-products', 'storage-components', 'Consumer and creator desktop graphics cards.'),

        ('Cables & Networking', 'cables-networking', 'our-products', 'our-products', 'Cables, adapters, and high-speed Wi-Fi network cards.'),
        ('USB Cables', 'usb-cables', 'our-products', 'cables-networking', 'Braided USB-C, Type-A, and 100W PD charging cables.'),
        ('Ethernet Cables', 'ethernet-cables', 'our-products', 'cables-networking', 'Cat6 and Cat7 shielded gigabit network cables.'),
        ('Network Adapters', 'network-adapters', 'our-products', 'cables-networking', 'USB-C to 2.5G/5G Ethernet network adapters.'),
        ('Wi-Fi Adapters', 'wifi-adapters', 'our-products', 'cables-networking', 'Wi-Fi 6E dual and tri-band high-gain wireless USB cards.')
    ]

    all_products = []

    # 1. Parse SERVER_CATALOG from products.html
    server_start = products_html.find('const SERVER_CATALOG = {')
    server_end = products_html.find('const CATEGORY_MAP = {', server_start)
    server_block = products_html[server_start:server_end]
    
    server_matches = re.findall(r"'([a-z0-9\-]+)':\s*\{([\s\S]*?)\}(?=,\s*'|\s*\n\s*\};)", server_block)
    sku_counter = 1001
    for model_slug, body in server_matches:
        name = extract_field(body, 'name')
        brand = extract_field(body, 'brand') or 'Dell'
        condition_raw = extract_field(body, 'condition').lower()
        condition = 'refurbished' if 'refurb' in condition_raw else 'new'
        form_factor = extract_field(body, 'formFactor')
        processors = extract_field(body, 'processors')
        memory = extract_field(body, 'memory')
        storage = extract_field(body, 'storage')
        raid = extract_field(body, 'raid')
        power = extract_field(body, 'power')
        network = extract_field(body, 'network')
        image = extract_field(body, 'image') or 'images/dell-poweredge-r640.png'

        if 'r840' in model_slug or 'dl580' in model_slug or 'dl560' in model_slug:
            price_paise = 24500000
            mrp_paise = 32000000
        elif 'r740xd' in model_slug or 'dl380-g10' in model_slug:
            price_paise = 17500000
            mrp_paise = 24000000
        elif 'g11' in model_slug or 'g12' in model_slug or 'r760' in model_slug or 'r860' in model_slug:
            price_paise = 38500000
            mrp_paise = 45000000
        elif condition == 'refurbished':
            price_paise = 9500000
            mrp_paise = 16000000
        else:
            price_paise = 21000000
            mrp_paise = 28000000

        if 'dell' in brand.lower() and condition == 'refurbished':
            cat_slug = 'dell-refurbished-servers'
        elif 'hpe' in brand.lower() and condition == 'refurbished':
            cat_slug = 'hpe-refurbished-servers'
        elif 'dell' in brand.lower():
            cat_slug = 'dell-new-servers'
        elif 'hpe' in brand.lower():
            cat_slug = 'hpe-new-servers'
        elif 'cisco' in brand.lower():
            cat_slug = 'cisco-servers'
        elif 'huawei' in brand.lower():
            cat_slug = 'huawei-refurbished-servers'
        else:
            cat_slug = 'servers'

        short_desc = f"{brand} enterprise server featuring {processors}. Form factor: {form_factor}."
        desc = f"Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty."

        specs_list = [
            ('Form Factor', form_factor),
            ('Processors', processors),
            ('Memory Capacity', memory),
            ('Internal Storage', storage),
            ('RAID Controller', raid),
            ('Power Supply', power),
            ('Network Interface', network)
        ]

        all_products.append({
            'title': name,
            'slug': model_slug,
            'sku': f"IB-SRV-{sku_counter}",
            'brand_name': brand,
            'category_slug': cat_slug,
            'condition': condition,
            'model': model_slug,
            'short_description': short_desc,
            'description': desc,
            'price_paise': price_paise,
            'compare_price_paise': mrp_paise,
            'stock_quantity': 8,
            'is_featured': 1 if sku_counter <= 1006 else 0,
            'image': image,
            'specs': specs_list
        })
        sku_counter += 1

    # 2. Parse NETWORKING_PRODUCTS from products.html
    net_start = products_html.find('const NETWORKING_PRODUCTS = [')
    net_end = products_html.find('const COMPONENTS_PRODUCTS = [', net_start)
    net_block = products_html[net_start:net_end]
    net_entries = re.split(r'\{\s*name:\s*', net_block)[1:]

    sku_counter = 2001
    for raw in net_entries:
        # extract name from first quoted string
        nm = re.match(r"['\"]([^'\"]*)['\"]", raw)
        name = nm.group(1).strip() if nm else ''
        brand = extract_field(raw, 'brand') or 'Cisco'
        cat = extract_field(raw, 'category') or 'networking'
        cond_raw = extract_field(raw, 'condition') or 'New'
        specs = extract_field(raw, 'specs') or ''
        form_factor = extract_field(raw, 'formFactor') or '1U'
        img = extract_field(raw, 'image') or 'images/server-hud.png'

        cond = 'refurbished' if 'refurb' in cond_raw.lower() else 'new'
        slug = slugify(name)
        if 'firewall' in name.lower():
            price_paise = 4850000
            mrp_paise = 6500000
            cat_slug = 'firewalls'
        elif 'switch' in name.lower():
            price_paise = 6800000
            mrp_paise = 9200000
            cat_slug = 'network-switches'
        elif 'balancer' in name.lower():
            price_paise = 11500000
            mrp_paise = 15000000
            cat_slug = 'load-balancers'
        elif 'sfp' in name.lower() or 'module' in name.lower():
            price_paise = 850000
            mrp_paise = 1200000
            cat_slug = 'sfp-modules'
        elif 'dac' in name.lower():
            price_paise = 450000
            mrp_paise = 650000
            cat_slug = 'dac-cables'
        else:
            price_paise = 3200000
            mrp_paise = 4500000
            cat_slug = 'networking'

        specs_list = [
            ('Brand', brand),
            ('Form Factor', form_factor),
            ('Specifications', specs),
            ('Condition', cond_raw)
        ]

        all_products.append({
            'title': name,
            'slug': slug,
            'sku': f"IB-NET-{sku_counter}",
            'brand_name': brand,
            'category_slug': cat_slug,
            'condition': cond,
            'model': slug[:30],
            'short_description': f"{brand} enterprise networking solution. {specs}",
            'description': f"High reliability network hardware built for 24x7 continuous datacenter and campus deployment. Features: {specs}.",
            'price_paise': price_paise,
            'compare_price_paise': mrp_paise,
            'stock_quantity': 15,
            'is_featured': 1 if sku_counter in [2001, 2004] else 0,
            'image': img,
            'specs': specs_list
        })
        sku_counter += 1

    # 3. Parse COMPONENTS_PRODUCTS from products.html
    comp_start = net_end
    comp_end = products_html.find('const COMPONENT_CATEGORY_KEYS =', comp_start)
    comp_block = products_html[comp_start:comp_end]
    comp_entries = re.split(r'\{\s*name:\s*', comp_block)[1:]

    sku_counter = 3001
    for raw in comp_entries:
        nm = re.match(r"['\"]([^'\"]*)['\"]", raw)
        name = nm.group(1).strip() if nm else ''
        brand = extract_field(raw, 'brand') or 'Intel'
        cat = extract_field(raw, 'category') or 'components'
        cond_raw = extract_field(raw, 'condition') or 'New'
        specs = extract_field(raw, 'specs') or ''
        form_factor = extract_field(raw, 'formFactor') or ''
        img = extract_field(raw, 'image') or 'images/server-hud.png'

        cond = 'refurbished' if 'refurb' in cond_raw.lower() or 'pull' in cond_raw.lower() else 'new'
        slug = slugify(name)
        if 'rtx' in name.lower() or 'gpu' in name.lower():
            price_paise = 42500000
            mrp_paise = 52000000
            cat_slug = 'enterprise-gpus'
        elif 'epyc' in name.lower():
            price_paise = 8500000
            mrp_paise = 11000000
            cat_slug = 'amd-epyc-processors'
        elif 'xeon' in name.lower() or 'processor' in name.lower():
            price_paise = 4500000
            mrp_paise = 6200000
            cat_slug = 'processors'
        elif 'ddr5' in name.lower():
            price_paise = 1850000
            mrp_paise = 2400000
            cat_slug = 'ddr5-server-ram'
        elif 'ddr4' in name.lower() or 'ram' in name.lower():
            price_paise = 750000
            mrp_paise = 1100000
            cat_slug = 'ddr4-server-ram'
        elif 'hdd' in name.lower() or 'hard disk' in name.lower():
            price_paise = 1650000
            mrp_paise = 2200000
            cat_slug = 'hard-disk-drives'
        elif 'ssd' in name.lower():
            price_paise = 2100000
            mrp_paise = 2900000
            cat_slug = 'solid-state-drives'
        else:
            price_paise = 1250000
            mrp_paise = 1800000
            cat_slug = 'other-spares'

        specs_list = [
            ('Brand', brand),
            ('Form Factor', form_factor),
            ('Technical Specifications', specs),
            ('Hardware Condition', cond_raw)
        ]

        all_products.append({
            'title': name,
            'slug': slug,
            'sku': f"IB-CMP-{sku_counter}",
            'brand_name': brand,
            'category_slug': cat_slug,
            'condition': cond,
            'model': slug[:30],
            'short_description': f"High-grade {brand} component. {specs}",
            'description': f"Authentic server component tested for compatibility and stability in critical server systems. {specs}.",
            'price_paise': price_paise,
            'compare_price_paise': mrp_paise,
            'stock_quantity': 20,
            'is_featured': 1 if 'rtx 6000' in name.lower() or 'epyc 7742' in name.lower() else 0,
            'image': img,
            'specs': specs_list
        })
        sku_counter += 1

    # 4. Parse SAMPLE_PRODUCTS from our-products-data.js
    our_prod_start = our_products_js.find('const SAMPLE_PRODUCTS = [')
    our_prod_end = our_products_js.find('const IBOLT_OUR_PRODUCTS =', our_prod_start)
    our_block = our_products_js[our_prod_start:our_prod_end]
    our_entries = re.split(r'\{\s*id:\s*', our_block)[1:]

    sku_counter = 4001
    for raw in our_entries:
        nm = extract_field(raw, 'name')
        if not nm:
            continue
        clean_name = nm.replace('[Demo]', '').replace('(Demo)', '').strip()
        cat_slug = extract_field(raw, 'category') or 'computer-accessories'
        price_m = re.search(r'price:\s*(\d+)', raw)
        price_inr = int(price_m.group(1)) if price_m else 1999
        specs = extract_field(raw, 'specs') or ''
        img = extract_field(raw, 'image') or 'assets/images/placeholder-keyboard.svg'

        slug = slugify(clean_name)
        price_paise = price_inr * 100
        mrp_paise = int(price_paise * 1.35)

        brand = 'iboltnetworks'
        for b in ['Logitech', 'Samsung', 'Kingston', 'Crucial', 'Dell', 'HP', 'Lenovo', 'ASUS', 'TP-Link', 'Seagate', 'Western Digital']:
            if b.lower() in clean_name.lower():
                brand = b
                break

        specs_list = [
            ('Brand', brand),
            ('Category', cat_slug.replace('-', ' ').title()),
            ('Key Features', specs)
        ]

        all_products.append({
            'title': clean_name,
            'slug': slug,
            'sku': f"IB-PRD-{sku_counter}",
            'brand_name': brand,
            'category_slug': cat_slug,
            'condition': 'new',
            'model': slug[:30],
            'short_description': f"{clean_name} — {specs}.",
            'description': f"Designed for exceptional durability, performance, and everyday reliability. Features: {specs}.",
            'price_paise': price_paise,
            'compare_price_paise': mrp_paise,
            'stock_quantity': 25,
            'is_featured': 1 if sku_counter in [4001, 4007, 4015, 4022] else 0,
            'image': img,
            'specs': specs_list
        })
        sku_counter += 1

    print(f"Total extracted products: {len(all_products)}")

    # Ensure uniqueness of slugs and skus
    seen_slugs = set()
    for p in all_products:
        orig = p['slug']
        c = 1
        while p['slug'] in seen_slugs:
            p['slug'] = f"{orig}-{c}"
            c += 1
        seen_slugs.add(p['slug'])

    # Build SQL script
    sql_lines = [
        "-- ==============================================================================",
        "-- iboltnetworks Database Seed Data",
        "-- Auto-generated from products.html & our-products-data.js",
        "-- ==============================================================================",
        "USE `iboltnetworks_db`;",
        "",
        "SET FOREIGN_KEY_CHECKS = 0;",
        "TRUNCATE TABLE `admin_activity_logs`;",
        "TRUNCATE TABLE `inventory_movements`;",
        "TRUNCATE TABLE `payments`;",
        "TRUNCATE TABLE `order_items`;",
        "TRUNCATE TABLE `orders`;",
        "TRUNCATE TABLE `addresses`;",
        "TRUNCATE TABLE `product_specifications`;",
        "TRUNCATE TABLE `product_images`;",
        "TRUNCATE TABLE `products`;",
        "TRUNCATE TABLE `brands`;",
        "TRUNCATE TABLE `categories`;",
        "TRUNCATE TABLE `admin_users`;",
        "TRUNCATE TABLE `users`;",
        "SET FOREIGN_KEY_CHECKS = 1;",
        "",
        "-- ------------------------------------------------------------------------------",
        "-- 1. Seed Super Admin User (Default Password: Admin@ibolt2026!)",
        "-- ------------------------------------------------------------------------------",
        "INSERT INTO `admin_users` (`name`, `email`, `password_hash`, `role`, `status`) VALUES",
        "('System Administrator', 'admin@iboltnetworks.com', '$2y$10$K1YNv3/82PdEhRwVR/YXROt7J8IpvrMy/DGgrk.qiTSpW6aE31vlq', 'super_admin', 'active');",
        "",
        "-- ------------------------------------------------------------------------------",
        "-- 2. Seed Brands",
        "-- ------------------------------------------------------------------------------"
    ]

    brand_inserts = []
    for b_name, b_slug in brands_set.items():
        brand_inserts.append(f"({sql_escape(b_name)}, {sql_escape(b_slug)}, 1)")
    sql_lines.append("INSERT INTO `brands` (`name`, `slug`, `is_active`) VALUES\n" + ",\n".join(brand_inserts) + ";\n")

    # Categories
    sql_lines.append("-- ------------------------------------------------------------------------------")
    sql_lines.append("-- 3. Seed Categories (Hierarchy with Parent Slugs)")
    sql_lines.append("-- ------------------------------------------------------------------------------")

    cat_inserts = []
    for c_name, c_slug, c_sec, c_parent, c_desc in categories_def:
        cat_inserts.append(f"({sql_escape(c_name)}, {sql_escape(c_slug)}, {sql_escape(c_sec)}, {sql_escape(c_desc)}, 1)")
    sql_lines.append("INSERT INTO `categories` (`name`, `slug`, `section`, `description`, `is_active`) VALUES\n" + ",\n".join(cat_inserts) + ";\n")

    for c_name, c_slug, c_sec, c_parent, c_desc in categories_def:
        if c_parent:
            sql_lines.append(f"UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = {sql_escape(c_parent)}) AS p) WHERE `slug` = {sql_escape(c_slug)};")

    sql_lines.append("\n-- ------------------------------------------------------------------------------")
    sql_lines.append("-- 4. Seed Products")
    sql_lines.append("-- ------------------------------------------------------------------------------")

    for p in all_products:
        cat_subquery = f"(SELECT `id` FROM `categories` WHERE `slug` = {sql_escape(p['category_slug'])} LIMIT 1)"
        brand_subquery = f"(SELECT `id` FROM `brands` WHERE `slug` = {sql_escape(slugify(p['brand_name']))} LIMIT 1)"

        p_sql = f"""INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  {cat_subquery},
  {brand_subquery},
  {sql_escape(p['title'])},
  {sql_escape(p['slug'])},
  {sql_escape(p['sku'])},
  {sql_escape(p['model'])},
  {sql_escape(p['condition'])},
  {sql_escape(p['short_description'])},
  {sql_escape(p['description'])},
  {p['price_paise']},
  {p['compare_price_paise']},
  {p['stock_quantity']},
  {p['is_featured']},
  'published'
);"""
        sql_lines.append(p_sql)

        img_sql = f"""INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = {sql_escape(p['slug'])}),
  {sql_escape(p['image'])},
  {sql_escape(p['title'])},
  1,
  0
);"""
        sql_lines.append(img_sql)

        for spec_k, spec_v in p['specs']:
            spec_sql = f"""INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = {sql_escape(p['slug'])}),
  'Specifications',
  {sql_escape(spec_k)},
  {sql_escape(spec_v)},
  0
);"""
            sql_lines.append(spec_sql)

    with open(output_sql_path, 'w', encoding='utf-8') as f:
        f.write("\n".join(sql_lines))

    print(f"Generated {output_sql_path} successfully. Total products: {len(all_products)}")

if __name__ == '__main__':
    main()
