/**
 * @file
 * Joinup theme scripts.
 */

(function ($) {
  $(function () {
    $('.sidebar-menu__button--desktop').on('click', function () {
      $(this).siblings('.mdl-menu__container').toggleClass('is-hidden');
    });

    // Add classes to support menu button.
    $('.support-menu .support-button').each(function () {
      if (!$(this).closest('div').hasClass('hidden')) {
        $('.search-bar--header').addClass('search-bar--header-shifted');
      }
    });

    // Add wrapper to tables.
    $('.fieldset--textarea table').each(function () {
      $(this).wrap('<div class="fieldset__table-wrapper"></div>');
    });

    // Fix keydown event for mdl menu items.
    $('.mdl-menu__item').each(function () {
      $(this).on('keydown', function (event) {
        if (event.which === 13) {
          window.location.href = $(this).find('a').attr('href');
        }
      });
    });

    // Always use the fullscreen indicator for ajax throbbers in the frontend.
    if (typeof Drupal !== 'undefined' && Drupal.Ajax) {
      // Sets the fullscreen progress indicator.
      Drupal.Ajax.prototype.setProgressIndicatorFullscreen = function () {
        this.progress.element = $(
          '<div class="mdl-spinner mdl-js-spinner mdl-spinner--single-color is-active"></div>'
        );
        $('body').after(this.progress.element);
        componentHandler.upgradeAllRegistered();
      };
      Drupal.Ajax.prototype.setProgressIndicatorThrobber =
        Drupal.Ajax.prototype.setProgressIndicatorFullscreen;
    }

    // Refreshes MDL checkbox classes after ajax callbacks.
    $(document).ajaxComplete(function (event, xhr, settings) {
      componentHandler.upgradeAllRegistered();
      $('.mdl-js-checkbox').each(function (index, element) {
        element.MaterialCheckbox.updateClasses_();
      });
    });

  });
})(jQuery);
