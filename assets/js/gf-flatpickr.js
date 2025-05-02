jQuery(document).ready(function ($) {

  var linkedPickers = {}; // Global object to store min and max dates for linked pickers

  //Normalize the time to 0
  function setMidnight(date) {
    if (date instanceof Date) {
      date.setHours(0, 0, 0, 0); // Set time to midnight
    }
    return date;
  }

  function initializeFlatpickr() {
    $('.gfield--type-flatpickr_date input').each(function () {
      var $field = $(this);
      var blackoutDates = $field.data('blackout-dates') || [];
      var blackoutDays = $field.data('blackout-days') || [];
      var linkedPickerId = $field.data('linked-picker') || '';
      var minDateOffset = $field.data('min-date-offset') !== undefined ? parseInt($field.data('min-date-offset'), 10) : false;
      var maxDateOffset = parseInt($field.data('max-date-offset'), 10) || false;
      var mode = $field.data('calendar-mode') || 'single';

      // Parse blackout dates and days
      try {
        if (typeof blackoutDates === 'string') {
          blackoutDates = JSON.parse(blackoutDates);
        }
      } catch (e) {
        blackoutDates = blackoutDates.split(',').map(function (d) {
          return d.trim();
        });
      }

      try {
        if (typeof blackoutDays === 'string') {
          blackoutDays = JSON.parse(blackoutDays);
        }
      } catch (e) {
        // Keep as empty if parsing fails
      }

      // Calculate min and max dates for the current field
      var today = new Date();
      var minDate = null;
      var maxDate = null;

      if (minDateOffset !== false) {
        minDate = new Date(today);
        minDate.setDate(minDate.getDate() + minDateOffset);
        setMidnight(minDate);
      }
      if (maxDateOffset !== false) {
        maxDate = new Date(today);
        maxDate.setDate(maxDate.getDate() + maxDateOffset);
        setMidnight(maxDate);
      }

      // Check for globally stored pending min/max dates
      var fieldId = $field.attr('id');
      if (linkedPickers[fieldId]) {
        minDate = linkedPickers[fieldId].minDate || minDate;
        maxDate = linkedPickers[fieldId].maxDate || maxDate;
        delete linkedPickers[fieldId]; // Clear it after applying
      }

      // Initialize Flatpickr
      $field.flatpickr({
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat:'Y-m-d',
        mode: mode,
        minDate: minDate,
        maxDate: maxDate,
        disable: [
          ...blackoutDates,
          function (date) {
            return blackoutDays.indexOf(date.getDay()) > -1;
          },
        ],
        onChange: function (selectedDates, dateStr, instance) {
          if (linkedPickerId) {
            var $linkedField = $('#input_' + linkedPickerId);

            if ($linkedField.length > 0) {
              // Calculate minDate and maxDate for the linked picker
              var newMinDate = new Date(selectedDates[0]); // Use the actual Date object
              newMinDate.setHours(0, 0, 0, 0); // Set time to midnight
              //console.log('Normalized newMinDate:', newMinDate);
              //var newMinDate = new Date(dateStr); // Use the selected date as the new minDate
              //setMidnight(newMinDate); // Normalize to midnight
              var newMaxDate = null;

              if (maxDateOffset !== false) {
                newMaxDate = new Date(newMinDate); // Clone the selected date
                //Get the MaxDateOffset of the linked picker
                maxDateOffset = parseInt($linkedField.data('max-date-offset'), 10) || false;
                newMaxDate.setDate(newMinDate.getDate() + maxDateOffset); // Add the offset
                setMidnight(newMaxDate);

              }

              // Save the new dates globally for the linked picker
              linkedPickers['input_' + linkedPickerId] = {
                minDate: newMinDate,
                maxDate: newMaxDate,
              };

              // If the linked Flatpickr is already initialized, apply the new dates
              if ($linkedField[0]._flatpickr) {
                //console.log('Updating linked picker with:', newMinDate, newMaxDate);
                $linkedField[0]._flatpickr.set('minDate', newMinDate);
                $linkedField[0]._flatpickr.set('maxDate', newMaxDate);
              }
            }
          }

          const input = $(instance.input);
          input.trigger('flatpickr_date_changed', [dateStr, instance, fieldId]);

        },
        onDayCreate: function (dObj, dStr, fp, dayElem) {
          const date = dayElem.dateObj;
          const dayOfWeek = date.getDay();

          // Add custom styles for Saturday and Sunday
          if (dayOfWeek === 0) {
            dayElem.classList.add('sunday'); // Add class for Sunday
          } else if (dayOfWeek === 6) {
            dayElem.classList.add('saturday'); // Add class for Saturday
          }
        }
      });
    });
  }

  // Initialize Flatpickr on document ready
  initializeFlatpickr();

  // Reinitialize Flatpickr on Gravity Forms AJAX render
  $(document).on('gform_post_render', function () {
    initializeFlatpickr();
  });
});
