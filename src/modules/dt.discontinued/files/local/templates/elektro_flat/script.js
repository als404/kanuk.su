function adjustItemHeight(item) {
  var maxHeight = 0;
  item.css('height', 'auto');
  item.each(function () {
    if ($(this).height() > maxHeight) {
      maxHeight = $(this).height();
    }
  });
  item.height(maxHeight);
}

$(function () {
  $('.close').live('click', function () {
    CloseModalWindow('#addItemInCart');
  });
  $(document).keyup(function (event) {
    if (event.keyCode == 27) {
      CloseModalWindow('#addItemInCart');
    }
  });
});

function CentriredModalWindow(ModalName) {
  $(window).resize(function () {
    modalHeight = ($(window).height() - $(ModalName).height()) / 2;
    $(ModalName).css({
      top: modalHeight + 'px',
    });
  });
  $(window).resize();
}

function OpenModalWindow(ModalName) {
  $(ModalName).fadeIn(300);
  $('#bgmod').fadeIn(300);
}

function CloseModalWindow(ModalName) {
  $('#bgmod').fadeOut(300);
  $(ModalName).fadeOut(300);
}

function refreshCartLine(result, disabled) {
  disabled = disabled || false;

  var basketCont, sumOld, sumCurr;

  basketCont = $('.cart_line');

  basketCont.find('.qnt').text($(result).find('.qnt').text());

  basketCont
    .find('.sum')
    .data('decimal', $(result).find('.sum').data('decimal'));

  sumOld = basketCont.find('.sum').data('sum');
  basketCont.find('.sum').data('sum', $(result).find('.sum').data('sum'));
  sumCurr = basketCont.find('.sum').data('sum');

  if (sumCurr != sumOld) {
    var options = {
      useEasing: false,
      useGrouping: true,
      separator: basketCont.find('.sum').data('separator'),
      decimal: basketCont.find('.sum').data('dec-point'),
    };
    var counter = new countUp(
      'cartCounter',
      sumOld,
      sumCurr,
      basketCont.find('.sum').data('decimal'),
      0.5,
      options,
    );
    counter.start();
  }

  if (disabled != true)
    basketCont
      .find('.oformit_cont')
      .html($(result).find('.oformit_cont').html());
}

function addToCompare(href, btn, site_dir) {
  $.ajax({
    type: 'POST',
    url: href,
    success: function (html) {
      $.post(site_dir + 'ajax/compare_line.php', function (data) {
        $('.compare_line').replaceWith(data);
      });
      $('#' + btn)
        .removeClass('catalog-item-compare')
        .addClass('catalog-item-compared')
        .removeAttr('onclick')
        .css({ cursor: 'default' });
    },
  });
  return false;
}

function addToDelay(id, qnt_cont, props, select_props, btn, site_dir, dsbl) {
  dsbl = dsbl || false;
  $.ajax({
    type: 'POST',
    url: site_dir + 'ajax/add2delay.php',
    data:
      'id=' +
      id +
      '&qnt=' +
      $('#' + qnt_cont).val() +
      '&props=' +
      props +
      '&select_props=' +
      select_props,
    success: function (html) {
      $.post(site_dir + 'ajax/delay_line.php', function (data) {
        $('.delay_line').replaceWith(data);
      });
      $.post(site_dir + 'ajax/basket_line.php', function (data) {
        refreshCartLine(data, dsbl);
      });
      $('#' + btn)
        .removeClass('catalog-item-delay')
        .addClass('catalog-item-delayed')
        .removeAttr('onclick')
        .css({ cursor: 'default' });
    },
  });
  return false;
}

// Раскрываем фильтры.
// Оставляем параметры "Розничная цена" и "Производитель" остальное скрываем и добавляем ссылку "Показать все параметры".
// Кликая по ссылке раскрываем все остальные параметры.
document.addEventListener('DOMContentLoaded', () => {
  (() => {
    'use strict';
    const filterList = document.querySelectorAll('.bx_filter_box');

    if (filterList.length != 0) {
      init(document.querySelector('.filter table tbody'));

      const btnShowFilter = document.querySelector('.btn__show_filter');

      hideProps(filterList, btnShowFilter);

      btnShowFilter.addEventListener('click', () => {
        showProps(filterList, btnShowFilter);
      });
    }

    function init(parent) {
      let tr = document.createElement('tr');
      tr.innerHTML =
        '<tr style="display: block;"><td colspan="2" style="text-align:center; color:#cd3367; cursor:pointer;"><div class="btn__show_filter">Показать все параметры</div></td></tr>';
      parent.append(tr);

      const toogleFilter = document.querySelector(
        '.catalog_item_toogle_filter',
      );
      if (!toogleFilter.classList.contains('active')) {
        toogleFilter.classList.add('active');
        document.querySelector('.filter').style.display = 'block';
      }
    }

    function show(elem) {
      elem.style.display = 'block';
    }

    function hide(elem) {
      elem.style.display = 'none';
    }

    function hideProps(boxs, btn) {
      boxs.forEach((item) => {
        const name = item.children[0].innerText;
        if (
          name.indexOf('Розничная цена') == 0 ||
          name.indexOf('Производитель') == 0
        ) {
          show(item);
        } else {
          hide(item);
        }
      });

      if (btn) {
        btn.classList = 'btn__show_filter';
        btn.innerText = 'Показать все параметры';
        btn.addEventListener('click', () => {
          showProps(boxs, btn);
        });
      }
    }

    function showProps(boxs, btn) {
      boxs.forEach((item) => {
        if (item.style.display == 'none') {
          show(item);
        }
      });

      btn.classList = 'btn__hide_filter';
      btn.innerText = 'Скрыть параметры';

      btn.addEventListener('click', () => {
        hideProps(boxs, btn);
      });
    }
  })();
});

document.addEventListener('DOMContentLoaded', () => {
  (() => {
    'use strict';
    let meta = '';
    let title = '';
    let catalogDescription = document.querySelector('.catalog_description');
  })();
});

document.addEventListener('DOMContentLoaded', () => {
  (() => {
    // use query selector that matches your class name
    // you may assign it to a variable; we have used literal here
    // to make this example more clear.

    const lazyBackgrounds = [].slice.call(
      document.querySelectorAll('.lazy-background'),
    );

    if ('IntersectionObserver' in window) {
      let lazyBackgroundObserver = new IntersectionObserver(function (
        entries,
        observer,
      ) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            // remove the lazy class name
            // when image enters viewport
            entry.target.classList.remove('lazy-background');
            lazyBackgroundObserver.unobserve(entry.target);
          }
        });
      });

      lazyBackgrounds.forEach(function (lazyBackground) {
        lazyBackgroundObserver.observe(lazyBackground);
      });
    } else {
      lazyBackgrounds.forEach(function (entry) {
        entry.classList.remove('lazy-background');
      });
    }
  })();
});
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
