<?php
/**
 * Plugin Name:       Elementor Template Kit Manager
 * Description:       Manage and deploy Elementor template kits stored as ZIP files. Upload, store, and import complete template packages with a simple admin interface.
 * Version:           0.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            WordPress Telex
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       elementor-template-kit-manager
 *
 * @package ElementorTemplateKitManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ETKM_VERSION', '0.1.0' );
define( 'ETKM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ETKM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Initialize the plugin
 */
function etkm_init() {
	// Check if Elementor is installed
	if ( ! did_action( 'elementor/loaded' ) ) {
		add_action( 'admin_notices', 'etkm_elementor_missing_notice' );
		return;
	}

	// Add admin menu
	add_action( 'admin_menu', 'etkm_add_admin_menu' );
	
	// Register AJAX handlers
	add_action( 'wp_ajax_etkm_upload_kit', 'etkm_handle_upload' );
	add_action( 'wp_ajax_etkm_import_kit', 'etkm_handle_import' );
	add_action( 'wp_ajax_etkm_delete_kit', 'etkm_handle_delete' );
	add_action( 'wp_ajax_etkm_get_kits', 'etkm_get_kits_ajax' );
	add_action( 'wp_ajax_etkm_upload_image', 'etkm_handle_image_upload' );
	
	// Enqueue admin scripts
	add_action( 'admin_enqueue_scripts', 'etkm_enqueue_admin_scripts' );
	
	// Handle Elementor tools page redirect
	add_action( 'admin_init', 'etkm_handle_elementor_upload_redirect' );
}
add_action( 'plugins_loaded', 'etkm_init' );

/**
 * Show notice if Elementor is not installed
 */
function etkm_elementor_missing_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Elementor Template Kit Manager requires Elementor to be installed and activated.', 'elementor-template-kit-manager' ); ?></p>
	</div>
	<?php
}

/**
 * Add admin menu
 */
function etkm_add_admin_menu() {
	add_menu_page(
		__( 'Template Kits', 'elementor-template-kit-manager' ),
		__( 'Template Kits', 'elementor-template-kit-manager' ),
		'manage_options',
		'elementor-template-kits',
		'etkm_render_admin_page',
		'dashicons-download',
		59
	);
}

/**
 * Get upload directory for template kits
 */
function etkm_get_upload_dir() {
	$upload_dir = wp_upload_dir();
	$kit_dir = $upload_dir['basedir'] . '/elementor-template-kits';
	
	if ( ! file_exists( $kit_dir ) ) {
		wp_mkdir_p( $kit_dir );
		// Add index.php for security
		file_put_contents( $kit_dir . '/index.php', '<?php // Silence is golden' );
	}
	
	return $kit_dir;
}

/**
 * Get URL for upload directory
 */
function etkm_get_upload_url() {
	$upload_dir = wp_upload_dir();
	return $upload_dir['baseurl'] . '/elementor-template-kits';
}

/**
 * Enqueue admin scripts and styles
 */
