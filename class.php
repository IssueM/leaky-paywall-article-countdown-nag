<?php
/**
 * Registers zeen101's Leaky Paywall class
 *
 * @package zeen101's Leaky Paywall - Article Countdown Nag
 * @since 1.0.0
 */

/**
 * This class registers the main issuem functionality
 *
 * @since 1.0.0
 */

class Leaky_Paywall_Article_Countdown_Nag {
	
	/**
	 * Class constructor, puts things in motion
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
				
		$settings = $this->get_settings();
		
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );
		
		// add_action( 'wp', array( $this, 'process_requests' ), 15 );

		add_action( 'leaky_paywall_after_general_settings', array( $this, 'settings_div' ) );
		add_action( 'leaky_paywall_update_settings', array( $this, 'update_settings_div' ) );

		add_action( 'wp_ajax_nopriv_maybe_display_countdown', array( $this, 'maybe_display_countdown' ) );
		add_action( 'wp_ajax_maybe_display_countdown', array( $this, 'maybe_display_countdown' ) );
		
	}

	/**
	 * Determine whether to show the countdown nag
	 *
	 * @since 3.4.3
	 *
	 */
	public function maybe_display_countdown() 
	{

		$post_id = $_REQUEST['post_id'];
		$post_obj = get_post( $post_id );
		$current_post_type = $post_obj->post_type;
		$post_type_obj = get_post_type_object( $current_post_type );
		$content_remaining = 0;
		$allowed_value = 0;
		
		$lp_restrictions = new Leaky_Paywall_Restrictions();
		$restrictions = $lp_restrictions->get_subscriber_restrictions();

		$settings = $this->get_settings();
		$lp_settings = get_leaky_paywall_settings();
		$login_url = get_page_link( $lp_settings['page_for_login'] );
		$subscription_url = get_page_link( $lp_settings['page_for_subscription'] );

		do_action( 'leaky_paywall_acn_before_process_requests', $lp_settings );
		
		// allow admins to view all content
		if ( current_user_can( apply_filters( 'leaky_paywall_current_user_can_view_all_content', 'manage_options' ) ) ) {
			echo '';
			exit();
		}

		if ( $this->is_unblockable_content( $post_id ) ) {
			echo '';
			exit();
		}

		if ( $lp_restrictions->visibility_allows_access( $post_obj ) ) {
			echo '';
			exit();
		}

		if ( $lp_restrictions->visibility_is_restricted( $post_obj ) ) {

			ob_start(); 
			?>
			<div class="acn-zero-remaining-overlay"></div>
			<div id="issuem-leaky-paywall-articles-zero-remaining-nag">
				<div id="issuem-leaky-paywall-articles-remaining-close">&nbsp;</div>
				<div id="issuem-leaky-paywall-articles-remaining">
					<div id="issuem-leaky-paywall-articles-remaining-count">0</div>
					<div id="issuem-leaky-paywall-articles-remaining-text">This content is restricted.</div>
				</div>
				<div id="issuem-leaky-paywall-articles-remaining-subscribe-link"><a href="<?php echo $subscription_url; ?>"><?php _e( 'Subscribe today for full access', 'issuem-lp-anc' ); ?></a></div>
				<div id="issuem-leaky-paywall-articles-remaining-login-link"><a href="<?php echo $login_url; ?>"><?php _e( 'Current subscriber? Login here', 'issuem-lp-anc' ); ?></a></div>
			</div>
			<?php 
			$content = ob_get_contents();
			ob_end_clean();

			if ( 'no' == $settings['zero_remaining_popup'] ) {
				echo '';
			} else {
				echo $content; 
			}
			
			exit();
		}


		// at this point we need to test against the global restriction settings

		$post_type_id = '';
		$restricted_post_type = '';
		$is_restricted = false;

		if ( !empty( $restrictions ) ) {

			foreach( $restrictions as $key => $restriction ) {

				if ( $restriction['post_type'] == $current_post_type ) {

					// this will only be ignored if the allowed value is unlimited ( -1 )
					if ( 0 <= $restriction['allowed_value'] ) {

						$post_type_id = $key;
						$allowed_value = $restriction['allowed_value'];
						$restricted_post_type = $restriction['post_type'];
						$is_restricted = true;

						break;

					}

				}

			}

		}

		$is_restricted = apply_filters( 'leaky_paywall_filter_is_restricted', $is_restricted, $restrictions, $post_obj );

		if ( !$is_restricted ) {
			echo '';
			exit();
		}


		// content that can be accessed because the user has viewed it already
		$available_content = $lp_restrictions->get_available_content( $restricted_post_type );

		if ( !empty( $available_content[$restricted_post_type] ) ) {
			$content_remaining = $allowed_value - count( $available_content[$restricted_post_type] );
		} else {
			$content_remaining = $allowed_value;
		}

		if ( $content_remaining < 0 ) {
			$content_remaining = 0;
		}

	    if ( $settings['nag_after_countdown'] > $allowed_value - $content_remaining) {
	    	echo '';
	    	exit();
	    }

		if ( $lp_restrictions->combined_restrictions_enabled() ) {

			if ( $lp_restrictions->is_restricted_combined( $restrictions, $available_content, $post_type_id, $restricted_post_type, $post_id ) ) {
				
				$remaining_text = ( 1 === $content_remaining ) 
	        		?  sprintf( __( '%s Remaining', 'issuem-lp-anc' ), $post_type_obj->labels->singular_name )
	        		:  sprintf( __( '%s Remaining', 'issuem-lp-anc' ), $post_type_obj->labels->name );

				ob_start(); 
				?>
				<div class="acn-zero-remaining-overlay"></div>
				<div id="issuem-leaky-paywall-articles-zero-remaining-nag">
					<div id="issuem-leaky-paywall-articles-remaining-close">&nbsp;</div>
					<div id="issuem-leaky-paywall-articles-remaining">
						<div id="issuem-leaky-paywall-articles-remaining-count">0</div>
						<div id="issuem-leaky-paywall-articles-remaining-text"><?php echo $remaining_text; ?></div>
					</div>
					<div id="issuem-leaky-paywall-articles-remaining-subscribe-link"><a href="<?php echo $subscription_url; ?>"><?php _e( 'Subscribe today for full access', 'issuem-lp-anc' ); ?></a></div>
					<div id="issuem-leaky-paywall-articles-remaining-login-link"><a href="<?php echo $login_url; ?>"><?php _e( 'Current subscriber? Login here', 'issuem-lp-anc' ); ?></a></div>
				</div>
				<?php 
				$content = ob_get_contents();
				ob_end_clean();
				
				if ( 'no' == $settings['zero_remaining_popup'] ) {
					echo '';
				} else {
					echo $content; 
				}

				exit();

			} else {

				$remaining_text = ( 1 === $content_remaining ) 
	        		?  sprintf( __( '%s Remaining', 'issuem-lp-anc' ), $post_type_obj->labels->singular_name )
	        		:  sprintf( __( '%s Remaining', 'issuem-lp-anc' ), $post_type_obj->labels->name );

			    ob_start(); 

			    if ( $settings['nag_theme'] == 'slim' ) {
			    	?>
			    	<div id="issuem-leaky-paywall-articles-remaining-nag">
			    		<div id="issuem-leaky-paywall-articles-remaining-close">x</div>
			    		<div id="issuem-leaky-paywall-articles-remaining-count">
			    			<p><?php echo $content_remaining; ?></p>
			    		</div>

				    	<div id="issuem-leaky-paywall-articles-remaining">
				    		
				    		<div id="issuem-leaky-paywall-articles-remaining-text"><?php echo $remaining_text; ?></div>

				    		<p>
				    			<span id="issuem-leaky-paywall-articles-remaining-subscribe-link"><a href="<?php echo $subscription_url; ?>"><?php _e( 'Subscribe', 'issuem-lp-anc' ); ?></a></span> 
				    			| 
				    			<span id="issuem-leaky-paywall-articles-remaining-login-link"><a href="<?php echo $login_url; ?>"><?php _e( 'Login', 'issuem-lp-anc' ); ?></a></span>
				    		</p>

			    		</div>
			    	</div>
			    	<?php 
			    } else {
			    	?>

			    	<div id="issuem-leaky-paywall-articles-remaining-nag">
						<div id="issuem-leaky-paywall-articles-remaining-close">x</div>
						<div id="issuem-leaky-paywall-articles-remaining">
							<div id="issuem-leaky-paywall-articles-remaining-count"><?php echo $content_remaining; ?></div>
							<div id="issuem-leaky-paywall-articles-remaining-text"><?php echo $remaining_text; ?></div>

						</div>
						<div id="issuem-leaky-paywall-articles-remaining-subscribe-link"><a href="<?php echo esc_js( $subscription_url ); ?>"><?php _e( 'Subscribe today for full access', 'issuem-lp-anc' ); ?></a></div>
						<div id="issuem-leaky-paywall-articles-remaining-login-link"><a href="<?php echo esc_js( $login_url ); ?>"><?php _e( 'Current subscriber? Login here', 'issuem-lp-anc' ); ?></a></div>
					</div>
			    	<?php 
			    }
			  	
			  	$content = ob_get_contents();
				ob_end_clean();

				echo $content; 
				exit();
			}

		} else {
			
			
			if ( $lp_restrictions->is_restricted_default( $restrictions, $available_content, $post_type_id, $restricted_post_type, $post_id ) ) {

				$remaining_text = ( 1 === $content_remaining ) 
	        		?  sprintf( __( '%s Remaining', 'issuem-lp-anc' ), $post_type_obj->labels->singular_name )
	        		:  sprintf( __( '%s Remaining', 'issuem-lp-anc' ), $post_type_obj->labels->name );

				ob_start(); 
				?>
				<div class="acn-zero-remaining-overlay"></div>
				<div id="issuem-leaky-paywall-articles-zero-remaining-nag">
					<div id="issuem-leaky-paywall-articles-remaining-close">&nbsp;</div>
					<div id="issuem-leaky-paywall-articles-remaining">
						<div id="issuem-leaky-paywall-articles-remaining-count">0</div>
						<div id="issuem-leaky-paywall-articles-remaining-text"><?php echo $remaining_text; ?></div>
					</div>
					<div id="issuem-leaky-paywall-articles-remaining-subscribe-link"><a href="<?php echo $subscription_url; ?>"><?php _e( 'Subscribe today for full access', 'issuem-lp-anc' ); ?></a></div>
					<div id="issuem-leaky-paywall-articles-remaining-login-link"><a href="<?php echo $login_url; ?>"><?php _e( 'Current subscriber? Login here', 'issuem-lp-anc' ); ?></a></div>
				</div>
				<?php 
				$content = ob_get_contents();
				ob_end_clean();

				if ( 'no' == $settings['zero_remaining_popup'] ) {
					echo '';
				} else {
					echo $content; 
				}

				exit();
				
			} else {

				$remaining_text = ( 1 === $content_remaining ) 
	        		?  sprintf( __( '%s Remaining', 'issuem-lp-anc' ), $post_type_obj->labels->singular_name )
	        		:  sprintf( __( '%s Remaining', 'issuem-lp-anc' ), $post_type_obj->labels->name );
				
			    ob_start(); 

			   	if ( $settings['nag_theme'] == 'slim' ) {
			    	?>
			    	<div id="issuem-leaky-paywall-articles-remaining-nag">
			    		<div id="issuem-leaky-paywall-articles-remaining-close">x</div>
			    		<div id="issuem-leaky-paywall-articles-remaining-count">
			    			<p><?php echo $content_remaining; ?></p>
			    		</div>

				    	<div id="issuem-leaky-paywall-articles-remaining">
				    		
				    		<div id="issuem-leaky-paywall-articles-remaining-text"><?php echo $remaining_text; ?></div>

				    		<p>
				    			<span id="issuem-leaky-paywall-articles-remaining-subscribe-link"><a href="<?php echo $subscription_url; ?>"><?php _e( 'Subscribe', 'issuem-lp-anc' ); ?></a></span> 
				    			| 
				    			<span id="issuem-leaky-paywall-articles-remaining-login-link"><a href="<?php echo $login_url; ?>"><?php _e( 'Login', 'issuem-lp-anc' ); ?></a></span>
				    		</p>

			    		</div>
			    	</div>
			    	<?php 
			    } else {
			    	?>

			    	<div id="issuem-leaky-paywall-articles-remaining-nag">
						<div id="issuem-leaky-paywall-articles-remaining-close">x</div>
						<div id="issuem-leaky-paywall-articles-remaining">
							<div id="issuem-leaky-paywall-articles-remaining-count"><?php echo $content_remaining; ?></div>
							<div id="issuem-leaky-paywall-articles-remaining-text"><?php echo $remaining_text; ?></div>

						</div>
						<div id="issuem-leaky-paywall-articles-remaining-subscribe-link"><a href="<?php echo esc_js( $subscription_url ); ?>"><?php _e( 'Subscribe today for full access', 'issuem-lp-anc' ); ?></a></div>
						<div id="issuem-leaky-paywall-articles-remaining-login-link"><a href="<?php echo esc_js( $login_url ); ?>"><?php _e( 'Current subscriber? Login here', 'issuem-lp-anc' ); ?></a></div>
					</div>
			    	<?php 
			    }
			  	
			    $content = ob_get_contents();
				ob_end_clean();
				echo $content; 
				exit();
			}

		}
	    
		echo 'end';
		exit();

	}

