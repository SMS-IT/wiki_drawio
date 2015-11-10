jQuery(document).ready( function() {
    var plugin_iframe = document.getElementById('drawio-iframe');
	var xml_data = '';

    // sending message to draw.io
    var send_msg = function (data) {
        plugin_iframe.contentWindow.postMessage(JSON.stringify(data), 'https://www.draw.io');
    }

    // the event handler of save
    var save_callback = function(xml) {
		// save the xml
        document.getElementById('drawio-editor-mask').style.display = "block";
        document.getElementById('drawio-xml').value = xml;
		xml_data = xml;
		
		// initiate the png export
        img_msg = {
            'action':       'export',
            'embedImages':  true,
            'format':       'png' 
        };
        send_msg(img_msg);
    }

    // the event handler of export
    var get_img = function(img_type, img_data) {
        var data = {
            'xml': xml_data,
			'drawio': document.getElementById('drawio-name').value,
            'img_type': img_type,
            'png': img_data
        };
				
		// special page url
		upload_url = document.getElementById('drawio-upload-url').value;
		
        // post to a special page that will save the xml and png in the wiki
        jQuery.post(upload_url, data, function(response) {
            
            if(response['success']) {
                document.getElementById('drawio-editor-mask').style.display = "none";           
            } else {
                img_msg = {
                    'action':       'dialog',
                    'title':        'Error',
                    'message':      resp['html'],
                    'button':       'OK',
                    'modified':     true
                };
                send_msg(img_msg);
            }

            document.getElementById('drawio-editor-mask').style.display = "none";
        });	
		
		// initiate a re-download diagram,
		// make it available for editing
		load_msg = {
            'action':   'load',
            'xml':      xml_data
        };
        send_msg(load_msg);
    }

    // the event handler of exit
    var exit_callback = function() {
		location = document.getElementById('drawio-close-url').value;
    }

    // Wait for messages from draw.io iframe.
    var receive = function(evt) {
        if(evt.origin == 'https://www.draw.io') {
            resp = JSON.parse(evt.data);

            switch(resp['event']) {
				
                // the init event of draw.io
                case 'init':
					// source xml
                    xml = document.getElementById('drawio-xml');

                    if(xml === null) {
                        alert('xml is null!');
                        xml = '';
                    } else {
                        xml = xml.value;
					}
					
					// initiate the download diagram
                    load_msg = {
                        'action': 'load',
                        'xml': xml
                    };
                    send_msg(load_msg);
                    break;

                // event loading the diagram
                case 'load':
                    break;

                // event saving the diagram
                case 'save':
                    save_callback(resp['xml']);
                    break;

                // event exporting the diagram
                case 'export':
                    get_img(resp['format'], resp['data']);
                    break;

                // the output event from the draw.io
                case 'exit':
                    exit_callback();
                    break;

                default:
                    alert('ERROR: Unrecognized message');
                    break;
            }
        }
    };
    window.addEventListener('message', receive);
});