function etkm_enqueue_admin_scripts( $hook ) {
	if ( 'toplevel_page_elementor-template-kits' !== $hook ) {
		return;
	}
	
	wp_enqueue_media();
	
	wp_enqueue_style(
		'etkm-admin-style',
		ETKM_PLUGIN_URL . 'build/style-index.css',
		array(),
		ETKM_VERSION
	);
	
	wp_enqueue_script(
		'etkm-admin-script',
		ETKM_PLUGIN_URL . 'build/view.js',
		array(),
		ETKM_VERSION,
		true
	);
	
	wp_localize_script(
		'etkm-admin-script',
		'etkm',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'adminUrl' => admin_url(),
			'nonce' => wp_create_nonce( 'etkm_nonce' ),
			'maxFileSize' => wp_max_upload_size(),
			'uploadUrl' => etkm_get_upload_url(),
			'strings' => array(
				'uploadSuccess' => __( 'Template kit uploaded successfully!', 'elementor-template-kit-manager' ),
				'uploadError' => __( 'Upload failed. Please try again.', 'elementor-template-kit-manager' ),
				'importSuccess' => __( 'Templates imported successfully!', 'elementor-template-kit-manager' ),
				'importError' => __( 'Import failed. Please check the file and try again.', 'elementor-template-kit-manager' ),
				'deleteConfirm' => __( 'Are you sure you want to delete this template kit?', 'elementor-template-kit-manager' ),
				'deleteSuccess' => __( 'Template kit deleted successfully.', 'elementor-template-kit-manager' ),
				'invalidFile' => __( 'Please select a valid ZIP file.', 'elementor-template-kit-manager' ),
				'fileTooLarge' => __( 'File size exceeds the maximum allowed size.', 'elementor-template-kit-manager' ),
				'imageUploadSuccess' => __( 'Image uploaded successfully!', 'elementor-template-kit-manager' ),
				'imageUploadError' => __( 'Failed to upload image.', 'elementor-template-kit-manager' ),
			)
		)
	);
}

/**
 * Handle redirect to Elementor tools page
 */
function etkm_handle_elementor_upload_redirect() {
	if ( ! isset( $_GET['page'] ) || 'elementor-tools' !== $_GET['page'] ) {
		return;
	}
	
	if ( ! isset( $_GET['action'] ) || 'upload_kit' !== $_GET['action'] ) {
		return;
	}
	
	if ( ! isset( $_GET['kit_file'] ) || ! isset( $_GET['nonce'] ) ) {
		return;
	}
	
	if ( ! wp_verify_nonce( $_GET['nonce'], 'etkm_nonce' ) ) {
		return;
	}
	
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	
	$filename = sanitize_file_name( $_GET['kit_file'] );
	$upload_dir = etkm_get_upload_dir();
	$filepath = $upload_dir . '/' . $filename;
	
	if ( ! file_exists( $filepath ) ) {
		wp_die( esc_html__( 'Template kit file not found.', 'elementor-template-kit-manager' ) );
	}
	
	// Add hook to inject the file into Elementor's upload handler
	add_action( 'elementor/tools/after_general_tab', function() use ( $filepath, $filename ) {
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Auto-trigger upload with our file
			var fileInput = $('#elementor-import-template-trigger, input[name="file"], input[type="file"][accept=".zip"]').first();
			
			if (fileInput.length) {
				// Create a notice
				var notice = $('<div class="notice notice-info is-dismissible"><p><strong>Template Kit Manager:</strong> Ready to upload "<?php echo esc_js( $filename ); ?>"</p></div>');
				$('.elementor-panel-scheme-items, .elementor-panel-scheme-content').first().prepend(notice);
				
				// Create a custom file object
				fetch('<?php echo esc_url( etkm_get_upload_url() . '/' . $filename ); ?>')
					.then(function(response) { return response.blob(); })
					.then(function(blob) {
						var file = new File([blob], '<?php echo esc_js( $filename ); ?>', { type: 'application/zip' });
						var dataTransfer = new DataTransfer();
						dataTransfer.items.add(file);
						fileInput[0].files = dataTransfer.files;
						
						// Trigger change event
						fileInput.trigger('change');
						
						// Update notice
						notice.find('p').html('<strong>Template Kit Manager:</strong> File loaded! Click "Import Now" to continue.');
					})
					.catch(function(error) {
						notice.removeClass('notice-info').addClass('notice-error');
						notice.find('p').html('<strong>Template Kit Manager:</strong> Failed to load file. ' + error.message);
					});
			}
		});
		</script>
		<?php
	}, 5 );
}

/**
 * Render admin page
 */
