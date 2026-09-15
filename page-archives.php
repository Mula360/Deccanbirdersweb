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

<section class="section-white" style="padding: 0 20px 40px;">
  <div class="section-boxed">
    <?php db_hero_image('archives_hero_image', get_the_ID(), 'Indian Roller photographed by a Deccan Birders member'); ?>
  </div>
</section>

<section class="section-white" style="padding: 0 20px 60px;">
  <div class="section-boxed">
    <div class="library-columns">
      <div class="library-col">
        <h3>Society</h3>
        <ul><li><a href="https://deccanbirders.org/wp-content/uploads/2024/01/Byelaws2018.pdf" target="_blank" rel="noopener">Memorandum, Rules and Regulations</a></li></ul>
      </div>
      <div class="library-col">
        <h3>Publications</h3>
        <ul>
          <li>Aasheesh Pittie and Siraj A. Taher: <a href="https://drive.google.com/open?id=0B-u8pdUedG35RWtMOFB0WjNyblk" target="_blank" rel="noopener">Mid-winter Waterbird Census in Andhra Pradesh: 1987–1996</a></li>
          <li>Aasheesh Pittie: <a href="https://drive.google.com/open?id=0B-u8pdUedG35UjFUMXlKaDFtYTQ" target="_blank" rel="noopener">Checklist of birds of Andhra Pradesh (Version 1.1)</a></li>
        </ul>
      </div>
    </div>
  </div>
</section>

<section class="section-white" style="padding: 0 20px 80px;">
  <div class="section-boxed">
    <h2 style="margin-bottom:12px;">PITTA Archives</h2>
    <p style="color:var(--text-muted);max-width:50ch;margin-bottom:26px;">Pick a year to see every issue. Issues from 2010–2013 are hosted on archive.org; later years on Google Drive.</p>
    <?php echo do_shortcode('[db_pitta_accordion]'); ?>
  </div>
</section>

<?php endwhile;
get_footer();
