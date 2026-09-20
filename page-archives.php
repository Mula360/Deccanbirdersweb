<?php
/**
 * Template for the Archives page — rendered directly in PHP (no Elementor).
 *
 * Container rhythm follows the design: heading, banner image at 32px top,
 * the Society/Publications card pair, then the PITTA archive (covers by
 * year, see template-parts/shortcode-pitta-accordion.php) carrying the
 * section's bottom spacing.
 */
get_header();
while (have_posts()) : the_post();
?>

<section class="hero-light">
  <div class="hero-light-inner">
    <span class="eyebrow" style="color:var(--blue);">Library</span>
    <h1>Archives</h1>
  </div>
</section>

<div class="page-banner">
  <?php db_hero_image('archives_hero_image', get_the_ID(), 'Indian Roller photographed by a Deccan Birders member', 'clamp(190px, 30vw, 340px)'); ?>
</div>

<div class="library-wrap">
  <div class="docs">
    <section class="card">
      <h3>Society</h3>
      <a href="https://deccanbirders.org/wp-content/uploads/2024/01/Byelaws2018.pdf" target="_blank" rel="noopener">
        <span>Memorandum, Rules and Regulations</span><span aria-hidden="true">↗</span>
      </a>
    </section>
    <section class="card">
      <h3>Publications</h3>
      <a href="https://drive.google.com/open?id=0B-u8pdUedG35RWtMOFB0WjNyblk" target="_blank" rel="noopener">
        <span><span class="by">Aasheesh Pittie &amp; Siraj A. Taher · </span>Mid-winter Waterbird Census in Andhra Pradesh: 1987–1996</span><span aria-hidden="true">↗</span>
      </a>
      <a href="https://drive.google.com/open?id=0B-u8pdUedG35UjFUMXlKaDFtYTQ" target="_blank" rel="noopener">
        <span><span class="by">Aasheesh Pittie · </span>Checklist of birds of Andhra Pradesh (v1.1)</span><span aria-hidden="true">↗</span>
      </a>
    </section>
  </div>
</div>

<section class="pitta-section">
  <?php echo do_shortcode('[db_pitta_accordion]'); ?>
</section>

<?php get_template_part('template-parts/join-band'); ?>

<?php endwhile;
get_footer();
