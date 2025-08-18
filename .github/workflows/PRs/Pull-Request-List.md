# Instructions

Do not carry out these PR's blindly. Confirm on your own if they represent the truth, if they do not, edit accordingly. This should be a hierarchical checklist. A parent should never be checked unless all of its children are first. Confirm completion before checking off a task.

Do due diligence for being non-destructive. There is most certainly duplicatae logic in this code base, if you find yourself about to remove or edit something because it's not working or found elsewhere **FAVOR COMMENTING IT OUT** or if necessary, make a _archive/ directory in the same folder as the file, and copy the file there before making changes. If its a file you're relocating, make comments at the top, if its code you're commenting out, comment above or inline to explain the reason for the change. 

Before beginning each PR make sure you've made a todo list as part of your due diligence. 

# 🔴 PR 0: Fix Test Infrastructure (Unskipped Tests)

**Why**
Right now, `ui/test-results/junit.xml` shows all **165 tests skipped**, which means we effectively have no automated test coverage. This creates a **false sense of security** and blocks us from trusting CI/CD. Getting tests to run (and fail/pass meaningfully) is the single most important step before any other refactors.

**How**

* Investigated why tests are skipped in both frontend (`ui/`) and backend (`php artisan test`).
* Fixed test runner configs (Vitest/Jest/Playwright for UI; PHPUnit for backend).
* Updated package/test scripts to ensure they actually execute.
* Cleaned up test discovery (removed `.skip`, ensured proper extensions).
* Validated locally that tests now produce real pass/fail outcomes.

**Test Plan**

1. Run `npm run test` in `ui/` → should execute all UI tests, not skip.
2. Run `php artisan test` in backend → should run backend suite.
3. CI must show >0 tests executed (and passing/failing as expected).
4. Confirm `ui/test-results/junit.xml` contains results with pass/fail counts, not 100% skipped.

---

# 🔴 PR 1: CI/CD Test Execution (GitHub Actions)

**Why**
Even with tests working locally, CI wasn’t actually running them. The existing `.github/workflows/test.yml` doesn’t execute both frontend + backend suites, so regressions can sneak in. This PR wires tests into CI/CD and makes them required for merges.

**How**

* Updated `test.yml` to include **two jobs**:

  * `frontend-tests`: runs `npm ci && npm run test` in `/ui`.
  * `backend-tests`: runs `composer install && php artisan test`.
* Added caching for `node_modules` and `vendor` to speed builds.
* Uploaded JUnit XML as GitHub Action artifact for visibility.
* Marked both jobs as required for merge in branch protection rules.

**Test Plan**

1. Push commit to feature branch → CI should run **both jobs**.
2. If a test fails, CI fails. If all pass, CI is green.
3. Inspect CI artifacts: `junit.xml` should be downloadable for both jobs.
4. Merge is blocked if either job fails.

---

# 🟠 PR 2: Runtime Config & Hardcoded Values

**Why**
There are **hardcoded API URLs and IDs** across frontend composables and config (e.g., `useBackendApi.js`, `nuxt.config.ts`, `ui/config/app.js`). This hurts portability (stuck on one environment), leaks sensitive IDs, and risks inconsistencies. Also, several **magic constants** (debounce timers, gas reserves, discounts) are hardcoded, making tuning difficult.

**How**

* Removed all hardcoded API URLs and IDs from source.
* Moved configuration into environment variables (`.env`).
* Updated `nuxt.config.ts` to pull from `runtimeConfig.public` for frontend-safe values and private config for secrets.
* Externalized “magic numbers” into config/env (e.g., debounce = `NUXT_PUBLIC_DEBOUNCE_MS`, gas reserve = `NUXT_PUBLIC_GAS_RESERVE`).
* Added build-time checks to fail if required env vars are missing.

**Test Plan**

1. Delete `.env` and run `npm run dev` → build should fail with missing-env error.
2. Add `.env` with valid values → app should boot and read them correctly.
3. Change debounce env var → UI debounce reflects new timing.
4. Confirm `runtimeConfig.public` contains only safe values; secrets do not appear in client bundle.

Got it 👍 — here are the next two PR write-ups, continuing in the same **Why / How / Test Plan** format. These are the **security foundation** PRs you’ll want right after fixing tests & config.

---

# 🟠 PR 3: Lock Down Admin Endpoints

**Why**
The indexer exposes sensitive endpoints like `/api/admin/indexer/start`, `/stop`, and `/status` without authentication. Right now, *anyone who can reach the service can toggle the indexer*. This is a critical security risk — even if rate-limiting is applied, there’s no protection against unauthorized requests.

**How**

* Added an authentication layer for `/api/admin/*` routes.

  * Expect `X-Admin-Token` header (configurable via env).
  * Reject requests without valid token.
* Restricted CORS origins on admin routes to a configured allowlist.
* Applied stricter rate limits to admin routes (low burst, low max).
* Updated documentation to note new `ADMIN_TOKEN` env var requirement.

**Test Plan**

1. Start server with `ADMIN_TOKEN=secret123`.
2. Call `/api/admin/indexer/status` **without header** → should return `401 Unauthorized`.
3. Call with wrong token → should return `401 Unauthorized`.
4. Call with correct `X-Admin-Token: secret123` → should succeed.
5. Check rate-limiting: rapid fire >N requests should get `429 Too Many Requests`.

---

# 🟡 PR 4: Security Headers (CSP, Frameguard, HSTS)

**Why**
Currently, the Nuxt app defines meta tags but does not enforce critical browser-side security protections. Without proper headers:

