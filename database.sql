CREATE DATABASE IF NOT EXISTS innonce_outfits CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE innonce_outfits;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  phone VARCHAR(20) DEFAULT NULL,
  role ENUM('customer','admin','worker') DEFAULT 'customer',
  google_id VARCHAR(255) DEFAULT NULL UNIQUE,
  language VARCHAR(5) DEFAULT 'en',
  notify_email TINYINT(1) DEFAULT 1,
  notify_sms TINYINT(1) DEFAULT 0,
  notify_inapp TINYINT(1) DEFAULT 1,
  profile_photo VARCHAR(255) DEFAULT NULL,
  photo_align VARCHAR(50) DEFAULT 'center',
  last_activity DATETIME DEFAULT NULL,
  remember_token VARCHAR(100) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE password_resets (
  email VARCHAR(255) NOT NULL,
  token VARCHAR(255) NOT NULL,
  otp VARCHAR(6) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email (email)
);

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name_en VARCHAR(255) NOT NULL,
  name_sw VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL UNIQUE,
  parent_id INT DEFAULT NULL,
  description_en TEXT DEFAULT NULL,
  description_sw TEXT DEFAULT NULL,
  image VARCHAR(255) DEFAULT NULL,
  status TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE CASCADE
);

CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT DEFAULT NULL,
  name_en VARCHAR(255) NOT NULL,
  name_sw VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL UNIQUE,
  description_en TEXT DEFAULT NULL,
  description_sw TEXT DEFAULT NULL,
  brand VARCHAR(255) DEFAULT NULL,
  sku VARCHAR(255) NOT NULL UNIQUE,
  price DECIMAL(12,2) NOT NULL,
  discount_price DECIMAL(12,2) DEFAULT NULL,
  quantity INT DEFAULT 0,
  sizes JSON DEFAULT NULL,
  colors JSON DEFAULT NULL,
  gender VARCHAR(20) DEFAULT NULL,
  video VARCHAR(255) DEFAULT NULL,
  featured TINYINT(1) DEFAULT 0,
  new_arrival TINYINT(1) DEFAULT 0,
  status ENUM('active','inactive','draft') DEFAULT 'active',
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE product_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  image_hash VARCHAR(64) DEFAULT NULL,
  is_primary TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE product_reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  product_id INT NOT NULL,
  rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
  review TEXT DEFAULT NULL,
  images JSON DEFAULT NULL,
  status VARCHAR(20) DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE cart (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT DEFAULT 1,
  size VARCHAR(50) DEFAULT NULL,
  color VARCHAR(50) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  order_number VARCHAR(50) NOT NULL UNIQUE,
  status ENUM('pending','confirmed','processing','packed','shipped','delivered','cancelled') DEFAULT 'pending',
  subtotal DECIMAL(12,2) NOT NULL,
  tax DECIMAL(12,2) DEFAULT 0,
  shipping DECIMAL(12,2) DEFAULT 0,
  discount DECIMAL(12,2) DEFAULT 0,
  total DECIMAL(12,2) NOT NULL,
  payment_method VARCHAR(50) DEFAULT NULL,
  payment_status ENUM('unpaid','paid','failed','refunded') DEFAULT 'unpaid',
  currency VARCHAR(3) DEFAULT 'TZS',
  shipping_address JSON DEFAULT NULL,
  latitude DECIMAL(10,8) DEFAULT NULL,
  longitude DECIMAL(11,8) DEFAULT NULL,
  billing_address JSON DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  volume_discount DECIMAL(12,2) DEFAULT 0,
  delivery_method ENUM('delivery','pickup') DEFAULT 'delivery',
  worker_id INT DEFAULT NULL,
  coupon_code VARCHAR(50) DEFAULT NULL,
  coupon_discount DECIMAL(12,2) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL,
  price DECIMAL(12,2) NOT NULL,
  total DECIMAL(12,2) NOT NULL,
  size VARCHAR(50) DEFAULT NULL,
  color VARCHAR(50) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE order_trackings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  status VARCHAR(100) NOT NULL,
  description TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

CREATE TABLE payment_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  user_id INT NOT NULL,
  payment_method VARCHAR(50) NOT NULL,
  transaction_id VARCHAR(255) DEFAULT NULL UNIQUE,
  reference VARCHAR(255) NOT NULL UNIQUE,
  amount DECIMAL(12,2) NOT NULL,
  currency VARCHAR(3) DEFAULT 'TZS',
  phone VARCHAR(20) DEFAULT NULL,
  status ENUM('pending','success','failed','cancelled') DEFAULT 'pending',
  request_data JSON DEFAULT NULL,
  response_data JSON DEFAULT NULL,
  callback_data JSON DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE wishlists (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  product_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE coupons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  type ENUM('percentage','fixed') DEFAULT 'percentage',
  value DECIMAL(12,2) NOT NULL,
  min_purchase DECIMAL(12,2) DEFAULT NULL,
  usage_limit INT DEFAULT NULL,
  used_count INT DEFAULT 0,
  expires_at TIMESTAMP NULL DEFAULT NULL,
  status TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE campaigns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title_en VARCHAR(255) NOT NULL,
  title_sw VARCHAR(255) NOT NULL,
  type VARCHAR(50) NOT NULL,
  discount_type VARCHAR(50) DEFAULT NULL,
  discount_value DECIMAL(12,2) DEFAULT NULL,
  status TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE pages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title_en VARCHAR(255) NOT NULL,
  title_sw VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL UNIQUE,
  content_en TEXT DEFAULT NULL,
  content_sw TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE contacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  phone VARCHAR(20) DEFAULT NULL,
  subject VARCHAR(255) DEFAULT NULL,
  message TEXT NOT NULL,
  reply TEXT DEFAULT NULL,
  replied_at TIMESTAMP NULL DEFAULT NULL,
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE newsletters (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE loyalty_points (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  points INT DEFAULT 0,
  action VARCHAR(255) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE referrals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  referrer_id INT NOT NULL,
  referred_id INT DEFAULT NULL,
  code VARCHAR(50) NOT NULL UNIQUE,
  status ENUM('pending','completed') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (referred_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(255) NOT NULL UNIQUE,
  `value` TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE themes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL UNIQUE,
  description TEXT DEFAULT NULL,
  preview_color VARCHAR(7) DEFAULT '#FF8C00',
  is_active TINYINT(1) DEFAULT 0,
  is_default TINYINT(1) DEFAULT 0,
  auto_schedule TINYINT(1) DEFAULT 0,
  scheduled_from DATE DEFAULT NULL,
  scheduled_to DATE DEFAULT NULL,
  css_variables JSON NOT NULL,
  decorations JSON DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  message TEXT DEFAULT NULL,
  type VARCHAR(50) DEFAULT 'info',
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Seed data
INSERT INTO settings (`key`, `value`) VALUES
('site_name', 'INNOCE OUTFITS'),
('currency', 'TZS'),
('tax_rate', '5'),
('shipping_fee', '5000'),
('free_shipping_min', '100000'),
('default_payment', 'mpesa');

-- Default theme (Light) - activated by default
INSERT INTO themes (name, slug, description, preview_color, is_active, is_default, css_variables, decorations) VALUES
('Default Light', 'default-light', 'Default light theme with orange/gold brand colors', '#FF8C00', 1, 1, '{"--orange":"#FF8C00","--orange-light":"#FFAA40","--orange-dark":"#CC7000","--gold":"#FF8C00","--gold-light":"#FFAA40","--gold-dark":"#CC7000","--bg-body":"#FFF5EB","--bg-navbar":"#FF8C00","--bg-hero":"linear-gradient(135deg, #FF8C00 0%, #FF6B35 50%, #FF5500 100%)","--bg-section-alt":"#FFEDD5","--bg-card":"#fff","--text-primary":"#121212","--text-secondary":"#444","--text-on-orange":"#fff","--border-light":"#E8D5C0","--shadow-sm":"0 2px 8px rgba(0,0,0,0.08)","--shadow-md":"0 4px 20px rgba(0,0,0,0.12)","--shadow-lg":"0 8px 32px rgba(0,0,0,0.16)","--shadow-gold":"0 4px 20px rgba(255,140,0,0.35)","_dark":{"--orange":"#FF8C00","--orange-light":"#FFAA40","--orange-dark":"#CC7000","--gold":"#FF8C00","--gold-light":"#FFAA40","--gold-dark":"#CC7000","--bg-body":"#121212","--bg-navbar":"#121212","--bg-hero":"linear-gradient(135deg, #0A0A0A 0%, #1A1A1A 50%, #0A0A0A 100%)","--bg-section-alt":"#1A1A1A","--bg-card":"#1E1E1E","--text-primary":"#F5F0EB","--text-secondary":"#BBB","--text-on-orange":"#fff","--border-light":"#333","--shadow-sm":"0 2px 8px rgba(0,0,0,0.3)","--shadow-md":"0 4px 20px rgba(0,0,0,0.4)","--shadow-lg":"0 8px 32px rgba(0,0,0,0.5)","--shadow-gold":"0 4px 20px rgba(255,140,0,0.25)"}}', '{"enabled":false}'),
('Dark Elegance', 'dark-elegance', 'Dark mode with warm orange accents', '#FF8C00', 0, 0, '{"--orange":"#FF8C00","--orange-light":"#FFAA40","--orange-dark":"#CC7000","--gold":"#FF8C00","--gold-light":"#FFAA40","--gold-dark":"#CC7000","--bg-body":"#121212","--bg-navbar":"#1A1A1A","--bg-hero":"linear-gradient(135deg, #0A0A0A 0%, #1A1A1A 50%, #0A0A0A 100%)","--bg-section-alt":"#1A1A1A","--bg-card":"#1E1E1E","--text-primary":"#F5F0EB","--text-secondary":"#BBB","--text-on-orange":"#fff","--border-light":"#333","--shadow-sm":"0 2px 8px rgba(0,0,0,0.3)","--shadow-md":"0 4px 20px rgba(0,0,0,0.4)","--shadow-lg":"0 8px 32px rgba(0,0,0,0.5)","--shadow-gold":"0 4px 20px rgba(255,140,0,0.25)"}', '{"enabled":false}'),
('Christmas', 'christmas', 'Festive red, green and gold for the holiday season', '#D42426', 0, 0, '{"--orange":"#D42426","--orange-light":"#E85D5D","--orange-dark":"#A01B1B","--gold":"#FFD700","--gold-light":"#FFE44D","--gold-dark":"#B8960F","--bg-body":"#FEF5E7","--bg-navbar":"#D42426","--bg-hero":"linear-gradient(135deg, #D42426 0%, #2D8B2D 50%, #D42426 100%)","--bg-section-alt":"#E8F5E8","--bg-card":"#FFFFFF","--text-primary":"#1A1A1A","--text-secondary":"#555555","--text-on-orange":"#fff","--border-light":"#E0D5C8","--shadow-sm":"0 2px 8px rgba(0,0,0,0.08)","--shadow-md":"0 4px 20px rgba(0,0,0,0.12)","--shadow-lg":"0 8px 32px rgba(0,0,0,0.16)","--shadow-gold":"0 4px 20px rgba(255,215,0,0.35)"}', '{"enabled":true,"particles":"snow","particle_count":80,"badge_enabled":true,"badge_text_en":"Merry Christmas! 🎄","badge_text_sw":"Krismasi Njema! 🎄","badge_icon":"fa-tree"}'),
('Easter', 'easter', 'Soft pastel pinks, purples and yellows for Easter', '#FFB6C1', 0, 0, '{"--orange":"#E8A0BF","--orange-light":"#F2C4DF","--orange-dark":"#C2708A","--gold":"#FFB6C1","--gold-light":"#FFD1DC","--gold-dark":"#D4899B","--bg-body":"#FFF5F8","--bg-navbar":"#DDA0DD","--bg-hero":"linear-gradient(135deg, #FFB6C1 0%, #DDA0DD 50%, #FFFACD 100%)","--bg-section-alt":"#FFF0F5","--bg-card":"#FFFFFF","--text-primary":"#3A2A3A","--text-secondary":"#665566","--text-on-orange":"#fff","--border-light":"#E8D5E0","--shadow-sm":"0 2px 8px rgba(0,0,0,0.06)","--shadow-md":"0 4px 20px rgba(0,0,0,0.1)","--shadow-lg":"0 8px 32px rgba(0,0,0,0.14)","--shadow-gold":"0 4px 20px rgba(255,182,193,0.35)"}', '{"enabled":true,"particles":"confetti","particle_count":40,"badge_enabled":true,"badge_text_en":"Happy Easter! 🐣","badge_text_sw":"Pasaka Njema! 🐣","badge_icon":"fa-egg"}'),
('Saba Saba', 'saba-saba', 'Tanzania-inspired green, yellow and black for Saba Saba Day (July 7)', '#1EB53A', 0, 0, '{"--orange":"#FCD116","--orange-light":"#FDE16B","--orange-dark":"#C8A500","--gold":"#FCD116","--gold-light":"#FDE16B","--gold-dark":"#C8A500","--bg-body":"#F4F7F0","--bg-navbar":"#1EB53A","--bg-hero":"linear-gradient(135deg, #1EB53A 0%, #FCD116 50%, #000000 100%)","--bg-section-alt":"#E8F0E0","--bg-card":"#FFFFFF","--text-primary":"#1A1A1A","--text-secondary":"#4A4A4A","--text-on-orange":"#000","--border-light":"#D0D8C8","--shadow-sm":"0 2px 8px rgba(0,0,0,0.08)","--shadow-md":"0 4px 20px rgba(0,0,0,0.12)","--shadow-lg":"0 8px 32px rgba(0,0,0,0.16)","--shadow-gold":"0 4px 20px rgba(30,181,58,0.3)"}', '{"enabled":true,"particles":"confetti","particle_count":60,"badge_enabled":true,"badge_text_en":"Happy Saba Saba Day! 🇹🇿","badge_text_sw":"Heri ya Saba Saba! 🇹🇿","badge_icon":"fa-flag"}'),
('Maulidi', 'maulidi', 'Elegant green and gold for Maulidi celebrations', '#006837', 0, 0, '{"--orange":"#D4A847","--orange-light":"#E0C070","--orange-dark":"#A8832E","--gold":"#D4A847","--gold-light":"#E0C070","--gold-dark":"#A8832E","--bg-body":"#F7F5EF","--bg-navbar":"#006837","--bg-hero":"linear-gradient(135deg, #006837 0%, #D4A847 50%, #006837 100%)","--bg-section-alt":"#E8F0E4","--bg-card":"#FFFFFF","--text-primary":"#1A1A1A","--text-secondary":"#555555","--text-on-orange":"#fff","--border-light":"#D8D5C8","--shadow-sm":"0 2px 8px rgba(0,0,0,0.08)","--shadow-md":"0 4px 20px rgba(0,0,0,0.12)","--shadow-lg":"0 8px 32px rgba(0,0,0,0.16)","--shadow-gold":"0 4px 20px rgba(212,168,71,0.35)"}', '{"enabled":true,"particles":"stars","particle_count":30,"badge_enabled":true,"badge_text_en":"Happy Maulidi! ☪","badge_text_sw":"Maulidi Mbarikiwa! ☪","badge_icon":"fa-star-and-crescent"}'),
('Eid Mubarak', 'eid-mubarak', 'Celebratory green, gold and white for Eid festivities', '#2E7D32', 0, 0, '{"--orange":"#2E7D32","--orange-light":"#4CAF50","--orange-dark":"#1B5E20","--gold":"#FFD700","--gold-light":"#FFE44D","--gold-dark":"#C8A600","--bg-body":"#F5F7F2","--bg-navbar":"#2E7D32","--bg-hero":"linear-gradient(135deg, #2E7D32 0%, #FFD700 50%, #2E7D32 100%)","--bg-section-alt":"#E8F0E4","--bg-card":"#FFFFFF","--text-primary":"#1A1A1A","--text-secondary":"#555555","--text-on-orange":"#fff","--border-light":"#D0D8C8","--shadow-sm":"0 2px 8px rgba(0,0,0,0.08)","--shadow-md":"0 4px 20px rgba(0,0,0,0.12)","--shadow-lg":"0 8px 32px rgba(0,0,0,0.16)","--shadow-gold":"0 4px 20px rgba(255,215,0,0.35)"}', '{"enabled":true,"particles":"stars","particle_count":40,"badge_enabled":true,"badge_text_en":"Eid Mubarak! 🌙","badge_text_sw":"Eid Mubarak! 🌙","badge_icon":"fa-moon"}'),
('Independence', 'independence', 'Patriotic blue, gold and green for Independence Day', '#0066CC', 0, 0, '{"--orange":"#0066CC","--orange-light":"#3388DD","--orange-dark":"#004499","--gold":"#FFD700","--gold-light":"#FFE44D","--gold-dark":"#B8960F","--bg-body":"#F0F4F8","--bg-navbar":"#0066CC","--bg-hero":"linear-gradient(135deg, #0066CC 0%, #FFD700 50%, #1EB53A 100%)","--bg-section-alt":"#E0ECF8","--bg-card":"#FFFFFF","--text-primary":"#1A1A1A","--text-secondary":"#4A5568","--text-on-orange":"#fff","--border-light":"#C8D0D8","--shadow-sm":"0 2px 8px rgba(0,0,0,0.08)","--shadow-md":"0 4px 20px rgba(0,0,0,0.12)","--shadow-lg":"0 8px 32px rgba(0,0,0,0.16)","--shadow-gold":"0 4px 20px rgba(0,102,204,0.3)"}', '{"enabled":true,"particles":"confetti","particle_count":60,"badge_enabled":true,"badge_text_en":"Happy Independence Day! 🎉","badge_text_sw":"Heri ya Uhuru! 🎉","badge_icon":"fa-flag"}');

INSERT INTO users (name, email, password, phone, role) VALUES
('Admin', 'mahuvechristian@gmail.com', '$2b$12$PoYnRKbRr8brJ3/ymQIIyOUPa1k7LpUtOushwzc1/A77DlWIJn7ry', '+255712345678', 'admin');

INSERT INTO categories (name_en, name_sw, slug) VALUES
('Dresses', 'Magauni', 'dresses'),
('Evening Dresses', 'Magauni ya Sherehe', 'evening-dresses'),
('Abaya', 'Abaya', 'abaya'),
('T-Shirts', 'Fulana', 't-shirts'),
('Blouses', 'Blauzi', 'blouses'),
('Body Suits', 'Body Suits', 'body-suits'),
('Tops', 'Tops', 'tops'),
('Jeans', 'Jeans', 'jeans'),
('Flare Jeans', 'Jeans Flare', 'flare-jeans'),
('Cargo Pants', 'Suruali za Cargo', 'cargo-pants'),
('Skirts', 'Sketi', 'skirts'),
('Official Trousers', 'Suruali za Ofisi', 'official-trousers'),
('Bwanga', 'Bwanga', 'bwanga'),
('Body Shapers', 'Body Shaper', 'body-shapers'),
('Two Pieces', 'Two Pieces', 'two-pieces'),
('Jumpsuits', 'Jamsuit', 'jumpsuits'),
('Sweaters', 'Masweta', 'sweaters'),
('Blazers', 'Blaza', 'blazers'),
('Jeans Jackets', 'Makoti ya Jeans', 'jeans-jackets'),
('Ponchos', 'Poncho', 'ponchos');
