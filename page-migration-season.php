<?php
/**
 * Birding Tools → Migration Season: the winter visitors to expect each
 * month, where to see them, and when each group peaks.
 *
 * All from inc/birding-tools/engine.php (recalculated every 3 days),
 * printed here as JSON for assets/js/migration-season.js to draw. The
 * page is the design's, inside the site's own header, join band and
 * footer. Birding Tools in the menu opens this page.
 */
get_header();
$data = db_bt_read('migration.json');
?>
<div class="bt bt--migration">
  <section class="bt-hero bt-dark">
    <div class="bt-wrap">
      <div class="bt-hero-grid">
        <div>
          <div class="bt-eyebrow">Winter <?php echo esc_html($data['season'] ?? ''); ?></div>
          <h1 class="bt-h1">Get ready for<br>migration season</h1>
          <p class="bt-lead">Arrival dates, what past seasons tell us to expect, and where to be.</p>
        </div>
        <?php if ($data): ?><div class="bt-waves" id="waves" aria-label="Countdown to peak arrival"></div><?php endif; ?>
      </div>
      <?php if ($data): ?><div class="bt-pills bt-month-pills" id="months" role="group" aria-label="Choose a month"></div><?php endif; ?>
    </div>
  </section>

  <?php if (!$data): ?>
    <p class="bt-pending">The figures are being worked out from the latest eBird records. Please check back in an hour.</p>
  <?php else: ?>
    <div class="bt-wrap bt-lift">
      <div class="bt-lift-card">
        <div class="bt-exp-head">
          <h2 id="expTitle">Expected</h2>
          <div class="bt-pills bt-hab-pills" id="habs" role="group" aria-label="Filter by habitat"></div>
        </div>
        <div class="bt-g4" id="expected"></div>
        <div class="bt-more" id="expMore" hidden><button type="button"></button></div>
      </div>
    </div>

    <section class="bt-wrap" style="padding-top:72px;padding-bottom:24px">
      <div class="bt-eyebrow">Where to go</div>
      <h2 class="bt-h2">Migration hotspots</h2>
      <div class="bt-map" id="map"></div>
      <div class="bt-g3" id="spots" style="margin-top:16px"></div>
    </section>

    <section class="bt-wrap" style="padding-top:48px;padding-bottom:40px">
      <div class="bt-eyebrow">When they're here</div>
      <h2 class="bt-h2">Arrival calendar</h2>
      <div class="bt-card bt-tl" id="timeline"></div>
      <div class="bt-more" id="tlMore" hidden><button type="button"></button></div>
    </section>
    <?php db_bt_credit($data['generated'] ?? '', 'Typical months from ' . ($data['years'] ?? '') . '. Boundaries: IndiaStateTopojsonFiles.'); ?>
    <script type="application/json" id="bt-data"><?php echo wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); ?></script>
  <?php endif; ?>
</div>

<?php get_template_part('template-parts/join-band'); ?>
<?php get_footer();