* Inline scripts/styles could run (XSS risk).
* App could be embedded in iframes (clickjacking risk).
* No HSTS means downgrade attacks are possible.

Adding these headers hardens the app in production.

**How**

* Configured Nitro route rules to add headers:

  * `Content-Security-Policy`: `default-src 'self'; script-src 'self' 'nonce-<generated>'; connect-src 'self' <API_HOST> <RPC_HOST>; style-src 'self';`
  * `Strict-Transport-Security`: `max-age=31536000; includeSubDomains; preload`
  * `X-Frame-Options`: `DENY`
  * `Referrer-Policy`: `no-referrer`
* Set nonce generator for inline Vue scripts.
* Ensured CSP allows necessary external resources (fonts, RPC endpoints).
* Added docs snippet explaining how to adjust CSP when new dependencies are added.

**Test Plan**

1. Run app in production build.
2. Inspect response headers → confirm CSP, HSTS, Frameguard, Referrer-Policy are present.
3. Attempt to embed app in `<iframe>` → should be blocked.
4. Attempt to inject inline `<script>` without nonce → should be blocked by CSP.
5. Check HTTPS response headers with curl: `curl -I https://app.example.com` → headers should appear.

---

Absolutely — let’s continue down the roadmap. Next up are the **indexer modularization** and **error handling consolidation** PRs.

---

# 🟡 PR 5: Indexer Modularization

**Why**
Right now, `server.js` is a “god file” that mixes:

* Route definitions (transactions, vesting, admin, health)
* Error handling
* Logging & metrics setup
* App bootstrap logic

This makes the file hard to read, test, and extend. Breaking it into smaller route modules improves clarity and separation of concerns.

**How**

* Created new route modules:

  * `routes/admin.ts` → `/api/admin/*` endpoints
  * `routes/transactions.ts` → transaction endpoints
  * `routes/health.ts` → health/metrics endpoints
* Added JSON schemas for query/params validation using Fastify built-ins.
* Updated `server.js` to:

  * Register these route modules.
  * Initialize error handler and logger.
  * Contain only high-level setup.

**Test Plan**

1. Start indexer and hit `/api/health` → responds as before.
2. Hit `/api/admin/indexer/status` → responds as before.
3. Call `/api/transactions/:id` → responds as before.
4. Invalid params (e.g., bad tx ID) → validation schema rejects with `400`.
5. Confirm log output unchanged.

---

# 🟡 PR 6: Error Handling Consolidation

**Why**
Error handling is duplicated across multiple files:

* Local try/catch blocks re-map errors.
* Global error handler is defined but not consistently used.

This creates inconsistent responses and harder maintenance. Consolidating into one error mapping function makes error handling uniform and predictable.

**How**

* Added `lib/errors.ts` with a `mapError(error)` function that:

  * Maps `ValidationError` → `400 Bad Request`.
  * Maps `DatabaseError` → `500 Internal Server Error`.
  * Maps `IndexerError` → `502 Bad Gateway`.
  * Falls back to `500 Unknown Error`.
* Updated Fastify `setErrorHandler` to always use `mapError`.
* Simplified route handlers: no more manual error translation; just `throw` and let Fastify handle.
* Ensured logs still capture stack traces for debugging.

**Test Plan**

1. Trigger validation error (e.g., invalid address) → `400` JSON with `code: "VALIDATION_ERROR"`.
2. Force DB error (e.g., stop DB service) → `500` JSON with `code: "DATABASE_ERROR"`.
3. Force indexer error → `502` JSON with `code: "INDEXER_ERROR"`.
4. Trigger unknown error → `500` JSON with generic error message.
5. Inspect logs → full error + stack trace should still be recorded.

---

Absolutely — let’s continue down the roadmap. Next up are the **indexer modularization** and **error handling consolidation** PRs.

---

# 🟡 PR 5: Indexer Modularization

**Why**
Right now, `server.js` is a “god file” that mixes:

* Route definitions (transactions, vesting, admin, health)
* Error handling
* Logging & metrics setup
* App bootstrap logic

This makes the file hard to read, test, and extend. Breaking it into smaller route modules improves clarity and separation of concerns.

**How**

* Created new route modules:

  * `routes/admin.ts` → `/api/admin/*` endpoints
  * `routes/transactions.ts` → transaction endpoints
  * `routes/health.ts` → health/metrics endpoints
* Added JSON schemas for query/params validation using Fastify built-ins.
* Updated `server.js` to:

  * Register these route modules.
  * Initialize error handler and logger.
  * Contain only high-level setup.

**Test Plan**

1. Start indexer and hit `/api/health` → responds as before.
2. Hit `/api/admin/indexer/status` → responds as before.
3. Call `/api/transactions/:id` → responds as before.
4. Invalid params (e.g., bad tx ID) → validation schema rejects with `400`.
5. Confirm log output unchanged.

---

# 🟡 PR 6: Error Handling Consolidation

**Why**
Error handling is duplicated across multiple files:

* Local try/catch blocks re-map errors.
* Global error handler is defined but not consistently used.

This creates inconsistent responses and harder maintenance. Consolidating into one error mapping function makes error handling uniform and predictable.

**How**

* Added `lib/errors.ts` with a `mapError(error)` function that:

  * Maps `ValidationError` → `400 Bad Request`.
  * Maps `DatabaseError` → `500 Internal Server Error`.
  * Maps `IndexerError` → `502 Bad Gateway`.
  * Falls back to `500 Unknown Error`.
