/*
 * Theme Black
 * Author: Paweł Nosko → https://pawelnosko.com/
 * Company: Design Cart → https://www.designcart.pl/
 */

/* ==========================================================================
   OpenCart core helpers
   ========================================================================== */
function getURLVar(key) {
  var value = [];
  var query = String(document.location).split('?');

  if (query[1]) {
    var part = query[1].split('&');

    for (var i = 0; i < part.length; i++) {
      var pair = part[i].split('=');

      if (pair[0] && pair[1]) {
        value[pair[0]] = pair[1];
      }
    }

    if (value[key]) {
      return value[key];
    }
  }

  return '';
}

(function ($) {
  $.fn.observe = function (callback) {
    if (!$(this)[0]) {
      return;
    }

    var observer = new MutationObserver(callback);
    observer.observe($(this)[0], { characterData: false, childList: true, attributes: false });
  };
})(jQuery);

$(document).ready(function () {
  $(document).on('mouseenter', "[data-bs-toggle='tooltip']", function () {
    var tooltip = bootstrap.Tooltip.getInstance(this);

    if (!tooltip) {
      tooltip = bootstrap.Tooltip.getOrCreateInstance(this);
      tooltip.show();
    }
  });

  $(document).on('click', 'button', function () {
    $('.tooltip').remove();
  });

  $('#alert').observe(function () {
    window.setTimeout(function () {
      $('#alert .alert-dismissible').fadeTo(3000, 0, function () {
        $(this).remove();
      });
    }, 3000);
  });
});

$(document).ready(function () {
  (function ($) {
    $.fn.button = function (state) {
      return this.each(function () {
        var element = this;

        if (state == 'loading') {
          this.html = $(element).html();
          this.state = $(element).prop('disabled');
          $(element).prop('disabled', true).width($(element).width()).html('<i class="fa-solid fa-circle-notch fa-spin"></i>');
        }

        if (state == 'reset') {
          $(element).prop('disabled', this.state).width('').html(this.html);
        }
      });
    };
  })(jQuery);
});

/* ==========================================================================
   Generic AJAX forms: <form data-oc-toggle="ajax"> / <button data-oc-toggle="ajax">
   ========================================================================== */
