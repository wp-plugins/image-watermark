<?php
/*
Plugin Name: Image Watermark
Description: Image Watermark allows you to automatically watermark images uploaded to the WordPress Media Library.
Version: 1.0.0
Author: dFactory
Author URI: http://www.dfactory.eu/
Plugin URI: http://www.dfactory.eu/plugins/image-watermark/
License: MIT License
License URI: http://opensource.org/licenses/MIT

Image Watermark
Copyright (C) 2013, Digital Factory - info@digitalfactory.pl

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/


global $wp_version;

if(version_compare(PHP_VERSION, '5.0', '<') || version_compare($wp_version, '3.5', '<'))
{
	wp_die(__('Sorry, Image Watermark plugin requires at least PHP 5.0 and WP 3.5 or higher.'));
}

class ImageWatermark
{
	private $_messages = array();
	private $_image_sizes = array('thumbnail', 'medium', 'large', 'fullsize');
	private $_watermark_positions = array (
		'x' => array('left', 'center', 'right'),
		'y' => array('top', 'middle', 'bottom'),
	);
	protected $_options = array(
		'df_watermark_on' => array(),
		'df_watermark_cpt_on' => array(),
		'df_watermark_type' => 'image',
		'df_watermark_image' => array(
			'url' => 0,
			'width' => 80,
			'plugin_off' => 1,
			'position' => 'bottom_right',
			'watermark_size_type' => 2,
			'offset_width' => 0,
			'offset_height' => 0,
			'absolute_width' => 0,
			'absolute_height' => 0,
			'transparent' => 50
		),
		'df_image_protection' => array(
			'rightclick' => 0,
			'draganddrop' => 0,
			'forlogged' => 0,
		)
	);


	public function __construct()
	{
		// register installer function
		register_activation_hook(__FILE__, array(&$this, 'activate_watermark'));

		//actions
		add_action('plugins_loaded', array(&$this, 'load_textdomain'));
		add_action('admin_enqueue_scripts', array(&$this, 'watermark_scripts_styles'));
		add_action('admin_menu', array(&$this, 'watermark_admin_menu'));
		add_action('wp_footer', array(&$this, 'watermark_no_right_click'));

		// check if post_id is "-1", meaning we're uploading watermark image
		if(!(array_key_exists('post_id', $_REQUEST) && $_REQUEST['post_id'] == -1))
		{
			add_filter('wp_handle_upload_prefilter', array(&$this, 'delay_upload_filter'), 10, 1);
		}
	}


	public function delay_upload_filter($value)
	{
		if(isset($_REQUEST['post_id']))
		{
			$option = get_option('df_watermark_cpt_on');

			if($option[0] === 'everywhere' || in_array(get_post_type($_REQUEST['post_id']), array_keys($option)) === TRUE)
			{
				add_filter('wp_generate_attachment_metadata', array(&$this, 'apply_watermark'));
			}
		}

		return $value;
	}


	public function load_textdomain()
	{
		load_plugin_textdomain('image-watermark', FALSE, basename(dirname(__FILE__)).'/languages');
	}


	// Enqueue scripts and styles
	public function watermark_scripts_styles($page)
	{
		if($page !== 'settings_page_watermark-options')
        	return;

		wp_enqueue_media();

		wp_enqueue_script(
			'upload-manager',
			plugins_url('/js/upload-manager.js', __FILE__)
		);

		wp_enqueue_script(
			'wp-like',
			plugins_url('js/wp-like.js', __FILE__),
			array('jquery', 'jquery-ui-core', 'jquery-ui-button')
		);

		wp_enqueue_script(
			'watermark-admin-script',
			plugins_url('js/admin.js', __FILE__),
			array('jquery', 'wp-like')
		);

		wp_localize_script(
			'upload-manager',
			'upload_manager_args',
			array(
				'title'			=> __('Select watermark', 'image-watermark'),
				'originalSize'	=> __('Original size', 'image-watermark'),
				'frame'			=> 'select',
				'button'		=> array('text' => __('Add watermark', 'image-watermark')),
				'multiple'		=> FALSE,
			)
		);

		wp_enqueue_style('thickbox');
		wp_enqueue_style('watermark-style', plugins_url('css/style.css', __FILE__));
		wp_enqueue_style('wp-like-ui-theme', plugins_url('css/wp-like-ui-theme.css', __FILE__));
	}


	// Create options page in menu & print styles & scripts
	public function watermark_admin_menu()
	{
		$watermark_settings_page = add_options_page(
			__('Image Watermark Options', 'image-watermark'),
			__('Watermark', 'image-watermark'),
			'manage_options',
			'watermark-options',
			array(&$this, 'watermark_options_page')
		);
	}


	// Block right click on images
	public function watermark_no_right_click()
	{
		$options = $this->get_option('df_image_protection');

		if(($options['forlogged'] == 0 && is_user_logged_in()) || ($options['draganddrop'] == 0 && $options['rightclick'] == 0))
		{
			return;
		}
		
		// Oj nieladnie :)
		// to wszystko powinno siedziec np. w tym no-right-click.js lub oddzielnym pliku js, enqueue_script? (jesli to cos robi na froncie)

		echo '
		<script language="javascript" type="text/javascript">
			var df_nrc_extra="'.($options['rightclick'] == 1 ? 'Y' : 'N').'";
			var df_nrc_drag="'.($options['draganddrop'] == 1 ? 'Y' : 'N').'";
		</script>
		<script language="javascript" type="text/javascript" src="'.addslashes(WP_PLUGIN_URL.'/image-watermark'.'/js/no-right-click.js').'">
		</script>';
	}


	private function getCustomPostTypes()
	{
		return array_merge(array('post', 'page'), get_post_types(array('_builtin' => FALSE), 'names'));
	}


	/**
	 * Display options page
	 */
	public function watermark_options_page()
	{
		// if user clicked "Save Changes" save them
		if(isset($_POST['submit']))
		{
			foreach($this->_options as $option => $value)
			{
				if(array_key_exists($option, $_POST))
				{
					switch($option)
					{
						case 'df_watermark_on':
						{
							$tmp = array();

							foreach($this->_image_sizes as $size)
							{
								if(in_array($size, array_keys($_POST[$option])))
								{
									$tmp[$size] = 1;
								}
							}

							update_option($option, $tmp);
							break;
						}
						case 'df_watermark_cpt_on':
						{
							if($_POST['df_watermark_cpt_on'] === 'everywhere')
							{
								update_option($option, array('everywhere'));
							}
							elseif($_POST['df_watermark_cpt_on'] === 'specific')
							{
								if(isset($_POST['df_watermark_cpt_on_type']))
								{
									$tmp = array();

									foreach($this->getCustomPostTypes() as $cpt)
									{
										if(in_array($cpt, array_keys($_POST['df_watermark_cpt_on_type'])))
										{
											$tmp[$cpt] = 1;
										}
									}

									if(count($tmp) === 0) update_option($option, array('everywhere'));
									else update_option($option, $tmp);
								}
								else update_option($option, array('everywhere'));
							}

							break;
						}
						case 'df_watermark_image':
						{
							$tmp = array();

							foreach($this->_options[$option] as $image_option => $value_i)
							{
								switch($image_option)
								{
									case 'width':
									case 'plugin_off':
									case 'watermark_size_type':
									case 'offset_width':
									case 'offset_height':
									case 'absolute_width':
									case 'absolute_height':
									case 'transparent':
										$tmp[$image_option] = (int)(isset($_POST[$option][$image_option]) ? $_POST[$option][$image_option] : $this->_options[$option][$image_option]);
										break;

									case 'url':
										$tmp[$image_option] = (isset($_POST[$option][$image_option]) ? (int)$_POST[$option][$image_option] : $this->_options[$option][$image_option]);
										break;

									case 'position':
										$positions = array();

										foreach($this->_watermark_positions['y'] as $position_y)
										{
											foreach($this->_watermark_positions['x'] as $position_x)
											{
												$positions[] = $position_y.'_'.$position_x;
											}
										}

										$tmp[$image_option] = (isset($_POST[$option][$image_option]) && in_array($_POST[$option][$image_option], $positions) ? $_POST[$option][$image_option] : $this->_options[$option][$image_option]);
										break;
								}
							}

							update_option($option, $tmp);
							break;
						}
						case 'df_image_protection':
						{
							$tmp = array();

							foreach($this->_options[$option] as $protection => $value_p)
							{
								if(in_array($protection, array_keys($_POST[$option])))
								{
									$tmp[$protection] = 1;
								}
							}

							update_option($option, $tmp);
							break;
						}
					}
				}
				else
				{
					update_option($option, $value);
				}
			}
		}

		if(!extension_loaded('gd'))
		{
			$this->_messages['error'][] = __('Image Watermark will not work properly without GD PHP extension.', 'image-watermark');
		}

		foreach($this->_messages as $namespace => $messages)
		{
			foreach($messages as $message)
			{
				echo '
				<div class="'.$namespace.'">
				<p>
					<strong>'.$message.'</strong>
				</p>
				</div>';
			}
		}

		$watermark_image = $this->get_option('df_watermark_image'); 
		$image_protection = $this->get_option('df_image_protection');
	?>
	<div class="wrap">
        <div id="icon-options-general" class="icon32"><br /></div>
        <h2><?php echo __('Image Watermark Settings', 'image-watermark'); ?></h2>
        <div id="image-watermark" class="postbox-container">
        	<div class="metabox-holder">
                <form method="post" action="">
                	<h3><?php echo __('General settings', 'image-watermark'); ?></h3>
                    <table id="watermark-general-table" class="form-table">
                        <tr valign="top">
                            <th scope="row"><?php echo __('Enable watermark', 'image-watermark'); ?></th>
                            <td class="wr_width">
                                <fieldset class="wr_width">
                                    <legend class="screen-reader-text"><span><?php echo __('Width', 'image-watermark'); ?></span></legend>
                                    <div id="run-watermark">
                                        <label for="plugin_on"><?php echo __('on', 'image-watermark'); ?></label>
                                        <input type="radio" id="plugin_on" value="0" name="df_watermark_image[plugin_off]" <?php checked($watermark_image['plugin_off'], 0, TRUE); ?> />
                                        <label for="plugin_off"><?php echo __('off', 'image-watermark'); ?></label>
                                        <input type="radio" id="plugin_off" value="1" name="df_watermark_image[plugin_off]" <?php checked($watermark_image['plugin_off'], 1, TRUE); ?> />
                                    </div>
                                    <p class="howto"><?php echo __('Enable or disable watermark for uploaded images.', 'image-watermark'); ?></p>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                    <table id="watermark-for-table" class="form-table">
                        <tr valign="top">
                            <th scope="row"><?php echo __('Enable watermark for', 'image-watermark'); ?></th>
                            <td class="wr_width">
                                <fieldset class="wr_width">
                                	<legend class="screen-reader-text"><span><?php echo __('Enable watermark for', 'image-watermark'); ?></span></legend>
                                    <?php $watermark_on = array_keys($this->get_option('df_watermark_on')); ?>
                                    <div id="thumbnail-select">
										<?php foreach($this->_image_sizes as $image_size) : ?>
                                            <input name="df_watermark_on[<?php echo $image_size; ?>]" type="checkbox" id="<?php echo $image_size; ?>" value="1" <?php echo (in_array($image_size, $watermark_on) ? ' checked="checked"' : ''); ?> />
                                            <label for="<?php echo $image_size; ?>"><?php echo $image_size; ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="howto"><?php echo __('Check image sizes on which watermark should appear.', 'image-watermark'); ?></p>
									<legend class="screen-reader-text"><span><?php echo __('Enable watermark for', 'image-watermark'); ?></span></legend>
                                    <?php $watermark_cpt_on = array_keys($this->get_option('df_watermark_cpt_on'));
									if(in_array('everywhere', $watermark_cpt_on) && count($watermark_cpt_on) === 1)
									{ $first_checked = TRUE; $second_checked = FALSE; $watermark_cpt_on = array(); }
									else { $first_checked = FALSE; $second_checked = TRUE; } ?>
									<div id="cpt-specific">
										<input id="df_option_everywhere" type="radio" name="df_watermark_cpt_on" value="everywhere" <?php echo ($first_checked === TRUE ? 'checked="checked"' : ''); ?>/><label for="df_option_everywhere"><?php _e('everywhere', 'image-watermark'); ?></label>
										<input id="df_option_cpt" type="radio" name="df_watermark_cpt_on" value="specific" <?php echo ($second_checked === TRUE ? 'checked="checked"' : ''); ?> /><label for="df_option_cpt"><?php _e('on selected post types only', 'image-watermark'); ?></label>
									</div>
									<div id="cpt-select" <? echo ($second_checked === FALSE ? 'style="display: none;"' : ''); ?>>
										<?php foreach($this->getCustomPostTypes() as $cpt) : ?>
                                            <input name="df_watermark_cpt_on_type[<?php echo $cpt; ?>]" type="checkbox" id="<?php echo $cpt; ?>" value="1" <?php echo (in_array($cpt, $watermark_cpt_on) ? ' checked="checked"' : ''); ?> />
                                            <label for="<?php echo $cpt; ?>"><?php echo $cpt; ?></label>
                                        <?php endforeach; ?>
										</div>
                                    <p class="howto"><?php echo __('Check custom post types on which watermark should be applied to uploaded images.', 'image-watermark'); ?></p>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                    <hr />
                    <h3><?php echo __('Watermark position', 'image-watermark'); ?></h3>
                    <table id="watermark-position-table" class="form-table">
                        <tr valign="top">
                            <th scope="row"><?php echo __('Watermark alignment','image-watermark'); ?></th>
                            <td>
                                <fieldset>
                                <legend class="screen-reader-text"><span><?php __('Watermark alignment','image-watermark'); ?></span></legend>
                                    <table id="watermark_position" border="1">
                                        <?php $watermark_position = $watermark_image['position']; ?>
                                        <?php foreach($this->_watermark_positions['y'] as $y) : ?>
                                        <tr>
                                            <?php foreach($this->_watermark_positions['x'] as $x) : ?>
                                            <td title="<?php echo ucfirst($y . ' ' . $x); ?>">
                                                <input name="df_watermark_image[position]" type="radio" value="<?php echo $y . '_' . $x; ?>"<?php echo ($watermark_position == $y . '_' . $x ? ' checked="checked"' : NULL); ?> />
                                            </td>
                                            <?php endforeach; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    </table>
                                    <p class="howto"><?php echo __('Choose the position of watermark image.','image-watermark'); ?></p>
                                </fieldset>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php echo __('Watermark offset','image-watermark'); ?></th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><span><?php echo __('Watermark offset','image-watermark'); ?></span></legend>
                                    <?php echo __('x:','image-watermark'); ?> <input type="text" size="5"  name="df_watermark_image[offset_width]" value="<?php echo $watermark_image['offset_width']; ?>"> <?php echo __('px','image-watermark'); ?>
                                    <br />
                                    <?php echo __('y:','image-watermark'); ?> <input type="text" size="5"  name="df_watermark_image[offset_height]" value="<?php echo $watermark_image['offset_height']; ?>"> <?php echo __('px','image-watermark'); ?>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                    <hr />
                    <h3><?php echo __('Watermark image','image-watermark'); ?></h3>
                    <p class="howto"><?php echo __('Configure your watermark image. Allowed file formats are: jpg, png, gif.','image-watermark'); ?></p>
                    <table id="watermark-image-table" class="form-table">
                        <tr valign="top">
                            <th scope="row"><?php echo __('Watermark image','image-watermark'); ?></th>
                            <td>
								<input id="upload_image" type="hidden" name="df_watermark_image[url]" value="<?php echo (int)$watermark_image['url']; ?>" />
                                <input id="upload_image_button" type="button" class="button button-secondary" value="<?php echo __('Select image','image-watermark'); ?>" />
                                <input id="turn_off_image_button" type="button" class="button button-secondary" value="<?php echo __('Turn off image','image-watermark'); ?>" />
								<p class="howto"><?php _e('You have to save changes after the selection or removal of the image.', 'image-watermark'); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php echo __('Watermark preview', 'image-watermark'); ?></th>
                            <td class="wr_width">
                                <fieldset class="wr_width">
                                    <legend class="screen-reader-text"><span><?php echo __('Watermark Preview', 'image-watermark'); ?></span></legend>
                                    <div id="previewImg_imageDiv">
                                        <?php if($watermark_image['url'] !== NULL && $watermark_image['url'] != 0) {
										$image = wp_get_attachment_image_src($watermark_image['url'], array(300, 300), FALSE);
										?>
                                        <img id="previewImg_image" src="<? echo $image[0]; ?>" alt="" width="300" />
                                        <? } else { ?>
                                        <span id="previewImg_image">
                                        <?php _e('Watermak has not been selected yet.', 'image-watermark');?>
                                        </span>
                                        <?php } ?>
										<span id="previewImg_image_hidden" style="display: none;">
                                        <?php _e('Watermak has not been selected yet.', 'image-watermark');?>
                                        </span>
                                    </div>
                                    <p id="previewImg_imageDivSize" class="howto"></p>
                                </fieldset>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php echo __('Watermark size', 'image-watermark'); ?></th>
                            <td class="wr_width">
                                <fieldset class="wr_width">
                                <legend class="screen-reader-text"><span><?php echo __('Width', 'image-watermark'); ?></span></legend>
                                    <div id="watermark-type">
                                    <label for="type1"><?php echo __('original', 'image-watermark'); ?></label>
                                    <input type="radio" id="type1" value="0" name="df_watermark_image[watermark_size_type]" <?php checked($watermark_image['watermark_size_type'], 0, TRUE); ?> />
                                    <label for="type2"><?php echo __('custom', 'image-watermark'); ?></label>
                                    <input type="radio" id="type2" value="1" name="df_watermark_image[watermark_size_type]" <?php checked($watermark_image['watermark_size_type'], 1, TRUE); ?> />
                                    <label for="type3"><?php echo __('scaled', 'image-watermark'); ?></label>
                                    <input type="radio" id="type3" value="2" name="df_watermark_image[watermark_size_type]" <?php checked($watermark_image['watermark_size_type'], 2, TRUE); ?> />
                                    </div>
                                    <p class="howto"><?php echo __('Select method of aplying watermark size.', 'image-watermark'); ?></p>
                                </fieldset>
                            </td>
                        </tr>
                        <tr valign="top" id="watermark_size_custom">
                            <th scope="row"><?php echo __('Watermark custom size', 'image-watermark'); ?></th>
                            <td class="wr_width">
                                <fieldset class="wr_width">
                                <legend class="screen-reader-text"><span><?php echo __('Width', 'image-watermark'); ?></span></legend>
                                    <?php echo __('x:', 'image-watermark'); ?> <input type="text" size="5"  name="df_watermark_image[absolute_width]" value="<?php echo $watermark_image['absolute_width']; ?>"> <?php echo __('px', 'image-watermark'); ?>
                                    <br />
                                    <?php echo __('y:', 'image-watermark'); ?> <input type="text" size="5"  name="df_watermark_image[absolute_height]" value="<?php echo $watermark_image['absolute_height']; ?>"> <?php echo __('px','image-watermark'); ?>
                                </fieldset>
                                <p class="howto"><?php echo __('Those dimensions will be used if "custom" method is selected above.', 'image-watermark'); ?></p>
                            </td>
                        </tr>
                        <tr valign="top" id="watermark_size_scale">
                            <th scope="row"><?php echo __('Scale of watermark in relation to image width', 'image-watermark'); ?></th>
                            <td class="wr_width">
                                <fieldset class="wr_width">
                                <legend class="screen-reader-text"><span><?php echo __('Width', 'image-watermark'); ?></span></legend>
                                    <input type="text" size="5"  name="df_watermark_image[width]" value="<?php echo $watermark_image['width']; ?>">%
                                </fieldset>
                                <p class="howto"><?php echo __('This value will be used if "scaled" method if selected above. <br />Enter a number ranging from 0 to 100. 100 makes width of watermark image equal to width of the image it is applied to.','image-watermark'); ?></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php echo __('Watermark transparency / opacity','image-watermark'); ?></th>
                            <td class="wr_width">
                                <fieldset class="wr_width">
                                    <input type="text" size="5"  name="df_watermark_image[transparent]" value="<?php echo $watermark_image['transparent']; ?>">%
                                </fieldset>
                                <p class="howto"><?php echo __('Enter a number ranging from 0 to 100. 0 makes watermark image completely transparent, 100 shows it as is.','image-watermark'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <input type="hidden" name="action" value="update" />
                    <hr />
                    <h3><?php echo __('Image protection','image-watermark'); ?></h3>
                    <table id="watermark-protection-table" class="form-table">
                        <tr>
                            <th><?php echo __('Disable right mouse click on images','image-watermark'); ?></th>
                            <td><input type="checkbox" <?php checked($image_protection['rightclick'], 1, TRUE); ?> value="1" name="df_image_protection[rightclick]"></td>
                        </tr>
                        <tr>
                            <th><?php echo __('Prevent drag and drop','image-watermark'); ?></th>
                            <td><input type="checkbox" <?php checked($image_protection['draganddrop'], 1, TRUE); ?> value="1" name="df_image_protection[draganddrop]"></td>
                        </tr>
                        <tr>
                            <th><?php echo __('Disable image protection for logged-in users','image-watermark'); ?></th>
                            <td><input type="checkbox" <?php checked($image_protection['forlogged'], 1, TRUE); ?> value="1" name="df_image_protection[forlogged]"></td>
                        </tr>
                    </table>
                    <hr />
                    <input type="submit" id="watermark-submit" class="button button-primary" name="submit" value="<?php echo __('Save Changes','image-watermark'); ?>" />
                </form>
            </div>
        </div>
        
        <div id="df-credits" class="postbox-container">
        	<h3 class="metabox-title"><?php _e('Image Watermark','image-watermark'); ?></h3>
            <div class="inner">
                <h3><?php _e('Need support?','image-watermark'); ?></h3>
                <p><?php _e('If you are having problems with this plugin, please talk about them in the','image-watermark'); ?> <a href="http://dfactory.eu/support/" target="_blank" title="<?php _e('Support forum','image-watermark'); ?>"><?php _e('Support forum','image-watermark'); ?></a>.</p>
                <hr />
                <h3><?php _e('Do you like this plugin?','image-watermark'); ?></h3>
                <p><?php _e('Rate it 5 on WordPress.org','image-watermark'); ?><br />
				<?php _e('Blog about it & link to the','image-watermark'); ?> <a href="http://dfactory.eu/plugins/image-watermark/" target="_blank" title="<?php _e('plugin page','image-watermark'); ?>"><?php _e('plugin page','image-watermark'); ?></a>.<br />
                <?php _e('Check out our other','image-watermark'); ?> <a href="http://dfactory.eu/plugins/" target="_blank" title="<?php _e('WordPress plugins','image-watermark'); ?>"><?php _e('WordPress plugins','image-watermark'); ?></a>.
                </p>
                
                <hr />
                <p class="df-link"><?php echo __('Created by', 'restrict-widgets'); ?><a href="http://www.dfactory.eu" target="_blank" title="dFactory - Quality plugins for WordPress"><img src="<?php echo plugins_url( 'images/logo-dfactory.png' , __FILE__ ); ?>" title="dFactory - Quality plugins for WordPress" alt="dFactory - Quality plugins for WordPress" /></a></p>
                
			</div>
      	</div>
        
	</div>
	<?php
	}


	/**
	 * Get option by setting name with default value if option is unexistent
	 *
	 * @param string $setting
	 * @return mixed
	 */
	protected function get_option($setting)
	{
		if(is_array($this->_options[$setting]))
		{
			$options = array_merge($this->_options[$setting], get_option($setting));
		}
		else
		{
			$options = get_option($setting, $this->_options[$setting]);
		}

		return $options;
	}


	/**
	 * Get array with options
	 *
	 * @return array
	 */
	private function get_options()
	{
		$options = array();

		// loop through default options and get user defined options
		foreach($this->_options as $option => $value)
		{
			$options[$option] = $this->get_option($option);
		}

		return $options;
	}


	/**
	 * Plugin installation method
	 */
	public function activate_watermark()
	{
		// record install time
		add_option('df_watermark_installed', time(), NULL, 'no');
		add_option('df_watermark_cpt_on', array('everywhere'), NULL, 'no');

		// loop through default options and add them into DB
		foreach($this->_options as $option => $value)
		{
			add_option($option, $value, NULL, 'no');
		}
	}


	/**
	 * Apply watermark to selected image sizes
	 *
	 * @param array $data
	 * @return array
	 */
	public function apply_watermark($data)
	{
		// get settings for watermarking
		$upload_dir = wp_upload_dir();
		$watermark_on = $this->get_option('df_watermark_on');

		// loop through image sizes...
		foreach($watermark_on as $image_size => $on)
		{
			if($on === 1)
			{
				switch($image_size)
				{
					case 'fullsize':
						$filepath = $upload_dir['basedir'].DIRECTORY_SEPARATOR.$data['file'];
						break;

					default:
						if(!empty($data['sizes']) && array_key_exists($image_size, $data['sizes']))
						{
							$filepath = $upload_dir['basedir'].DIRECTORY_SEPARATOR.dirname($data['file']).DIRECTORY_SEPARATOR.$data['sizes'][$image_size]['file'];
						}
						else
						{
							// early getaway
							continue 2;
						}
				}

				// ...and apply watermark
				$this->do_watermark($filepath);
			}
		}

		// pass forward attachment metadata
		return $data;
	}


	/**
	* Apply watermark to certain image
	*
	* @param string $filepath
	* @return boolean
	*/
	public function do_watermark($filepath)
	{
		// get image mime type
		$mime_type = wp_check_filetype($filepath);
		$mime_type = $mime_type['type'];
		// get watermark settings
		$options = $this->get_options();

		if($options['df_watermark_image']['plugin_off'] === 1)
		{
			return TRUE;
		}

		// get image resource
		$image = $this->get_image_resource($filepath, $mime_type);

		if($options['df_watermark_type'] === 'image')
		{
			// add watermark image to image
			$this->add_watermark_image($image, $options);
		}

		// save watermarked image
		return $this->save_image_file($image, $mime_type, $filepath);
	}


	/**
	 * Add watermark image to image
	 *
	 * @param resource $image
	 * @param array $opt
	 * @return resource
	 */
	private function add_watermark_image($image, array $opt)
	{
		// get size and url of watermark
		$size_type = $opt['df_watermark_image']['watermark_size_type'];
		$url = $opt['df_watermark_image']['url'];
		$file = pathinfo($url);
		$ext = $file['extension'];

		switch($ext)
		{
			case 'jpg':
			case 'jpeg':
				$watermark = imagecreatefromjpeg("$url");
				break;

			case 'gif':
				$watermark = imagecreatefromgif("$url");
				break;

			default:
				$watermark = imagecreatefrompng("$url");
		}

		$watermark_width = imagesx($watermark);
		$watermark_height = imagesy($watermark);
		$img_width = imagesx($image);
		$img_height = imagesy($image);

		if($size_type == 1) // custom
		{
			$w = $opt['df_watermark_image']['absolute_width'];
			$h = $opt['df_watermark_image']['absolute_height'];
		}
		elseif($size_type == 2) // scale
		{
			$size = $opt['df_watermark_image']['width'] / 100;
			$ratio = (($img_width * $size) / $watermark_width);
			$w = ($watermark_width * $ratio);
			$h = ($watermark_height * $ratio);
		}
		else
		{
			$size = 1;
			$w = ($watermark_width * $size);
			$h = ($watermark_height * $size);
		}

		$offset_w = $opt['df_watermark_image']['offset_width'];
		$offset_h = $opt['df_watermark_image']['offset_height'];

		switch($opt['df_watermark_image']['position'])
		{
			case 'top_left':
				$dest_x = $dest_y = 0;
				break;

			case 'top_center':
				$dest_x = ($img_width / 2) - ($w / 2);
				$dest_y = 0;
				break;

			case 'top_right':
				$dest_x = $img_width - $w;
				$dest_y = 0;
				break;

			case 'middle_left':
				$dest_x = 0;
				$dest_y = ($img_height / 2) - ($h / 2);
				break;

			case 'middle_right':
				$dest_x = $img_width - $w;
				$dest_y = ($img_height / 2) - ($h / 2);
				break;

			case 'bottom_left':
				$dest_x = 0;
				$dest_y = $img_height - $h;
				break;

			case 'bottom_center':
				$dest_x = ($img_width / 2) - ($w / 2);
				$dest_y = $img_height - $h;
				break;

			case 'bottom_right':
				$dest_x = $img_width - $w;
				$dest_y = $img_height - $h;
				break;

			default:
				$dest_x = ($img_width / 2) - ($w / 2);
				$dest_y = ($img_height / 2) - ($h / 2);
		}

		$dest_x += $offset_w;
		$dest_y += $offset_h;
		$resized = $this->resize($watermark, $url, $w, $h);
		$this->imagecopymerge_alpha($image, $resized, $dest_x, $dest_y, 0, 0, $w, $h, $opt['df_watermark_image']['transparent']);

		return $image;
	}


	private function imagecopymerge_alpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct)
	{
		// creating a cut resource
		$cut = imagecreateTRUEcolor($src_w, $src_h);
		// copying relevant section from background to the cut resource
		imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h);
		// copying relevant section from watermark to the cut resource
		imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h);
		// insert cut resource to destination image
		imagecopymerge($dst_im, $cut, $dst_x, $dst_y, 0, 0, $src_w, $src_h, $pct);
	}


	private function resize($im, $path, $nWidth, $nHeight)
	{
		$imgInfo = getimagesize($path);
		$newImg = imagecreateTRUEcolor($nWidth, $nHeight);

		// Check if this image is PNG or GIF, then set if Transparent
		if($imgInfo[2] == 1 || $imgInfo[2] == 3)
		{
			imagealphablending($newImg, FALSE);
			imagesavealpha($newImg, TRUE);
			$transparent = imagecolorallocatealpha($newImg, 255, 255, 255, 127);
			imagefilledrectangle($newImg, 0, 0, $nWidth, $nHeight, $transparent);
		}

		imagecopyresampled($newImg, $im, 0, 0, 0, 0, $nWidth, $nHeight, $imgInfo[0], $imgInfo[1]);
		return $newImg;
	}


	/**
	* Get image resource accordingly to mimetype
	*
	* @param string $filepath
	* @param string $mime_type
	* @return resource
	*/
	private function get_image_resource($filepath, $mime_type)
	{
		switch($mime_type)
		{
			case 'image/jpeg':
				return imagecreatefromjpeg($filepath);

			case 'image/png':
				$res = imagecreatefrompng($filepath);
				$transparent = imagecolorallocatealpha($res, 255, 255, 254, 127);
				imagefilledrectangle($res, 0, 0, imagesx($res), imagesy($res), $transparent);
				return $res;

			case 'image/gif':
				$res = imagecreatefromgif($filepath);
				$transparent = imagecolorallocatealpha($res, 255, 255, 254, 127);
				imagefilledrectangle($res, 0, 0, imagesx($res), imagesy($res), $transparent);
				return $res;

			default:
				return FALSE;
		}
	}


	/**
	 * Save image from image resource
	 *
	 * @param resource $image
	 * @param string $mime_type
	 * @param string $filepath
	 * @return boolean
	 */
	private function save_image_file($image, $mime_type, $filepath)
	{
		switch($mime_type)
		{
			case 'image/jpeg':
				return imagejpeg($image, $filepath, apply_filters('jpeg_quality', 90));

			case 'image/png':
				return imagepng($image, $filepath);

			case 'image/gif':
				return imagegif($image, $filepath);

			default:
				return FALSE;
		}
	}
}

$wpm_in = new ImageWatermark();
?>