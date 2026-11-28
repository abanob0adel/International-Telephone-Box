<?php
/**
 * Settings page for International Telephone Box.
 *
 * @package International_Telephone_Box
 */

namespace International_Telephone_Box;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Intelebox_Admin
 *
 * Settings → International Telephone Box.
 */
class Intelebox_Admin
{

    /**
     * Menu slug.
     */
    const MENU_SLUG = 'intelebox-settings';

    /**
     * Hook suffix returned by `add_options_page`.
     *
     * @var string
     */
    private $hook_suffix = '';

    /**
     * Register hooks. Only ever instantiated when `is_admin()`.
     */
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_filter(
            'plugin_action_links_' . plugin_basename( INTELEBOX_PLUGIN_FILE ),
            array( $this, 'add_action_link' )
        );
    }

    /**
     * Add the options page under Settings.
     */
    public function add_settings_page()
    {
        $this->hook_suffix = add_options_page(
            __( 'International Telephone Box', 'international-telephone-box' ),
            __( 'International Tel Box', 'international-telephone-box' ),
            'manage_options',
            self::MENU_SLUG,
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register the single option.
     */
    public function register_settings()
    {
        register_setting(
            Intelebox_Settings::OPTION_GROUP,
            Intelebox_Settings::OPTION_NAME,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( Intelebox_Settings::class, 'sanitize' ),
                'default'           => Intelebox_Settings::defaults(),
                'show_in_rest'      => false,
            )
        );
    }

    /**
     * Add a "Settings" link on the Plugins screen.
     *
     * @param array $links Existing action links.
     * @return array
     */
    public function add_action_link( $links ): array
    {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'options-general.php?page=' . self::MENU_SLUG ) ),
            esc_html__( 'Settings', 'international-telephone-box' )
        );

        array_unshift( $links, $settings_link );

        return $links;
    }

    /**
     * Enqueue admin CSS/JS only on our screen.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     */
    public function enqueue_assets( $hook_suffix )
    {
        if ( $this->hook_suffix === '' || $hook_suffix !== $this->hook_suffix ) {
            return;
        }

        wp_enqueue_style(
            'intl-tel-input',
            INTELEBOX_PLUGIN_URL . 'public/css/intlTelInput.css',
            array(),
            INTELEBOX_INTL_TEL_INPUT_VERSION
        );

        wp_enqueue_style(
            'intelebox-admin',
            INTELEBOX_PLUGIN_URL . 'public/admin/css/admin.css',
            array( 'intl-tel-input' ),
            INTELEBOX_VERSION
        );

        wp_enqueue_script(
            'intl-tel-input',
            INTELEBOX_PLUGIN_URL . 'public/js/intlTelInputWithUtils.min.js',
            array(),
            INTELEBOX_INTL_TEL_INPUT_VERSION,
            true
        );

        wp_enqueue_script(
            'intelebox-admin',
            INTELEBOX_PLUGIN_URL . 'public/admin/js/admin.js',
            array( 'intl-tel-input' ),
            INTELEBOX_VERSION,
            true
        );

        // Keep JSON types (bool, numbers); wp_localize_script would stringify everything.
        wp_add_inline_script(
            'intelebox-admin',
            'window.inteleboxAdmin = ' . wp_json_encode( $this->localized_data() ) . ';',
            'before'
        );
    }

    /**
     * Data passed to admin.js.
     *
     * @return array
     */
    private function localized_data(): array
    {
        $countries_data = array();
        foreach ( Intelebox_Settings::countries() as $code => $data ) {
            $countries_data[ $code ] = array(
                'name' => isset( $data['name'] ) ? (string) $data['name'] : $code,
                'dial' => isset( $data['dial'] ) ? (string) $data['dial'] : '',
            );
        }

        return array(
            'countries'   => $countries_data,
            'defaults'    => Intelebox_Settings::defaults(),
            'maxPreferred' => Intelebox_Settings::MAX_PREFERRED,
            'strings'     => array(
                /* translators: %s: country name. */
                'removeCountry'  => __( 'Remove %s', 'international-telephone-box' ),
                'noResults'      => __( 'No countries found', 'international-telephone-box' ),
                /* translators: %d: number of selected countries. */
                'selectedCount'  => _n( '%d selected', '%d selected', 1, 'international-telephone-box' ),
                /* translators: 1: current number of preferred countries, 2: maximum number. */
                'preferredCount' => _n( '%1$d of %2$d selected', '%1$d of %2$d selected', 1, 'international-telephone-box' ),
                'selectAll'      => __( 'Select all visible', 'international-telephone-box' ),
                'clear'          => __( 'Clear', 'international-telephone-box' ),
                'previewLabel'   => __( 'Preview', 'international-telephone-box' ),
                'modeHelpAll'     => __( 'Visitors can pick any country.', 'international-telephone-box' ),
                'modeHelpInclude' => __( 'Visitors can only pick these countries.', 'international-telephone-box' ),
                'modeHelpExclude' => __( 'These countries are removed from the list.', 'international-telephone-box' ),
            ),
        );
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page()
    {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings  = Intelebox_Settings::get();
        $countries = Intelebox_Settings::countries();
        $mode      = $settings['country_mode'];
        $selected  = $settings['countries'];
        $preferred = $settings['preferred_countries'];
        $initial   = $settings['initial_country'];
        $fallback  = $settings['fallback_country'];
        $geo       = $settings['geo_lookup'];

        $mode_help = array(
            'all'     => __( 'Visitors can pick any country.', 'international-telephone-box' ),
            'include' => __( 'Visitors can only pick these countries.', 'international-telephone-box' ),
            'exclude' => __( 'These countries are removed from the list.', 'international-telephone-box' ),
        );
        ?>
<div class="wrap intelebox-admin">
    <header class="intelebox-header">
        <p class="intelebox-eyebrow"><?php esc_html_e( 'International Telephone Box · Settings', 'international-telephone-box' ); ?></p>
        <h1 class="intelebox-title"><?php esc_html_e( 'Phone field', 'international-telephone-box' ); ?> <em><?php esc_html_e( 'countries', 'international-telephone-box' ); ?></em></h1>
        <p class="intelebox-intro"><?php esc_html_e( 'Choose which countries the phone field offers, which country it starts on, and how the visitor\'s country is detected.', 'international-telephone-box' ); ?></p>
    </header>

    <form class="intelebox-form" method="post" action="options.php" novalidate>
        <?php settings_fields( Intelebox_Settings::OPTION_GROUP ); ?>

        <!-- ============================ Countries ============================ -->
        <section class="intelebox-section" aria-labelledby="intelebox-heading-countries">
            <div class="intelebox-section__aside">
                <h2 id="intelebox-heading-countries" class="intelebox-heading"><?php esc_html_e( 'Countries', 'international-telephone-box' ); ?></h2>
                <p class="intelebox-help"><?php esc_html_e( 'Pick which countries the phone field offers.', 'international-telephone-box' ); ?></p>
            </div>
            <div class="intelebox-section__main">
                <fieldset class="intelebox-fieldset">
                    <legend class="intelebox-label"><?php esc_html_e( 'Countries to offer', 'international-telephone-box' ); ?></legend>

                    <div class="intelebox-segmented" role="radiogroup" aria-label="<?php esc_attr_e( 'Countries to offer', 'international-telephone-box' ); ?>">
                        <input type="radio" class="intelebox-segmented__input" name="intelebox_settings[country_mode]" id="intelebox-mode-all" value="all" <?php checked( $mode, 'all' ); ?> data-mode>
                        <label class="intelebox-segmented__label" for="intelebox-mode-all"><?php esc_html_e( 'All countries', 'international-telephone-box' ); ?></label>

                        <input type="radio" class="intelebox-segmented__input" name="intelebox_settings[country_mode]" id="intelebox-mode-include" value="include" <?php checked( $mode, 'include' ); ?> data-mode>
                        <label class="intelebox-segmented__label" for="intelebox-mode-include"><?php esc_html_e( 'Only selected', 'international-telephone-box' ); ?></label>

                        <input type="radio" class="intelebox-segmented__input" name="intelebox_settings[country_mode]" id="intelebox-mode-exclude" value="exclude" <?php checked( $mode, 'exclude' ); ?> data-mode>
                        <label class="intelebox-segmented__label" for="intelebox-mode-exclude"><?php esc_html_e( 'All except selected', 'international-telephone-box' ); ?></label>
                    </div>

                    <p class="intelebox-help" data-mode-help><?php echo esc_html( $mode_help[ $mode ] ); ?></p>

                    <div class="intelebox-picker" data-picker="countries">
                        <label class="intelebox-label" for="intelebox-country-search"><?php esc_html_e( 'Search countries', 'international-telephone-box' ); ?></label>
                        <input type="search" class="intelebox-input intelebox-search" id="intelebox-country-search" data-search placeholder="<?php esc_attr_e( 'Search by name or dial code', 'international-telephone-box' ); ?>" autocomplete="off">

                        <div class="intelebox-chips" data-chips aria-label="<?php esc_attr_e( 'Selected countries', 'international-telephone-box' ); ?>"></div>

                        <div class="intelebox-picker__toolbar">
                            <p class="intelebox-counter" data-counter aria-live="polite"><?php
                            $n = count( $selected );
                            /* translators: %d: number of selected countries. */
                            echo esc_html( sprintf( _n( '%d selected', '%d selected', $n, 'international-telephone-box' ), $n ) );
                            ?></p>
                            <button type="button" class="intelebox-text-button" data-select-all><?php esc_html_e( 'Select all visible', 'international-telephone-box' ); ?></button>
                            <button type="button" class="intelebox-text-button" data-clear><?php esc_html_e( 'Clear', 'international-telephone-box' ); ?></button>
                        </div>

                        <div class="intelebox-country-list" data-list role="group" aria-label="<?php esc_attr_e( 'Countries', 'international-telephone-box' ); ?>">
                            <?php
                            foreach ( $countries as $code => $data ) {
                                $name  = isset( $data['name'] ) ? (string) $data['name'] : $code;
                                $dial  = isset( $data['dial'] ) ? (string) $data['dial'] : '';
                                $check = in_array( $code, $selected, true );
                                ?>
                                <label class="intelebox-country-row" data-iso="<?php echo esc_attr( $code ); ?>" data-name="<?php echo esc_attr( $name ); ?>" data-dial="<?php echo esc_attr( $dial ); ?>">
                                    <input type="checkbox" name="intelebox_settings[countries][]" value="<?php echo esc_attr( $code ); ?>" <?php checked( $check ); ?>>
                                    <span class="iti__flag iti__<?php echo esc_attr( $code ); ?>" aria-hidden="true"></span>
                                    <span class="intelebox-country-name"><?php echo esc_html( $name ); ?></span>
                                    <span class="intelebox-country-dial">+<?php echo esc_html( $dial ); ?></span>
                                </label>
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                </fieldset>
            </div>
        </section>

        <!-- ========================= Default country ========================= -->
        <section class="intelebox-section" aria-labelledby="intelebox-heading-default">
            <div class="intelebox-section__aside">
                <h2 id="intelebox-heading-default" class="intelebox-heading"><?php esc_html_e( 'Default country', 'international-telephone-box' ); ?></h2>
                <p class="intelebox-help"><?php esc_html_e( 'Which country the field starts on, and which is used when detection fails.', 'international-telephone-box' ); ?></p>
            </div>
            <div class="intelebox-section__main">
                <div class="intelebox-field">
                    <label class="intelebox-label" for="intelebox-initial-country"><?php esc_html_e( 'Initial country', 'international-telephone-box' ); ?></label>
                    <select class="intelebox-select" id="intelebox-initial-country" name="intelebox_settings[initial_country]" data-initial-country>
                        <option value="auto" <?php selected( $initial, 'auto' ); ?>><?php esc_html_e( "Detect from visitor's location", 'international-telephone-box' ); ?></option>
                        <?php foreach ( $countries as $code => $data ) : ?>
                            <option value="<?php echo esc_attr( $code ); ?>" data-name="<?php echo esc_attr( isset( $data['name'] ) ? $data['name'] : $code ); ?>" <?php selected( $initial, $code ); ?>><?php echo esc_html( isset( $data['name'] ) ? $data['name'] : $code ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="intelebox-field">
                    <label class="intelebox-label" for="intelebox-fallback-country"><?php esc_html_e( 'Fallback country', 'international-telephone-box' ); ?></label>
                    <select class="intelebox-select" id="intelebox-fallback-country" name="intelebox_settings[fallback_country]" data-fallback-country>
                        <?php foreach ( $countries as $code => $data ) : ?>
                            <option value="<?php echo esc_attr( $code ); ?>" data-name="<?php echo esc_attr( isset( $data['name'] ) ? $data['name'] : $code ); ?>" <?php selected( $fallback, $code ); ?>><?php echo esc_html( isset( $data['name'] ) ? $data['name'] : $code ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="intelebox-help"><?php esc_html_e( "Used when detection is off or doesn't find an allowed country.", 'international-telephone-box' ); ?></p>
                </div>

                <fieldset class="intelebox-fieldset intelebox-picker" data-picker="preferred" data-preferred="<?php echo esc_attr( wp_json_encode( $preferred ) ); ?>">
                    <legend class="intelebox-label"><?php esc_html_e( 'Preferred countries', 'international-telephone-box' ); ?></legend>
                    <p class="intelebox-help"><?php
                        /* translators: %d: maximum number of preferred countries. */
                        printf( esc_html__( 'Shown at the top of the dropdown, in this order. At most %d.', 'international-telephone-box' ), (int) Intelebox_Settings::MAX_PREFERRED );
                    ?></p>

                    <label class="intelebox-label" for="intelebox-preferred-search"><?php esc_html_e( 'Search countries', 'international-telephone-box' ); ?></label>
                    <input type="search" class="intelebox-input intelebox-search" id="intelebox-preferred-search" data-search placeholder="<?php esc_attr_e( 'Search by name or dial code', 'international-telephone-box' ); ?>" autocomplete="off">

                    <div class="intelebox-chips" data-chips aria-label="<?php esc_attr_e( 'Preferred countries', 'international-telephone-box' ); ?>"></div>

                    <div class="intelebox-picker__toolbar">
                        <p class="intelebox-counter" data-counter aria-live="polite"></p>
                    </div>

                    <div class="intelebox-country-list intelebox-country-list--preferred" data-list role="group" aria-label="<?php esc_attr_e( 'Preferred countries', 'international-telephone-box' ); ?>">
                        <?php
                        foreach ( $countries as $code => $data ) {
                            $name  = isset( $data['name'] ) ? (string) $data['name'] : $code;
                            $dial  = isset( $data['dial'] ) ? (string) $data['dial'] : '';
                            $check = in_array( $code, $preferred, true );
                            ?>
                            <label class="intelebox-country-row" data-iso="<?php echo esc_attr( $code ); ?>" data-name="<?php echo esc_attr( $name ); ?>" data-dial="<?php echo esc_attr( $dial ); ?>">
                                <input type="checkbox" name="intelebox_settings[preferred_countries][]" value="<?php echo esc_attr( $code ); ?>" <?php checked( $check ); ?>>
                                <span class="iti__flag iti__<?php echo esc_attr( $code ); ?>" aria-hidden="true"></span>
                                <span class="intelebox-country-name"><?php echo esc_html( $name ); ?></span>
                                <span class="intelebox-country-dial">+<?php echo esc_html( $dial ); ?></span>
                            </label>
                            <?php
                        }
                        ?>
                    </div>
                </fieldset>
            </div>
        </section>

        <!-- ============================= Privacy ============================= -->
        <section class="intelebox-section" aria-labelledby="intelebox-heading-privacy">
            <div class="intelebox-section__aside">
                <h2 id="intelebox-heading-privacy" class="intelebox-heading"><?php esc_html_e( 'Privacy', 'international-telephone-box' ); ?></h2>
                <p class="intelebox-help"><?php esc_html_e( 'How the visitor\'s country is detected.', 'international-telephone-box' ); ?></p>
            </div>
            <div class="intelebox-section__main">
                <label class="intelebox-toggle" for="intelebox-geo-lookup">
                    <input type="checkbox" role="switch" class="intelebox-toggle__input" id="intelebox-geo-lookup" name="intelebox_settings[geo_lookup]" value="1" <?php checked( $geo, true ); ?> data-geo-lookup>
                    <span class="intelebox-toggle__track" aria-hidden="true"></span>
                    <span class="intelebox-toggle__label"><?php esc_html_e( "Detect the visitor's country with ipapi.co", 'international-telephone-box' ); ?></span>
                </label>
                <p class="intelebox-help">
                    <?php esc_html_e( "When enabled, this sends the visitor's IP address to ipapi.co to guess their country.", 'international-telephone-box' ); ?>
                    <a href="<?php echo esc_url( 'https://ipapi.co/privacy/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'ipapi.co privacy policy', 'international-telephone-box' ); ?></a>.
                    <?php esc_html_e( 'When it is off, the fallback country is used.', 'international-telephone-box' ); ?>
                </p>
            </div>
        </section>

        <!-- ============================= Preview ============================= -->
        <section class="intelebox-section" aria-labelledby="intelebox-heading-preview">
            <div class="intelebox-section__aside">
                <h2 id="intelebox-heading-preview" class="intelebox-heading"><?php esc_html_e( 'Preview', 'international-telephone-box' ); ?></h2>
                <p class="intelebox-help"><?php esc_html_e( 'Live preview of the current, unsaved settings. Never contacts ipapi.co.', 'international-telephone-box' ); ?></p>
            </div>
            <div class="intelebox-section__main">
                <label class="intelebox-label" for="intelebox-preview-input"><?php esc_html_e( 'Phone number', 'international-telephone-box' ); ?></label>
                <div class="intelebox-preview">
                    <input type="tel" class="intelebox-input" id="intelebox-preview-input" data-preview placeholder="<?php esc_attr_e( 'Enter phone number', 'international-telephone-box' ); ?>" autocomplete="off">
                </div>
            </div>
        </section>

        <footer class="intelebox-footer">
            <button type="submit" class="intelebox-button intelebox-button--primary"><?php esc_html_e( 'Save changes', 'international-telephone-box' ); ?></button>
            <button type="button" class="intelebox-button intelebox-button--secondary" data-reset><?php esc_html_e( 'Reset to defaults', 'international-telephone-box' ); ?></button>
        </footer>
    </form>
</div>
        <?php
    }
}
