<?php
/**
 * Birding Tools → Backpack: what to pack for a morning in the field.
 *
 * Static content, from the "Pack your bag" design, inside the site's own
 * header and footer. The kit is grouped into categories; every category
 * is in the page as written, and assets/js/backpack.js turns them into
 * tabs (with no script, they simply read one after another).
 */
if (!defined('ABSPATH')) exit;
get_header();
$img = get_template_directory_uri() . '/assets/img/backpack/';

// [id, name, sketch, tint, intro, [[item, note, essential], …]]
$cats = [
  ['optics', 'Optics', 'binoculars', '#FFF4D6', 'Without these, you are just a person standing very still in a field.', [
    ['Binoculars', '8x42 or 10x42. Use a harness. Your neck has done nothing to deserve this.', true],
    ['Lens cloth', 'By nine, the Deccan has dusted your lenses a fetching shade of laterite.'],
    ['Spotting scope & tripod', 'For waders on the far shore. Also makes you look like you know things.'],
    ['Camera, spare battery & card', 'The Indian Courser will appear the second your battery dies. Carry a spare.'],
  ]],
  ['notes', 'Notes & ID', 'notebook', '#E6F4EC', 'Because "small brown bird" is not an accepted species on eBird.', [
    ['Field guide', 'Birds of the Indian Subcontinent. Heavier than it looks, settles every argument.'],
    ['Phone with eBird & Merlin', 'Download the India pack offline. The birds are excellent out here. The network is not.', true],
    ['Notebook & pencil', 'Pens leak in the heat and quit in the rain. Pencils just keep going.'],
    ['Power bank & cable', 'Your phone is a field guide, camera, map and torch. It will be tired.'],
  ]],
  ['clothing', 'Clothing', 'shirt', '#E6F4EC', 'Dress like a bush. A slightly stylish bush.', [
    ['Full-sleeve shirt in earthy tones', 'Olive, khaki or brown. That neon running tee is visible from the next district.', true],
    ['Long trousers', 'The grassland will try to come home with you as seeds, thorns and ticks.'],
    ['Closed shoes with grip', 'Loose laterite and rocky outcrops. Leave the chappals for the chai stall.', true],
    ['Light jacket', 'Winter dawns hit 12°C. By ten you will be carrying it. Bring it anyway.'],
  ]],
  ['sun', 'Sun & weather', 'hat', '#FFF4D6', 'By ten, the plateau turns into a frying pan with good birds on it.', [
    ['Wide-brim hat', 'A cap protects your face. A wide brim protects your face, neck and dignity.', true],
    ['Sunscreen & lip balm', 'Once before you leave, once mid-morning. Tan lines are not a field mark.'],
    ['Sunglasses', 'For the drive and the walk back. Not while scanning, unless you enjoy dark birds.'],
    ['Rain cover for bag & optics', 'June to September. A poncho covers you and the binoculars. Mostly the binoculars.'],
  ]],
  ['food', 'Food & water', 'bottle', '#EAF2FA', 'The best spots are, by design, nowhere near a chai stall.', [
    ['Water, at least 2 litres', 'More in summer. If you are thinking "I will buy some there", there is no there.', true],
    ['ORS sachets', 'Tastes like mild regret, works like a charm on hot mornings.'],
    ['Snacks', 'Nuts, fruit, chikki. Not the crinkly packet that clears the whole marsh.'],
    ['Small bag for trash', 'Take out everything you bring in. The only thing to leave behind is a checklist.'],
  ]],
  ['safety', 'Safety', 'torch', '#FFF4D6', 'Early starts, late finishes, and the occasional argument with a thorn bush.', [
    ['Head torch', 'For 5 am starts, and for nightjars that refuse to show before dark.'],
    ['Basic first-aid kit', 'Plasters, antiseptic, antihistamine. Babul thorns are very committed.'],
    ['Insect & tick repellent', 'Spray shoes and cuffs. Ticks are also enthusiastic about grasslands.'],
    ['ID & permit copies', 'Some forest gates need a permit. The guard does not care about your lifer.', true],
  ]],
];
$flat = [['binoculars', 'Binoculars'], ['camera', 'Camera'], ['notebook', 'Notebook'], ['hat', 'Hat'], ['shirt', 'Full sleeves'], ['boot', 'Shoes'], ['bottle', 'Water'], ['torch', 'Head torch']];
$seasons = [
  ['Mar – May', 'Summer', 'Out at first light, home by ten. After that, only mad dogs, Englishmen and Red-wattled Lapwings stay out.'],
  ['Jun – Sep', 'Monsoon', 'Cover everything, wear quick-dry, accept the mud. Leeches near streams consider you lunch.'],
  ['Oct – Feb', 'Winter', 'Migrants arrive, dawns are cold, and everyone is suddenly very keen. Bring a layer.'],
];
$leave = [
  ['Call playback speakers', 'The bird thinks a rival has moved in. It is stressful for them. We do not use it on walks.'],
  ['Flash photography', 'Owls do not enjoy paparazzi either, especially at roosts and nests.'],
  ['Bright or white clothing', 'You will be the most visible thing on the plateau. The birds will notice first.'],
  ['Perfume and strong scents', 'The bees will love it. You will not love the bees.'],
];
$total = str_pad((string) count($cats), 2, '0', STR_PAD_LEFT);
$membership = ($m = get_page_by_path('membership')) ? get_permalink($m) : '/membership/';
?>
<div class="bt bt--pack">
  <section class="bt-pack-hero">
    <div class="bt-pack-hero-inner">
      <div>
        <div class="bt-pack-eyebrow">Backpack · Field kit</div>
        <h1>Pack your bag</h1>
        <p>The Deccan is hot, dry and wide open for most of the year, then soaked for four months. Here is what our members carry so the only thing that goes wrong is the bird flying off.</p>
      </div>
      <div class="bt-pack-flat" aria-hidden="true">
        <?php foreach ($flat as [$key, $label]): ?>
          <figure><img src="<?php echo esc_url($img . $key . '.svg'); ?>" alt="" width="80" height="80"><figcaption><?php echo esc_html($label); ?></figcaption></figure>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="bt-pack-kit">
    <div class="bt-pack-tabs" role="tablist" aria-label="What to pack" hidden>
      <?php foreach ($cats as $i => $c): ?>
        <button type="button" role="tab" id="tab-<?php echo esc_attr($c[0]); ?>" aria-controls="cat-<?php echo esc_attr($c[0]); ?>" aria-selected="<?php echo $i ? 'false' : 'true'; ?>" tabindex="<?php echo $i ? '-1' : '0'; ?>"><?php echo esc_html($c[1]); ?></button>
      <?php endforeach; ?>
    </div>

    <?php foreach ($cats as $i => [$id, $name, $sketch, $tint, $intro, $items]): ?>
      <div class="bt-pack-cat" id="cat-<?php echo esc_attr($id); ?>" role="tabpanel" aria-labelledby="tab-<?php echo esc_attr($id); ?>">
        <div class="bt-pack-cat-side" style="background:<?php echo esc_attr($tint); ?>">
          <div class="bt-pack-cat-head">
            <span class="bt-pack-count"><?php echo esc_html(str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) . ' / ' . $total); ?></span>
            <h2><?php echo esc_html($name); ?></h2>
            <p><?php echo esc_html($intro); ?></p>
          </div>
          <img src="<?php echo esc_url($img . $sketch . '.svg'); ?>" alt="" width="180" height="180">
          <div class="bt-pack-arrows" hidden>
            <button type="button" data-step="-1" aria-label="Previous category">←</button>
            <button type="button" data-step="1" aria-label="Next category">→</button>
          </div>
        </div>
        <ol class="bt-pack-items">
          <?php foreach ($items as $j => $it): ?>
            <li>
              <span class="bt-pack-num"><?php echo esc_html(str_pad((string) ($j + 1), 2, '0', STR_PAD_LEFT)); ?></span>
              <div>
                <div class="bt-pack-item-head">
                  <h3><?php echo esc_html($it[0]); ?></h3>
                  <?php if (!empty($it[2])): ?><span class="bt-pack-essential">Essential</span><?php endif; ?>
                </div>
                <p><?php echo esc_html($it[1]); ?></p>
              </div>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="bt-pack-seasons">
    <?php foreach ($seasons as [$months, $name, $note]): ?>
      <div>
        <span class="bt-pack-season"><?php echo esc_html($name); ?></span>
        <span class="bt-pack-months"><?php echo esc_html($months); ?></span>
        <p><?php echo esc_html($note); ?></p>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="bt-pack-leave-wrap">
    <div class="bt-pack-leave">
      <div class="bt-pack-leave-head"><img src="<?php echo esc_url($img . 'leave-at-home.svg'); ?>" alt="" width="80" height="80"><h2>Leave at home, please</h2></div>
      <div class="bt-pack-leave-list">
        <?php foreach ($leave as [$name, $note]): ?>
          <div><div class="bt-pack-leave-name"><?php echo esc_html($name); ?></div><div><?php echo esc_html($note); ?></div></div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</div>

<section class="join-band">
  <div class="join-band-inner">
    <div>
      <h2 class="join-band-title">Join our members</h2>
      <p class="join-band-text">Bag packed? Good. Now you need people to show it to.</p>
    </div>
    <a href="<?php echo esc_url($membership); ?>" class="join-band-btn">Apply for Membership</a>
  </div>
</section>
<?php get_footer();
