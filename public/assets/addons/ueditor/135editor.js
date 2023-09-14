UE.registerUI('135editor',function(editor,uiName){
    // var dialog = new UE.ui.Dialog({
    //     iframeUrl: editor.options.UEDITOR_HOME_URL+'dialogs/135editor/135EditorDialogPage.html',
    //     cssRules:"width:"+ parseInt(document.body.clientWidth*0.9) +"px;height:"+(window.innerHeight -50)+"px;",
    //     editor:editor,
    //     name:uiName,
    //     title:"135编辑器"
    // });
    // dialog.fullscreen = false;
    // dialog.draggable = false;

    var editor135;
    function onContentFrom135(event) {
        if (typeof event.data !== 'string') {
            if(event.data.ready) {
                editor135.postMessage(editor.getContent(),'*');
            }
            return;
        };

        if(event.data.indexOf('<') !== 0) return;

        editor.setContent(event.data);
        editor.fireEvent("catchRemoteImage");
        window.removeEventListener('message', onContentFrom135);
    }

    var btn = new UE.ui.Button({
        name:'btn-dialog-' + uiName,       
        className:'edui-for-135editor',
        title:'135编辑器',
        onclick:function () {
            // dialog.render();
            // dialog.open();

            editor135 = window.open('https://www.135editor.com/simple_editor.html?callback=true&appkey=')
            
            window.removeEventListener('message', onContentFrom135);
            window.addEventListener('message', onContentFrom135, false);
        }
    });
    return btn;
},undefined);