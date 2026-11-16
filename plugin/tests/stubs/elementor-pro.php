<?php
/**
 * A fake Elementor Pro Theme Builder for the stub harness: exactly what NH_Site::chrome_documents() calls —
 * `\ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager()
 * ->get_documents_for_location( $location )` (PRO Elements 4.4.0: Theme_Document objects keyed by
 * template id, display conditions applied) and `$document->get_main_id()` (Elementor core Document).
 *
 * Not loaded by bootstrap.php: core/scope.test.php loads it after testing the site without Pro, because a
 * class cannot be undefined. The templates shown on a request: $GLOBALS['nh_test']['locations'][ $location ]
 * = [ post ids ]; every call is counted in $GLOBALS['nh_test']['pro_calls'].
 *
 * @package nevehairus-site
 */

namespace ElementorPro\Modules\ThemeBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * A Theme Builder template (only the method the plugin reads).
 */
class NH_Test_Theme_Document {
	/** @var int */
	private $id;

	public function __construct( int $id ) {
		$this->id = $id;
	}

	public function get_main_id() {
		return $this->id;
	}
}

/**
 * The conditions manager.
 */
class NH_Test_Conditions_Manager {
	public function get_documents_for_location( $location ) {
		$GLOBALS['nh_test']['pro_calls'] = ( $GLOBALS['nh_test']['pro_calls'] ?? 0 ) + 1;
		if ( ! empty( $GLOBALS['nh_test']['pro_throws'] ) ) {
			throw new \RuntimeException( 'Pro failed' );
		}
		$out = [];
		foreach ( (array) ( $GLOBALS['nh_test']['locations'][ (string) $location ] ?? [] ) as $id ) {
			$out[ (int) $id ] = new NH_Test_Theme_Document( (int) $id );
		}
		return $out;
	}
}

/**
 * The Theme Builder module (Elementor's Module::instance() singleton).
 */
class Module {
	/** @var Module|null */
	private static $instance = null;

	/** @var NH_Test_Conditions_Manager */
	private $conditions;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance             = new self();
			self::$instance->conditions = new NH_Test_Conditions_Manager();
		}
		return self::$instance;
	}

	public function get_conditions_manager() {
		return $this->conditions;
	}
}
