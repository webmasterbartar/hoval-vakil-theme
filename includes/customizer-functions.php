<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Register Customizer controls for header & footer.
 *
 * @return void
 */
function hello_customizer_register( $wp_customize ) {
	require_once get_template_directory() . '/includes/customizer/customizer-action-links.php';

	$wp_customize->add_section(
		'hello-options',
		[
			'title' => esc_html__( 'Header & Footer', 'hello-elementor' ),
			'capability' => 'edit_theme_options',
		]
	);

	$wp_customize->add_setting(
		'hello-header-footer',
		[
			'sanitize_callback' => false,
			'transport' => 'refresh',
		]
	);

	$wp_customize->add_control(
		new HelloElementor\Includes\Customizer\Hello_Customizer_Action_Links(
			$wp_customize,
			'hello-header-footer',
			[
				'section' => 'hello-options',
				'priority' => 20,
			]
		)
	);

	$wp_customize->add_section(
		'hvl-footer-options',
		[
			'title'      => esc_html__( 'Hovalvakil Footer', 'hello-elementor' ),
			'capability' => 'edit_theme_options',
			'priority'   => 40,
		]
	);

	$hvl_text_fields = [
		'hvl_footer_phone'      => [ 'label' => 'شماره تماس', 'default' => '۰۲۱۲۲۰۰۰۷۵۲' ],
		'hvl_footer_email'      => [ 'label' => 'ایمیل', 'default' => 'info@hovalvakil.ir' ],
		'hvl_footer_address'    => [ 'label' => 'آدرس', 'default' => 'تهران -بلوار کاوه نرسیده به چهارراه دولت پ ۱۲' ],
		'hvl_footer_copyright'  => [ 'label' => 'متن کپی‌رایت', 'default' => '© ۱۴۰۲ تمامی حقوق برای هوالوکیل محفوظ است.' ],
	];
	$hvl_url_fields  = [
		'hvl_footer_social_1'  => [ 'label' => 'لینک شبکه اجتماعی ۱', 'default' => '#' ],
		'hvl_footer_social_2'  => [ 'label' => 'لینک شبکه اجتماعی ۲', 'default' => '#' ],
		'hvl_footer_social_3'  => [ 'label' => 'لینک شبکه اجتماعی ۳', 'default' => '#' ],
		'hvl_footer_social_4'  => [ 'label' => 'لینک شبکه اجتماعی ۴', 'default' => '#' ],
		'hvl_footer_playstore' => [ 'label' => 'لینک Google Play', 'default' => '#' ],
		'hvl_footer_appstore'  => [ 'label' => 'لینک App Store', 'default' => '#' ],
	];

	foreach ( $hvl_text_fields as $setting_id => $field ) {
		$wp_customize->add_setting(
			$setting_id,
			[
				'default'           => $field['default'],
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			]
		);

		$wp_customize->add_control(
			$setting_id,
			[
				'label'   => $field['label'],
				'section' => 'hvl-footer-options',
				'type'    => 'text',
			]
		);
	}

	foreach ( $hvl_url_fields as $setting_id => $field ) {
		$wp_customize->add_setting(
			$setting_id,
			[
				'default'           => $field['default'],
				'sanitize_callback' => 'esc_url_raw',
				'transport'         => 'refresh',
			]
		);

		$wp_customize->add_control(
			$setting_id,
			[
				'label'   => $field['label'],
				'section' => 'hvl-footer-options',
				'type'    => 'url',
			]
		);
	}
}
add_action( 'customize_register', 'hello_customizer_register' );

/**
 * Register Customizer controls for Elementor Pro upsell.
 *
 * @return void
 */
function hello_customizer_register_elementor_pro_upsell( $wp_customize ) {
	if ( function_exists( 'elementor_pro_load_plugin' ) ) {
		return;
	}

	require_once get_template_directory() . '/includes/customizer/customizer-upsell.php';

	$wp_customize->add_section(
		new HelloElementor\Includes\Customizer\Hello_Customizer_Upsell(
			$wp_customize,
			'hello-upsell-elementor-pro',
			[
				'heading' => esc_html__( 'Customize your entire website with Elementor Pro', 'hello-elementor' ),
				'description' => esc_html__( 'Build and customize every part of your website, including Theme Parts with Elementor Pro.', 'hello-elementor' ),
				'button_text' => esc_html__( 'Upgrade Now', 'hello-elementor' ),
				'button_url' => 'https://elementor.com/pro/?utm_source=hello-theme-customize&utm_campaign=gopro&utm_medium=wp-dash',
				'priority' => 999999,
			]
		)
	);
}
add_action( 'customize_register', 'hello_customizer_register_elementor_pro_upsell' );

/**
 * Enqueue Customizer CSS.
 *
 * @return void
 */
function hello_customizer_styles() {
	wp_enqueue_style(
		'hello-elementor-customizer',
		HELLO_THEME_STYLE_URL . 'customizer.css',
		[],
		HELLO_ELEMENTOR_VERSION
	);
}
add_action( 'admin_enqueue_scripts', 'hello_customizer_styles' );