$(document).on('submit', 'form', function (event) {
  var form = this;
  var button = typeof event.originalEvent !== 'undefined' && typeof event.originalEvent.submitter !== 'undefined'
    ? event.originalEvent.submitter
    : '';

  if ($(form).attr('data-oc-toggle') == 'ajax' || $(button).attr('data-oc-toggle') == 'ajax') {
    event.preventDefault();

    var target = event.target;
    var action = $(button).attr('formaction') || $(target).attr('action');
    var method = $(button).attr('formmethod') || $(target).attr('method') || 'post';
    var enctype = $(button).attr('formenctype') || $(target).attr('enctype') || 'application/x-www-form-urlencoded';

    if (!action) {
      return;
    }

    if (typeof CKEDITOR !== 'undefined') {
      for (var instance in CKEDITOR.instances) {
        CKEDITOR.instances[instance].updateElement();
      }
    }

    $.ajax({
      url: String(action).replaceAll('&amp;', '&'),
      type: method,
      data: $(target).serialize(),
      dataType: 'json',
      contentType: enctype,
      beforeSend: function () {
        $(button).button('loading');
      },
      complete: function () {
        $(button).button('reset');
      },
      success: function (json) {
        $('.alert-dismissible').remove();
        $(form).find('.is-invalid').removeClass('is-invalid');
        $(form).find('.invalid-feedback').removeClass('d-block');

        var isCartAdd = action && (action.indexOf('cart.add') !== -1 || action.indexOf('checkout/cart.add') !== -1);
        var isWishlist = action && action.indexOf('wishlist') !== -1;

        // Listing "Add to bag" on products with required options:
        // OC returns errors + redirect to the product page — follow it.
        if (isCartAdd && json.redirect && json.error) {
          location = json.redirect;
          return;
        }

        // Other ajax forms: redirect only when there is no error
        if (json.redirect && !json.error && !isCartAdd) {
          location = json.redirect;
          return;
        }

        if (typeof json.error === 'string') {
          showModalMessage('danger', json.error);
        }

        if (typeof json.error === 'object') {
          if (json.error.warning) {
            $('#alert').prepend('<div class="alert alert-danger alert-dismissible"><i class="fa-solid fa-circle-exclamation"></i> ' + json.error.warning + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
          }

          for (var key in json.error) {
            if (key === 'warning') {
              continue;
            }

            $('#input-' + key.replaceAll('_', '-')).addClass('is-invalid').find('.form-control, .form-select, .form-check-input, .form-check-label').addClass('is-invalid');
            $('#error-' + key.replaceAll('_', '-')).html(json.error[key]).addClass('d-block');
          }
        }

        if (json.success) {
          if (isWishlist) {
            $(form).find('.product-card-wish').addClass('is-active');
            $(button).filter('.product-card-wish').addClass('is-active');
          }

          var loadUrl = $(target).attr('data-oc-load');
          var loadTarget = $(target).attr('data-oc-target');

          var afterCartRefresh = function () {
            if (isCartAdd) {
              setTimeout(openCart, 300);
              setTimeout(updateCartCount, 300);
            }
          };

          // Adding to bag opens the drawer — no modal needed. Everything else gets feedback.
          if (!isCartAdd) {
            showModalMessage('success', json.success);
          }

          if (typeof loadUrl !== 'undefined' && typeof loadTarget !== 'undefined') {
            // Replace the drawer content (avoids nested #cart)
            if (loadTarget === '#cart' || loadTarget === '.cart-panel-content') {
              $.get(loadUrl.replaceAll('&amp;', '&'), function (html) {
                $('.cart-panel-content').html(html);
                afterCartRefresh();
              });
            } else {
              $(loadTarget).load(loadUrl.replaceAll('&amp;', '&'), afterCartRefresh);
            }
          } else if (isCartAdd) {
            refreshSlideCart(afterCartRefresh);
          }
        }

        for (var field in json) {
          $(form).find("[name='" + field + "']").val(json[field]);
        }
      },
      error: function (xhr, status, error) {
        console.log(error + '\r\n' + xhr.statusText + '\r\n' + xhr.responseText);
      }
    });
  }
});

$(document).on('click', "button[data-oc-toggle='upload']", function () {
  var element = this;

  if (!$(element).prop('disabled')) {
    $('#form-upload').remove();
    $('body').prepend('<form enctype="multipart/form-data" id="form-upload" style="display: none;"><input type="file" name="file" value=""/></form>');
    $('#form-upload input[name=\'file\']').trigger('click');

    $('#form-upload input[name=\'file\']').on('change', function () {
      if (this.files[0].size / 1024 > $(element).attr('data-oc-size-max')) {
        alert($(element).attr('data-oc-size-error'));
        $(this).val('');
      }
    });

    if (typeof uploadInterval !== 'undefined') {
      clearInterval(uploadInterval);
    }

    var uploadInterval = setInterval(function () {
      if ($('#form-upload input[name=\'file\']').val() != '') {
        clearInterval(uploadInterval);

        $.ajax({
          url: $(element).attr('data-oc-url'),
          type: 'post',
          data: new FormData($('#form-upload')[0]),
          dataType: 'json',
          cache: false,
          contentType: false,
          processData: false,
          beforeSend: function () {
            $(element).button('loading');
          },
          complete: function () {
            $(element).button('reset');
          },
          success: function (json) {
            if (json.error) {
              alert(json.error);
            }

            if (json.success) {
              alert(json.success);
            }

            if (json.code) {
              $($(element).attr('data-oc-target')).attr('value', json.code);
            }
          },
          error: function (xhr, status, error) {
            console.log(error + '\r\n' + xhr.statusText + '\r\n' + xhr.responseText);
          }
        });
      }
    }, 500);
  }
});

function Chain() {
  this.start = false;
  this.data = [];
}

Chain.prototype.attach = function (callable) {
  this.data.push(callable);

  if (!this.start) {
    this.execute();
  }
};

Chain.prototype.execute = function () {
  var chain = this;

  if (this.data.length) {
    this.start = true;

    this.data.shift()().done(function () {
      chain.execute();
    });
  } else {
    this.start = false;
  }
};

var chain = new Chain;

(function ($) {
  $.fn.autocomplete = function (options) {
    return this.each(function () {
      var element = this;
      var target = $('#' + $(element).attr('data-oc-target'));

      this.timer = null;
      this.items = [];

      $.extend(this, options);

      $(element).on('focusin', function () {
        element.request();
      });

      $(element).on('focusout', function (event) {
        if (!event.relatedTarget || !$(event.relatedTarget).hasClass('dropdown-item')) {
          target.removeClass('show');
        }
      });

      $(element).on('input', function () {
        element.request();
      });

      target.on('click', 'a', function (event) {
        event.preventDefault();

        var value = $(this).attr('href');

        if (typeof element.items[value] !== 'undefined') {
          element.select(element.items[value]);
          target.removeClass('show');
        }
      });

      this.request = function () {
        clearTimeout(this.timer);
        $('#autocomplete-loading').remove();
        target.prepend('<li id="autocomplete-loading"><span class="dropdown-item text-center disabled"><i class="fa-solid fa-circle-notch fa-spin"></i></span></li>');
        target.addClass('show');

        this.timer = setTimeout(function (item) {
          item.source($(item).val(), $.proxy(item.response, item));
        }, 150, this);
      };

      this.response = function (json) {
        var html = '';
        var category = {};
        var categoryName;
        var i;
        var j;

        if (json.length) {
          for (i = 0; i < json.length; i++) {
            this.items[json[i].value] = json[i];

            if (json[i].category) {
              categoryName = json[i].category;

              if (!category[categoryName]) {
                category[categoryName] = [];
              }

              category[categoryName].push(json[i]);
            } else {
              html += '<li><a href="' + json[i].value + '" class="dropdown-item">' + json[i].label + '</a></li>';
            }
          }

          for (categoryName in category) {
            html += '<li><h6 class="dropdown-header">' + categoryName + '</h6></li>';

            for (j = 0; j < category[categoryName].length; j++) {
              html += '<li><a href="' + category[categoryName][j].value + '" class="dropdown-item">' + category[categoryName][j].label + '</a></li>';
            }
          }
        }

        target.html(html);
      };
    });
  };
})(jQuery);

$(document).ready(function () {
  $('#form-currency .dropdown-item').on('click', function (event) {
    event.preventDefault();
    $('#form-currency input[name=\'code\']').val($(this).attr('href'));
    $('#form-currency').submit();
  });

  $('#form-language .dropdown-item').on('click', function (event) {
    event.preventDefault();
    $('#form-language input[name=\'code\']').val($(this).attr('href'));
    $('#form-language').submit();
  });

  $('body').on('click', '.modal-link', function (event) {
    event.preventDefault();

    $('#modal-information').remove();

    $.ajax({
      url: $(this).attr('href'),
      dataType: 'html',
      success: function (html) {
        $('body').append(html);
        $('#modal-information').modal('show');
      }
    });
  });

  $('#cookie button').on('click', function () {
    var element = this;

    $.ajax({
      url: $(this).val(),
      type: 'get',
      dataType: 'json',
      beforeSend: function () {
        $(element).button('loading');
      },
      complete: function () {
        $(element).button('reset');
      },
      success: function (json) {
        if (json.success) {
          $('#cookie').fadeOut(400, function () {
            $('#cookie').remove();
          });
        }
      },
      error: function (xhr, status, error) {
        console.log(error + '\r\n' + xhr.statusText + '\r\n' + xhr.responseText);
      }
    });
  });
});

/* ==========================================================================
   Cart drawer (#cart-panel)
   ========================================================================== */
function openCart() {
  closeMobileMenu();
  closeSearch();
  $('#cart-panel').addClass('open').attr('aria-hidden', 'false');
  $('.cart-overlay').addClass('open');
  $('body').addClass('cart-open');
}

function closeCart() {
  $('#cart-panel').removeClass('open').attr('aria-hidden', 'true');
  $('.cart-overlay').removeClass('open');
  $('body').removeClass('cart-open');
}

function refreshSlideCart(callback) {
  var language = getURLVar('language');
  var url = 'index.php?route=common/cart.info';

  if (language) {
    url += '&language=' + encodeURIComponent(language);
  }

  $.get(url, function (html) {
    $('.cart-panel-content').html(html);
    updateCartCount();

    if (typeof callback === 'function') {
      callback();
    }
  });
}

function updateCartCount() {
  var total = 0;

  $('.cart-panel-content .qty-input').each(function () {
    var qty = parseInt($(this).val(), 10);

    if (!isNaN(qty)) {
      total += qty;
    }
  });

  var badge = $('.cart-count-badge');

  badge.text(total);
  $('.cart-count-number').text(total);

  if (total === 0) {
    badge.hide();
  } else {
    badge.css('display', 'flex');
  }
}

function updateQuantity(cartId, quantity) {
  $('.cart-loader').fadeIn();

  $.ajax({
    url: 'index.php?route=extension/pn_black/checkout/dc_cart.edit',
    type: 'post',
    data: {
      quantity: (function () {
        var payload = {};
        payload[cartId] = quantity;
        return payload;
      })()
    },
    dataType: 'json',
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    },
    success: function () {
      refreshSlideCart(function () {
        $('.cart-loader').fadeOut();
      });
    },
    error: function (xhr, status, error) {
      console.error('AJAX error:', error, xhr.responseText);
      $('.cart-loader').fadeOut();
    }
  });
}

