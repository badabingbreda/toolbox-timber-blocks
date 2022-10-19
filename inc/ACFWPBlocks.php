<?php
/**
 * Original library found here
 * https://github.com/palmiak/timber-acf-wp-blocks
 * 
 * @package timber-acf-wp-blocks
 * @since 1.0.0
 * @license MIT
 */
namespace ToolboxTimberBlocks;

use Timber\Timber;


/**
 * Main ACFWPBlocks Class
 */
class ACFWPBlocks {

	private static $acfActive = null;

	private static $metaboxActive = null;

	/**
	 * Constructor
	 */
	public function __construct() {

			// setup directories to search for twig templates
			add_action( 'after_setup_theme' , __CLASS__ . '::after_setup' ,10, 1 );
		
	}
	
	public static function after_setup() {
		
		if ( 
			is_callable( 'add_action' )	&& 
			( is_callable( 'acf_register_block_type' ) || is_callable( 'mb_get_block_field' ) ) && 
			class_exists( 'Timber' )
		) {
			// setup directories to search for twig templates
			add_action( 'acf/init' , __CLASS__ . '::add_default_dirs' ,10, 0 );
			// make sure to check for ACF before continuing
			add_action( 'acf/init', __CLASS__ . '::timber_block_init_acf' , 10, 0 );
			// check for metabox so callback can handle it
			add_filter( 'rwmb_meta_boxes' , __CLASS__ . '::timber_block_init_metabox', 10 , 1 ) ;
			// for ACF only add the blocks as they are found in the directories
			add_action( 'acf/init', __CLASS__ . '::timber_block_init' , 10, 0 );
			
		} elseif ( is_callable( 'add_action' ) ) {
			add_action(
				'admin_notices',
				function() {
					echo '<div class="error"><p>ACF WP Blocks requires Timber and ACF.';
					echo 'Check if the plugins or libraries are installed and activated.</p></div>';
				}
			);
		}
	}

		
	/**
	 * add_default_dirs
	 * 
	 * Adds theme or child-theme/views/blocks/ directory
	 *
	 * @return void
	 */
	public static function add_default_dirs() {

		add_filter( 'toolbox/block-template-dirs' , __CLASS__ . '::add_default_blocks_dir' , 10, 1 );
		
		// if twig templates CPT is NOT enabled, bail early
		if ( !get_option( 'toolbox_enable_twig_templates' ) ) return;

		add_filter( 'toolbox/block-template-dirs' , __CLASS__ . '::add_twig_templates_dir' , 10, 1 );

	}
	
	/**
	 * timber_block_init_metabox
	 * 
	 * When using metabox, activate this so the callback knows how to react
	 *
	 * @param  mixed $meta_boxes
	 * @return void
	 */
	public static function timber_block_init_metabox( $meta_boxes ) {

		if ( self::$metaboxActive === null && is_callable( 'mb_get_block_field' ) ) self::$metaboxActive = true;

		return $meta_boxes;
	}
	
	/**
	 * timber_block_init_acf
	 * 
	 * check for ACF and block capability
	 *
	 * @return void
	 */
	public static function timber_block_init_acf( ) {
		
		if ( self::$acfActive === null && is_callable( 'acf_register_block_type' ) ) self::$acfActive = true;
	}

