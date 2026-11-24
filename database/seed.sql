INSERT INTO vendors (id, name, pickup_location) VALUES
    (1, 'North Hall Kitchen', 'North Hall, Ground Floor, Counter 2'),
    (2, 'The Commons Cafe', 'Student Commons, Ground Floor, Cafe Counter');

-- All demo accounts use CampusDemo!2026. Use seed data on an isolated test database.
INSERT INTO users (id, username, email, password_hash, role, vendor_id) VALUES
    (1, 'student1', 'student1@campusbite.test', '$2y$12$LxZp.6d8dhosAqMOViCz2eb87HvIH62AL1qeSZfmH8NwPWLqJATQi', 'student', NULL),
    (2, 'student2', 'student2@campusbite.test', '$2y$12$LxZp.6d8dhosAqMOViCz2eb87HvIH62AL1qeSZfmH8NwPWLqJATQi', 'student', NULL),
    (3, 'northhall', 'northhall@campusbite.test', '$2y$12$LxZp.6d8dhosAqMOViCz2eb87HvIH62AL1qeSZfmH8NwPWLqJATQi', 'vendor', 1),
    (4, 'commons', 'commons@campusbite.test', '$2y$12$LxZp.6d8dhosAqMOViCz2eb87HvIH62AL1qeSZfmH8NwPWLqJATQi', 'vendor', 2),
    (5, 'campusadmin', 'admin@campusbite.test', '$2y$12$LxZp.6d8dhosAqMOViCz2eb87HvIH62AL1qeSZfmH8NwPWLqJATQi', 'admin', NULL);

INSERT INTO categories (id, vendor_id, name) VALUES
    (1, 1, 'Bowls'), (2, 1, 'Sides'), (3, 2, 'Sandwiches'), (4, 2, 'Drinks');

INSERT INTO menu_items (id, vendor_id, category_id, name, description, price, photo_slot, is_active) VALUES
    (1, 1, 1, 'Korean Chicken Bowl', 'Sticky gochujang chicken with rice, greens and sesame.', 14.90, 1, 1),
    (2, 1, 1, 'Sesame Tofu Bowl', 'Golden tofu, edamame and fresh vegetables with sesame dressing.', 13.50, 2, 1),
    (3, 1, 2, 'Pan-fried Gyoza', 'Five crisp vegetable dumplings with a soy dipping sauce.', 7.90, 3, 1),
    (4, 2, 3, 'Chicken Caesar Wrap', 'Grilled chicken, crisp romaine and parmesan in a toasted wrap.', 12.90, 4, 1),
    (5, 2, 3, 'Caprese Toastie', 'Grilled sourdough with mozzarella, tomato and fresh basil.', 11.90, 5, 1),
    (6, 2, 4, 'Iced Matcha Latte', 'Whisked matcha and chilled milk served over ice.', 9.50, 6, 1),
    (7, 2, 4, 'Cold Brew Coffee', 'Slow-steeped coffee served over ice.', 8.00, NULL, 0);

INSERT INTO orders (id, student_id, vendor_id, vendor_name, pickup_location, total, fulfillment, delivery_location, status, submission_key) VALUES
    (1, 1, 1, 'North Hall Kitchen', 'North Hall, Ground Floor, Counter 2', 22.80, 'pickup', NULL, 'pending', SHA2('demo-pickup', 256)),
    (2, 2, 2, 'The Commons Cafe', 'Student Commons, Ground Floor, Cafe Counter', 22.40, 'delivery', 'Library, Level 1 entrance', 'pending', SHA2('demo-delivery', 256));

INSERT INTO order_items (order_id, menu_item_id, item_name, unit_price, quantity) VALUES
    (1, 1, 'Korean Chicken Bowl', 14.90, 1), (1, 3, 'Pan-fried Gyoza', 7.90, 1),
    (2, 4, 'Chicken Caesar Wrap', 12.90, 1), (2, 6, 'Iced Matcha Latte', 9.50, 1);

INSERT INTO order_status_history (order_id, new_status, changed_by) VALUES (1, 'pending', 1), (2, 'pending', 2);
