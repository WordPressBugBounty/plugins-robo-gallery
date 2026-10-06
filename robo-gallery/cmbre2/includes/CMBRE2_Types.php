<?php
/**
 * CMB field type objects
 *
 * @since  1.0.0
 *
 * @category  WordPress_Plugin
 * @package   CMBRE2
 * @author    WebDevStudios
 * @license   GPL-2.0+
 * @link      http://webdevstudios.com
 */
if ( ! defined( 'WPINC' ) ) exit;
class CMBRE2_Types {

	/**
	 * An iterator value for repeatable fields
	 * @var   integer
	 * @since 1.0.0
	 */
	public $iterator = 0;

	/**
	 * Current CMBRE2_Field field object
	 * @var   CMBRE2_Field object
	 * @since 1.0.0
	 */
	public $field;

	public function __construct( CMBRE2_Field $field ) {
		$this->field = $field;
	}

	/**
	 * Default fallback. Allows rendering fields via "cmbre2_render_$name" hook
	 * @since  1.0.0
	 * @param  string $name      Non-existent method name
	 * @param  array  $arguments All arguments passed to the method
	 */
	public function __call( $name, $arguments ) {
		/**
		 * Pass non-existent field types through an action
		 *
		 * The dynamic portion of the hook name, $name, refers to the field type.
		 *
		 * @param array  $field              The passed in `CMBRE2_Field` object
		 * @param mixed  $escaped_value      The value of this field escaped.
		 *                                   It defaults to `sanitize_text_field`.
		 *                                   If you need the unescaped value, you can access it
		 *                                   via `$field->value()`
		 * @param int    $object_id          The ID of the current object
		 * @param string $object_type        The type of object you are working with.
		 *                                   Most commonly, `post` (this applies to all post-types),
		 *                                   but could also be `comment`, `user` or `options-page`.
		 * @param object $field_type_object  This `CMBRE2_Types` object
		 */
		do_action( "cmbre2_render_$name", $this->field, $this->field->escaped_value(), $this->field->object_id, $this->field->object_type, $this );

		// the render callbacks print their own markup; _render() passes this result to wp_kses()
		return '';
	}

	/**
	 * Render a field (repeatable fields are not supported any more)
	 * @since  1.1.0
	 */
	public function render() {
		$this->_render();
	}

	/**
	 * Render a field type
	 * @since  1.1.0
	 */
	protected function _render() {
		$this->field->peform_param_callback( 'before_field' );
		echo wp_kses( $this->{$this->field->type()}(), cmbre2_form_allowed_html() );
		$this->field->peform_param_callback( 'after_field' );
	}

	/**
	 * Retrieve text parameter from field's options array (if it has one), or use fallback text
	 * @since  2.0.0
	 * @param  string  $option_key Key in field's options array
	 * @param  string  $fallback   Fallback text
	 * @return string              Text
	 */
	public function _text( $option_key, $fallback ) {
		$has_string_param = $this->field->options( $option_key );
		return $has_string_param ? $has_string_param : $fallback;
	}

	/**
	 * Handles parsing and filtering attributes while preserving any passed in via field config.
	 * @since  1.1.0
	 * @param  array  $args     Override arguments
	 * @param  string $element  Element for filter
	 * @param  array  $defaults Default arguments
	 * @return array            Parsed and filtered arguments
	 */
	public function parse_args( $args, $element, $defaults ) {
		return wp_parse_args( apply_filters( "cmbre2_{$element}_attributes", $this->field->maybe_set_attributes( $args ), $defaults, $this->field, $this ), $defaults );
	}

	/**
	 * Combines attributes into a string for a form element
	 * @since  1.1.0
	 * @param  array  $attrs        Attributes to concatenate
	 * @param  array  $attr_exclude Attributes that should NOT be concatenated
	 * @return string               String of attributes for form element
	 */
	public function concat_attrs( $attrs, $attr_exclude = array() ) {
		$attributes = '';
		foreach ( $attrs as $attr => $val ) {
			$excluded = in_array( $attr, (array) $attr_exclude, true );
			$empty    = false === $val && 'value' !== $attr;
			if ( ! $excluded && ! $empty ) {
				// if data attribute, use single quote wraps, else double
				$quotes = false !== stripos( $attr, 'data-' ) ? "'" : '"';
				// the plugin's field types pass stored values (colors, sizes) here
				// straight from the meta; esc_attr() leaves already-escaped ones as they are
				$attributes .= sprintf( ' %1$s=%3$s%2$s%3$s', esc_attr( $attr ), esc_attr( $val ), $quotes );
			}
		}
		return $attributes;
	}