* Updated Fastify `setErrorHandler` to always use `mapError`.
* Simplified route handlers: no more manual error translation; just `throw` and let Fastify handle.
* Ensured logs still capture stack traces for debugging.

**Test Plan**

1. Trigger validation error (e.g., invalid address) → `400` JSON with `code: "VALIDATION_ERROR"`.
2. Force DB error (e.g., stop DB service) → `500` JSON with `code: "DATABASE_ERROR"`.
3. Force indexer error → `502` JSON with `code: "INDEXER_ERROR"`.
4. Trigger unknown error → `500` JSON with generic error message.
5. Inspect logs → full error + stack trace should still be recorded.

---

Absolutely — let’s continue down the roadmap. Next up are the **indexer modularization** and **error handling consolidation** PRs.

---

# 🟡 PR 5: Indexer Modularization

**Why**
Right now, `server.js` is a “god file” that mixes:

* Route definitions (transactions, vesting, admin, health)
* Error handling
* Logging & metrics setup
* App bootstrap logic

This makes the file hard to read, test, and extend. Breaking it into smaller route modules improves clarity and separation of concerns.

**How**

* Created new route modules:

  * `routes/admin.ts` → `/api/admin/*` endpoints
  * `routes/transactions.ts` → transaction endpoints
  * `routes/health.ts` → health/metrics endpoints
* Added JSON schemas for query/params validation using Fastify built-ins.
* Updated `server.js` to:

  * Register these route modules.
  * Initialize error handler and logger.
  * Contain only high-level setup.

**Test Plan**

1. Start indexer and hit `/api/health` → responds as before.
2. Hit `/api/admin/indexer/status` → responds as before.
3. Call `/api/transactions/:id` → responds as before.
4. Invalid params (e.g., bad tx ID) → validation schema rejects with `400`.
5. Confirm log output unchanged.

---

# 🟡 PR 6: Error Handling Consolidation

**Why**
Error handling is duplicated across multiple files:

* Local try/catch blocks re-map errors.
* Global error handler is defined but not consistently used.

This creates inconsistent responses and harder maintenance. Consolidating into one error mapping function makes error handling uniform and predictable.

**How**

* Added `lib/errors.ts` with a `mapError(error)` function that:

  * Maps `ValidationError` → `400 Bad Request`.
  * Maps `DatabaseError` → `500 Internal Server Error`.
  * Maps `IndexerError` → `502 Bad Gateway`.
  * Falls back to `500 Unknown Error`.
* Updated Fastify `setErrorHandler` to always use `mapError`.
* Simplified route handlers: no more manual error translation; just `throw` and let Fastify handle.
* Ensured logs still capture stack traces for debugging.

**Test Plan**

1. Trigger validation error (e.g., invalid address) → `400` JSON with `code: "VALIDATION_ERROR"`.
2. Force DB error (e.g., stop DB service) → `500` JSON with `code: "DATABASE_ERROR"`.
3. Force indexer error → `502` JSON with `code: "INDEXER_ERROR"`.
4. Trigger unknown error → `500` JSON with generic error message.
5. Inspect logs → full error + stack trace should still be recorded.

---

Got it — let’s keep moving. Next in the roadmap are the **Vue/UI refactor PRs** (RecipientAddressInput, SwapForm, validation/addressFormatting).

---

# 🟡 PR 7a: Refactor `RecipientAddressInput.vue`

**Why**
`RecipientAddressInput.vue` (\~150 lines) contains duplicated validation logic, multiple tightly coupled handlers, and verbose UI state code. Centralizing validation and simplifying handlers improves maintainability and reduces drift with utils.

**How**

* Replaced inline validation logic with calls to shared functions from `validation.js` / `addressFormatting.js`.
* Merged `handleInput` + `handleValidation` into a single debounced handler.
* Extracted conditional class/icon logic into computed properties (`inputClasses`, `iconComponent`).
* Added prop validation with `defineProps` validators for stricter typing.
* Removed leftover debug `console.log` statements.

**Test Plan**

1. Enter valid address → component emits correct value, shows success styling.
2. Enter invalid address → shows error styling + message from shared validator.
3. Debounce input: type rapidly, validation should trigger once per debounce window.
4. Use `focusInput` / `clearAndFocusInput` methods → field updates as expected.

---

# 🟡 PR 7b: Refactor `SwapForm.vue`

**Why**
`SwapForm.vue` (\~600 lines) is too large and mixes UI, state, and business logic. It has multiple watchers, scattered error handling, and unused props. Breaking it down will improve readability and testability.

**How**

* Split component into:

  * `SwapInputs.vue` → sell/buy amount fields.
  * `SwapValidation.vue` → error + quote display.
* Extracted quote calculation, validation, and tx prep logic into composable `useSwapTransaction.js`.
* Grouped refs (`inputAmount`, `cirxAmount`, `lastUpdatedField`) into a single `reactive` form state.
* Consolidated watchers on quote/amount into one combined watcher with debounce.
* Replaced repetitive try/catch blocks with `withErrorHandling(asyncFn)` wrapper.
* Removed unused props (`network-fee-eth`).
* Cleaned up console logs.

**Test Plan**

1. Enter valid swap input → quotes update correctly.
2. Enter invalid swap input → errors surface via `SwapValidation.vue`.
3. Trigger swap execution → errors are caught/logged consistently.
4. Confirm new composable (`useSwapTransaction.js`) handles logic in isolation (unit testable).
5. Build size/complexity check: `SwapForm.vue` reduced to <300 lines.

---

# 🟡 PR 7c: Refactor `validation.js` & `addressFormatting.js`

