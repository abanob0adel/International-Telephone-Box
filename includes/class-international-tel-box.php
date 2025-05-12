<?php

/**
 * The main plugin class.
 *
 * @since      1.0.0
 * @package    International_Tel_Box
 */

class International_Tel_Box
{

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      International_Tel_Box_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies()
    {
        // Load required files here
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks()
    {
        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_scripts'));
        add_shortcode('international_tel_box', array($this, 'render_tel_box'));
    }

    /**
     * Add menu items to the admin area.
     *
     * @since    1.0.0
     */
    public function add_plugin_admin_menu()
    {
        add_options_page(
            'International Telephone Box Settings',
            'International Tel Box',
            'manage_options',
            'international-tel-box',
            array($this, 'display_plugin_admin_page')
        );
    }

    /**
     * Register plugin settings.
     *
     * @since    1.0.0
     */
    public function register_settings()
    {
        register_setting(
            'international_tel_box_settings',
            'itb_settings',
            array(
                'sanitize_callback' => array($this, 'sanitize_settings')
            )
        );
    }

    /**
     * Sanitize plugin settings.
     *
     * @since    1.0.0
     * @param    array    $input    The settings input.
     * @return   array    The sanitized settings.
     */
    public function sanitize_settings($input)
    {
        $sanitized = array();

        if (is_array($input)) {
            foreach ($input as $key => $value) {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }

    /**
     * Enqueue scripts and styles for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_public_scripts()
    {
        wp_enqueue_style('intl-tel-input', 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/css/intlTelInput.css');
        wp_enqueue_script('intl-tel-input', 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/intlTelInput.min.js', array('jquery'), null, true);
        wp_enqueue_script('intl-tel-input-utils', 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js', array('intl-tel-input'), null, true);
        wp_enqueue_style(
            'international-tel-box',
            ITB_PLUGIN_URL . 'public/css/international-tel-box.css',
            array(),
            ITB_VERSION
        );

        wp_enqueue_script(
            'international-tel-box',
            ITB_PLUGIN_URL . 'public/js/international-tel-box.js',
            array('jquery'),
            ITB_VERSION,
            true
        );
    }

    /**
     * Render the telephone input box.
     *
     * @since    1.0.0
     * @param    array    $atts    Shortcode attributes.
     * @return   string   The rendered HTML.
     */
    public function render_tel_box($atts)
    {
        $atts = shortcode_atts(array(
            'placeholder' => 'Enter phone number',
            'class' => '',
        ), $atts);

        ob_start();
?>
        <div class="itb-container <?php echo esc_attr($atts['class']); ?>">
            <input type="tel"
                class="itb-phone-input"
                placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
                data-validate="true">
        </div>
<?php
        return ob_get_clean();
    }

    /**
     * Run the plugin.
     *
     * @since    1.0.0
     */
    public function run()
    {
        // Plugin is running
    }
}
