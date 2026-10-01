<?php
/**
 * What happens to a submitted photograph before it is stored.
 *
 * The upload is rotated upright, resized to the configured longest edge,
 * recompressed (JPEG, or WebP for a WebP upload) and stripped of every
 * piece of metadata — EXIF, GPS, XMP, IPTC — except the colour profile,
 * which is put back so colours look as the photographer saw them. The
 * result is saved under a random name. The original upload is never
 * stored, so its location data never reaches the site, even while the
 * photo is waiting for review.
 *
 * Imagick does the work (it can keep the colour profile while removing
 * the rest). If a host only has GD, WordPress's own editor is used, which
 * also strips everything but cannot keep the profile.
 */

if (!defined('ABSPATH')) exit;

/**
 * Record a step of a run being traced (the Photo processing screen traces
 * each photo it reprocesses). Saved as it goes, so if a request dies the
 * screen can show the last step it reached.
 */
function db_photo_step($label) {
  global $db_photo_trace;
  if (!is_array($db_photo_trace)) return;
  $db_photo_trace['steps'][] = [$label, round((microtime(true) - $db_photo_trace['t0']) * 1000)];
  update_option('db_photo_last_run', $db_photo_trace, false);
}

/**
 * Process one image file into $dest_dir. Returns ['path', 'name', 'mime',
 * 'report'] or a WP_Error. The report records the before and after.
 */
function db_photo_process($src, $dest_dir) {
  $max_edge = db_gallery_setting('max_edge_px');
  $quality  = db_gallery_setting('image_quality');
  $name     = 'db-photo-' . bin2hex(random_bytes(8));

  // One thread: multi-threaded ImageMagick is a known cause of hung
  // requests under PHP on shared hosting.
  if (extension_loaded('imagick')) {
    Imagick::setResourceLimit(Imagick::RESOURCETYPE_THREAD, 1);
    // And a ceiling on what one image may use, whatever it claims to be.
    Imagick::setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024);
    Imagick::setResourceLimit(Imagick::RESOURCETYPE_MAP, 512 * 1024 * 1024);
    if (defined('Imagick::RESOURCETYPE_AREA')) Imagick::setResourceLimit(Imagick::RESOURCETYPE_AREA, 60000000);
  }

  try {
    $result = extension_loaded('imagick')
      ? db_photo_process_imagick($src, $dest_dir, $name, $max_edge, $quality)
      : db_photo_process_gd($src, $dest_dir, $name, $max_edge, $quality);
  } catch (Throwable $e) {
    return new WP_Error('image', 'That photograph could not be processed: ' . $e->getMessage());
  }
  if (is_wp_error($result)) return $result;
  db_photo_step('image processed');

  // Belt and braces: never keep a file that still carries a location.
  if (db_photo_has_location($result['path'])) {
    @unlink($result['path']);
    return new WP_Error('image', 'The location data could not be removed from that photograph.');
  }
  return $result;
}

/** Does this file still carry GPS data, in EXIF or XMP? */
function db_photo_has_location($path) {
  if (function_exists('exif_read_data') && @exif_imagetype($path) === IMAGETYPE_JPEG) {
    $exif = @exif_read_data($path, 'GPS', true);
    if (!empty($exif['GPS'])) return true;
  }
  if (extension_loaded('imagick')) {
    $im = new Imagick($path);
    $gps = $im->getImageProperties('exif:GPS*');
    $xmp = $im->getImageProfiles('xmp', true);
    $im->clear();
    if ($gps) return true;
    if (!empty($xmp['xmp']) && preg_match('/GPS(Latitude|Longitude)/i', $xmp['xmp'])) return true;
  }
  return false;
}

