<?php
/**
 * CMBRE2 field sanitization
 *
 * @since  0.0.4
 *
 * @category  WordPress_Plugin
 * @package   CMBRE2
 * @author    WebDevStudios
 * @license   GPL-2.0+
 * @link      http://webdevstudios.com
 *
 * @method string _id()
 */
if ( ! defined( 'WPINC' ) ) exit;
class CMBRE2_Sanitize {

	/**
	 * A CMB field object
	 * @var CMBRE2_Field object
	 */
	public $field;

	/**
	 * Field's value
	 * @var mixed
	 */
	public $value;

	/**
	 * Setup our class vars
	 * @since 1.1.0
	 * @param CMBRE2_Field $field A CMBRE2 field object
	 * @param mixed      $value Field value
	 */
	public function __construct( CMBRE2_Field $field, $value ) {
		$this->field = $field;
		$this->value = stripslashes_deep( $value ); // get rid of those evil magic quotes
	}

	/**
	 * Catchall method if field's 'sanitization_cb' is NOT defined, or field type does not have a corresponding validation method
	 * @since  1.0.0
	 * @param  string $name      Non-existent method name
	 * @param  array  $arguments All arguments passed to the method
	 */
	public function __call( $name, $arguments ) {
		return $this->default_sanitization( $this->value );
	}

	/**
	 * Default fallback sanitization method. Applies filters.
	 * @since  1.0.2
	 */
	public function default_sanitization() {

		/**
		 * Filter the value before it is saved.
		 *
		 * The dynamic portion of the hook name, $this->field->type(), refers to the field type.
		 *
		 * Passing a non-null value to the filter will short-circuit saving
		 * the field value, saving the passed value instead.
		 *
		 * @param bool|mixed $override_value Sanitization/Validation override value to return.
		 *                                   Default false to skip it.
		 * @param mixed      $value      The value to be saved to this field.
		 * @param int        $object_id  The ID of the object where the value will be saved
		 * @param array      $field_args The current field's arguments
		 * @param object     $sanitizer  This `CMBRE2_Sanitize` object
		 */
		$override_value = apply_filters( "cmbre2_sanitize_{$this->field->type()}", null, $this->value, $this->field->object_id, $this->field->args(), $this );
		/**
		 * This exists for back-compatibility, but validation
		 * is not what happens here.
		 * @deprecated See documentation above.
		 */
		$override_value = apply_filters( "cmbre2_validate_{$this->field->type()}", $override_value, $this->value, $this->field->object_id, $this->field->args(), $this );

		if ( null !== $override_value ) {
			return $override_value;
		}

		$sanitized_value = '';

		// Only the types the plugin's boxes use are left (title/hidden + the
		// cmbre2/fields types); the rest of CMB2's types were removed.
		switch ( $this->field->type() ) {
			case 'rbstextarea':
				$sanitized_value = $this->textarea( $this->value );
				break;
			default:
				// Handle repeatable fields array
				// We'll fallback to 'sanitize_text_field'
				$sanitized_value = is_array( $this->value ) ? array_map( 'sanitize_text_field', $this->value ) : call_user_func( 'sanitize_text_field', $this->value );
				break;
		}

		return $this->_is_empty_array( $sanitized_value ) ? '' : $sanitized_value;
	}

	/**
	 * Sanitize textareas and wysiwyg fields
	 * @since  1.0.1
	 * @return string       Sanitized data
	 */
	public function textarea() {
		return is_array( $this->value ) ? array_map( 'wp_kses_post', $this->value ) : wp_kses_post( $this->value );
	}

	/**
	 * Determine if passed value is an empty array
	 * @since  2.0.6
	 * @param  mixed  $to_check Value to check
	 * @return boolean          Whether value is an array that's empty
	 */
	public function _is_empty_array( $to_check ) {
		if ( is_array( $to_check ) ) {
			$cleaned_up = array_filter( $to_check );
			return empty( $cleaned_up );
		}
		return false;
	}

}