function removeProduct(cartId) {
  $('.cart-loader').fadeIn();

  $.ajax({
    url: 'index.php?route=extension/pn_black/checkout/dc_cart.remove',
    type: 'post',
    data: {
      cart_id: cartId
    },
    dataType: 'json',
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    },
    success: function () {
      refreshSlideCart(function () {
        $('.cart-loader').fadeOut();
      });
    },
    error: function (xhr, status, error) {
      console.error('AJAX error:', error, xhr.responseText);
      $('.cart-loader').fadeOut();
    }
  });
}

function showModalMessage(type, message) {
  var modal = $('#message-modal');

  if (!modal.length) {
    return;
  }

  var header = $('#message-modal-header');
  var icon = $('#message-modal-icon');
  var body = $('#message-modal-body');

  header.removeClass('bg-success bg-danger bg-info bg-warning text-white');
  icon.html('');
  body.html('');

  if (type === 'success') {
    icon.html('<i class="fa-solid fa-circle-check" style="font-size: 2.5rem;"></i>');
  } else if (type === 'danger') {
    icon.html('<i class="fa-solid fa-circle-xmark" style="font-size: 2.5rem;"></i>');
  } else {
    icon.html('<i class="fa-solid fa-circle-info" style="font-size: 2.5rem;"></i>');
  }

  body.html(message);
  modal.modal('show');

  setTimeout(function () {
    modal.modal('hide');
  }, 3000);
}

