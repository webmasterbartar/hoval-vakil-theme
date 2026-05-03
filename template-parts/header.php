<?php
/**
 * Coded Hovalvakil header.
 *
 * @package HelloElementor
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$site_name = 'هوالوکیل';
$header_phone   = (string) get_theme_mod( 'hvl_footer_phone', '۰۲۱۲۲۰۰۰۷۵۲' );
$header_address = (string) get_theme_mod( 'hvl_footer_address', 'تهران -بلوار کاوه نرسیده به چهارراه دولت پ ۱۲' );
$header_phone_dial = preg_replace( '/\D+/', '', $header_phone );
if ( is_string( $header_phone_dial ) && '' !== $header_phone_dial && 0 === strpos( $header_phone_dial, '0' ) ) {
	$header_phone_dial = '+98' . substr( $header_phone_dial, 1 );
}

$current_path = trim( (string) wp_parse_url( home_url( add_query_arg( [] ) ), PHP_URL_PATH ), '/' );

$header_links = [
	[
		'label' => 'خانه',
		'url'   => home_url( '/' ),
		'icon'  => 'home',
		'slug'  => '',
	],
	[
		'label' => 'وکلا',
		'url'   => get_post_type_archive_link( 'hvl_lawyer' ),
		'icon'  => 'gavel',
		'slug'  => 'hvl_lawyer',
	],
	[
		'label' => 'تخصص‌ها',
		'url'   => home_url( '/takha/' ),
		'icon'  => 'work',
		'slug'  => 'takha',
	],
	[
		'label' => 'مراکز حقوقی',
		'url'   => home_url( '/marakez/' ),
		'icon'  => 'account_balance',
		'slug'  => 'marakez',
	],
	[
		'label' => 'تماس با ما',
		'url'   => home_url( '/tamas/' ),
		'icon'  => 'call',
		'slug'  => 'tamas',
	],
	[
		'label' => 'درباره ما',
		'url'   => home_url( '/about/' ),
		'icon'  => 'info',
		'slug'  => 'about',
	],
];
?>

<header id="site-header" class="site-header hvl-header">
	<div class="hvl-header-utility">
		<div class="container hvl-header-utility-inner">
			<div class="hvl-header-utility-left">
				<div class="hvl-header-utility-address">
					<span class="hvl-header-utility-icon material-symbols-outlined">location_on</span>
					<span><?php echo esc_html( $header_address ); ?></span>
				</div>
				<div class="hvl-header-utility-phone">
					<span class="hvl-header-utility-icon material-symbols-outlined">phone</span>
					<span dir="ltr"><?php echo esc_html( $header_phone ); ?></span>
				</div>
			</div>
		</div>
	</div>

	<div class="hvl-header-main">
		<div class="container hvl-header-main-inner">
			<div class="hvl-header-logo">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( $site_name ); ?></a>
			</div>
			<nav class="hvl-header-nav" aria-label="<?php echo esc_attr__( 'Main menu', 'hello-elementor' ); ?>">
				<?php foreach ( $header_links as $item ) : ?>
					<?php
					$active = '';
					if ( '' === $item['slug'] ) {
						$active = '' === $current_path ? ' is-active' : '';
					} elseif ( false !== strpos( $current_path, (string) $item['slug'] ) ) {
						$active = ' is-active';
					}
					?>
					<a class="<?php echo esc_attr( 'hvl-header-link' . $active ); ?>" href="<?php echo esc_url( $item['url'] ); ?>">
						<span class="material-symbols-outlined"><?php echo esc_html( $item['icon'] ); ?></span>
						<?php echo esc_html( $item['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<div class="hvl-header-actions">
				<a class="hvl-header-login" href="<?php echo esc_url( home_url( '/my-account/' ) ); ?>">ورود | ثبت‌نام</a>
				<button id="hvl-mobile-menu-toggle" class="hvl-header-mobile-toggle" type="button" aria-label="باز کردن منو" aria-expanded="false" aria-controls="hvl-mobile-drawer">
					<span class="material-symbols-outlined">menu</span>
				</button>
			</div>
		</div>
	</div>

	<div id="hvl-mobile-backdrop" class="hvl-mobile-backdrop" hidden></div>
	<aside id="hvl-mobile-drawer" class="hvl-mobile-drawer" aria-hidden="true" tabindex="-1">
		<div class="hvl-mobile-drawer-head">
			<div class="hvl-mobile-drawer-brand"><?php echo esc_html( $site_name ); ?></div>
			<button id="hvl-mobile-menu-close" type="button" class="hvl-mobile-drawer-close" aria-label="بستن منو">
				<span class="material-symbols-outlined">close</span>
			</button>
		</div>
		<nav class="hvl-mobile-nav" aria-label="منوی موبایل">
			<?php foreach ( $header_links as $item ) : ?>
				<?php
				$active = '';
				if ( '' === $item['slug'] ) {
					$active = '' === $current_path ? ' is-active' : '';
				} elseif ( false !== strpos( $current_path, (string) $item['slug'] ) ) {
					$active = ' is-active';
				}
				?>
				<a class="<?php echo esc_attr( 'hvl-mobile-link' . $active ); ?>" href="<?php echo esc_url( $item['url'] ); ?>">
					<span class="material-symbols-outlined"><?php echo esc_html( $item['icon'] ); ?></span>
					<span><?php echo esc_html( $item['label'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>
		<div class="hvl-mobile-drawer-footer">
			<a class="hvl-mobile-primary-cta" href="<?php echo esc_url( home_url( '/my-account/' ) ); ?>">ورود | ثبت‌نام</a>
			<a class="hvl-mobile-support-link" href="<?php echo esc_url( 'tel:' . (string) $header_phone_dial ); ?>">
				<span class="material-symbols-outlined">call</span>
				پشتیبانی تلفنی
			</a>
		</div>
	</aside>
</header>

<script>
	(function () {
		const toggleBtn = document.getElementById('hvl-mobile-menu-toggle');
		const closeBtn = document.getElementById('hvl-mobile-menu-close');
		const drawer = document.getElementById('hvl-mobile-drawer');
		const backdrop = document.getElementById('hvl-mobile-backdrop');
		if (!toggleBtn || !closeBtn || !drawer || !backdrop) return;

		let lastFocused = null;

		const setOpenState = (open) => {
			if (open) {
				lastFocused = document.activeElement;
				drawer.classList.add('is-open');
				backdrop.classList.add('is-open');
				backdrop.hidden = false;
				drawer.setAttribute('aria-hidden', 'false');
				toggleBtn.setAttribute('aria-expanded', 'true');
				document.body.classList.add('hvl-mobile-menu-open');
				window.setTimeout(() => closeBtn.focus(), 30);
			} else {
				drawer.classList.remove('is-open');
				backdrop.classList.remove('is-open');
				drawer.setAttribute('aria-hidden', 'true');
				toggleBtn.setAttribute('aria-expanded', 'false');
				document.body.classList.remove('hvl-mobile-menu-open');
				window.setTimeout(() => { backdrop.hidden = true; }, 220);
				if (lastFocused && typeof lastFocused.focus === 'function') {
					lastFocused.focus();
				}
			}
		};

		toggleBtn.addEventListener('click', () => setOpenState(true));
		closeBtn.addEventListener('click', () => setOpenState(false));
		backdrop.addEventListener('click', () => setOpenState(false));

		drawer.querySelectorAll('a').forEach((link) => {
			link.addEventListener('click', () => setOpenState(false));
		});

		document.addEventListener('keydown', (event) => {
			if (event.key === 'Escape' && drawer.classList.contains('is-open')) {
				setOpenState(false);
			}
		});
	})();
</script>
