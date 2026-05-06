## Plan: Unified OTP Activation

Replace auto-login on registration with a mandatory OTP activation flow for customer accounts. Registration will create the account without tokens, login for an unverified customer will auto-send a fresh OTP and return a verification challenge without tokens, and successful OTP verification will activate the account and return access plus refresh tokens. The implementation should support both email and phone channels, keep verification state separate from `is_active`, integrate Vonage for SMS delivery, and avoid locking out existing staff or legacy customers during rollout.

**Steps**
1. Phase 1 — Normalize the account verification model. Add a new nullable `phone_verified_at` column, make `users.email` nullable so phone-only registration is legal, and add a database-level unique index for `users.phone` because phone login becomes a first-class identifier. Keep `email_verified_at` for email verification only, keep `is_active` as the admin/business toggle, and add a single computed helper on the user model for “account is verified” based on the selected channel or either verified timestamp.
2. Phase 1 — Define rollout safety for existing data. Exempt `admin` and `provider` accounts from the new customer activation gate, and grandfather existing customer accounts so the deploy does not lock them out. During implementation, also account for the fact that API registration currently does not assign the `customer` role, so the rollout logic should either backfill missing customer roles or classify protected accounts by “not admin/provider” until role data is normalized.
3. Phase 2 — Upgrade the request contracts. Update registration to require `registration_method` from `RegistrationMethod`, require `email` only when the method is `email`, require `phone` only when the method is `phone`, and allow phone-only registration by making the other identifier optional. Update login to accept the same method flag plus the matching identifier field, and align OTP verify/resend requests so they can operate generically on either channel instead of keeping an email-only activation path.
4. Phase 2 — Refactor OTP orchestration around channels. Keep OTP persistence in the existing `otps` table, but move delivery into explicit channel-aware handling in `OtpService`: email continues through mail, SMS is added through a Vonage-backed integration configured in `config/services.php` and environment variables. Preserve the current “invalidate previous unused OTPs” behavior per target and type, and return masked target metadata in responses so the client can show where the OTP was sent.
5. Phase 3 — Change registration behavior. In the API register flow, create the user in an unverified state, assign the `customer` role immediately, send OTP through the chosen channel, and return a `201` response with user data plus verification metadata such as `requires_otp_verification`, `registration_method`, and masked destination, but no access or refresh tokens. Keep the debug-only OTP echo for non-production if that pattern is still wanted in this repository.
6. Phase 3 — Change login behavior into a verification challenge for unverified customers. Resolve the account by the supplied method and identifier, validate the password, and branch by account type. Verified customers and exempt staff continue to receive tokens normally. Unverified customers receive no tokens; instead the API generates or resends an OTP on the registered channel and returns a structured challenge response with `requires_otp_verification=true`. Apply the same verification check to refresh-token issuance so an unverified customer cannot bypass the challenge through the refresh endpoint.
7. Phase 3 — Make OTP verification the point where full login completes. Replace or extend the current OTP verification endpoint so that, after a valid OTP, the controller updates the correct timestamp (`email_verified_at` or `phone_verified_at`), preserves `email_verified_via_otp_at` for the email-specific audit trail if still useful, and then issues access plus refresh tokens in the same response. Resend endpoints should also become channel-aware so the frontend can use a single flow after either register or login.
8. Phase 4 — Enforce verification with the correct middleware. The current bookings routes use Laravel `verified`, but the user model does not implement `MustVerifyEmail`, so this protection is effectively not enforcing customer OTP activation. Replace that with a dedicated customer account-verification middleware, either by evolving the existing `verified.otp` alias into a generic account gate or by introducing a clearer alias. Apply it to customer-authenticated API routes that should stay inaccessible before activation, while leaving verification, resend, and logout paths reachable.
9. Phase 4 — Update API resources and contracts. Extend the auth responses and, if needed, `UserResource` so the client receives consistent verification-state fields. Update `API.md`, the API test plan, and the Postman collection to document the new register, login, verify-OTP, resend-OTP, and refresh behaviors, including the fact that register no longer auto-logs in and unverified login returns a verification challenge instead of tokens.
10. Phase 5 — Add focused automated coverage. There are currently no auth feature tests in this repository, so add new feature tests for: email registration without tokens, phone registration without tokens, unverified login issuing an OTP challenge, successful email OTP verification returning tokens, successful phone OTP verification returning tokens, refresh being denied for unverified customers, verification middleware blocking protected customer endpoints, and admin/provider login remaining unaffected. Use the existing `RefreshDatabase`, `Sanctum`, and facade-fake patterns already used in the feature test suite.

