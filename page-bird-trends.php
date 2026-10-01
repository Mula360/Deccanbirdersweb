<?php
/**
 * Birding Tools → Bird Trends: which species birders report less, or
 * more, in Telangana and Andhra Pradesh.
 *
 * The figures are inc/birding-tools/engine.php's, recalculated every 3
 * days and printed here as JSON for assets/js/bird-trends.js to draw;
 * the page is otherwise the design's, inside the site's own header,
 * join band and footer.
 */
if (!defined('ABSPATH')) exit;
get_header();
$data = db_bt_read('trends.json');
$states = $data['states'] ?? [];
$window = function($k) use ($states) {
  return empty($states[$k]['years']) ? '' : $states[$k]['years'][0] . '–' . end($states[$k]['years']);
};
?>
<div class="bt bt--trends">
  <section class="bt-hero bt-dark">
    <div class="bt-wrap bt-hero-grid">
      <div>
        <div class="bt-eyebrow">eBird reporting trends</div>
        <h1 class="bt-h1" style="text-wrap:balance;max-width:14ch">Which birds are we seeing less of?</h1>
        <p class="bt-lead">How often birders report each species in Telangana and Andhra Pradesh. This tracks reporting, not how many birds there are.</p>
      </div>
      <?php if ($data): ?>
        <div class="bt-tools">
          <div class="bt-pills" id="tabs" role="group" aria-label="Choose a state"></div>
          <div class="bt-search">
            <input id="q" type="search" placeholder="Search any species" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="sugg" aria-autocomplete="list" aria-label="Search any species">
            <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true"><circle cx="7.5" cy="7.5" r="5.5" fill="none" stroke="currentColor" stroke-width="2"></circle><path d="M12 12l4.5 4.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path></svg>
            <ul class="bt-sugg" id="sugg" role="listbox" aria-label="Matching species"></ul>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <?php if (!$data): ?>
    <p class="bt-pending">The figures are being worked out from the latest eBird records. Please check back in an hour.</p>
  <?php else: ?>
    <div class="bt-wrap bt-lift">
      <div class="bt-lift-card bt-detail" id="detail"></div>
    </div>

    <section class="bt-wrap" style="padding-top:72px;padding-bottom:24px">
      <div class="bt-eyebrow">Ranked by yearly change</div>
      <h2 class="bt-h2" id="decTitle">Declining</h2>
      <div class="bt-g3" id="dec"></div>
      <div class="bt-more" id="decMore" hidden><button type="button"></button></div>
      <h3 class="bt-sora" style="font-weight:700;font-size:24px;letter-spacing:-.02em;color:var(--ink);margin:48px 0 16px">Increasing</h3>
      <div class="bt-g4 bt-incs" id="inc"></div>
      <div class="bt-more" id="incMore" hidden><button type="button"></button></div>
    </section>

    <section class="bt-wrap" style="padding-top:48px">
      <div class="bt-g3 bt-how">
        <div><div class="bt-n">01</div><p>“12 per 1,000” means about 12 of every 1,000 eBird records that year were this species, measured against the typical species in the state.</p></div>
        <div><div class="bt-n">02</div><p>A falling line means birders report the species less often. It doesn't count birds.</p></div>
        <div><div class="bt-n">03</div><p>Districts keep fixed weights, so busier birding in one place doesn't skew results. <a href="#method" id="methodLink" class="bt-sora" style="font-weight:600;font-size:15.5px;border-bottom:2px solid var(--yellow)">Read the method</a></p></div>
      </div>
      <details class="bt-card bt-method" id="method" style="padding:20px 24px;margin-bottom:40px">
        <summary>Method<span aria-hidden="true">+</span></summary>
        <p>Data window: <?php
          $parts = [];
          foreach (['TS' => 'Telangana', 'AP' => 'Andhra Pradesh'] as $k => $name) if ($window($k)) $parts[] = $name . ' ' . $window($k);
          echo esc_html(implode(', ', $parts));
        ?>. Every month is included, bird-count weekends such as the Great Backyard Bird Count too.</p>
        <p>Districts with at least 500 records every year each get a fixed weight. The index is each species' share of records, weighted by district. The trend is a Theil–Sen slope with a 90% range from 300 bootstrap resamples of the districts.</p>
        <p>As birders log more species per outing, every species' share of the records drifts a little each year, with no change in the birds<?php
          $drifts = [];
          foreach (['TS' => 'Telangana', 'AP' => 'Andhra Pradesh'] as $k => $name) {
            if (isset($states[$k]['drift'])) $drifts[] = $name . ' ' . ($states[$k]['drift'] < 0 ? '−' : '+') . abs($states[$k]['drift']) . '% a year';
          }
          echo $drifts ? ' (' . esc_html(implode(', ', $drifts)) . ' for the typical species)' : '';
        ?>. Each species is measured against that typical species, so a decline here means falling faster than the rest.</p>
        <p>Clear decline: −5% a year or worse, the whole range below −2%, and at least 25% down from the first three years to the last three. Possible decline: −3% a year or worse and at least 15% down. Increases mirror these. A species needs records in every year, at least 300 in all, about 20 a year, and from at least two districts for a verdict.</p>
      </details>
    </section>
    <?php db_bt_credit($data['generated'] ?? ''); ?>
    <script type="application/json" id="bt-data"><?php echo wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); ?></script>
  <?php endif; ?>
</div>

<?php get_template_part('template-parts/join-band'); ?>
<?php get_footer();
