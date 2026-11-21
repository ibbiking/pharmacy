# Project documentation

| Document | What it covers |
|---|---|
| [ROADMAP.md](ROADMAP.md) | Every step: status, what it includes, and the rule that each step needs approval before it starts |
| [BUSINESS_LOGIC.md](BUSINESS_LOGIC.md) | How the business works today, verified bugs, the product owner's decisions, rules for future modules |
| [PRODUCT_GUIDE.md](PRODUCT_GUIDE.md) | Owner's Q&A: business types, which modules each can have, Inventory vs B2B vs Pharmacy, credit and partial credit, Purchases vs B2B bills. Public version: the `/guide` page |
| [BUSINESS_TYPES_AND_SERVICES.md](BUSINESS_TYPES_AND_SERVICES.md) | One business = one type (2026-11-19): owner's request word for word, answers, module model per type, credit design, books sync, phases and progress log |
| [DEMO_GUIDE.md](DEMO_GUIDE.md) | Client demo: what each type can / can't have, a full tour per type, scenarios checklist (also on `/guide` → Demo tour) |
| [REFERENCE_APPS_MODULES.md](REFERENCE_APPS_MODULES.md) | Features of the reference Books / Inventory / Expense apps we could add as micro services (waiting for the owner) |
| [WORK_SUMMARY_2026-11-19.md](WORK_SUMMARY_2026-11-19.md) | Overview of the 2026-11-19 work (business types, credit, Wholesale, Books sync, demo guide) and what waits for the owner |
| [MODULES_AND_PLANS.md](MODULES_AND_PLANS.md) | How modules are split, how they sync, business types, package plans, Stripe, business creation, per-business email |
| [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) | Visual language, dark/light theme, layout, charts, helper pages |
| [FBR_INTEGRATION.md](FBR_INTEGRATION.md) | FBR POS Integration and Digital Invoicing: regimes, the With/Without FBR preference, the guided integration page, technical design, open questions, sources |
| [REFERENCE_SCREENSHOTS.md](REFERENCE_SCREENSHOTS.md) | Catalogue of reference screens from SeeATS, Books, IMS and Expense |
| [REMAINING.md](REMAINING.md) | Short list of open work (the roadmap has the detail) |
| [SECURITY.md](SECURITY.md) | Secrets to rotate, super-admin account, security review findings, `bec_*` tables |
| [DEPLOYMENT.md](DEPLOYMENT.md) | Production server: nginx + PHP-FPM, `.env`, queue worker, scheduler, backups, HTTPS, updating, rollback |
| [DEMO_ACCOUNTS.md](DEMO_ACCOUNTS.md) | Demo businesses, users and logins for trying every business type and module |

Installing and running: `../INSTALL_NOTES.txt` (commands only) and `../setup.sh`.
Code-level guide for developers and AI assistants: `../CLAUDE.md`.
- [SUPER_ADMIN_PORTAL.md](SUPER_ADMIN_PORTAL.md): separate super admin portal at `/platform` (plan + progress).