	/**
	 * Generates html for concatenated items
	 * @since  1.1.0
	 * @param  array   $args Optional arguments
	 * @return string        Concatenated html items
	 */
	public function concat_items( $args = array() ) {

		$method = isset( $args['method'] ) ? $args['method'] : 'select_option';
		unset( $args['method'] );

		$value = $this->field->escaped_value()
			? $this->field->escaped_value()
			: $this->field->args( 'default' );

		$concatenated_items = ''; $i = 1;

		$options = array();
		if ( $option_none = $this->field->args( 'show_option_none' ) ) {
			$options[ '' ] = $option_none;
		}
		$options = $options + (array) $this->field->options();
		foreach ( $options as $opt_value => $opt_label ) {

			// Clone args & modify for just this item
			$a = $args;

			$a['value'] = $opt_value;
			$a['label'] = $opt_label;

			// Check if this option is the value of the input
			if ( $value == $opt_value ) {
				$a['checked'] = 'checked';
			}

			$concatenated_items .= $this->$method( $a, $i++ );
		}

		return $concatenated_items;
	}

	/**
	 * Generates html for an option element
	 * @since  1.1.0
	 * @param  array  $args Arguments array containing value, label, and checked boolean
	 * @return string       Generated option element html
	 */
	public function select_option( $args = array() ) {
		return sprintf( "\t" . '<option value="%s" %s>%s</option>', $args['value'], selected( isset( $args['checked'] ) && $args['checked'], true, false ), $args['label'] ) . "\n";
	}

	/**
	 * Generates description markup
	 * @since  1.0.0
	 * @param  boolean $paragraph Paragraph tag or span
	 * @param  boolean $echo      Whether to echo description or only return it
	 * @return string             Field's description markup
	 */
	public function _desc( $paragraph = false, $echo = false, $repeat_group = false ) {
		// Prevent description from printing multiple times for repeatable fields
		if ( ! $repeat_group && ( $this->field->args( 'repeatable' ) || $this->iterator > 0 ) ) {
			return '';
		}

		$desc = $this->field->args( 'description' );

		if ( ! $desc ) {
			return '';
		}

		$tag = $paragraph ? 'p' : 'span';
		$desc = sprintf( "\n" . '<%1$s class="cmbre2-metabox-description">%2$s</%1$s>' . "\n", $tag, $desc );

		if ( $echo ) {
			echo wp_kses_post( $desc );
		}
		return $desc;
	}

	/**
	 * Generate field name attribute
	 * @since  1.1.0
	 * @param  string  $suffix For multi-part fields
	 * @return string          Name attribute
	 */
	public function _name( $suffix = '' ) {
		return $this->field->args( '_name' ) . ( $this->field->args( 'repeatable' ) ? '[' . $this->iterator . ']' : '' ) . $suffix;
	}

	/**
	 * Generate field id attribute
	 * @since  1.1.0
	 * @param  string  $suffix For multi-part fields
	 * @return string          Id attribute
	 */
	public function _id( $suffix = '' ) {
		return $this->field->id() . $suffix . ( $this->field->args( 'repeatable' ) ? '_' . $this->iterator . '" data-iterator="' . $this->iterator : '' );
	}

	/**
	 * Handles outputting an 'input' element
	 * @since  1.1.0
	 * @param  array  $args Override arguments
	 * @return string       Form input element
	 */
	public function input( $args = array() ) {
		$a = $this->parse_args( $args, 'input', array(
			'type'  => 'text',
			'class' => 'regular-text',
			'name'  => $this->_name(),
			'id'    => $this->_id(),
			'value' => $this->field->escaped_value(),
			'desc'  => $this->_desc( true ),
		) );

		return sprintf( '<input%s/>%s', $this->concat_attrs( $a, array( 'desc' ) ), $a['desc'] );
	}

	/**
	 * Field types. Only title and hidden are used as CMB2 types; input() and
	 * select() are also called by the plugin's own types (cmbre2/fields). Other
	 * types fall through __call() to the "cmbre2_render_$name" action.
	 */

	public function hidden() {
		return $this->input( array( 'type' => 'hidden', 'desc' => '', 'class' => false ) );
	}

	public function title( $args = array() ) {
		$a = $this->parse_args( $args, 'title', array(
			'tag'   => $this->field->object_type == 'post' ? 'h5' : 'h3',
			'class' => 'cmbre2-metabox-title',
			'name'  => $this->field->args( 'name' ),
			'desc'  => $this->_desc( true ),
		) );

		return sprintf( '<%1$s class="%2$s">%3$s</%1$s>%4$s', $a['tag'], $a['class'], $a['name'], $a['desc'] );
	}

	public function select( $args = array() ) {
		$a = $this->parse_args( $args, 'select', array(
			'class'   => 'cmbre2_select',
			'name'    => $this->_name(),
			'id'      => $this->_id(),
			'desc'    => $this->_desc( true ),
			'options' => $this->concat_items(),
		) );

		$attrs = $this->concat_attrs( $a, array( 'desc', 'options' ) );
		return sprintf( '<select%s>%s</select>%s', $attrs, $a['options'], $a['desc'] );
	}

}
