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
      <?php
      // The real logo already contains both the bird illustration and the
      // "Deccan Birders" wordmark, so it replaces the whole lockup — showing
      // the text alongside it would duplicate the wordmark.
      // Shown about 133 px wide (55 px tall), so a 300 px copy is plenty,
      // even on sharp screens; the original upload is 1,696 px and 103 KB.
      $logo_id = get_theme_mod('custom_logo');
      if ($logo_id && wp_attachment_is_image($logo_id)):
      ?>
        <?php echo wp_get_attachment_image($logo_id, 'medium', false, [
          'class' => 'logo-img', 'alt' => 'Deccan Birders', 'loading' => false,
          'fetchpriority' => 'high', 'sizes' => '(max-width: 640px) 102px, 133px',
        ]); ?>
      <?php else: ?>
        <span class="logo-mark" aria-hidden="true">DB</span>
        <span class="logo-name">Deccan Birders</span>
      <?php endif; ?>
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