	public function is_unblockable_content( $post_id ) 
	{
		$settings = get_leaky_paywall_settings();

		$unblockable_content = array(
			$settings['page_for_login'],
			$settings['page_for_subscription'],
			$settings['page_for_profile'],
			$settings['page_for_register']
		);

		if ( in_array( $post_id, $unblockable_content ) ) {
			return true;
		}

		return false;
	}


	public function process_requests() {
		
		global $leaky_paywall, $post;
		
		$lp_settings = get_leaky_paywall_settings();

		do_action( 'leaky_paywall_acn_before_process_requests', $lp_settings );
		
		if ( !is_singular() ) {
			return;
		}
							
		if ( current_user_can( apply_filters( 'leaky_paywall_acn_current_user_can_view_all_content', 'manage_options' ) ) ) { //Admins can see it all
			return;
		}

		// We don't ever want to block the login, subscription
		if ( is_page( array( $lp_settings['page_for_login'], $lp_settings['page_for_subscription'], $lp_settings['page_for_profile'], $lp_settings['page_for_register'] ) ) ) {
			return;
		}

		$site = leaky_paywall_get_current_site();
		
		$post_type_id = '';
		$restricted_post_type = '';
		$is_restricted = false;
		$content_remaining = 0;
		$allowed_value = 0;
		$available_content = array();
		
		$settings = $this->get_settings();

		// get the restrictions of the current logged in user's level
		$restrictions = leaky_paywall_subscriber_restrictions();

		
        if ( empty( $restrictions ) ) {
        	$restrictions = $lp_settings['restrictions']['post_types']; //default restrictions
        }    			
		// find out if they have any available content, which is content they have already read before the zero nag is triggered
		if ( !empty( $_COOKIE['lp_cookie' . $site] ) ) {
			$available_content = json_decode( stripslashes( $_COOKIE['lp_cookie' . $site] ), true );
		}else if( !empty( $_COOKIE['issuem_lp' . $site] ) ) {
			$available_content = json_decode( stripslashes( $_COOKIE['issuem_lp' . $site] ), true );							
		}


		// if restrictions are set, either by the user's level or the default, then see if the content currently being viewed is restricted or not
		if ( !empty( $restrictions ) ) {

			foreach( $restrictions as $key => $restriction ) {

				if ( is_singular( $restriction['post_type'] ) ) {

					$post_type_id = $key;
					
					// if the access is limited, see how much viewable content is remaining
					if ( 0 <= $restriction['allowed_value'] ) {

						$restricted_post_type = $restriction['post_type'];
						$allowed_value = $restriction['allowed_value'];
						$is_restricted = true;
						
						if ( !empty( $available_content[$restricted_post_type] ) ) {
							$content_remaining = $allowed_value - count( $available_content[$restricted_post_type] );
						} else {
							$content_remaining = $allowed_value;
						}
						break;
						
					} 
					
				}
				
			}
		
		}

		$level_ids = leaky_paywall_subscriber_current_level_ids();
		$visibility = get_post_meta( $post->ID, '_issuem_leaky_paywall_visibility', true );

		// if the current content has specific leaky paywall restrictions set, check those here
		if ( false !== $visibility && !empty( $visibility['visibility_type'] ) && 'default' !== $visibility['visibility_type'] ) {
									
			switch( $visibility['visibility_type'] ) {

				case 'only':
					$only = array_intersect( $level_ids, $visibility['only_visible'] );
					if ( empty( $only ) ) {
						add_filter( 'the_content', array( $this, 'the_content_paywall' ), 999 );
						do_action( 'leaky_paywall_is_restricted_content' );
						return;
					}
					break;
					
				case 'always':
					$always = array_intersect( $level_ids, $visibility['always_visible'] );
					if ( in_array( -1, $visibility['always_visible'] ) || !empty( $always ) ) { //-1 = Everyone
						return; //always visible, don't need process anymore
					}
					break;
				
				case 'onlyalways':
					$onlyalways = array_intersect( $level_ids, $visibility['only_always_visible'] );
					if ( empty( $onlyalways ) ) {
						add_filter( 'the_content', array( $this, 'the_content_paywall' ), 999 );
						do_action( 'leaky_paywall_is_restricted_content' );
						return;
					} else if ( !empty( $onlyalways ) ) {
						return; //always visible, don't need process anymore
					}
					break;
				
				
			}
			
		}

		$is_restricted = apply_filters( 'leaky_paywall_acn_filter_is_restricted', $is_restricted, $restrictions, $post );
		
		// if the current user's level access for the current content is unlimted, we don't need to show the nag
		if( -1 == $restrictions[$post_type_id]['allowed_value'] ) {
			return;
		}

		// content remaining cant be less than zero
		if ( $content_remaining < 0 ) {
			$content_remaining = 0;
		}

	    if ( $settings['nag_after_countdown'] <= $allowed_value - $content_remaining) {
	    	if(empty($available_content[$restricted_post_type])){
				return;		    		
	    	}else if ( 0 !== $content_remaining || array_key_exists( $post->ID, $available_content[$restricted_post_type] )  ) {

				add_action( 'wp_footer', array( $this, 'output_countdown_nag' ) );
			} else {

				if ( 'no' == $settings['zero_remaining_popup'] ) {
					return;
				}

				add_action( 'wp_enqueue_scripts', array( $this, 'zero_article_scripts' ) );
				add_action( 'wp_head', array( $this, 'output_zero_nag' ) );
				add_filter( 'leaky_paywall_subscriber_or_login_message', array( $this, 'leaky_paywall_subscriber_or_login_message' ), 10, 3 );
			}
						
		}

	}
	
