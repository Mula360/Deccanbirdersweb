<?php
/**
 * Page dispatcher — loads the matching page-{slug}.php template (rendered
 * directly in PHP, no Elementor) for the current page's slug. Falls back
 * to plain the_content() for any page that doesn't have a dedicated
 * template (e.g. a page created later that isn't one of the 10 core ones).
 */
global $post;
$slug = $post ? $post->post_name : '';

$known_templates = ['about', 'committee', 'aims', 'activities', 'sightings', 'events', 'gallery', 'archives', 'membership', 'contact'];

if (in_array($slug, $known_templates, true) && locate_template('page-' . $slug . '.php')) {
  get_template_part('page-' . $slug);
  return;
}

get_header();
?>
<main class="site-main">
  <?php if (have_posts()) { while (have_posts()) { the_post(); the_content(); } } ?>
</main>
<?php get_footer();
