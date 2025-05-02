<?php
/**
 * Plugin Name: Gravity Forms FlatPickr Date Field
 * Description: Adds an advanced date field to Gravity Forms using Flatpickr with blackout dates, repeating blackout days, and interdependent pickers.
 * Version: 1.0.0
 * Author: Nic Scott
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * 1. Define the custom field class by extending GF_Field
 */
class GF_Field_Advanced_Date extends GF_Field {

    public $type = 'flatpickr_date';

    /**
     * Get field title for the form editor.
     */
    public function get_form_editor_field_title() {
        return esc_html__( 'Flatpickr Date Picker', 'gravityforms' );
    }

    /**
     * Specify which button group to display this field in.
     */
    public function get_form_editor_button() {
        return array(
            'group' => 'advanced_fields',
            'text'  => $this->get_form_editor_field_title(),
        );
    }

    /**
     * This is the main list of standard settings displayed in the Form Editor.
     * Each string corresponds to a setting partial that Gravity Forms provides.
     */
    public function get_form_editor_field_settings() {
        return array(
            'label_setting',
            'description_setting',
            'css_class_setting',
            'visibility_setting',
            'conditional_logic_field_setting',
            'error_message_setting',
            'rules_setting', // Standard "Required" checkbox
            'size_setting',
            // Custom field settings (defined by you).
            'calendar_mode_setting',
            'blackout_dates_setting',
            'blackout_days_setting',
            'linked_picker_setting',
            'us_holidays_setting', // <--- US Holidays
            'relative_date_setting',  // <--- Our new setting
        );
    }

    /**
     * (Optional) By default, GF won't know if your field supports conditional logic.
     * Return true here if you'd like to allow conditional logic on this field.
     */
    public function is_conditional_logic_supported() {
        return true;
    }

    /**
     * Render the actual input on the front end form.
     *
     * @param  array      $form  The Form object.
     * @param  mixed      $value Current value (from entry or $_POST).
     * @param  null|array $entry Entry if available.
     */
    public function get_field_input( $form, $value = '', $entry = null ) {

        //Mode
        $calendar_mode = isset( $this->calendar_mode ) ? $this->calendar_mode : 'single';


        // Original comma-separated dates entered by user
        $raw_blackout_dates = isset( $this->blackout_dates ) ? $this->blackout_dates : '';
        // Convert to array (split by comma)
        $blackout_dates = array_filter( array_map( 'trim', explode( ',', $raw_blackout_dates ) ) );

        // If US holiday keys selected, compute them as YYYY-MM-DD and merge
        if ( ! empty( $this->us_holidays ) ) {
            $holiday_dates = $this->compute_us_holidays( $this->us_holidays ); 
            // Merge with existing
            $blackout_dates = array_merge( $blackout_dates, $holiday_dates );
        }

        // Re-combine as comma-separated if that's how you're passing it to JS
        $final_blackout_dates = json_encode( $blackout_dates );
        


        $blackout_days  = isset( $this->blackout_days ) ? json_encode( $this->blackout_days ) : '[]';
        $linked_picker  = isset( $this->linked_picker )  ? $form['id'] . '_' . $this->linked_picker : '';

        // Offsets
        $min_date_offset    = isset( $this->min_date_offset ) ? $this->min_date_offset : 0; 
        $max_date_offset    = isset( $this->max_date_offset ) ? $this->max_date_offset : 0;

        // GF convention: "input_{field_id}"
        $field_id   = $this->id;
        $input_name = "input_{$this->id}";

        // Build the field markup
        $tabindex   = $this->get_tabindex();  // Gravity Forms handles accessible tabbing
        $css_class  = esc_attr( $this->size ); // or use a custom class if you prefer





        ob_start();
        ?>
        <input 
            type="text"
            name="<?php echo esc_attr( $input_name ); ?>"
            id="input_<?php echo absint( $form['id'] ); ?>_<?php echo absint( $field_id ); ?>"
            class="gform_flatpickr_date <?php echo $css_class; ?>"
            data-blackout-dates="<?php echo esc_attr( $final_blackout_dates ); ?>"
            data-blackout-days="<?php echo esc_attr( $blackout_days ); ?>"
            data-linked-picker="<?php echo esc_attr( $linked_picker ); ?>"
            data-min-date-offset="<?php echo esc_attr( $min_date_offset ); ?>"
            data-max-date-offset="<?php echo esc_attr( $max_date_offset ); ?>"
            data-calendar-mode="<?php echo esc_attr( $calendar_mode ); ?>"
            value="<?php echo esc_attr( $value ); ?>"
            <?php echo $tabindex; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        />
        <?php
        return ob_get_clean();
    }

