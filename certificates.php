<?php

/*
 Plugin Name: Custom Course Certificates
 Author: Nathan Smith
 Version: 0.1
*/

class Harvest_CourseCertificates {
	const POST_TYPE = 'harvest_certificates';

	public function bootstrap() {
		add_action('init', [$this, 'init']);
		add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
		add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
		add_action('save_post', [$this, 'saveMetaBoxes'], 10, 2);
		add_action('wp_ajax_myprefix_get_image', [$this, 'previewImageAjax']);
		add_filter('wpcw_certificate_generated_url',[$this,'customCertificateUrlFilter']);
		if(isset($_GET['generate_certificate'])) {
			$this->generateCertificate();
		}
	}

	public function init() {
		register_post_type(self::POST_TYPE, ['labels' => ['name' => 'Certificates', 'singular_name' => 'Certificate',], 'public' => false, 'has_archive' => false, 'show_ui' => true, 'show_in_menu' => true, 'show_in_admin_bar' => true,'menu_icon' => 'dashicons-id']);
		remove_post_type_support(self::POST_TYPE, 'editor');
		remove_post_type_support(self::POST_TYPE, 'thumbnail');
		remove_post_type_support(self::POST_TYPE, 'excerpt');
		remove_post_type_support(self::POST_TYPE, 'trackbacks');
		remove_post_type_support(self::POST_TYPE, 'custom-fields');
		remove_post_type_support(self::POST_TYPE, 'comments');
		remove_post_type_support(self::POST_TYPE, 'page-attributes');
		remove_post_type_support(self::POST_TYPE, 'post-formats');
	}

	public function addMetaBoxes() {
		if(get_post_type() !== self::POST_TYPE) {
			return;
		}
		$config = get_post_meta(get_post()->ID, 'certificate_config', true);
		add_meta_box('certificate_image', 'Image', function() use($config) {
			if(empty($config)) {
				$config = ['image_id' => null, 'filename' => '', 'color' => '#000', 'text_x' => 0, 'text_y' => 0, 'course_id' => null];
			}
			$image_id = $config['image_id'];
			if(intval($image_id) > 0) {
				$image = wp_get_attachment_image_url($image_id, 'full', false);
			}
			else {
				$image = 'https://via.placeholder.com/1024x781';
			}
			$color = $config['color'];
			$position_x = $config['text_x'];
			$position_y = $config['text_y'];
			?>
			Drag the name where you want it to appear below.
			<br />
			<br />
			<input type="button" class="button-primary" value="Select an Image" id="certificate-media-manager"/>
			<br />
			<input type="hidden" name="certificate_image_id" id="certificate-image-id"
				   value="<?php echo esc_attr($image_id); ?>"/>
			<input type="hidden" name="certificate_position_x" id="certificate-position-x"
				   value="<?php echo esc_attr($position_x); ?>"/>
			<input type="hidden" name="certificate_position_y" id="certificate-position-y"
				   value="<?php echo esc_attr($position_y); ?>"/>
			<br/>
			<label><input type="checkbox" id="certificate-toggle-lines"/> Show centering lines</label>
			<br/>
			<br/>
			<input type="text" class="regular-text certificate-text-color" name="certificate_color"
				   value="<?php echo esc_attr($color ?: '#0000') ?>" placeholder="Hex text color"/>
			<br/>
			<br/>
			<div id="certificate-container">
				<div id="certificate-centering-line" style="display: none" ;></div>
				<div id="certificate-text-placement" data-left="<?php echo esc_attr($position_x - 145) ?>"
					 data-top="<?php echo esc_attr($position_y) ?>"
					 style="
							 color:<?php echo esc_attr($color ?: '#0000') ?>;
							 left: <?php echo esc_attr($position_x - 145) ?>px;
							 top: <?php echo esc_attr($position_y) ?>px">
					Sample Name
				</div>
				<img id="certificate-preview-image" src="<?php echo $image; ?>"/>
			</div>
			<?php
		});
		add_meta_box('certificate_image_details', 'Certificate details', function() use($config) {
			$courses = $GLOBALS['wpdb']->get_results('SELECT course_id,course_title FROM ' . $GLOBALS['wpdb']->prefix . 'wpcw_courses');
			$filename = $config['filename'];
			$course_id = get_post_meta(get_post()->ID, 'certificate_course_id', true);
			?>
			<label><input type="text" class="regular-text" name="certificate_filename"
						  value="<?php echo esc_attr($filename) ?>" placeholder="Filename (e.g. TellSomeone_Certificate.jpg)"/>
				<br />
				Filename</label>
			<br/>
			<br/>
			<label><select name="certificate_course_id">
					<option></option>
					<?php foreach($courses as $course) { ?>
						<option value="<?php echo $course->course_id ?>" <?php echo selected($course_id,$course->course_id)?>><?php echo esc_html($course->course_title) ?></option>
					<?php } ?>
				</select>
				<br />
				Course</label>
			<?php
		});
	}