function etkm_render_admin_page() {
	?>
	<div class="wrap etkm-admin-wrap">
		<h1><?php esc_html_e( 'Elementor Template Kit Manager', 'elementor-template-kit-manager' ); ?></h1>
		
		<div class="etkm-upload-section">
			<h2><?php esc_html_e( 'Upload New Template Kit', 'elementor-template-kit-manager' ); ?></h2>
			<div class="etkm-upload-area" id="etkm-upload-area">
				<div class="etkm-upload-content">
					<span class="dashicons dashicons-upload"></span>
					<p><?php esc_html_e( 'Drag and drop your Elementor template kit ZIP file here', 'elementor-template-kit-manager' ); ?></p>
					<p class="etkm-or"><?php esc_html_e( 'or', 'elementor-template-kit-manager' ); ?></p>
					<button type="button" class="button button-primary" id="etkm-select-file">
						<?php esc_html_e( 'Select File', 'elementor-template-kit-manager' ); ?>
					</button>
					<input type="file" id="etkm-file-input" accept=".zip" style="display: none;">
					<p class="etkm-file-info">
						<?php
						printf(
							/* translators: %s: maximum file size */
							esc_html__( 'Maximum file size: %s', 'elementor-template-kit-manager' ),
							esc_html( size_format( wp_max_upload_size() ) )
						);
						?>
					</p>
				</div>
				<div class="etkm-upload-progress" style="display: none;">
					<div class="etkm-progress-bar">
						<div class="etkm-progress-fill"></div>
					</div>
					<p class="etkm-progress-text">0%</p>
				</div>
			</div>
		</div>
		
		<div class="etkm-kits-section">
			<div class="etkm-section-header">
				<h2><?php esc_html_e( 'Template Kit Gallery', 'elementor-template-kit-manager' ); ?></h2>
				<div class="etkm-view-toggle">
					<button type="button" class="etkm-view-btn active" data-view="gallery">
						<span class="dashicons dashicons-grid-view"></span>
						<?php esc_html_e( 'Gallery', 'elementor-template-kit-manager' ); ?>
					</button>
					<button type="button" class="etkm-view-btn" data-view="list">
						<span class="dashicons dashicons-list-view"></span>
						<?php esc_html_e( 'List', 'elementor-template-kit-manager' ); ?>
					</button>
				</div>
			</div>
			<div id="etkm-kits-list" class="etkm-kits-list etkm-gallery-view">
				<div class="etkm-loading">
					<span class="spinner is-active"></span>
					<p><?php esc_html_e( 'Loading template kits...', 'elementor-template-kit-manager' ); ?></p>
				</div>
			</div>
		</div>
		
		<div id="etkm-notification" class="etkm-notification" style="display: none;"></div>
	</div>
	<?php
}

/**
 * Handle file upload via AJAX
 */
function etkm_handle_upload() {
	check_ajax_referer( 'etkm_nonce', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'elementor-template-kit-manager' ) ) );
	}
	
	if ( empty( $_FILES['file'] ) ) {
		wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'elementor-template-kit-manager' ) ) );
	}
	
	$file = $_FILES['file'];
	
	// Validate file type
	$file_type = wp_check_filetype( $file['name'] );
	if ( 'zip' !== $file_type['ext'] ) {
		wp_send_json_error( array( 'message' => __( 'Only ZIP files are allowed.', 'elementor-template-kit-manager' ) ) );
	}
	
	// Validate file size
	if ( $file['size'] > wp_max_upload_size() ) {
		wp_send_json_error( array( 'message' => __( 'File size exceeds the maximum allowed size.', 'elementor-template-kit-manager' ) ) );
	}
	
	$upload_dir = etkm_get_upload_dir();
	$filename = sanitize_file_name( $file['name'] );
	$destination = $upload_dir . '/' . $filename;
	
	// Check if file already exists
	if ( file_exists( $destination ) ) {
		$filename = wp_unique_filename( $upload_dir, $filename );
		$destination = $upload_dir . '/' . $filename;
	}
	
	if ( move_uploaded_file( $file['tmp_name'], $destination ) ) {
		// Try to extract preview image
		etkm_extract_preview_image( $destination, $filename );
		
		wp_send_json_success( array(
			'message' => __( 'Template kit uploaded successfully!', 'elementor-template-kit-manager' ),
			'filename' => $filename
		) );
	} else {
		wp_send_json_error( array( 'message' => __( 'Failed to upload file.', 'elementor-template-kit-manager' ) ) );
	}
}