	/**
	 * Create blocks based on templates found in Timber's "views/blocks" directory
	 * 
	 * init the blocks if ACF is found active
	 */
	public static function timber_block_init( ) {

		//if ( !self::$acfActive ) return;


		// Get an array of directories containing blocks.
		$directories = self::timber_block_directory_getter();

		// Check whether ACF exists before continuing.
		foreach ( $directories as $dir ) {
			// Sanity check whether the directory we're iterating over exists first.
			if ( ! file_exists( $dir ) ) {
				continue;
			}
			
			
			// Iterate over the directories provided and look for templates.
			$template_directory = new \DirectoryIterator( $dir );

			foreach ( $template_directory as $template ) {

				if ( $template->isDot() || $template->isDir() ) {
					continue;
				}

				$file_parts = pathinfo( $template->getFilename() );
				if ( 'twig' !== $file_parts['extension'] ) {
					continue;
				}

				// Strip the file extension to get the slug.
				$slug = $file_parts['filename'];

				// Get header info from the found template file(s).
				$file_path    = $dir . "/${slug}.twig" ;
				$file_headers = get_file_data(
					$file_path,
					array(
						'title'                      => 'Title',
						'description'                => 'Description',
						'category'                   => 'Category',
						'template_type'				 => 'TemplateType',
						'icon'                       => 'Icon',
						'keywords'                   => 'Keywords',
						'mode'                       => 'Mode',
						'align'                      => 'Align',
						'post_types'                 => 'PostTypes',
						'supports_align'             => 'SupportsAlign',
						'supports_mode'              => 'SupportsMode',
						'supports_multiple'          => 'SupportsMultiple',
						'supports_anchor'            => 'SupportsAnchor',
						'enqueue_style'              => 'EnqueueStyle',
						'enqueue_script'             => 'EnqueueScript',
						'enqueue_assets'             => 'EnqueueAssets',
						'supports_custom_class_name' => 'SupportsCustomClassName',
						'supports_reusable'          => 'SupportsReusable',
						'supports_full_height'       => 'SupportsFullHeight',
						'example'                    => 'Example',
						'supports_jsx'               => 'SupportsJSX',
						'parent'                     => 'Parent',
						'default_data'               => 'DefaultData',
					)
				);

				if ( 
					empty( $file_headers['title'] ) 
					|| empty( $file_headers['category'] ) 
					|| $file_headers[ 'template_type' ] !== 'block' 
				) {	continue; }

				// Keywords exploding with quotes.
				$keywords = str_getcsv( $file_headers['keywords'], ' ', '"' );

				// Set up block data for registration.
				$data = array(
					'name'            => $slug,	// for acf
					'context'		  => 'side',
					'title'           => $file_headers['title'],
					'description'     => $file_headers['description'],
					'category'        => $file_headers['category'],
					'icon'            => $file_headers['icon'],
					'keywords'        => $keywords,
					'mode'            => $file_headers['mode'],
					'align'           => $file_headers['align'],
					'render_callback' => array( __CLASS__, 'acf_block_callback' ),	// for acf
					'enqueue_style'	  => null,
					'enqueue_assets'  => $file_headers['enqueue_assets'],
					'default_data'    => $file_headers['default_data'],
				);

				// Removes empty defaults.
				$data = array_filter( $data );

				// If the PostTypes header is set in the template, restrict this block
				// to those types.
				if ( ! empty( $file_headers['post_types'] ) ) {
					$data['post_types'] = explode( ' ', $file_headers['post_types'] );
				}
				// If the SupportsAlign header is set in the template, restrict this block
				// to those aligns.
				if ( ! empty( $file_headers['supports_align'] ) ) {
					$data['supports']['align'] =
						in_array( $file_headers['supports_align'], array( 'true', 'false' ), true ) ?
						filter_var( $file_headers['supports_align'], FILTER_VALIDATE_BOOLEAN ) :
						explode( ' ', $file_headers['supports_align'] );
				}
				// If the SupportsMode header is set in the template, restrict this block
				// mode feature.
				if ( ! empty( $file_headers['supports_mode'] ) ) {
					$data['supports']['mode'] =
						( 'true' === $file_headers['supports_mode'] ) ? true : false;
				}
				// If the SupportsMultiple header is set in the template, restrict this block
				// multiple feature.
				if ( ! empty( $file_headers['supports_multiple'] ) ) {
					$data['supports']['multiple'] =
						( 'true' === $file_headers['supports_multiple'] ) ? true : false;
				}
				// If the SupportsAnchor header is set in the template, restrict this block
				// anchor feature.
				if ( ! empty( $file_headers['supports_anchor'] ) ) {
					$data['supports']['anchor'] =
						( 'true' === $file_headers['supports_anchor'] ) ? true : false;
				}

				// If the SupportsCustomClassName is set to false hides the possibilty to
				// add custom class name.
				if ( ! empty( $file_headers['supports_custom_class_name'] ) ) {
					$data['supports']['customClassName'] =
						( 'true' === $file_headers['supports_custom_class_name'] ) ? true : false;
				}

				// If the SupportsReusable is set in the templates it adds a posibility to
				// make this block reusable.
				if ( ! empty( $file_headers['supports_reusable'] ) ) {
					$data['supports']['reusable'] =
						( 'true' === $file_headers['supports_reusable'] ) ? true : false;
				}

				// If the SupportsFullHeight is set in the templates it adds a posibility to
				// make this block full height.
				if ( ! empty( $file_headers['supports_full_height'] ) ) {
					$data['supports']['full_height'] =
						( 'true' === $file_headers['supports_full_height'] ) ? true : false;
				}

				// Gives a possibility to enqueue style. If not an absoulte URL than adds
				// theme directory.
				if ( ! empty( $file_headers['enqueue_style'] ) ) {
					if ( ! filter_var( $file_headers['enqueue_style'], FILTER_VALIDATE_URL ) ) {
						$data['enqueue_style'] =
							$file_headers['enqueue_style'];
					} else {
						$data['enqueue_style'] = $file_headers['enqueue_style'];
					}
				}

				// Gives a possibility to enqueue script. If not an absoulte URL than adds
				// theme directory.
				if ( ! empty( $file_headers['enqueue_script'] ) ) {
					if ( ! filter_var( $file_headers['enqueue_script'], FILTER_VALIDATE_URL ) ) {
						$data['enqueue_script'] =
							$file_headers['enqueue_script'];
					} else {
						$data['enqueue_script'] = $file_headers['enqueue_script'];
					}
				}

				// Gives a possibility to enqueue assets. Takes a function name only
				// in which the assets (wp_enqueue_style and wp_enqueue_script) will need to be enqueued
				if ( ! empty( $file_headers['enqueue_assets'] ) ) {
					$data['enqueue_assets'] = $file_headers['enqueue_assets'];
				}


				// Support for experimantal JSX.
				if ( ! empty( $file_headers['supports_jsx'] ) ) {
					// Leaving the experimaental part for 2 versions.
					$data['supports']['__experimental_jsx'] =
						( 'true' === $file_headers['supports_jsx'] ) ? true : false;
					$data['supports']['jsx']                =
						( 'true' === $file_headers['supports_jsx'] ) ? true : false;
				}

				// Support for "example".
				if ( ! empty( $file_headers['example'] ) ) {
					$json                       = json_decode( $file_headers['example'], true );
					$example_data               = ( null !== $json ) ? $json : array();
					$example_data['is_example'] = true;
					$data['example']            = array(
						'attributes' => array(
							'mode' => 'preview',
							'data' => $example_data,
						),
					);
				}

				// Support for "parent".
				if ( ! empty( $file_headers['parent'] ) ) {
					$data['parent'] = str_getcsv( $file_headers['parent'], ' ', '"' );
				}

				// Merges the default options.
				$data = self::timber_block_default_data( $data );

				acf_register_block_type( $data );

			}
		}

	}

