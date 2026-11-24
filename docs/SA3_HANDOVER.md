# CampusBite SA3 Developer Handover

## Test builds

Use the Phase 1 package for partner black-box testing of F01–F03. It has student registration, login, menu search and the cart. Give testers its English guide and a reachable lab URL. Use an isolated database for each build. Keep the developer's hidden defect log out of the partner package.

Use the full CampusBite package for F01–F08, status transitions, admin workflows and post-fix regression. The full package uses the same schema and supports a fresh seed for each run. If your team uses Git, preserve the exact test source under a tag such as `sa3-test-v1` and record the tag and URL in the handover.

## This repository's branches and tags

| Ref | What it is |
| --- | --- |
| `main` | Clean build — the redesigned application with all 8 profile functions, no known defects. Use for the final corrected code and the performance baseline. |
| `sa3-test-v1` (tag `sa3-test-v1`) | **Phase 1 test build — contains the 4 seeded defects.** Hand this exact source (or a deployment of it) to the partner test group. |
| `docs/HIDDEN_BUG_LOG.md` | Hidden bug log — kept **local only** (gitignored) and uploaded to the hidden Moodle section before Phase 2. Never commit or share it. |

See `docs/DEVELOPER_HANDOVER.md` for architecture, deployment steps and the Phase 1 / Phase 3 checklists.

## Partner findings and fixes

Record actual results after the partner team reports them. Add a row for each fix and attach the test evidence.

| Reported defect ID | Root cause | Code change and commit | Retest result and evidence | Developer name and student ID |
| --- | --- | --- | --- | --- |
| | | | | |

Compare partner findings with the seeded defect IDs after the hidden log is revealed. Record matched, missed and new findings with the supporting test evidence. For personal contribution records, enter the work you completed, date, time spent, code or screenshot reference and issues in the course logbook. Use the course process for Test Leader verification.

## Performance testing support

Use a fresh copy of the full app with MySQL and a web server that can handle concurrent requests. The form endpoint for both registration and menu interactions is `public/index.php`. For signup, first GET `index.php?page=register`, keep the `campusbite` session cookie, and extract `csrf` from the form. POST `action=register` with `csrf`, `username`, `email`, `password` and `confirm_password`. Use unique usernames and emails for every signup. For a second function, GET `index.php?page=menu&q=tofu`. See README for isolated database reset.
