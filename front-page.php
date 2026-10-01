<?php
/**
 * Front page dispatcher — loads page-home.php, which handles its own
 * get_header()/get_footer().
 */
if (!defined('ABSPATH')) exit;
get_template_part('page-home');
