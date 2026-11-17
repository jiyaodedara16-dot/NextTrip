<?php
/**
 * auth/login.php
 * ------------------------------------------------------------
 * One auth page for the whole site: Sign in (all roles) and
 * Create account (Traveller / Travel agent, 3 steps + email OTP).
 *
 * Styles : assets/css/auth.css
 * Script : assets/js/auth.js
 * auth/register.php redirects here with ?mode=signup.
 * ------------------------------------------------------------
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/config.php';

// Redirect targets are written from the site root (for example 'destinations.php').
// Accept only plain page paths on this site, then build the path for this auth/ folder.
function nt_safe_redirect($raw) {
    $raw = ltrim(trim((string) $raw), '/');
    $raw = preg_replace('#^(\.\./)+#', '', $raw);
    if ($raw === '' || !preg_match('#^[A-Za-z0-9_\-]+(/[A-Za-z0-9_\-]+)*\.php(\?[A-Za-z0-9_=&%.\-]*)?$#', $raw)) {
        return '';
    }
    return $raw;
}
$redirectRaw = nt_safe_redirect($_POST['redirect'] ?? $_GET['redirect'] ?? '');
$redirect    = $redirectRaw !== '' ? '../' . $redirectRaw : '';
$mode     = ($_GET['mode'] ?? '') === 'signup' ? 'signup' : 'signin';
$error    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'signin') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = trim($_POST['password'] ?? '');

    if ($identifier === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT a.account_id, a.password_hash, a.status, r.role_name
                                        FROM accounts a
                                        JOIN roles r ON r.role_id = a.role_id
                                        WHERE a.email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $identifier);
        mysqli_stmt_execute($stmt);
        $acc = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        // Seeded demo rows store a plain-text password; accounts created
        // through this page use password_hash().
        $valid = $acc && (
            $acc['password_hash'] === $password ||
            password_verify($password, $acc['password_hash'])
        );

        if (!$valid) {
            $error = 'Invalid email or password.';
        } elseif ($acc['status'] !== 'Active') {
            $error = $acc['status'] === 'Blocked'
                ? 'This account has been blocked. Contact support for help.'
                : 'This account is inactive. Contact support for help.';
        } else {
            $accountId = (int) $acc['account_id'];
            if ($acc['role_name'] === 'User') {
                $u = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id, first_name, last_name FROM users WHERE account_id = $accountId LIMIT 1"));
                $_SESSION['user_id']    = (int) $u['user_id'];
                $_SESSION['account_id'] = $accountId;
                $_SESSION['user_name']  = trim($u['first_name'] . ' ' . $u['last_name']);
                mysqli_query($conn, "UPDATE accounts SET last_login = NOW() WHERE account_id = $accountId");
                header('Location: ' . ($redirect !== '' ? $redirect : '../index.php'));
                exit;
            } elseif ($acc['role_name'] === 'Agent') {
                $ag = mysqli_fetch_assoc(mysqli_query($conn, "SELECT agent_id, full_name FROM agents WHERE account_id = $accountId LIMIT 1"));
                $_SESSION['agent_id']   = (int) $ag['agent_id'];
                $_SESSION['account_id'] = $accountId;
                $_SESSION['agent_name'] = $ag['full_name'];
                mysqli_query($conn, "UPDATE accounts SET last_login = NOW() WHERE account_id = $accountId");
                header('Location: ../agent/dashboard.php');
                exit;
            } else { // Admin
                $_SESSION['admin_id']    = $accountId;
                $_SESSION['admin_email'] = $identifier;
                $_SESSION['admin_name']  = 'Admin';
                mysqli_query($conn, "UPDATE accounts SET last_login = NOW() WHERE account_id = $accountId");
                header('Location: ../admin/dashboard.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign in · NextTrip</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Manrope:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body class="auth-page">

<button type="button" class="signup-close" id="signupClose" aria-label="Close and go back" title="Close">&times;</button>
<style>
  .signup-close {
    position: fixed; top: 18px; right: 18px; z-index: 9999;
    width: 44px; height: 44px; border-radius: 50%;
    border: 1px solid #e3ebe6; background: #ffffff; color: #146339;
    font-size: 26px; line-height: 1; cursor: pointer;
    display: grid; place-items: center;
    box-shadow: 0 10px 24px -12px rgba(16,24,20,.35);
    transition: transform .25s cubic-bezier(.22,1,.36,1), background .25s, color .25s, border-color .25s;
    animation: signup-close-in .5s cubic-bezier(.22,1,.36,1) both;
  }
  .signup-close:hover { background: #fbe9e9; color: #d64545; border-color: #f2c9c9; transform: rotate(90deg); }
  .signup-close:focus-visible { outline: 3px solid #f4a627; outline-offset: 3px; }
  @keyframes signup-close-in { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: none; } }
  @media (max-width: 560px) { .signup-close { top: 12px; right: 12px; width: 40px; height: 40px; } }
  @media (prefers-reduced-motion: reduce) { .signup-close { animation: none; transition: none; } }
</style>
<script>
  (function () {
    var btn = document.getElementById('signupClose');
    if (!btn) return;
    btn.addEventListener('click', function () {
      if (window.history.length > 1 && document.referrer) {
        window.history.back();
      } else {
        window.location.href = '../index.php';
      }
    });
  })();
</script>

  <!-- Icon sprite (referenced with <use href="#i-…">) -->
  <svg class="sprite" aria-hidden="true" focusable="false">
    <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></symbol>
    <symbol id="i-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3.5 7.5l8.5 6 8.5-6"/></symbol>
    <symbol id="i-phone" viewBox="0 0 24 24"><path d="M5 4h3.2l1.6 4.2-2 1.4a11 11 0 0 0 6.6 6.6l1.4-2 4.2 1.6V19a2 2 0 0 1-2.2 2A16 16 0 0 1 3 6.2 2 2 0 0 1 5 4z"/></symbol>
    <symbol id="i-lock" viewBox="0 0 24 24"><rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/></symbol>
    <symbol id="i-pin" viewBox="0 0 24 24"><path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.4"/></symbol>
    <symbol id="i-home" viewBox="0 0 24 24"><path d="M3.5 11L12 4l8.5 7"/><path d="M5.5 10v9.5h13V10"/></symbol>
    <symbol id="i-briefcase" viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="13" rx="2.5"/><path d="M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7"/></symbol>
    <symbol id="i-upload" viewBox="0 0 24 24"><path d="M12 15V4"/><path d="M7.5 8.5L12 4l4.5 4.5"/><path d="M4 15v3.5A1.5 1.5 0 0 0 5.5 20h13a1.5 1.5 0 0 0 1.5-1.5V15"/></symbol>
    <symbol id="i-file" viewBox="0 0 24 24"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></symbol>
    <symbol id="i-eye" viewBox="0 0 24 24"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></symbol>
    <symbol id="i-eye-off" viewBox="0 0 24 24"><path d="M3 3l18 18"/><path d="M10.6 6.1A10 10 0 0 1 12 6c6.4 0 10 6 10 6a17 17 0 0 1-3.2 3.9M6.6 6.6C3.8 8.4 2 12 2 12s3.6 7 10 7a9.8 9.8 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></symbol>
    <symbol id="i-compass" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M15.5 8.5l-2 5-5 2 2-5z"/></symbol>
    <symbol id="i-mail-check" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3.5 7.5l8.5 6 8.5-6"/></symbol>
    <symbol id="i-arrow-right" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></symbol>
    <symbol id="i-arrow-left" viewBox="0 0 24 24"><path d="M19 12H5"/><path d="M11 6l-6 6 6 6"/></symbol>
    <symbol id="i-alert" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 16h.01"/></symbol>
    <symbol id="i-login" viewBox="0 0 24 24"><path d="M14 4h4.5A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5H14"/><path d="M4 12h10"/><path d="M10 8l4 4-4 4"/></symbol>
  </svg>

  <div class="auth-card<?= $mode === 'signup' ? ' right-panel-active' : '' ?>" id="authCard">

    <!-- ===================== SIGN IN ===================== -->
    <div class="auth-form-side sign-in-side">
      <form id="form-signin" class="auth-panel" method="POST" novalidate>
        <input type="hidden" name="form" value="signin">
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

        <span class="eyebrow">Welcome back</span>
        <h1 class="panel-title">Sign in</h1>
        <p class="panel-sub">Travellers, agents and admins all sign in here.</p>

        <?php if ($error): ?>
        <div class="form-alert" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="panel-body">
          <div class="field">
            <label class="field__label" for="si-id">Email address</label>
            <div class="control">
              <span class="control__icon"><svg class="ico" aria-hidden="true"><use href="#i-mail"/></svg></span>
              <input type="text" id="si-id" name="identifier" placeholder="you@example.com" autocomplete="username" value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>" required>
            </div>
          </div>

          <div class="field">
            <label class="field__label" for="si-pass">Password</label>
            <div class="control control--action">
              <span class="control__icon"><svg class="ico" aria-hidden="true"><use href="#i-lock"/></svg></span>
              <input type="password" id="si-pass" name="password" placeholder="Enter your password" autocomplete="current-password" required>
              <button type="button" class="control__btn" data-toggle-pw data-target="si-pass" aria-label="Show password"></button>
            </div>
          </div>
        </div>

        <div class="panel-foot">
          <button type="submit" class="btn btn--primary">
            <span class="btn__label">Sign in</span>
            <svg class="ico" aria-hidden="true"><use href="#i-login"/></svg>
            <span class="btn__spin"></span>
          </button>
        </div>

        <div class="mobile-switch">New here? <button type="button" id="toSignUpMobile">Create an account</button></div>
      </form>
    </div>

    <!-- ===================== CREATE ACCOUNT ===================== -->
    <div class="auth-form-side sign-up-side">
      <div class="signup-stack" id="signupInner">

        <!-- STEP 1 · Account -->
        <section class="auth-panel" id="step1" data-step-panel="1">
          <div class="steps" aria-hidden="true"><span class="is-active"></span><span></span><span></span></div>
          <span class="eyebrow">Step 1 of 3 · Account</span>
          <h1 class="panel-title">Let&rsquo;s get started</h1>
          <p class="panel-sub">First, tell us who you are.</p>

          <div class="panel-body">
            <div class="seg" id="register-role-tabs" role="tablist" aria-label="Account type">
              <button type="button" role="tab" aria-selected="true" data-role="user">Traveller</button>
              <button type="button" role="tab" aria-selected="false" data-role="agent">Travel agent</button>
            </div>

            <div class="row" id="userNameFields">
              <div class="field">
                <label class="field__label" for="re-first">First name <span class="req">*</span></label>
                <div class="control">
                  <span class="control__icon"><svg class="ico" aria-hidden="true"><use href="#i-user"/></svg></span>
                  <input type="text" id="re-first" name="first_name" placeholder="Name" autocomplete="given-name">
                </div>
              </div>
              <div class="field">
                <label class="field__label" for="re-last">Last name <span class="req">*</span></label>
                <div class="control">
                  <span class="control__icon"><svg class="ico" aria-hidden="true"><use href="#i-user"/></svg></span>
                  <input type="text" id="re-last" name="last_name" placeholder="Surname" autocomplete="family-name">
                </div>
              </div>
            </div>

            <div class="field" id="agentNameField" hidden>
              <label class="field__label" for="re-agent-name">Full name <span class="req">*</span></label>
              <div class="control">
                <span class="control__icon"><svg class="ico" aria-hidden="true"><use href="#i-user"/></svg></span>
                <input type="text" id="re-agent-name" name="full_name" placeholder="Full Name" autocomplete="name">
              </div>
            </div>

            <div class="field">
              <label class="field__label" for="re-email">Email address <span class="req">*</span></label>
              <div class="control">
                <span class="control__icon"><svg class="ico" aria-hidden="true"><use href="#i-mail"/></svg></span>
                <input type="email" id="re-email" name="email" placeholder="you@example.com" autocomplete="email">
              </div>
            </div>

            <div class="field">
              <label class="field__label" for="re-phone">Phone <span class="req">*</span></label>
              <div class="control">
                <span class="control__icon"><svg class="ico" aria-hidden="true"><use href="#i-phone"/></svg></span>
                <input type="tel" id="re-phone" name="phone" placeholder="+91 90000 00000" autocomplete="tel">
              </div>
            </div>
          </div>

          <div class="panel-foot">
            <button type="button" class="btn btn--primary" data-next="2">
              <span class="btn__label">Continue</span>
              <svg class="ico" aria-hidden="true"><use href="#i-arrow-right"/></svg>
            </button>
          </div>
          <div class="mobile-switch">Already have an account? <button type="button" id="toSignInMobile">Sign in</button></div>
        </section>

        <!-- STEP 2 · Details -->
        <section class="auth-panel" id="step2" data-step-panel="2" hidden>
          <div class="steps" aria-hidden="true"><span class="is-done"></span><span class="is-active"></span><span></span></div>
          <span class="eyebrow">Step 2 of 3 · Details</span>
          <h1 class="panel-title" id="step2Title">Where are you based?</h1>
          <p class="panel-sub" id="step2Sub">Helps us tailor recommendations to you.</p>

          <div class="panel-body">
            <!-- Traveller -->
            <div id="userDetailFields">
              <div class="row">
                <div class="field">
                  <label class="field__label" for="re-address">Address <span class="opt">(optional)</span></label>
                  <div class="control">
                    <span class="control__icon"><svg class="ico" aria-hidden="true"><use href="#i-home"/></svg></span>
                    <input type="text" id="re-address" name="address" placeholder="Street, area" autocomplete="street-address">
                  </div>
                </div>
                <div class="field">
                  <label class="field__label" for="re-city">City <span class="opt">(optional)</span></label>
                  <div class="control">
                    <span class="control__icon"><svg class="ico" aria-hidden="true"><use href="#i-pin"/></svg></span>
                    <input type="text" id="re-city" name="city" placeholder="Ahmedabad" autocomplete="address-level2">
                  </div>
                </div>
              </div>
            </div>

            <!-- Travel agent -->
            <div id="agentDetailFields" hidden>
              <div class="row row--addr">
                <div class="field">
                  <label class="field__label" for="re-agent-address">Address <span class="req">*</span></label>
                  <div class="control">
                    <span class="control__icon"><svg class="ico" aria-hidden="true"><use href="#i-home"/></svg></span>
                    <input type="text" id="re-agent-address" name="agent_address" placeholder="Street, area" autocomplete="street-address">
                  </div>
                </div>
                <div class="field">
                  <label class="field__label" for="re-agent-city">City <span class="req">*</span></label>
                  <div class="control">
                    <span class="control__icon"><svg class="ico" aria-hidden="true"><use href="#i-pin"/></svg></span>
                    <input type="text" id="re-agent-city" name="agent_city" placeholder="Ahmedabad" autocomplete="address-level2">
                  </div>
                </div>
              </div>
              <div class="field">
                <span class="field__label">Aadhaar card <span class="req">*</span></span>
                <div class="doc" id="fieldAadhaar">
                  <input type="file" id="re-aadhaar" name="aadhaar_file" accept="image/jpeg,image/png,image/webp,application/pdf" aria-label="Aadhaar card">
                  <span class="doc__tile"><svg class="ico" aria-hidden="true"><use href="#i-upload"/></svg></span>
                  <span class="doc__text">
                    <span class="doc__name" data-default="Upload Aadhaar card">Upload Aadhaar card</span>
                    <span class="doc__hint">JPG, PNG or PDF, up to 5 MB</span>
                  </span>
                  <span class="doc__status"><svg class="ico" aria-hidden="true"><use href="#i-check"/></svg></span>
                </div>
              </div>
              <div class="field">
                <span class="field__label">PAN card <span class="req">*</span></span>
                <div class="doc" id="fieldPan">
                  <input type="file" id="re-pan" name="pan_file" accept="image/jpeg,image/png,image/webp,application/pdf" aria-label="PAN card">
                  <span class="doc__tile"><svg class="ico" aria-hidden="true"><use href="#i-upload"/></svg></span>
                  <span class="doc__text">
                    <span class="doc__name" data-default="Upload PAN card">Upload PAN card</span>
                    <span class="doc__hint">JPG, PNG or PDF, up to 5 MB</span>
                  </span>
                  <span class="doc__status"><svg class="ico" aria-hidden="true"><use href="#i-check"/></svg></span>
                </div>
              </div>
              <div class="field">
                <span class="field__label">Business document <span class="opt">(GST, optional)</span></span>
                <div class="doc" id="fieldBusinessDoc">
                  <input type="file" id="re-business-doc" name="business_document_file" accept="image/jpeg,image/png,image/webp,application/pdf" aria-label="Business document (GST, optional)">
                  <span class="doc__tile"><svg class="ico" aria-hidden="true"><use href="#i-upload"/></svg></span>
                  <span class="doc__text">
                    <span class="doc__name" data-default="Upload business document">Upload business document</span>
                    <span class="doc__hint">JPG, PNG or PDF, up to 5 MB</span>
                  </span>
                  <span class="doc__status"><svg class="ico" aria-hidden="true"><use href="#i-check"/></svg></span>
                </div>
              </div>
            </div>
          </div>

          <div class="panel-foot">
            <button type="button" class="btn btn--ghost btn--back" data-back="1">
              <svg class="ico" aria-hidden="true"><use href="#i-arrow-left"/></svg>Back
            </button>
            <button type="button" class="btn btn--primary" data-next="3">
              <span class="btn__label">Continue</span>
              <svg class="ico" aria-hidden="true"><use href="#i-arrow-right"/></svg>
            </button>
          </div>
        </section>

        <!-- STEP 3 · Security -->
        <form class="auth-panel" id="form-register" data-step-panel="3" hidden novalidate>
          <div class="steps" aria-hidden="true"><span class="is-done"></span><span class="is-done"></span><span class="is-active"></span></div>
          <span class="eyebrow">Step 3 of 3 · Security</span>
          <h1 class="panel-title">Secure your account</h1>
          <p class="panel-sub">One last step before we send your verification code.</p>

          <div class="form-alert" id="registerFormError" role="alert" hidden></div>

          <div class="panel-body">
            <div class="field">
              <label class="field__label" for="re-pass">Password <span class="req">*</span></label>
              <div class="control control--action">
                <span class="control__icon"><svg class="ico" aria-hidden="true"><use href="#i-lock"/></svg></span>
                <input type="password" id="re-pass" name="password" placeholder="At least 8 characters" autocomplete="new-password">
                <button type="button" class="control__btn" data-toggle-pw data-target="re-pass" aria-label="Show password"></button>
              </div>
            </div>

            <div class="field">
              <label class="field__label" for="re-confirm">Confirm password <span class="req">*</span></label>
              <div class="control control--action">
                <span class="control__icon"><svg class="ico" aria-hidden="true"><use href="#i-lock"/></svg></span>
                <input type="password" id="re-confirm" name="password_confirm" placeholder="Re-enter your password" autocomplete="new-password">
                <button type="button" class="control__btn" data-toggle-pw data-target="re-confirm" aria-label="Show password"></button>
              </div>
              <span class="field__hint">Use a letter and a number, at least 8 characters.</span>
            </div>

            <div class="terms-field">
              <label class="terms">
                <input type="checkbox" id="re-terms" name="terms">
                <span>I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>.</span>
              </label>
              <span class="field__error" id="termsError" role="alert" hidden></span>
            </div>
          </div>

          <div class="panel-foot">
            <button type="button" class="btn btn--ghost btn--back" data-back="2">
              <svg class="ico" aria-hidden="true"><use href="#i-arrow-left"/></svg>Back
            </button>
            <button type="submit" class="btn btn--primary" id="signupContinueBtn">
              <span class="btn__label">Create account</span>
              <span class="btn__spin"></span>
            </button>
          </div>
        </form>

        <!-- STEP 4 · Verify email -->
        <section class="auth-panel otp-panel" id="stepOtp" data-step-panel="otp" hidden>
          <form id="form-otp" class="otp-form" novalidate>
            <div class="otp-icon"><svg class="ico" aria-hidden="true"><use href="#i-mail-check"/></svg></div>
            <span class="eyebrow">Verify your email</span>
            <h1 class="panel-title">Enter your code</h1>
            <p class="panel-sub">We sent a 6-digit code to <span class="otp-email" id="otpEmailDisplay"></span>.</p>

            <div class="notice" id="otpMailWarning" hidden>
              <svg class="ico" aria-hidden="true"><use href="#i-alert"/></svg>
              <span>We couldn&rsquo;t confirm delivery of that email. Check your inbox in a minute, or use Resend below.</span>
            </div>

            <div class="otp-grid" id="otpBoxes">
              <input type="text" inputmode="numeric" maxlength="1" aria-label="Digit 1">
              <input type="text" inputmode="numeric" maxlength="1" aria-label="Digit 2">
              <input type="text" inputmode="numeric" maxlength="1" aria-label="Digit 3">
              <input type="text" inputmode="numeric" maxlength="1" aria-label="Digit 4">
              <input type="text" inputmode="numeric" maxlength="1" aria-label="Digit 5">
              <input type="text" inputmode="numeric" maxlength="1" aria-label="Digit 6">
            </div>
            <span class="field__error" id="otpError" role="alert" hidden></span>

            <div class="panel-foot">
              <button type="submit" class="btn btn--primary" id="otpVerifyBtn">
                <span class="btn__label">Verify and create account</span>
                <span class="btn__spin"></span>
              </button>
            </div>
          </form>

          <div class="resend">
            <span id="otpResendHint">Resend code in <span id="otpCountdown">20</span>s</span>
            <button type="button" id="otpResendBtn" hidden>Resend code</button>
          </div>
          <button type="button" class="link-back" id="otpBackBtn">
            <svg class="ico" aria-hidden="true"><use href="#i-arrow-left"/></svg>Edit details
          </button>
        </section>

      </div>
    </div>

    <!-- ===================== BRAND OVERLAY ===================== -->
    <div class="auth-overlay-wrap">
      <div class="auth-overlay">
        <div class="overlay-panel overlay-left">
          <span class="overlay-mark"><svg class="ico" aria-hidden="true"><use href="#i-compass"/></svg></span>
          <span class="overlay-tag">Smart tourism · Gujarat</span>
          <h2>Welcome back, explorer</h2>
          <p>Sign in to pick up your saved itineraries, favourites and trip plans right where you left off.</p>
          <button type="button" class="btn btn--glass" id="toSignIn">Sign in</button>
        </div>
        <div class="overlay-panel overlay-right">
          <span class="overlay-mark"><svg class="ico" aria-hidden="true"><use href="#i-briefcase"/></svg></span>
          <span class="overlay-tag">New to NextTrip?</span>
          <h2>Start your journey</h2>
          <p>Create a traveller account to plan smarter, or apply as a travel agent to list packages and destinations.</p>
          <button type="button" class="btn btn--glass" id="toSignUp">Create account</button>
        </div>
      </div>
    </div>

  </div>

  <div class="toast" id="toast" role="status" aria-live="polite"></div>

  <script src="../assets/js/auth.js" defer></script>
</body>
</html>
