// DT DISCONTINUED QUANTITY LIMIT
(function () {
  function getLimits(input) {
    var max = parseFloat(input.getAttribute('data-dt-max-quantity'));

    var step = parseFloat(input.getAttribute('data-dt-quantity-step'));

    if (!isFinite(max) || !isFinite(step) || step <= 0) {
      return null;
    }

    max = Math.floor((max + 0.000000001) / step) * step;

    return {
      max: Number(max.toFixed(8)),
      step: step,
    };
  }

  function normalizeQuantity(input) {
    var limits = getLimits(input);

    if (!limits || limits.max < limits.step) {
      return;
    }

    var value = parseFloat(input.value.replace(',', '.'));

    if (!isFinite(value) || value < limits.step) {
      value = limits.step;
    }

    value = Math.min(value, limits.max);

    value = Math.floor((value + 0.000000001) / limits.step) * limits.step;

    input.value = Number(value.toFixed(8));
  }

  document.addEventListener(
    'click',
    function (event) {
      var target = event.target;

      if (!target || !target.closest) {
        return;
      }

      var button = target.closest('a.plus, a.minus');

      if (!button) {
        return;
      }

      var form = button.closest(
        'form.add2basket_form, form.add2basket_search_form',
      );

      if (!form) {
        return;
      }

      var input = form.querySelector(
        "input[name='quantity'][data-dt-max-quantity]",
      );

      if (!input) {
        return;
      }

      var limits = getLimits(input);

      if (!limits) {
        return;
      }

      // Отключаем штатный onclick только для отмеченных товаров.
      event.preventDefault();
      event.stopImmediatePropagation();

      if (limits.max < limits.step) {
        return;
      }

      normalizeQuantity(input);

      var value = parseFloat(input.value);

      if (button.classList.contains('plus')) {
        value += limits.step;
      } else {
        value -= limits.step;
      }

      input.value = Number(
        Math.max(limits.step, Math.min(value, limits.max)).toFixed(8),
      );
    },
    true,
  );

  document.addEventListener(
    'change',
    function (event) {
      var input = event.target;

      if (
        input &&
        input.matches &&
        input.matches("input[name='quantity'][data-dt-max-quantity]")
      ) {
        normalizeQuantity(input);
      }
    },
    true,
  );
})();
// END DT DISCONTINUED QUANTITY LIMIT