    /**
     * Inline script that gets loaded in the Form Editor.
     * Used to link JS-based settings to the correct UI fields.
     */
    public function get_form_editor_inline_script_on_page_render() {
        // The key here must match the $type property: 'fieldSettings.flatpickr_date'
        // so GF knows which settings to show/hide.
        return <<<JS
            //fieldSettings.flatpickr_date = '.label_setting, .description_setting, .blackout_dates_setting, .blackout_days_setting, .linked_picker_setting';

            // When the field is selected in the editor, load the custom properties into the inputs.
            jQuery(document).on('gform_load_field_settings', function(event, field, form){
                if(field.type === 'flatpickr_date') {
                    jQuery('#field_blackout_dates').val( field.blackout_dates ? field.blackout_dates : '' );
                    jQuery('#field_blackout_days').val( field.blackout_days ? field.blackout_days : '' );
                    jQuery('#field_linked_picker').val( field.linked_picker ? field.linked_picker : '' );
                    jQuery('#field_min_date_offset').val(field.min_date_offset || '');
                    jQuery('#field_max_date_offset').val(field.max_date_offset || '');
                    jQuery('#field_calendar_mode').val(field.calendar_mode || '');
                }

                // US Holidays: set checkboxes based on field.us_holidays
                let selected = field.us_holidays ? field.us_holidays.split(',') : [];
                    jQuery('.us-holiday-checkbox').each(function(){
                        jQuery(this).prop('checked', selected.indexOf(jQuery(this).val()) !== -1);
                    });

            });



            function onHolidayCheckboxChange() {
                var selected = [];
                jQuery('.us-holiday-checkbox').each(function(){
                    if (jQuery(this).is(':checked')) {
                        selected.push(jQuery(this).val());
                    }
                });
                // Store in the field object
                SetFieldProperty('us_holidays', selected.join(','));

                // If not all are selected, uncheck "select all" box
                if (selected.length < jQuery('.us-holiday-checkbox').length) {
                    jQuery('#select_all_holidays').prop('checked', false);
                } else {
                    jQuery('#select_all_holidays').prop('checked', true);
                }
            }

            // For the "Select All" checkbox
            jQuery('#select-all-holidays').on('click', function(){
                var checked = jQuery(this).is(':checked');
                jQuery('.us-holiday-checkbox').prop('checked', checked);
                onHolidayCheckboxChange();
            });

            
        JS;
    }





/**
 * Placeholder method to determine how many years ahead to compute.
 * Replace this with actual logic to fetch "max date" from Gravity Forms field settings.
 */
private function get_max_year_offset() {

    $max_date_offset = isset( $this->max_date_offset ) ? $this->max_date_offset : 2;

    return ceil( $max_date_offset / 365 );

}





/**
 * Compute holiday dates on the server side for a range of years.
 *
 * @param string $selected_holidays Comma-separated holiday keys.
 * @return array List of holiday dates (YYYY-MM-DD) across the years.
 */
private function compute_us_holidays( $selected_holidays ) {
    // Determine the start year (current year)
    $start_year = (int) current_time( 'Y' );

    // Assume we have a method or setting to get how many years ahead to compute.
    // Replace get_max_year_offset() with actual retrieval method for your configuration.
    $max_year_offset = $this->get_max_year_offset(); 
    $end_year = $start_year + (int) $max_year_offset;

    // Convert comma-separated string to array of holiday keys
    $holiday_keys = array_filter( array_map( 'trim', explode( ',', $selected_holidays ) ) );
    if ( empty( $holiday_keys ) ) {
        return [];
    }

    $all_holiday_dates = [];

    // Helper functions remain unchanged (fmt, nthWeekdayOfMonth, lastWeekdayOfMonth)
    $fmt = function( DateTime $date ) {
        return $date->format('Y-m-d');
    };
    $nthWeekdayOfMonth = function( $year, $month, $weekday, $nth ) {
        $firstOfMonth = new DateTime("$year-$month-01");
        $firstDow = (int) $firstOfMonth->format('w'); 
        $offset = ($weekday - $firstDow + 7) % 7 + (7 * ($nth - 1));
        return (clone $firstOfMonth)->modify("+{$offset} days");
    };
    $lastWeekdayOfMonth = function( $year, $month, $weekday ) {
        $dt = new DateTime("$year-$month-01");
        $dt->modify('last day of this month');
        while ( (int) $dt->format('w') !== $weekday ) {
            $dt->modify('-1 day');
        }
        return $dt;
    };

    // Loop over each year in the range
    for ( $year = $start_year; $year <= $end_year; $year++ ) {
        // For each holiday key, compute the date for the given year
        foreach ( $holiday_keys as $key ) {
            switch ( $key ) {
                case 'newyears':
                    $all_holiday_dates[] = "$year-01-01";
                    break;

                case 'mlk': {
                    $d = $nthWeekdayOfMonth($year, 1, 1, 3);
                    $all_holiday_dates[] = $fmt($d);
                    break;
                }

                case 'presidents': {
                    $d = $nthWeekdayOfMonth($year, 2, 1, 3);
                    $all_holiday_dates[] = $fmt($d);
                    break;
                }

                case 'good_friday': {
                    $easter = new DateTime('@' . easter_date( $year ));
                    $good_friday = $easter->modify('-2 days')->format('Y-m-d');
                    $all_holiday_dates[] = $good_friday;
                    break;
                }

                case 'memorial': {
                    $d = $lastWeekdayOfMonth($year, 5, 1);
                    $all_holiday_dates[] = $fmt($d);
                    break;
                }

                case 'juneteenth':
                    $all_holiday_dates[] = "$year-06-19";
                    break;

                case 'july4':
                    $all_holiday_dates[] = "$year-07-04";
                    break;

                case 'labor': {
                    $d = $nthWeekdayOfMonth($year, 9, 1, 1);
                    $all_holiday_dates[] = $fmt($d);
                    break;
                }

                case 'columbus': {
                    $d = $nthWeekdayOfMonth($year, 10, 1, 2);
                    $all_holiday_dates[] = $fmt($d);
                    break;
                }

                case 'election_day': {
                    $first_monday = $nthWeekdayOfMonth( $year, 11, 1, 1 );
                    if ( $first_monday->format('j') == 1 ) {
                        $first_monday->modify('+7 days');
                    }
                    $election_day = $first_monday->modify('+1 day')->format('Y-m-d');
                    $all_holiday_dates[] = $election_day;
                    break;
                }

                case 'veterans':
                    $all_holiday_dates[] = "$year-11-11";
                    break;

                case 'thanksgiving': {
                    $d = $nthWeekdayOfMonth($year, 11, 4, 4);
                    $all_holiday_dates[] = $fmt($d);
                    break;
                }

                case 'black_friday': {
                    $d = $nthWeekdayOfMonth($year, 11, 5, 4);
                    $all_holiday_dates[] = $fmt($d);
                    break;
                }

                case 'christmas':
                    $all_holiday_dates[] = "$year-12-25";
                    break;

                default:
                    // Unrecognized holiday key; skip
                    break;
            }
        }
    }

    return $all_holiday_dates;
}



