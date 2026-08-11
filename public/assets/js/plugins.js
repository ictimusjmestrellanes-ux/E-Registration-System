(function() {
    var needsToast = document.querySelectorAll("[toast-list]").length > 0;
    var needsChoices = document.querySelectorAll("[data-choices]").length > 0;
    var needsFlatpickr = document.querySelectorAll("[data-provider]").length > 0;

    if (needsToast || needsChoices || needsFlatpickr) {
        if (needsToast) {
            var l = document.createElement("link");
            l.rel = "stylesheet";
            l.href = "assets/libs/toastify/css/toastify.min.css";
            document.head.appendChild(l);
        }

        var scripts = [];
        if (needsToast) scripts.push("assets/libs/toastify/js/toastify.min.js");
        if (needsChoices) scripts.push("assets/libs/choices.js/public/assets/scripts/choices.min.js");
        if (needsFlatpickr) scripts.push("assets/libs/flatpickr/flatpickr.min.js");

        var load = function(index) {
            if (index >= scripts.length) return;
            var s = document.createElement("script");
            s.src = scripts[index];
            s.onload = function() { load(index + 1); };
            document.body.appendChild(s);
        };
        load(0);
    }
})();
