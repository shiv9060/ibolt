-- ==============================================================================
-- Migration: 004_add_custom_categories_and_brands.sql
-- Add user-specified categories and brands for product management
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- 1. Brands
-- ------------------------------------------------------------------------------
INSERT INTO `brands` (`name`, `slug`, `is_active`) VALUES
('SK Hynix', 'sk-hynix', 1),
('WD', 'wd', 1),
('D-Link', 'd-link', 1),
('Cisco', 'cisco', 1),
('Micron', 'micron', 1),
('Molex', 'molex', 1),
('TP-Link', 'tp-link', 1),
('CommScope', 'commscope', 1),
('Kingston', 'kingston', 1),
('Nokia', 'nokia', 1),
('Seagate', 'seagate', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `is_active` = 1;

-- ------------------------------------------------------------------------------
-- 2. Categories
-- ------------------------------------------------------------------------------
INSERT INTO `categories` (`name`, `slug`, `section`, `description`, `is_active`) VALUES
('Laptop RAM', 'laptop-ram', 'components', 'DDR4, DDR5, and SODIMM memory modules for laptops and notebooks.', 1),
('Desktop RAM', 'desktop-ram', 'components', 'High-speed DDR4 and DDR5 DIMM RAM for desktop computers and workstations.', 1),
('Laptop SSD', 'laptop-ssd', 'components', 'M.2 NVMe and 2.5-inch SATA solid state drives for laptops.', 1),
('Desktop Hard Disk', 'desktop-hard-disk', 'components', '3.5-inch SATA internal and external hard disk drives for desktop PCs.', 1),
('Laptop Hard Disk', 'laptop-hard-disk', 'components', '2.5-inch SATA slim internal hard disk drives for laptops.', 1),
('Server Hard Disk', 'server-hard-disk', 'components', 'Enterprise 2.5-inch and 3.5-inch 12Gbps SAS, SATA, and NVMe server hard drives.', 1),
('Server RAM', 'server-ram', 'components', 'ECC Registered (RDIMM/LRDIMM) server memory for Dell, HPE, and Cisco servers.', 1),
('SFP', 'sfp', 'networking', '1G and 10G SFP/SFP+ optical transceiver modules and transceivers.', 1),
('QSFP', 'qsfp', 'networking', 'High-bandwidth 40G, 100G, and 200G QSFP+/QSFP28 transceivers and interconnects.', 1),
('Module', 'module', 'networking', 'Network expansion modules, supervisor modules, and line cards.', 1),
('Laptop', 'laptop', 'our-products', 'Business, enterprise, and portable performance laptops.', 1),
('Desktop', 'desktop', 'our-products', 'Desktop tower PCs, small form factor (SFF) units, and workstations.', 1),
('Server', 'server', 'servers', 'Enterprise rack, tower, and modular compute server systems.', 1),
('Switch', 'switch', 'networking', 'Managed Layer 2/3 network switches and PoE distribution switches.', 1),
('Cable', 'cable', 'networking', 'Ethernet patch cords, fiber optic patch cables, and DAC interconnects.', 1),
('Adapter', 'adapter', 'our-products', 'Power adapters, charging bricks, and hardware interface adapters.', 1),
('Battery', 'battery', 'our-products', 'Replacement laptop batteries, server RAID cache batteries, and UPS batteries.', 1),
('Phone', 'phone', 'our-products', 'VoIP business desk phones, IP conference phones, and communication handsets.', 1),
('Printer', 'printer', 'our-products', 'Office laser printers, multifunction network printers, and consumables.', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `section` = VALUES(`section`), `is_active` = 1;

-- ------------------------------------------------------------------------------
-- 3. Link Parent Categories (Safe Hierarchical Association)
-- ------------------------------------------------------------------------------
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'components' LIMIT 1) AS p) 
WHERE `slug` IN ('laptop-ram', 'desktop-ram', 'laptop-ssd', 'desktop-hard-disk', 'laptop-hard-disk', 'server-hard-disk', 'server-ram');

UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'networking' LIMIT 1) AS p) 
WHERE `slug` IN ('sfp', 'qsfp', 'module', 'switch', 'cable');

UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'our-products' LIMIT 1) AS p) 
WHERE `slug` IN ('laptop', 'desktop', 'adapter', 'battery', 'phone', 'printer');

UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'servers' LIMIT 1) AS p) 
WHERE `slug` IN ('server');

-- ------------------------------------------------------------------------------
-- 4. Extend Inventory Movements Reason ENUM to support stock_in
-- ------------------------------------------------------------------------------
ALTER TABLE `inventory_movements` MODIFY COLUMN `reason` ENUM('order_placed', 'order_cancelled', 'manual_adjustment', 'restock', 'damage', 'stock_in') NOT NULL;

