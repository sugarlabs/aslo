

function addTag() {
	// mod_rewrite doesn't play well with quotes

	var tagVal = $("#newTag").val();
	var addonid = $("#tags ul").attr("id").split("addonid-")[1];
	
	if($.trim(tagVal)=='' || $.trim(addonid)==''){
		return;
	}
	$.get(add_ajax_url+addonid+"/" + urlencode(tagVal), function(data) {
		$("#tags").html(data);
		$(".addtagform form")[0].reset();
		$("#tags .addon-tags").removeClass("nojs");
	});
};

 // PHP-compatible urlencode() for Javascript from http://us.php.net/manual/en/function.urlencode.php#85903
 function urlencode(s) {
  s = encodeURIComponent(s);
  return s.replace(/~/g,'%7E').replace(/%20/g,'+');
 };

function remTag(addonid, tagid){
	//var addonid = '<?= $addon['Addon']['id']; ?>';
	$.get(remove_ajax_url+ addonid +"/"+ tagid, function(data){
		$("#tags").html(data);
		$(".addtagform form")[0].reset();
		$("#tags .addon-tags").removeClass("nojs");
	});
};

$(document).ready(function(){
	//remove nojs classname so that css will hide the x's
	$("#tags .addon-tags").removeClass("nojs");
	//hide add tag form if you have js
	$(".addtagform ").addClass("hidden");
	
	$("#addtagbutton").click(function(e){
		addTag();
		e.preventDefault();
		e.stopPropagation();
	});
	$("#tags .removetag").live("click",function(e){
		var tagid = $(this).attr("id").split("remtag-")[1];
		var addonid = $(this).parents("ul").attr("id").split("addonid-")[1];
		//var tagid = $(this).parent().find(".tagitem").text();
		remTag(addonid,tagid);
		e.preventDefault();
		e.stopPropagation();
	});
	
	$("#tags .developertag, #tags .usertag")
		.live("mouseover",function(){
			$(this).addClass("hover");
			})
		.live("mouseout",function(){
			$(this).removeClass("hover");
			});
	
	$("#addatag").click(function(e){
		$(".addtagform")
			.removeClass("hidden")
			.attr("style","display:block;");
		e.preventDefault();
		e.stopPropagation();
	});

	$("#newTag").live("keypress",function(e){
		//alert(e.keyCode);
		if($.trim($(this).val()) != '' &&  e.keyCode == 13) {
			console.log("add tag")
			$("#addtagbutton").click();
			e.preventDefault();
			e.stopPropagation();
		}

	});
})