/**
 * Extract preview image from ZIP
 */
function etkm_extract_preview_image( $zip_path, $filename ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return;
	}
	
	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path ) ) {
		return;
	}
	
	$upload_dir = etkm_get_upload_dir();
	$preview_dir = $upload_dir . '/previews';
	
	if ( ! file_exists( $preview_dir ) ) {
		wp_mkdir_p( $preview_dir );
	}
	
	// Look for common preview image names
	$preview_names = array( 'preview.jpg', 'preview.png', 'thumbnail.jpg', 'thumbnail.png', 'screenshot.jpg', 'screenshot.png' );
	
	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$file_info = $zip->statIndex( $i );
		$file_name = basename( $file_info['name'] );
		
		if ( in_array( strtolower( $file_name ), $preview_names ) ) {
			$preview_content = $zip->getFromIndex( $i );
			if ( $preview_content ) {
				$preview_filename = pathinfo( $filename, PATHINFO_FILENAME ) . '.' . pathinfo( $file_name, PATHINFO_EXTENSION );
				file_put_contents( $preview_dir . '/' . $preview_filename, $preview_content );
				break;
			}
		}
	}
	
	$zip->close();
}

/**
 * Handle image upload via AJAX
 */
function etkm_handle_image_upload() {
	check_ajax_referer( 'etkm_nonce', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'elementor-template-kit-manager' ) ) );
	}
	
	$kit_filename = isset( $_POST['kit_filename'] ) ? sanitize_file_name( $_POST['kit_filename'] ) : '';
	$image_id = isset( $_POST['image_id'] ) ? intval( $_POST['image_id'] ) : 0;
	
	if ( empty( $kit_filename ) || empty( $image_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'elementor-template-kit-manager' ) ) );
	}
	
	// Get the attachment URL
	$image_url = wp_get_attachment_image_url( $image_id, 'large' );
	if ( ! $image_url ) {
		wp_send_json_error( array( 'message' => __( 'Invalid image.', 'elementor-template-kit-manager' ) ) );
	}
	
	// Copy the image to our preview directory
	$image_path = get_attached_file( $image_id );
	if ( ! $image_path || ! file_exists( $image_path ) ) {
		wp_send_json_error( array( 'message' => __( 'Image file not found.', 'elementor-template-kit-manager' ) ) );
	}
	
	$upload_dir = etkm_get_upload_dir();
	$preview_dir = $upload_dir . '/previews';
	
	if ( ! file_exists( $preview_dir ) ) {
		wp_mkdir_p( $preview_dir );
	}
	
	// Delete any existing preview images for this kit
	$base_filename = pathinfo( $kit_filename, PATHINFO_FILENAME );
	$extensions = array( 'jpg', 'png', 'jpeg' );
	foreach ( $extensions as $ext ) {
		$old_preview = $preview_dir . '/' . $base_filename . '.' . $ext;
		if ( file_exists( $old_preview ) ) {
			unlink( $old_preview );
		}
	}
	
	// Copy the new image
	$extension = pathinfo( $image_path, PATHINFO_EXTENSION );
	$preview_filename = $base_filename . '.' . $extension;
	$preview_path = $preview_dir . '/' . $preview_filename;
	
	if ( copy( $image_path, $preview_path ) ) {
		$preview_url = etkm_get_upload_url() . '/previews/' . $preview_filename;
		wp_send_json_success( array(
			'message' => __( 'Image uploaded successfully!', 'elementor-template-kit-manager' ),
			'preview_url' => $preview_url
		) );
	} else {
		wp_send_json_error( array( 'message' => __( 'Failed to save image.', 'elementor-template-kit-manager' ) ) );
	}
}

/**
 * Get all template kits via AJAX
 */
