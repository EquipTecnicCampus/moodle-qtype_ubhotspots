<?php
/**
 * The Hot Spots editor
 *    
 * @copyright &copy; 2011 Universitat de Barcelona
 * @author <jleyva@cvaconsulting.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package ubhotspots
 *
 */

require_once("../../../config.php");
require_once($CFG->dirroot.'/question/editlib.php');

$cmid = optional_param('cmid',0,PARAM_INT);
$courseid = optional_param('courseid',0,PARAM_INT);


// Access control
if($cmid){
    
    list($module, $cm) = get_module_from_cmid($cmid);

    if(!$course = get_record('course','id',$cm->course))
        error('Invalid course');
    
    require_login($course->id, false, $cm);
    $context = get_context_instance(CONTEXT_MODULE, $cmid);
    require_capability('moodle/question:add', $context);
}
else{
    
    if(!$course = get_record('course','id',$courseid))
        error('Invalid course');
	
    require_login($course->id);
    $context = get_context_instance(CONTEXT_COURSE, $course->id);
    require_capability('moodle/question:add', $context);
}



// Wee need the lang file for load strings
$lang = current_language();
$langfile = $CFG->dirroot."/question/type/ubhotspots/lang/$lang/qtype_ubhotspots.php";
if(file_exists($langfile)){
    include($langfile);
}
else{
    include($CFG->dirroot."/question/type/ubhotspots/lang/en_utf8/qtype_ubhotspots.php");
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>UB Hotspots</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <link type="text/css" rel="stylesheet" href="css/jquery.layout.css" />
    <link type="text/css" rel="stylesheet" href="css/smoothness/jquery-ui-1.8.16.custom.css" />
    <script type="text/javascript" src="jquery.js"></script>
    <script type="text/javascript" src="jquery.layout.js"></script>
    <script type="text/javascript" src="jquery.ui.js"></script>
    <!--[if IE]><script type="text/javascript" src="excanvas.js"></script><![endif]-->
    <script language="javascript">
        var canvasWidth = 400;
        var canvasHeight = 400;
        var hotSpotsCounter = 1;
	var toolSelected = null;
	var currentHS = 0;
	var paint,debugOn = false;
	var startX,startY,endX,endY = 0;
	var K = 4*((Math.SQRT2-1)/3);
	var drawBuffer = [];
	var hsColors = ['aqua', 'red', 'blue', 'fuchsia','green' ,'yellow', 'navy','lime', 'maroon',  'olive',  'gray', 'purple',  'silver', 'teal'];
	var canvas1, canvas2, ctx1, ctx2;
	var backgroundImgPath = "<?php echo $CFG->wwwroot.'/file.php/'.$course->id.'/'; ?>"+opener.document.getElementById('id_image').value;
	var backgroundImg;	
	
	var localStrings = {};
	<?php
	    foreach($string as $key=>$val){
		if(strpos($key,'js') === 0){
		    $key = substr($key,2);
		    echo "localStrings['$key'] = '$val';\n";
		}
	    }
	?>
	
	
        var accordionHeader = "<h3 id=\"ahid_idname_\"><a href=\"#\">hotspot _idname_</a><span style=\"position: absolute; right: 2px; top: 2px; background-color: _color_; width: 10px; height: 10px\"></span></h3>";
	accordionHeader += "<div id=\"abid_idname_\" style=\"padding: 2px\">";
	accordionHeader += "<div class=\"rb\">";
	accordionHeader += "<input type=\"radio\" id=\"rect_idname_\" name=\"shape_idname_\" class=\"toolrect\"/><label for=\"rect_idname_\">"+getString("rectangle")+"</label>";
	accordionHeader += "<input type=\"radio\" id=\"ellip_idname_\" name=\"shape_idname_\" class=\"toolellip\"/><label for=\"ellip_idname_\">"+getString("ellipse")+"</label>";
	accordionHeader += "<div id=\"hst_idname_\" class=\"hst\">";
        accordionHeader += getString('entertext');
        accordionHeader += "</div><a href=\"#\" id=\"delhs_idname_\">"+getString("delete")+"</a></div></div>";
        

	function getString(s){	    
	    if(typeof(localStrings[s]) != 'undefined')
		return localStrings[s];
	    return '';
	}
	
	$(document).ready(function () {             
	    
	    // Draw surface (canvas)
	    // excanvas needs this code to be place here
	    
	    var canvasDiv = $('#canvas1');
	    
	    canvas1 = document.createElement('canvas');
	    
	    canvas1.setAttribute('id', 'c1');
	    
	    canvasDiv.append(canvas1);
	    if(typeof G_vmlCanvasManager != 'undefined') {
		canvas1 = G_vmlCanvasManager.initElement(canvas1);
	    }
	    ctx1 = canvas1.getContext("2d");		
		
	    canvas2 = document.createElement('canvas');
         		
	    canvas2.setAttribute('id', 'c2');
	    canvasDiv.append(canvas2);
	    
	    if(typeof G_vmlCanvasManager != 'undefined') {
		canvas2 = G_vmlCanvasManager.initElement(canvas2);
	    }
	    ctx2 = canvas2.getContext("2d");
	    
	    backgroundImg = new Image();
	    backgroundImg.onload = function(){   
		
		canvasWidth = backgroundImg.width;
		canvasHeight = backgroundImg.height;
		canvas1.setAttribute('width', canvasWidth);
		canvas1.setAttribute('height', canvasHeight);
		canvas2.setAttribute('width', canvasWidth);
		canvas2.setAttribute('height', canvasHeight);  
		canvas2.style.marginLeft = '-'+canvasWidth+'px';
		
		ctx1.drawImage(backgroundImg, 0, 0);
		// Chrome bug - See http://groups.google.com/group/jquery-ui-layout/browse_thread/thread/c8c48ccd838797f9
		setTimeout(initLayout, 20);
	      
	    }
	    backgroundImg.src = backgroundImgPath;
	}); 
	
        function initLayout(){

	    function addElementAcc(){
		
		// Layout init
		
		
		$('#questiontext').html(opener.document.getElementById('id_name').value);
		
		//add accordion item, destroy then re-create
		var colorIndex = hotSpotsCounter;
		if(hotSpotsCounter >= hsColors.length)
		    colorIndex = parseInt(hotSpotsCounter % hsColors.length);		    
	    
		$("#spots").append(accordionHeader.replace(/_idname_/g,hotSpotsCounter).replace(/_color_/,hsColors[colorIndex])).accordion("destroy");		
		//set state
		$("#spots").accordion({active: hotSpotsCounter - 1});		
		
		$(".rb").buttonset();
		$("#hst"+hotSpotsCounter).click(function(){
		    var hsid = $(this).attr('id');
		    $("#hsaddtext").attr('value',$("#"+hsid).html());
		    $('#addtextdialog').dialog({
			    width: 600,
			    height: 400,
			    title: 'change text',
			    modal: true,
			    autoOpen: false,
			    buttons: { "Ok": function() {
					    $("#"+hsid).html($("#hsaddtext").attr('value'));					    
					    $(this).dialog("close");
					    }
				    }
			    }).dialog('open');
		});
		$("#ellip"+hotSpotsCounter).click(function(){
		    toolSelected = 'ellip';
		    currentHS = parseInt($(this).attr('id').replace("ellip",""));			
		});
    
		$("#rect"+hotSpotsCounter).click(function(){
		    toolSelected = 'rect';
		    currentHS = parseInt($(this).attr('id').replace("rect",""));			
		});
		
		
		$("#delhs"+hotSpotsCounter).click(function(){
		    var imgId = parseInt($(this).attr('id').replace('delhs',''));
		    if(drawBuffer[imgId].draw != null){			
			$('<div><p>'+getString('confirmdelete')+'</p>').dialog({
							width: 350,
							height: 250,
							modal:true,
							autoOpen:false,
							buttons: { "Yes": function() {
									    drawBuffer[imgId].draw = null;
									    drawBuffer[imgId].shape = null;
									    drawBackground();
									    $(this).dialog("close");
								},  "No": function() {
									    $(this).dialog("close");
									    }}
							}).dialog('open');		    
		    }		    		    
		});
		
		drawBuffer[hotSpotsCounter] = {draw: null, text: null, shape: null};
		hotSpotsCounter++;
	    }
	    
	    // Accordion
	    $( "#spots" ).accordion();
	               
                        
	    // Add, save and close buttons
            $("#badd").button({ icons: {primary:'ui-icon-plusthick'}}).click(addElementAcc);	    
	    $("#bsave").button({ icons: {primary:'ui-icon-circle-check'}}).click(saveChanges);
	    $("#bclose").button({ icons: {primary:'ui-icon-circle-close'}}).click(function(){
										    $("<p>"+getString('closewithout')+"</p>").dialog({title: getString('warning'), modal: true,autoOpen: false, buttons: { "OK": function() { self.close(); },  "No": function() { $(this).dialog("close"); }}}).dialog('open');
									       });
	    
	    // End layout
	    
	    // Loading saving data
	    var loadedElements = 0;
	    
	    if(opener.document.getElementById('mform1').hseditordata.value){
		var savedData = $.parseJSON(opener.document.getElementById('mform1').hseditordata.value);		
		if(savedData){		    
		    for(var el = 0; el < savedData.length; el++ ){
			if(savedData[el]){
			    addElementAcc();
			    loadedElements++;
			    
			    drawBuffer[el] = savedData[el];			    
			    
			    $("#hst"+el).html((drawBuffer[el].text)? drawBuffer[el].text.replace(/<br \/>/g,"\n") : getString('entertext'));			    			    			    
			}
		    }
		    drawBackground();
		}
	    }
	    
	    if(!loadedElements){
		// First element
		addElementAcc();  
	    }
	    
	    // Functions
	    
	    function saveChanges(){
		var jsonData = drawBuffer;
		for(var el in drawBuffer){		    	    	    
		    jsonData[el] = drawBuffer[el];
		    jsonData[el].text = $("#hst"+el).html().replace(/\n/g,'<br />');		    
		}
		opener.document.getElementById('mform1').hseditordata.value = JSON.stringify(jsonData);		
		self.close();
	    }
		    
        
	    // Events on canvas
	    
	    function drawRect(startX, startY , endX, endY, cIndex, ctx){
		canvas2.width = canvas2.width; // Clears the canvas
		
		// colors: http://www.w3.org/TR/css3-color/		
		if(cIndex >= hsColors.length)
		    cIndex = parseInt(cIndex % hsColors.length);
				
		ctx.strokeStyle = hsColors[cIndex];
		ctx.lineJoin = "round";
		ctx.lineWidth = 2;
		
		ctx.strokeRect(startX, startY, endX - startX, endY - startY);
		
	    }
	    
	    function drawEllip(cx, cy, rx, ry, startX, startY, endX, endY, cIndex, ctx){
		canvas2.width = canvas2.width; // Clears the canvas
		
		// colors: http://www.w3.org/TR/css3-color/		
		if(cIndex >= hsColors.length)
		    cIndex = parseInt(cIndex % hsColors.length);
				
		ctx.strokeStyle = hsColors[cIndex];
		ctx.lineJoin = "round";
		ctx.lineWidth = 2;
						
		ctx.beginPath();
		
		// startX, startY
		ctx.moveTo(cx, startY);
		
		// Control points: cp1x, cp1y, cp2x, cp2y, destx, desty
		// go clockwise: top-middle, right-middle, bottom-middle, then left-middle
		ctx.bezierCurveTo(cx + rx, startY, endX, cy - ry, endX, cy);
		ctx.bezierCurveTo(endX, cy + ry, cx + rx, endY, cx, endY);
		ctx.bezierCurveTo(cx - rx, endY, startX, cy + ry, startX, cy);
		ctx.bezierCurveTo(startX, cy - ry, cx - rx, startY, cx, startY);
		
		ctx.stroke();
		ctx.closePath();
		
	    }
	    
	            
	    function redrawCanvas(){		
						
		if(toolSelected == 'rect'){		    
		    drawRect( startX, startY , endX, endY, currentHS, ctx2);
		    drawBuffer[currentHS].shape = {shape: 'rect', startX: startX, startY: startY , endX: endX, endY: endY};
		}
		
		// http://www.powerbasic.com/support/pbforums/showthread.php?t=42583
		if(toolSelected == 'ellip'){
		    w = endX - startX;
		    h = endY - startY;
		    
		    // Ellipse radius
		    var rx = w/2,
			ry = h/2; 
		    
		    // Ellipse center
		    var cx = startX+rx,
			cy = startY+ry;
		    
		    // Ellipse radius*Kappa, for the Bézier curve control points
		    rx *= K;
		    ry *= K;
		    drawEllip( cx, cy, rx, ry, startX, startY, endX, endY, currentHS, ctx2);		    
		    drawBuffer[currentHS].shape = {shape: 'ellip', cx: cx, cy: cy, rx: rx, ry: ry, startX: startX, startY: startY, endX: endX, endY: endY};		
		}	    
	    
	    }
        
	    $('#c2').mousedown(function(e){		   
		    
		if(!currentHS || drawBuffer[currentHS].draw != null)
		    return true;
		
		var diffLayout = 0;

		if(! myLayout.state.north.isClosed){
		    diffLayout = myLayout.state.north.size;
		}
		
		var mouseX = e.pageX - this.offsetLeft;
		var mouseY = e.pageY - this.offsetTop - diffLayout;        
		  
		if(! $.browser.mozilla){
		    mouseX = e.offsetX;
		    mouseY = e.offsetY;
		}
		  
		paint = true;
		startX = mouseX;
		startY = mouseY;
		
		endX = mouseX;
		endY = mouseY;
		redrawCanvas();
	    });
	
	
	    $('#c2').mousemove(function(e){
			
		if(!currentHS || drawBuffer[currentHS].draw != null)
		    return true;
		
		if(debugOn){
		    var data = '';
		    for(el in e){
			if(el.indexOf('X') > 0 || el.indexOf('Y') > 0 || el.indexOf('offset') > 0)
			    data += el + " -> " + e[el] + "<br>"; 
		    }
		    data += "offsetLeft" + this.offsetLeft+ "<br>";
		    data += "offsetTop" + this.offsetTop;
		    $("#debugdiv").html(data);
		}
		    
		var diffLayout = 0;

		if(! myLayout.state.north.isClosed){
		    diffLayout = myLayout.state.north.size;
		}
		
		var mouseX = e.pageX - this.offsetLeft;
		var mouseY = e.pageY - this.offsetTop - diffLayout;    
		
		if(! $.browser.mozilla){
		    mouseX = e.offsetX;
		    mouseY = e.offsetY;
		}
				
		if(paint){
		  endX = mouseX;
		  endY = mouseY;
	      
		  redrawCanvas();
		}
		
	    });        
	    
	    
	    $('#c2').mouseup(function(e){
		
		if(!currentHS || drawBuffer[currentHS].draw != null)
			return true;
		
		paint = false;
		
		finishDrawing(currentHS);
		
	    });
	      
	    $('#c2').mouseleave(function(e){
		paint = false;
	    });
	    
	    
	    function finishDrawing(hsIndex){
		// Add draw to the buffer
		//drawBuffer[hsIndex].draw = canvas2.toDataURL('image/png');
		drawBuffer[hsIndex].draw = 1;
		// clear overlay canvas
		canvas2.width = canvas2.width;
		// Redraw the background canvas
		drawBackground();
		
	    }
	    
	    function drawBackground(){
		canvas1.width = canvas1.width;
		ctx1.drawImage(backgroundImg, 0, 0);
		for(var el = 1; el <  drawBuffer.length; el++){	    
		    if(typeof(drawBuffer[el]) != 'undefined' && drawBuffer[el].draw != null){			
			/*var img = new Image();			
			img.src=drawBuffer[el].draw;
			drawImage(img);
			*/
			if(drawBuffer[el].shape.shape == 'rect'){
			    var s = drawBuffer[el].shape;
			    drawRect( s.startX, s.startY , s.endX, s.endY, el, ctx1);				
			}
			else if(drawBuffer[el].shape.shape == 'ellip'){			    
			    var s = drawBuffer[el].shape;
			    drawEllip( s.cx, s.cy, s.rx, s.ry, s.startX, s.startY, s.endX, s.endY, el, ctx1);
			}	
			
		    }
		}
	    }
	    
	    /*function drawImage(img){
		img.onload=function(){
		ctx1.drawImage(img,0,0);			  
	      }
	    } */   
	    
	    // Layout
	    var myLayout = $('body').layout({
                center__size: canvasWidth,
                center__minSize: canvasWidth,
                north__maxSize: 150,
                east__minSize: 300,
                east__maxSize: 600
            });
            
        }
    </script>
</head>

<body>

<div class="ui-layout-north">
<p id="questiontext"></p>
</div>
<div class="ui-layout-center">
    <div id="maincontainer">
        <div id="canvas1" style="left: 0px; top: 0px; float: left">
        
        </div>        
    </div> 
</div>
<div class="ui-layout-east">
<div id="spots">
    
</div>
<button id="badd"><?php echo get_string('jsadd','qtype_ubhotspots'); ?></button>
<br /><br /><br />
    <div id="actionsb">
    <button id="bsave"><?php echo get_string('jssave','qtype_ubhotspots'); ?></button>
    <button id="bclose"><?php echo get_string('jscancel','qtype_ubhotspots'); ?></button>
    </div>
<div id="debugdiv">    
</div>
</div>

<p id="addtextdialog"><textarea id="hsaddtext" style="width: 100%; height:100%"></textarea></p>

</body>
</html>