/* ==========================================================================
   Mobile menu drawer
   ========================================================================== */
function openMobileMenu() {
  closeCart();
  closeSearch();
  $('#pn-mobile').addClass('is-open').attr('aria-hidden', 'false');
  $('[data-pn-menu-open]').attr('aria-expanded', 'true');
  $('body').addClass('pn-lock');
}

function closeMobileMenu() {
  $('#pn-mobile').removeClass('is-open').attr('aria-hidden', 'true');
  $('[data-pn-menu-open]').attr('aria-expanded', 'false');

  if (!$('body').hasClass('cart-open')) {
    $('body').removeClass('pn-lock');
  }
}

function initMobileMenu() {
  $(document).on('click', '[data-pn-menu-open]', function (event) {
    event.preventDefault();
    openMobileMenu();
  });

  $(document).on('click', '[data-pn-menu-close]', function () {
    closeMobileMenu();
  });

  // click on the dimmed backdrop (outside of the panel)
  $(document).on('click', '#pn-mobile', function (event) {
    if (event.target === this) {
      closeMobileMenu();
    }
  });

  $(document).on('click', '#pn-mobile a', function () {
    closeMobileMenu();
  });

  $(document).on('click', '#pn-mobile .pn-menu__toggle', function (event) {
    event.preventDefault();
    event.stopPropagation();

    var $btn = $(this);
    var $item = $btn.closest('.pn-menu__item');
    var open = !$item.hasClass('is-open');

    $item.toggleClass('is-open', open);
    $btn.attr('aria-expanded', open ? 'true' : 'false');
  });

  // desktop breakpoint reached — drop drawer state
  if (window.matchMedia) {
    var mq = window.matchMedia('(min-width: 992px)');
    var onChange = function (e) {
      if (e.matches) {
        closeMobileMenu();
      }
    };

    if (mq.addEventListener) {
      mq.addEventListener('change', onChange);
    } else if (mq.addListener) {
      mq.addListener(onChange);
    }
  }
}

/* ==========================================================================
   Header search panel
   ========================================================================== */
function openSearch() {
  var $header = $('.pn-header');

  $header.addClass('is-search-open');
  $('[data-pn-search-toggle]').attr('aria-expanded', 'true');

  setTimeout(function () {
    $header.find('.pn-search-panel input[name="search"]').trigger('focus');
  }, 200);
}

function closeSearch() {
  $('.pn-header').removeClass('is-search-open');
  $('[data-pn-search-toggle]').attr('aria-expanded', 'false');
}

function initSearch() {
  $(document).on('click', '[data-pn-search-toggle]', function (event) {
    event.preventDefault();

    if ($('.pn-header').hasClass('is-search-open')) {
      closeSearch();
    } else {
      openSearch();
    }
  });

  $(document).on('click', function (event) {
    if (!$(event.target).closest('.pn-header').length) {
      closeSearch();
    }
  });
}

/* ==========================================================================
   Reveal on scroll
   ========================================================================== */
