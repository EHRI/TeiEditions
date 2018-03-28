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
  var infoPanel = $("#content-info");

  $(".tei-entity-ref").hoverIntent(function() {
    var url = $(this).data("ref");
    var $entity = $entities.find(".content-info-entity[data-ref='" + url + "']");
    if ($entity.length > 0) {
      infoPanel
          .children()
          .remove()
          .end()
          .append($entity.clone().show());
    }
  });
});