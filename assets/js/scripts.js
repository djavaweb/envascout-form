(function ($) {
  $(document).on('mousedown', '.caldera_forms_form', function() {
    tinyMCE.triggerSave();
  });
})(jQuery);