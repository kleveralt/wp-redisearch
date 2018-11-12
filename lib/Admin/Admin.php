<?php

namespace WPRedisearch;

use SevenFields\Fields\Fields;
use SevenFields\Container\Container;

use WPRedisearch\Settings;
use WPRedisearch\Features;
use WPRedisearch\WPRedisearch;
use WPRedisearch\RediSearch\Index;
use WPRedisearch\RediSearch\Setup;

class Admin {

  public function __construct() {
    add_action( 'admin_enqueue_scripts', array( $this, 'wp_redisearch_enqueue_scripts' ) );
    self::init();
  }


  /**
  * Initiate admin.
  * @since    0.1.0
  * @param
  * @return 
  */
  public static function init() {
    add_action( 'admin_menu', array( __CLASS__, 'setting_pages_init' ) );
  }

  public static function setting_pages_init() {
    // Redisearch Dashboard
    Container::make( __( 'Redisearch', 'wp-redisearch' ), 'redisearch' )
    ->set_menu_position( 20 )
    ->set_icon( 'dashicons-search' )
    ->plain_page()
    ->add_fields(array( __CLASS__, 'wp_redisearch_status_page'));
    // Redis server configurations.
    Container::make( __( 'Redis server', 'wp-redisearch' ), 'redis-server')
    ->set_parent('redisearch')
    ->add_fields(array( __CLASS__, 'wp_redisearch_redis_server_conf') );
    // Indexing options and configurations.
    Container::make( __( 'Indexing options', 'wp-redisearch' ), 'indexing-options')
    ->set_parent('redisearch')
    ->add_fields(array( __CLASS__, 'wp_redisearch_indexing_fields') );
  }


  /**
  * Fields for Redis Status option page.
  * @since    0.1.0
  * @param
  * @return object $fields
  */
  public static function wp_redisearch_status_page() {
    Fields::add('html', 'stats', 'Index status', self::index_status_html() );
    $features = Features::init()->features;
    if ( isset( $features ) && !empty( $features ) ) {
      echo '<div class="wprds-wrapper-grid">';
      echo '<div id="normal-sortables" class="meta-box-sortables">';
      foreach ($features as $feature) {
      ?>
        <div class="postbox wprds-feature">
          <div class="wprds-feature-<?php echo esc_attr( $feature->slug ); ?> <?php if ( $feature->is_active() ) : ?>feature-active<?php endif; ?>">
            <h2 class="hndle"><span><?php _e( $feature->title, 'wp-redisearch' ); ?></span></h2>
            <div class="inside">
              <?php $feature->output_feature_box(); ?>
            </div>
          </div>
        </div>
      <?php
      }
      echo '</div></div>';
    }
  }

  public static function index_status_html() {
    $default_args = Settings::query_args();
    $default_args['posts_per_page'] = -1;
    $args = apply_filters( 'wp_redisearch_posts_args', $default_args);

    $query = new \WP_Query( $args );
    $num_posts = $query->found_posts;

    $index_options = __( 'Indexing options:', 'wp-redisearch' );
    $index_btn = __( 'Index posts', 'wp-redisearch' );
    $num_docs = 0;
    if ( isset( WPRedisearch::$indexInfo ) && gettype( WPRedisearch::$indexInfo ) == 'array' ) {
      $num_docs_offset = array_search( 'num_docs', WPRedisearch::$indexInfo ) + 1;
      $num_docs = WPRedisearch::$indexInfo[$num_docs_offset];
    }
    
    $status_html = <<<"EOT"
      <div id="post-body-content">
        <div class="postbox" style="display: block;">
          <div style="padding:20px 20px 0;">
            <p>This is RediSearch status page.</p>
            <p>Whith the current settings, there is <strong>${num_posts}</strong> posts to be indexed.</p>
            <p>Right now, <strong>${num_docs}</strong> posts have been indexed.</p>
            <div class="indexing-options" data-num-posts="${num_posts}" data-num-docs="${num_docs}">
              <span>${index_options}</spam>
              <a class="dashicons indexing-btn start-indexing dashicons-update" title="Dump existing index and re-index."></a>
              <a class="dashicons indexing-btn resume-indexing dashicons-controls-play" title="Resume indexing from where it stoped."></a>
            </div>
          </div>
          <div id="indexingProgress">
            <div id="indexBar" data-num-posts="${num_posts}" data-num-docs="${num_docs}"></div>
            <span id="indexedStat">
            <span id="statNumDoc">${num_docs}</span>/<span id="statNumPosts">${num_posts}</span></span>
          </div>
          <style>
            .indexing-options{margin-top:20px;}
            .indexing-btn{position:relative;cursor: pointer}
            #indexingProgress {position: relative;background:#eee;margin-top:30px;height:20px;width: 100%;}
            #indexBar {width: 1%;max-width:100%;height: 100%;background-color: #0dbcac;transition: all linear 0.1s;}
            span#indexedStat {position: absolute;bottom: 0;right: 4px;line-height:20px;color: #000000;}
          </style>
        </div>
      </div>
      <div class="clear"></div>
EOT;
    return $status_html;
  }