**Why**
Currently, address validation logic is duplicated between these two utils. Hardcoded regexes appear in multiple places, which risks drift and inconsistency. Some validators (email, password) aren’t used.

**How**

* Unified regex-based address validators into a single function:

  ```js
  isValidAddress(type, address) { /* switch or regex map */ }
  ```
* Removed redundant per-chain `isValid*Address` functions.
* Updated `validateWalletAddress` in `validation.js` to import and reuse the shared validators.
* Added support for async validators (API checks) as extension point.
* Externalized error messages for i18n support.
* Removed unused validators (or moved them to dedicated auth utils if still needed).

**Test Plan**

1. Call `isValidAddress("eth", validEthAddress)` → returns true.
2. Call `isValidAddress("cirx", invalidAddress)` → returns false.
3. Confirm all existing UI validators (`RecipientAddressInput`, `SwapForm`) still function.
4. Run new unit tests in `utils/__tests__` for every validator.
5. Verify that error messages can be swapped out via config/i18n.

---


Yes 👍 — let’s keep going. After the Vue/UI refactors (PR 7a–7c), the roadmap continues into **backend/worker refactors** and then **wallet/TypeScript/CI polish**.

---

# 🟡 PR 8: Worker Repository Pattern (Idempotency & DLQ)

**Why**
Workers directly query/update Eloquent models with fixed batch sizes and no strict idempotency. This risks double-processing transactions and makes retries brittle. There’s also no clear handling for “poisoned” transactions that always fail.

**How**

* Introduced `TransactionRepository` abstraction with:

  * `fetchBatch(status, limit)`
  * `updateStatusIfUnchanged(txId, expectedStatus, newStatus)` (compare-and-swap)
* Updated workers to use repository instead of direct DB access.
* Added `dead_letter_reason` column for permanent failures.
* Implemented retry/backoff with exponential delay.
* Poisoned tx → moved to DLQ state with reason stored.

**Test Plan**

1. Process a batch of pending txs → all move to verified/failed correctly.
2. Simulate retryable failure (e.g., RPC timeout) → transaction retries with backoff.
3. Simulate poisoned tx (always fails validation) → moved to DLQ with reason logged.
4. Run workers twice on same batch → no double-processing (idempotency check).

---

# 🟡 PR 9: PHP PaymentVerificationService Refactor

**Why**
`PaymentVerificationService` mixes indexer HTTP calls with blockchain fallback logic in one class. This creates tangled error handling and limits testability. Splitting into strategies makes it easier to extend and maintain.

**How**

* Created `IndexerPaymentVerifier` class → verifies via indexer API.
* Created `ChainPaymentVerifier` class → verifies directly on-chain.
* Updated `PaymentVerificationService` to:

  * First call indexer verifier.
  * On certain errors, fallback to chain verifier.
* Added retry/backoff with circuit breaker around Guzzle calls.
* Added unit tests for both verifiers.

**Test Plan**

1. Valid payment → indexer returns success.
2. Indexer unavailable → falls back to chain verifier, still succeeds.
3. Both verifiers fail → service returns failure with structured reason.
4. Simulate repeated indexer outage → circuit breaker opens, avoids spamming indexer.

---

# 🟢 PR 10: Wallet Composable Cleanup

**Why**
`MultiWalletButton.vue` currently redeclares connection logic inline, leading to build-time redeclaration errors and duplicated wallet handling. Centralizing wallet logic in a composable simplifies usage and fixes the bug.

**How**

* Created `useWallet()` composable with:

  * `connectWallet()`
  * `disconnectWallet()`
  * reactive `isConnected`, `address` state
* Updated `MultiWalletButton.vue` to use composable functions.
* Removed duplicate function redeclarations.
* Added unit test for wallet connect/disconnect flow.

**Test Plan**

1. Click “Connect Wallet” → composable updates state, wallet connects.
2. Click “Disconnect” → state resets, wallet disconnects.
3. Build project → no redeclaration errors.
4. Unit test: composable correctly handles connect/disconnect cycle.

---

# 🟢 PR 11: TypeScript Coverage Expansion

**Why**
The current `tsconfig.json` only includes generated Nuxt types and e2e configs — not actual source files. This leaves large parts of the UI untyped, allowing bugs to slip through.

**How**

* Expanded `tsconfig.json` to include:

  * `ui/composables/**/*`
  * `ui/stores/**/*`
  * `ui/components/**/*`
* Enabled `strict: true`.
* Migrated utilities (`validation.js`, `addressFormatting.js`) to TypeScript gradually.
* Added types for props/emits in Vue components.

**Test Plan**

1. Run `tsc --noEmit` → should pass or show actionable errors.
2. Verify that a missing prop type in a component causes compile-time error.
3. Ensure existing tests still pass under TS strict mode.

---

# 🟢 PR 12: CI Enhancements (Schema & Config Checks)

**Why**
Current CI runs tests but doesn’t enforce runtimeConfig validity or schema consistency. Adding automated checks catches misconfigurations early.

**How**

* Added script to validate `runtimeConfig` against JSON schema.
* Added lint step for API route schemas.
* Deferred “console.log checker” until later (since logs are useful during dev).

**Test Plan**

1. Push PR with missing required env var → CI fails.
2. Push PR with invalid runtimeConfig schema → CI fails.
3. Push PR with valid config → CI passes.

---

# 🟢 PR 13: Build Script Flexibility

**Why**
`build.sh` is hardcoded to use `nitro.preset = static` for Cloudflare Pages. If we later need server-rendered routes (e.g., edge functions), we’ll need flexibility.

**How**

