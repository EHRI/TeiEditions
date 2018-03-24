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

  var $itemTexts = $("#item-texts");
  var $all = $(".tei-entity-data");

  $(".tei-entity").hoverIntent(function() {
    var url = $(this).data("ref");
    var $entities = $(".tei-entity-data[data-ref='" + url + "']");
    $all.hide();
    if ($entities.length > 0) {
      $entities.css({
        position: "fixed",
        left: $itemTexts.offset().left + $itemTexts.width(),
        top: $entities.offset().top
      }).show();
    }
  });
});