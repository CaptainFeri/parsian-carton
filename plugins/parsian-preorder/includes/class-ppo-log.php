<?php
/**
 * تاریخچهٔ هر درخواست.
 *
 * مثل «یادداشت‌های سفارش» ووکامرس، رویدادها به‌صورت دیدگاه ذخیره می‌شوند: هر
 * تغییر وضعیت، هر پیامک، هر واگذاری و هر یادداشت دستی یک سطر است. این کار
 * مسئولیت‌پذیری می‌آورد — همیشه معلوم است چه کسی، کِی، چه کرد.
 *
 * @package parsian-preorder
 */

defined( 'ABSPATH' ) || exit;

/**
 * یادداشت و رویداد.
 */
class PPO_Log {

	/**
	 * نوع دیدگاه.
	 */
	const TYPE = 'ppo_note';

	/**
	 * افزودن یک رویداد.
	 *
	 * @param int    $request_id شناسهٔ درخواست.
	 * @param string $text       متن.
	 * @param string $kind       system | note | sms | status | order.
	 * @param int    $user_id    نویسنده (۰ = سامانه).
	 * @return int شناسهٔ دیدگاه.
	 */
	public static function add( $request_id, $text, $kind = 'system', $user_id = null ) {
		$request_id = (int) $request_id;
		$text       = trim( wp_strip_all_tags( (string) $text ) );

		if ( ! $request_id || '' === $text ) {
			return 0;
		}

		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$user   = $user_id ? get_userdata( $user_id ) : false;
		$author = $user ? $user->display_name : __( 'سامانه', 'parsian-preorder' );

		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'  => $request_id,
				'comment_author'   => $author,
				'comment_content'  => $text,
				'comment_type'     => self::TYPE,
				'comment_approved' => 1,
				'user_id'          => (int) $user_id,
				'comment_agent'    => 'ParsianPreorder',
			)
		);

		if ( $comment_id ) {
			add_comment_meta( $comment_id, '_ppo_kind', sanitize_key( $kind ) );
		}

		return (int) $comment_id;
	}

	/**
	 * خواندن تاریخچهٔ یک درخواست — تازه‌ترین بالا.
	 *
	 * @param int $request_id شناسهٔ درخواست.
	 * @return array[]
	 */
	public static function get( $request_id ) {
		// فیلتر پنهان‌سازی موقتاً برداشته می‌شود، وگرنه همین پرس‌وجو هم خالی برمی‌گردد.
		remove_filter( 'comments_clauses', array( __CLASS__, 'hide_from_comments' ) );

		$comments = get_comments(
			array(
				'post_id' => (int) $request_id,
				'type'    => self::TYPE,
				'orderby' => 'comment_date_gmt',
				'order'   => 'DESC',
				'status'  => 'approve',
			)
		);

		add_filter( 'comments_clauses', array( __CLASS__, 'hide_from_comments' ) );

		$entries = array();

		foreach ( $comments as $comment ) {
			$entries[] = array(
				'id'     => (int) $comment->comment_ID,
				'text'   => $comment->comment_content,
				'author' => $comment->comment_author,
				'date'   => $comment->comment_date,
				'kind'   => (string) get_comment_meta( $comment->comment_ID, '_ppo_kind', true ),
			);
		}

		return $entries;
	}

	/**
	 * پنهان کردن یادداشت‌های پیش‌فروش از فهرست دیدگاه‌های وردپرس.
	 *
	 * @param array $clauses بندهای پرس‌وجو.
	 * @return array
	 */
	public static function hide_from_comments( $clauses ) {
		global $wpdb;

		$clauses['where'] .= ( '' !== trim( (string) $clauses['where'] ) ? ' AND ' : '' )
			. $wpdb->prepare( "{$wpdb->comments}.comment_type != %s", self::TYPE );

		return $clauses;
	}

}

add_filter( 'comments_clauses', array( 'PPO_Log', 'hide_from_comments' ) );