/** Rotate an image to match its EXIF orientation, then mark it upright. */
function db_photo_orient(Imagick $im) {
  switch ($im->getImageOrientation()) {
    case Imagick::ORIENTATION_TOPRIGHT:    $im->flopImage(); break;
    case Imagick::ORIENTATION_BOTTOMRIGHT: $im->rotateImage('#000', 180); break;
    case Imagick::ORIENTATION_BOTTOMLEFT:  $im->flipImage(); break;
    case Imagick::ORIENTATION_LEFTTOP:     $im->transposeImage(); break;
    case Imagick::ORIENTATION_RIGHTTOP:    $im->rotateImage('#000', 90); break;
    case Imagick::ORIENTATION_RIGHTBOTTOM: $im->transverseImage(); break;
    case Imagick::ORIENTATION_LEFTBOTTOM:  $im->rotateImage('#000', -90); break;
  }
  $im->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
}

function db_photo_process_imagick($src, $dest_dir, $name, $max_edge, $quality) {
  $im = new Imagick($src);
  db_photo_step('original read');
  if ($im->getNumberImages() > 1) { // an animated WebP/PNG: keep the first frame
    $im->setIteratorIndex(0);
    $im = $im->getImage();
  }
  $in_format = strtoupper($im->getImageFormat());
  $profiles  = $im->getImageProfiles('*', true);
  $before = [
    'bytes'  => filesize($src),
    'width'  => $im->getImageWidth(),
    'height' => $im->getImageHeight(),
    'format' => $in_format,
  ];
  $had_gps  = (bool) $im->getImageProperties('exif:GPS*')
    || (!empty($profiles['xmp']) && preg_match('/GPS(Latitude|Longitude)/i', $profiles['xmp']));
  $removed  = array_values(array_diff(array_keys($profiles), ['icc']));

  // A CMYK file's profile describes CMYK, so it cannot follow the pixels
  // to sRGB; everything else keeps its own profile.
  $icc = $profiles['icc'] ?? null;
  if ($im->getImageColorspace() === Imagick::COLORSPACE_CMYK) {
    $im->transformImageColorspace(Imagick::COLORSPACE_SRGB);
    $icc = null;
  }

  db_photo_orient($im);

  $w = $im->getImageWidth();
  $h = $im->getImageHeight();
  if (max($w, $h) > $max_edge) {
    $scale = $max_edge / max($w, $h);
    $im->resizeImage(max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)), Imagick::FILTER_LANCZOS, 1);
  }

  $out = $in_format === 'WEBP' ? 'webp' : 'jpeg';
  if ($out === 'jpeg' && $im->getImageAlphaChannel()) {
    // JPEG has no transparency: lay the photo on white rather than black.
    $im->setImageBackgroundColor('white');
    $im = $im->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
  }

  // Never recompress a JPEG at a higher quality than it already had: it
  // would only make the file bigger without looking any better.
  if ($in_format === 'JPEG') {
    $source_q = (int) $im->getImageCompressionQuality();
    if ($source_q > 0 && $source_q < $quality) $quality = $source_q;
  }

  $im->stripImage();
  if ($icc) $im->profileImage('icc', $icc);
  $im->setImageFormat($out);
  $im->setImageCompressionQuality($quality);
  if ($out === 'jpeg') {
    $im->setImageCompression(Imagick::COMPRESSION_JPEG);
    $im->setInterlaceScheme(Imagick::INTERLACE_PLANE); // progressive
  } else {
    $im->setOption('webp:method', '6');
  }

  $ext  = $out === 'webp' ? 'webp' : 'jpg';
  $path = trailingslashit($dest_dir) . $name . '.' . $ext;
  $im->writeImage($path);
  db_photo_step('processed copy written');
  $after = ['bytes' => filesize($path), 'width' => $im->getImageWidth(), 'height' => $im->getImageHeight(), 'format' => strtoupper($out)];
  $im->clear();

  // Report what the saved file holds, not what was asked for: some
  // ImageMagick builds drop the profile on writing.
  $saved = new Imagick($path);
  $icc_saved = array_key_exists('icc', $saved->getImageProfiles('icc', true));
  $saved->clear();
  $profile = $icc ? ($icc_saved ? 'kept' : 'lost when saving') : (isset($profiles['icc']) ? 'converted to sRGB' : 'none in the original');

  return [
    'path'   => $path,
    'name'   => $name . '.' . $ext,
    'mime'   => $out === 'webp' ? 'image/webp' : 'image/jpeg',
    'report' => [
      'editor'           => 'imagick',
      'before'           => $before,
      'after'            => $after,
      'quality'          => $quality,
      'colour_profile'   => $profile,
      'had_location'     => $had_gps,
      'metadata_removed' => $removed,
      'processed_at'     => current_time('mysql'),
    ],
  ];
}

