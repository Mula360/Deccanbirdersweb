<?php
/**
 * Template for the Activities page — rendered directly in PHP (no Elementor).
 * Content pulled from the "Activities Page" ACF field group (repeater
 * default_value) where available, falls back to the same 6 activities.
 */
get_header();
while (have_posts()) : the_post();
  $id = get_the_ID();
  $activities = get_field('activities', $id);
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

<section class="hero-light">
  <div class="hero-light-inner">
    <span class="eyebrow" style="color:var(--blue);">Activities</span>
    <h1>An array of activities</h1>
  </div>
</section>

<section class="section-white" style="padding: 0 20px 40px;">
  <div class="section-boxed">
    <p>Deccan Birders organizes field trips, lectures, film and slide shows, nature camps, treks, waterfowl counts, bird ringing, etc.</p>
  </div>
</section>

<div class="section-boxed">
  <?php foreach ($activities as $a): ?>
    <div class="activity-row">
      <div class="activity-media">
        <?php if (!empty($a['activity_image']['url'])): ?>
          <img src="<?php echo esc_url($a['activity_image']['url']); ?>"
               alt="<?php echo esc_attr($a['activity_image']['alt'] ?: $a['activity_title'] . ' — Deccan Birders'); ?>">
        <?php else: ?>
          <div class="img-placeholder" aria-label="Photo coming soon">
            <span>Photo coming soon</span>
          </div>
        <?php endif; ?>
      </div>
      <div class="activity-text">
        <p style="display:inline-block;background:var(--green-light);color:var(--green-dark);padding:5px 14px;border-radius:999px;font-family:var(--font-head);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.05em;margin:0 0 12px;">
          <?php echo esc_html($a['activity_cadence']); ?>
        </p>
        <div class="activity-title"><?php echo esc_html($a['activity_title']); ?></div>
        <div class="activity-desc"><?php echo wp_kses_post($a['activity_description']); ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php get_template_part('template-parts/join-band'); ?>

<?php endwhile;
get_footer();
