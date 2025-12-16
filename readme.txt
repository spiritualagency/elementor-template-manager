
=== Elementor Template Kit Manager ===

Contributors:      WordPress Telex
Tags:              elementor, templates, template kits, import, export
Tested up to:      6.8
Stable tag:        0.1.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Requires at least: 5.8
Requires PHP:      7.4

A powerful WordPress plugin for managing and deploying Elementor template kits stored as ZIP files.

== Description ==

Elementor Template Kit Manager is a comprehensive solution for WordPress administrators who need to manage, store, and deploy complete Elementor website template packages. This plugin streamlines the process of importing pre-designed Elementor templates, making it easy to set up new websites or add new sections to existing ones.

**Key Features:**

* **Upload Template Kits**: Upload complete Elementor template ZIP files directly through the WordPress admin interface
* **Drag & Drop Interface**: Modern drag-and-drop upload functionality for seamless file management
* **Template Library**: View all available template kits in a clean, organized list view
* **Quick Deployment**: One-click import functionality to deploy templates directly into Elementor
* **File Validation**: Automatic validation ensures only valid Elementor template ZIPs are accepted
* **Progress Indicators**: Real-time progress feedback during upload and deployment processes
* **Detailed Information**: See template kit names, upload dates, and file sizes at a glance
* **Error Handling**: Clear, informative error messages for troubleshooting failed imports
* **Metadata Preservation**: Maintains all template metadata and dependencies during import
* **Elementor Compatible**: Seamlessly integrates with Elementor's native import system

**Perfect For:**

* Web agencies managing multiple client sites
* Developers who frequently deploy template kits
* WordPress administrators maintaining template libraries
* Anyone working with Elementor templates regularly

== Installation ==

1. Ensure Elementor is installed and activated on your WordPress site
2. Upload the plugin files to the `/wp-content/plugins/elementor-template-kit-manager` directory, or install the plugin through the WordPress plugins screen directly
3. Activate the plugin through the 'Plugins' screen in WordPress
4. Navigate to 'Template Kits' in the WordPress admin menu to start managing your template kits

== Frequently Asked Questions ==

= Does this plugin require Elementor? =

Yes, this plugin is designed specifically to work with Elementor and requires Elementor to be installed and activated.

= What file formats are supported? =

The plugin accepts ZIP files containing Elementor template exports. Files must follow Elementor's standard template export format.

= Where are the uploaded template kits stored? =

Template kits are stored in a dedicated directory within your WordPress uploads folder for easy management and backup.

= Can I delete template kits after importing them? =

Yes, you can delete template kit ZIP files from the library at any time. This won't affect templates that have already been imported into Elementor.

= Is there a file size limit for uploads? =

Upload limits are determined by your server's PHP configuration (upload_max_filesize and post_max_size). You can increase these limits in your php.ini file if needed.

= What happens if an import fails? =

The plugin provides detailed error messages to help troubleshoot import failures. Common issues include corrupted ZIP files, insufficient permissions, or incompatible template formats.

== Screenshots ==

1. Main template kit library view showing all available kits with their details
2. Drag and drop upload interface for adding new template kits
3. Import progress indicator during template deployment
4. Success confirmation showing all imported templates
5. Error handling with clear troubleshooting messages

== Changelog ==

= 0.1.0 =
* Initial release
* Upload and manage Elementor template kit ZIP files
* One-click import functionality
* Drag and drop upload interface
* File validation and error handling
* Progress indicators for uploads and imports
* Template kit library with detailed information

== Additional Information ==

**Requirements:**

* WordPress 5.8 or higher
* PHP 7.4 or higher
* Elementor (free or pro version)
* Sufficient server storage for template kits
* PHP ZipArchive extension enabled

**Support:**

For support, feature requests, or bug reports, please visit the plugin support forum.

**Privacy:**

This plugin does not collect or transmit any user data. All template kits are stored locally on your WordPress server.