	public function frontend_scripts() {

		$settings = $this->get_settings();

		wp_enqueue_script( 'leaky-paywall-article-countdown-nag', LP_ACN_URL . 'js/article-countdown-nag.js', array( 'jquery' ), LP_ACN_VERSION );

		$protocol = isset( $_SERVER['HTTPS'] ) ? 'https://' : 'http://';

		$params = array(
			'ajaxurl' => admin_url( 'admin-ajax.php', $protocol )
		);

		wp_localize_script( 'leaky-paywall-article-countdown-nag', 'lp_acn', $params );


		if ( $settings['nag_theme'] == 'slim' ) {
			wp_enqueue_style( 'leaky-paywall-article-countdown-nag', LP_ACN_URL . 'css/article-countdown-nag-slim.css', '', LP_ACN_VERSION );
		} else {
			wp_enqueue_style( 'leaky-paywall-article-countdown-nag', LP_ACN_URL . 'css/article-countdown-nag.css', '', LP_ACN_VERSION );
		}
					
	}
	
	public function output_zero_nag() {
		
		global $leaky_paywall, $post, $blog_id;
		
		
		$site = leaky_paywall_get_current_site();
					
		$lp_settings = $leaky_paywall->get_settings();
		$restrictions = leaky_paywall_subscriber_restrictions();
                    if ( empty( $restrictions ) )
                        $restrictions = $lp_settings['restrictions']['post_types']; //default restrictions

		$available_content = array();
		$content_remaining = 0;

		if ( !empty( $_COOKIE['lp_cookie' . $site] ) ) {
			$available_content = json_decode( stripslashes( $_COOKIE['lp_cookie' . $site] ), true );
		} else if( !empty( $_COOKIE['issuem_lp' . $site] ) ) {
			$available_content = json_decode( stripslashes( $_COOKIE['issuem_lp' . $site] ), true );							
		}
        
        if ( !empty( $restrictions['post_types'] ) ) {
						
			foreach( $restrictions['post_types'] as $key => $restriction ) {
				
				if ( is_singular( $restriction['post_type'] ) ) {
		
					if ( 0 <= $restriction['allowed_value'] ) {
					
						$post_type_id = $key;
						$restricted_post_type = $restriction['post_type'];
						
						if ( !empty( $available_content[$restricted_post_type] ) ) {
							$content_remaining = $restriction['allowed_value'] - count( $available_content[$restricted_post_type] );
						} else {
							$content_remaining = $restriction['allowed_value'];
						}
						break;
						
					}
					
				}
				
			}
		
		}
		
		$post_type_obj = get_post_type_object( $post->post_type );
        $remaining_text = ( 1 === $content_remaining ) 
        		?  sprintf( __( '%s Remaining', 'issuem-lp-anc' ), $post_type_obj->labels->singular_name )
        		:  sprintf( __( '%s Remaining', 'issuem-lp-anc' ), $post_type_obj->labels->name );
        
		$login_url = get_page_link( $lp_settings['page_for_login'] );
		$subscription_url = get_page_link( $lp_settings['page_for_subscription'] );
	
		?>
		
		<div class="acn-zero-remaining-overlay"></div>
		<div id="issuem-leaky-paywall-articles-zero-remaining-nag">
			<div id="issuem-leaky-paywall-articles-remaining-close">&nbsp;</div>
			<div id="issuem-leaky-paywall-articles-remaining">
				<div id="issuem-leaky-paywall-articles-remaining-count"><?php echo $content_remaining; ?></div>
				<div id="issuem-leaky-paywall-articles-remaining-text"><?php echo $remaining_text; ?></div>
			</div>
			<div id="issuem-leaky-paywall-articles-remaining-subscribe-link"><a href="<?php echo $subscription_url; ?>"><?php _e( 'Subscribe today for full access', 'issuem-lp-anc' ); ?></a></div>
			<div id="issuem-leaky-paywall-articles-remaining-login-link"><a href="<?php echo $login_url; ?>"><?php _e( 'Current subscriber? Login here', 'issuem-lp-anc' ); ?></a></div>
		</div>
		
		<?php
		
	}
	
