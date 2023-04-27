<?php
namespace WprAddonsPro\Modules\ThemeBuilder\ArchiveTitlePro\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Core\Responsive\Responsive;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Text_Stroke;
use Elementor\Group_Control_Typography;
use Elementor\Core\Schemes\Typography;
use Elementor\Core\Schemes\Color;
use WprAddons\Classes\Utilities;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Wpr_Archive_Title_Pro extends \WprAddons\Modules\ThemeBuilder\ArchiveTitle\Widgets\Wpr_Archive_Title {
	public function add_control_archive_description() {
		$this->add_control(
			'archive_description',
			[
				'label' => esc_html__( 'Show Description', 'wpr-addons' ),
				'type' => Controls_Manager::SWITCHER,
				'default' => 'yes',
				'return_value' => 'yes',
			]
		);
	}
}