  /**
  * Fields for Redis Server Configuration option page.
  * @since    0.1.0
  * @param
  * @return object $fields
  */
  public static function wp_redisearch_redis_server_conf() {
    Fields::add('header', null, __( 'Redis server configurations', 'wp-redisearch' ));
    Fields::add('text', 'wp_redisearch_server', __( 'Redis server', 'wp-redisearch' ), __( 'Redis server url, usually it is 127.0.0.1', 'wp-redisearch' ) );
    Fields::add( 'text', 'wp_redisearch_port', __( 'Redis port', 'wp-redisearch' ), __( 'Redis port number, by default it is 6379', 'wp-redisearch' ) );
    Fields::add( 'text', 'wp_redisearch_index_name', __( 'Redisearch index name', 'wp-redisearch' ) );
  }

  
  /**
  * Fields for indexable stuff options page.
  * @since    0.1.0
  * @param
  * @return object $fields
  */
  public static function wp_redisearch_indexing_fields() {
    Fields::add( 'header', null, __( 'General indexing settings', 'wp-redisearch' ) );
    Fields::add( 'text', 'wp_redisearch_indexing_batches',  __( 'Posts will be indexed in baches of:', 'wp-redisearch' ) );
    Fields::add( 'header', null, __( 'Persist index after server restart.', 'wp-redisearch' ), __( 'Redisearch is in-memory database, which means after server restart (for any reason), all data in the redis database will be lost. But redis also can write to the disk.', 'wp-redisearch' ) );
    Fields::add( 'checkbox', 'wp_redisearch_write_to_disk', __( 'Write redis data to the disk', 'wp-redisearch' ), __( 'If enabled, after indexing manualy in redisearch dashboard or adding new post to the site, entire redisearch index will be written to the disk and after server restart, you won\'t loos any data', 'wp-redisearch') );
    Fields::add( 'header', null, 'What to be indexed' );
    Fields::add( 'multiselect', 'wp_redisearch_post_types',  __( 'Post types', 'wp-redisearch' ), __( 'Post types to be indexed', 'wp-redisearch' ), self::post_types() );
    Fields::add( 'multiselect', 'wp_redisearch_indexable_terms',  __( 'Taxonomies', 'wp-redisearch' ), __( 'Post tag, category and custom taxonomies to be indexed', 'wp-redisearch' ), self::get_terms() );
    // In case we need to extend option fields.
    do_action( 'wp_redisearch_settings_indexing_fields' );    
  }

  /**
  * List of all post types.
  * @since    0.1.0
  * @param integer $post
  * @return string
  */
  private static function post_types() {
    $post_types = get_post_types(
      array(
        'public' => true,
        'exclude_from_search' => false,
        'show_ui' => true,
      )
    );
    /**
     * We exclude product from indexable post types, since woocommerce support added via Features.
     * 
     * @since 0.2.1
     */
    if ( function_exists( 'array_diff' ) ) {
      $post_types = array_diff( $post_types, array( 'product' ) );
    }
    return $post_types;
  }

  /**
  * Get all terms.
  * @since    0.1.0
  * @param integer $post
  * @return array $terms
  */
  private static function get_terms() {
    $post_types = self::post_types();
    $indexable_taxonomies = array();

    foreach ( $post_types as $post_type ) {
      $taxonomies = get_object_taxonomies( $post_type, 'objects' );
      foreach ( $taxonomies as $taxonomy ) {
        if ( $taxonomy->public || $taxonomy->publicly_queryable ) {
          $indexable_taxonomies[] = $taxonomy->name;
        }
      }
    }

		return $indexable_taxonomies;
  }
  
