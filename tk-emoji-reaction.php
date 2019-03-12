<?php
/**
 * Plugin Name:     Trew Knowledge - Comment Reaction Emojis
 * Plugin URI:      https://trewknowledge.com
 * Description:     Enables a series of emojis to be used as reactions to comments.
 * Author:          Trew Knowledge
 * Author URI:      https://trewknowledge.com
 * Text Domain:     tk-emoji-reaction
 * Domain Path:     /languages
 * Version:         1.0.0
 *
 * @package         Tk_Emoji_Reaction
 */

require 'vendor/autoload.php';

use Emojione\Emojione;
use Emojione\Client as EmojioneClient;

define( 'TK_EMOJI_REACTION_VERSION', '1.0.0' );

add_action( 'wp_enqueue_scripts', function() {
	wp_enqueue_style( 'tk-emoji-reaction-emoji-css', plugin_dir_url( __FILE__ ) . 'assets/css/emojionearea.min.css', array(), TK_EMOJI_REACTION_VERSION );
	wp_enqueue_script( 'tk-emoji-reaction-emoji-js', plugin_dir_url( __FILE__ ) . 'assets/js/emojionearea.min.js', array(), TK_EMOJI_REACTION_VERSION, true );

	wp_enqueue_style( 'tk-emoji-reaction-public-css', plugin_dir_url( __FILE__ ) . 'assets/css/public.css', array(), TK_EMOJI_REACTION_VERSION );
	wp_enqueue_script( 'tk-emoji-reaction-public-js', plugin_dir_url( __FILE__ ) . 'assets/js/public.js', array( 'jquery', 'tk-emoji-reaction-emoji-js' ), TK_EMOJI_REACTION_VERSION, true );

	wp_localize_script( 'tk-emoji-reaction-public-js', 'TKER', array(
		'ajaxurl' => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'tk_save_reaction_nonce' ),
		'logged_in' => is_user_logged_in(),
	) );
});


add_filter( 'comment_reply_link', function( $link, $args, $comment, $post ) {
	$new_html = '';

	// Load emoji instead of shortname.
	$emojioneClient = new EmojioneClient();
	Emojione::setClient( $emojioneClient );

	$reactions      = get_comment_meta( $comment->comment_ID, 'tker_reactions', true );
	$reactions      = $reactions ?: array();
	$user_reactions = get_user_meta( get_current_user_id(), "tker_comment_id_{$comment->comment_ID}", true );
	$user_reactions = $user_reactions ?: array();
	ob_start();
	?>
	<div class="comment-reactions" data-comment-id="<?php echo esc_attr( $comment->comment_ID ); ?>">
		<div class="reaction template" style="display: none;">
			<button type="button">
				<span class="count"></span>
			</button>
		</div>
		<div class="tker-reaction-picker"></div>
		<?php if ( ! empty( $reactions ) ): ?>
			<?php $emoji_kses = array(
				'img' => array(
					'src' => true,
					'class' => true,
					'title' => true,
				),
			); ?>
			<?php foreach ( $reactions as $emoji => $count ): ?>
				<?php
					$active = '';
					if ( in_array( $emoji, $user_reactions, true ) ) {
						$active = 'tker_active';
					}
				?>
				<div class="reaction <?php echo esc_attr( $active ); ?>" data-emoji="<?php echo esc_attr( $emoji ); ?>">
					<button type="button">
						<?php echo wp_kses( Emojione::shortnameToImage( $emoji ), $emoji_kses ) ; ?>
						<span class="count"><?php echo esc_html( $count ); ?></span>
					</button>
				</div>
			<?php endforeach ?>
		<?php endif ?>
	</div>
	<?php
	$new_html = ob_get_clean();
	$new_html = $link . $new_html;
	return $new_html;
}, 10, 4 );