/** GD fallback, through WordPress's own editor: strips everything, including the profile. */
function db_photo_process_gd($src, $dest_dir, $name, $max_edge, $quality) {
  $size = @getimagesize($src);
  $had_gps = false;
  if (function_exists('exif_read_data') && ($size[2] ?? 0) === IMAGETYPE_JPEG) {
    $exif = @exif_read_data($src, 'GPS', true);
    $had_gps = !empty($exif['GPS']);
  }
  $editor = wp_get_image_editor($src);
  if (is_wp_error($editor)) return $editor;
  if (method_exists($editor, 'maybe_exif_rotate')) $editor->maybe_exif_rotate();
  $editor->resize($max_edge, $max_edge, false);
  $editor->set_quality($quality);
  $mime  = ($size['mime'] ?? '') === 'image/webp' ? 'image/webp' : 'image/jpeg';
  $ext   = $mime === 'image/webp' ? 'webp' : 'jpg';
  $saved = $editor->save(trailingslashit($dest_dir) . $name . '.' . $ext, $mime);
  if (is_wp_error($saved)) return $saved;
  $after = $editor->get_size();
  return [
    'path'   => $saved['path'],
    'name'   => $saved['file'],
    'mime'   => $mime,
    'report' => [
      'editor'           => 'gd',
      'before'           => ['bytes' => filesize($src), 'width' => $size[0] ?? 0, 'height' => $size[1] ?? 0, 'format' => strtoupper(str_replace('image/', '', $size['mime'] ?? ''))],
      'after'            => ['bytes' => filesize($saved['path']), 'width' => $after['width'], 'height' => $after['height'], 'format' => strtoupper($ext === 'jpg' ? 'jpeg' : $ext)],
      'quality'          => $quality,
      'colour_profile'   => 'not kept (GD)',
      'had_location'     => $had_gps,
      'metadata_removed' => ['all'],
      'processed_at'     => current_time('mysql'),
    ],
  ];
}

/**
 * Process a file and add it to the media library, attached to $post_id.
 * Returns the attachment id, or a WP_Error. $src is left in place.
 */
function db_photo_store($src, $post_id, $title) {
  require_once ABSPATH . 'wp-admin/includes/file.php';
  require_once ABSPATH . 'wp-admin/includes/media.php';
  require_once ABSPATH . 'wp-admin/includes/image.php';

  $done = db_photo_process($src, get_temp_dir());
  if (is_wp_error($done)) return $done;
  db_photo_step('location check passed');

  // Sideload moves the processed file into uploads/ and makes the sizes.
  $attachment_id = media_handle_sideload(['name' => $done['name'], 'tmp_name' => $done['path']], $post_id, $title);
  if (is_wp_error($attachment_id)) {
    @unlink($done['path']);
    return $attachment_id;
  }
  db_photo_step('added to media library, sizes made');
  update_post_meta($attachment_id, '_db_processed', $done['report']);
  update_post_meta($post_id, '_db_image', $done['report']);
  return $attachment_id;
}

/** "2.3 MB", "196 KB". */
function db_bytes($n) {
  return $n >= MB_IN_BYTES ? number_format($n / MB_IN_BYTES, 1) . ' MB' : number_format($n / KB_IN_BYTES) . ' KB';
}

/* -----------------------------------------------------------------------
 * Photos submitted before processing existed
 *
 * They were stored as uploaded — full size, metadata and all, and
 * reachable by anyone who guesses the file's address. This replaces each
 * with a processed copy and deletes the original and all its sizes.
 * -------------------------------------------------------------------- */

