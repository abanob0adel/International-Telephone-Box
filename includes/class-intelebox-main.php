<?php

namespace International_Telephone_Box;

if (! defined('ABSPATH')) {
    exit;
}

class Intelebox_Main
{

    /**
     * Initialize the class and set its properties.
     *
     * @since 1.1.1
     */
    public function __construct()
    {
        $this->define_public_hooks();
    }

    /**
     * Register all of the hooks related to the public-facing functionality.
     *
     * @since 1.1.1
     */
    private function define_public_hooks()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_styles'));
        add_shortcode('intelebox_input', array($this, 'render_tel_box'));
    }

    /**
     * Enqueue public styles.
     *
     * @since 1.1.1
     */
    public function enqueue_public_styles()
    {
        wp_enqueue_style(
            'intl-tel-input',
            INTELEBOX_PLUGIN_URL . 'public/css/intlTelInput.css',
            array(),
            INTELEBOX_INTL_TEL_INPUT_VERSION
        );
        wp_enqueue_style(
            'international-telephone-box',
            INTELEBOX_PLUGIN_URL . 'public/css/international-tel-box.css',
            array('intl-tel-input'),
            INTELEBOX_VERSION
        );
    }

    /**
     * Enqueue public scripts.
     *
     * @since 1.1.1
     */
    public function enqueue_public_scripts()
    {
        // intl-tel-input v29 has no jQuery build and loads its utils script
        // with a dynamic import(), so use the bundle that has utils built in.
        wp_enqueue_script(
            'intl-tel-input',
            INTELEBOX_PLUGIN_URL . 'public/js/intlTelInputWithUtils.min.js',
            array(),
            INTELEBOX_INTL_TEL_INPUT_VERSION,
            true
        );

        wp_enqueue_script(
            'international-telephone-box',
            INTELEBOX_PLUGIN_URL . 'public/js/international-tel-box.js',
            array('intl-tel-input'),
            INTELEBOX_VERSION,
            true
        );
    }

    /**
     * Render the telephone input box (shortcode).
     *
     * @since 1.1.1
     * @param array $atts
     * @return string
     */
    public function render_tel_box($atts)
    {
        $atts = shortcode_atts(array(
            'name'        => 'phone',
            'id'          => '',
            'required'    => '',
            'placeholder' => __( 'Enter phone number', 'international-telephone-box' ),
            'class'       => '',
        ), $atts);

        $required = in_array(
            strtolower( trim( (string) $atts['required'] ) ),
            array( 'true', '1', 'yes' ),
            true
        );

        ob_start();
?>
        <div class="intelebox-container <?php echo esc_attr($atts['class']); ?>">
            <input type="tel"
                name="<?php echo esc_attr( $atts['name'] ); ?>"
                <?php if ( ! empty( $atts['id'] ) ) { ?>id="<?php echo esc_attr( $atts['id'] ); ?>"<?php } ?>
                class="intelebox-phone-input"
                placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
                <?php if ( $required ) { ?>required<?php } ?>
                data-validate="true">
        </div>
<?php
        return ob_get_clean();
    }

    /**
     * Run the plugin.
     *
     * @since 1.1.1
     */
    public function run() {}
}
