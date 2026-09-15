<?php
/**
 * Template for the Archives page — rendered directly in PHP (no Elementor).
 *
 * Container rhythm follows the design: heading, banner image at 32px top,
 * the Society/Publications card pair, then the PITTA accordion carrying
 * the section's bottom spacing.
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

<div class="library-columns">
  <div class="library-col">
    <h2 class="library-col-title">Society</h2>
    <a class="library-doc" href="https://deccanbirders.org/wp-content/uploads/2024/01/Byelaws2018.pdf" target="_blank" rel="noopener">Memorandum Rules and Regulations</a>
  </div>
  <div class="library-col">
    <h2 class="library-col-title">Publications</h2>
    <div class="library-pubs">
      <div>Aasheesh Pittie and Siraj A. Taher: <a href="https://drive.google.com/open?id=0B-u8pdUedG35RWtMOFB0WjNyblk" target="_blank" rel="noopener">Mid-winter Waterbird Census in Andhra Pradesh: 1987–1996</a></div>
      <div>Aasheesh Pittie: <a href="https://drive.google.com/open?id=0B-u8pdUedG35UjFUMXlKaDFtYTQ" target="_blank" rel="noopener">Checklist of birds of Andhra Pradesh (Version 1.1)</a></div>
    </div>
  </div>
</div>

<section class="pitta-section">
  <h2 class="pitta-section-title">PITTA Archives</h2>
  <p class="pitta-section-intro">Pick a year to see every issue. Issues from 2010–2013 are hosted on archive.org; later years on Google Drive.</p>
  <?php echo do_shortcode('[db_pitta_accordion]'); ?>
</section>

<?php get_template_part('template-parts/join-band'); ?>

<?php endwhile;
get_footer();