function db_photo_unprocessed() {
  $posts = get_posts(['post_type' => 'db_gallery_photo', 'post_status' => 'any', 'numberposts' => -1]);
  return array_values(array_filter($posts, function($p) {
    $att = (int) get_post_meta($p->ID, 'photo', true);
    return $att && !get_post_meta($att, '_db_processed', true);
  }));
}

/**
 * Replace one photo stored before processing with a processed copy, and
 * delete the original with all its sizes. One per request, traced, so a
 * slow or failing photo is found on its own.
 */
function db_photo_process_one(WP_Post $p) {
  global $db_photo_trace;
  @set_time_limit(120);
  $db_photo_trace = ['post' => $p->ID, 'title' => get_the_title($p), 'started' => current_time('mysql'), 't0' => microtime(true), 'steps' => [], 'finished' => false];
  db_photo_step('started');

  $old  = (int) get_post_meta($p->ID, 'photo', true);
  $file = function_exists('wp_get_original_image_path') ? wp_get_original_image_path($old) : get_attached_file($old);
  if (!$old || !$file || !file_exists($file)) return ['post' => $p, 'error' => 'the file is missing'];

  // Work on a copy: the sideload moves its input, and the original goes
  // with its attachment below.
  $copy = wp_tempnam(basename($file));
  copy($file, $copy);
  db_photo_step('original copied (' . db_bytes(filesize($copy)) . ')');
  $new = db_photo_store($copy, $p->ID, get_the_title($old) ?: get_field('species_name', $p->ID));
  @unlink($copy);
  if (is_wp_error($new)) {
    db_photo_step('failed: ' . $new->get_error_message());
    return ['post' => $p, 'error' => $new->get_error_message()];
  }

  update_field('field_gallery_photo', $new, $p->ID);
  if (get_post_thumbnail_id($p->ID) === $old) set_post_thumbnail($p->ID, $new);
  wp_delete_attachment($old, true);
  db_photo_step('original and its sizes deleted');
  if (class_exists('LiteSpeed\Purge')) LiteSpeed\Purge::purge_all();

  $db_photo_trace['finished'] = true;
  db_photo_step('done');
  return ['post' => $p, 'report' => get_post_meta($new, '_db_processed', true)];
}

add_action('admin_menu', function() {
  add_submenu_page(
    'edit.php?post_type=db_gallery_photo',
    'Photo processing',
    'Photo processing',
    'manage_options',
    'db-photo-processing',
    'db_photo_processing_page'
  );
});

