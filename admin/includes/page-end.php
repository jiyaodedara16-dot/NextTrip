    </main>
  </div>
</div>

<!-- Shared modal: fixed to the viewport, so it is always centred on the visible screen -->
<div class="modal-backdrop" id="appModal" aria-hidden="true">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-head">
      <h3 id="modalTitle"></h3>
      <button class="icon-btn" type="button" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body" id="modalBody" data-lenis-prevent></div>
    <div class="modal-foot" id="modalFoot"></div>
  </div>
</div>
<div class="toast-stack" id="toastStack" aria-live="polite"></div>

<script src="https://cdn.jsdelivr.net/npm/lenis@1.1.18/dist/lenis.min.js"></script>
<script src="assets/js/smooth-scroll.js"></script>
<script src="assets/js/admin.js"></script>
<?php if (!empty($extra_js)): foreach ($extra_js as $js): ?>
<script src="<?= h($js) ?>"></script>
<?php endforeach; endif; ?>
</body>
</html>
