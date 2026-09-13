<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<header class="site-header" role="banner">
  <div class="header-inner">
    <a href="<?php echo esc_url(home_url('/')); ?>" class="site-logo" aria-label="Deccan Birders home">
      <span class="logo-mark" aria-hidden="true">DB</span>
      <span class="logo-name">Deccan Birders</span>
    </a>
    <nav class="primary-nav" role="navigation" aria-label="Primary">
      <?php wp_nav_menu(['theme_location'=>'primary-nav','container'=>false,'menu_class'=>'nav-list','fallback_cb'=>false]); ?>
    </nav>
    <a href="<?php echo esc_url(get_permalink(get_page_by_path('membership'))); ?>" class="btn btn-primary nav-cta">Join us</a>
    <button class="hamburger" aria-label="Open navigation menu" aria-expanded="false" aria-controls="mobile-nav">
      <span></span><span></span><span></span>
    </button>
  </div>
  <div class="mobile-nav" id="mobile-nav" aria-hidden="true" role="dialog" aria-label="Navigation menu">
    <?php wp_nav_menu(['theme_location'=>'primary-nav','container'=>false,'menu_class'=>'mobile-nav-list','fallback_cb'=>false]); ?>
    <a href="<?php echo esc_url(get_permalink(get_page_by_path('membership'))); ?>" class="btn btn-primary">Join us</a>
  </div>
  <div class="nav-overlay" aria-hidden="true"></div>
</header>
