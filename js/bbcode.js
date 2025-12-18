// BBCode control (modernized, TBDev compatible)

(function () {
  "use strict";

  function addEvent(el, type, fn) {
    if (!el) return;
    if (el.addEventListener) el.addEventListener(type, fn, false);
    else if (el.attachEvent) el.attachEvent("on" + type, fn);
    else el["on" + type] = fn;
  }

  function getTextareaSelection(t) {
    if (!t) return ["", null];

    // IE <= 8
    if (document.selection && t.createTextRange) {
      t.focus();
      var r = document.selection.createRange();
      if (!r) return ["", null];
      return [r.text || "", r];
    }

    // Modern
    if (typeof t.selectionStart === "number" && typeof t.selectionEnd === "number") {
      var start = t.selectionStart, end = t.selectionEnd;
      return [t.value.substring(start, end), { start: start, end: end }];
    }

    return ["", null];
  }

  // BBCode control. (based on bbcode.js from http://forum.dklab.ru)
  function BBCode(textarea) { this.construct(textarea); }

  BBCode.prototype = {
    VK_TAB: 9,
    VK_ENTER: 13,
    VK_PAGE_UP: 33,

    BRK_OP: "[",
    BRK_CL: "]",

    textarea: null,
    stext: "",
    quoter: null,
    quoterText: "",
    collapseAfterInsert: false,
    replaceOnInsert: false,
    tags: null,

    construct: function (textarea) {
      this.textarea = textarea;
      this.tags = {};

      var th = this;

      // Quoter tag: quote="name" + selected text
      this.addTag(
        "_quoter",
        function () { return 'quote="' + (th.quoter || "") + '"'; },
        "/quote",
        null,
        null,
        function () {
          th.collapseAfterInsert = true;
          return th._prepareMultiline(th.quoterText || "");
        }
      );

      addEvent(textarea, "keydown", function (e) { return th.onKeyPress(e || window.event, "down"); });
      addEvent(textarea, "keypress", function (e) { return th.onKeyPress(e || window.event, "press"); });
    },

    onclickPoster: function (name) {
      var sel = this.getSelection()[0];
      if (sel) {
        this.quoter = name;
        this.quoterText = sel;
        this.insertTag("_quoter");
      } else {
        this.insertAtCursor("[b]" + name + "[/b]\n");
      }
      return false;
    },

    onclickQuoteSel: function () {
      var sel = this.getSelection()[0];
      if (sel) this.insertAtCursor("[quote]" + sel + "[/quote]\n");
      else alert("Пожалуйста выделите текст для цитирования");
      return false;
    },

    emoticon: function (em) {
      if (!em) return false;
      this.insertAtCursor(" " + em + " ");
      return false;
    },

    refreshSelection: function (get) {
      this.stext = get ? this.getSelection()[0] : "";
    },

    // IMPORTANT: selection from textarea, not window selection
    getSelection: function () {
      var rt = getTextareaSelection(this.textarea);
      var text = (rt[0] || "");
      if (!text) text = this.stext || "";
      text = String(text).replace(/^\s+|\s+$/g, "");
      return [text, rt[1]];
    },

    insertAtCursor: function (text) {
      var t = this.textarea;
      if (!t) return;
      t.focus();

      // IE <= 8
      if (document.selection && document.selection.createRange) {
        var r = document.selection.createRange();
        if (!this.replaceOnInsert) r.collapse(false);
        r.text = text;
        r.collapse(false);
        r.select();
        return;
      }

      // Modern
      if (typeof t.selectionStart === "number" && typeof t.selectionEnd === "number") {
        var start = this.replaceOnInsert ? t.selectionStart : t.selectionEnd;
        var end = t.selectionEnd;
        t.value = t.value.substring(0, start) + text + t.value.substring(end);
        t.selectionStart = t.selectionEnd = start + text.length;
        return;
      }

      t.value += text;
    },

    surround: function (open, close, fTrans) {
      var t = this.textarea;
      if (!t) return false;
      t.focus();

      if (!fTrans) fTrans = function (x) { return x; };

      var rt = this.getSelection();
      var text = rt[0];
      var range = rt[1];

      // IE <= 8 real range object
      if (range && range.text !== undefined) {
        var newText = open + fTrans(text) + (close ? close : "");
        range.text = newText;

        // place caret inside if empty selection
        if (!text) {
          range.moveStart("character", -(close ? close.length : 0));
          range.moveEnd("character", 0);
        }
        if (!this.collapseAfterInsert) range.select();
        this.collapseAfterInsert = false;
        return !!text;
      }

      // Modern selection by indexes
      if (range && typeof range.start === "number") {
        var start = range.start, end = range.end;
        var top = t.scrollTop;

        var sel = fTrans(t.value.substring(start, end));
        var inner = open + sel + (close || "");

        t.value = t.value.substring(0, start) + inner + t.value.substring(end);

        if (sel) {
          t.selectionStart = start;
          t.selectionEnd = start + inner.length;
        } else {
          t.selectionStart = t.selectionEnd = start + open.length;
        }

        if (this.collapseAfterInsert) {
          t.selectionStart = t.selectionEnd = start + inner.length;
        }

        t.scrollTop = top;
        this.collapseAfterInsert = false;
        return !!sel;
      }

      // Fallback
      t.value += open + (text || "") + (close || "");
      this.collapseAfterInsert = false;
      return !!text;
    },

    _cancelEvent: function (e) {
      if (!e) return false;
      if (e.preventDefault) e.preventDefault();
      if (e.stopPropagation) e.stopPropagation();
      e.returnValue = false;
      return false;
    },

    onKeyPress: function (e, type) {
      if (!e) return true;

      var keyCode = e.keyCode || e.which || 0;
      var key = "";
      if (keyCode && keyCode >= 32) key = String.fromCharCode(keyCode);

      // Hotkeys for tags
      for (var id in this.tags) {
        if (!Object.prototype.hasOwnProperty.call(this.tags, id)) continue;
        var tag = this.tags[id];

        if (tag.ctrlKey && !e[tag.ctrlKey + "Key"]) continue;
        if (!tag.key) continue;
        if (!key || key.toUpperCase() !== String(tag.key).toUpperCase()) continue;

        // insert on keydown to avoid double insert
        if (e.type === "keydown") this.insertTag(id);
        return this._cancelEvent(e);
      }

      // Tab inserts [tab]
      if (type === "press" && keyCode === this.VK_TAB && !e.shiftKey && !e.ctrlKey && !e.altKey) {
        this.insertAtCursor("[tab]");
        return this._cancelEvent(e);
      }

      // Ctrl+Tab: go next field if exists
      if (keyCode === this.VK_TAB && e.ctrlKey && !e.shiftKey && !e.altKey) {
        var f = this.textarea && this.textarea.form;
        if (f && f.post && f.post.focus) f.post.focus();
        return this._cancelEvent(e);
      }

      // ALT+ENTER preview, CTRL+ENTER post (if exist)
      var form = this.textarea && this.textarea.form;
      if (form && keyCode === this.VK_ENTER && !e.shiftKey) {
        if (e.altKey && form.preview && form.preview.click) {
          form.preview.click();
          return this._cancelEvent(e);
        }
        if (e.ctrlKey && form.post && form.post.click) {
          form.post.click();
          return this._cancelEvent(e);
        }
      }

      // SHIFT+ALT+PAGEUP add attachment (if exist)
      if (form && keyCode === this.VK_PAGE_UP && e.shiftKey && e.altKey && !e.ctrlKey) {
        if (form.add_attachment_box && form.add_attachment_box.click) {
          form.add_attachment_box.click();
          return this._cancelEvent(e);
        }
      }

      return true;
    },

    addTag: function (id, open, close, key, ctrlKey, multiline) {
      if (!ctrlKey) ctrlKey = "ctrl";

      var tag = {
        id: id,
        open: open,
        close: close,
        key: key,
        ctrlKey: ctrlKey,
        multiline: multiline,
        elt: (this.textarea && this.textarea.form) ? this.textarea.form[id] : null
      };

      this.tags[id] = tag;

      var elt = tag.elt;
      if (!elt) return;

      var th = this;
      var tagName = (elt.tagName || "").toUpperCase();
      var type = (elt.type || "").toLowerCase();

      // ✅ SELECT: только change (иначе выпадашка закрывается сразу)
      if (tagName === "SELECT") {
        addEvent(elt, "change", function () { th.insertTag(id); return false; });
        return;
      }

      // ✅ BUTTON / INPUT button: click
      if (tagName === "BUTTON" || type === "button" || type === "submit" || type === "image") {
        addEvent(elt, "click", function () { th.insertTag(id); return false; });
      }
    },

    insertTag: function (id) {
      var tag = this.tags[id];
      if (!tag) return false;

      var op = (typeof tag.open === "function") ? tag.open(tag.elt) : tag.open;

      // ✅ если функция селекта вернула пустоту — ничего не вставляем
      if (!op) return false;

      var cl = (tag.close != null) ? tag.close : ("/" + op);

      // Wrap by [] if needed
      if (op && op.charAt(0) !== this.BRK_OP) op = this.BRK_OP + op + this.BRK_CL;
      if (cl && cl.charAt(0) !== this.BRK_OP) cl = this.BRK_OP + cl + this.BRK_CL;

      // Quoter needs newline after closing quote
      if (id === "_quoter") cl += "\n";

      var ml = tag.multiline;
      var fTrans = (!ml) ? null : (ml === true ? this._prepareMultiline.bind(this) : ml);

      this.surround(op, cl, fTrans);
      return true;
    },

    _prepareMultiline: function (text) {
      text = String(text || "").replace(/\s+$/, "").replace(/^([ \t]*\r?\n)+/, "");
      if (text.indexOf("\n") >= 0) text = "\n" + text + "\n";
      return text;
    }
  };

  // keep globals for old code
  window.BBCode = BBCode;

  // old helper (used by your textarea handlers)
  window.storeCaret = function (textEl) {
    if (textEl && textEl.createTextRange && document.selection) {
      textEl.caretPos = document.selection.createRange().duplicate();
    }
  };

})();