**Relevant files**
- `d:\Coding\BarberBooking\app\Http\Controllers\Api\AuthController.php` — rework `register()`, `login()`, and `refresh()` to remove premature token issuance and add the verification challenge branch.
- `d:\Coding\BarberBooking\app\Http\Controllers\Api\OtpController.php` — unify account verification and resend flows for email and phone; make verify return tokens after activation.
- `d:\Coding\BarberBooking\app\Services\OtpService.php` — keep OTP generation and validation, add explicit channel delivery and Vonage-backed SMS sending.
- `d:\Coding\BarberBooking\app\Services\AuthTokenService.php` — reuse token creation, but guard refresh against unverified customer accounts.
- `d:\Coding\BarberBooking\app\Http\Requests\RegisterRequest.php` — add conditional validation around `registration_method`, `email`, and `phone`.
- `d:\Coding\BarberBooking\app\Http\Requests\LoginRequest.php` — add conditional validation for login by email or phone.
- `d:\Coding\BarberBooking\app\Enum\RegistrationMethod.php` — use as the canonical request flag for auth/register/login/verify flows.
- `d:\Coding\BarberBooking\app\Enum\OtpType.php` — keep as the delivery-channel enum used by OTP generation and verification.
- `d:\Coding\BarberBooking\app\Models\User.php` — add `phone_verified_at`, casts, fillable handling, and a computed verification helper; keep `is_active` separate from activation.
- `d:\Coding\BarberBooking\app\Http\Middleware\EnsureEmailIsVerifiedViaOtp.php` — likely evolve into a generic customer-account verification middleware or replace with a clearer equivalent.
- `d:\Coding\BarberBooking\bootstrap\app.php` — register the final middleware alias used by the protected customer API routes.
- `d:\Coding\BarberBooking\routes\api.php` — swap ineffective `verified` usage for the project’s customer verification middleware and keep verify/resend routes outside the protected gate.
- `d:\Coding\BarberBooking\app\Http\Resources\UserResource.php` — optionally expose normalized verification metadata used by the client after register/login/verify.
- `d:\Coding\BarberBooking\database\migrations\0001_01_01_000000_create_users_table.php` — reference point for current constraints that must be superseded by a new migration.
- `d:\Coding\BarberBooking\config\services.php` — add Vonage/Nexmo credentials and service configuration.
- `d:\Coding\BarberBooking\composer.json` — add the Vonage client package if it is not already present.
- `d:\Coding\BarberBooking\API.md` — update frontend-facing auth documentation and response examples.
- `d:\Coding\BarberBooking\docs\API\API_TEST_PLAN.md` — align API QA scenarios with the new verification-first auth lifecycle.
- `d:\Coding\BarberBooking\docs\API\BarberBooking_Postman_Collection.json` — update requests and token-capture logic for the changed auth flow.
- `d:\Coding\BarberBooking\tests\Feature\ProfileUpdateTest.php` — reference for existing API feature-test style.
- `d:\Coding\BarberBooking\tests\Feature\DeleteAccountTest.php` — reference for Sanctum and database-assertion patterns.
- `d:\Coding\BarberBooking\tests\Feature` — add new auth verification feature tests in this area.

**Verification**
1. Run focused feature tests for the new auth and OTP scenarios, including both email and phone registration/login/verification paths.
2. Run a narrow API regression pass for `register`, `login`, `refresh`, `verify-otp`, `resend-verification-otp`, and one protected customer route to verify middleware behavior.
3. Validate Vonage SMS integration in a non-production environment and confirm OTP resend/invalidation behavior for repeated login attempts.
4. Verify rollout safety by testing one grandfathered customer account plus one admin/provider account to confirm they are not blocked unexpectedly.
5. Reconcile and update all public API docs and Postman examples so frontend consumers are not working against stale token semantics.

**Decisions**
- Agreed: unverified login should automatically send OTP and return a verification challenge without tokens.
- Agreed: successful OTP verification should return access and refresh tokens directly.
- Agreed: login and registration should use a method flag with separate `email` or `phone` fields, not a single merged identifier field.
- Agreed: add `phone_verified_at`; do not overload `email_verified_at` or `is_active` to mean generic phone activation.
- Agreed: phone-only registration is valid, so `users.email` must become nullable.
- Agreed: phone verification is in scope now and should integrate with Vonage/Nexmo.
- Agreed: `admin` and `provider` accounts stay exempt; the stricter activation rule applies to customer onboarding without breaking existing access.

**Further Considerations**
1. If backward compatibility matters for existing clients, the implementation can temporarily keep `verify-email-otp` as a thin wrapper around the new generic verification flow while introducing the new canonical contract.
2. Because the current API register path does not assign the `customer` role, the rollout should explicitly normalize that gap instead of assuming every customer record already carries the role metadata needed for exemption logic.
3. Since there are no current auth feature tests, treat the test slice as part of the core deliverable rather than optional follow-up work.
