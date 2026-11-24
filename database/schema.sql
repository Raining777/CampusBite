CREATE TABLE IF NOT EXISTS vendors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    pickup_location VARCHAR(150) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(30) NOT NULL UNIQUE,
    email VARCHAR(254) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student','vendor','admin') NOT NULL DEFAULT 'student',
    vendor_id INT UNSIGNED NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    auth_version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id),
    CHECK ((role = 'vendor' AND vendor_id IS NOT NULL) OR (role <> 'vendor' AND vendor_id IS NULL)),
    INDEX users_role_status (role, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT UNSIGNED NOT NULL,
    name VARCHAR(60) NOT NULL,
    archived_at DATETIME NULL,
    UNIQUE (vendor_id, id),
    UNIQUE (vendor_id, name),
    FOREIGN KEY (vendor_id) REFERENCES vendors(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS menu_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(500) NOT NULL,
    price DECIMAL(10,2) NOT NULL CHECK (price > 0),
    photo_slot TINYINT UNSIGNED NULL CHECK (photo_slot BETWEEN 1 AND 6),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    archived_at DATETIME NULL,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id),
    FOREIGN KEY (vendor_id, category_id) REFERENCES categories(vendor_id, id),
    INDEX menu_vendor_visibility (vendor_id, is_active, archived_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cart_items (
    user_id INT UNSIGNED NOT NULL,
    menu_item_id INT UNSIGNED NOT NULL,
    quantity TINYINT UNSIGNED NOT NULL CHECK (quantity BETWEEN 1 AND 99),
    PRIMARY KEY (user_id, menu_item_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    vendor_id INT UNSIGNED NOT NULL,
    vendor_name VARCHAR(100) NOT NULL,
    pickup_location VARCHAR(150) NOT NULL,
    total DECIMAL(12,2) NOT NULL CHECK (total > 0),
    fulfillment ENUM('pickup','delivery') NOT NULL,
    delivery_location VARCHAR(150) NULL,
    status ENUM('pending','preparing','ready_for_pickup','out_for_delivery','completed') NOT NULL DEFAULT 'pending',
    submission_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (vendor_id) REFERENCES vendors(id),
    UNIQUE (student_id, submission_key),
    INDEX orders_student_date (student_id, created_at, id),
    INDEX orders_vendor_status_date (vendor_id, status, created_at, id),
    CHECK ((fulfillment = 'pickup' AND delivery_location IS NULL) OR (fulfillment = 'delivery' AND delivery_location IS NOT NULL)),
    CHECK ((fulfillment = 'pickup' AND status <> 'out_for_delivery') OR (fulfillment = 'delivery' AND status <> 'ready_for_pickup'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    menu_item_id INT UNSIGNED NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL CHECK (unit_price > 0),
    quantity TINYINT UNSIGNED NOT NULL CHECK (quantity BETWEEN 1 AND 99),
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    old_status ENUM('pending','preparing','ready_for_pickup','out_for_delivery','completed') NULL,
    new_status ENUM('pending','preparing','ready_for_pickup','out_for_delivery','completed') NOT NULL,
    changed_by INT UNSIGNED NOT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (changed_by) REFERENCES users(id),
    INDEX history_order (order_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
