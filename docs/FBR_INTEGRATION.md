# FBR integration (Pakistan)

Status: **planned (Roadmap step 6)**. The current `App\Services\FbrInvoiceService` is a stub and has not been
verified against FBR. Businesses must stay on **Local** invoicing until step 6 ships and passes FBR's own sandbox.

> ⚠️ **Verification rule.** Everything below comes from public FBR material and third-party integrator write-ups
> (sources at the end). They **disagree on the exact endpoint URLs**. Before any HTTP call is written, the
> developer must confirm the endpoints, field names and the test procedure against the **current official
> FBR / PRAL technical specification** and the business's own IRIS sandbox. Wording on the in-app guide page is
> stored as editable content (super admin) so it can be corrected without a deploy if FBR changes the portal.

## 1. Two FBR regimes, mapped to our business types

| | **FBR POS Integration** (retail) | **FBR Digital Invoicing (DI)** (B2B) |
|---|---|---|
| Who | Tier-1 retailers. Third-party guide criteria: shop area over 1,000 sq ft, **or** a chain/multiple outlets under one brand, **or** electricity bill over Rs 1.2 M in the last 12 months, **or** wholesaler-cum-retailer / importer. Others may opt in. | Sales-tax-registered persons (manufacturers, distributors, wholesalers, …) under **SRO 709(I)/2025**, per third-party sources |
| Our business types | Pharmacy, Inventory (POS sales) | Inventory B2B (B2B invoices, credit/debit notes) |
| Registered where | IRIS (`iris.fbr.gov.pk`): POS registration per outlet/terminal → **POS ID** | IRIS → Digital Invoicing → Integration Mode → "Proceed with PRAL as Licensed Integrator" → **bearer token** (sandbox and production) |
| Per sale | Send the invoice → FBR returns a **fiscal invoice number** | Send the invoice → FBR returns an **invoice reference number (IRN)** plus QR data |
| Must print | FBR invoice number, **QR code**, **FBR POS logo** | IRN, **QR code** (≥ 2 × 2 cm), DI logo/verification note |
| Customer checks | Tax Asaan app → verify invoice (scan QR / enter number) | FBR online invoice verification |
| Testing | Sandbox / test POS ID before going live | **28 mandatory sandbox scenarios** must pass before production |
| Extra | Third-party guides mention a **Re 1 FBR service charge per POS invoice** (to verify) | Validate and cancel endpoints; HS codes, sale type, rate strings like `"18%"` |

## 2. Business-wide preference: "Invoice numbering source"
Set in **Settings → FBR Integration** and changeable at any time by the owner.

| Option | Behaviour |
|---|---|
| **Without FBR (Local)**, the default | The invoice gets the business's own number, e.g. `INV-000123`, from its own series (prefix and next number are editable). Nothing is sent to FBR. |
| **With FBR** (POS Integration or Digital Invoicing) | The invoice still gets its local number, which is also sent to FBR as the unique sale reference. The **FBR number is stored on the invoice and printed as the fiscal number**, with the QR code and logo. Allowed only once the integration status is **Verified** (see the wizard). |

**Switching rules.**
- Applies to new invoices only. Old invoices keep whatever they were issued with.
- Every switch is audit-logged (who, when, from → to).
- Switching to FBR is blocked until a sandbox test invoice has succeeded for that business and environment.

**If FBR can't be reached at checkout**, a preference decides what happens. The default is **Block**, which is
today's behaviour.
- **Block sale:** keep FBR compliance strict.
- **Save and retry:** save the sale locally, queue the FBR submission (with retries) and reprint the receipt with
  the FBR number once it arrives. Shows an "FBR pending" badge, and invoices pending for over an hour raise an alert
  to the owner.

The product owner should confirm "Save and retry" is acceptable for their FBR regime before it is enabled.

## 3. The in-app integration page (built in Inertia, step 6)
Location: **Settings → FBR Integration**. Owners only. One page per business. Built as a guided wizard with a
progress tracker; every step can be resumed later.

1. **Are you required?** A short checklist (Tier-1 criteria or sales-tax registration) and an explanation of which
   regime applies. The default regime is pre-selected from the business type, and the owner can change it.
