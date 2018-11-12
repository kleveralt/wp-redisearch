<?php

namespace WPRedisearch\RediSearch;

use WPRedisearch\WPRedisearch;
use WPRedisearch\Settings;
use WPRedisearch\Features;
use WPRedisearch\RediSearch\Setup;
use WPRedisearch\RedisRaw\PredisAdapter;

class Index {

	/**
	 * @param object $client
	 */
  public $client;

	/**
	 * @param object $index
	 */
  private $index;

  public function __construct( $client ) {
    $this->client = $client;
  }

  /**
  * Drop existing index.
  * @since    0.1.0
  * @param
  * @return
  */
  public function drop() {
    // First of all, we reset saved index_meta from optinos
    delete_option( 'wp_redisearch_index_meta' );

    $index_name = Settings::indexName();
    return $this->client->rawCommand('FT.DROP', [$index_name]);
  }

  /**
  * Create connection to redis server.
  * @since    0.1.0
  * @param
  * @return
  */
  public function create() {
    // First of all, we reset saved index_meta from optinos
    $num_docs = 0;
    if ( isset( WPRedisearch::$indexInfo ) && gettype( WPRedisearch::$indexInfo ) == 'array' ) {
      $num_docs_offset = array_search( 'num_docs', WPRedisearch::$indexInfo ) + 1;
      $num_docs = WPRedisearch::$indexInfo[$num_docs_offset];
    }
    if ( $num_docs == 0 ) {
      delete_option( 'wp_redisearch_index_meta' );
    }

    $index_name = Settings::indexName();

    $title_schema = ['post_title', 'TEXT', 'WEIGHT', 5.0, 'SORTABLE'];
    $content_schema = ['post_content', 'TEXT'];
    $content_filtered_schema = ['post_content_filtered', 'TEXT'];
    $excerpt_schema = ['post_excerpt', 'TEXT'];
    $post_type_schema = ['post_type', 'TEXT'];
    $author_schema = ['post_author', 'TEXT'];
    $id_schema = ['post_id', 'NUMERIC', 'SORTABLE'];
    $menu_order_schema = ['menu_order', 'NUMERIC'];
    $permalink_schema = ['permalink', 'TEXT'];
    $date_schema = ['post_date', 'NUMERIC', 'SORTABLE'];

		/**
		 * Filter index-able post meta
		 * Allows for specifying public or private meta keys that may be indexed.
		 * @since 0.2.0
		 * @param array Array 
		 */
    $indexable_meta_keys = apply_filters( 'wp_redisearch_indexable_meta_keys', array() );

    $meta_schema = array();
    
    if ( isset( $indexable_meta_keys ) && !empty( $indexable_meta_keys ) ) {
      foreach ($indexable_meta_keys as $meta) {
        $meta_schema[] = array( $meta, 'TEXT' );
      }
    }
    /**
     * Filter index-able post meta schema
     * Allows for manipulating schema of public or private meta keys.
     * @since 0.2.0
     * @param array $meta_schema            Array of index-able meta key schemas.
     * @param array $indexable_meta_keys    Array of index-able meta keys.
		 */
    $meta_schema = apply_filters( 'wp_redisearch_indexable_meta_schema', $meta_schema, $indexable_meta_keys );

    $indexable_terms = array_keys( Settings::get( 'wp_redisearch_indexable_terms', array() ) );
    $terms_schema = array();
    if ( isset( $indexable_terms ) && !empty( $indexable_terms ) ) {
      foreach ($indexable_terms as $term) {
        $terms_schema[] = [$term, 'TAG'];
      }
    }
    $schema = array_merge( [$index_name, 'SCHEMA'], $title_schema, $content_schema, $content_filtered_schema, $excerpt_schema, $post_type_schema, $author_schema, $id_schema, $menu_order_schema, $permalink_schema, $date_schema, ...$terms_schema, ...$meta_schema );

    $this->index = $this->client->rawCommand('FT.CREATE', $schema);

    /**
     * Action wp_redisearch_after_index_created fires after index created.
     * Some features need to do something after activation. Some of them trigger re-indexing. 
     * But after they do what they suppose to do with the index, the index will be deleted to re-index the site.
     * So those features can use this filter instead.
     * 
     * @since 0.2.0
     * @param array $client       Created redis client instance
		 */
    do_action( 'wp_redisearch_after_index_created', $this->client);

    return $this;
  }

