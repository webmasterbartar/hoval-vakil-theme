<?php
/**
 * Page template: Lawyer Profile Panel.
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="content" class="hvl-ll-page hvl-ll-page--panel">
	<?php echo do_shortcode( '[hvl_lawyer_panel]' ); ?>
</main>
<?php
get_footer();
