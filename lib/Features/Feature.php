<?php

namespace WPRedisearch\Features;

class Feature {
	/**
	 * Feature slug
	 *
	 * @var string
	 * @since 0.1.2
	 */
	public $slug;

	/**
	 * Feature title
	 *
	 * @var string
	 * @since 0.1.2
	 */
	public $title;

	/**
	 * Optional feature default settings
	 *
	 * @since 0.1.2
	 * @var  array
	 */
	public $default_settings = array();

	/**
	 * Contains registered callback to execute after setup
	 *
	 * @since 0.1.2
	 * @var callback
	 */
	public $setup_cb;

	/**
	 * Callback function to check feature requirements
	 *
	 * @since 0.1.2
	 * @var callback
	 */
	public $requirements_cb;

	/**
	 * Callback function after feature activation
	 *
	 * @since 0.1.2
	 * @var callback
	 */
	public $activation_cb;

	/**
	 * Callback function that outputs HTML for feature description
	 *
	 * @since 0.1.2
	 * @var callback
	 */
	public $feature_desc_cb;

	/**
	 * Callback function that outputs custom feature settings
	 *
	 * @since 0.1.2
	 * @var callback
	 */
	public $feature_settings_cb;

	/**
	 * True if the feature requires content reindexing after activating
	 *
	 * @since 0.1.2
	 * @var bool
	 */
	public $requires_reindex;

	/**
	 * Initiate the feature, setting all relevant instance variables
	 *
	 * @since 0.1.2
	 */
	public function __construct( $args ) {
		foreach ( $args as $key => $value ) {
			$this->$key = $value;
		}

		do_action( 'wp_redisearch_feature_create', $this );
	}

	/**
	 * Returns requirements status of the feature
	 *
	 * @since 0.1.2
	 * @return 
	 */
	public function requirements_status() {
		$status = new \stdClass();
		$status->code = 0;
		$status->message = array();

		if ( ! empty( $this->requirements_cb ) ) {
			$status = call_user_func( $this->requirements_cb, $this );
		}

		return apply_filters( 'wp_redisearch_feature_requirements_status', $status, $this );
  }
  
	/**
	 * Run on every page load for feature to set itself up
	 *
	 * @since 0.1.2
	 */
	public function setup() {
		if ( ! empty( $this->setup_cb ) ) {
			call_user_func( $this->setup_cb, $this );
		}

		do_action( 'wp_redisearch_feature_setup', $this->slug, $this );
	}

	/**
	 * Return feature settings
	 *
	 * @since 0.1.2
	 * @return array|bool
	 */
	public function get_settings() {
    $feature_settings = get_option( 'wp_redisearch_feature_settings', array() );

		return ( ! empty( $feature_settings[ $this->slug ] ) ) ? $feature_settings[ $this->slug ] : false;
	}

	/**
	 * Returns true if feature is active
	 *
	 * @since 0.1.2
	 * @return boolean
	 */
	public function is_active() {
    $feature_settings = get_option( 'wp_redisearch_feature_settings', array() );

		$active = false;

		if ( ! empty( $feature_settings[ $this->slug ] ) && $feature_settings[ $this->slug ]['active'] ) {
			$active = true;
		}

		return apply_filters( 'wp_redisearch_feature_active', $active, $feature_settings, $this );
	}

	/**
	 * Ran after a feature is activated
	 *
	 * @since 0.1.2
	 */
	public function activation() {
		if ( ! empty( $this->activation_cb ) ) {
			call_user_func( $this->activation_cb, $this );
		}

		do_action( 'wp_redisearch_feature_post_activation', $this->slug, $this );
	}

	/**
	 * Outputs feature box
	 *
	 * @since 0.1.2
	 */
	public function output_feature_box() {
		$requirements_status = $this->requirements_status();
		if ( ! empty( $requirements_status->message ) ) {
			$messages = (array) $requirements_status->message;
			foreach ( $messages as $message ) {
				echo '<div class="wprds-feature-notice notice inline notice-error notice-alt">';
					echo wp_kses_post( $message );
				echo '</div>';
			}
		}
		
		$this->output_desc();
		$this->output_settings_box();
	}

	/**
	 * Outputs feature box long description
	 *
	 * @since 0.1.2
	 */
	public function output_desc() {
		if ( ! empty( $this->feature_desc_cb ) ) {
			call_user_func( $this->feature_desc_cb, $this );
		}

		do_action( 'wp_redisearch_feature_box_full', $this->slug, $this );
	}

	public function output_settings_box() {
		$requirements_status = $this->requirements_status();
		?>

		<h3><?php esc_html_e( 'Settings', 'wp-redisearch' ); ?></h3>
		<div class="feature-fields" >
			<div class="field-name status"><?php esc_html_e( 'Status', 'wp-redisearch' ); ?></div>
			<div class="input-wrap <?php if ( 1 === $requirements_status->code ) : ?>disabled<?php endif; ?>">
				<label for="feature_<?php echo esc_attr( $this->slug ); ?>_enabled">
					<input type="radio" name="feature_<?php echo esc_attr( $this->slug ); ?>"
									id="feature_<?php echo esc_attr( $this->slug ); ?>_enabled"
									data-field-name="active"
									class="setting-field" <?php if ( 1 === $requirements_status->code ) : ?>disabled<?php endif; ?>
									<?php if ( $this->is_active() ) : ?>checked<?php endif; ?>
									value="1" /><?php esc_html_e( 'Enabled', 'wp-redisearch' ); ?>
				</label><br>
				<label for="feature_<?php echo esc_attr( $this->slug ); ?>_disabled">
					<input type="radio" name="feature_<?php echo esc_attr( $this->slug ); ?>"
									id="feature_<?php echo esc_attr( $this->slug ); ?>_disabled"
									data-field-name="active"
									class="setting-field" <?php if ( 1 === $requirements_status->code ) : ?>disabled<?php endif; ?>
									<?php if ( !$this->is_active() ) : ?>checked<?php endif; ?>
									value="0" /><?php esc_html_e( 'Disabled', 'wp-redisearch' ); ?>
				</label>
			</div>
		</div>

		<?php
		if ( ! empty( $this->feature_settings_cb ) ) {
			call_user_func( $this->feature_settings_cb, $this );
			return;
		}

		do_action( 'wp_redisearch_feature_box_settings_' . $this->slug, $this );

		?>

		<div class="action-wrap">
			<?php if ( $this->requires_reindex ) : ?>
				<span class="reindex-required">
					<?php esc_html_e('Setting adjustments to this feature require a re-index.', 'wp-redisearch' ); ?>
				</span>
			<?php endif; ?>

			<a class="button button-primary save-settings <?php if ( 1 === $requirements_status->code ): ?>disabled<?php endif; ?>"
					data-feature="<?php echo esc_attr( $this->slug ); ?>">
				<?php esc_html_e( 'Save', 'wp-redisearch' ); ?>
			</a>
		</div>
		<?php
	}
}