  /**
  * Prepare items (posts) to be indexed.
  * @since    0.1.0
  * @param
  * @return object $this
  */
  public function add() {
    $index_meta = get_option( 'wp_redisearch_index_meta' );
    if ( empty( $index_meta ) ) {
      $index_meta['offset'] = 0;
    }
    $posts_per_page = apply_filters( 'wp_redisearch_posts_per_page', Settings::get( 'wp_redisearch_indexing_batches', 20 ) );

    $default_args = Settings::query_args();
    $default_args['posts_per_page'] = $posts_per_page;
    $default_args['offset'] = $index_meta['offset'];

    $args = apply_filters( 'wp_redisearch_posts_args', $default_args);

    $query = new \WP_Query( $args );
    $index_meta['found_posts'] = $query->found_posts;

    if ( $index_meta['offset'] >= $index_meta['found_posts'] ) {
      $index_meta['offset'] = $index_meta['found_posts'];
    }
    
    if ( $query->have_posts() ) {
      $index_name = Settings::indexName();
      
      while ( $query->have_posts() ) {
        $query->the_post();
        $indexing_options = array();

        $title = get_the_title();
        $permalink = get_permalink();
        $content = wp_strip_all_tags( get_the_content(), true );
        $id = get_the_id();
        // Post language. This could be useful to do some stop word, stemming and etc.
        $indexing_options['language'] = apply_filters( 'wp_redisearch_index_language', 'english', $id );
        $indexing_options['fields'] = $this->prepare_post( get_the_id() );

        $this->addPosts($index_name, $id, $indexing_options);

        /**
         * Action wp_redisearch_after_post_indexed fires after post added to the index.
         * Since this action called from within post loop, all Wordpress functions for post are available in the calback.
         * Example:
         * To get post title, you can simply call 'get_the_title()' function
         * 
         * @since 0.2.0
         * @param array $client             Created redis client instance
         * @param array $index_name         Index name
         * @param array $indexing_options   Posts extra options like language and fields
         */
        do_action( 'wp_redisearch_after_post_indexed', $this->client, $index_name, $indexing_options );
      }
      $index_meta['offset'] = absint( $index_meta['offset'] + $posts_per_page );
      update_option( 'wp_redisearch_index_meta', $index_meta );
    }
    return $index_meta;
  }

  /**
	 * Prepare a post for indexing.
	 *
	 * @param object $post_id
	 * @since 0.1.0
	 * @return bool|array
	 */
	public function prepare_post( $post_id ) {
    $post = get_post( $post_id );
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
    
    
    $post_categories = get_the_category( $post->ID );

		$post_args = array(
			'post_id', $post->ID,
			'post_author', $user_data,
			'post_date', strtotime( $post_date ),
			'post_title', $post->post_title,
			'post_excerpt', $post->post_excerpt,
			'post_content_filtered', wp_strip_all_tags( apply_filters( 'the_content', $post->post_content ), true ),
			'post_content', wp_strip_all_tags( $post->post_content, true ),
			'post_type', $post->post_type,
			'permalink', get_permalink( $post->ID ),
			'menu_order', absint( $post->menu_order )
    );
    
    $post_terms = apply_filters( 'wp_redisearch_prepared_terms', $this->prepare_terms( $post ), $post );

    $prepared_meta = $this->prepare_meta( $post->ID );
    
    $post_args = array_merge( $post_args, $post_terms, $prepared_meta );

		$post_args = apply_filters( 'wp_redisearch_prepared_post_args', $post_args, $post );

		return $post_args;
	}

