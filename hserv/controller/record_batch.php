<?php

    /**
    * Application interface. See HRecordMgr in hapi.js
    * Add/replace/delete details in batch
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Artem Osmakov   <osmakov@gmail.com>
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

    require_once dirname(__FILE__).'/../../autoload.php';
    require_once dirname(__FILE__).'/../records/edit/recordsBatch.php';

    $response = array();
    $res = false;

    $system = new hserv\System();
    if( ! $system->init(@$_REQUEST['db']) ){
        //get error and response
        $response = $system->getError();

    }else {

        set_time_limit(0);

        $dbRecDetails = new RecordsBatch($system, $_REQUEST);


        if(is_array(@$_REQUEST['actions'])){

            $res = $dbRecDetails->multiAction();

        }elseif(@$_REQUEST['a'] == 'add'){

            $res = $dbRecDetails->detailsAdd();

        }elseif(@$_REQUEST['a'] == 'replace'){

            $res = $dbRecDetails->detailsReplace();

        }elseif(@$_REQUEST['a'] == 'addreplace'){

                $res = $dbRecDetails->detailsReplace();
                if(is_array($res) && @$res['passed']==1 && @$res['undefined']==1){
                    //detail not found - add new one
                    $res = $dbRecDetails->detailsAdd();
                }

        }elseif(@$_REQUEST['a'] == 'delete'){

            $res = $dbRecDetails->detailsDelete();

        }elseif(@$_REQUEST['a'] == 'add_reverse_pointer_for_child'){

            $res = $dbRecDetails->addRevercePointerForChild();

        }elseif(@$_REQUEST['a'] == 'add_links_by_matching'){

            $res = $dbRecDetails->createRecordLinksByMatching();


        }elseif(@$_REQUEST['a'] == 'rectype_change'){

            $res = $dbRecDetails->changeRecordTypeInBatch();

        }elseif(@$_REQUEST['a'] == 'extract_pdf'){

            $res = $dbRecDetails->extractPDF();

        }elseif(@$_REQUEST['a'] == 'url_to_file'){

            $res = $dbRecDetails->changeUrlToFileInBatch();

        }elseif(@$_REQUEST['a'] == 'local_to_repository'){
            // load several  files (linked to set of records) ext.repository - from recordAction
            // see also upload_file_nakala in usr_info
            $res = $dbRecDetails->uploadFileToRepository();

        }elseif(@$_REQUEST['a'] == 'reset_thumbs'){

            $res = $dbRecDetails->resetThumbnails();

        }elseif(@$_REQUEST['a'] == 'create_sub_records'){

            $res = $dbRecDetails->createSubRecords();

        }elseif(@$_REQUEST['a'] == 'case_conversion'){

            $res = $dbRecDetails->caseConversion();

        }elseif(@$_REQUEST['a'] == 'nl2br'){

            $res = $dbRecDetails->nl2brConversion();

        }elseif(@$_REQUEST['a'] == 'translation'){

            $res = $dbRecDetails->fieldTranslation();

        }else {

            $system->addError(HEURIST_INVALID_REQUEST, "Type of request not defined or not allowed");
        }

        $dbRecDetails->removeSession();

        $system->dbclose();
    }


    if( is_bool($res) && !$res ){
        $response = $system->getError();
    }else{
        $response = array("status"=>HEURIST_OK, "data"=> $res);
    }

    $system->setResponseHeader();//UTF-8?? apparently need to remove
    print json_encode($response);
    exit;
?>