    /**
     * Validate user-inputted value (e.g., ensure date is not in blackout list, etc.).
     */
    public function validate( $value, $form ) {
        // Example: Basic check if value is empty but required
        if ( $this->isRequired && rgblank( $value ) ) {
            $this->failed_validation  = true;
            $this->validation_message = esc_html__( 'Please select a date.', 'gravityforms' );
        }
        // You could also parse $value and check it against $this->blackout_dates, etc.
    }

    /**
     * Enqueue the Flatpickr library and our custom JS/CSS when the form is displayed or in the admin.
     */
    public function enqueue_scripts() {
        // Only enqueue once to avoid duplicates
        if ( ! wp_script_is( 'flatpickr', 'enqueued' ) ) {
            wp_enqueue_script(
                'flatpickr',
                plugin_dir_url( __FILE__ ) . 'assets/js/flatpickr.min.js',
                array(),
                '4.6.13',
                true
            );
        }

        wp_enqueue_script(
            'gf-flatpickr-date',
            plugin_dir_url( __FILE__ ) . 'assets/js/gf-flatpickr.js',
            array( 'flatpickr' ),
            '1.0.0',
            true
        );

        if ( ! wp_style_is( 'flatpickr-css', 'enqueued' ) ) {
            wp_enqueue_style(
                'flatpickr-css',
                plugin_dir_url( __FILE__ ) . 'assets/css/flatpickr.min.css',
                array(),
                '4.6.13'
            );
        }
    }
}