function etkm_get_kits_ajax() {
	check_ajax_referer( 'etkm_nonce', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'elementor-template-kit-manager' ) ) );
	}
	
	$kits = etkm_get_template_kits();
	wp_send_json_success( array( 'kits' => $kits ) );
}

/**
 * Get all template kits
 */
function etkm_get_template_kits() {
	$upload_dir = etkm_get_upload_dir();
	$upload_url = etkm_get_upload_url();
	$kits = array();
	
	if ( ! is_dir( $upload_dir ) ) {
		return $kits;
	}
	
	$files = scandir( $upload_dir );
	
	foreach ( $files as $file ) {
		if ( '.' === $file || '..' === $file || 'index.php' === $file || 'previews' === $file ) {
			continue;
		}
		
		$filepath = $upload_dir . '/' . $file;
		
		if ( is_file( $filepath ) && 'zip' === pathinfo( $file, PATHINFO_EXTENSION ) ) {
			$preview_url = etkm_get_preview_url( $file );
			
			$kits[] = array(
				'name' => $file,
				'display_name' => etkm_format_kit_name( $file ),
				'size' => size_format( filesize( $filepath ), 2 ),
				'date' => date_i18n( get_option( 'date_format' ), filemtime( $filepath ) ),
				'timestamp' => filemtime( $filepath ),
				'preview' => $preview_url
			);
		}
	}
	
	// Sort by date, newest first
	usort( $kits, function( $a, $b ) {
		return $b['timestamp'] - $a['timestamp'];
	});
	
	return $kits;
}

/**
 * Get preview URL for a kit
 */
function etkm_get_preview_url( $filename ) {
	$upload_dir = etkm_get_upload_dir();
	$upload_url = etkm_get_upload_url();
	$preview_dir = $upload_dir . '/previews';
	
	$base_filename = pathinfo( $filename, PATHINFO_FILENAME );
	$extensions = array( 'jpg', 'png', 'jpeg' );
	
	foreach ( $extensions as $ext ) {
		$preview_file = $preview_dir . '/' . $base_filename . '.' . $ext;
		if ( file_exists( $preview_file ) ) {
			return $upload_url . '/previews/' . $base_filename . '.' . $ext;
		}
	}
	
	return false;
}

/**
 * Format kit name for display
 */
function etkm_format_kit_name( $filename ) {
	$name = pathinfo( $filename, PATHINFO_FILENAME );
	$name = str_replace( array( '-', '_' ), ' ', $name );
	$name = ucwords( $name );
	return $name;
}

/**
 * Handle template import via AJAX
 */
function etkm_handle_import() {
	check_ajax_referer( 'etkm_nonce', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'elementor-template-kit-manager' ) ) );
	}
	
	$filename = isset( $_POST['filename'] ) ? sanitize_file_name( $_POST['filename'] ) : '';
	
	if ( empty( $filename ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid filename.', 'elementor-template-kit-manager' ) ) );
	}
	
	$upload_dir = etkm_get_upload_dir();
	$filepath = $upload_dir . '/' . $filename;
	
	if ( ! file_exists( $filepath ) ) {
		wp_send_json_error( array( 'message' => __( 'File not found.', 'elementor-template-kit-manager' ) ) );
	}
	
	// Import the template kit
	$result = etkm_import_template_kit( $filepath );
	
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}
	
	wp_send_json_success( array(
		'message' => __( 'Template kit imported successfully!', 'elementor-template-kit-manager' ),
		'imported' => $result
	) );
}

/**
 * Import template kit
 */