  /**
  * Prepare post terms.
  * @since    0.1.0
  * @param integer $post
  * @return string
  */
  private function prepare_terms( $post ) {
    $indexable_terms = Settings::get( 'wp_redisearch_indexable_terms' );
    $indexable_terms = isset( $indexable_terms ) ? array_keys( $indexable_terms ) : array();

    /**
     * Filter wp_redisearch_indexable_temrs to manipulate indexable terms list
     * 
     * @since 0.2.1
     * @param array $indexable_terms        Default terms list
     * @param array $post                   The post object
     * @return array $indexable_terms       Modified taxobomy terms list
     */
		$indexable_terms = apply_filters( 'wp_redisearch_indexable_temrs', $indexable_terms, $post );

		if ( empty( $indexable_terms ) ) {
			return array();
		}

		$terms = array();
		foreach ( $indexable_terms as $taxonomy ) {

			$post_terms = get_the_terms( $post->ID, $taxonomy );

			if ( ! $post_terms || is_wp_error( $post_terms ) ) {
				continue;
			}

			$terms_dic = [];

			foreach ( $post_terms as $term ) {
        $terms_dic[] = $term->name;
      }
      $terms_dic = implode( ',', $terms_dic );
			$terms[] = $taxonomy;
			$terms[] = ltrim( $terms_dic );
		}

		return $terms;
	}

  /**
  * Prepare post meta.
  * @since    0.2.1
  * @param integer $post_id
  * @return array $prepared_meta
  */
  public function prepare_meta( $post_id ) {
    $post_meta = (array) get_post_meta( $post_id );
    
		if ( empty( $post_meta ) ) {
      return array();
    }
    
    $prepared_meta = array();
    
		/**
		 * Filter index-able post meta
		 * Allows for specifying public or private meta keys that may be indexed.
		 * @since 0.2.0
		 * @param array Array 
		 */
    $indexable_meta_keys = apply_filters( 'wp_redisearch_indexable_meta_keys', array() );

		foreach( $post_meta as $key => $value ) {
      if ( in_array( $key, $indexable_meta_keys ) ) {
        $prepared_meta[] = $key;
        $extracted_value = maybe_unserialize( $value[0] );
        $prepared_meta[] = is_array( $extracted_value ) ? json_encode( maybe_unserialize( $value[0] ) ) : $extracted_value;
			}
		}

		return $prepared_meta; 
  }

  /**
  * Add to index or in other term, index items.
  * @since    0.1.0
  * @param integer $post_id
  * @param array $post
  * @param array $indexing_options
  * @return object $index
  */
  public function addPosts($index_name, $id, $indexing_options) {
    $command = array_merge( [$index_name, $id , 1, 'LANGUAGE', $indexing_options['language']] );

    $extra_params = isset( $indexing_options['extra_params'] ) ? $indexing_options['extra_params'] : array();
    $extra_params = apply_filters( 'wp_redisearch_index_extra_params', $extra_params );
    // If any extra options passed, merge it to $command
    if ( isset( $extra_params ) ) {
      $command = array_merge( $command, $extra_params );
    }

    $command = array_merge( $command, array( 'FIELDS' ), $indexing_options['fields'] );

    $index = $this->client->rawCommand('FT.ADD', $command);
    return $index;
  }

  /**
  * Delete post from index.
  * @since    0.1.0
  * @param
  * @return object $this
  */
  public function deletePosts($index_name, $id) {
    $command = array( $index_name, $id , 'DD' );
    $this->client->rawCommand('FT.DEL', $command);
    return $this;
  }

  /**
  * Write entire redisearch index to the disk to persist it.
  * @since    0.1.0
  * @param
  * @return
  */
  public function writeToDisk() {
    return $this->client->rawCommand('SAVE', []);
  }

}