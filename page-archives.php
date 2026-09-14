<?php
/**
 * Template for the Archives page — rendered directly in PHP (no Elementor).
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

<section class="section-white" style="padding: 0 20px 60px;">
  <div class="section-boxed">
    <div class="library-columns">
      <div class="library-col">
        <h3>Society</h3>
        <ul><li><a href="#" target="_blank" rel="noopener">Memorandum, Rules and Regulations</a></li></ul>
      </div>
      <div class="library-col">
        <h3>Publications</h3>
        <ul>
          <li><a href="#" target="_blank" rel="noopener">Aasheesh Pittie and Siraj A. Taher: Mid-winter Waterbird Census in Andhra Pradesh: 1987–1996</a></li>
          <li><a href="#" target="_blank" rel="noopener">Aasheesh Pittie: Checklist of birds of Andhra Pradesh (Version 1.1)</a></li>
        </ul>
      </div>
    </div>
  </div>
</section>

<section class="section-white" style="padding: 0 20px 20px;">
  <div class="section-boxed">
    <p>PITTA is Deccan Birders' monthly bulletin published since 1980. Pick a year to see every issue. Issues from 2010–2013 are hosted on archive.org; later years on Google Drive.</p>
  </div>
</section>

<section class="section-white" style="padding: 0 20px 80px;">
  <div class="section-boxed">
    <?php echo do_shortcode('[db_pitta_accordion]'); ?>
  </div>
</section>

<?php endwhile;
get_footer();