	/**
	 * Callback to register blocks
	 *
	 * @param array  $block      stores all the data from ACF.
	 * @param string $content    content passed to block.
	 * @param bool   $is_preview checks if block is in preview mode.
	 * @param int    $post_id    Post ID.
	 */
	public static function timber_blocks_callback( $block, $content = '', $is_preview = false, $post_id = 0 , $cf_lib = 'acf'  ) {
		
		if ( $cf_lib == 'meta_box' ) {

			// get the meta_boxes
			$meta_boxes = apply_filters( 'rwmb_meta_boxes' , [] );
			// extract the name
			$name = $block[ 'name' ];
			// filter the metaboxes so
			$meta_boxes = array_filter( $meta_boxes , function( $item ) use ( $name ) {
				if ( !isset( $item['id'] ) ) return false;
				return $item[ 'id' ] == $name;
			} );
			
			if (sizeof( $meta_boxes ) > 0 ) {
				$meta_box = $meta_boxes[ array_keys($meta_boxes)[0] ];
			}
	
			if ( isset( $meta_box[ 'twigtemplate' ] ) ) {
	
				$paths = $meta_box[ 'twigtemplate' ];
	
			} else {
	
				$paths = $block[ 'name' ] . '.twig';
			}

		}

		if ( $cf_lib == 'acf' ) {
			// Set up the slug to be useful.
			$slug = str_replace( 'acf/', '', $block['name'] );
			
		}

		if ( $cf_lib == 'meta_box' ) {
			// Set up the slug to be useful.
			$slug = str_replace( 'meta_box/', '', $block['name'] );
			
		}

		// Context compatibility.
		if ( method_exists( 'Timber', 'context' ) ) {
			$context = Timber::context();
		} else {
			$context = Timber::get_context();
		}

		$context['block']      = $block;
		$context['post_id']    = $post_id;
		$context['slug']       = $slug;
		$context['is_preview'] = $is_preview;

		if ( $cf_lib == 'acf'  ) {
			
			$context['fields']     = \get_fields();
		}

		if ($cf_lib == 'meta_box' ) {

			$fields = array_keys( isset( $block[ 'data' ] ) ? $block[ 'data' ] : [] );

			foreach ($fields as $field ) {
				$context[ 'fields' ][ $field ] = \mb_get_block_field( $field );
			}			
		}

		$classes = array_merge(
			array( $slug ),
			isset( $block['className'] ) ? array( $block['className'] ) : array(),
			$is_preview ? array( 'is-preview' ) : array(),
			array( 'align' .  (isset( $context['block']['align'] ) ? $context['block']['align'] : '' ) )
		);

		$context['classes'] = implode( ' ', $classes );

		$is_example = false;

		if ( ! empty( $block['data']['is_example'] ) ) {
			$is_example        = true;
			$context['fields'] = $block['data'];
		}

		$context = apply_filters( 'timber/acf-gutenberg-blocks-data', $context );
		$context = apply_filters( 'timber/acf-gutenberg-blocks-data/' . $slug, $context );
		$context = apply_filters( 'timber/acf-gutenberg-blocks-data/' . $block['id'], $context );

		if ( $cf_lib == 'acf' ) {

			$paths = self::timber_acf_path_render( $slug, $is_preview, $is_example );
		}


		Timber::render( $paths, $context );
	}

