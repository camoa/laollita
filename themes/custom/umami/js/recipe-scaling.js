/**
 * @file
 * Recipe scaling functionality - adjusts ingredient amounts based on servings.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Fraction map for converting decimals to Unicode fractions.
   */
  const fractionMap = {
    '.5': '½',
    '.25': '¼',
    '.75': '¾',
    '.33': '⅓',
    '.333': '⅓',
    '.34': '⅓',
    '.67': '⅔',
    '.667': '⅔',
    '.66': '⅔',
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
   * Convert decimal to fraction display.
   */
  function toFraction(num) {
    if (Number.isInteger(num)) {
      return num.toString();
    }

    const whole = Math.floor(num);
    const decimal = num - whole;
    const decimalStr = '.' + decimal.toFixed(3).split('.')[1];

    // Try exact matches first.
    for (const [key, fraction] of Object.entries(fractionMap)) {
      if (Math.abs(parseFloat(key) - decimal) < 0.02) {
        if (whole === 0) {
          return fraction;
        }
        return whole + fraction;
      }
    }

    // No fraction match, return rounded decimal.
    if (whole === 0) {
      return num.toFixed(2).replace(/\.?0+$/, '');
    }
    return num.toFixed(2).replace(/\.?0+$/, '');
  }

  /**
   * Parse an amount string that may be a range (e.g., "400-500").
   * Returns an object with min and max values.
   */
  function parseAmount(amountStr) {
    const str = amountStr.trim();

    // Check for range pattern (number-number).
    const rangeMatch = str.match(/^(\d+(?:\.\d+)?)\s*-\s*(\d+(?:\.\d+)?)$/);
    if (rangeMatch) {
      return {
        isRange: true,
        min: parseFloat(rangeMatch[1]),
        max: parseFloat(rangeMatch[2])
      };
    }

    // Single number.
    const num = parseFloat(str);
    if (!isNaN(num)) {
      return {
        isRange: false,
        value: num
      };
    }

    // Unparseable - return null.
    return null;
  }

  /**
   * Format a scaled amount for display.
   */
  function formatAmount(parsed, scaleFactor) {
    if (!parsed) {
      return null;
    }

    if (parsed.isRange) {
      const scaledMin = parsed.min * scaleFactor;
      const scaledMax = parsed.max * scaleFactor;
      // For ranges, round to reasonable precision.
      const formatNum = (n) => {
        if (n >= 10) {
          return Math.round(n).toString();
        }
        return toFraction(n);
      };
      return formatNum(scaledMin) + '-' + formatNum(scaledMax);
    }

    return toFraction(parsed.value * scaleFactor);
  }

  /**
   * Recipe scaling behavior.
   */
  Drupal.behaviors.recipeScaling = {
    attach: function (context) {
      once('recipe-scaling', '.recipe-scaling', context).forEach(function (container) {
        const originalServings = parseInt(container.dataset.originalServings, 10);
        let currentServings = originalServings;

        const servingsDisplay = container.querySelector('.recipe-scaling__servings');
        const decreaseBtn = container.querySelector('.recipe-scaling__btn--decrease');
        const increaseBtn = container.querySelector('.recipe-scaling__btn--increase');
        const resetBtn = container.querySelector('.recipe-scaling__reset');

        // Find all ingredient amounts on the page.
        const ingredientAmounts = document.querySelectorAll('.ingredient-amount[data-original]');

        /**
         * Update all ingredient amounts based on current servings.
         */
        function updateAmounts() {
          const scaleFactor = currentServings / originalServings;

          ingredientAmounts.forEach(function (element) {
            const original = element.dataset.original;
            const parsed = parseAmount(original);
            const formatted = formatAmount(parsed, scaleFactor);

            if (formatted !== null) {
              element.textContent = formatted;
            }
          });

          // Update servings display.
          servingsDisplay.textContent = currentServings;

          // Update button states.
          decreaseBtn.disabled = currentServings <= 1;

          // Show/hide reset button.
          if (currentServings === originalServings) {
            resetBtn.classList.add('recipe-scaling__reset--hidden');
          } else {
            resetBtn.classList.remove('recipe-scaling__reset--hidden');
          }
        }

        // Decrease servings.
        decreaseBtn.addEventListener('click', function () {
          if (currentServings > 1) {
            currentServings--;
            updateAmounts();
          }
        });

        // Increase servings.
        increaseBtn.addEventListener('click', function () {
          currentServings++;
          updateAmounts();
        });

        // Reset to original.
        resetBtn.addEventListener('click', function () {
          currentServings = originalServings;
          updateAmounts();
        });

        // Initial state - hide reset if at original.
        if (currentServings === originalServings) {
          resetBtn.classList.add('recipe-scaling__reset--hidden');
        }
      });
    }
  };

})(Drupal, once);