/**
 * 2. Register this field with Gravity Forms once GF is loaded.
 */
add_action( 'gform_loaded', function() {
    if ( class_exists( 'GF_Field' ) ) {
        GF_Fields::register( new GF_Field_Advanced_Date() );
    }
} );

/**
 * 3. Enqueue scripts on front-end form load
 */
add_action( 'wp_enqueue_scripts', function() {
    // Just instantiate the class to call enqueue_scripts
    $field = new GF_Field_Advanced_Date();
    $field->enqueue_scripts();
} );

/**
 * 4. Enqueue scripts in the admin as well (for form preview, etc.)
 */
add_action( 'admin_enqueue_scripts', function() {
    $field = new GF_Field_Advanced_Date();
    $field->enqueue_scripts();
} );

/**
 * 5. (Optional) Add the HTML for the custom field settings in the GF editor sidebar.
 *    This is how your .blackout_dates_setting, etc., get rendered.
 *    Gravity Forms automatically picks up the class names to attach them to 'fieldSettings.flatpickr_date'.
 */
add_action( 'gform_field_standard_settings', function( $position, $form_id ) {
    // This displays after the 'Appearance' tab (1600 is typical).
    if ( 1600 === $position ) : ?>

        <li class="calendar_mode_setting field_setting">
            <label for="field_calendar_mode" class="section_label">
                <?php esc_html_e( 'Calendar Mode', 'gravityforms' ); ?>
            </label>
            <select id="field_calendar_mode" onchange="SetFieldProperty('calendar_mode', this.value)" >
                <option value="single">Single</option>
                <option value="multiple">Multiple (untested)</option>
                <option value="range">Range</option>
            </select>

        </li>
        <li class="blackout_dates_setting field_setting">
            <label for="field_blackout_dates" class="section_label">
                <?php esc_html_e( 'Blackout Dates (comma separated; 2025-01-08)', 'gravityforms' ); ?>
            </label>
            <input type="text" id="field_blackout_dates" oninput="SetFieldProperty('blackout_dates', this.value)" />
        </li>

        <li class="blackout_days_setting field_setting">
            <label for="field_blackout_days" class="section_label">
                <?php esc_html_e( 'Blackout Days (0=Sun,1=Mon...)', 'gravityforms' ); ?>
            </label>
            <input type="text" id="field_blackout_days" oninput="SetFieldProperty('blackout_days', this.value)" />
        </li>

        <li class="linked_picker_setting field_setting">
            <label for="field_linked_picker" class="section_label">
                <?php esc_html_e( 'Other Picker ID for Min Date', 'gravityforms' ); ?>
            </label>
            <input type="text" id="field_linked_picker" oninput="SetFieldProperty('linked_picker', this.value)" />
        </li>

    <?php
    endif;
}, 10, 2 );





