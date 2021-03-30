(function ($, $$) {
    console.time("INIT");
    console.time("READY");
    console.time("LOAD");

    var IMG_SRC = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgA" +
        "AABAAAAAQCAIAAACQkWg2AAAAJ0lEQVR42mOcOXMmAzZw9uxZrOKM" +
        "oxpooiEtLQ2rhLGx8agG+mkAACpiL/lWCxuBAAAAAElFTkSuQmCC";
    var hideTabs = function () {
        $$(".tab-content>div").forEach(function (v) {
            v.style.display = "none";
        });
        //Remove active header
        $$(".tab-header>.active").forEach(function (v) {
            v.classList.remove("active");
        });
    };
    var showTab = function (id) {
        var tab = $("#tab-content-" + id);
        var header = $("#tab-header-" + id);
        if (tab && header) {
            hideTabs();
            tab.style.display = "block";
            header.classList.add("active");
            return true;
        }
        return false;
    };
    var addTab = function (e) {
        var tb = this;
        var key = e.keyCode || e.charCode || e.which;
        if (key === 9 && !e.ctrl && !e.alt && !e.shift && !tb.disabled && !tb.readonly) {
            e.preventDefault();
            var scrollPos = tb.scrollTop;
            if (tb.setSelectionRange) {
                var sS = tb.selectionStart;
                var sE = tb.selectionEnd;
                tb.value = tb.value.substring(0, sS) + "\t" + tb.value.substr(sE);
                tb.setSelectionRange(sS + 1, sS + 1);
                tb.focus();
            } else if (tb.createTextRange) {
                document.selection.createRange().text = "\t";
            }
            //Restore scrolling position
            tb.scrollTop = scrollPos;
        }
    };
    showTab(0);

    //Click handler for tab header
    $$(".tab-header>div").forEach(function (v) {
        v.addEventListener("click", function (e) {
            e.preventDefault();
            showTab(+this.id.split('-').pop());
        });
    });

    //Back link
    $$(".backlink").forEach(function (v) {
        v.addEventListener("click", function (e) {
            e.preventDefault();
            history.back();
        });
    });

    $$("img[data-original]").forEach(function (v) {
        v.addEventListener("click", function (e) {
            e.preventDefault();
            if (this.getAttribute("loading") !== 'y') {
                var orig = this.getAttribute("data-original");
                var current = this.src;
                this.src = orig;
                this.setAttribute("data-original", current);
                this.setAttribute("loading", 'y');
            } else {
                alert("Please wait, the image is still loading.");
            }
        });
    });

    $$("#cmdcopylink").forEach(function (v) {
        v.addEventListener("click", function (e) {
            var target = $("input[name=cmd]");
            e.preventDefault();
            target.value = this.getAttribute("data-command") || "";
            target.focus();
        });
    });

    $$("img[data-original]").forEach(function (v) {
        v.addEventListener("load", function () {
            this.removeAttribute("loading");
        });
        v.addEventListener("error", function () {
            this.removeAttribute("loading");
            alert("Failed to load the image");
        });
    });

    $$(".accept-tab").forEach(function (tb) {
        tb.addEventListener("keydown", addTab);
    });

    document.addEventListener("DOMContentLoaded", function () {
        console.timeEnd("READY");
    });
    window.addEventListener("load", function () {
        console.timeEnd("LOAD");
    });
    console.timeEnd("INIT");
})(document.querySelector.bind(document), document.querySelectorAll.bind(document))