function etkm_import_template_kit( $filepath ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'zip_error', __( 'ZipArchive extension is not available.', 'elementor-template-kit-manager' ) );
	}
	
	$zip = new ZipArchive();
	
	if ( true !== $zip->open( $filepath ) ) {
		return new WP_Error( 'zip_error', __( 'Failed to open ZIP file.', 'elementor-template-kit-manager' ) );
	}
	
	$temp_dir = get_temp_dir() . 'etkm_' . uniqid();
	wp_mkdir_p( $temp_dir );
	
	$zip->extractTo( $temp_dir );
	$zip->close();
	
	// Find and import JSON files
	$imported_templates = array();
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $temp_dir ) );
	
	foreach ( $iterator as $file ) {
		if ( $file->isFile() && 'json' === $file->getExtension() ) {
			$json_content = file_get_contents( $file->getPathname() );
			$template_data = json_decode( $json_content, true );
			
			if ( ! empty( $template_data ) && isset( $template_data['content'] ) ) {
				$result = etkm_import_single_template( $template_data );
				if ( ! is_wp_error( $result ) ) {
					$imported_templates[] = array(
						'title' => isset( $template_data['title'] ) ? $template_data['title'] : basename( $file->getFilename(), '.json' ),
						'type' => isset( $template_data['type'] ) ? $template_data['type'] : 'page',
						'id' => $result
					);
				}
			}
		}
	}
	
	// Clean up temp directory
	etkm_delete_directory( $temp_dir );
	
	if ( empty( $imported_templates ) ) {
		return new WP_Error( 'import_error', __( 'No valid templates found in the ZIP file.', 'elementor-template-kit-manager' ) );
	}
	
	return $imported_templates;
}

/**
 * Import single template
 */
function etkm_import_single_template( $template_data ) {
	$defaults = array(
		'title' => __( 'Imported Template', 'elementor-template-kit-manager' ),
		'type' => 'page',
		'content' => array()
	);
	
	$template_data = wp_parse_args( $template_data, $defaults );
	
	// Create new template post
	$template_id = wp_insert_post( array(
		'post_title' => $template_data['title'],
		'post_type' => 'elementor_library',
		'post_status' => 'publish',
		'meta_input' => array(
			'_elementor_data' => wp_json_encode( $template_data['content'] ),
			'_elementor_template_type' => $template_data['type'],
			'_elementor_edit_mode' => 'builder'
		)
	) );
	
	if ( is_wp_error( $template_id ) ) {
		return $template_id;
	}
	
	return $template_id;
}

/**
 * Handle template kit deletion via AJAX
 */
function etkm_handle_delete() {
	check_ajax_referer( 'etkm_nonce', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'elementor-template-kit-manager' ) ) );
	}
	
	$filename = isset( $_POST['filename'] ) ? sanitize_file_name( $_POST['filename'] ) : '';
	
	if ( empty( $filename ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid filename.', 'elementor-template-kit-manager' ) ) );
	}
	
	$upload_dir = etkm_get_upload_dir();
	$filepath = $upload_dir . '/' . $filename;
	
	if ( ! file_exists( $filepath ) ) {
		wp_send_json_error( array( 'message' => __( 'File not found.', 'elementor-template-kit-manager' ) ) );
	}
	
	if ( unlink( $filepath ) ) {
		// Also delete preview image if exists
		$preview_dir = $upload_dir . '/previews';
		$base_filename = pathinfo( $filename, PATHINFO_FILENAME );
		$extensions = array( 'jpg', 'png', 'jpeg' );
		
		foreach ( $extensions as $ext ) {
			$preview_file = $preview_dir . '/' . $base_filename . '.' . $ext;
			if ( file_exists( $preview_file ) ) {
				unlink( $preview_file );
			}
		}
		
		wp_send_json_success( array( 'message' => __( 'Template kit deleted successfully.', 'elementor-template-kit-manager' ) ) );
	} else {
		wp_send_json_error( array( 'message' => __( 'Failed to delete file.', 'elementor-template-kit-manager' ) ) );
	}
}

/**
 * Recursively delete directory
 */
function etkm_delete_directory( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	
	$files = array_diff( scandir( $dir ), array( '.', '..' ) );
	
	foreach ( $files as $file ) {
		$path = $dir . '/' . $file;
		is_dir( $path ) ? etkm_delete_directory( $path ) : unlink( $path );
	}
	
	rmdir( $dir );
}