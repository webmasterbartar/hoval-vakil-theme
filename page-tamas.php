<?php
/**
 * Page template for contact page (tamas).
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$contact_phone   = (string) get_theme_mod( 'hvl_footer_phone', '۰۲۱۲۲۰۰۰۷۵۲' );
$contact_email   = (string) get_theme_mod( 'hvl_footer_email', 'info@hovalvakil.ir' );
$contact_address = (string) get_theme_mod( 'hvl_footer_address', 'تهران -بلوار کاوه نرسیده به چهارراه دولت پ ۱۲' );

get_header();
?>
<main id="content" class="tamas-page">
	<section class="tamas-hero">
		<div class="container">
			<h1>تماس با ما</h1>
			<p>در هر ساعت از شبانه‌روز پاسخگوی شما هستیم. برای ارتباط با تیم هوالوکیل فرم زیر را تکمیل کنید.</p>
		</div>
	</section>

	<section class="tamas-content container">
		<div class="tamas-grid">
			<aside class="tamas-card tamas-contact-card">
				<h2>اطلاعات تماس</h2>
				<ul class="tamas-contact-list">
					<li>
						<span class="material-symbols-outlined">call</span>
						<div>
							<strong>شماره تماس پشتیبانی</strong>
							<p dir="ltr"><?php echo esc_html( $contact_phone ); ?></p>
						</div>
					</li>
					<li>
						<span class="material-symbols-outlined">mail</span>
						<div>
							<strong>پست الکترونیک</strong>
							<p dir="ltr"><?php echo esc_html( $contact_email ); ?></p>
						</div>
					</li>
					<li>
						<span class="material-symbols-outlined">location_on</span>
						<div>
							<strong>دفتر مرکزی</strong>
							<p><?php echo esc_html( $contact_address ); ?></p>
						</div>
					</li>
					<li>
						<span class="material-symbols-outlined">schedule</span>
						<div>
							<strong>ساعات پاسخگویی</strong>
							<p>شنبه تا چهارشنبه ۹ صبح تا ۱۸ عصر</p>
						</div>
					</li>
				</ul>
			</aside>

			<div class="tamas-card tamas-form-card">
				<h2>پیام خود را بفرستید</h2>
				<form id="tamas-contact-form" class="tamas-form" novalidate>
					<div class="tamas-row">
						<div class="tamas-field">
							<label for="tamas-full-name">نام و نام خانوادگی</label>
							<input id="tamas-full-name" name="full_name" type="text" placeholder="مثال: علی رضایی" required />
						</div>
						<div class="tamas-field">
							<label for="tamas-mobile">شماره موبایل</label>
							<input id="tamas-mobile" name="mobile" dir="ltr" type="tel" placeholder="0912 345 6789" required />
						</div>
					</div>
					<div class="tamas-row">
						<div class="tamas-field">
							<label for="tamas-email">ایمیل (اختیاری)</label>
							<input id="tamas-email" name="email" dir="ltr" type="email" placeholder="email@example.com" />
						</div>
						<div class="tamas-field">
							<label for="tamas-subject">موضوع</label>
							<select id="tamas-subject" name="subject" required>
								<option value="">انتخاب کنید...</option>
								<option value="support">پشتیبانی</option>
								<option value="complaint">انتقاد</option>
								<option value="suggestion">پیشنهاد</option>
								<option value="cooperation">همکاری</option>
								<option value="register">ثبت مرکز حقوقی</option>
							</select>
						</div>
					</div>
					<div class="tamas-field">
						<label for="tamas-message">متن پیام</label>
						<textarea id="tamas-message" name="message" rows="5" placeholder="پیام خود را کامل و شفاف بنویسید..." required></textarea>
					</div>
					<div class="tamas-submit-wrap">
						<button id="tamas-submit" type="submit">
							<span class="material-symbols-outlined">send</span>
							ارسال پیام
						</button>
					</div>
					<p id="tamas-form-feedback" class="tamas-feedback" hidden></p>
				</form>
			</div>
		</div>
	</section>
</main>

<script>
	(function () {
		const form = document.getElementById('tamas-contact-form');
		const feedback = document.getElementById('tamas-form-feedback');
		if (!form || !feedback) return;

		form.addEventListener('submit', function (event) {
			event.preventDefault();
			const formData = new FormData(form);
			const fullName = String(formData.get('full_name') || '').trim();
			const mobile = String(formData.get('mobile') || '').trim().replace(/\s+/g, '');
			const subject = String(formData.get('subject') || '').trim();
			const message = String(formData.get('message') || '').trim();

			if (!fullName || !mobile || !subject || !message) {
				feedback.hidden = false;
				feedback.className = 'tamas-feedback tamas-feedback-error';
				feedback.textContent = 'لطفاً همه فیلدهای الزامی را تکمیل کنید.';
				return;
			}

			if (!/^(\+98|0)?9\d{9}$/.test(mobile)) {
				feedback.hidden = false;
				feedback.className = 'tamas-feedback tamas-feedback-error';
				feedback.textContent = 'شماره موبایل معتبر نیست.';
				return;
			}

			feedback.hidden = false;
			feedback.className = 'tamas-feedback tamas-feedback-success';
			feedback.textContent = 'پیام شما با موفقیت ثبت شد. به زودی با شما تماس می‌گیریم.';
			form.reset();
		});
	})();
</script>

<?php get_footer(); ?>
