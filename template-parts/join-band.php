<?php
/**
 * Join band — the membership call-to-action that appears at the foot of
 * every page except Membership and Contact (the design's showJoinBand
 * rule: `p !== "membership" && p !== "contact"`).
 *
 * Light blue band, heading and standfirst on the left, green pill button
 * on the right, wrapping to stacked on narrow screens.
 */
$membership_url = ($m = get_page_by_path('membership')) ? get_permalink($m) : '/membership';
?>
<section class="join-band">
  <div class="join-band-inner">
    <div>
      <h2 class="join-band-title">Join 500+ members</h2>
      <p class="join-band-text">Stay in the loop with everything you need to know about bird watching.</p>
    </div>
    <a href="<?php echo esc_url($membership_url); ?>" class="join-band-btn">Apply for Membership</a>
  </div>
</section>
