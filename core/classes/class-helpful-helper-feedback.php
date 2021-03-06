<?php
/**
 * Helper for receiving stored feedback, feedback informations and
 * user avatars.
 *
 * @package Helpful
 * @author  Pixelbart <me@pixelbart.de>
 */

/* Prevent direct access */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Helpful_Helper_Feedback
{
	/**
	 * Get feedback data by post object.
	 *
	 * @param object $entry post object.
	 *
	 * @return string json
	 */
	public static function getFeedback( $entry )
	{
		$post = get_post( $entry->post_id );
		$time = strtotime( $entry->time );

		$feedback            = [];
		$feedback['id']      = $entry->id;
		$feedback['name']    = __( 'Anonymous', 'helpful' );
		$feedback['message'] = nl2br( $entry->message );
		$feedback['pro']     = $entry->pro;
		$feedback['contra']  = $entry->contra;
		$feedback['post']    = $post;
		$feedback['time']    = sprintf(
			/* translators: %s = time difference */
			__( 'Submitted %s ago', 'helpful' ),
			human_time_diff( $time, current_time( 'timestamp' ) )
		);

		if ( $entry->fields ) {
			$fields = [];
			$items  = maybe_unserialize( $entry->fields );
			if ( is_array( $items ) ) {
				foreach ( $items as $label => $value ) {
					$feedback['fields'][ $label ] = $value;
				}
			}
		}

		$feedback['avatar'] = self::getAvatar();

		if ( isset( $feedback['fields']['email'] ) && '' !== $feedback['fields']['email'] ) {
			$feedback['avatar'] = self::getAvatar( $feedback['fields']['email'] );
		}

		if ( isset( $feedback['fields']['name'] ) && '' !== $feedback['fields']['name'] ) {
			$feedback['name'] = $feedback['fields']['name'];
		}

		$feedback = apply_filters( 'helpful_admin_feedback_item', $feedback, $entry );

		return json_decode( wp_json_encode( $feedback ) );
	}

	/**
	 * Get avatar or default helpful avatar by email.
	 *
	 * @param string  $email user email.
	 * @param integer $size  image size.
	 *
	 * @return string
	 */
	public static function getAvatar( $email = null, $size = 55 )
	{
		$default = plugins_url( 'core/assets/images/avatar.jpg', HELPFUL_FILE );

		if ( get_option( 'helpful_feedback_gravatar' ) ) {
			if ( ! is_null( $email ) ) {
				return get_avatar( $email, $size, $default );
			}
		}

		$html = '<img src="%1$s" height="%2$s" width="%2$s" alt="no avatar">';
		$html = apply_filters( 'helpful_feedback_noavatar', $html );

		return sprintf( $html, $default, $size );
	}

	/**
	 * Get feedback items.
	 *
	 * @global $wpdb
	 *
	 * @param integer $limit posts per page.
	 *
	 * @return object
	 */
	public static function getFeedbackItems( $limit = null )
	{
		if ( is_null( $limit ) ) {
			$limit = absint( get_option( 'helpful_widget_amount' ) );
		}

		global $wpdb;

		$helpful = $wpdb->prefix . 'helpful_feedback';

		$query   = "SELECT * FROM $helpful ORDER BY time DESC LIMIT %d";
		$query   = $wpdb->prepare( $query, $limit );
		$results = $wpdb->get_results( $query );

		if ( $results ) {
			return $results;
		}

		return false;
	}

	/**
	 * Insert feedback into database.
	 *
	 * @global $wpdb
	 *
	 * @return integer
	 */
	public static function insertFeedback()
	{
		global $wpdb;

		$fields  = [];
		$pro     = 0;
		$contra  = 0;
		$message = null;

		if ( ! isset( $_REQUEST['post_id'] ) ) {
			$message = 'Helpful Notice: Feedback was not saved because the post id is empty in %s on line %d.';
			helpful_error_log( sprintf( $message, __FILE__, __LINE__ ) );
			return null;
		}

		$post_id = absint( sanitize_text_field( wp_unslash( $_REQUEST['post_id'] ) ) );

		if ( ! isset( $_REQUEST['message'] ) ) {
			$message = 'Helpful Notice: Feedback was not saved because the message is empty in %s on line %d.';
			helpful_error_log( sprintf( $message, __FILE__, __LINE__ ) );
			return null;
		}

		$message = trim( $_REQUEST['message'] );

		if ( '' === $message ) {
			$message = 'Helpful Notice: Feedback was not saved because the message is empty in %s on line %d.';
			helpful_error_log( sprintf( $message, __FILE__, __LINE__ ) );
			return null;
		}

		if ( helpful_backlist_check( $_REQUEST['message'] ) ) {
			$message = 'Helpful Notice: Feedback was not saved because the message contains blacklisted words in %s on line %d.';
			helpful_error_log( sprintf( $message, __FILE__, __LINE__ ) );
			return null;
		}

		if ( isset( $_REQUEST['fields'] ) ) {
			foreach ( $_REQUEST['fields'] as $key => $value ) {
				$fields[ $key ] = sanitize_text_field( $value );
			}

			$session = [];

			if ( isset( $_REQUEST['session'] ) ) {
				$session = $_REQUEST['session'];
			}

			$fields = apply_filters( 'helpful_feedback_submit_fields', $fields, $session );
		}

		if ( is_user_logged_in() ) {
			$user   = wp_get_current_user();
			$fields = [];

			$fields['name']  = $user->display_name;
			$fields['email'] = $user->user_email;

			$fields = apply_filters( 'helpful_feedback_submit_fields', $fields );
		}

		if ( isset( $_REQUEST['message'] ) ) {
			$message = sanitize_textarea_field( wp_strip_all_tags( wp_unslash( $_REQUEST['message'] ) ) );
			$message = stripslashes( $message );
			$message = apply_filters( 'helpful_feedback_submit_message', $message );
		}

		if ( isset( $_REQUEST['type'] ) ) {
			$type = sanitize_text_field( wp_unslash( $_REQUEST['type'] ) );

			if ( 'pro' === $type ) {
				$pro = 1;
			} elseif ( 'contra' === $type ) {
				$contra = 1;
			}
		}

		$data = [
			'time'    => current_time( 'mysql' ),
			'user'    => esc_attr( $_REQUEST['user_id'] ),
			'pro'     => $pro,
			'contra'  => $contra,
			'post_id' => $post_id,
			'message' => $message,
			'fields'  => maybe_serialize( $fields ),
		];

		/* send email */
		self::send_email( $data );

		$table_name = $wpdb->prefix . 'helpful_feedback';
		$wpdb->insert( $table_name, $data );
		return $wpdb->insert_id;
	}

	/**
	 * Send feedback email.
	 *
	 * @param array $feedback feedback data.
	 *
	 * @return void
	 */
	public static function send_email( $feedback )
	{
		if ( 'on' !== get_option( 'helpful_feedback_send_email' ) ) {
			return;
		}

		$post = get_post( $feedback['post_id'] );

		if ( ! $post ) {
			return;
		}

		/* email subject */
		$subject = get_option( 'helpful_feedback_subject' );

		/* unserialize feedback fields */
		$feedback['fields'] = maybe_unserialize( $feedback['fields'] );

		$type = esc_html__( 'positive', 'helpful' );
		if ( 1 === $feedback['contra'] ) { 
			$type = esc_html__( 'negative', 'helpful' );
		}

		/* body tags */
		$tags = [
			'{type}'       => $type,
			'{name}'       => $feedback['fields']['name'],
			'{email}'      => $feedback['fields']['email'],
			'{message}'    => $feedback['message'],
			'{post_url}'   => get_permalink( $post ),
			'{post_title}' => $post->post_title,
			'{blog_name}'  => get_bloginfo( 'name' ),
			'{blog_url}'   => site_url(),
		];

		$tags = apply_filters( 'helpful_feedback_email_tags', $tags );
		$body = get_option( 'helpful_feedback_email_content' );
		$body = str_replace( array_keys( $tags ), array_values( $tags ), $body );

		/* receivers by post meta */
		$post_receivers = [];

		if ( get_post_meta( $post->ID, 'helpful_feedback_receivers', true ) ) {
			$post_receivers = get_post_meta( $post->ID, 'helpful_feedback_receivers', true );
			$post_receivers = helpful_trim_all( $post_receivers );
			$post_receivers = explode( ',', $post_receivers );
		}

		/* receivers by helpful options */
		$helpful_receivers = [];

		if ( get_option( 'helpful_feedback_receivers' ) ) {
			$helpful_receivers = get_option( 'helpful_feedback_receivers' );
			$helpful_receivers = helpful_trim_all( $helpful_receivers );
			$helpful_receivers = explode( ',', $helpful_receivers );
		}

		$receivers = array_merge( $helpful_receivers, $post_receivers );
		$receivers = array_unique( $receivers );

		/* receivers array is empty */
		if ( empty( $receivers ) ) {
			return;
		}

		/* email headers */
		$headers   = [];		
		$headers[] = 'Content-Type: text/html; charset=UTF-8';

		if ( $feedback['fields']['email'] ) {
			$headers[] = sprintf( 'Reply-To: %s', $feedback['fields']['email'] );
		}

		/* filters */
		$receivers = apply_filters( 'helpful_feedback_email_receivers', $receivers, $feedback );
		$subject   = apply_filters( 'helpful_feedback_email_subject', $subject, $feedback );
		$body      = apply_filters( 'helpful_feedback_email_body', $body, $feedback );
		$headers   = apply_filters( 'helpful_feedback_email_headers', $headers, $feedback );

		$response = wp_mail( $receivers, $subject, $body, $headers );

		if ( false === $response ) {
			$message = 'Helpful Warning: Email could not be sent in %s on line %d.';
			helpful_error_log( sprintf( $message, __FILE__, __LINE__ ) );
		}
	}

	/**
	 * Outputs the amount of feedback for a post.
	 *
	 * @global $wpdb
	 *
	 * @param int|null $post_id
	 *
	 * @return int
	 */
	public static function get_feedback_count( $post_id = null )
	{
		global $wpdb;

		$helpful = $wpdb->prefix . 'helpful_feedback';

		if ( null === $post_id || ! is_numeric( $post_id ) ) {
			$sql = "SELECT COUNT(*) FROM $helpful";

			return $wpdb->get_var( $sql );
		}

		$post_id = intval( $post_id );
		$sql     = "SELECT COUNT(*) FROM $helpful WHERE post_id = %d";

		return $wpdb->get_var( $wpdb->prepare( $sql, $post_id ) );
	}

	

	/**
	 * Render after messages or feedback form, after vote.
	 * Checks if custom template exists.
	 *
	 * @param integer $post_id post id.
	 * @param bool $show_feedback show feedback form anyway.
	 *
	 * @return string
	 */
	public static function after_vote( $post_id, $show_feedback = false )
	{
		$feedback_text = esc_html_x(
			'Thank you very much. Please write us your opinion, so that we can improve ourselves.',
			'form user note',
			'helpful'
		);

		$hide_feedback = get_post_meta( $post_id, 'helpful_hide_feedback_on_post', true );
		$hide_feedback = ( 'on' === $hide_feedback ) ? true : false;

		$user_id = Helpful_Helper_Values::getUser();
		$type    = Helpful_Helper_Values::get_user_vote_status( $user_id, $post_id );

		if ( 'pro' === $type ) {
			$feedback_text = get_option( 'helpful_feedback_message_pro' );

			if ( true !== $show_feedback ) {
				if ( ! get_option( 'helpful_feedback_after_pro' ) || false !== $hide_feedback ) {
					return do_shortcode( get_option( 'helpful_after_pro' ) );
				}
			}
		}

		if ( 'contra' === $type ) {
			$feedback_text = get_option( 'helpful_feedback_message_contra' );

			if ( true !== $show_feedback ) {
				if ( ! get_option( 'helpful_feedback_after_contra' ) || false !== $hide_feedback ) {
					return do_shortcode( get_option( 'helpful_after_contra' ) );
				}
			}
		}

		if ( false !== $show_feedback ) {
			$feedback_text = get_option( 'helpful_feedback_message_voted' );
		}

		if ( '' === trim( $feedback_text ) ) {
			$feedback_text = false;
		}

		ob_start();

		$default_template = HELPFUL_PATH . 'templates/feedback.php';
		$custom_template  = locate_template( 'helpful/feedback.php' );

		do_action( 'helpful_before_feedback_form' );

		echo '<form class="helpful-feedback-form">';

		printf( '<input type="hidden" name="user_id" value="%s">', $user_id );
		printf( '<input type="hidden" name="action" value="%s">', 'helpful_save_feedback' );
		printf( '<input type="hidden" name="post_id" value="%s">', $post_id );
		printf( '<input type="hidden" name="type" value="%s">', $type );
		
		/**
		 * Simple Spam Protection
		 */
		$spam_protection = apply_filters( 'helpful_simple_spam_protection', true );

		if ( ! is_bool( $spam_protection ) ) {
			$spam_protection = true;
		}

		if ( true === $spam_protection ) {
			echo '<input type="text" name="website" id="website" style="display:none;">';
		}
		
		wp_nonce_field( 'helpful_feedback_nonce' );

		if ( '' !== $custom_template ) {
			include $custom_template;
		} else {
			include $default_template;
		}

		echo '</form>';

		do_action( 'helpful_after_feedback_form' );

		$content = ob_get_contents();
		ob_end_clean();

		if ( false !== $show_feedback ) {
			$content = '<div class="helpful helpful-prevent-form"><div class="helpful-content" role="alert">' . $content . '</div></div>';
		}

		return $content;
	}
}
