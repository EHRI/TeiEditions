jQuery(function($) {
  $("#document-text")
      .tabs({
        show: false,
        active: 0,
        activate: function(e, ui) {
          ui.oldTab.find("a")
              .toggleClass("element-text-language-selected element-text-language");
          ui.newTab.find("a")
              .toggleClass("element-text-language-selected element-text-language");
        }
      });

  var $entities = $(".tei-entities");
  var $infoPanel = $("#content-info");

  function affix($elem) {
      var margin = 20; // FIXME: margin from page top
      if ($elem.length) {
          $elem.toggleClass("affixed", $infoPanel.offset().top < window.scrollY + margin);
      }
  }

  function showInPanel($elem) {
    if ($elem.length > 0) {
      var $clone = $elem.clone();
      $infoPanel
          .children()
          .remove()
          .end()
          .append($clone.show());
      affix($clone)
    }
  }

  $(window).scroll(function(e) {
      affix($infoPanel.find(".content-info-entity, .tei-note"));
  });

  $(".tei-entity-ref").hoverIntent(function() {
    var url = $(this).data("ref");
    showInPanel($entities.find(".content-info-entity[data-ref='" + url + "']"));
  });

  $(".tei-note-ref").hoverIntent(function() {
    showInPanel($(this).next(".tei-note"));
  });
});