* Parameterized Nitro preset via `$NITRO_PRESET` env.
* Default remains `static` for CF Pages.
* Allowed `node-server` or `cloudflare` for other deployments.

**Test Plan**

1. Run `NITRO_PRESET=static ./build.sh` → static build works.
2. Run `NITRO_PRESET=node-server ./build.sh` → server build works.
3. Deploy both builds → app behaves as expected.

---

# 🟢 PR 14: Integration Test Strengthening

**Why**
Some integration tests only assert “no exception thrown.” This is weak — we need state-based assertions to catch regressions.

**How**

* Updated transaction tests to assert actual status transitions:

  * `PENDING_PAYMENT_VERIFICATION → PAYMENT_VERIFIED`
  * `PENDING_PAYMENT_VERIFICATION → FAILED_*`
* Added regression test for wallet redeclaration bug.
* Added test coverage for DLQ worker behavior.

**Test Plan**

1. Run integration tests → must assert concrete state changes, not just execution.
2. Simulate poisoned tx → test asserts DLQ placement.
3. Build runs without wallet redeclaration bug.

---

# 🟢 PR 15: Docs & Cleanup

**Why**
The repo contains dev artifacts and duplicated docs. Cleaning these up improves clarity and reduces noise.

**How**

* Removed `claude_chart.md`, `.backup` files, and outdated sandbox HTML.
* Deduplicated README vs. audit doc — linked instead of copy-pasting.
* Added JSDoc/TS docstrings to utils and composables.
* Ran `npm audit fix` to update dependencies.

**Test Plan**

1. Check repo tree → no leftover backup or artifact files.
2. README still contains architecture overview, links to audits.
3. Run `npm audit` → minimal vulnerabilities.

---

✅ With these, the roadmap is **fully covered (PR 0–15)**:

* Foundation (tests, CI, config, security)
* Backend modularization + workers
* UI component refactors
* Wallet + TypeScript cleanup
* CI/build/test strengthening
* Docs & final cleanup

---

# 🟢 PR 16: Add Code Coverage Reporting (Frontend + Backend)

**Why**
Right now we have tests (once PR 0–1 are merged), but no visibility into **how much of the codebase is covered**. Without coverage reports:

* We can’t identify untested files (e.g., validation utils, worker logic).
* CI can’t prevent regressions that reduce coverage.
* The team lacks a baseline metric to track test quality over time.

Adding code coverage ensures we can measure, improve, and enforce test health.

**How**

* **Frontend (Nuxt/Vue)**:

  * Configured Vitest with coverage enabled (`c8` provider).
  * Added reporters: `text`, `html`, `lcov`.
  * Coverage reports saved to `ui/coverage/`.
* **Backend (Laravel/PHP)**:

  * Enabled PHPUnit coverage with Xdebug/PCOV.
  * Configured output: `coverage.xml` (clover format) + optional HTML.
* **Node Indexer/Workers**:

  * Extended Jest/Vitest test config with coverage enabled.
  * Reports saved to `indexer/coverage/`.
* **CI Integration (GitHub Actions)**:

  * Updated `test.yml` to run all coverage steps.
  * Uploads reports as GitHub Action artifacts.
  * (Optional) Integrated with Codecov for PR annotations + dashboards.
* Set a **baseline threshold** (e.g., 40%) so PRs can’t reduce coverage below that.

**Test Plan**

1. Run `npm run test -- --coverage` in `ui/` → generates `coverage/index.html` and `lcov.info`.
2. Run `php artisan test --coverage-html coverage-report` → generates HTML report in backend.
3. Run worker/indexer tests with `--coverage` → generates `coverage/` folder.
4. Check GitHub Actions run → should include uploaded coverage artifacts for frontend, backend, and indexer.
5. (If Codecov enabled) Open a PR → Codecov bot comments coverage % and flags diffs.

---

⚡ With PR 16, you'll have a **baseline coverage metric** and visibility per PR, which makes all future refactors safer and measurable.

---

# 🔴 PR 17: Critical Component Decomposition - Break Down Monolithic swap.vue (2,700 lines)

**Why**
The `ui/pages/swap.vue` file has grown to 2,700 lines, mixing presentation, business logic, state management, and API integration. This violates Single Responsibility Principle and makes the component:
- Impossible to unit test effectively (cognitive overload)
- Prone to merge conflicts with multiple developers
- Difficult to debug and maintain
- Performance bottleneck due to excessive reactivity

This is blocking team productivity and must be addressed immediately.

**How**

* **Phase 1: Extract Chart and Staking Components**
  * Create `components/SwapChart.vue` (~200 lines) - TradingView chart integration
  * Create `components/SwapStaking.vue` (~200 lines) - Staking panel logic
  * Move chart state management to `composables/useSwapChart.js`
  * Move staking logic to `composables/useSwapStaking.js`

* **Phase 2: Create Form Component Hierarchy**
  * Create `components/SwapContainer.vue` (~150 lines) - Main layout orchestrator
  * Create `components/SwapFormInputs.vue` (~250 lines) - Input fields and validation
  * Create `components/SwapQuoteDisplay.vue` (~150 lines) - Price quotes and calculations
  * Create `components/SwapActionButtons.vue` (~100 lines) - CTA buttons and modal triggers

* **Phase 3: Extract Business Logic**
  * Create `composables/useSwapOrchestration.js` (~300 lines) - Main swap flow coordination
  * Create `composables/useSwapValidation.js` (~200 lines) - All validation logic
  * Create `composables/useSwapQuotes.js` (~150 lines) - Price calculation and fetching
  * Create `composables/useSwapState.js` (~100 lines) - Reactive state management

