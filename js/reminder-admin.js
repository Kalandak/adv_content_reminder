(function ($, Drupal, once) {

  Drupal.behaviors.reminderAdmin = {
    attach: function (context) {

      /*
       * ============================================
       * Preview Button Handler
       * ============================================
       */
      once('previewButton', '.preview-button', context).forEach(function (element) {

        $(element).on('click', function (e) {
          e.preventDefault();

          const stage = $(this).data('stage');

          const subject = $('input[name="' + stage + '[subject]"]').val();
          const body = $('textarea[name="' + stage + '[body][value]"]').val();

          if (!subject && !body) {
            alert('Nothing to preview.');
            return;
          }

          const $button = $(this);
          $button.prop('disabled', true);

          $.ajax({
            url: Drupal.url('admin/config/content/adv-content-reminder/preview'),
            method: 'POST',
            data: {
              subject: subject,
              body: body
            },
            success: function (response) {
              Drupal.dialog(response, {
                title: 'Email Preview',
                width: 800
              }).showModal();
            },
            error: function () {
              alert('Unable to generate preview.');
            },
            complete: function () {
              $button.prop('disabled', false);
            }
          });

        });

      });

      /*
       * ============================================
       * Send Test Email Handler (FIXED)
       * ============================================
       */
      once('testEmailButton', '.send-test-button', context).forEach(function (element) {

        $(element).on('click', function (e) {
          e.preventDefault();

          // ✅ SELECT BY NAME (Drupal-safe)
          const email = $('input[name="test_email"]').val();
          const stage = $('select[name="test_stage"]').val();

          if (!email) {
            alert('Please enter a test email address.');
            return;
          }

          const $button = $(this);
          $button.prop('disabled', true);

          $.ajax({
            url: Drupal.url('admin/config/content/adv-content-reminder/send-test'),
            method: 'POST',
            dataType: 'json',
            data: {
              email: email,
              stage: stage
            },
            success: function (response) {

              Drupal.dialog('<p>' + response.message + '</p>', {
                title: response.status === 'success' ? 'Success' : 'Error',
                width: 400
              }).showModal();

            },
            error: function () {
              Drupal.dialog('<p>Unexpected error sending test email.</p>', {
                title: 'Error',
                width: 400
              }).showModal();
            },
            complete: function () {
              $button.prop('disabled', false);
            }
          });

        });

      });

    }
  };

})(jQuery, Drupal, once);
