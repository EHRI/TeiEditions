jQuery(function($) {

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