* **Phase 4: Refactor Main Component**
  * Reduce `pages/swap.vue` to ~200 lines - pure layout orchestration
  * Implement prop-based communication between components
  * Use provide/inject for deep state sharing
  * Maintain backward compatibility with existing functionality

**Test Plan**

1. **Component Extraction Verification**:
   - Each extracted component renders independently without errors
   - Props and events work correctly between parent and children
   - All original functionality preserved (address validation, quote calculation, modal flows)

2. **Business Logic Isolation**:
   - Composables can be imported and tested in isolation
   - State management works correctly across component boundaries
   - No circular dependencies between composables

3. **Performance Testing**:
   - Page load time should improve (less initial JavaScript parsing)
   - Component reactivity should be more granular
   - Memory usage should decrease during interactions

4. **Integration Testing**:
   - All 7 CTA states work correctly (Connect, Enter Address, Enter Amount, etc.)
   - Address validation with yellow flashing still functions
   - Modal interactions (wallet selection) work as before
   - Form submission and error handling preserved

5. **Developer Experience**:
   - Hot module replacement works faster
   - Components can be developed independently
   - Merge conflicts reduced significantly

**Effort Estimate**: 20-24 hours (1.5-2 weeks)
**Priority**: Critical - Blocking all team development

---

# 🔴 PR 18: Backend Blockchain Client Refactoring - Strategy Pattern for Transaction Logic

**Why**
The `CirxBlockchainClient.php` file contains a 350+ line `sendCirxTransfer` method with 12 hardcoded transaction strategies in a single function. This creates:
- Extremely high cyclomatic complexity (>25)
- Impossible to unit test individual strategies
- Difficult to add/remove strategies
- Brittle error handling across strategies
- Violation of Open/Closed Principle

The 679-line file needs immediate refactoring using Strategy Pattern.

**How**

* **Phase 1: Create Strategy Interface and Base Classes**
  ```php
  interface TransactionStrategyInterface {
      public function execute(TransactionContext $context): TransactionResult;
      public function canHandle(TransactionContext $context): bool;
      public function getName(): string;
  }
  
  abstract class BaseTransactionStrategy implements TransactionStrategyInterface {
      protected CircularProtocolAPI $api;
      protected LoggerInterface $logger;
      // Common functionality
  }
  ```

* **Phase 2: Extract Individual Strategies**
  * `SimpleNonceStrategy` - working_simple_nonce_plus1 logic
  * `StringNonceStrategy` - working_simple_string_nonce logic
  * `EmptySignatureStrategy` - working_simple_original logic
  * `PlusOneNonceStrategy` - nonce increment variations
  * `HexFixedStrategy` - hexFix payload variations
  * Create 8-10 concrete strategy classes (~50 lines each)

* **Phase 3: Create Strategy Manager**
  ```php
  class TransactionStrategyManager {
      private array $strategies;
      
      public function executeTransaction(TransactionContext $context): TransactionResult {
          foreach ($this->strategies as $strategy) {
              if ($strategy->canHandle($context)) {
                  try {
                      $result = $strategy->execute($context);
                      if ($result->isSuccess()) return $result;
                  } catch (StrategyException $e) {
                      continue; // Try next strategy
                  }
              }
          }
          throw new NoValidStrategyException();
      }
  }
  ```

* **Phase 4: Refactor CirxBlockchainClient**
  * Reduce `sendCirxTransfer` method to ~50 lines
  * Inject `TransactionStrategyManager` dependency
  * Delegate all strategy logic to manager
  * Maintain existing public API for backward compatibility

* **Phase 5: Add Comprehensive Unit Tests**
  * Test each strategy in isolation
  * Mock external dependencies (API, logger)
  * Test strategy selection logic
  * Test error handling and fallbacks

**Test Plan**

1. **Strategy Isolation Testing**:
   - Each strategy can be tested independently with mocked context
   - Strategy selection logic works correctly
   - Error handling propagates correctly

2. **Integration Testing**:
   - All existing transaction flows continue to work
   - Strategy fallback chain functions as before
   - Logging and error reporting maintained

3. **Performance Testing**:
   - Transaction execution time remains similar
   - Memory usage doesn't increase significantly
   - Strategy evaluation overhead is minimal

4. **Backward Compatibility**:
   - Public API methods unchanged
   - Existing calling code requires no modifications
   - All transaction types continue to work

**Effort Estimate**: 16-20 hours (1.5 weeks)
**Priority**: Critical - High complexity blocking maintainability

---

# 🔴 PR 19: Frontend State Management Refactoring - Modular Wallet Store Architecture

**Why**
The `stores/wallet.js` file (578 lines) mixes multiple concerns in a single store:
- MetaMask connection logic
- Phantom wallet integration  
- Global connection state
- Error handling and persistence
- Complex computed properties with mixed responsibilities

This creates tight coupling, makes testing difficult, and violates separation of concerns.

**How**

* **Phase 1: Create Specialized Wallet Stores**
  ```javascript
  // stores/wallet/metamask.js (~150 lines)
  export const useMetaMaskStore = defineStore('metamask', () => {
    const wallet = ref(useMetaMask())
    const isConnected = ref(false)
    const address = ref(null)
    
    const connect = async () => { /* MetaMask-specific logic */ }
    const disconnect = async () => { /* MetaMask-specific logic */ }
    
    return { wallet, isConnected, address, connect, disconnect }
  })
  
  // stores/wallet/phantom.js (~150 lines) 
  // stores/wallet/connection.js (~100 lines)
  // stores/wallet/persistence.js (~80 lines)
  ```

