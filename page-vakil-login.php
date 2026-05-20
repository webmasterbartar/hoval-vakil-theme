<?php
/**
 * Page template: Lawyer OTP Login.
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="content" class="hvl-ll-page hvl-ll-page--login">
	<?php echo do_shortcode( '[hvl_lawyer_login]' ); ?>
</main>
<?php
get_footer();
