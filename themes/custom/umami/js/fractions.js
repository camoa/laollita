(function (Drupal, once) {
  'use strict';

  /**
   * Fraction character mappings.
   */
  const fractionMap = {
    '.5': '½',
    '.25': '¼',
    '.75': '¾',
    '.33': '⅓',
    '.333': '⅓',
    '.67': '⅔',
    '.667': '⅔',
    '.125': '⅛',
    '.375': '⅜',
    '.625': '⅝',
    '.875': '⅞',
    '.2': '⅕',
    '.4': '⅖',
    '.6': '⅗',
    '.8': '⅘'
  };

  /**
   * Convert decimal string to fraction display.
   */
  function convertToFraction(text) {
    return text.replace(/(\d+)?(\.\d+)/g, function (match, whole, decimal) {
      const fraction = fractionMap[decimal];
      if (fraction) {
        if (!whole || whole === '0') {
          return fraction;
        }
        return whole + fraction;
      }
      return match;
    });
  }

  /**
   * Convert ingredient amounts from decimals to fractions.
   */
  Drupal.behaviors.recetasFractions = {
    attach: function (context) {
      once('fractions', '.ingredient-amount', context).forEach(function (element) {
        const original = element.textContent;
        const converted = convertToFraction(original);
        if (original !== converted) {
          element.textContent = converted;
        }
      });
    }
  };

})(Drupal, once);
