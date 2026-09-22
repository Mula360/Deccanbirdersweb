<?php
/**
 * The society's activities — the list that used to be its own page and now
 * lives inside About. page-activities.php is gone; /activities redirects to
 * the About page's #activities anchor (see functions.php).
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
?>

<section class="activities-head" id="activities">
  <span class="eyebrow" style="color:var(--blue);">Activities</span>
  <h2 class="activities-title">An array of activities</h2>
  <p class="activities-intro">Deccan Birders organizes field trips, lectures, film and slide shows, nature camps, treks, waterfowl counts, bird ringing, etc.</p>
</section>
<div class="activities-list">
  <?php foreach ($activities as $i => $a):
    // ACF may hand back either the image array or a bare attachment ID
    // depending on how the row was written, so normalise both.
    $img     = $a['activity_image'] ?? null;
    $img_url = is_array($img) ? ($img['url'] ?? '') : ($img ? wp_get_attachment_image_url($img, 'large') : '');
    $img_alt = is_array($img) && !empty($img['alt']) ? $img['alt'] : $a['activity_title'];
    $flip    = ($i % 2 === 1); // alternate which side the photo sits on
  ?>
    <div class="activity-card<?php echo $flip ? ' activity-card--flip' : ''; ?>">
      <div class="activity-text">
        <span class="activity-cadence"><?php echo esc_html($a['activity_cadence']); ?></span>
        <h2 class="activity-title"><?php echo esc_html($a['activity_title']); ?></h2>
        <div class="activity-points"><?php echo wp_kses_post($a['activity_description']); ?></div>
      </div>
      <?php if ($img_url): ?>
        <img class="activity-photo" src="<?php echo esc_url($img_url); ?>" alt="<?php echo esc_attr($img_alt); ?>" loading="lazy">
      <?php else: ?>
        <div class="activity-photo img-placeholder" aria-label="Photo coming soon"><span>Photo coming soon</span></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

