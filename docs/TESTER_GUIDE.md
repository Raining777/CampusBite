# CampusBite Tester Guide

Open the URL provided by the developer. For the default local installation, use `http://127.0.0.1:8080/`. Choose **Sign in** and enter a username below. All accounts use `CampusDemo!2026` on the seeded test database.

| Role | Username | Starting data |
| --- | --- | --- |
| Student | `student1` | North Hall pickup order `CB-00001` |
| Student | `student2` | The Commons delivery order `CB-00002` |
| Vendor | `northhall` | North Hall Kitchen |
| Vendor | `commons` | The Commons Cafe |
| Administrator | `campusadmin` | Student, kitchen and vendor account management |

## Student

1. Use **Create account** to register with a unique username and email. Use 3–30 characters for the username and at least 8 characters for the password, including a number and a special character.
2. Open **Menu** and search for `tofu`, select a kitchen, or choose a category. Add a dish and use **Cart** to change its quantity, remove it, or review your subtotal. Select an item from the other kitchen to check the switch prompt.
3. Continue to **Checkout**. Place a self-pickup order. Place a second order with **Campus delivery** and enter a campus meeting point. Record the order numbers and check **My orders** after each vendor update.

## Vendor and administrator

1. As the appropriate vendor, open **Orders**, select an order and advance it. The next stage after **Preparing** is **Ready for Pickup** for pickup, or **Out for Delivery** for delivery. Advance once more to **Completed**.
2. Under **Categories** and **Menu items**, add a category and a dish and check its student-facing visibility. Edit its price, then confirm the value on an existing order still matches the checkout price.
3. As administrator, check **Students**, **Kitchens** and **Vendor accounts**. Disable and re-enable a student or kitchen. Confirm sign-in or ordering access changes and check a previous order from an active student account.

Use an incognito window or separate browser profile for each role. Record the URL, order number, steps, expected result and actual result for each finding. The developer can provide a fresh seed database for another test cycle.
