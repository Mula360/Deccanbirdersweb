<?php
/**
 * Trip Photos: one entry per trip, holding as many photos as it needs.
 *
 * The photos are picked in one go from the Media Library (several at once,
 * in any order, dragged to reorder) and kept in the _db_trip_images meta
 * as attachment IDs. Entries made before this, with one featured image
 * each, still show: their featured image counts as their one photo.
 *
 * The Events page shows them as a strip of tiles, the size of the home
 * page's "From members' cameras", newest trip first.
 */

if (!defined('ABSPATH')) exit;

const DB_TRIP_IMAGES_META = '_db_trip_images';

/** A trip's photos as attachment IDs, in the chosen order. */
function db_trip_images($post_id) {
  $ids = array_filter(array_map('intval', explode(',', (string) get_post_meta($post_id, DB_TRIP_IMAGES_META, true))));
  if (!$ids && ($thumb = (int) get_post_thumbnail_id($post_id))) $ids = [$thumb];
  return array_values(array_filter($ids, fn($id) => wp_attachment_is_image($id)));
}

/**
 * Photos for the Events strip: [attachment ID, alt text] pairs from the
 * newest trips, up to $limit photos. A photo's own alt text wins; the
 * trip's title stands in for it otherwise.
 */
function db_trip_strip_photos($limit = 36) {
  $trips = get_posts([
    'post_type'      => 'db_trip_photo',
    'posts_per_page' => 20,
    'post_status'    => 'publish',
    'orderby'        => ['menu_order' => 'ASC', 'date' => 'DESC'],
  ]);
  $photos = [];
  foreach ($trips as $trip) {
    foreach (db_trip_images($trip->ID) as $id) {
      $alt = trim((string) get_post_meta($id, '_wp_attachment_image_alt', true));
      $photos[] = [$id, $alt !== '' ? $alt : $trip->post_title];
      if (count($photos) >= $limit) return $photos;
    }
  }
  return $photos;
}

add_action('add_meta_boxes_db_trip_photo', function() {
  add_meta_box('db-trip-photos-box', __('Photos', 'deccan-birders'), 'db_trip_images_box', 'db_trip_photo', 'normal', 'high');
});

function db_trip_images_box($post) {
  wp_nonce_field('db_trip_images', 'db_trip_images_nonce');
  $ids = db_trip_images($post->ID);
  ?>
  <p class="description" style="margin-top:0">
    <?php esc_html_e('Press Add photos, then click every photo you want (each click adds one), or drag a batch from your computer into the window to upload them all at once. Drag the tiles to change the order. Landscape, about 1600 × 900 px and under 400 KB each.', 'deccan-birders'); ?>
  </p>
  <ul id="db-trip-images" style="display:flex;flex-wrap:wrap;gap:8px;margin:12px 0">
    <?php foreach ($ids as $id): ?>
      <li data-id="<?php echo (int) $id; ?>" style="position:relative;cursor:move;margin:0">
        <?php echo wp_get_attachment_image($id, 'thumbnail', false, ['style' => 'width:96px;height:96px;object-fit:cover;border-radius:6px;display:block']); ?>
        <button type="button" class="db-trip-remove" aria-label="<?php esc_attr_e('Remove this photo', 'deccan-birders'); ?>"
                style="position:absolute;top:2px;right:2px;border:0;border-radius:50%;width:22px;height:22px;background:#b32d2e;color:#fff;cursor:pointer;line-height:1">×</button>
      </li>
    <?php endforeach; ?>
  </ul>
  <input type="hidden" name="db_trip_images" id="db-trip-images-input" value="<?php echo esc_attr(implode(',', $ids)); ?>">
  <p>
    <button type="button" class="button button-primary" id="db-trip-add"><?php esc_html_e('Add photos', 'deccan-birders'); ?></button>
    <span id="db-trip-count" style="margin-left:8px;color:#646970"></span>
  </p>
  <?php
}

add_action('save_post_db_trip_photo', function($post_id) {
  if (!isset($_POST['db_trip_images_nonce']) || !wp_verify_nonce($_POST['db_trip_images_nonce'], 'db_trip_images')) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!current_user_can('edit_post', $post_id)) return;

  $ids = array_values(array_unique(array_filter(
    array_map('intval', explode(',', sanitize_text_field(wp_unslash($_POST['db_trip_images'] ?? '')))),
    fn($id) => $id > 0 && wp_attachment_is_image($id)
  )));
  update_post_meta($post_id, DB_TRIP_IMAGES_META, implode(',', $ids));
  // The first photo doubles as the featured image, so the list screen shows it.
  if ($ids) set_post_thumbnail($post_id, $ids[0]);
  else delete_post_thumbnail($post_id);
});

add_action('admin_enqueue_scripts', function($hook) {
  if (!in_array($hook, ['post.php', 'post-new.php'], true) || get_current_screen()->post_type !== 'db_trip_photo') return;
  wp_enqueue_media();
  wp_enqueue_script('jquery-ui-sortable');
  wp_add_inline_script('jquery-ui-sortable', <<<'JS'
jQuery(function($) {
  var list = $('#db-trip-images'), input = $('#db-trip-images-input'), count = $('#db-trip-count'), frame;
  function sync() {
    var ids = list.children().map(function() { return $(this).data('id'); }).get();
    input.val(ids.join(','));
    count.text(ids.length === 1 ? '1 photo' : ids.length + ' photos');
  }
  function tile(att) {
    var src = (att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail : att).url;
    return $('<li style="position:relative;cursor:move;margin:0">').attr('data-id', att.id)
      .append($('<img alt="" style="width:96px;height:96px;object-fit:cover;border-radius:6px;display:block">').attr('src', src))
      .append('<button type="button" class="db-trip-remove" aria-label="Remove this photo" style="position:absolute;top:2px;right:2px;border:0;border-radius:50%;width:22px;height:22px;background:#b32d2e;color:#fff;cursor:pointer;line-height:1">×</button>');
  }
  list.sortable({ update: sync });
  list.on('click', '.db-trip-remove', function() { $(this).closest('li').remove(); sync(); });
  $('#db-trip-add').on('click', function() {
    if (!frame) {
      frame = wp.media({ title: 'Add trip photos', button: { text: 'Add to this trip' }, library: { type: 'image' }, multiple: 'add' });
      frame.on('select', function() {
        var have = input.val().split(',');
        frame.state().get('selection').each(function(m) {
          if (have.indexOf(String(m.id)) === -1) list.append(tile(m.toJSON()));
        });
        sync();
      });
    }
    frame.open();
  });
  sync();
});
JS
  );
});

// The list of trips: how many photos each holds, beside its first one.
add_filter('manage_db_trip_photo_posts_columns', function($cols) {
  return array_slice($cols, 0, 1, true) + ['db_trip_thumb' => ''] + array_slice($cols, 1, 1, true) + ['db_trip_count' => __('Photos', 'deccan-birders')] + array_slice($cols, 2, null, true);
});
add_action('manage_db_trip_photo_posts_custom_column', function($col, $post_id) {
  $ids = db_trip_images($post_id);
  if ($col === 'db_trip_thumb' && $ids) echo wp_get_attachment_image($ids[0], [60, 60], false, ['style' => 'width:60px;height:60px;object-fit:cover;border-radius:4px']);
  if ($col === 'db_trip_count') echo (int) count($ids);
}, 10, 2);

add_filter('enter_title_here', fn($text, $post) => $post->post_type === 'db_trip_photo' ? __('Trip name, e.g. Lakshmipur Lake, 28 September', 'deccan-birders') : $text, 10, 2);