	public function output_countdown_nag() {

		global $leaky_paywall, $post, $blog_id;
		
		$site = leaky_paywall_get_current_site();
		
		$lp_settings = $leaky_paywall->get_settings();
		$restrictions = leaky_paywall_subscriber_restrictions();

        if ( empty( $restrictions ) ) {
        	$restrictions = $lp_settings['restrictions']['post_types']; //default restrictions
        }   
		
		$available_content = array();
		$content_remaining = 0;

        if ( !empty( $_COOKIE['lp_cookie' . $site] ) ) {
			$available_content = json_decode( stripslashes( $_COOKIE['lp_cookie' . $site] ), true );
		}else if( !empty( $_COOKIE['issuem_lp' . $site] ) ) {
			$available_content = json_decode( stripslashes( $_COOKIE['issuem_lp' . $site] ), true );							
		}

        if ( !empty( $restrictions) ) {


			foreach( $restrictions as $key => $restriction ) {
				
				if ( is_singular( $restriction['post_type'] ) ) {

		
					if ( 0 <= $restriction['allowed_value'] ) {
					
						$post_type_id = $key;
						$restricted_post_type = $restriction['post_type'];
						if ( !empty( $available_content[$restricted_post_type] ) ) {
							$content_remaining = $restriction['allowed_value'] - count( $available_content[$restricted_post_type] );
						} else {
							$content_remaining = $restriction['allowed_value'];
						}
						break;
						
					}
					
				}
				
			}
		
		}

		if ( $content_remaining < 0 ) {
			$content_remaining = 0;
		}

		$post_type_obj = get_post_type_object( $post->post_type );
        $remaining_text = ( 1 === $content_remaining ) 
        		?  sprintf( __( '%s Remaining', 'issuem-lp-anc' ), $post_type_obj->labels->singular_name )
        		:  sprintf( __( '%s Remaining', 'issuem-lp-anc' ), $post_type_obj->labels->name );
        
		$login_url = get_page_link( $lp_settings['page_for_login'] );
		$subscription_url = get_page_link( $lp_settings['page_for_subscription'] );

		$settings = $this->get_settings();

		if ( $settings['nag_theme'] == 'slim' ) {
			?>
			<div id="issuem-leaky-paywall-articles-remaining-nag">
				<div id="issuem-leaky-paywall-articles-remaining-close">x</div>

				<div id="issuem-leaky-paywall-articles-remaining-count">
					<p><?php echo $content_remaining; ?></p>
				</div>

			<div id="issuem-leaky-paywall-articles-remaining">
				
				<div id="issuem-leaky-paywall-articles-remaining-text"><?php echo $remaining_text; ?></div>

				<p>
					<span id="issuem-leaky-paywall-articles-remaining-subscribe-link"><a href="<?php echo $subscription_url; ?>"><?php _e( 'Subscribe', 'issuem-lp-anc' ); ?></a></span> 
					| 
					<span id="issuem-leaky-paywall-articles-remaining-login-link"><a href="<?php echo $login_url; ?>"><?php _e( 'Login', 'issuem-lp-anc' ); ?></a></span>
				</p>

			</div>
			</div>

		<?php } else { ?>

			<div id="issuem-leaky-paywall-articles-remaining-nag">
				<div id="issuem-leaky-paywall-articles-remaining-close">x</div>
				<div id="issuem-leaky-paywall-articles-remaining">
					<div id="issuem-leaky-paywall-articles-remaining-count"><?php echo $content_remaining; ?></div>
					<div id="issuem-leaky-paywall-articles-remaining-text"><?php echo $remaining_text; ?></div>

				</div>
				<div id="issuem-leaky-paywall-articles-remaining-subscribe-link"><a href="<?php echo $subscription_url; ?>"><?php _e( 'Subscribe today for full access', 'issuem-lp-anc' ); ?></a></div>
				<div id="issuem-leaky-paywall-articles-remaining-login-link"><a href="<?php echo $login_url; ?>"><?php _e( 'Current subscriber? Login here', 'issuem-lp-anc' ); ?></a></div>
			</div>
		

		<?php }
		
	}
	
