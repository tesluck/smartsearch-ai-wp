/**
 * SmartSearch AI — Admin JavaScript
 *
 * @package SmartSearch_AI
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    // ── Tab switching ──────────────────────────────────
    $(".ssai-tab").on("click", function () {
      var tab = $(this).data("tab");
      $(".ssai-tab").removeClass("active");
      $(this).addClass("active");
      $(".ssai-tab-content").removeClass("active");
      $("#ssai-tab-" + tab).addClass("active");
    });

    // ── Test OpenAI connection ─────────────────────────
    $("#ssai-test-openai").on("click", function () {
      var $btn = $(this);
      var $status = $("#ssai-openai-status");

      $btn.prop("disabled", true).text("Testing...");
      $status.text("").removeClass("success error");

      $.ajax({
        url: ssaiAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "ssai_test_openai",
          nonce: ssaiAdmin.nonce,
        },
        success: function (response) {
          if (response.success) {
            $status.text(response.data.message).addClass("success");
          } else {
            $status.text(response.data.message).addClass("error");
          }
        },
        error: function () {
          $status.text("Request failed").addClass("error");
        },
        complete: function () {
          $btn.prop("disabled", false).text("Test Connection");
        },
      });
    });

    // ── Export dictionary ───────────────────────────────
    $("#ssai-export-btn").on("click", function () {
      $.ajax({
        url: ssaiAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "ssai_export_dictionary",
          nonce: ssaiAdmin.nonce,
        },
        success: function (response) {
          if (response.success) {
            var blob = new Blob([response.data.json], {
              type: "application/json",
            });
            var url = URL.createObjectURL(blob);
            var a = document.createElement("a");
            a.href = url;
            a.download = "smartsearch-ai-dictionary.json";
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
          }
        },
      });
    });

    // ── Import dictionary ──────────────────────────────
    $("#ssai-import-btn").on("click", function () {
      $("#ssai-import-file").trigger("click");
    });

    $("#ssai-import-file").on("change", function (e) {
      var file = e.target.files[0];
      if (!file) return;

      var reader = new FileReader();
      reader.onload = function (e) {
        if (
          confirm(
            "Import this dictionary? It will be saved as a new dictionary file."
          )
        ) {
          $.ajax({
            url: ssaiAdmin.ajaxUrl,
            type: "POST",
            data: {
              action: "ssai_import_dictionary",
              nonce: ssaiAdmin.nonce,
              dictionary_json: e.target.result,
            },
            success: function (response) {
              if (response.success) {
                alert(response.data.message);
                location.reload();
              } else {
                alert("Error: " + response.data.message);
              }
            },
          });
        }
      };
      reader.readAsText(file);
    });
  });
})(jQuery);
