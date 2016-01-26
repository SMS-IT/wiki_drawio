<?php

class Drawio extends SpecialPage {

    var $mUploadDescription, $mLicense, $mUploadOldVersion;
    var $mUploadCopyStatus, $mUploadSource, $mWatchthis;

    static $version = "0.2";


    function Drawio() {
        parent::__construct( 'Drawio' );

        $this->mUploadDescription = '';
        $this->mLicense = '';
        $this->mUploadCopyStatus = '';
        $this->mUploadSource = '';
        $this->mWatchthis = false;
    }

    function execute( $par ) {
        $this->processUpload();
    }

    function loadFile($type) {
        global $wgRequest, $wgUser;

        $ext = $type;

        $file_name = "Drawio_" . $wgRequest->getVal('drawio'). "." . $ext;

        $wgRequest->setVal('wpDestFile', $file_name);
        $wgRequest->setVal('wpIgnoreWarning', '1');
        $wgRequest->setVal('wpDestFileWarningAck', '1');
        $wgRequest->setVal('wpUploadDescription', "");
        $wgRequest->setVal('action', "");

        if ($type == "png") {
            $file_type = "image/png";
            $pngval = $wgRequest->getVal($type);
            $comma_pos = strpos($pngval, ',');
            if($comma_pos === false) {
                $file_body = stripslashes($pngval);
            } else {
                $file_body = base64_decode(substr($pngval, $comma_pos + 1));
            }
        } else {
            $file_type = "text/xml";
            $file_body = $wgRequest->getVal($type);
        }

        $file_len = strlen($file_body);

        if ($file_len > 0) {
            $_FILES['wpUploadFile']['name'] = $file_name;
            $_FILES['wpUploadFile']['type'] = $file_type;
            $_FILES['wpUploadFile']['error'] = 0;
            $_FILES['wpUploadFile']['size'] = $file_len;
            $tmp_name = $this->getUploadDirectory() . "/drawio_tmp_".rand(0,1000).rand(0,1000).".".$ext;
            $f = fopen($tmp_name, "w");
            fwrite($f,$file_body);
            fclose($f);
            $_FILES['wpUploadFile']['tmp_name'] = $tmp_name;

            // Upload
            $form = UploadBase::createFromRequest($wgRequest, null);
            $outcome = $form->verifyUpload();
            $res = $form->performUpload("", "", true, $wgUser);

            if (file_exists($tmp_name)) {
              unlink($tmp_name);
            }
        }

        return $outcome;
    }

    function getUploadDirectory() {
		return wfTempDir();
        //global $wgUploadDirectory;
        //return $wgUploadDirectory;
        //return $_SERVER["DOCUMENT_ROOT"] . "/tmp";
    }

    function processUpload() {
        global $wgRequest;

        // Загрузка изображения
        $outcome = $this->loadFile("png");

        // Загрузка xml
        $outcome = $this->loadFile("xml");

        // Return outcome along with an appropriate error message to the client
        switch ($outcome['status']) {
            case  UploadBase::SUCCESS :
                header('HTTP/1.0 200 OK');
                header('Content-Type: text/json');
                echo('{"success":"true"}');
                break;

            case  UploadBase::FILE_TOO_LARGE :
                header('HTTP/1.0 500 Internal Server Error');
                echo('<html><body>'.wfMsgHtml( 'largefileserver' ).'</body></html>');
                break;

            case  UploadBase::EMPTY_FILE :
                header('HTTP/1.0 400 Bad Request');
                echo('<html><body>'.wfMsgHtml( 'emptyfile' ).'</body></html>');
                break;

            case  UploadBase::MIN_LENGTH_PARTNAME :
                header('HTTP/1.0 400 Bad Request');
                echo('<html><body>'.wfMsgHtml( 'minlength1' ).'</body></html>');
                break;

            case  UploadBase::ILLEGAL_FILENAME :
                header('HTTP/1.0 400 Bad Request');
                echo('<html><body>' . wfMsgHtml( 'illegalfilename', htmlspecialchars($wgRequest->getVal('mockup'))) . '</body></html>');
                break;
            case  UploadBase::OVERWRITE_EXISTING_FILE :
                header('HTTP/1.0 403 Forbidden');
                echo('<html><body>You may not overwrite the existing drawio diagram.</body></html>');
                break;

            case  UploadBase::FILETYPE_MISSING :
                header('HTTP/1.0 400 Bad Request');
                echo('<html><body>The type of the uploaded file is not explicitly allowed.</body></html>');
                break;

            case  UploadBase::FILETYPE_BADTYPE :
                header('HTTP/1.0 400 Bad Request');
                echo('<html><body>The type of the uploaded file is explicitly disallowed.</body></html>');
                break;

            case  UploadBase::VERIFICATION_ERROR :
                header('HTTP/1.0 400 Bad Request');
                echo('<html><body>');
				echo('<p>The uploaded file did not pass server verification: ' . print_r($outcome, true) . '</p>');
                echo('</body></html>');
                break;

            case  UploadBase::UPLOAD_VERIFICATION_ERROR :
                header('HTTP/1.0 403 Bad Request');
                echo('<html><body>');
                echo('<p>The uploaded file did not pass server verification:</p>');
                $this->echoDetails($details['error']);
                echo('</body></html>');
                break;

            default :
                header('HTTP/1.0 500 Internal Server Error');
                echo('<html><body>Function UploadForm:internalProcessUpload returned an unknown code: ' . print_r($outcome, true) . '.</body></html>');
                break;
        }

        // Выключение протоколирования ошибок
        error_reporting(0);

        exit();
    }

    function echoDetails($msg) {
        if (is_array($msg)) {
            foreach ($msg as $submsg) {
                $this->echoDetails($submsg);
            }
        } else {
            echo('</p>'.$msg.'</p>');
        }
    }
}
?>