	public function leaky_paywall_subscriber_or_login_message( $new_content, $message, $content ) {
		return $content;
	}
	
	/**
	 * Get zeen101's Leaky Paywall - Article Countdown Nag options
	 *
	 * @since 1.0.0
	 */
	public function get_settings() {
		
		$defaults = array( 
			'nag_after_countdown' => '0',
			'nag_theme' => 'default',
			'zero_remaining_popup' => 'yes'
		);
	
		$defaults = apply_filters( 'leaky_paywall_article_countdown_nag_default_settings', $defaults );
		
		$settings = get_option( 'issuem-leaky-paywall-article-countdown-nag' );
											
		return wp_parse_args( $settings, $defaults );
		
	}
	
	/**
	 * Update zeen101's Leaky Paywall options
	 *
	 * @since 1.0.0
	 */
	public function update_settings( $settings ) {
		
		update_option( 'issuem-leaky-paywall-article-countdown-nag', $settings );
		
	}
	
	/**
	 * Create and Display settings page
	 *
	 * @since 1.0.0
	 */
	public function settings_div() {
		
		// Get the user options
		$settings = $this->get_settings();
		
		// Display HTML form for the options below
		?>
        <div id="modules" class="postbox">
        
            <div class="handlediv" title="Click to toggle"><br /></div>
            
            <h3 class="hndle"><span><?php _e( 'Article Countdown Nag', 'issuem-lp-anc' ); ?></span></h3>
            
            <div class="inside">
            
            <table id="leaky_paywall_article_countdown_nag" class="form-table">
            
                <tr>
                    <th><?php _e( 'Show Nag After Reading', 'issuem-lp-anc' ); ?></th>
                    <td>
                    <input class="small-text" type="number" value="<?php echo $settings['nag_after_countdown']; ?>" name="nag_after_countdown" /> <?php _e( 'restricted content items', 'issuem-lp-anc' ); ?>
                    <p class="description"><?php _e( 'Display the article countdown nag popup after the user has read the given number of restricted content items. <br>Set to 0 to show the nag the first time restricted content is viewed.' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th><?php _e( 'Nag Theme', 'issuem-lp-anc' ); ?></th>
                    <td>
                  
                        <select id="nag_theme" name="nag_theme">
                             <option value="default" <?php selected( 'default' === $settings['nag_theme'] ); ?>><?php _e( 'Default', 'issuem-lp-anc' ); ?></option>
                             <option value="slim" <?php selected( 'slim' === $settings['nag_theme'] ); ?>><?php _e( 'Slim', 'issuem-lp-anc' ); ?></option>
                        </select>

                    <p class="description"><?php _e( 'Choose theme for article countdown nag popup.' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th><?php _e( 'Zero Remaining Popup', 'issuem-lp-anc' ); ?></th>
                    <td>
                   		<select id="zero_remaining_popup" name="zero_remaining_popup">
                             <option value="yes" <?php selected( 'yes' === $settings['zero_remaining_popup'] ); ?>><?php _e( 'Yes', 'issuem-lp-anc' ); ?></option>
                             <option value="no" <?php selected( 'no' === $settings['zero_remaining_popup'] ); ?>><?php _e( 'No', 'issuem-lp-anc' ); ?></option>
                        </select>
                    	<p class="description"><?php _e( 'Display the zero remaining popup over the top of the page when the content limit is reached. If set to "No", the user will instead see the default Leaky Paywall subscribe nag in the content.' ); ?></p>
                    </td>
                </tr>
                
            </table>
          
            </div>
            
        </div>
		<?php
		
	}
	
	public function update_settings_div() {

		if(isset($_GET['tab'])) {
			$tab = $_GET['tab'];
		} else if ( $_GET['page'] == 'issuem-leaky-paywall' ) {
			$tab = 'general';
		} else {
			$tab = '';
		}

		if ( $tab != 'general' ) {
			return;
		}
	
		// Get the user options
		$settings = $this->get_settings();
			
		if ( !empty( $_POST['nag_after_countdown'] ) ) {
			$settings['nag_after_countdown'] = absint( trim( $_POST['nag_after_countdown'] ) );
		} else {
			$settings['nag_after_countdown'] = '0';
		}

		if ( isset( $_POST['nag_theme'] ) ) {
			$settings['nag_theme'] = sanitize_text_field( $_POST['nag_theme'] );
		}

		if ( isset( $_POST['zero_remaining_popup'] ) ) {
			$settings['zero_remaining_popup'] = sanitize_text_field( $_POST['zero_remaining_popup'] );
		}
		
		$this->update_settings( $settings );
		
	}
	
}