var pnRevealObserver = null;

function pnReveal(root) {
  var elements = (root ? $(root) : $(document)).find('.reveal:not(.is-visible)').get();

  if (!elements.length) {
    return;
  }

  if (!('IntersectionObserver' in window)) {
    elements.forEach(function (element) {
      element.classList.add('is-visible');
    });

    return;
  }

  if (!pnRevealObserver) {
    pnRevealObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          pnRevealObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.08, rootMargin: '0px 0px 15% 0px' });
  }

  elements.forEach(function (element) {
    pnRevealObserver.observe(element);
  });
}

/* ==========================================================================
   Collection filter (All / groups)
   ========================================================================== */
function initCollectionFilter() {
  $(document).on('click', '[data-pn-filter]', function () {
    var filter = $(this).attr('data-pn-filter');
    var $scope = $(this).closest('.pn-collection');

    $scope.find('[data-pn-filter]').removeClass('is-active').attr('aria-pressed', 'false');
    $(this).addClass('is-active').attr('aria-pressed', 'true');

    $scope.find('[data-pn-group]').each(function () {
      var show = filter === 'all' || $(this).attr('data-pn-group') === filter;
      $(this).toggleClass('is-filtered-out', !show);
    });

    pnReveal($scope);
  });
}

/* ==========================================================================
   Newsletter (AJAX)
   ========================================================================== */
function initNewsletter() {
  $(document).on('submit', '#form-newsletter', function (event) {
    event.preventDefault();

    var $form = $(this);
    var $button = $form.find('[type="submit"]');
    var $error = $form.find('.pn-newsletter-error');

    $.ajax({
      url: 'index.php?route=extension/pn_black/module/pn_black.newsletter',
      type: 'post',
      data: $form.serialize(),
      dataType: 'json',
      beforeSend: function () {
        $button.prop('disabled', true);
        $error.attr('hidden', true).text('');
      },
      complete: function () {
        $button.prop('disabled', false);
      },
      success: function (json) {
        if (json.error) {
          var message = typeof json.error === 'string' ? json.error : Object.keys(json.error).map(function (key) {
            return json.error[key];
          }).join(' ');

          $error.text(message).removeAttr('hidden');
          return;
        }

        if (json.success) {
          var $wrap = $form.closest('.pn-newsletter-inner');

          $form.attr('hidden', true);
          $wrap.find('.pn-newsletter-success').removeAttr('hidden');
          $form[0].reset();
        }
      },
      error: function (xhr, status, error) {
        console.log(error + '\r\n' + xhr.statusText + '\r\n' + xhr.responseText);
        $error.text('Something went wrong. Please try again.').removeAttr('hidden');
      }
    });
  });
}

/* ==========================================================================
   Boot
   ========================================================================== */
$(document).ready(function () {
  setTimeout(function () {
    updateCartCount();
  }, 10);

  initMobileMenu();
  initSearch();
  initCollectionFilter();
  initNewsletter();
  pnReveal();

  // sticky header shadow
  var $header = $('.pn-header');
  var onScroll = function () {
    $header.toggleClass('is-scrolled', window.pageYOffset > 8);
  };

  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  // cart open / close
  $(document).on('click', '[data-open-cart]', function (event) {
    event.preventDefault();
    openCart();
  });

  $(document).on('click', '[data-pn-cart-close], .cart-overlay', function () {
    closeCart();
  });

  // cart drawer quantity / remove
  $(document).on('click', '.btn-qty', function () {
    var $group = $(this).closest('.quantity-group');
    var $input = $group.find('.qty-input');
    var cartId = $group.data('cart-id');
    var qty = parseInt($input.val(), 10) || 1;
    var type = $(this).data('type');

    if (type === 'plus') {
      qty++;
    }

    if (type === 'minus' && qty > 1) {
      qty--;
    }

    $input.val(qty);
    updateQuantity(cartId, qty);
  });

  $(document).on('change', '.qty-input', function () {
    var $group = $(this).closest('.quantity-group');
    var cartId = $group.data('cart-id');
    var qty = parseInt($(this).val(), 10);

    if (isNaN(qty) || qty < 1) {
      qty = 1;
    }

    $(this).val(qty);
    updateQuantity(cartId, qty);
  });

  $(document).on('click', '.btn-remove', function () {
    removeProduct($(this).data('cart-id'));
  });

  // Escape closes every overlay
  $(document).on('keydown', function (event) {
    if (event.key !== 'Escape') {
      return;
    }

    closeCart();
    closeMobileMenu();
    closeSearch();
  });
});
