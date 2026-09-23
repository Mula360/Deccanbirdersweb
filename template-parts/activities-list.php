<?php
/**
 * The society's activities — the list that used to be its own page and now
 * lives inside About. page-activities.php is gone; /activities redirects to
 * the About page's #activities anchor (see functions.php).
 *
 * A dark panel: the six activities as a list on the left, the selected
 * one's photograph and points on the right. It advances on its own every
 * few seconds (assets/js/activities.js) and can be steered by clicking a
 * title. Every panel is in the HTML and the first is marked active, so
 * without JavaScript the section still reads as one complete activity.
 *
 * Content comes from the About page's own fields when set, falling back to
 * the Activities page's if that content was entered there, and finally to
 * the list below.
 */

$activities_page = get_page_by_path('activities');
$activities = get_field('activities', get_the_ID())
  ?: ($activities_page ? get_field('activities', $activities_page->ID) : null);
if (!$activities) {
  $activities = [
    ['activity_title' => 'Monthly Field Trips', 'activity_cadence' => 'Every month',
        'activity_description' => '<ul><li>Visit to all the birding hot spots around Hyderabad</li><li>Mingle with the experts</li><li>Experience the joy of live bird sightings</li></ul>'],
      ['activity_title' => 'PITTA – Monthly Newsletter', 'activity_cadence' => 'Twelve issues a year',
        'activity_description' => '<ul><li>Trip reports by members with great details and pictures</li><li>Bird of the month column</li><li>Opportunity to print your articles</li></ul>'],
      ['activity_title' => 'Annual Bird Race', 'activity_cadence' => 'Once a year',
        'activity_description' => '<ul><li>Full day birding with an assigned team</li><li>Team with the maximum sightings wins</li><li>Sumptuous dinner to celebrate the day</li></ul>'],
      ['activity_title' => 'Annual Waterfowl Census', 'activity_cadence' => 'Every winter',
        'activity_description' => '<ul><li>Census performed every Winter season</li><li>Volunteering opportunity to do the census</li><li>Data submitted to Wetlands International</li></ul>'],
      ['activity_title' => 'Webinars', 'activity_cadence' => 'Through the year',
        'activity_description' => '<ul><li>Talks by eminent ornithologists</li><li>Network with the experts</li><li>Become aware of the latest developments</li></ul>'],
      ['activity_title' => 'Annual Nature Camps and Trekking', 'activity_cadence' => 'Once a year',
        'activity_description' => '<ul><li>National and International camps</li><li>Focus on the bird watching</li><li>Exclusive access to sanctuaries wherever possible</li></ul>'],
  ];
}

/**
 * The points shown beside the photograph. Editors write the description
 * as a bulleted list, so the items are pulled out of it; anything that
 * isn't a list is kept whole as a single point.
 */
$activity_points = function($html) {
  if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', (string) $html, $m)) {
    return array_values(array_filter(array_map(
      fn($p) => trim(wp_strip_all_tags($p)),
      $m[1]
    )));
  }
  $plain = trim(wp_strip_all_tags((string) $html));
  return $plain === '' ? [] : [$plain];
};
?>

<section class="acts" id="activities">
  <div class="acts-inner">
    <div class="acts-head">
      <div>
        <span class="eyebrow acts-eyebrow">Activities</span>
        <h2 class="acts-title">An array of activities</h2>
      </div>
      <p class="acts-intro">Deccan Birders organizes field trips, lectures, film and slide shows, nature camps, treks, waterfowl counts, bird ringing, etc.</p>
    </div>

    <div class="acts-body" id="acts">
      <div class="acts-list" role="tablist" aria-label="<?php esc_attr_e('Society activities', 'deccan-birders'); ?>">
        <?php foreach ($activities as $i => $a): ?>
          <button type="button" class="acts-item<?php echo $i === 0 ? ' is-active' : ''; ?>"
                  role="tab" id="acts-tab-<?php echo $i; ?>" aria-controls="acts-panel-<?php echo $i; ?>"
                  aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                  tabindex="<?php echo $i === 0 ? '0' : '-1'; ?>" data-index="<?php echo $i; ?>">
            <span class="acts-when"><?php echo esc_html($a['activity_cadence'] ?? ''); ?></span>
            <span class="acts-name"><?php echo esc_html($a['activity_title'] ?? ''); ?></span>
          </button>
        <?php endforeach; ?>
      </div>

      <div class="acts-detail">
        <div class="acts-media">
          <?php foreach ($activities as $i => $a):
            // ACF may hand back either the image array or a bare attachment
            // ID depending on how the row was written, so normalise both.
            $img     = $a['activity_image'] ?? null;
            $img_url = is_array($img) ? ($img['url'] ?? '') : ($img ? wp_get_attachment_image_url($img, 'large') : '');
            $img_alt = is_array($img) && !empty($img['alt']) ? $img['alt'] : ($a['activity_title'] ?? '');
          ?>
            <figure class="acts-figure<?php echo $i === 0 ? ' is-active' : ''; ?>" data-index="<?php echo $i; ?>">
              <?php if ($img_url): ?>
                <img src="<?php echo esc_url($img_url); ?>" alt="<?php echo esc_attr($img_alt); ?>"
                     loading="<?php echo $i === 0 ? 'eager' : 'lazy'; ?>" decoding="async">
              <?php else: ?>
                <span class="acts-figure-empty"><?php esc_html_e('Photo coming soon', 'deccan-birders'); ?></span>
              <?php endif; ?>
            </figure>
          <?php endforeach; ?>
        </div>

        <?php foreach ($activities as $i => $a):
          $points = $activity_points($a['activity_description'] ?? '');
        ?>
          <ul class="acts-points<?php echo $i === 0 ? ' is-active' : ''; ?>" data-index="<?php echo $i; ?>"
              id="acts-panel-<?php echo $i; ?>" role="tabpanel" aria-labelledby="acts-tab-<?php echo $i; ?>"
              <?php if ($i !== 0) echo 'hidden'; ?>>
            <?php foreach ($points as $p): ?>
              <li><?php echo esc_html($p); ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
