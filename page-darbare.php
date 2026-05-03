<?php
/**
 * Page template for about page (darbare).
 *
 * @package HelloElementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="content" class="darbare-page">
	<header class="darbare-hero">
		<div class="container">
			<h1>درباره هوالوکیل</h1>
			<p>ما اینجاییم تا دسترسی به عدالت را برای همه آسان کنیم.</p>
		</div>
	</header>

	<section class="container darbare-intro">
		<div class="darbare-card">
			<h2>هوالوکیل چیست؟</h2>
			<p>
				هوالوکیل بزرگترین و معتبرترین سامانه نوبت‌دهی آنلاین و مشاوره حقوقی در ایران است. ما با بهره‌گیری از تکنولوژی روز، پلی امن و سریع میان مردم و وکلای پایه یک دادگستری ایجاد کرده‌ایم. هدف ما شفافیت، دسترسی آسان و ارائه خدمات حقوقی با بالاترین استانداردهاست تا هیچ‌کس به دلیل عدم دسترسی به وکیل متخصص، از حقوق خود محروم نماند.
			</p>
		</div>
	</section>

	<section class="container darbare-stats">
		<article class="darbare-stat">
			<span class="material-symbols-outlined">gavel</span>
			<strong>۵۰۰۰+</strong>
			<small>وکیل متخصص</small>
		</article>
		<article class="darbare-stat">
			<span class="material-symbols-outlined">forum</span>
			<strong>۱۰۰k+</strong>
			<small>مشاوره موفق</small>
		</article>
		<article class="darbare-stat">
			<span class="material-symbols-outlined">location_city</span>
			<strong>۲۰۰+</strong>
			<small>شهر تحت پوشش</small>
		</article>
		<article class="darbare-stat">
			<span class="material-symbols-outlined">verified</span>
			<strong>۹۸٪</strong>
			<small>رضایت کاربران</small>
		</article>
	</section>

	<section class="container">
		<div class="darbare-mission">
			<h2>ماموریت ما</h2>
			<p>
				ما معتقدیم که دسترسی برابر به دانش و خدمات حقوقی، پایه و اساس یک جامعه عادلانه است. ماموریت ما در هوالوکیل، شکستن سدهای جغرافیایی و اطلاعاتی است تا هر فرد، فارغ از موقعیت مکانی و وضعیت مالی، بتواند در کوتاه‌ترین زمان ممکن، بهترین تصمیم حقوقی را با راهنمایی وکلای مجرب اتخاذ کند.
			</p>
		</div>
	</section>

	<section class="container darbare-values-wrap">
		<h2>ارزش‌های بنیادین ما</h2>
		<div class="darbare-values">
			<article class="darbare-value">
				<div class="darbare-value-icon"><span class="material-symbols-outlined">balance</span></div>
				<h3>عدالت محوری</h3>
				<p>تلاش برای احقاق حق و ایجاد بستری منصفانه برای تمامی کاربران و وکلا.</p>
			</article>
			<article class="darbare-value">
				<div class="darbare-value-icon"><span class="material-symbols-outlined">shield_lock</span></div>
				<h3>امنیت و اعتماد</h3>
				<p>حفظ حریم خصوصی اطلاعات پرونده‌ها و تضمین امنیت ارتباطات حقوقی.</p>
			</article>
			<article class="darbare-value">
				<div class="darbare-value-icon"><span class="material-symbols-outlined">lightbulb</span></div>
				<h3>نوآوری مستمر</h3>
				<p>بهره‌گیری از جدیدترین فناوری‌ها برای ساده‌سازی فرآیندهای پیچیده حقوقی.</p>
			</article>
		</div>
	</section>
</main>

<?php get_footer(); ?>
