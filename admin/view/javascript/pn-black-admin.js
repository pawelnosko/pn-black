/**
 * Theme Black
 * Author: Paweł Nosko → https://pawelnosko.com/
 * Company: Design Cart → https://www.designcart.pl/
 */
(function (global) {
  'use strict';

  var emojiList = [];
  var emojiPeople = [];
  var emojiTargetInput = null;
  var emojiTargetPreview = null;

  function qs(sel, root) {
    return (root || document).querySelector(sel);
  }

  function qsAll(sel, root) {
    return [...(root || document).querySelectorAll(sel)];
  }

  function loadEmojiData() {
    var listEl = document.getElementById('dc-emoji-list');
    var peopleEl = document.getElementById('dc-emoji-people');

    if (listEl) {
      try { emojiList = JSON.parse(listEl.textContent) || []; } catch (e) { emojiList = []; }
    }

    if (peopleEl) {
      try { emojiPeople = JSON.parse(peopleEl.textContent) || []; } catch (e) { emojiPeople = []; }
    }
  }

  function escapeRegExp(str) {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  function reindexContainer(container) {
    if (!container) return;

    var base = container.getAttribute('data-dc-reindex');
    if (!base) return;

    var esc = escapeRegExp(base);
    var items = [...container.children].filter(function (el) {
      return el.matches('[data-dc-accordion-item], .dc-accordion__item, .dc-dynamic-item');
    });

    items.forEach(function (item, index) {
      item.setAttribute('data-dc-index', String(index));

      qsAll('[name]', item).forEach(function (input) {
        input.name = input.name.replace(new RegExp('^' + esc + '\\[\\d+\\]'), base + '[' + index + ']');
      });

      qsAll('[id]', item).forEach(function (el) {
        el.id = el.id
          .replace(/slide-\d+-/g, 'slide-' + index + '-')
          .replace(/review-\d+-/g, 'review-' + index + '-')
          .replace(/social-\d+-/g, 'social-' + index + '-')
          .replace(/tag-\d+-/g, 'tag-' + index + '-')
          .replace(/contact-\d+-/g, 'contact-' + index + '-')
          .replace(/lang-slide-\d+-/g, 'lang-slide-' + index + '-')
          .replace(/lang-review-\d+-/g, 'lang-review-' + index + '-')
          .replace(/lang-tag-\d+-/g, 'lang-tag-' + index + '-')
          .replace(/lang-contact-\d+-/g, 'lang-contact-' + index + '-');
      });

      // Keep OC4 image manager hooks in sync with reindexed ids
      ['data-oc-target', 'data-oc-thumb'].forEach(function (attr) {
        qsAll('[' + attr + ']', item).forEach(function (el) {
          var val = el.getAttribute(attr);
          if (!val) return;
          el.setAttribute(attr, val
            .replace(/slide-\d+-/g, 'slide-' + index + '-')
            .replace(/review-\d+-/g, 'review-' + index + '-')
            .replace(/social-\d+-/g, 'social-' + index + '-')
            .replace(/tag-\d+-/g, 'tag-' + index + '-')
            .replace(/contact-\d+-/g, 'contact-' + index + '-'));
        });
      });

      qsAll('[data-dc-lang]', item).forEach(function (btn) {
        var val = btn.getAttribute('data-dc-lang');
        if (val) {
          btn.setAttribute('data-dc-lang', val
            .replace(/lang-slide-\d+-/, 'lang-slide-' + index + '-')
            .replace(/lang-review-\d+-/, 'lang-review-' + index + '-')
            .replace(/lang-tag-\d+-/, 'lang-tag-' + index + '-')
            .replace(/lang-contact-\d+-/, 'lang-contact-' + index + '-'));
        }
      });

      qsAll('.dc-lang-panel[id]', item).forEach(function (panel) {
        panel.id = panel.id
          .replace(/lang-slide-\d+-/, 'lang-slide-' + index + '-')
          .replace(/lang-review-\d+-/, 'lang-review-' + index + '-')
          .replace(/lang-tag-\d+-/, 'lang-tag-' + index + '-')
          .replace(/lang-contact-\d+-/, 'lang-contact-' + index + '-');
      });

      qsAll('[data-star-input]', item).forEach(function (el) {
        var sel = el.getAttribute('data-star-input');
        if (sel) {
          el.setAttribute('data-star-input', sel.replace(/-\d+$/, '-' + index));
        }
      });
    });
  }

  function refreshInterface(root) {
    if (global.DCInterface) {
      global.DCInterface.initAccordion(root);
      if (global.DCInterface.initLangTabs) {
        global.DCInterface.initLangTabs(root);
      } else if (typeof global.DCInterface.init === 'function') {
        global.DCInterface.init(root);
      }
    }
  }

  function initAddRemove(root) {
    qsAll('[data-dc-add-item]', root || document).forEach(function (btn) {
      if (btn._dcAddBound) return;
      btn._dcAddBound = true;

      btn.addEventListener('click', function () {
        var listName = this.getAttribute('data-dc-add-item');
        var templateId = this.getAttribute('data-dc-template');
        var template = document.getElementById(templateId);
        var container = qs('[data-dc-accordion-list="' + listName + '"]');

        if (!template || !container) return;

        var index = container.querySelectorAll('[data-dc-accordion-item], .dc-accordion__item, .dc-dynamic-item').length;
        var html = template.innerHTML.replace(/__INDEX__/g, String(index));
        var wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        var item = wrap.firstElementChild;

        container.appendChild(item);
        reindexContainer(container);
        refreshInterface(container);
        initStarRatings(container);
        initEmojiTriggers(container);
        initSortable(container);
        initSlideTitleSync(container);
        initReviewAuthorSync(container);
      });
    });

    if (document._dcRemoveBound) return;
    document._dcRemoveBound = true;

    document.addEventListener('click', function (e) {
      var removeBtn = e.target.closest('[data-dc-remove-item]');
      if (!removeBtn) return;

      var item = removeBtn.closest('[data-dc-accordion-item], .dc-accordion__item, .dc-dynamic-item');
      var container = removeBtn.closest('[data-dc-accordion-list]');

      if (!item || !container) return;

      item.remove();
      reindexContainer(container);
    });
  }

  function ensureSortable(callback) {
    if (global.Sortable) {
      callback();
      return;
    }

    var existing = document.getElementById('dc-sortable-cdn');
    if (existing) {
      existing.addEventListener('load', callback);
      return;
    }

    var script = document.createElement('script');
    script.id = 'dc-sortable-cdn';
    script.src = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js';
    script.onload = callback;
    document.head.appendChild(script);
  }

  function initNativeSortable(container) {
    var items = qsAll(':scope > .dc-accordion__item, :scope > .dc-dynamic-item', container);
    items.forEach(function (item) {
      item.setAttribute('draggable', 'true');
      item.addEventListener('dragstart', function (e) {
        item.classList.add('dc-dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', item.dataset.dcIndex || '0');
      });
      item.addEventListener('dragend', function () {
        item.classList.remove('dc-dragging');
        reindexContainer(container);
      });
      item.addEventListener('dragover', function (e) {
        e.preventDefault();
      });
      item.addEventListener('drop', function (e) {
        e.preventDefault();
        var dragging = container.querySelector('.dc-dragging');
        if (dragging && dragging !== item) {
          container.insertBefore(dragging, item);
        }
      });
    });
  }

  function reindexTileSort(container) {
    qsAll('[data-dc-select-tile]', container).forEach(function (tile, index) {
      var input = tile.querySelector('[data-dc-sort-order]');
      if (input) {
        input.value = String(index);
      }
    });
  }

  function initSortable(root) {
    qsAll('[data-dc-accordion-list]', root || document).forEach(function (container) {
      if (container._dcSortableBound) return;
      container._dcSortableBound = true;

      ensureSortable(function () {
        if (global.Sortable) {
          global.Sortable.create(container, {
            handle: '.dc-sort-handle',
            animation: 150,
            draggable: '.dc-accordion__item, .dc-dynamic-item',
            onEnd: function () {
              reindexContainer(container);
            }
          });
        } else {
          initNativeSortable(container);
        }
      });
    });

    qsAll('[data-dc-sortable-tiles]', root || document).forEach(function (container) {
      if (container._dcTileSortableBound) return;
      container._dcTileSortableBound = true;

      ensureSortable(function () {
        if (!global.Sortable) return;

        global.Sortable.create(container, {
          handle: '.dc-sort-handle',
          animation: 150,
          draggable: '[data-dc-select-tile]',
          ghostClass: 'dc-select-tile--ghost',
          chosenClass: 'dc-select-tile--chosen',
          dragClass: 'dc-select-tile--drag',
          onEnd: function () {
            reindexTileSort(container);
          }
        });
      });
    });
  }

  function openEmojiModal(source) {
    var modal = document.getElementById('dcEmojiModal');
    var grid = document.getElementById('dcEmojiGrid');
    if (!modal || !grid) return;

    var list = source === 'people' && emojiPeople.length ? emojiPeople : emojiList;
    grid.innerHTML = '';

    list.forEach(function (emoji) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'dc-emoji-modal__item';
      btn.textContent = emoji;
      btn.addEventListener('click', function () {
        if (emojiTargetInput) emojiTargetInput.value = emoji;
        if (emojiTargetPreview) emojiTargetPreview.textContent = emoji;
        closeEmojiModal();
      });
      grid.appendChild(btn);
    });

    modal.hidden = false;
    document.body.classList.add('dc-emoji-open');
  }

  function closeEmojiModal() {
    var modal = document.getElementById('dcEmojiModal');
    if (modal) modal.hidden = true;
    document.body.classList.remove('dc-emoji-open');
    emojiTargetInput = null;
    emojiTargetPreview = null;
  }

  function initEmojiModal() {
    var modal = document.getElementById('dcEmojiModal');
    if (!modal || modal._dcEmojiBound) return;
    modal._dcEmojiBound = true;

    qsAll('[data-emoji-close]', modal).forEach(function (el) {
      el.addEventListener('click', closeEmojiModal);
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeEmojiModal();
    });
  }

  function initEmojiTriggers(root) {
    qsAll('[data-emoji-target], .dc-emoji-trigger, .dc-select-tile__emoji', root || document).forEach(function (btn) {
      if (btn._dcEmojiTriggerBound) return;
      btn._dcEmojiTriggerBound = true;

      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var targetSel = this.getAttribute('data-emoji-target');
        var previewSel = this.getAttribute('data-emoji-preview');
        var source = this.getAttribute('data-emoji-source') || 'list';

        emojiTargetInput = targetSel ? qs(targetSel) : null;
        emojiTargetPreview = previewSel ? qs(previewSel) : this.querySelector('.dc-emoji-preview, span');

        openEmojiModal(source);
      });
    });
  }

  function initStarRatings(root) {
    qsAll('[data-star-rating]', root || document).forEach(function (wrap) {
      if (wrap._dcStarsBound) return;
      wrap._dcStarsBound = true;

      var inputSel = wrap.getAttribute('data-star-input');
      var input = inputSel ? qs(inputSel) : wrap.parentElement.querySelector('input[type="hidden"]');

      qsAll('[data-star-value]', wrap).forEach(function (star) {
        star.addEventListener('click', function (e) {
          e.preventDefault();
          var value = parseInt(this.getAttribute('data-star-value'), 10) || 0;
          if (input) input.value = String(value);

          qsAll('[data-star-value]', wrap).forEach(function (s) {
            var v = parseInt(s.getAttribute('data-star-value'), 10) || 0;
            s.classList.toggle('dc-star-rating__star--active', v <= value);
          });
        });
      });
    });
  }

  function initSelectableTiles(root) {
    qsAll('[data-dc-selectable-tiles]', root || document).forEach(function (grid) {
      qsAll('[data-dc-tile-checkbox]', grid).forEach(function (checkbox) {
        if (checkbox._dcTileBound) return;
        checkbox._dcTileBound = true;

        function sync() {
          var tile = checkbox.closest('[data-dc-select-tile]');
          if (tile) tile.classList.toggle('dc-select-tile--active', checkbox.checked);
        }

        checkbox.addEventListener('change', sync);
        sync();
      });
    });
  }

  function initSlideTitleSync(root) {
    qsAll('[data-dc-slide-title]', root || document).forEach(function (input) {
      if (input._dcTitleBound) return;
      input._dcTitleBound = true;

      input.addEventListener('input', function () {
        var item = input.closest('.dc-accordion__item');
        if (!item) return;
        var titleEl = item.querySelector('.dc-accordion__title');
        if (titleEl && input.value.trim()) {
          var sub = titleEl.querySelector('.dc-accordion__sub');
          titleEl.childNodes[0].textContent = input.value.trim() + ' ';
        }
      });
    });
  }

  function initReviewAuthorSync(root) {
    qsAll('[data-dc-review-author]', root || document).forEach(function (input) {
      if (input._dcAuthorBound) return;
      input._dcAuthorBound = true;

      input.addEventListener('input', function () {
        var item = input.closest('.dc-accordion__item');
        if (!item) return;
        var titleEl = item.querySelector('.dc-accordion__title');
        if (titleEl && input.value.trim()) {
          titleEl.childNodes[0].textContent = input.value.trim() + ' ';
        }
      });
    });
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function productFieldName(picker, group) {
    var explicit = picker.getAttribute('data-field-name');
    if (explicit) return explicit;
    return 'module_pn_black[' + group + '][products][]';
  }

  function buildProductTile(product, group, selected, withName, picker) {
    var tile = document.createElement('div');
    tile.className = 'dc-select-tile dc-select-tile--product' + (selected ? ' dc-select-tile--active' : '');
    tile.setAttribute('data-dc-select-tile', '');
    tile.setAttribute('data-product-id', String(product.product_id));
    var nameAttr = withName
      ? ' name="' + escapeHtml(productFieldName(picker || document.createElement('div'), group)) + '"'
      : '';
    tile.innerHTML =
      '<label class="dc-check dc-select-tile__check">' +
        '<input class="dc-check__input" type="checkbox" value="' + escapeHtml(product.product_id) + '"' + nameAttr + (selected ? ' checked' : '') + ' data-dc-tile-checkbox/>' +
        '<span class="dc-check__box"></span>' +
      '</label>' +
      '<div class="dc-select-tile__image"><img src="' + escapeHtml(product.image) + '" alt="' + escapeHtml(product.name) + '" loading="lazy"/></div>' +
      '<div class="dc-select-tile__body"><strong class="dc-select-tile__name">' + escapeHtml(product.name) + '</strong></div>';
    return tile;
  }

  function syncProductPicker(picker) {
    var selected = qs('[data-dc-product-selected]', picker);
    var countEl = qs('[data-dc-product-count]', picker);
    var emptyEl = qs('[data-dc-product-empty]', picker);
    var count = selected ? qsAll('[data-dc-tile-checkbox]:checked', selected).length : 0;

    if (countEl) countEl.textContent = String(count);
    if (emptyEl) emptyEl.hidden = count > 0;
  }

  function bindProductTile(tile, picker) {
    var checkbox = qs('[data-dc-tile-checkbox]', tile);
    if (!checkbox || checkbox._dcProductBound) return;
    checkbox._dcProductBound = true;

    var group = picker.getAttribute('data-group') || '';
    var selectedBox = qs('[data-dc-product-selected]', picker);
    var resultsBox = qs('[data-dc-product-results]', picker);

    function apply() {
      var productId = tile.getAttribute('data-product-id');
      tile.classList.toggle('dc-select-tile--active', checkbox.checked);

      if (checkbox.checked) {
        if (selectedBox && tile.parentElement !== selectedBox) {
          var existing = selectedBox.querySelector('[data-product-id="' + productId + '"]');
          if (existing) {
            var existingCb = qs('[data-dc-tile-checkbox]', existing);
            if (existingCb) existingCb.checked = true;
            existing.classList.add('dc-select-tile--active');
          } else {
            var clone = buildProductTile({
              product_id: productId,
              name: (qs('.dc-select-tile__name', tile) || {}).textContent || '',
              image: (qs('img', tile) || {}).getAttribute('src') || ''
            }, group, true, true, picker);
            selectedBox.appendChild(clone);
            bindProductTile(clone, picker);
          }
          checkbox.checked = true;
          tile.classList.add('dc-select-tile--active');
        }
      } else {
        if (selectedBox && tile.parentElement === selectedBox) {
          tile.remove();
        } else if (selectedBox) {
          var selectedTile = selectedBox.querySelector('[data-product-id="' + productId + '"]');
          if (selectedTile) selectedTile.remove();
        }

        if (resultsBox) {
          qsAll('[data-product-id="' + productId + '"]', resultsBox).forEach(function (resultTile) {
            var cb = qs('[data-dc-tile-checkbox]', resultTile);
            if (cb) {
              cb.checked = false;
              resultTile.classList.remove('dc-select-tile--active');
            }
          });
        }
      }

      syncProductPicker(picker);
    }

    checkbox.addEventListener('change', apply);

    tile.addEventListener('click', function (e) {
      if (e.target.closest('input, label, a, button')) return;
      checkbox.checked = !checkbox.checked;
      checkbox.dispatchEvent(new Event('change', { bubbles: true }));
    });
  }

  function renderProductResults(picker, items) {
    var results = qs('[data-dc-product-results]', picker);
    var selected = qs('[data-dc-product-selected]', picker);
    var group = picker.getAttribute('data-group') || '';

    if (!results) return;

    results.innerHTML = '';
    results.hidden = false;

    if (!Array.isArray(items) || !items.length) {
      results.innerHTML = '<p class="dc-help mb-0">No products found.</p>';
      return;
    }

    var selectedIds = {};
    if (selected) {
      qsAll('[data-dc-tile-checkbox]:checked', selected).forEach(function (cb) {
        selectedIds[String(cb.value)] = true;
      });
    }

    var grid = document.createElement('div');
    grid.className = 'dc-tile-grid dc-tile-grid--products';
    grid.setAttribute('data-dc-selectable-tiles', '');

    items.forEach(function (product) {
      var isSelected = !!selectedIds[String(product.product_id)];
      var tile = buildProductTile(product, group, isSelected, false, picker);
      grid.appendChild(tile);
      bindProductTile(tile, picker);
    });

    results.appendChild(grid);
  }

  function productSearchUrl(picker, query) {
    var token = picker.getAttribute('data-user-token') || '';
    var route = picker.getAttribute('data-autocomplete-route') || 'extension/pn_black/module/pn_black.productAutocomplete';
    var params = 'route=' + encodeURIComponent(route) +
      '&user_token=' + encodeURIComponent(token) +
      '&filter_name=' + encodeURIComponent(query || '');

    return 'index.php?' + params;
  }

  function runProductSearch(picker, query) {
    var results = qs('[data-dc-product-results]', picker);
    if (!results) return;

    results.hidden = false;
    results.innerHTML = '<p class="dc-help mb-0">Searching…</p>';

    fetch(productSearchUrl(picker, query), {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    })
      .then(function (r) {
        return r.text().then(function (text) {
          var data;
          try {
            data = JSON.parse(text);
          } catch (e) {
            throw new Error('Invalid JSON');
          }
          if (!r.ok) throw new Error('HTTP ' + r.status);
          return data;
        });
      })
      .then(function (items) {
        renderProductResults(picker, items);
      })
      .catch(function () {
        results.hidden = false;
        results.innerHTML = '<p class="dc-help mb-0">Search failed. Try again.</p>';
      });
  }

  function initProductPickers(root) {
    qsAll('[data-dc-product-picker]', root || document).forEach(function (picker) {
      if (picker._dcProductPickerBound) return;
      picker._dcProductPickerBound = true;

      var input = qs('[data-dc-product-search]', picker);
      var results = qs('[data-dc-product-results]', picker);
      var selected = qs('[data-dc-product-selected]', picker);
      var timer = null;

      if (selected) {
        qsAll('[data-dc-select-tile]', selected).forEach(function (tile) {
          bindProductTile(tile, picker);
        });
      }

      if (results) {
        qsAll('[data-dc-select-tile]', results).forEach(function (tile) {
          bindProductTile(tile, picker);
        });
      }

      syncProductPicker(picker);

      if (!input || !results) return;

      // Prevent parent settings form from submitting on Enter
      input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          clearTimeout(timer);
          runProductSearch(picker, input.value.trim());
        }
      });

      input.addEventListener('input', function () {
        clearTimeout(timer);
        var query = input.value.trim();

        timer = setTimeout(function () {
          runProductSearch(picker, query);
        }, 280);
      });
    });
  }

  function initAll(root) {
    root = root || document;
    loadEmojiData();
    initEmojiModal();
    initEmojiTriggers(root);
    initStarRatings(root);
    initSelectableTiles(root);
    initProductPickers(root);
    initAddRemove(root);
    initSortable(root);
    initSlideTitleSync(root);
    initReviewAuthorSync(root);
  }

  global.PnBlackAdmin = {
    init: initAll,
    reindexContainer: reindexContainer
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { initAll(); });
  } else {
    initAll();
  }
})(typeof window !== 'undefined' ? window : globalThis);
