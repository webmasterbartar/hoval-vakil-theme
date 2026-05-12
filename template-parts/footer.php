<?php
/**
 * The template for displaying footer.
 *
 * @package HelloElementor
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$footer_phone     = get_theme_mod( 'hvl_footer_phone', '۰۲۱۲۲۰۰۰۷۵۲' );
$footer_email     = get_theme_mod( 'hvl_footer_email', 'info@hovalvakil.ir' );
$footer_address   = get_theme_mod( 'hvl_footer_address', 'تهران -بلوار کاوه نرسیده به چهارراه دولت پ ۱۲' );
$footer_copy      = get_theme_mod( 'hvl_footer_copyright', '© ۱۴۰۲ تمامی حقوق برای هوالوکیل محفوظ است.' );
$footer_phone_ascii = strtr(
	(string) $footer_phone,
	[
		'۰' => '0',
		'۱' => '1',
		'۲' => '2',
		'۳' => '3',
		'۴' => '4',
		'۵' => '5',
		'۶' => '6',
		'۷' => '7',
		'۸' => '8',
		'۹' => '9',
	]
);
$footer_phone_dial = preg_replace( '/\D+/', '', $footer_phone_ascii );
if ( ! is_string( $footer_phone_dial ) || '' === $footer_phone_dial ) {
	$footer_phone_dial = '02122000752';
}
?>
<footer id="site-footer" class="site-footer hvl-footer">
	<div class="container">
		<div class="hvl-footer-main">
			<section>
				<div class="hvl-footer-brand">
					<span>هوالوکیل</span>
				</div>
				<p class="hvl-footer-bio">
					هوالوکیل، بزرگ‌ترین سامانه نوبت‌دهی و مشاوره آنلاین با وکلای متخصص ایران است. ما با هدف دسترسی آسان و سریع مردم به خدمات حقوقی باکیفیت، بستری امن و هوشمند برای ارتباط موکل و وکیل فراهم کرده‌ایم.
				</p>
			</section>

			<section>
				<h4 class="hvl-footer-title">دسترسی سریع</h4>
				<ul class="hvl-footer-list">
					<li><a href="<?php echo esc_url( get_post_type_archive_link( 'hvl_lawyer' ) ); ?>">جستجوی وکیل</a></li>
					<li><a href="<?php echo esc_url( home_url( '/takha/' ) ); ?>">تخصص‌ها</a></li>
					<li><a href="<?php echo esc_url( home_url( '/marakez/' ) ); ?>">مراکز حقوقی</a></li>
					<li><a href="<?php echo esc_url( home_url( '/rezerv/' ) ); ?>">مشاوره آنلاین</a></li>
					<li><a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>">مجله حقوقی</a></li>
				</ul>
			</section>

			<section>
				<h4 class="hvl-footer-title">درباره ما</h4>
				<ul class="hvl-footer-list">
					<li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">درباره هوالوکیل</a></li>
					<li><a href="<?php echo esc_url( home_url( '/tamas/' ) ); ?>">تماس با ما</a></li>
					<li><a href="<?php echo esc_url( home_url( '/faq/' ) ); ?>">سوالات متداول</a></li>
					<li><a href="<?php echo esc_url( home_url( '/terms/' ) ); ?>">قوانین و مقررات</a></li>
					<li><a href="<?php echo esc_url( home_url( '/privacy/' ) ); ?>">حریم خصوصی</a></li>
				</ul>
			</section>

			<section>
				<h4 class="hvl-footer-title">تماس با ما</h4>
				<div class="hvl-footer-contact">
					<div class="hvl-footer-contact-row">
						<span class="material-symbols-outlined">phone</span>
						<a dir="ltr" href="<?php echo esc_url( 'tel:' . (string) $footer_phone_dial ); ?>"><?php echo esc_html( $footer_phone ); ?></a>
					</div>
					<div class="hvl-footer-contact-row">
						<span class="material-symbols-outlined">mail</span>
						<span><?php echo esc_html( $footer_email ); ?></span>
					</div>
					<div class="hvl-footer-contact-row">
						<span class="material-symbols-outlined">location_on</span>
						<span><?php echo esc_html( $footer_address ); ?></span>
					</div>
				</div>
			</section>
		</div>

		<div class="hvl-footer-divider"></div>

		<div class="hvl-footer-bottom">
			<p class="hvl-footer-copyright"><?php echo esc_html( $footer_copy ); ?></p>
		</div>
	</div>
</footer>
