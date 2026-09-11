<?php
/**
 * همگام‌سازی خودکار و زمان‌بندی‌شده از روی یک فایل ثابت یا نشانی اینترنتی.
 *
 * @package parsian-catalog-sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * زمان‌بندی همگام‌سازی.
 */
class PCS_Scheduler {

	const HOOK = 'pcs_scheduled_sync';

	/**
	 * ثبت قلاب‌ها.
	 */
	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
	}

	/**
	 * تنظیم دوبارهٔ زمان‌بندی.
	 *
	 * @param string $schedule off | hourly | twicedaily | daily.
	 */
	public static function reschedule( $schedule ) {
		$timestamp = wp_next_scheduled( self::HOOK );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
		}

		if ( 'off' === $schedule || '' === $schedule ) {
			return;
		}

		wp_schedule_event( time() + MINUTE_IN_SECONDS, $schedule, self::HOOK );
	}

	/**
	 * پاک‌سازی زمان‌بندی هنگام غیرفعال شدن افزونه.
	 */
	public static function clear() {
		$timestamp = wp_next_scheduled( self::HOOK );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
		}
	}

	/**
	 * اجرای همگام‌سازی زمان‌بندی‌شده.
	 */
	public static function run() {
		$settings = PCS_Settings::instance();
		$source   = $settings->get( 'source' );

		if ( '' === $source ) {
			return;
		}

		$path = self::localize( $source );

		if ( is_wp_error( $path ) ) {
			$settings->record_run(
				array(
					'created'   => 0,
					'updated'   => 0,
					'skipped'   => 0,
					'failed'    => 1,
					'missing'   => 0,
					'timestamp' => current_time( 'mysql' ),
					'messages'  => array(
						array(
							'type' => 'error',
							'row'  => 0,
							'sku'  => '',
							'text' => $path->get_error_message(),
						),
					),
				)
			);
			return;
		}

		$plan = PCS_Sync::plan( $path, $settings->get( 'sheet' ) );

		if ( is_wp_error( $plan ) ) {
			$settings->record_run(
				array(
					'created'   => 0,
					'updated'   => 0,
					'skipped'   => 0,
					'failed'    => 1,
					'missing'   => 0,
					'timestamp' => current_time( 'mysql' ),
					'messages'  => array(
						array(
							'type' => 'error',
							'row'  => 0,
							'sku'  => '',
							'text' => $plan->get_error_message(),
						),
					),
				)
			);
		} else {
			PCS_Sync::apply( $plan );
		}

		// فایل موقتِ دانلودشده پس از اجرا پاک می‌شود.
		if ( preg_match( '#^https?://#i', $source ) && file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * تبدیل منبع به مسیر محلی قابل خواندن.
	 *
	 * @param string $source مسیر یا نشانی.
	 * @return string|WP_Error
	 */
	public static function localize( $source ) {
		if ( ! preg_match( '#^https?://#i', $source ) ) {
			return is_readable( $source )
				? $source
				: new WP_Error(
					'pcs_source_missing',
					sprintf(
						/* translators: %s: مسیر فایل. */
						__( 'فایل منبع در مسیر «%s» پیدا نشد.', 'parsian-catalog-sync' ),
						$source
					)
				);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$temp = download_url( $source, 60 );

		if ( is_wp_error( $temp ) ) {
			return $temp;
		}

		// download_url پسوند را نگه نمی‌دارد؛ خواننده به پسوند نیاز دارد.
		$extension = strtolower( pathinfo( wp_parse_url( $source, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		$extension = in_array( $extension, array( 'xlsx', 'xlsm', 'csv', 'txt' ), true ) ? $extension : 'csv';
		$renamed   = $temp . '.' . $extension;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		if ( ! @rename( $temp, $renamed ) ) {
			return $temp;
		}

		return $renamed;
	}
}