function db_photo_processing_page() {
  if (!current_user_can('manage_options')) return;
  $rows = null;
  if (isset($_POST['db_process_one']) && check_admin_referer('db_process_one')) {
    $post = get_post((int) $_POST['db_process_one']);
    if ($post && $post->post_type === 'db_gallery_photo') $rows = [db_photo_process_one($post)];
  }
  $pending = db_photo_unprocessed();
  $last    = get_option('db_photo_last_run');
  ?>
  <div class="wrap">
    <h1>Photo processing</h1>
    <p>Every submitted photograph is resized to <?php echo (int) db_gallery_setting('max_edge_px'); ?>px on its longest edge,
      recompressed at quality <?php echo (int) db_gallery_setting('image_quality'); ?>, and stripped of EXIF, GPS and other metadata
      (the colour profile is kept). The original upload is never stored.
      Image library on this server: <strong><?php echo extension_loaded('imagick') ? 'Imagick ' . esc_html(phpversion('imagick')) : 'GD (colour profiles cannot be kept)'; ?></strong>.</p>

    <?php if ($rows !== null): ?>
      <h2>Processed just now</h2>
      <?php db_photo_report_table($rows); ?>
    <?php endif; ?>

    <?php if (is_array($last) && !empty($last['steps'])): ?>
      <div class="notice notice-<?php echo $last['finished'] ? 'info' : 'warning'; ?> inline" style="margin:16px 0">
        <p><strong>Last run:</strong> <?php echo esc_html($last['title']); ?>, started <?php echo esc_html($last['started']); ?> —
          <?php echo $last['finished'] ? 'finished.' : '<strong>did not finish</strong>; it stopped after the last step below.'; ?></p>
        <ol style="margin-left:20px"><?php foreach ($last['steps'] as [$label, $ms]): ?>
          <li><?php echo esc_html($label); ?> <span class="description">(<?php echo esc_html(number_format($ms / 1000, 1)); ?> s)</span></li>
        <?php endforeach; ?></ol>
      </div>
    <?php endif; ?>

    <h2>Photos stored before processing</h2>
    <?php if (!$pending): ?>
      <p>None — every gallery photo has been processed.</p>
    <?php else: ?>
      <p><?php echo esc_html(sprintf(
        _n('%d photo was stored as uploaded: full size, with its metadata.', '%d photos were stored as uploaded: full size, with their metadata.', count($pending)),
        count($pending)
      )); ?> Processing replaces one with a processed copy and deletes the original. One photo at a time.</p>
      <form method="post">
        <?php wp_nonce_field('db_process_one'); ?>
        <table class="widefat striped" style="max-width:900px">
          <thead><tr><th>Photo</th><th>Stored file</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($pending as $p):
            $att  = (int) get_post_meta($p->ID, 'photo', true);
            $file = function_exists('wp_get_original_image_path') ? wp_get_original_image_path($att) : get_attached_file($att);
            $meta = wp_get_attachment_metadata($att); ?>
            <tr>
              <td><a href="<?php echo esc_url(get_edit_post_link($p)); ?>"><?php echo esc_html(get_the_title($p)); ?></a></td>
              <td><?php echo $file && file_exists($file)
                ? esc_html(sprintf('%s · %d×%d · %s', basename($file), $meta['width'] ?? 0, $meta['height'] ?? 0, db_bytes(filesize($file))))
                : '<span style="color:#b32d2e">file missing</span>'; ?></td>
              <td><button class="button" name="db_process_one" value="<?php echo (int) $p->ID; ?>">Process</button></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </form>
    <?php endif; ?>

    <h2>Recently processed</h2>
    <?php
    $recent = get_posts(['post_type' => 'db_gallery_photo', 'post_status' => 'any', 'numberposts' => 20, 'meta_key' => '_db_image']);
    if ($recent) db_photo_report_table(array_map(fn($p) => ['post' => $p, 'report' => get_post_meta($p->ID, '_db_image', true)], $recent));
    else echo '<p>None yet.</p>';
    ?>
  </div>
  <?php
}

function db_photo_report_table(array $rows) {
  ?>
  <table class="widefat striped" style="max-width:1200px">
    <thead><tr><th>Photo</th><th>Before</th><th>After</th><th>Saved</th><th>Colour profile</th><th>Location data</th><th>Metadata removed</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
      $title = get_the_title($r['post']);
      if (!empty($r['error'])): ?>
        <tr><td><?php echo esc_html($title); ?></td><td colspan="6" style="color:#b32d2e">Not processed: <?php echo esc_html($r['error']); ?></td></tr>
      <?php continue; endif;
      $x = $r['report']; $b = $x['before']; $a = $x['after'];
      $saved = $b['bytes'] ? round(100 - 100 * $a['bytes'] / $b['bytes']) : 0; ?>
      <tr>
        <td><a href="<?php echo esc_url(get_edit_post_link($r['post'])); ?>"><?php echo esc_html($title); ?></a></td>
        <td><?php echo esc_html(sprintf('%s · %d×%d · %s', $b['format'], $b['width'], $b['height'], db_bytes($b['bytes']))); ?></td>
        <td><?php echo esc_html(sprintf('%s · %d×%d · %s · q%d', $a['format'], $a['width'], $a['height'], db_bytes($a['bytes']), $x['quality'])); ?></td>
        <td><?php echo esc_html($saved . '%'); ?></td>
        <td><?php echo esc_html($x['colour_profile']); ?></td>
        <td><?php echo $x['had_location'] ? 'Removed' : 'None in the original'; ?></td>
        <td><?php echo esc_html($x['metadata_removed'] ? implode(', ', $x['metadata_removed']) : '—'); ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php
}
