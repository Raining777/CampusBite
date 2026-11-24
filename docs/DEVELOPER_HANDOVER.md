# CampusBite Developer Handover — SA3 Phase 1

Prepared for the CampusBite developer team (Huang Jinning, SWE2409020 · Kim Maxim, SWE2409091), SWE301 Group Project, November 2026.

## 1. Versions

| Ref | Branch / tag | Contents | Use |
| --- | --- | --- | --- |
| **Clean build** | `main` | Complete application, all 8 profile functions, no known defects | Course deliverables, final corrected code (Phase 3), performance testing baseline |
| **Test build** | `sa3-test-v1` (tag `sa3-test-v1`) | Clean build + 4 seeded defects | Hand this exact source to the partner test group in Phase 2 |

The hidden bug log describing the 4 seeded defects is **not** in this repository. It is uploaded to the hidden Moodle section before Phase 2 (file kept locally as `docs/HIDDEN_BUG_LOG.md`, gitignored). Reveal it only after the partner group has reported their findings.

## 2. Architecture

Plain PHP 8.4 with PDO (no framework), MySQL 8.4, one self-hosted CSS design system and vanilla JavaScript. Single entry point `public/index.php` routes by `?page=`; POST actions route through `app/actions.php`. Only `public/` is web-reachable.

```
public/index.php        routing, page data, global headers (CSP, cache, referrer policy)
app/bootstrap.php       config, PDO, session, CSRF token, helpers (e, url, notice, money…)
app/accounts.php        registration, sign-in, account lifecycle (roles, auth_version)
app/ordering.php        menu browse/search, cart, checkout, order lifecycle
app/management.php      vendor menu/category management, admin students/kitchens/vendor accounts
app/actions.php         POST action table, CSRF check, redirects, error notices
app/views.php           shared layout (header, nav, footer, notices, empty states)
app/student-view.php    student pages (menu, auth, cart, checkout, orders)
app/workspace-view.php  vendor and admin pages
app/order-view.php      order detail: items, history, progress stepper, advance button
app/orders-table.php    shared order table
bin/install.php         schema + demo seed installer (CLI)
database/schema.sql     tables, constraints (CHECK, unique, foreign keys)
database/seed.sql       demo data, test accounts
public/assets/app.css   design system (tokens, components, responsive)
public/assets/app.js    steppers, strength hint, double-submit guard, sticky header
docs/TESTER_GUIDE.md    one-page user guide for the partner test group
```

## 3. Feature map (Project Profile functions F01–F08)

| ID | Function | Files |
| --- | --- | --- |
| F01 | Student registration and role-based sign-in (server-side validation, bcrypt) | `app/accounts.php` |
| F02 | Menu browsing, search, kitchen and category filters | `app/ordering.php`, `app/student-view.php` |
| F03 | Persistent single-kitchen shopping cart with quantity updates | `app/ordering.php` |
| F04 | Order placement: self-pickup or campus delivery | `app/ordering.php` |
| F05 | Order status tracking with history (Pending → Preparing → Ready for Pickup / Out for Delivery → Completed) | `app/order-view.php`, `app/ordering.php` |
| F06 | Vendor menu management: items, categories, prices, availability | `app/management.php` |
| F07 | Vendor order queue with status updates | `app/ordering.php`, `app/workspace-view.php` |
| F08 | Admin management of students, kitchens and vendor accounts | `app/management.php`, `app/workspace-view.php` |

Registration rules (all enforced server-side): username required, unique, 3–30 chars; email valid format and unique; password at least 8 chars with a number and a special character, stored with `password_hash()` (bcrypt); confirm password must match.

## 4. Running the application

### Docker Compose (recommended for the lab)

```sh
docker compose up --build -d
docker compose exec web php bin/install.php --demo
```

Open `http://127.0.0.1:8080/`. For lab devices, set `CAMPUSBITE_BIND` to the lab interface address in `.env`.

### Native PHP + MySQL

PHP 8.2+ with `pdo_mysql`, MySQL 8.4. Copy `config.example.php` to `config.local.php` (gitignored) and set the connection, then:

```sh
php bin/install.php --demo
php -S 127.0.0.1:8080 -t public
```

### Test accounts (demo seed, password `CampusDemo!2026`)

| Role | Username |
| --- | --- |
| Student | `student1`, `student2` |
| Vendor | `northhall` (North Hall Kitchen), `commons` (The Commons Cafe) |
| Administrator | `campusadmin` |

## 5. Local regression tooling (`.shots/`)

Playwright scripts that drive an installed Chrome (no browser download; `npm i playwright-core`):

> `flowtest.js` and `browsertest.js` assert the **clean** behaviour and are meant to run against `main` only. On `sa3-test-v1` the seeded defects intentionally fail some of their checks. The local-only `seedtest.js` verifies that each seeded defect manifests on the test build — keep it out of the repository for the same reason as the hidden bug log.

- `flowtest.js` — 60 server-side functional checks; needs a fresh demo database per run
- `browsertest.js` — 11 real-browser checks (named-button form serialization, confirm dialogs, filter counts)
- `designcheck.js` — 20 design/a11y checks (contrast, overflow, fonts, console errors)
- `shots.js` — screenshot capture; `compare.html` shows before/after redesign pairs

## 6. Phase 1 checklist

- [x] Login/registration converted to a web application with server-side validation
- [x] Additional functions built (menu, cart, ordering, status tracking, vendor and admin management)
- [x] Test accounts and one-page user guide (`docs/TESTER_GUIDE.md`)
- [x] 4 realistic defects seeded on branch `sa3-test-v1`
- [ ] **Upload the hidden bug log to the hidden Moodle section before Phase 2** (local file `docs/HIDDEN_BUG_LOG.md` — not committed)
- [ ] Deploy the test build where the partner group can reach it (lab network or free cloud tier) and share its URL plus `docs/TESTER_GUIDE.md`

## 7. Phase 3: fixes after partner testing

1. Collect partner defect reports (ID, severity, steps, screenshots).
2. Fix each confirmed defect on `sa3-test-v1` or a fix branch; re-test each fix.
3. Compare reported defects with the hidden bug log: which seeded defects were found, which were missed (and why), which findings were new.
4. Record every fix in the fix-log table in `docs/SA3_HANDOVER.md` (defect ID, root cause, code change and commit, retest result, developer name and student ID).
5. Merge the corrected code back to `main` so the final repository link in the report points at the clean, fixed version.
6. Performance testers: run JMeter against the deployed app (registration + menu search endpoints; see `docs/SA3_HANDOVER.md` for the request shapes).