* **Phase 2: Create Connection Orchestrator**
  ```javascript
  // stores/wallet/index.js (~100 lines)
  export const useWalletStore = defineStore('wallet', () => {
    const metamask = useMetaMaskStore()
    const phantom = usePhantomStore() 
    const connection = useConnectionStore()
    
    return {
      // Computed properties delegating to specialized stores
      isConnected: computed(() => connection.isConnected),
      activeWallet: computed(() => connection.activeWallet),
      address: computed(() => connection.activeAddress),
      
      // Methods delegating to appropriate stores
      connect: connection.connect,
      disconnect: connection.disconnect,
      
      // Expose individual wallet stores for specific needs
      metamask,
      phantom
    }
  })
  ```

* **Phase 3: Extract Persistence Layer**
  * Create `stores/wallet/persistence.js` for localStorage management
  * Abstract storage interface for testing
  * Implement data serialization/deserialization
  * Add encryption for sensitive wallet data

* **Phase 4: Update Component Dependencies**
  * Update all components using wallet store
  * Migrate to new modular API
  * Ensure reactivity still works correctly
  * Add proper TypeScript types

**Test Plan**

1. **Store Isolation Testing**:
   - Each specialized store works independently
   - MetaMask store handles MetaMask operations only
   - Phantom store handles Phantom operations only
   - Connection store orchestrates correctly

2. **Integration Testing**:
   - Main wallet store delegates correctly to specialized stores
   - Components receive correct reactive updates
   - Store hydration from persistence works
   - Multiple wallet types can be managed simultaneously

3. **Performance Testing**:
   - Store initialization time doesn't increase
   - Reactive updates remain performant
   - Memory usage doesn't increase significantly
   - No unnecessary re-computations

4. **Backward Compatibility**:
   - Existing components continue to work with main wallet store API
   - All wallet connection flows preserved
   - Error handling and state management unchanged from user perspective

**Effort Estimate**: 12-16 hours (1 week)
**Priority**: Critical - Affects all wallet operations

---

# 🟠 PR 20: Standardize Error Handling Patterns Across Codebase

**Why**
The refactoring audit identified 3 different error handling approaches across the codebase:
- Frontend: Mix of try/catch blocks, toast notifications, and console errors
- Backend: Inconsistent exception types and response formats
- Services: Different error propagation strategies

This creates inconsistent user experience and makes debugging difficult.

**How**

* **Phase 1: Create Error Hierarchy**
  ```php
  // Backend error hierarchy
  abstract class CircularProtocolException extends Exception {
      public function getErrorCode(): string;
      public function getContext(): array;
  }
  
  class ValidationException extends CircularProtocolException {}
  class BlockchainException extends CircularProtocolException {}
  class NetworkException extends CircularProtocolException {}
  ```

  ```javascript
  // Frontend error hierarchy  
  class CircularProtocolError extends Error {
    constructor(message, code, context = {}) {
      super(message)
      this.code = code
      this.context = context
    }
  }
  
  class ValidationError extends CircularProtocolError {}
  class NetworkError extends CircularProtocolError {}
  class WalletError extends CircularProtocolError {}
  ```

* **Phase 2: Create Centralized Error Handlers**
  ```javascript
  // composables/useErrorHandler.js
  export const useErrorHandler = () => {
    const handleError = (error, context = {}) => {
      // Log error with context
      console.error('[ErrorHandler]', { error, context })
      
      // Show appropriate user notification
      if (error instanceof ValidationError) {
        showValidationToast(error.message)
      } else if (error instanceof NetworkError) {
        showNetworkErrorToast()
      } else {
        showGenericErrorToast()
      }
      
      // Report to monitoring service
      reportError(error, context)
    }
    
    return { handleError }
  }
  ```

* **Phase 3: Update All Error Handling**
  * Replace all manual try/catch blocks with standardized handlers
  * Update API responses to use consistent error format
  * Add error context (user action, component name, timestamp)
  * Implement error recovery strategies where possible

* **Phase 4: Add Error Monitoring**
  * Integrate with error tracking service (Sentry/Rollbar)
  * Add error rate monitoring
  * Create error reporting dashboard
  * Set up alerts for error spikes

**Test Plan**

1. **Error Hierarchy Testing**:
   - Each error type has appropriate properties and methods
   - Error inheritance chain works correctly
   - Context data is preserved and serializable

2. **Handler Integration Testing**:
   - Errors are caught and handled consistently across components
   - User notifications appear for different error types
   - Error reporting to monitoring service works

3. **Recovery Testing**:
   - Network errors trigger retry mechanisms
   - Validation errors show corrective guidance
   - Fatal errors gracefully degrade functionality

**Effort Estimate**: 8-10 hours (1 week)
**Priority**: High - Improves debugging and user experience

---

# 🟠 PR 21: Consolidate Duplicate Validation Logic 

**Why**
The audit found duplicate address validation logic in 8+ files:
- `utils/validation.js`
- `utils/addressFormatting.js`  
- `composables/useCircularAddressValidation.js`
- `components/RecipientAddressInput.vue`
- `components/SwapForm.vue`
- Plus backend validation in multiple controllers

This creates maintenance burden and inconsistency risks.

**How**