	public static function acf_block_callback( $block , $content = '' , $is_preview = false , $post_id = 0 ) {

		self::timber_blocks_callback( $block , $content , $is_preview , $post_id , 'acf' );
	}

	public static function metabox_callback( $block , $is_preview = false , $post_id = null ) {

		self::timber_blocks_callback( $block , $is_preview , $post_id , 'meta_box' );

	}

	/**
	 * Generates array with paths and slugs
	 *
	 * @param string $slug       File slug.
	 * @param bool   $is_preview Checks if preview.
	 * @param bool   $is_example Checks if example.
	 */
	public static function timber_acf_path_render( $slug, $is_preview, $is_example ) {
		$directories = self::timber_block_directory_getter();

		$ret = array();

		/**
		 * Filters the name of suffix for example file.
		 *
		 * @since 1.12
		 */
		$example_identifier = apply_filters( 'timber/acf-gutenberg-blocks-example-identifier', '-example' );

		/**
		 * Filters the name of suffix for preview file.
		 *
		 * @since 1.12
		 */
		$preview_identifier = apply_filters( 'timber/acf-gutenberg-blocks-preview-identifier', '-preview' );

		foreach ( $directories as $directory ) {
			if ( $is_example ) {
				$ret[] = $directory . "/{$slug}{$example_identifier}.twig";
			}
			if ( $is_preview ) {
				$ret[] = $directory . "/{$slug}{$preview_identifier}.twig";
			}
			$ret[] = $directory . "/{$slug}.twig";
		}

		return $ret;
	}

	/**
	 * Generates the list of subfolders based on current directories
	 *
	 * @param array $directories File path array.
	 */
	public static function timber_blocks_subdirectories( $directories ) {
		$ret = array();

		foreach ( $directories as $base_directory ) {
			// Check if the folder exist.
			if ( ! file_exists( $base_directory ) ) {
				continue;
			}

			$template_directory = new \RecursiveDirectoryIterator(
				$base_directory,
				\FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_SELF
			);

			if ( $template_directory ) {
				foreach ( $template_directory as $directory ) {
					if ( $directory->isDir() && ! $directory->isDot() ) {
						$ret[] = $base_directory . '/' . $directory->getFilename();
					}
				}
			}
		}

		return $ret;
	}

	/**
	 * Universal function to handle getting folders and subfolders
	 */
	public static function timber_block_directory_getter() {
		// Get an array of directories containing blocks.
		$directories = apply_filters( 'toolbox/block-template-dirs', [] );

		// Check subfolders.
		$subdirectories = self::timber_blocks_subdirectories( $directories );

		if ( ! empty( $subdirectories ) ) {
			$directories = array_merge( $directories, $subdirectories );
		}

		return $directories;
	}

	/**
	 * Default options setter.
	 *
	 * @param  [array] $data - header set data.
	 * @return [array]
	 */
	public static function timber_block_default_data( $data ) {
		$default_data = apply_filters( 'timber/acf-gutenberg-blocks-default-data', array() );
		$data_array   = array();

		if ( ! empty( $data['default_data'] ) ) {
			$default_data_key = $data['default_data'];
		}

		if ( isset( $default_data_key ) && ! empty( $default_data[ $default_data_key ] ) ) {
			$data_array = $default_data[ $default_data_key ];
		} elseif ( ! empty( $default_data['default'] ) ) {
			$data_array = $default_data['default'];
		}

		if ( is_array( $data_array ) ) {
			$data = array_merge( $data_array, $data );
		}

		return $data;
	}
	
	/**
	 * add_default_blocks_dir
	 * 
	 * Adds the (child-)theme views/blocks dir (and subdir) templates
	 *
	 * @param  mixed $views
	 * @return void
	 */
	public static function add_default_blocks_dir( $views ) {
		return array_merge( $views , [ \get_stylesheet_directory() . '/views/blocks' ] );
	}
	
	/**
	 * add_twig_templates_dir
	 * 
	 * Adds the Toolbox Twig Templates CPT folder
	 *
	 * @param  mixed $views
	 * @return void
	 */
	public static function add_twig_templates_dir( $views ) {

		$upload_dir = wp_upload_dir();

		return array_merge( $views , [ $upload_dir['basedir'] . '/'. 'toolbox_twigs' ] );
	}


}



