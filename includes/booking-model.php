<?php
/* Booking modal. Included by package-details.php. Expects $package, $id, $isLoggedIn, $currentUserId. */
require_once __DIR__ . '/booking-lib.php';
if (!$isLoggedIn) return;

$bkPrice = (float) $package['price_per_person'];
$bkMaxA  = (int) ($package['max_adults'] ?: 0);
$bkMaxC  = (int) ($package['max_children'] ?: 0);
$bkUser  = bk_row("SELECT u.first_name, u.last_name, u.phone, a.email FROM users u JOIN accounts a ON a.account_id = u.account_id WHERE u.user_id = $currentUserId");
$bkName  = $bkUser ? trim($bkUser['first_name'] . ' ' . $bkUser['last_name']) : '';
$bkPhone = $bkUser['phone'] ?? '';
$bkEmail = $bkUser['email'] ?? '';
$bkMin   = date('Y-m-d', strtotime('+1 day'));
$e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
?>
<div class="bk-backdrop" id="bkModal" aria-hidden="true">
  <div class="bk-dialog" role="dialog" aria-modal="true" aria-labelledby="bkTitle"
       data-price="<?= $bkPrice ?>" data-max-adults="<?= $bkMaxA ?>" data-max-children="<?= $bkMaxC ?>">

    <header class="bk-head">
      <div>
        <span class="bk-eyebrow">Book your trip</span>
        <h2 id="bkTitle"><?= $e($package['package_name']) ?></h2>
        <p class="bk-sub"><?= (int) $package['duration_days'] ?> days / <?= (int) $package['duration_nights'] ?> nights · <?= bk_money($bkPrice) ?> per adult</p>
      </div>
      <button type="button" class="bk-close" data-bk-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
    </header>

    <ol class="bk-steps">
      <li class="is-active" data-ind="0"><span>1</span> Trip</li>
      <li data-ind="1"><span>2</span> Travellers</li>
      <li data-ind="2"><span>3</span> Review</li>
    </ol>

    <div class="bk-alert" id="bkAlert" hidden></div>

    <form id="bkForm" novalidate>
      <input type="hidden" name="package_id" value="<?= (int) $id ?>">

      <!-- Step 1: trip -->
      <section class="bk-step is-active" data-step="0">
        <div class="bk-field">
          <label for="bkDate">Travel date</label>
          <input type="date" id="bkDate" name="travel_date" min="<?= $bkMin ?>" required>
          <small>Pick a date from tomorrow onwards.</small>
        </div>
        <div class="bk-grid-2">
          <div class="bk-field">
            <label>Adults</label>
            <div class="bk-stepper" data-stepper>
              <button type="button" data-delta="-1" aria-label="Fewer adults">−</button>
              <input type="number" id="bkAdults" name="adult_count" value="1" min="1" <?= $bkMaxA ? 'max="' . $bkMaxA . '"' : '' ?> readonly>
              <button type="button" data-delta="1" aria-label="More adults">+</button>
            </div>
          </div>
          <div class="bk-field">
            <label>Children</label>
            <div class="bk-stepper" data-stepper>
              <button type="button" data-delta="-1" aria-label="Fewer children">−</button>
              <input type="number" id="bkChildren" name="child_count" value="0" min="0" <?= $bkMaxC ? 'max="' . $bkMaxC . '"' : '' ?> readonly>
              <button type="button" data-delta="1" aria-label="More children">+</button>
            </div>
            <small>Children are charged at 50% of the adult fare.</small>
          </div>
        </div>
        <?php if ($bkMaxA || $bkMaxC): ?>
          <p class="bk-hint"><i class="bi bi-info-circle"></i> Up to <?= $bkMaxA ?: 'any' ?> adults and <?= $bkMaxC ?: 'any' ?> children per booking.</p>
        <?php endif; ?>
        <div class="bk-field">
          <label for="bkSpecial">Special requests <span class="bk-opt">(optional)</span></label>
          <textarea id="bkSpecial" name="special_requests" rows="3" maxlength="500" placeholder="Dietary needs, accessibility, room preferences…"></textarea>
        </div>
      </section>

      <!-- Step 2: travellers -->
      <section class="bk-step" data-step="1">
        <h4 class="bk-h4">Contact person</h4>
        <div class="bk-grid-2">
          <div class="bk-field">
            <label for="bkCName">Full name</label>
            <input type="text" id="bkCName" name="contact_name" value="<?= $e($bkName) ?>" required>
          </div>
          <div class="bk-field">
            <label for="bkCPhone">Phone</label>
            <input type="tel" id="bkCPhone" name="contact_phone" value="<?= $e($bkPhone) ?>" required>
          </div>
          <div class="bk-field bk-wide">
            <label for="bkCEmail">Email</label>
            <input type="email" id="bkCEmail" name="contact_email" value="<?= $e($bkEmail) ?>" required>
            <small>Booking updates and your payment link are sent here.</small>
          </div>
        </div>

        <h4 class="bk-h4">Travellers <span class="bk-opt" id="bkPaxCount"></span></h4>
        <div id="bkTravellers" class="bk-travellers"></div>
      </section>

      <!-- Step 3: review -->
      <section class="bk-step" data-step="2">
        <div class="bk-summary">
          <div class="bk-sum-row"><span>Travel date</span><strong id="bkSumDate">—</strong></div>
          <div class="bk-sum-row"><span>Guests</span><strong id="bkSumGuests">—</strong></div>
          <div class="bk-sum-row"><span>Subtotal</span><strong id="bkSumSub">—</strong></div>
          <div class="bk-sum-row"><span>GST (5%)</span><strong id="bkSumGst">—</strong></div>
          <div class="bk-sum-row bk-sum-total"><span>Total</span><strong id="bkSumTotal">—</strong></div>
        </div>

        <div class="bk-agent">
          <div class="bk-agent-avatar"><?= $e(mb_substr($package['agent_name'] ?? 'A', 0, 1)) ?></div>
          <div>
            <strong><?= $e($package['agent_name'] ?? 'Your travel partner') ?></strong>
            <span><?= $e($package['agent_city'] ?? '') ?></span>
            <div class="bk-agent-contact">
              <?php if (!empty($package['agent_phone'])): ?><span><i class="bi bi-telephone-fill"></i> <?= $e($package['agent_phone']) ?></span><?php endif; ?>
              <?php if (!empty($package['agent_email'])): ?><span><i class="bi bi-envelope-fill"></i> <?= $e($package['agent_email']) ?></span><?php endif; ?>
            </div>
          </div>
        </div>

        <h4 class="bk-h4">How your booking works</h4>
        <ol class="bk-process">
          <li><span>1</span><div><strong>Send your request</strong><small>Your dates and travellers go to the agent.</small></div></li>
          <li><span>2</span><div><strong>Agent reviews it</strong><small>You get an email once it is accepted or declined.</small></div></li>
          <li><span>3</span><div><strong>Pay securely</strong><small>After acceptance, pay online from My Bookings.</small></div></li>
          <li><span>4</span><div><strong>Booking confirmed</strong><small>You and the agent both get a confirmation.</small></div></li>
        </ol>

        <div class="bk-terms">
          <h4 class="bk-h4">Terms</h4>
          <ul>
            <li>Your booking is confirmed only after the agent accepts <em>and</em> you complete payment.</li>
            <li>Availability on your travel date is subject to the agent's confirmation.</li>
            <li>Prices include 5% GST. Children are charged 50% of the adult fare.</li>
            <li>Cancellation and refunds follow the agent's policy for this package.</li>
            <li>Names must match the travellers' ID documents.</li>
          </ul>
          <label class="bk-check"><input type="checkbox" id="bkTerms" name="terms" value="1"> I have read and agree to these terms.</label>
        </div>
      </section>
    </form>

    <div class="bk-done" id="bkDone" hidden>
      <div class="bk-done-ic"><i class="bi bi-check-lg"></i></div>
      <h3>Request sent</h3>
      <p>Booking <strong id="bkDoneId">#</strong> is with the agent now. You'll get an email when it's accepted, then you can pay in My Bookings.</p>
      <a class="pd-btn pd-btn--primary" href="my-bookings.php">Go to My Bookings</a>
    </div>

    <footer class="bk-foot">
      <button type="button" class="bk-btn bk-btn-ghost" data-bk-back>Back</button>
      <span class="bk-foot-total">Total <strong id="bkFootTotal">—</strong></span>
      <div class="bk-foot-actions">
        <button type="button" class="bk-btn bk-btn-primary" data-bk-next>Continue</button>
        <button type="button" class="bk-btn bk-btn-primary" data-bk-submit hidden>Send request</button>
      </div>
    </footer>
  </div>
</div>