2. **Get ready.** What you need: NTN/CNIC, STRN (sales tax), IRIS login, a list of outlets and counters, and the
   product HS/PCT codes (DI).
3. **Register on IRIS.** Numbered steps with the portal menu path for the chosen regime, and a placeholder for each
   annotated screenshot, which the super admin can update. "Open IRIS" button.
4. **Enter your FBR details.** The fields depend on the regime:
   - Business: NTN/CNIC, STRN, FBR business name, province, address.
   - **Terminals** (POS Integration): one row per counter, with outlet name, **POS ID** and environment
     (sandbox/production). A business can have many terminals; each POS till is linked to one.
   - **Token** (DI, and POS if FBR issues one): stored encrypted, never shown again, can be replaced.
   - **Product mapping:** HS/PCT code and FBR sale type per product (DI; optional for POS). Bulk edit, server-paginated.
5. **Test in sandbox.** "Send test invoice" posts a sample invoice and shows FBR's raw response (secrets hidden). For
   DI, there is a checklist of the 28 scenarios with pass/fail for each.
6. **Go live.** Switch the environment to production, send one live test, then turn on the "With FBR" preference.
   The receipt preview shows the FBR number, QR code and logo.
7. **Status panel**, always visible on the page:
   - connection status;
   - last success and last error;
   - pending/failed submissions (retry button);
   - today's FBR-submitted count;
   - a link to the verification app/website.

Every field has a ⓘ explainer in plain language (English, with Urdu planned).

## 4. Technical design (step 6)
- `Modules/Fbr` with its own service provider. A `FbrClient` interface has two implementations: `PosIntegrationClient`
  and `DigitalInvoicingClient`. The environment (sandbox/production) is selected per business or terminal.
- Sending goes through a **queued job** `SubmitInvoiceToFbr`:
  - idempotent, keyed on the local invoice number;
  - retries with backoff of 1 s, 5 s, 30 s, 2 min and 10 min, then flagged for manual review;
  - a 4xx validation error is not retried, a 5xx or timeout is.

  In "Block" mode the first attempt runs synchronously at checkout, within the time limit.
- Tables:
  - `fbr_terminals`;
  - `fbr_submissions`: request and response JSON with secrets stripped, status, attempts, FBR number, QR payload;
  - `invoices.fbr_invoice_no`.
- Credit notes and returns send FBR's reference to the original invoice number (the field names must be checked
  against the spec).
- Daily reconciliation job: compares local FBR-submitted invoices with the submissions log and reports differences.
- Receipt: the QR code is generated locally from FBR's data (no `imagick` needed; SVG/GD backend). The FBR logo is
  supplied as an asset by the super admin.

## 5. Open questions for the product owner
1. Which regime do your first customers need: POS Integration (retail pharmacies) or DI (B2B)? Building POS
   Integration first is recommended.
2. Should the Re 1 FBR service charge (if confirmed) be added to the customer's bill automatically?
3. Is "Save and retry" acceptable when FBR is offline, or must sales always block?
4. Can you provide IRIS **sandbox** credentials and a test POS ID for development?

## Sources (third-party unless noted)
- FBR (official), Fiscalization Solution for Retailers (2019): https://download1.fbr.gov.pk/Docs/2019121116123534681FiscSolution_03-12-2019.pdf
- FBR (official), Technical Specification for Data Sharing through API (Digital Invoicing) v1.0: https://download1.fbr.gov.pk/Docs/202312202312256464Technical-Specification-Digital-Invoicing.pdf
- XStak, FBR POS integration guide for retailers: https://www.xstak.com/blog/fbr-pos-integration-guide-for-retailers
- Tallied, Integrating Pakistani businesses with the FBR Digital Invoicing API: https://dev.to/tallied/integrating-pakistani-businesses-with-the-fbr-digital-invoicing-api-1glk
- Tallied, DI sandbox scenarios explained: https://tallied.pk/blog/fbr-digital-invoicing-sandbox-scenarios.html
- FBR Integration Kit (GitHub): https://github.com/RKUsamaAli/FBR-Integration-Kit