  /**
  * Enqueue admin scripts.
  * @since    0.1.0
  * @param
  * @return
  */
  public function wp_redisearch_enqueue_scripts() {
    wp_enqueue_script( 'wp_redisearch_admin_js', WPRS_URL . 'lib/admin/js/admin.js', array( 'jquery' ), WPRS_VERSION, true );
    $localized_data = array(
      'ajaxUrl' 				=> admin_url( 'admin-ajax.php' ),
      'nonce'           => wp_create_nonce( 'wprds_dashboard_nonce' )
		);
    wp_localize_script( 'wp_redisearch_admin_js', 'wpRds', $localized_data );
    wp_enqueue_style( 'wp_redisearch_admin_styles', WPRS_URL . 'lib/admin/css/admin.css', false, WPRS_VERSION );
  }

  /**
  * action for "index it" ajax call to start indexing selected posts.
  * @since    0.1.0
  * @param
  * @return
  */
  public static function wp_redisearch_add_to_index() {
    $index = new Index( WPRedisearch::$client );
    $results = $index->create()->add();
    wp_send_json_success( $results );
  }

  /**
  * Write to disk to persist data.
  * @since    0.1.0
  * @param
  * @return
  */
  public static function wp_redisearch_write_to_disk() {
    if ( Settings::get( 'wp_redisearch_write_to_disk' ) ) {
      $index = new Index( WPRedisearch::$client );
      $result = $index->writeToDisk();
      wp_send_json_success( $result );
    }
  }

  /**
  * action for "index it" ajax call to start indexing selected posts.
  * @since    0.1.0
  * @param
  * @return
  */
  public function wp_redisearch_index_post_on_publish( $post_id, $post, $update ) {
    // If this is a revision, of it is auto save, don't do anything.
    if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) )
      return;

    $index = new Index( WPRedisearch::$client );
    $index_name = Settings::indexName();

    // If post is not published or un-published, delete from index then, return.
    if ( $post->post_status != 'publish' ) {
      $index->deletePosts( $index_name, $post_id );

      /**
       * Filter wp_redisearch_after_post_deleted fires after post deleted from the index.
       * 
       * @since 0.2.0
       * @param array $index_name         Index name
       * @param array $post               The post object
       */
      do_action( 'wp_redisearch_after_post_deleted', $index_name, $post );
      
      
      // If enabled, write to disk
      if ( Settings::get( 'wp_redisearch_write_to_disk' ) ) {
        $index = new Index( WPRedisearch::$client );
        $index->writeToDisk();
      }
      return;
    }
    
    $content = wp_strip_all_tags( $post->post_content, true );
    $permalink = get_permalink( $post_id );
    $user = get_userdata( $post->post_author );

		if ( $user instanceof WP_User ) {
			$user_data = $user->display_name;
		} else {
			$user_data = '';
    }
    
		$post_date = $post->post_date;
    $post_modified = $post->post_modified;
       // If date is invalid, set it to null
		if ( ! strtotime( $post_date ) || $post_date === "0000-00-00 00:00:00" ) {
			$post_date = null;
		}

    $indexing_options['language'] = apply_filters( 'wp_redisearch_index_language', 'english', $post_id );
    $indexing_options['fields'] = $index->prepare_post( $post_id );
    $indexing_options['extra_params'] = array( 'REPLACE' );
    // Finally, add post to index
    $index->addPosts( $index_name, $post_id, $indexing_options );

    /**
     * Filter wp_redisearch_after_post_indexed fires after post added to the index.
     * 
     * @since 0.2.0
     * @param array $index_name         Index name
     * @param array $post               The post object
     * @param array $indexing_options   Posts extra options like language and fields
     */
    do_action( 'wp_redisearch_after_post_published', $index_name, $post, $indexing_options );
    
    // If enabled, write to disk
    if ( Settings::get( 'wp_redisearch_write_to_disk' ) ) {
      $index = new Index( WPRedisearch::$client );
      $index->writeToDisk();
    }
  }

  /**
  * Drop existing index.
  * @since    0.1.0
  * @param
  * @return
  */
  public static function wp_redisearch_drop_index() {
    $index = new Index( WPRedisearch::$client );
    $results = $index->drop();
    wp_send_json_success( $results );
  }

}