* **Phase 1: Create Unified Validation Library**
  ```typescript
  // utils/validation/index.ts
  export interface ValidationResult {
    isValid: boolean
    error?: string
    code?: string
  }
  
  export interface AddressValidator {
    validate(address: string): ValidationResult
    validateAsync?(address: string): Promise<ValidationResult>
  }
  
  export class EthereumAddressValidator implements AddressValidator {
    validate(address: string): ValidationResult {
      // Single source of truth for Ethereum validation
    }
  }
  
  export class CircularAddressValidator implements AddressValidator {
    validate(address: string): ValidationResult {
      // Single source of truth for Circular validation  
    }
    
    async validateAsync(address: string): Promise<ValidationResult> {
      // API-based validation with balance checking
    }
  }
  ```

* **Phase 2: Create Validation Factory**
  ```typescript
  export class ValidationFactory {
    static getAddressValidator(type: 'ethereum' | 'circular' | 'solana'): AddressValidator {
      switch (type) {
        case 'ethereum': return new EthereumAddressValidator()
        case 'circular': return new CircularAddressValidator()  
        case 'solana': return new SolanaAddressValidator()
        default: throw new Error(`Unknown address type: ${type}`)
      }
    }
  }
  ```

* **Phase 3: Update All Validation Usage**
  * Replace inline validation with factory-created validators
  * Update components to use unified validation interface
  * Migrate backend controllers to use same validation logic
  * Ensure async validation (API calls) works consistently

* **Phase 4: Add Comprehensive Tests**
  * Unit tests for each validator class
  * Integration tests for validation factory
  * Component tests using validation
  * Backend API tests with validation

**Test Plan**

1. **Validator Testing**:
   - Each validator handles valid/invalid inputs correctly
   - Edge cases (empty, malformed, boundary values) work
   - Async validators handle network errors gracefully

2. **Integration Testing**:
   - All components use same validation logic
   - Backend and frontend validation results match
   - Error messages are consistent across the application

3. **Performance Testing**:
   - Validation performance doesn't degrade
   - Async validation has reasonable timeout/retry behavior
   - Memory usage remains stable

**Effort Estimate**: 6-8 hours (1 week)
**Priority**: High - Reduces maintenance burden

---

# 🟡 PR 22: Performance Optimization - Reduce Bundle Size and Improve Loading

**Why**
The refactoring audit identified several performance issues:
- Frontend bundle size is 2.8MB (target: <2MB)  
- Time to Interactive is 3.2s (target: <2s)
- Memory leaks growing at 10MB/hour
- Inefficient re-rendering in large components

**How**

* **Phase 1: Bundle Analysis and Code Splitting**
  * Run bundle analyzer to identify large dependencies
  * Implement dynamic imports for heavy components:
    ```javascript
    const TradingViewChart = defineAsyncComponent(() => 
      import('../components/TradingViewChart.vue')
    )
    ```
  * Split vendor bundles by usage patterns
  * Remove unused dependencies

* **Phase 2: Component Optimization**
  * Add `v-memo` directives for expensive list rendering
  * Implement virtual scrolling for large data sets
  * Use `shallowRef` and `shallowReactive` where appropriate
  * Add `defineAsyncComponent` for modals and overlays

* **Phase 3: Memory Leak Fixes**
  * Add proper cleanup in `onUnmounted` hooks
  * Remove event listeners and subscriptions
  * Clear intervals and timeouts
  * Implement WeakMap for caching where appropriate

* **Phase 4: Caching and Preloading**  
  * Implement service worker for API response caching
  * Add preload hints for critical resources
  * Use browser cache effectively for static assets
  * Implement intelligent prefetching for likely user actions

**Test Plan**

1. **Bundle Size Testing**:
   - Verify bundle size reduced to <2MB
   - Check that code splitting works correctly
   - Ensure no duplicate dependencies

2. **Performance Testing**:
   - Time to Interactive <2s on 3G connection
   - Memory usage stable over 30-minute session
   - Component rendering times improved

3. **Functionality Testing**:
   - All dynamic imports work correctly
   - No broken functionality from optimizations
   - Caching doesn't break real-time data

**Effort Estimate**: 10-12 hours (1.5 weeks)
**Priority**: Medium - Improves user experience

---

# 🟡 PR 23: Configuration Management Consolidation

**Why**
The audit found configuration scattered across multiple files:
- Environment variables in 6+ different files
- Hardcoded URLs and IDs in components
- Inconsistent configuration loading patterns
- No validation of required configuration

**How**

* **Phase 1: Create Configuration Schema**
  ```typescript
  interface AppConfig {
    api: {
      baseUrl: string
      timeout: number
      retries: number
    }
    blockchain: {
      networks: NetworkConfig[]
      defaultNetwork: string
    }
    ui: {
      debounceMs: number
      toastTimeout: number
      maxFileSize: number
    }
  }
  ```

* **Phase 2: Centralize Configuration Loading**
  ```typescript
  // config/index.ts
  export class ConfigManager {
    private static instance: AppConfig
    
    static load(): AppConfig {
      if (!this.instance) {
        this.instance = this.validate(this.loadFromEnv())
      }
      return this.instance
    }
    
    private static validate(config: unknown): AppConfig {
      // JSON schema validation
    }
  }
  ```

* **Phase 3: Update All Configuration Usage**
  * Replace hardcoded values with config references
  * Update components to use centralized config
  * Add runtime config validation
  * Create config documentation

**Test Plan**

1. **Configuration Loading**:
   - App fails to start with missing required config
   - Configuration validation catches invalid values
   - Default values work correctly

2. **Usage Testing**:
   - All hardcoded values replaced with config
   - Components receive config updates reactively
   - Build-time and runtime config work correctly

**Effort Estimate**: 6-8 hours (1 week)
**Priority**: Medium - Improves maintainability

---

