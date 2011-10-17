// Functions for the edit form

function hscheckImages(text1,text2,wwwroot,f){    
    
    if(!document.getElementById('id_image') || typeof(document.getElementById('id_image')) == 'null' || typeof(document.getElementById('id_image')) == 'undefined'){
        window.alert(text1);
        return true;
    }
    
    if(!document.getElementById('id_image').value){
        window.alert(text2);
        return true;
    }
    
    hsopenEditor(wwwroot,f);
}

function hsopenEditor(wwwroot,f){
    var cmid = f.cmid.value;
    var courseid = f.courseid.value;
    window.open(wwwroot+'/question/type/ubhotspots/hseditor.php?cmid='+cmid+'&courseid='+courseid,'hseditor','height='+screen.height+',width='+screen.width+'top=0, left=0, resizable=yes');
}