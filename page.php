<?php get_header(); ?>
<main class="site-main">
  <?php
  while ( have_posts() ) {
    the_post();
    // Let Elementor handle rendering if active on this page
    if ( did_action( 'elementor/loaded' ) && \Elementor\Plugin::$instance->db->is_built_with_elementor( get_the_ID() ) ) {
      echo \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( get_the_ID() );
    } else {
      the_content();
    }
  }
  ?>
</main>
<?php get_footer(); ?>
