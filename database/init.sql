SET NAMES utf8mb4;
SET time_zone = '+05:00';
SET FOREIGN_KEY_CHECKS = 0;

-- Baseline предназначен для новой демонстрационной базы и пересоздаёт схему целиком.
-- Для существующих данных применяются отдельные файлы из database/migrations/.
DROP TABLE IF EXISTS password_reset_tokens;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS housekeeping_tasks;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS booking_services;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS service_categories;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS clients;
DROP TABLE IF EXISTS associates;
DROP TABLE IF EXISTS rooms;
DROP TABLE IF EXISTS room_types;
DROP TABLE IF EXISTS floors;
DROP TABLE IF EXISTS room_views;
DROP TABLE IF EXISTS bathroom_types;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS schema_migrations;

CREATE TABLE schema_migrations (
  version VARCHAR(50) NOT NULL,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Справочники и продаваемые типы номеров.
CREATE TABLE categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  description TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY categories_name_unique (name),
  UNIQUE KEY categories_slug_unique (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE room_views (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY room_views_name_unique (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bathroom_types (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY bathroom_types_name_unique (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE floors (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  level INT NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY floors_level_unique (level),
  CONSTRAINT floors_level_chk CHECK (level >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE room_types (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id INT UNSIGNED NOT NULL,
  view_id INT UNSIGNED NOT NULL,
  bathroom_type_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL,
  short_description VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  base_price DECIMAL(10,2) NOT NULL,
  capacity_adults TINYINT UNSIGNED NOT NULL DEFAULT 2,
  capacity_children TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bed_count TINYINT UNSIGNED NOT NULL DEFAULT 1,
  size_sqm DECIMAL(6,2) DEFAULT NULL,
  bathroom_separate TINYINT(1) NOT NULL DEFAULT 0,
  photo VARCHAR(255) DEFAULT NULL,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY room_types_name_unique (name),
  UNIQUE KEY room_types_slug_unique (slug),
  KEY room_types_category_idx (category_id),
  KEY room_types_view_idx (view_id),
  KEY room_types_bathroom_idx (bathroom_type_id),
  CONSTRAINT room_types_category_fk FOREIGN KEY (category_id) REFERENCES categories (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT room_types_view_fk FOREIGN KEY (view_id) REFERENCES room_views (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT room_types_bathroom_fk FOREIGN KEY (bathroom_type_id) REFERENCES bathroom_types (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT room_types_price_chk CHECK (base_price >= 0),
  CONSTRAINT room_types_capacity_chk CHECK (capacity_adults >= 1),
  CONSTRAINT room_types_bed_count_chk CHECK (bed_count >= 1),
  CONSTRAINT room_types_flags_chk CHECK (
    bathroom_separate IN (0, 1) AND featured IN (0, 1) AND active IN (0, 1)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Физический фонд отделён от типа номера: продаётся категория, а комната назначается позже.
CREATE TABLE rooms (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  room_type_id INT UNSIGNED NOT NULL,
  floor_id INT UNSIGNED NOT NULL,
  number VARCHAR(20) NOT NULL,
  status ENUM('available', 'occupied', 'dirty', 'cleaning', 'maintenance') NOT NULL DEFAULT 'available',
  notes VARCHAR(255) DEFAULT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY rooms_number_unique (number),
  KEY rooms_type_idx (room_type_id),
  KEY rooms_floor_idx (floor_id),
  KEY rooms_status_idx (status),
  CONSTRAINT rooms_type_fk FOREIGN KEY (room_type_id) REFERENCES room_types (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT rooms_floor_fk FOREIGN KEY (floor_id) REFERENCES floors (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT rooms_active_chk CHECK (active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Карточки гостей, сотрудников и связанные учётные записи.
CREATE TABLE clients (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  last_name VARCHAR(50) NOT NULL,
  first_name VARCHAR(50) NOT NULL,
  middle_name VARCHAR(50) DEFAULT NULL,
  birth_date DATE DEFAULT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  preferences TEXT DEFAULT NULL,
  marketing_consent TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY clients_email_unique (email),
  UNIQUE KEY clients_phone_unique (phone),
  CONSTRAINT clients_marketing_chk CHECK (marketing_consent IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE associates (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  last_name VARCHAR(50) NOT NULL,
  first_name VARCHAR(50) NOT NULL,
  middle_name VARCHAR(50) DEFAULT NULL,
  position VARCHAR(80) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  birth_date DATE DEFAULT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY associates_email_unique (email),
  UNIQUE KEY associates_phone_unique (phone),
  CONSTRAINT associates_active_chk CHECK (active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id INT UNSIGNED DEFAULT NULL,
  associate_id INT UNSIGNED DEFAULT NULL,
  login VARCHAR(64) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('guest', 'admin', 'manager', 'reception', 'housekeeping', 'analyst') NOT NULL DEFAULT 'guest',
  active TINYINT(1) NOT NULL DEFAULT 1,
  privacy_accepted_at TIMESTAMP NULL DEFAULT NULL,
  last_login_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY users_login_unique (login),
  UNIQUE KEY users_email_unique (email),
  UNIQUE KEY users_client_unique (client_id),
  UNIQUE KEY users_associate_unique (associate_id),
  KEY users_role_idx (role),
  CONSTRAINT users_client_fk FOREIGN KEY (client_id) REFERENCES clients (id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT users_associate_fk FOREIGN KEY (associate_id) REFERENCES associates (id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT users_active_chk CHECK (active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Бронирование хранит снимок финансового итога, не зависящий от будущего изменения прайса.
CREATE TABLE bookings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(24) NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  associate_id INT UNSIGNED DEFAULT NULL,
  room_type_id INT UNSIGNED NOT NULL,
  room_id INT UNSIGNED DEFAULT NULL,
  date_in DATE NOT NULL,
  date_out DATE NOT NULL,
  status ENUM('pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'no_show') NOT NULL DEFAULT 'pending',
  adults TINYINT UNSIGNED NOT NULL DEFAULT 1,
  children TINYINT UNSIGNED NOT NULL DEFAULT 0,
  source ENUM('website', 'phone', 'walk_in', 'admin') NOT NULL DEFAULT 'website',
  guest_comment TEXT DEFAULT NULL,
  admin_note TEXT DEFAULT NULL,
  accommodation_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  services_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  cancelled_at TIMESTAMP NULL DEFAULT NULL,
  checked_in_at TIMESTAMP NULL DEFAULT NULL,
  checked_out_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY bookings_code_unique (code),
  KEY bookings_client_idx (client_id),
  KEY bookings_associate_idx (associate_id),
  KEY bookings_room_type_status_dates_idx (room_type_id, status, date_in, date_out),
  KEY bookings_room_status_dates_idx (room_id, status, date_in, date_out),
  CONSTRAINT bookings_client_fk FOREIGN KEY (client_id) REFERENCES clients (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT bookings_associate_fk FOREIGN KEY (associate_id) REFERENCES associates (id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT bookings_room_type_fk FOREIGN KEY (room_type_id) REFERENCES room_types (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT bookings_room_fk FOREIGN KEY (room_id) REFERENCES rooms (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT bookings_dates_chk CHECK (date_out > date_in),
  CONSTRAINT bookings_guests_chk CHECK (adults >= 1),
  CONSTRAINT bookings_totals_chk CHECK (
    accommodation_total >= 0 AND services_total >= 0 AND discount_total >= 0 AND total >= 0
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Каталог и заказы дополнительных услуг.
CREATE TABLE service_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  sort_order INT NOT NULL DEFAULT 100,
  PRIMARY KEY (id),
  UNIQUE KEY service_categories_name_unique (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE services (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL,
  description TEXT NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  pricing_type ENUM('per_order', 'per_day', 'per_person', 'per_unit') NOT NULL DEFAULT 'per_order',
  icon VARCHAR(50) DEFAULT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY services_slug_unique (slug),
  KEY services_category_idx (category_id),
  CONSTRAINT services_category_fk FOREIGN KEY (category_id) REFERENCES service_categories (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT services_price_chk CHECK (price >= 0),
  CONSTRAINT services_active_chk CHECK (active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE booking_services (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id INT UNSIGNED NOT NULL,
  service_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  unit_price DECIMAL(10,2) NOT NULL,
  total DECIMAL(10,2) NOT NULL,
  status ENUM('new', 'confirmed', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'new',
  scheduled_for DATETIME DEFAULT NULL,
  guest_note VARCHAR(500) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY booking_services_booking_idx (booking_id),
  KEY booking_services_service_idx (service_id),
  KEY booking_services_status_idx (status),
  CONSTRAINT booking_services_booking_fk FOREIGN KEY (booking_id) REFERENCES bookings (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT booking_services_service_fk FOREIGN KEY (service_id) REFERENCES services (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT booking_services_quantity_chk CHECK (quantity >= 1),
  CONSTRAINT booking_services_totals_chk CHECK (unit_price >= 0 AND total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Платежи, эксплуатационные задачи, отзывы и аудит.
CREATE TABLE payments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id INT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  method ENUM('cash', 'card', 'sandbox') NOT NULL DEFAULT 'sandbox',
  status ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
  provider_reference VARCHAR(100) DEFAULT NULL,
  paid_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY payments_booking_idx (booking_id),
  KEY payments_status_idx (status),
  CONSTRAINT payments_booking_fk FOREIGN KEY (booking_id) REFERENCES bookings (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT payments_amount_chk CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE housekeeping_tasks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  room_id INT UNSIGNED NOT NULL,
  booking_id INT UNSIGNED DEFAULT NULL,
  assigned_to INT UNSIGNED DEFAULT NULL,
  task_type ENUM('cleaning', 'inspection', 'maintenance') NOT NULL DEFAULT 'cleaning',
  status ENUM('pending', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
  priority ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
  due_at DATETIME DEFAULT NULL,
  notes VARCHAR(500) DEFAULT NULL,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY housekeeping_room_idx (room_id),
  KEY housekeeping_booking_idx (booking_id),
  KEY housekeeping_associate_idx (assigned_to),
  KEY housekeeping_status_idx (status),
  CONSTRAINT housekeeping_room_fk FOREIGN KEY (room_id) REFERENCES rooms (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT housekeeping_booking_fk FOREIGN KEY (booking_id) REFERENCES bookings (id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT housekeeping_associate_fk FOREIGN KEY (assigned_to) REFERENCES associates (id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reviews (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id INT UNSIGNED NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  comment TEXT DEFAULT NULL,
  status ENUM('pending', 'published', 'rejected') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY reviews_booking_unique (booking_id),
  KEY reviews_client_idx (client_id),
  CONSTRAINT reviews_booking_fk FOREIGN KEY (booking_id) REFERENCES bookings (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT reviews_client_fk FOREIGN KEY (client_id) REFERENCES clients (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT reviews_rating_chk CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED DEFAULT NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) DEFAULT NULL,
  entity_id VARCHAR(80) DEFAULT NULL,
  payload_json JSON DEFAULT NULL,
  ip_hash CHAR(64) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY audit_user_idx (user_id),
  KEY audit_entity_idx (entity_type, entity_id),
  KEY audit_created_idx (created_at),
  CONSTRAINT audit_user_fk FOREIGN KEY (user_id) REFERENCES users (id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_reset_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY password_reset_token_unique (token_hash),
  KEY password_reset_user_idx (user_id),
  CONSTRAINT password_reset_user_fk FOREIGN KEY (user_id) REFERENCES users (id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Демонстрационный набор покрывает разные роли, статусы броней и операционные сценарии.
INSERT INTO categories (name, slug, description) VALUES
  ('Стандарт', 'standard', 'Практичные номера для коротких и деловых поездок.'),
  ('Комфорт', 'comfort', 'Больше пространства и дополнительные удобства.'),
  ('Люкс', 'suite', 'Просторные номера для особенного отдыха.');

INSERT INTO room_views (name) VALUES
  ('Город'),
  ('Парк'),
  ('Внутренний двор');

INSERT INTO bathroom_types (name) VALUES
  ('Душевая кабина'),
  ('Ванна'),
  ('Ванна и душевая');

INSERT INTO floors (name, level) VALUES
  ('1 этаж', 1),
  ('2 этаж', 2),
  ('3 этаж', 3);

INSERT INTO room_types (
  category_id, view_id, bathroom_type_id, name, slug, short_description, description,
  base_price, capacity_adults, capacity_children, bed_count, size_sqm,
  bathroom_separate, photo, featured
) VALUES
  (1, 3, 1, 'Стандарт', 'standard', 'Уютный номер для одного или двух гостей.',
   'Светлый номер с рабочим местом, Wi-Fi, телевизором и собственной ванной комнатой.',
   2500.00, 2, 0, 1, 20.00, 0, 'Stan.jpg', 1),
  (2, 2, 2, 'Стандарт улучшенный', 'superior-standard', 'Комфортный номер с видом на парк.',
   'Улучшенный стандарт с просторной зоной отдыха, кондиционером и ванной.',
   3500.00, 2, 1, 1, 28.00, 1, 'StUl.jpg', 1),
  (3, 1, 3, 'Люкс', 'suite', 'Просторный номер для семьи или особенного отдыха.',
   'Двухзонный номер с гостиной, большой кроватью и панорамным видом на город.',
   6000.00, 3, 1, 2, 45.00, 1, 'Lux.jpg', 1),
  (2, 3, 1, 'Семейный', 'family', 'Семейный номер с дополнительными спальными местами.',
   'Функциональный номер для семьи с детьми, зоной отдыха и вместительным хранением.',
   4800.00, 2, 2, 3, 38.00, 0, 'room-4.jpg', 0),
  (2, 2, 2, 'Комфорт Twin', 'comfort-twin', 'Две отдельные кровати и тихий вид на парк.',
   'Номер для коллег или друзей с двумя полноценными кроватями и рабочей зоной.',
   3900.00, 2, 0, 2, 30.00, 0, 'room-5.jpg', 0),
  (3, 1, 3, 'Панорамный люкс', 'panoramic-suite', 'Максимальный комфорт и лучший вид отеля.',
   'Премиальный номер с гостиной, спальней, панорамными окнами и поздним выездом.',
   9500.00, 4, 2, 2, 62.00, 1, 'room-6.jpg', 1);

INSERT INTO rooms (room_type_id, floor_id, number, status) VALUES
  (1, 1, '101', 'available'),
  (1, 1, '102', 'available'),
  (1, 1, '103', 'dirty'),
  (2, 2, '201', 'available'),
  (2, 2, '202', 'available'),
  (4, 2, '203', 'available'),
  (5, 2, '204', 'maintenance'),
  (3, 3, '301', 'available'),
  (3, 3, '302', 'available'),
  (6, 3, '303', 'available');

INSERT INTO clients (
  last_name, first_name, middle_name, birth_date, email, phone, preferences, marketing_consent
) VALUES
  ('Соколова', 'Мария', 'Андреевна', '1995-05-14', 'guest@hotel.localhost', '+7 922 000-10-10',
   'Высокий этаж, тихий номер', 1),
  ('Волков', 'Алексей', 'Игоревич', '1988-11-02', 'alexey@example.test', '+7 922 000-20-20',
   'Дополнительная подушка', 0);

INSERT INTO associates (
  last_name, first_name, middle_name, position, email, phone, birth_date, active
) VALUES
  ('Орлова', 'Анна', 'Сергеевна', 'Управляющая', 'admin@hotel.localhost', '+7 922 100-10-10', '1987-04-21', 1),
  ('Морозов', 'Илья', 'Петрович', 'Менеджер', 'manager@hotel.localhost', '+7 922 100-20-20', '1991-08-12', 1),
  ('Лебедева', 'Елена', 'Викторовна', 'Администратор стойки', 'reception@hotel.localhost', '+7 922 100-30-30', '1997-02-18', 1),
  ('Кузнецова', 'Ольга', 'Ивановна', 'Старшая горничная', 'housekeeping@hotel.localhost', '+7 922 100-40-40', '1989-09-09', 1);

INSERT INTO users (
  client_id, associate_id, login, email, password_hash, role, active, privacy_accepted_at
) VALUES
  (NULL, 1, 'hotel_admin', 'admin@hotel.localhost',
   '$2y$10$6HsAv70sgq/7xVScdm3xKeM9TlSbwgJrPwm/kYQEGtjxB/O9yYxWm', 'admin', 1, NOW()),
  (NULL, 2, 'manager', 'manager@hotel.localhost',
   '$2y$10$FKzxvmstv8ofrmbrm4TdWupRZgLaA9X.1rsDaXoeZz8hgZE1oKQ/S', 'manager', 1, NOW()),
  (NULL, 3, 'reception', 'reception@hotel.localhost',
   '$2y$10$FKzxvmstv8ofrmbrm4TdWupRZgLaA9X.1rsDaXoeZz8hgZE1oKQ/S', 'reception', 1, NOW()),
  (NULL, 4, 'housekeeping', 'housekeeping@hotel.localhost',
   '$2y$10$FKzxvmstv8ofrmbrm4TdWupRZgLaA9X.1rsDaXoeZz8hgZE1oKQ/S', 'housekeeping', 1, NOW()),
  (1, NULL, 'guest@hotel.localhost', 'guest@hotel.localhost',
   '$2y$10$9XzM6hsaOXRvdCwKsC3SNefqGJZZZDSc8c4Ud1AWaWhkZbHSYQq6a', 'guest', 1, NOW());

INSERT INTO bookings (
  code, client_id, associate_id, room_type_id, room_id, date_in, date_out, status,
  adults, children, source, guest_comment, accommodation_total, services_total, total,
  checked_in_at, checked_out_at
) VALUES
  ('HTL-DEMO-001', 1, 3, 2, NULL, DATE_ADD(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 8 DAY),
   'confirmed', 2, 0, 'website', 'Нужен тихий номер', 10500.00, 1500.00, 12000.00, NULL, NULL),
  ('HTL-DEMO-002', 2, 3, 1, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 2 DAY),
   'checked_in', 1, 0, 'phone', NULL, 7500.00, 500.00, 8000.00, NOW(), NULL),
  ('HTL-DEMO-003', 1, 2, 3, 8, DATE_SUB(CURDATE(), INTERVAL 45 DAY), DATE_SUB(CURDATE(), INTERVAL 42 DAY),
   'checked_out', 2, 1, 'website', NULL, 18000.00, 2200.00, 20200.00,
   DATE_SUB(NOW(), INTERVAL 45 DAY), DATE_SUB(NOW(), INTERVAL 42 DAY));

INSERT INTO service_categories (name, sort_order) VALUES
  ('Питание', 10),
  ('Транспорт', 20),
  ('Комфорт', 30),
  ('Отдых', 40);

INSERT INTO services (category_id, name, slug, description, price, pricing_type, icon, active) VALUES
  (1, 'Завтрак', 'breakfast', 'Завтрак в формате шведского стола с 07:00 до 10:30.', 750.00, 'per_person', 'coffee', 1),
  (2, 'Трансфер из аэропорта', 'airport-transfer', 'Встреча с табличкой и доставка до отеля.', 1800.00, 'per_order', 'car', 1),
  (3, 'Ранний заезд', 'early-check-in', 'Заселение с 09:00 при наличии подготовленного номера.', 1200.00, 'per_order', 'clock', 1),
  (3, 'Поздний выезд', 'late-check-out', 'Продление проживания до 18:00.', 1500.00, 'per_order', 'clock', 1),
  (3, 'Дополнительная кровать', 'extra-bed', 'Подготовленная дополнительная кровать с комплектом белья.', 900.00, 'per_day', 'bed', 1),
  (4, 'Сауна', 'sauna', 'Час приватного посещения сауны для двух гостей.', 2200.00, 'per_unit', 'spa', 1);

INSERT INTO booking_services (
  booking_id, service_id, quantity, unit_price, total, status, scheduled_for, guest_note
) VALUES
  (1, 1, 2, 750.00, 1500.00, 'confirmed', DATE_ADD(CURDATE(), INTERVAL 6 DAY), NULL),
  (2, 1, 1, 500.00, 500.00, 'completed', CURDATE(), 'Без лактозы'),
  (3, 6, 1, 2200.00, 2200.00, 'completed', DATE_SUB(NOW(), INTERVAL 44 DAY), NULL);

INSERT INTO payments (booking_id, amount, method, status, provider_reference, paid_at) VALUES
  (1, 6000.00, 'sandbox', 'paid', 'demo_pay_001', NOW()),
  (2, 8000.00, 'card', 'paid', 'frontdesk_002', DATE_SUB(NOW(), INTERVAL 1 DAY)),
  (3, 20200.00, 'card', 'paid', 'frontdesk_003', DATE_SUB(NOW(), INTERVAL 42 DAY));

INSERT INTO housekeeping_tasks (
  room_id, booking_id, assigned_to, task_type, status, priority, due_at, notes, completed_at
) VALUES
  (3, NULL, 4, 'cleaning', 'pending', 'high', DATE_ADD(NOW(), INTERVAL 1 HOUR), 'Подготовить к вечернему заезду', NULL),
  (1, 2, 4, 'inspection', 'pending', 'normal', DATE_ADD(NOW(), INTERVAL 2 DAY), 'Проверка после выезда', NULL),
  (7, NULL, NULL, 'maintenance', 'in_progress', 'urgent', NOW(), 'Диагностика кондиционера', NULL);

INSERT INTO reviews (booking_id, client_id, rating, comment, status) VALUES
  (3, 1, 5, 'Очень чистый номер и внимательный персонал.', 'published');

INSERT INTO schema_migrations (version) VALUES ('2026-09-product-mvp');

SET FOREIGN_KEY_CHECKS = 1;