function tker_comment_callback( $comment, $args, $depth ) {
	// Load emoji instead of shortname.
	$emojioneClient = new EmojioneClient();
	Emojione::setClient( $emojioneClient );

	$reactions      = get_comment_meta( $comment->comment_ID, 'tker_reactions', true );
	$reactions      = $reactions ?: array();
	$user_reactions = get_user_meta( get_current_user_id(), "tker_comment_id_{$comment->comment_ID}", true );
	$user_reactions = $user_reactions ?: array();
	?>
	<div class="comment-reactions" data-comment-id="<?php echo esc_attr( $comment->comment_ID ); ?>">
		<div class="reaction template" style="display: none;">
			<button type="button">
				<span class="count"></span>
			</button>
		</div>
		<div class="tker-reaction-picker"></div>
		<?php if ( ! empty( $reactions ) ): ?>
			<?php $emoji_kses = array(
				'img' => array(
					'src' => true,
					'class' => true,
					'title' => true,
				),
			); ?>
			<?php foreach ( $reactions as $emoji => $count ): ?>
				<?php
					$active = '';
					if ( in_array( $emoji, $user_reactions, true ) ) {
						$active = 'tker_active';
					}
				?>
				<div class="reaction <?php echo esc_attr( $active ); ?>" data-emoji="<?php echo esc_attr( $emoji ); ?>">
					<button type="button">
						<?php echo wp_kses( Emojione::shortnameToImage( $emoji ), $emoji_kses ) ; ?>
						<span class="count"><?php echo esc_html( $count ); ?></span>
					</button>
				</div>
			<?php endforeach ?>
		<?php endif ?>
	</div>
	<?php
}

add_action( 'wp_ajax_tk_emoji_reaction_save', function() {
	error_log(print_r($_POST, true));
	if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'tk_save_reaction_nonce' ) ) {
		wp_send_json_error( 'Nonce failed' );
	}
	if ( empty( $_POST['comment_id'] ) ) {
		wp_send_json_error( 'Comment ID is required.' );
	}
	if ( empty( $_POST['emoji'] ) ) {
		wp_send_json_error( 'A reaction is required.' );
	}

	$user_id           = get_current_user_id();
	$comment_id        = absint( $_POST['comment_id'] );
	$emoji             = sanitize_text_field( wp_unslash( $_POST['emoji'] ) );
	$user_reacted      = false;
	$user_reactions    = get_user_meta( $user_id, "tker_comment_id_{$comment_id}", true );
	$user_reactions    = $user_reactions ?: array();
	$comment_reactions = get_comment_meta( $comment_id, "tker_reactions", true );
	$comment_reactions = $comment_reactions ?: array();

	if ( in_array( $emoji, $user_reactions, true ) ) {
		$user_reacted = true;
	}

	if ( $user_reacted ) {
		// Update User Meta.
		$index = array_search( $emoji, $user_reactions, true );
		unset( $user_reactions[ $index ] );
		update_user_meta( $user_id, "tker_comment_id_{$comment_id}", $user_reactions );

		// Update Comment Meta.
		$count = $comment_reactions[ $emoji ];
		$comment_reactions[ $emoji ] = --$count;
		if ( $count === 0 ) {
			unset( $comment_reactions[ $emoji ] );
		}
		update_comment_meta( $comment_id, 'tker_reactions', $comment_reactions );
		wp_send_json_success( false );
	} else {
		// Update User Meta.
		$user_reactions[] = $emoji;
		update_user_meta( $user_id, "tker_comment_id_{$comment_id}", $user_reactions );

		// Update Comment Meta.
		$count = 0;
		if ( isset( $comment_reactions[ $emoji ] ) ) {
			$count = $comment_reactions[ $emoji ];
		}
		$comment_reactions[ $emoji ] = ++$count;
		update_comment_meta( $comment_id, 'tker_reactions', $comment_reactions );
		wp_send_json_success( true );
	}

	wp_send_json_error( 'Something went wrong.' );
} );
