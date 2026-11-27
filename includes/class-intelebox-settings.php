<?php
/**
 * Settings storage, sanitising and consistency rules for International Telephone Box.
 *
 * @package International_Telephone_Box
 */

namespace International_Telephone_Box;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Intelebox_Settings
 *
 * One option, `intelebox_settings`, sanitised on save and re-validated on read
 * so a bad stored value can never break the front end.
 */
class Intelebox_Settings
{

    /**
     * Option name.
     */
    const OPTION_NAME = 'intelebox_settings';

    /**
     * Settings API group.
     */
    const OPTION_GROUP = 'intelebox_settings_group';

    /**
     * Allowed values for `country_mode`.
     */
    const COUNTRY_MODES = array( 'all', 'include', 'exclude' );

    /**
     * Maximum number of preferred countries.
     */
    const MAX_PREFERRED = 5;

    /**
     * Last-resort fallback ISO2 code.
     */
    const FALLBACK_DEFAULT = 'eg';

    /**
     * Cached country data.
     *
     * @var array|null
     */
    private static $countries_cache = null;

    /**
     * Defaults for every key.
     *
     * @return array
     */
    public static function defaults(): array
    {
        return array(
            'country_mode'       => 'all',
            'countries'          => array(),
            'preferred_countries' => array( self::FALLBACK_DEFAULT ),
            'initial_country'    => 'auto',
            'fallback_country'   => self::FALLBACK_DEFAULT,
            'geo_lookup'         => true,
        );
    }

    /**
     * Full list of valid countries, keyed by lower-case ISO2 code.
     *
     * Loads `countries.php` once. `il` is never present.
     *
     * @return array
     */
    public static function countries(): array
    {
        if ( null === self::$countries_cache ) {
            $countries = require INTELEBOX_PLUGIN_DIR . 'includes/data/countries.php';

            if ( ! is_array( $countries ) ) {
                $countries = array();
            }

            $clean = array();
            foreach ( $countries as $code => $data ) {
                $code = strtolower( (string) $code );
                if ( 'il' === $code ) {
                    continue;
                }
                $clean[ $code ] = $data;
            }

            self::$countries_cache = $clean;
        }

        return self::$countries_cache;
    }

    /**
     * Read the stored option, merge over defaults and re-apply consistency rules.
     *
     * @return array
     */
    public static function get(): array
    {
        $stored = get_option( self::OPTION_NAME, array() );

        if ( ! is_array( $stored ) ) {
            $stored = array();
        }

        $merged = array_merge( self::defaults(), $stored );

        return self::sanitize( $merged, false );
    }