	public function saveMetaBoxes($post_id, $post) {
		if(get_post_type() !== self::POST_TYPE || !current_user_can('edit_post', $post_id) || empty($_POST['certificate_image_id']) || wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
			return;
		}
		$config = ['image_id' => $_POST['certificate_image_id'], 'filename' => $_POST['certificate_filename'], 'color' => $_POST['certificate_color'], 'text_x' => $_POST['certificate_position_x'], 'text_y' => $_POST['certificate_position_y']];
		update_post_meta($post_id, 'certificate_config', $config);
		update_post_meta($post_id, 'certificate_course_id', $_POST['certificate_course_id']);
	}

	public function enqueueAdminScripts() {
		if(get_post_type() !== self::POST_TYPE || !get_post()->ID) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style('wp-color-picker');
		wp_enqueue_script('wp-color-picker');
		wp_enqueue_script('certificate_script', plugins_url('/certificates.js', __FILE__), ['jquery','jquery-ui-core','jquery-ui-draggable','iris'], '0.2');
		wp_enqueue_style('certificate_font', 'https://fonts.googleapis.com/css?family=Montserrat:700');
		wp_enqueue_style('certificate_styles', plugins_url('/certificates.css', __FILE__));
	}

	public function previewImageAjax() {
		if(isset($_GET['id'])) {
			$image = wp_get_attachment_image_url(filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT), 'full', false);
			$data = array('image' => '<img id="certificate-preview-image" src="' . $image . '" />');
			wp_send_json_success($data);
		}
		else {
			wp_send_json_error();
		}
	}

	function customCertificateUrlFilter($link) {
		$parts = parse_url($link);
		parse_str($parts['query'], $query);
		$data = WPCW_certificate_getCertificateDetails_byAccessKey($query['certificate']);

		$current_user = get_userdata($data->cert_user_id);

		if ( !($current_user instanceof WP_User) ) {
			wp_redirect( home_url() );
		}

		$cert_url = site_url('/?generate_certificate&cert=download&course='.$data->cert_course_id.'&name=');
		$name = urlencode($current_user->user_firstname . " " . $current_user->user_lastname);
		return $cert_url . $name;
	}

	public function generateCertificate() {
		if(empty($_GET['name']) || empty($_GET['name'])) {
			return;
		}
		include __DIR__ . '/vendor/autoload.php';

		$name = $_GET['name'];
		$course = (isset($_GET['course']) && $_GET['course'] != '') ? $_GET['course'] : '1';
		$download = (isset($_GET['cert']) && $_GET['cert'] == 'download') ? true : false;
		$sha = sha1($name . $course);
		$filename = null;

		$posts = get_posts([
			'post_type' => 'harvest_certificates',
			'meta_query' => [
				[
					'key' => 'certificate_course_id',
					'value' => $course,
					'compare' => 'LIKE'
				]
			]
		]);
		if($posts) {
			$config = get_post_meta(current($posts)->ID,'certificate_config',true);
			$filename = $config['filename'];
			$image_path = get_attached_file($config['image_id']);
			$image = new \NMC\ImageWithText\Image($image_path);
			$name_text = strtoupper($name);
			$text = new \NMC\ImageWithText\Text($name_text, 1, strlen($name_text));

			$text->font = dirname(__FILE__) . '/Montserrat-Bold.ttf';
			$text->align = 'left';
			$text->color = substr($config['color'],1);
			$text->lineHeight = 28;
			$text->size = 28;
			$bounds = imagettfbbox($text->size, 0, $text->font, $name_text);
			$text_width = abs($bounds[2] - $bounds[0]);
			// 5 is a tweak for this font
			$text->startX = (int)$config['text_x'] + 5 - ($text_width / 2);
			$text->startY = (int)$config['text_y'];
			$image->addText($text);
		}

		$filePath = wp_get_upload_dir()['basedir'] . '/'. sha1($name . $course) .'.jpg';
		$image->render($filePath);

		$fileSize = filesize($filePath);
		header('Content-Length: '.$fileSize);
		header('Content-Type: image/jpg');
		if($download) {
			// Output headers.
			header("Cache-Control: private");
			header("Content-Type: application/stream");
			header("Content-Disposition: attachment; filename=".$filename);
		}
		// Output file.
		readfile ($filePath);
		unlink($filePath);
		exit();
	}
}

$plugin = new Harvest_CourseCertificates();
$plugin->bootstrap();