add_action( 'gform_field_standard_settings', function( $position, $form_id ) {
    if ( 1600 === $position ) : ?>
        
        <!-- US Holidays Setting -->
        <li class="us_holidays_setting field_setting">
            <label class="section_label" for="field_us_holidays">
                <?php esc_html_e( 'Disable US Federal Holidays', 'gravityforms' ); ?>
            </label>

            <?php
            // Define your holiday "key => label" pairs.
            // The "key" is how we'll store it in the field property.
            $holidays = [
                'newyears'    => "New Year's Day (Jan 1)",
                'mlk'         => "MLK Day (3rd Monday in Jan)",
                'presidents'  => "Presidents Day (3rd Monday in Feb)",
                'good_friday' => "Good Friday",
                'memorial'    => "Memorial Day (Last Monday in May)",
                'juneteenth'  => "Juneteenth (June 19)",
                'july4'       => "Independence Day (July 4)",
                'labor'       => "Labor Day (1st Monday in Sep)",
                'columbus'    => "Columbus Day (2nd Monday in Oct)",
                'election_day' => 'Election Day',
                'veterans'    => "Veterans Day (Nov 11)",
                'thanksgiving'=> "Thanksgiving (4th Thu in Nov)",
                'black_friday' => "Friday after Thanksgiving",
                'christmas'   => "Christmas (Dec 25)",
            ];

            // Output each as a checkbox
            echo '<ul>';

                echo '<li>';
                echo( '<input type="checkbox" id="select-all-holidays" onclick="onHolidayCheckboxChange()">' );
                echo( '<label for="select-all-holidays" class="inline">Select All</label>' );
                echo '</li>';

            foreach( $holidays as $key => $label ) {
                
                echo '<li>';
                printf( '<input type="checkbox" id="holiday-%1$s" class="us-holiday-checkbox" value="%1$s" onclick="onHolidayCheckboxChange()">', esc_attr( $key ) );
                printf( '<label for="holiday-%s" class="inline">%s</label>', $key, $label );
                echo '</li>';
            }
            echo '</ul>';
            ?>
        </li>

        <li class="relative_date_setting field_setting">
            <label class="section_label" for="field_min_date_offset">
                <?php esc_html_e( 'Relative Date Offsets (Days)', 'gravityforms' ); ?>
            </label>
            <br/>

            <div style="margin-top: 5px; display:flex; flex-direction:column;">
                <strong><?php esc_html_e( 'Min Days Offset', 'gravityforms' ); ?></strong>
                <input 
                    type="number" 
                    id="field_min_date_offset" 
                    style="width:80px; margin-bottom:.5rem;" 
                    oninput="SetFieldProperty('min_date_offset', this.value)" 
                />
                <small><?php esc_html_e( 'Negative = allow past days', 'gravityforms' ); ?></small>
            </div>

            <div style="margin-top: 5px; display:flex; flex-direction:column;">
                <strong><?php esc_html_e( 'Max Days Offset', 'gravityforms' ); ?></strong>
                <input 
                    type="number" 
                    id="field_max_date_offset" 
                    style="width:80px; margin-bottom:.5rem;" 
                    oninput="SetFieldProperty('max_date_offset', this.value)" 
                />
                <small><?php esc_html_e( 'Positive = allow future days', 'gravityforms' ); ?></small>
            </div>
        </li>
    
    <?php
    endif;
}, 10, 2 );