    /**
     * Sanitize callback for `register_setting`, and the single source of truth
     * for validation and consistency.
     *
     * @param mixed $input         Raw value.
     * @param bool  $report_errors Whether to raise `add_settings_error` notices.
     * @return array
     */
    public static function sanitize( $input, bool $report_errors = true ): array
    {
        $defaults     = self::defaults();
        $countries    = self::countries();
        $valid_codes  = array_keys( $countries ); // Sorted by English name; `il` absent.
        $input        = is_array( $input ) ? $input : array();

        // Country mode.
        $mode = isset( $input['country_mode'] ) && is_string( $input['country_mode'] )
            ? strtolower( trim( $input['country_mode'] ) )
            : $defaults['country_mode'];
        if ( ! in_array( $mode, self::COUNTRY_MODES, true ) ) {
            $mode = $defaults['country_mode'];
        }

        // Selected countries (for include / exclude).
        $selected = self::sanitize_code_list(
            array_key_exists( 'countries', $input ) ? $input['countries'] : array(),
            $valid_codes
        );

        // Include mode with an empty list is invalid: fall back to "all".
        if ( 'include' === $mode && empty( $selected ) ) {
            $mode     = 'all';
            $selected = array();

            if ( $report_errors && function_exists( 'add_settings_error' ) ) {
                add_settings_error(
                    self::OPTION_NAME,
                    'intelebox_empty_countries',
                    __( 'Please select at least one country to offer, or choose "All countries".', 'international-telephone-box' ),
                    'error'
                );
            }
        }

        // The set of countries actually offered.
        if ( 'include' === $mode ) {
            $offered = $selected;
        } elseif ( 'exclude' === $mode ) {
            $offered = array_values( array_diff( $valid_codes, $selected ) );
        } else {
            $offered = $valid_codes;
        }

        // Offered list in alphabetical (English name) order, per `countries.php`.
        $offered_alpha = array_values(
            array_filter(
                $valid_codes,
                static function ( $code ) use ( $offered ) {
                    return in_array( $code, $offered, true );
                }
            )
        );

        // Preferred countries: valid, offered, ordered, de-duplicated, capped.
        $preferred = self::sanitize_code_list(
            array_key_exists( 'preferred_countries', $input ) ? $input['preferred_countries'] : array(),
            $valid_codes
        );
        $preferred = array_values(
            array_filter(
                $preferred,
                static function ( $code ) use ( $offered ) {
                    return in_array( $code, $offered, true );
                }
            )
        );
        $preferred = array_slice( $preferred, 0, self::MAX_PREFERRED );

        // Fallback country must be offered.
        $fallback_raw = self::sanitize_code(
            array_key_exists( 'fallback_country', $input ) ? $input['fallback_country'] : $defaults['fallback_country'],
            $valid_codes
        );
        if ( '' === $fallback_raw ) {
            $fallback_raw = $defaults['fallback_country'];
        }
        if ( ! in_array( $fallback_raw, $offered, true ) ) {
            $fallback_country = self::first_offered( $preferred, $offered_alpha );
        } else {
            $fallback_country = $fallback_raw;
        }

        // Initial country: "auto" or an offered ISO2 code.
        $initial_raw = array_key_exists( 'initial_country', $input ) ? $input['initial_country'] : $defaults['initial_country'];
        if ( is_string( $initial_raw ) && 'auto' === strtolower( trim( $initial_raw ) ) ) {
            $initial_country = 'auto';
        } else {
            $initial_code = self::sanitize_code( $initial_raw, $valid_codes );
            if ( '' !== $initial_code && ! in_array( $initial_code, $offered, true ) ) {
                $initial_code = '';
            }
            if ( '' === $initial_code ) {
                $initial_country = self::first_offered( $preferred, $offered_alpha );
            } else {
                $initial_country = $initial_code;
            }
        }

        // IP-lookup toggle.
        $geo_lookup = self::sanitize_bool(
            array_key_exists( 'geo_lookup', $input ) ? $input['geo_lookup'] : false
        );

        return array(
            'country_mode'        => $mode,
            'countries'           => $selected,
            'preferred_countries' => $preferred,
            'initial_country'     => $initial_country,
            'fallback_country'    => $fallback_country,
            'geo_lookup'          => $geo_lookup,
        );
    }

    /**
     * Preferred first, else first offered alphabetically, else a safe default.
     *
     * @param array $preferred     Filtered preferred codes.
     * @param array $offered_alpha Offered codes in alphabetical order.
     * @return string
     */
    private static function first_offered( array $preferred, array $offered_alpha ): string
    {
        if ( ! empty( $preferred ) ) {
            return $preferred[0];
        }
        if ( ! empty( $offered_alpha ) ) {
            return $offered_alpha[0];
        }
        return self::FALLBACK_DEFAULT;
    }

    /**
     * Sanitize a list of ISO2 codes: lower-case, keep only valid, de-duplicate.
     *
     * @param mixed $value       Raw list (or comma-separated string).
     * @param array $valid_codes Valid lower-case codes.
     * @return array
     */
    private static function sanitize_code_list( $value, array $valid_codes ): array
    {
        if ( is_string( $value ) ) {
            $value = explode( ',', $value );
        }

        if ( ! is_array( $value ) ) {
            return array();
        }

        $out = array();
        foreach ( $value as $item ) {
            if ( ! is_string( $item ) && ! is_numeric( $item ) ) {
                continue;
            }
            $code = strtolower( trim( (string) $item ) );
            if ( '' === $code ) {
                continue;
            }
            if ( ! in_array( $code, $valid_codes, true ) ) {
                continue; // Drops `il`, `xx`, `<script>`, anything unknown.
            }
            if ( in_array( $code, $out, true ) ) {
                continue; // De-duplicate.
            }
            $out[] = $code;
        }

        return $out;
    }

    /**
     * Sanitize a single ISO2 code; '' when invalid.
     *
     * @param mixed $value       Raw value.
     * @param array $valid_codes Valid lower-case codes.
     * @return string
     */
    private static function sanitize_code( $value, array $valid_codes ): string
    {
        if ( ! is_string( $value ) ) {
            return '';
        }
        $code = strtolower( trim( $value ) );
        if ( ! in_array( $code, $valid_codes, true ) ) {
            return '';
        }
        return $code;
    }

    /**
     * Sanitize a boolean coming from a checkbox or stored value.
     *
     * @param mixed $value Raw value.
     * @return bool
     */
    private static function sanitize_bool( $value ): bool
    {
        if ( is_bool( $value ) ) {
            return $value;
        }
        return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
    }
}
