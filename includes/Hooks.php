<?php
namespace MediaWiki\Extension\DrawIo;

class Hooks
{
    public static function onParserFirstCallInit( \Parser $parser )
    {
        // Create a function hook associating the "drawio" magic word with renderDrawIo()
        $parser->setFunctionHook( 'drawio', [ self::class, 'renderDrawIo' ] );
    }

    public static function renderDrawIo( &$parser, $name = null, $width = null, $height = null )
    {
        global $wgUser, $wgTitle, $wgArticlePath, $wgScriptPath, $wgEnableUploads;

        // Don't cache pages with drawio on it
        $parser->disableCache();

        # Validate parameters
        $error = '';
        if ($name == null || strlen($name) == 0) {
            $error .= '<br>Please specify a name for your drawio diagram.';
        }
        if ($width != null &&
            (! is_numeric($width) || $width < 1 || $width > 2000)) {
            $error .= '<br>Please specify the width as a number between 1 and 2000 or leave it away.';
        }
        if ($height != null &&
            (! is_numeric($height) || $height < 1 || $height > 2000)) {
            $error .= '<br>Please specify the height as a number between 1 and 2000 or leave it away.';
        }
        if (strlen($error) > 0) {
            $error = '<b>Sorry.</b>'.$error.'<br>'.
                'Usage: <code>{{#drawio:<i>drawio_id</i>}}</code><br>'.
                'Example: <code>{{#drawio:drawio_id}}</code><br>';
            return array($error, 'isHTML'=>true, 'noparse'=>true);
        }

        # The parser function itself
        # The input parameters are wikitext with templates expanded
        # The output should be wikitext too, but in this case, it is HTML
        #return array("param1 is $param1 and param2 is $param2", 'isHTML');

        $isProtected = $parser->getTitle()->isProtected();

        # Generate the image HTML as if viewed by a web request
        $image_name = "Drawio_".$name.".png";
        $image = wfLocalFile($image_name);

        if ($image !== false) {
            if ($image != null) {
                $width = $image->getWidth();
                $height = $image->getHeight();
            }
        }

        // render a header
        $output = '';
        if ($wgEnableUploads && !$isProtected && key_exists('drawiotitle', $_POST) && 	$_POST['drawiotitle'] == $name){

            // edit the drawio
            $uploadURL = str_replace('$1', 'Special:Drawio', $wgArticlePath);

            $xml_name = "Drawio_".$name.".xml";
            $xml = self::getXml($xml_name);

            // вывод фрэйма
            $output .=
                '<a name="Drawio" id="Drawio">'.
                '<script src="//code.jquery.com/jquery-1.10.2.js"></script>'.
                '<script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>'.
                '<script type="text/javascript" src="'.$wgScriptPath.'/extensions/DrawIo/resources/js/drawio.js"></script>'.
                '<link rel="stylesheet" href="'.$wgScriptPath.'/extensions/DrawIo/resources/css/drawio.css">'.
                '<link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">'.
                '<script>jQuery(function() {$( "#resizable" ).resizable();});</script>'.

                '<input type="hidden" id="drawio-xml" value="'.htmlspecialchars($xml).'">'.
                '<input type="hidden" id="drawio-name" value="'.htmlspecialchars($name).'">'.
                '<input type="hidden" id="drawio-upload-url" value="//'.$_SERVER['HTTP_HOST'].$uploadURL.'">'.
                '<input type="hidden" id="drawio-close-url" value="'.$_SERVER['HTTP_REFERER'].'"/>'.

                '<div id="resizable" style="width:100%;height:600px">'.
                '<iframe style="width:100%;height:100%;" class="drawio-editor-iframe" id="drawio-iframe" src="https://www.draw.io/?embed=1&analytics=0&gapi=0&db=0&od=0&proto=json&spin=1"></iframe>'.
                '</div>'.
                '<div class="drawio-editor-mask" id="drawio-editor-mask" style="display:none;">'.
                '<div class="drawio-editor-saving">Saving...<div class="drawio-editor-saving-x" onclick="jQuery(\'drawio-editor-mask\').css(\'display\',\'none\');">x</div></div>'.
                '</div>'.
                '</a>';

        } else {
            $output .= '<table><tr><td>';

            // Retrieve the page object of the image to determine, whether the user may edit it
            $filtered = preg_replace ( "/[^".\Title::legalChars()."]|:/", '-', $name );
            $nt = \Title::newFromText( $filtered );

            // Determine if the user has permission to edit the image
            $userCanEdit = $wgEnableUploads &&
                !$isProtected &&
                (is_null($image) || $wgUser->isAllowed( 'reupload' ));

            // If the user can edit the image, display an edit link.
            // We do not display the edit link, if the user is already
            // editing a drawio.
            if ($userCanEdit && ! key_exists('drawiotitle', $_POST)) {
                $formId = 'Form'.rand();
                global $wgUsePathInfo;
                if (is_null($wgTitle)) return;

                if ($wgUsePathInfo) {
                    $action = $wgTitle->getLocalURL().'#Drawio';
                } else {
                    $action = $wgTitle->getLocalURL();
                }
                $output .= '<form name="'.$formId.'" method="post" action="'.$action.'">'.
                    '<input type="hidden" name="drawiotitle" value="'.htmlspecialchars($name).'">'.
                    '<p align="right">'.
                    '<a class="noprint" href="javascript:document.'.$formId.'.submit();">['.wfMessage('edit').']</a>'.
                    '<noscript><input type="submit" name="submit" value="'.wfMessage('edit').'"></input></noscript>'.
                    '</p>';
            }

            // render the drawio
            if (($image === false) || ($image == null)) {
                // the drawio does not exist yet, render an empty rectangle
                $output .= '<div style="border:1px solid #000;text-align:center;'.
                    (($width != null) ? 'width:'.$width.'px;' : '').
                    (($height != null) ? 'height:'.$height.'px;' : '').'"'.
                    '>'.htmlspecialchars($name).'</div>';
            } else {
                $output .= '<a href="'.$wgScriptPath.'/index.php/Image:' . $image_name.'">';
                // Note: We append the timestamp of the image to the
                //       view URL as a query string. This way, we ensure,
                //       that the browser always displays the last edited version
                //       of the image
                $output .= '<img src="' . $image->getViewUrl().
                    '?version='.$image->nextHistoryLine()->img_timestamp.'" '.
                    (($width != null) ? 'width="'.$width.'" ' : '').
                    (($height != null) ? 'height="'.$height.'" ' : '').
                    'alt="Image:'.$name.'" '.
                    'title="Image:'.$name.'" '.
                    //(($isImageMap) ? 'usemap="#'.$mapId.'" ' : '').
                    '></img>';
                    $output .= '</a>';
            }
            // If the user can edit the image, display an edit link.
            // We do not display the edit link, if the user is already
            // editing a drawio.
            if ($userCanEdit && ! key_exists('drawiotitle', $_POST)) {
                $output .= '</form>';
            }

            $output .= '</tr></td></table>';
        }

        // render a footer
        $return = array($output, 'isHTML'=>true, 'noparse'=>true);
        return $return;
    }

    private static function getXml($xmlFileName)#
    {
        $file = wfLocalFile($xmlFileName);
        return file_get_contents($_SERVER['DOCUMENT_ROOT'] . $file->getViewUrl());
    }
}