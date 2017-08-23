<?php

/**
 * Plugin Name: Fatal Safe
 * Description: Saves you when you are locked when you get a fatal error while editing a live plugin or theme file from WordPress admin panel theme editor. In this case FatalSafe saves you by quickly switching the theme or deactivating the plugin that creates the fatal error.
 * Version: 1.0.0
 * Author: Hasin Hayder From ThemeBucket
 * Author URI: http://hasin.me
 * Plugin URI: https://github.com/hasinhayder/fatalsafe
 */
class FatalSafe {
	public function __construct() {
		register_shutdown_function( array( $this, 'ohShit' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueScripts' ) );
		add_action( 'wp_ajax_dismissfserror', array( $this, 'dismissFatalSafeErrorMessage' ) );

		$fatalErrorMessage = get_option( 'fatalsafe' );
		if ( trim( $fatalErrorMessage ) != '' ) {
			add_action( 'admin_notices', array( $this, 'honkAtWill' ) );
		}

		add_action( "activated_plugin", array( $this, "makeSureThatThisPluginLoadsFirst" ) );
	}

	function makeSureThatThisPluginLoadsFirst() {
		$path = str_replace( WP_PLUGIN_DIR . '/', '', __FILE__ );
		if ( $plugins = get_option( 'active_plugins' ) ) {
			if ( $key = array_search( $path, $plugins ) ) {
				array_splice( $plugins, $key, 1 );
				array_unshift( $plugins, $path );
				update_option( 'active_plugins', $plugins );
			}
		}
	}

	function dismissFatalSafeErrorMessage() {

		$nonce = $_POST['nonce'];
		if ( wp_verify_nonce( $nonce, 'dismissfserror' ) ) {
			//alright, you shall pass
			update_option( 'fatalsafe', '' );
			echo "You shall pass";
			die();
		}

	}

	function enqueueScripts() {
		$jsPath = plugin_dir_url( __FILE__ ) . 'js/fatalsafe.js';
		wp_enqueue_script( 'fatalsafe-js', $jsPath, array( 'jquery' ), null, true );

		$ajaxUrl = admin_url( 'admin-ajax.php' );
		wp_localize_script( 'fatalsafe-js', 'fatalsafe', array( 'ajaxurl' => $ajaxUrl ) );
	}

	function honkAtWill() {
		$fatalErrorMessage = get_option( 'fatalsafe' );
		?>
        <div class='notice error failsafe-error-msg is-dismissible'>
            <p>
				<?php echo $fatalErrorMessage; ?>
            </p>
            <input type='hidden' id='fatalsafe-nonce' value='<?php echo wp_create_nonce( 'dismissfserror' ); ?>'/>
        </div>
		<?php
	}

	function ohShit() {
		$error = error_get_last();
		if ( $error && E_ERROR == $error['type'] ) { // run time fatal
			$themeDirectory      = basename( get_template_directory() );
			$styleSheetDirectory = basename( get_stylesheet_directory() );
			if ( $styleSheetDirectory !== $themeDirectory ) {
				$themeDirectory = $styleSheetDirectory;
			}
			$pluginDirectory = WP_PLUGIN_DIR;

			$currentTheme = wp_get_theme();


			//lets check if it was a theme or a plugin
			if ( strpos( $error['file'], $themeDirectory ) !== false ) {

				$foundOneFromTwentySeries = false;
				$themes                   = wp_get_themes( array(
					'errors'  => false,
					'allowed' => true,
					'blog_id' => 0
				) );

				foreach ( $themes as $slug => $theme ) {
					if ( strpos( $slug, 'twenty' ) !== false && $theme->get_stylesheet() != $currentTheme->get_stylesheet() ) {
						switch_theme( $theme->get_stylesheet() );
						$foundOneFromTwentySeries = true;
						break;
					}
				}

				if ( ! $foundOneFromTwentySeries ) {
					//dang, no theme from twenty series was present
					foreach ( $themes as $slug => $theme ) {
						if ( $theme->get_stylesheet() != $currentTheme->get_stylesheet() ) {
							switch_theme( $theme->get_stylesheet() );
							break;
						}
					}
				}

				$error['message']  = nl2br( $error['message'] );
				$fatalErrorMessage = "Your <b>{$currentTheme->Name}</b> theme threw a fatal error on <b>line #{$error['line']}</b> in <strong>{$error['file']}</strong>.<br/><br/>The error message was:<br/><br/> {$error['message']}.<br/><br/>Please fix it.";

			} else if ( strpos( $error['file'], $pluginDirectory ) !== false ) {

				require_once ABSPATH . "/wp-admin/includes/plugin.php";
				deactivate_plugins( plugin_basename( $error['file'] ),true );
				$fatalErrorMessage = "A plugin threw a fatal error on <b>line #{$error['line']}</b> in <strong>{$error['file']}</strong>.<br/><br/>The error message was: {$error['message']}.<br/><br/>Please fix it.";

			}
			update_option( 'fatalsafe', $fatalErrorMessage );
			echo "<h1>Don't Panic! Refresh This Page To Get Some Oxygen</h1>";

		}
	}
}

new FatalSafe();