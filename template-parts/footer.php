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
$footer_email     = get_theme_mod( 'hvl_footer_email', 'info@holokeil.ir' );
$footer_address   = get_theme_mod( 'hvl_footer_address', 'تهران -بلوار کاوه نرسیده به چهارراه دولت پ ۱۲' );
$footer_copy      = get_theme_mod( 'hvl_footer_copyright', '© ۱۴۰۲ تمامی حقوق برای هولوکیل محفوظ است.' );
?>
<footer id="site-footer" class="site-footer hvl-footer">
	<div class="container">
		<div class="hvl-footer-main">
			<section>
				<div class="hvl-footer-brand">
					<span>هولوکیل</span>
				</div>
				<p class="hvl-footer-bio">
					هولوکیل، بزرگ‌ترین سامانه نوبت‌دهی و مشاوره آنلاین با وکلای متخصص ایران است. ما با هدف دسترسی آسان و سریع مردم به خدمات حقوقی باکیفیت، بستری امن و هوشمند برای ارتباط موکل و وکیل فراهم کرده‌ایم.
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
					<li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">درباره هولوکیل</a></li>
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
						<span><?php echo esc_html( $footer_phone ); ?></span>
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
