<?php
namespace hserv\entity;
use hserv\entity\DbEntityBase;
use hserv\utilities\UArchive;
use hserv\utilities\USanitize;
use hserv\utilities\DbUtils;

    /**
    * db access to recUploadedFiles table
    *
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

require_once dirname(__FILE__).'/../records/search/recordFile.php';
require_once dirname(__FILE__).'/../records/edit/recordModify.php';
require_once dirname(__FILE__).'/../../import/fieldhelper/harvestLib.php';

/**
* some public methods
*
*   registerImage - saves encoded image data as file and register it
*   registerFile - uses getFileInfoForReg to get file info
*   registerURL - register url: retrieves MimeExt
*
*/
class DbRecUploadedFiles extends DbEntityBase
{
    private $error_ext;
    
    //
    // constructor - load configuration from json file
    //
    public function __construct( $system, $data=null ) {

       parent::__construct( $system, $data );

       $this->error_ext = 'Error inserting file metadata or unable to recognise uploaded file format. '
.'This generally means that the mime type for this file has not been defined for this database (common mime types are defined by default). '
.'Please add mime type from Admin > Manage files > Define mime types. '
.'Otherwise please '.CONTACT_SYSADMIN.' or '.CONTACT_HEURIST_TEAM.'.';
    }

    /**
    *  search uploaded fils
    *
    *  other parameters :
    *  details - id|name|list|all or list of table fields
    *  offset
    *  limit
    *  request_id
    *
    *  @todo overwrite
    */
    public function search(){

        if(parent::search()===false){
              return false;
        }

        if(@$this->data['details']=='related_records'){
            return $this->_getRelatedRecords($this->data['ulf_ID'], true);
        }

        //compose WHERE
        $where = array();
        $from_table = array($this->config['tableName']);//'recUploadedFiles'

        $pred = $this->searchMgr->getPredicate('ulf_ID');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('ulf_OrigFileName');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('ulf_Caption');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('ulf_Copyright');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('ulf_Copyowner');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('ulf_ExternalFileReference');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('ulf_FilePath');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('ulf_Modified');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('ulf_UploaderUGrpID');
        if($pred!=null) {array_push($where, $pred);}


        $value = @$this->data['fxm_MimeType'];
        $needMimeType = !($value==null || $value=='any');
        if($needMimeType){
            array_push($where, "(fxm_MimeType like '$value%')");
        }
        if($needMimeType || @$this->data['details']=='full' || @$this->data['details']=='list'){
            array_push($where, "(fxm_Extension=ulf_MimeExt)");
            array_push($from_table, 'defFileExtToMimetype');
        }
        //----- order by ------------

        //compose ORDER BY
        $order = array();

        //$pred = $this->searchMgr->getSortPredicate('ulf_UploaderUGrpID');
        //if($pred!=null) {array_push($order, $pred);}
        $value = @$this->data['sort:ulf_Added'];
        if($value!=null){
            array_push($order, 'ulf_Added '.($value>0?'ASC':'DESC'));
        }else{
            $value = @$this->data['sort:ulf_FileSizeKB'];
            if($value!=null){
                array_push($order, 'ulf_FileSizeKB '.($value>0?'ASC':'DESC'));
            }else{
                array_push($order, 'ulf_OrigFileName ASC');
            }
        }


        $needRelations = false;
        $needCheck = false;
        $needCalcFields = false;
        $calculatedFields = null;

        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){

            $this->data['details'] = 'ulf_ID';

        }elseif(@$this->data['details']=='name'){

            $this->data['details'] = 'ulf_ID,ulf_OrigFileName';

        }elseif(@$this->data['details']=='list'){

            //$this->data['details'] = 'ulf_ID,ulf_OrigFileName,ulf_ExternalFileReference, ulf_ObfuscatedFileID';
            $this->data['details'] = 'ulf_ID,ulf_OrigFileName,ulf_ExternalFileReference,ulf_ObfuscatedFileID,ulf_FilePath,fxm_MimeType,ulf_PreferredSource,ulf_FileSizeKB';
            $needCalcFields = true;

        }elseif(@$this->data['details']=='full'){

            $this->data['details'] = 'ulf_ID,ulf_OrigFileName,ulf_ExternalFileReference,ulf_ObfuscatedFileID,ulf_Caption,ulf_Description,ulf_Copyright,ulf_Copyowner,ulf_FileSizeKB,ulf_MimeExt,ulf_Added,ulf_UploaderUGrpID,fxm_MimeType,ulf_PreferredSource';
            //$this->data['details'] = implode(',', $this->fields );
            $needRelations = true;
            $needCalcFields = true;
        }else{
            $needCheck = true;
        }

        if(!is_array($this->data['details'])){ //specific list of fields
            $this->data['details'] = explode(',', $this->data['details']);
        }

        //validate names of fields
        //validate names of fields
        if($needCheck && !$this->_validateFieldsForSearch()){
            return false;
        }

        $is_ids_only = (count($this->data['details'])==1);

        if($needCalcFields){
            //compose player html tag
            $calculatedFields = function ($fields, $row=null) {

                    if($row==null){
                        array_push($fields, 'ulf_PlayerTag');
                        return $fields;
                    }else{

                        $idx = array_search('ulf_ObfuscatedFileID', $fields);
                        if($idx!==false){
                            $fileid = $row[$idx];
                            $mimeType=null;
                            $external_url=null;
                            $idx = array_search('fxm_MimeType', $fields);
                            if($idx!==false) {$mimeType = $row[$idx];}
                            $idx = array_search('ulf_ExternalFileReference', $fields);
                            if($idx!==false) {$external_url = $row[$idx];}
                            array_push($row, fileGetPlayerTag($this->system, $fileid, $mimeType, null, $external_url));//add ulf_PlayerTag
                        }else{
                            array_push($row, '');
                        }

                        return $row;
                    }
            };
        }

        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS  '.implode(',', $this->data['details'])
        .' FROM '.implode(',', $from_table);

         if(count($where)>0){
            $query = $query.' WHERE '.implode(' AND ',$where);
         }
         if(count($order)>0){
            $query = $query.' ORDER BY '.implode(',',$order);
         }

         $query = $query.$this->searchMgr->getLimit().$this->searchMgr->getOffset();


         $result = $this->searchMgr->execute($query, $is_ids_only, $this->config['tableName'], $calculatedFields);

        //find related records
        if($needRelations && !(is_bool($result) && $result==false) && count($result['order'])>0 ){

            $result['relations'] = $this->_getRelatedRecords($result['order'], false);
            if(!$result['relations']){
                return false;
            }


        }//end find related records

        return $result;

    }

    //
    //
    //
    private function _getRelatedRecords($ulf_IDs, $ids_only){

            $ulf_IDs = prepareIds($ulf_IDs);

            if(count($ulf_IDs)==0){
                $res = false;
                $query = ': file ids are not defined';
            }else{

                if(count($ulf_IDs)>1){
                    $s = ' in ('.implode(',',$ulf_IDs).')';
                }else{
                    $s = '='.intval($ulf_IDs[0]);
                }

                $mysqli = $this->system->get_mysqli();

                //find all related records (that refer to this file)
                if($ids_only){
                    $query = 'SELECT dtl_RecID FROM recDetails WHERE dtl_UploadedFileID '.$s;

                    $res = mysql__select_list2($mysqli, $query);

                }else{
                    $query = 'SELECT dtl_UploadedFileID, dtl_RecID, dtl_ID, rec_Title, rec_RecTypeID '
                            .'FROM recDetails, Records WHERE dtl_UploadedFileID '.$s.' and dtl_RecID=rec_ID';
                    $direct = array();
                    $headers = array();

                    $res = $mysqli->query($query);
                    if ($res){
                            while ($row = $res->fetch_row()) {
                                $relation = new \stdClass();
                                $relation->recID = intval($row[0]);//file id
                                $relation->targetID = intval($row[1]);//record id
                                $relation->dtID  = intval($row[2]);
                                array_push($direct, $relation);
                                $headers[$row[1]] = array($row[3], $row[4]);
                            }
                            $res->close();

                            $res = array("direct"=>$direct, "headers"=>$headers);
                    }
                }
            }

            if($res===null || $res===false){
                    $this->system->addError(HEURIST_DB_ERROR,
                        'Search query error for records that use files. Query '.$query,
                        $mysqli->error);
                    return false;
            }else{
                    return $res;
            }
    }

    //
    //
    //
    protected function _validatePermission(){

        if(!$this->system->is_dbowner() && is_array($this->recordIDs) && count($this->recordIDs)>0){

            $ugr_ID = $this->system->get_user_id();

            $mysqli = $this->system->get_mysqli();

            $recIDs_norights = mysql__select_list($mysqli, $this->config['tableName'], $this->primaryField,
                    $this->primaryField.' in ('.implode(',', $this->recordIDs).') AND ulf_UploaderUGrpID != '.$ugr_ID.')');

            $cnt = is_array($recIDs_norights)?count($recIDs_norights):0;

            if($cnt>0){
                $this->system->addError(HEURIST_ACTION_BLOCKED,
                (($cnt==1 && (!is_array($this->records) || count($this->records)==1) )
                    ? 'File is'
                    : $cnt.' files are')
                    .' uploaded by other user. Insufficient rights (logout/in to refresh) for this operation');
                return false;
            }
        }

        return true;

    }

    protected function _validateValues(){

        $ret = parent::_validateValues();

        if($ret){

            $fieldvalues = $this->data['fields'];//current record

            /*
            if(!@$fieldvalues['ulf_OrigFileName']){
                $this->system->addError(HEURIST_INVALID_REQUEST, "Name of file not defined");
                return false;
            }
            if (!@$fieldvalues['ulf_ExternalFileReference']){

                if(!(@$fieldvalues['ulf_FilePath'] && @$fieldvalues['ulf_FileName'])){
                    $this->system->addError(HEURIST_INVALID_REQUEST, "Path or link to file not defined");
                    return false;
                }else{
                }
            }
            */

            $mimetypeExt = strtolower($fieldvalues['ulf_MimeExt']);
            $mimeType = mysql__select_value($this->system->get_mysqli(),
                    'select fxm_Mimetype from defFileExtToMimetype where fxm_Extension="'.addslashes($mimetypeExt).'"');

            if(!$mimeType){
                    $this->system->addError(HEURIST_ACTION_BLOCKED, 'Extension: '.$mimetypeExt.'<br> '.$this->error_ext);
                    return false;
            }

            if($fieldvalues['ulf_FileSizeKB']<0 || !is_numeric($fieldvalues['ulf_FileSizeKB'])){
                    $this->system->addError(HEURIST_ACTION_BLOCKED, 'Invalid file size value: '.$fieldvalues['ulf_FileSizeKB']);
                    return false;
            }
        }

        return $ret;
    }


    //
    //
    //
    protected function prepareRecords(){

        $ret = parent::prepareRecords();

        //add specific field values
        foreach($this->records as $idx=>$record){

            $rec_ID = intval(@$record[$this->primaryField]);
            $isinsert = ($rec_ID<1);

            $mimeType = strtolower($this->records[$idx]['ulf_MimeExt']);

            if(@$record['ulf_ExternalFileReference']){

                if(strpos(@$this->records[$idx]['ulf_OrigFileName'],'_tiled')!==0 &&
                   strpos(@$this->records[$idx]['ulf_OrigFileName'],'_iiif')!==0 &&
                   strpos(@$this->records[$idx]['ulf_PreferredSource'],'iiif')!==0 &&
                   strpos(@$this->records[$idx]['ulf_PreferredSource'],'tiled')!==0)
                {

                    /*(strpos($record['ulf_ExternalFileReference'], 'iiif')!==false
                    || strpos($record['ulf_ExternalFileReference'], 'manifest.json')!==false
                    || strpos($record['ulf_ExternalFileReference'], 'info.json')!==false) */
                    //check iiif - either manifest of image
                    if($mimeType=='json' || $mimeType=='application/json'|| $mimeType=='application/ld+json'){
/*
We can register either info.json (reference to local or remote IIIF server that describes particular IIIF image) or manifest.json (that describes set of media and their appearance).

On registration if mime type is application/json we loads this file and check whether it is image info or manifest. For former case we store in ulf_OrigFileName “iiif_image”, for latter one “iiif”.

When we open "iiif_image" in mirador viewer we generate manifest dynamically.
@see miradorViewer.php
*/


                        //verify that url points to iiif manifest
                        $iiif_manifest = loadRemoteURLContent($record['ulf_ExternalFileReference']);//check that json is iiif manifest
                        $iiif_manifest = json_decode($iiif_manifest, true);
                        if($iiif_manifest!==false && is_array($iiif_manifest))
                        {
                            if(@$iiif_manifest['@type']=='sc:Manifest' ||   //v2
                                @$iiif_manifest['type']=='Manifest')        //v3
                            {
                                //take label, description, thumbnail
                                //@$iiif_manifest['label'];

                                if(!@$record['ulf_Description'] && @$iiif_manifest['description']){
                                    $desc = $iiif_manifest['description'];
                                    if(is_array($desc)){ //multilang desc
                                        $desc = array_shift($desc);
                                        $desc = @$desc['@value'];
                                    }
                                    if($desc){
                                        $this->records[$idx]['ulf_Description'] = $desc;
                                    }
                                }
                                if(@$iiif_manifest['thumbnail']){

                                    if(@$iiif_manifest['thumbnail']['@id']){  //v2
                                        $this->records[$idx]['ulf_TempThumbUrl'] = @$iiif_manifest['thumbnail']['@id'];
                                    }elseif(@$iiif_manifest['thumbnail']['id']){  //v3
                                        $this->records[$idx]['ulf_TempThumbUrl'] = @$iiif_manifest['thumbnail']['id'];
                                    }
                                }else{
                                    //sequences -> canvases[0] -> images[0] -> resource -> @id or service -> @id

                                    $thumb_url = @$iiif_manifest['sequences'][0]['canvases'][0];
                                    if($thumb_url){

                                        if(@$thumb_url['thumbnail']['@id']){
                                            $thumb_url = @$thumb_url['thumbnail']['@id'];
                                        }else{
                                            if(@$thumb_url['images'][0]['resource']['service']['@id']){
                                                $image_url = $thumb_url['images'][0]['resource']['service']['@id'];
                                            }else{
                                                $image_url = @$thumb_url['images'][0]['resource']['@id'];
                                            }

                                            if($image_url!=null){
                                                $thumb_url = $this->_composeThumbnailIIIF(
                                                    $image_url,
                                                    @$thumb_url['images'][0]['resource']['width'],
                                                    @$thumb_url['images'][0]['resource']['height']
                                                                );

                                                $this->records[$idx]['ulf_TempThumbUrl'] = $thumb_url;
                                            }
                                        }

                                    }


                                }

                                //if(!@$this->records[$idx]['ulf_OrigFileName']){
                                $this->records[$idx]['ulf_OrigFileName'] = '_iiif';
                                //}
                                $this->records[$idx]['ulf_PreferredSource'] = 'iiif';
                                $mimeType = 'json';
                                $this->records[$idx]['ulf_MimeExt'] = 'json';

                            }elseif(@$iiif_manifest['@context'] && (@$iiif_manifest['@id'] || @$iiif_manifest['id'])
                                    && substr($record['ulf_ExternalFileReference'], 0, -9) == 'info.json' )
                            {   //IIIF image

                                //create url for thumbnail
                                $thumb_url = $record['ulf_ExternalFileReference'];
                                //remove info.json
                                $thumb_url = substr($thumb_url, 0, -9).'full/full/0/default.jpg';
                                $thumb_url = $this->_composeThumbnailIIIF($thumb_url,
                                            @$iiif_manifest['width'],
                                            @$iiif_manifest['height']);

                                $this->records[$idx]['ulf_TempThumbUrl'] = $thumb_url;

                                //if(!@$this->records[$idx]['ulf_OrigFileName']){
                                $this->records[$idx]['ulf_OrigFileName'] = '_iiif_image';
                                //}
                                $this->records[$idx]['ulf_PreferredSource'] = 'iiif_image';
                                $mimeType = 'json';
                                $this->records[$idx]['ulf_MimeExt'] = 'json';
                            }

                        }

                    }

                    if(!$this->records[$idx]['ulf_OrigFileName']){
                        $this->records[$idx]['ulf_OrigFileName'] = '_remote';
                    }
                    if(!@$this->records[$idx]['ulf_PreferredSource']){
                        $this->records[$idx]['ulf_PreferredSource'] = 'external';
                    }
                }
            }else{
                $this->records[$idx]['ulf_PreferredSource'] = 'local';

                if(@$record['ulf_FileUpload']){

                    $fields_for_reg = $this->getFileInfoForReg($record['ulf_FileUpload'], null);//thumbnail is created here
                    if(is_array($fields_for_reg)){
                        $this->records[$idx] = array_merge($this->records[$idx], $fields_for_reg);
                    }
                }
            }

            if($isinsert){
                $this->records[$idx]['ulf_UploaderUGrpID'] = $this->system->get_user_id();
                $this->records[$idx]['ulf_Added'] = date(DATE_8601);
            }else{
                //do not change these params on update
                if(@$this->records[$idx]['ulf_FilePath']=='') {unset($this->records[$idx]['ulf_FilePath']);}
            }
            if(@$this->records[$idx]['ulf_FileName']=='') {unset($this->records[$idx]['ulf_FileName']);}

            if(@$record['ulf_ExternalFileReference']==null || @$record['ulf_ExternalFileReference']==''){
                $this->records[$idx]['ulf_ExternalFileReference'] = null;
                unset($this->records[$idx]['ulf_ExternalFileReference']);
            }

            //change mimetype to extension
            if($mimeType==''){
                $mimeType = 'dat';
                $this->records[$idx]['ulf_MimeExt'] = 'dat';
                /*
                    $this->system->addError(HEURIST_ACTION_BLOCKED, $this->error_ext);
                    return false;
                */
            }
            if(strpos($mimeType,'/')>0){ //this is mimetype - find extension

                $mysqli = $this->system->get_mysqli();

                $query = 'select fxm_Extension from defFileExtToMimetype where fxm_Mimetype="'.
                $mysqli->real_escape_string($mimeType).'"';

                if($mimeType=='application/x-zip-compressed'){
                    $query = $query.' OR fxm_Mimetype="application/zip"';//backward capability
                }

                $fileExtension = mysql__select_value($mysqli, $query);

                if($fileExtension==null &&
                    $this->records[$idx]['ulf_PreferredSource']=='local')
                    //$this->records[$idx]['ulf_OrigFileName'] != '_remote' &&
                    //strpos($this->records[$idx]['ulf_OrigFileName'],'_tiled')!==0)
                {
                    //mimetype not found - try to get extension from name
                    $extension = strtolower(pathinfo($this->records[$idx]['ulf_OrigFileName'], PATHINFO_EXTENSION));
                    if($extension){
                        $fileExtension = mysql__select_value($mysqli,
                            'select fxm_Extension from defFileExtToMimetype where fxm_Extension="'.addslashes($extension).'"');
                        if($fileExtension==null){
                            //still not found
                            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Neither mimetype: '.$mimeType
                                    .' nor extension '.$extension.' are registered in database.<br><br>'.$this->error_ext);
                            return false;
                        }
                    }
                }
                if($fileExtension==null){
                    $this->system->addError(HEURIST_ACTION_BLOCKED, 'File mimetype is detected as: '.$mimeType
                        .'. It is not registered in database.<br><br>'.$this->error_ext);
                    return false;
                }
                $this->records[$idx]['ulf_MimeExt'] = $fileExtension;
            }

            if(!@$this->records[$idx]['ulf_FileSizeKB']) {
                $this->records[$idx]['ulf_FileSizeKB'] = 0;
            }

            //$this->records[$idx] = $record;
/*
                'ulf_MimeExt ' => array_key_exists('ext', $filedata)?$filedata['ext']:NULL,
                'ulf_FileSizeKB' => 0,
*/
        }

        return $ret;

    }

    //
    //
    //
    private function _composeThumbnailIIIF($image_url, $width, $height)
    {
        $x = intval($width);
        $y = intval($height);
        if(!($x>0)){
            $x = 200;
        }
        if(!($y>0)){
            $y = 200;
        }

        $rx = 200 / $x;
        $ry = 200 / $y;

        $scale = $rx ? ($ry ? min($rx, $ry) : $rx) : $ry;

        if ($scale > 1) { //no enlarge
            $scale = 1;
        }

        $new_x = ceil($x * $scale);
        $new_y = ceil($y * $scale);

        //https://gallica.bnf.fr/iiif/ark:/12148/bpt6k9604118j/f25/full/90,120/0/default.jpg
        //https://fragmentarium.ms/metadata/iiif/F-hsd6/manifest.json  or info.json
        //https://purl.stanford.edu/sn904cj3429/iiif/manifest
        //https://fragmentarium.ms:443/loris/F-hsd6/fol_2r.jp2/full/full/0/default.jpg

        if(strpos($image_url,'/full/full/')>0){
            $thumb_url = str_replace('/full/full/', '/full/'.$new_x.','.$new_y.'/', $image_url);
        }else{
            $thumb_url = $image_url.'/full/'.$new_x.','.$new_y.'/0/default.jpg';
        }

        return $thumb_url;
    }


    // there are 3 ways
    // 1) add for local files - via register
    // 2) remote - save as usual and define ulf_ObfuscatedFileID and ulf_FileName
    // 3) update just parent:save
    //
    public function save(){

        $ret = parent::save();
/*
        if($ret!==false){
            //treat thumbnail image
            foreach($this->records as $record){
                if(in_array(@$record['trm_ID'], $ret)){
                    $thumb_file_name = @$record['trm_Thumb'];

                    //rename it to recID.png
                    if($thumb_file_name){
                        parent::renameEntityImage($thumb_file_name, $record['trm_ID']);
                    }
                }
            }
        }
*/
        if($ret!==false){
        foreach($this->records as $rec_idx => $record){

            if(!@$record['ulf_ObfuscatedFileID']){ //define obfuscation

                $ulf_ID = $record['ulf_ID'];

                if($ulf_ID>0){
                    $nonce = addslashes(sha1($ulf_ID.'.'.random_int(0,99)));

                    $file2 = array();
                    $file2['ulf_ID'] = $ulf_ID;
                    $file2['ulf_ObfuscatedFileID'] = $nonce;

                    if(strpos($record['ulf_OrigFileName'],'_tiled')===0 || $record['ulf_PreferredSource']=='tiled')
                    {
                        if(!@$record['ulf_ExternalFileReference']){
                            if($record['ulf_MimeExt']=='mbtiles'){
                                $file2['ulf_ExternalFileReference'] = substr($record['ulf_OrigFileName'],7).'.mbtiles';
                            }else{
                                $file2['ulf_ExternalFileReference'] = $ulf_ID.'/';//HEURIST_TILESTACKS_URL.
                            }
                        }
                        $file2['ulf_FilePath'] = '';

                    }else
                    if(!@$record['ulf_ExternalFileReference'] && !@$record['ulf_FileName'])
                    {
                        $this->records[$rec_idx]['ulf_FileName'] = 'ulf_'.$ulf_ID.'_'.$record['ulf_OrigFileName'];
                        $file2['ulf_FileName'] = $this->records[$rec_idx]['ulf_FileName'];
                    }

                    $res = mysql__insertupdate($this->system->get_mysqli(), $this->config['tableName'], 'ulf', $file2);

                    if($res>0){
                        $this->records[$rec_idx]['ulf_ObfuscatedFileID'] = $nonce;
    //                    $this->records[$rec_idx]['ulf_ID'] = $res;
                    }
                }
            }

            if( (strpos($record['ulf_OrigFileName'],'_iiif')===0  || strpos($record['ulf_PreferredSource'],'iiif')===0)
                && @$record['ulf_TempThumbUrl']){

                    $thumb_name = HEURIST_THUMB_DIR.'ulf_'.$this->records[$rec_idx]['ulf_ObfuscatedFileID'].'.png';
                    $temp_path = tempnam(HEURIST_SCRATCH_DIR, "_temp_");
                    if(saveURLasFile($record['ulf_TempThumbUrl'], $temp_path)){ //save to temp in scratch folder
                        UImage::createScaledImageFile($temp_path, $thumb_name);//create thumbnail for iiif image
                        unlink($temp_path);
                    }
            }else
            //if there is file to be copied
            if(@$this->records[$rec_idx]['ulf_TempFile']){

                $ulf_ID = $this->records[$rec_idx]['ulf_ID'];
                $ulf_ObfuscatedFileID = $this->records[$rec_idx]['ulf_ObfuscatedFileID'];

                //copy temp file from scratch to fileupload folder
                $tmp_name = $this->records[$rec_idx]['ulf_TempFile'];

                if(strpos($record['ulf_OrigFileName'],'_tiled')===0  || $record['ulf_PreferredSource']=='tiled')
                {
                    if($record['ulf_MimeExt']=='mbtiles'){

                        $new_name = substr($record['ulf_OrigFileName'],7).'.mbtiles';

                        if( copy($tmp_name, HEURIST_TILESTACKS_DIR.$new_name) )
                        {
                            //remove temp file
                            unlink($tmp_name);

                            //create thumbnail
                            $thumb_name = HEURIST_THUMB_DIR.'ulf_'.$ulf_ObfuscatedFileID.'.png';
                            //UImage::createScaledImageFile($filename, $thumb_name);
                            $img = UImage::createFromString('tileserver tiled images');
                            imagepng($img, $thumb_name);//save into file
                            imagedestroy($img);


                        }else{
                            $this->system->addError(HEURIST_INVALID_REQUEST,
                                    "Upload file: $new_name couldn't be saved to upload path definied for db = "
                                . $this->system->dbname().' ('.HEURIST_TILESTACKS_DIR
                                .'). Please ask your system administrator to correct the path and/or permissions for this directory');
                        }

                    }else{

                        //create destination folder
                        $dest = HEURIST_TILESTACKS_DIR.$ulf_ID.'/';
                        $warn = folderCreate2($dest, '');

                        //unzip archive to HEURIST_TILESTACKS_DIR
                        $unzip_error = null;
                        try{
                            UArchive::unzip($this->system, $tmp_name, $dest);
                        } catch (\Exception  $e) {
                            $unzip_error = $e->getMessage();
                        }

                        if($unzip_error==null){
                            //remove temp file
                            unlink($tmp_name);

                            $file2 = array();

                            //detect 1) mimetype 2) summary size of stack images 3) copy first image as thumbnail
                            $size = folderSize2($dest);

                            //get first file from first folder - use it as thumbnail
                            $filename = folderFirstFile($dest);

                            $thumb_name = HEURIST_THUMB_DIR.'ulf_'.$ulf_ObfuscatedFileID.'.png';

                            $mimeExt = UImage::getImageType($filename);

                            if($mimeExt){
                                UImage::createScaledImageFile($filename, $thumb_name);//create thumbnail for tiled image
                                $file2['ulf_MimeExt'] = $mimeExt;
                            }else{
                                $file2['ulf_MimeExt'] = 'png';
                            }

                            $file2['ulf_ID'] = $ulf_ID;
                            $file2['ulf_FileSizeKB'] = $size/1024;

                            mysql__insertupdate($this->system->get_mysqli(), $this->config['tableName'], 'ulf', $file2);


                        }else{
                            $this->system->addError(HEURIST_ERROR,
                                    'Can\'t extract tiled images stack. It couldn\'t be saved to upload path definied for db = '
                                . $this->system->dbname().' ('.$dest
                                .'). Please ask your system administrator to correct the path and/or permissions for this directory', $unzip_error);
                            return false;
                        }

                    }

                }else{

                    $new_name = $this->records[$rec_idx]['ulf_FileName'];

                    if( copy($tmp_name, HEURIST_FILES_DIR.$new_name) )
                    {
                        //remove temp file
                        unlink($tmp_name);

                        //copy thumbnail
                        if(@$record['ulf_TempFileThumb']){
                            $thumb_name = HEURIST_SCRATCH_DIR.'thumbs/'.$record['ulf_TempFileThumb'];
                            if(file_exists($thumb_name)){
                                $new_name = HEURIST_THUMB_DIR.'ulf_'.$ulf_ObfuscatedFileID.'.png';
                                copy($thumb_name, $new_name);
                                //remove temp file
                                unlink($thumb_name);
                            }
                        }

                    }else{
                        $this->system->addError(HEURIST_INVALID_REQUEST,
                                "Upload file: $new_name couldn't be saved to upload path definied for db = "
                            . $this->system->dbname().' ('.HEURIST_FILES_DIR
                            .'). Please ask your system administrator to correct the path and/or permissions for this directory');
                    }
                }
            }
        }//after save loop
        }
        return $ret;
    }

    //   Actions:
    //   register URL/Path in batch
    //   optionally: download URL and register locally
    //
    //    csv_import (with optional is_download)
    //    delete_selected
    //    regExternalFiles (with optional is_download)
    //    merge_duplicates
    //    get_media_records
    //    create_media_records
    //    bulk_reg_filestore  
    //    import_data
    public function batch_action(){

        $mysqli = $this->system->get_mysqli();

        $this->need_transaction = false;
        $keep_autocommit = mysql__begin_transaction($mysqli);

        $ret = true;
        $is_csv_import = false;
        $cnt_skipped = 0;
        $cnt_imported = 0;
        $cnt_error = 0;
        $is_download = (@$this->data['is_download']==1);

        if($is_download){
            ini_set('max_execution_time', '0');
        }

        if(@$this->data['csv_import']){ // import new media via CSV. See importMedia.js

            $is_csv_import = true;

            if(@$this->data['fields'] && is_string($this->data['fields'])){ // new to perform extra validations first
                $this->data['fields'] = json_decode($this->data['fields'], true);
            }

            if(is_array($this->data['fields']) && count($this->data['fields'])>0){

                set_time_limit(0);

                foreach($this->data['fields'] as $idx => $record){

                    $is_url = false;
                    //url or relative path
                    $url = trim($record['ulf_ExternalFileReference']);
                    $description = @$record['ulf_Description'];
                    $caption = @$record['ulf_Caption'];
                    $copyright = @$record['ulf_Copyright'];
                    $copyowner = @$record['ulf_Copyowner'];

                    if(strpos($url,'http')===0){
                        //find if url is already registered
                        $is_url = true;
                        $file_query = 'SELECT ulf_ID FROM recUploadedFiles WHERE ulf_ExternalFileReference="'
                        .$mysqli->real_escape_string($url).'"';

                    }else{

                        $k = strpos($url,'uploaded_files/');
                        if($k===false) {$k = strpos($url,'file_uploads/');}

                        if($k===0 || $k===1){
                            //relative path in database folder
                            $filename = HEURIST_FILESTORE_DIR.$url;
                            if(file_exists($url)){
                                //this methods checks if file is already registered
                                $fres = fileRegister($this->system, $filename, $description);//see recordFile.php
                            }
                        }else {
                            $file_query = 'SELECT ulf_ID FROM recUploadedFiles WHERE ulf_ObfuscatedFileID="'
                            .$mysqli->real_escape_string($url).'"';
                        }
                    }

                    if($file_query){
                        $fres = mysql__select_value($mysqli, $file_query);
                    }

                                if($fres>0){
                                    $ulf_ID = $fres;
                                    $cnt_skipped++;

                                }elseif($is_url) {

                                    $fields = array(
                                        'ulf_Caption'=>$caption,
                                        'ulf_Copyright'=>$copyright,
                                        'ulf_Copyowner'=>$copyowner,
                                        'ulf_Description'=>$description,
                                        'ulf_MimeExt'=>getURLExtension($url));

                                    if($is_download){
                                        //download and register , last parameter - validate name and hash
                                        $ulf_ID = $this->downloadAndRegisterdURL($url, $fields, 2);//it returns ulf_ID
                                    }else{
                                        $ulf_ID = $this->registerURL( $url, false, 0, $fields);
                                    }

                                    if($ulf_ID>0){
                                        $cnt_imported++;
                                    }else {
                                        $cnt_error++;
                                    }
                                }

                } //foreach

            }else{
                $this->system->addError(HEURIST_ACTION_BLOCKED, 'No import data has been provided. Ensure that you have enter the necessary CSV rows.<br>Please contact the Heurist team if this problem persists.');
            }
        }
        elseif(@$this->data['delete_selected']){ // delete file records not in use
            
            $ret = $this->deleteSelected();
            
        }
        elseif(@$this->data['regExternalFiles']){ // attempt to register multiple URLs at once, and return necessary information for record editor

            $rec_fields = $this->data['regExternalFiles'];

            if(!empty($rec_fields) && is_string($rec_fields)){
                $rec_fields = json_decode($rec_fields, TRUE);
            }

            if(!empty($rec_fields)){

                $results = array();

                foreach ($rec_fields as $dt_id => $urls) {

                    if(!array_key_exists($dt_id, $results)){
                        $results[$dt_id] = array();
                    }

                    if(is_array($urls)){

                        foreach ($urls as $idx => $url) {

                            if(strpos($url, 'http') === 0){
                                $query = 'SELECT ulf_ID FROM recUploadedFiles WHERE ulf_ExternalFileReference = "' . $mysqli->real_escape_string($url) . '"';
                                $file_id = mysql__select_value($mysqli, $query);

                                if(!$file_id){ // new external file to save
                                    $file_id = $this->registerURL($url);
                                }

                                if($file_id > 0){ // retrieve file Obfuscated ID
                                    $query = 'SELECT ulf_ObfuscatedFileID, ulf_MimeExt FROM recUploadedFiles WHERE ulf_ID = ' . $file_id;
                                    $file_dtls = mysql__select_row($mysqli, $query);

                                    if(!$file_dtls){
                                        $results[$dt_id]['err_id'][$file_id] = $url; // cannot retrieve obfuscated id
                                    }else{
                                        $results[$dt_id][$idx] = array(
                                            'ulf_ID' => $file_id,
                                            'ulf_ExternalFileReference' => $url,
                                            'ulf_ObfuscatedFileID' => $file_dtls[0],
                                            'ulf_MimeExt' => $file_dtls[1],
                                            'ulf_OrigFileName' => '_remote'
                                        );
                                    }
                                }else{
                                    $results[$dt_id]['err_save'][] = $url; // unable to save
                                }
                            }else{
                                $results[$dt_id]['err_invalid'][] = $url; // invalid
                            }
                        }
                    }elseif(is_string($urls) && strpos($urls, 'http') === 0){

                        $query = 'SELECT ulf_ID FROM recUploadedFiles WHERE ulf_ExternalFileReference = "' . $mysqli->real_escape_string($urls) . '"';
                        $file_id = mysql__select_value($mysqli, $query);

                        if(!$file_id){ // new external file to save
                            $file_id = $this->registerURL($urls);
                        }

                        if($file_id > 0){ // retrieve file Obfuscated ID
                            $query = 'SELECT ulf_ObfuscatedFileID, ulf_MimeExt FROM recUploadedFiles WHERE ulf_ID = ' . $file_id;
                            $file_dtls = mysql__select_row($mysqli, $query);

                            if(!$file_dtls){
                                $results[$dt_id]['err_id'] = $urls;
                            }else{
                                $results[$dt_id] = array(
                                    'ulf_ID' => $file_id,
                                    'ulf_ExternalFileReference' => $urls,
                                    'ulf_ObfuscatedFileID' => $file_dtls[0],
                                    'ulf_MimeExt' => $file_dtls[1],
                                    'ulf_OrigFileName' => '_remote'
                                );
                            }
                        }else{
                            $results[$dt_id]['err_save'] = $urls;
                        }
                    }elseif(!empty($urls)){
                        $results[$dt_id]['err_invalid'] = $urls;
                    }
                }

                $ret = $results;
            }
        }
        elseif(@$this->data['merge_duplicates']){ // merge duplicate local + remote files
            $ret = $this->mergeDuplicates();
        }
        elseif(@$this->data['create_media_records']){ // create Multi Media records for files without one
        
            $ret = $this->createMediaRecords();

        }
        elseif(@$this->data['get_media_records']){ // retruns ids of referencing Multi Media records for given files
        
            $ret = $this->getMediaRecords($this->data['get_media_records'], @$this->data['mode'], @$this->data['return']);

        }
        elseif(@$this->data['bulk_reg_filestore']){ // create new file entires

            $error = array();// file missing or other errors
            $skipped = array();// already registered
            $created = array();// total ulf records created
            $exists = 0; // count of files that already exists

            $files = array();

            $dirs_and_exts = getMediaFolders($mysqli);

            if(array_key_exists('files', $this->data) && !empty($this->data['files'])){ // manageFilesUpload.php
                $files = json_decode($this->data['files']);
            }else{ // manageRecUploadedFiles.js

                if(!in_array('file_uploads', $dirs_and_exts['dirs'])){
                    $dirs_and_exts['dirs'][] = 'file_uploads';
                }

                // Get non-registered files
                doHarvest($this->system, $dirs_and_exts, false, 1, ['file_uploads']);
                $files = getRegInfoResult()['nonreg'];
            }

            // Add filestore path
            $dirs_and_exts['dirs'] = array_map(function($dir){
                if(strpos($dir, HEURIST_FILESTORE_DIR) === false){
                    $dir = HEURIST_FILESTORE_DIR . ltrim($dir, '/');
                }
                return rtrim($dir, '/');
            }, $dirs_and_exts['dirs']);

            $system_folders = $this->system->getSystemFolders();
            foreach ($files as $file_details) {

                $file = $file_details;
                if(is_object($file_details)){ // from decoded JS stringified
                    if(property_exists($file_details, 'file_path')){
                        $file = $file_details->file_path;
                    }else{ // not handled
                        $skipped[] = implode(',', $file) . ' => File data is not in valid format';
                        continue;
                    }
                }

                $file = urldecode($file);
                $provided_file = $file;
                if(strpos($file, HEURIST_FILESTORE_URL) === 0){
                    $file = str_replace(HEURIST_FILESTORE_URL, HEURIST_FILESTORE_DIR, $file);
                }else if(strpos($file, HEURIST_FILESTORE_DIR) === false){
                    $file = HEURIST_FILESTORE_DIR . $file;
                }

                if(!file_exists($file)){ // not found, or not in file store
                    $error[] = $provided_file . ' => File does not exist';
                    continue;
                }

                $fileinfo = pathinfo($file);
                $path = $fileinfo['dirname'];
                $name = $fileinfo['basename'];
                $valid_dir = false;

                // Check file directory against set 'upload file' directories
                foreach ($dirs_and_exts['dirs'] as $file_dir) {
                    if(strpos($path, $file_dir) !== false){
                        $valid_dir = true;
                        break;
                    }
                }
                if(!$valid_dir){
                    $skipped[] = $name . ' => File is not located within any set upload directories';
                    continue;
                }

                // Check extension
                if(!in_array(strtolower($fileinfo['extension']), $dirs_and_exts['exts'])){
                    $skipped[] = $name . ' => File extension is not allowed';
                    continue;
                }

                // Check if file is already registered
                if(fileGetByFileName($this->system, $file) > 0){
                    $exists ++;
                    continue;
                }

                $ulf_ID = fileRegister($this->system, $file); //@todo convert this function to method of this class
                if($ulf_ID > 0){
                    $created[] = $name . ' => Registered file as #' . $ulf_ID;
                }else{
                    $msg = $this->system->getError();
                    $error[] = $name . ' => Unable to register file' . (is_array($msg) && array_key_exists('message', $msg) ? ', <br>' . $msg['message'] : '');
                }
            }

            $ret = array();

            if(!empty($created)){
                $ret[] = 'Created:<br>' . implode('<br>', $created);
            }
            if($exists > 0){
                $ret[] = 'Already registered: ' . $exists;
            }
            if(!empty($skipped)){
                $ret[] = 'Skipped:<br>' . implode('<br>', $skipped);
            }
            if(!empty($error)){
                $ret[] = 'Errors:<br>' . implode('<br>', $error);
            }

            $ret = implode('<br><br>', $ret);
        }
        elseif(@$this->data['import_data']){ // importing file metadata

            $import_type = intval($this->data['import_data']);// import type; 1 - keep existing, 2 - append, 3 - replace
            $id_type = @$this->data['id_type'];
            $handled_ids = array('ulf_ID', 'ulf_ObfuscatedFileID', 'ulf_FullPath');// , 'ulf_Checksum'

            if(empty($id_type) || !in_array($id_type, $handled_ids)){
                $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid ID type provided, '. (empty($id_type) ? 'none provided' : 'provided type ' . $id_type));
                return false;
            }
            if($import_type < 1 || $import_type > 3){
                $this->system->addError(HEURIST_INVALID_REQUEST, 'An invalid import type has been provided');
                return false;
            }

            if(@$this->data['fields'] && is_string($this->data['fields'])){ // new to perform extra validations first
                $this->data['fields'] = json_decode($this->data['fields'], true);
            }

            if(is_array($this->data['fields']) && count($this->data['fields'])>0){

                $ret = array();

                foreach($this->data['fields'] as $file_details){

                    if(!is_array($file_details) || count($file_details) < 2){ // invalid row | no details
                        array_push($ret, (!is_array($file_details) ? 'Data is in invalid format' : 'No details provided'));
                        continue;
                    }

                    $id = array_shift($file_details);

                    if(empty($file_details)){ // nothing to process
                        array_push($ret, 'No details to import');
                        continue;
                    }

                    $where_clause = "";
                    if($id_type == 'ulf_ID' || $id_type == 'ulf_ObfuscatedFileID'){

                        if($id_type == 'ulf_ID' && (!is_numeric($id) || intval($id) <= 0)){
                            array_push($ret, "Invalid File ID provided");
                            continue;
                        }elseif($id_type == 'ulf_ObfuscatedFileID' && !preg_match('/^[a-z0-9]+$/', $id)){
                            array_push($ret, "Invalid Obfuscated ID provided");
                            continue;
                        }

                        $id = $id_type == 'ulf_ID' ? intval($id) : $mysqli->real_escape_string($id);
                        $where_clause = "$id_type = '$id'";
                    }elseif($id_type == 'ulf_FullPath'){

                        if(is_numeric($id)){
                            array_push($ret, "Invalid path provided " . htmlspecialchars($id));
                            continue;
                        }

                        $id = ltrim(str_replace(HEURIST_FILESTORE_DIR, '', $id), '\\');

                        $id = $mysqli->real_escape_string($id);
                        $where_clause = "CONCAT(ulf_FilePath, ulf_FileName) = $id";
                    }

                    if(empty($where_clause)){
                        $this->system->addError(HEURIST_ERROR, 'An error occurred with preparing the file id being searched for, where the id is ' . htmlspecialchars($id) . ' typed ' . $id_type);
                        $ret = false;
                        break;
                    }

                    $file_query = "SELECT ulf_ID, ulf_Description, ulf_Caption, ulf_Copyright, ulf_Copyowner FROM recUploadedFiles WHERE $where_clause";
                    $ulf_row = mysql__select_row_assoc($mysqli, $file_query);

                    if(!$ulf_row){
                        array_push($ret, 'An error occurred while trying to retrieve the existing file');
                        continue;
                    }

                    if($import_type != 3){

                        foreach($file_details as $field => $value){

                            if(!empty($ulf_row[$field])){

                                if($import_type == 1){ // retain existing value

                                    unset($file_details[$field]);
                                    continue;
                                }else{ // 2 - append value

                                    $file_details[$field] = $ulf_row[$field] . " ;" . $value;
                                }
                            }
                        }
                    } //else 3 - replace all values

                    if(empty($file_details)){
                        array_push($ret, 'No new details to import');
                        continue;
                    }

                    $file_details['ulf_ID'] = $ulf_row['ulf_ID'];

                    $res = mysql__insertupdate($this->system->get_mysqli(), 'recUploadedFiles', 'ulf', $file_details);

                    if($res != $ulf_row['ulf_ID']){
                        array_push($ret, 'An error occurred while attempting to update file record #' . intval($ulf_row['ulf_ID']));
                    }else{
                        array_push($ret, 'File details updated');
                    }
                }
            }else{
                $this->system->addError(HEURIST_ERROR, 'Data is in invalid format, ' . json_last_error_msg());
                $ret = false;
            }
        }

        if($ret===false){
            $mysqli->rollback();
        }else{
            $mysqli->commit();
        }

        if($keep_autocommit===true) {$mysqli->autocommit(TRUE);}

        if($ret && $is_csv_import){
            $ret = 'Uploaded / registered: '.$cnt_imported.' media resources. ';
            if($cnt_skipped>0){
                $ret = $ret.' Skipped/already exist: '.$cnt_skipped.' media resources';
            }
        }

        return $ret;
    }

    //
    //
    //
    public function delete($keep_uploaded_files=false, $check_referencing=true){ //$disable_foreign_checks = false

        $this->recordIDs = prepareIds($this->data[$this->primaryField]);

        if(count($this->recordIDs)==0){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid set of identificators');
            return false;
        }

        if(!$this->_validatePermission()){
            return false;
        }

        $mysqli = $this->system->get_mysqli();

        if($check_referencing){

            $cnt = mysql__select_value($mysqli, 'SELECT count(dtl_ID) '
                .'FROM recDetails WHERE dtl_UploadedFileID in ('.implode(',', $this->recordIDs).')');

            if($cnt>0){
                $this->system->addError(HEURIST_ACTION_BLOCKED,
                (($cnt==1 && count($this->records)==1)
                    ? 'There is a reference'
                    : 'There are '.$cnt.' references')
                    .' from record(s) to this File.<br>You must delete the records'
                    .' or the File field values in order to be able to delete the file.');
                return false;
            }
        }

        //collect data to remove files
        $query = 'SELECT ulf_ObfuscatedFileID, ulf_FilePath, ulf_FileName FROM recUploadedFiles WHERE ulf_ID in ('
                .implode(',', $this->recordIDs).')';

        $res = $mysqli->query($query);
        if ($res){
            $file_data = array();
            while ($row = $res->fetch_row()){

                $fullpath = null;
                $filename = null;

                if(@$row[1] || @$row[2]){
                    $fullpath = @$row[1] . @$row[2];
                    //add database media storage folder for relative paths
                    $fullpath = resolveFilePath($fullpath);
                    $filename = @$row[2];
                    if(!file_exists($fullpath)){
                        $fullpath=null;
                        $filename = null;
                    }
                }

                $file_data[$row[0]] = array('path' => $fullpath, 'name' => $filename);
            }
            $res->close();
        }


        $mysqli->query('SET foreign_key_checks = 0');
        $ret = $mysqli->query('DELETE FROM '.$this->config['tableName']
                               .' WHERE '.$this->primaryField.' in ('.implode(',',$this->recordIDs).')');
        $mysqli->query('SET foreign_key_checks = 1');

        if(!$ret){
            $this->system->addError(HEURIST_DB_ERROR,
                    "Cannot delete from table ".$this->config['entityName'], $mysqli->error);
            return false;
        }else{

            //remove files and webimagecache
            foreach ($file_data as $file_id=>$file){
                //remove main uploaded file
                if(!$keep_uploaded_files && $file['path']!=null){
                    unlink($file['path']);
                }
                //remove thumbnail
                $thumbnail_file = HEURIST_THUMB_DIR."ulf_".$file_id.".png";
                fileDelete($thumbnail_file);

                // remove web cached image
                $webimage_name = pathinfo($file['name']);
                $webcache_file = HEURIST_FILESTORE_DIR . 'webimagecache/'.$webimage_name['filename'];
                fileDelete($webcache_file.'.jpg');
                fileDelete($webcache_file.'.png');
            }
        }

        return true;
    }


    //
    //  get information for information for uploaded file
    //
    private function getFileInfoForReg($file, $newname){

        if(!is_a($file, 'stdClass')){

            $tmp_thumb = null;

            if(is_array($file)){

                $tmp_name  = $file[0]['name'];//name only
                $newname   = $file[0]['original_name'];
                $tmp_thumb = @$file[0]['thumbnailName'];

            }else{
                $tmp_name  = $file;
            }

            if(!file_exists($tmp_name)){
                $fileinfo = pathinfo($tmp_name);
                if($fileinfo['basename']==$tmp_name){ //only name - by default in scratch folder
                    $tmp_name = HEURIST_SCRATCH_DIR.$tmp_name;
                }
            }

            if(file_exists($tmp_name)){
                $fileinfo = pathinfo($tmp_name);

                $file = new \stdClass();
                //name with ext
                $file->original_name = $newname?$newname:$fileinfo['basename'];//was filename
                $file->name = $file->original_name;
                $file->size = filesize($tmp_name);//fix_integer_overflow
                $file->type = @$fileinfo['extension'];

                $file->thumbnailName = $tmp_thumb;
            }

        }else{
            //uploaded via UploadHandler is in scratch
            if(@$file->fullpath){
                $tmp_name = $file->fullpath;
            }else{
                $tmp_name = HEURIST_SCRATCH_DIR.$file->name;
            }
        }


        $errorMsg = null;
        if(file_exists($tmp_name)){

                $fields = array();
                /* clean up the provided file name -- these characters shouldn't make it through anyway */
                $name = $file->original_name;
                $name = str_replace("\0", '', $name);
                $name = str_replace('\\', '/', $name);
                $name = preg_replace('!.*/!', '', $name);

                $extension = null;
                if($file->type==null || $file->type=='application/octet-stream'){
                    //need to be more specific - try ro save extension
                    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                }

                $ret = array(
                'ulf_OrigFileName' => $name,
                'ulf_MimeExt' => $extension?$extension:$file->type, //extension or mimetype allowed
                'ulf_FileSizeKB' => ($file->size<1024?1:intval($file->size/1024)),
                'ulf_FilePath' => 'file_uploads/',   //relative path to HEURIST_FILESTORE_DIR - db root
                'ulf_TempFile' => $tmp_name);//file in scratch to be copied

                if(isset($file->thumbnailName)){
                    $ret['ulf_TempFileThumb'] = $file->thumbnailName;
                }

        }else{

            /*if(is_a($file,'stdClass')){
                $errorMsg = 'Cant find temporary uploaded file: '.$file->name
                            .' for db = ' . $this->system->dbname().' ('.HEURIST_SCRATCH_DIR
                            .')';
            }else{ */
            $errorMsg = 'Cant find file to be registred : '.$tmp_name
                           .' for db = ' . $this->system->dbname();

            $errorMsg = $errorMsg
                    .'. Please ask your system administrator to correct the path and/or permissions for this directory';

            $this->system->addError(HEURIST_INVALID_REQUEST, $errorMsg);

            $ret = false;
        }

        return $ret;
    }


    /**
    * Save encoded image data as file and register it
    *
    * @param mixed $data - image data
    * @param mixed $newname
    */
    public function registerImage($data, $newname){

        if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
            $data = substr($data, strpos($data, ',') + 1);
            $type = strtolower($type[1]);// jpg, png, gif

            if (!in_array($type, [ 'jpg', 'jpeg', 'jpe', 'jfif', 'gif', 'png' ])) {
                //throw new \Exception('invalid image type');
                return false;
            }

            $data = base64_decode($data);

            if ($data === false) {
                //throw new \Exception('base64_decode failed');
                return false;
            }
        } else {
            //throw new \Exception('did not match data URI with image data');
            return false;
        }

        $newname = basename($newname);

        $filename = USanitize::sanitizeFileName($newname.'.'.$type);

        file_put_contents(HEURIST_SCRATCH_DIR.$filename, $data);

        return $this->registerFile($filename, $newname);
    }


    /**
    * register file in database
    *
    * @param mixed $file - flle object see UploadHabdler->get_file_object)
    *
            $file = new \stdClass();
            original_name, type, name, size, url (get_download_url),
    *
    *
    * @param mixed $needclean - remove file from temp location after reg
    * @returns record or false
    */
    public function registerFile($file, $newname, $needclean = true, $tiledImageStack=false, $_fields=null){

       $this->records = null; //reset

       $fields = $this->getFileInfoForReg($file, $newname);

       if($fields!==false){

                if($tiledImageStack){
                    //special case for tiled images stack
                    $path_parts = pathinfo($fields['ulf_OrigFileName']);
                    $fields['ulf_OrigFileName'] = '_tiled@'.$path_parts['filename'];
                    $fields['ulf_PreferredSource'] = 'tiled';
                }else{
                    $fields['ulf_PreferredSource'] = 'local';
                }

                if(@$_fields['ulf_Description']!=null){
                    $fields['ulf_Description'] = $_fields['ulf_Description'];
                }

                $fileinfo = array('entity'=>'recUploadedFiles', 'fields'=>$fields);

                $this->setData($fileinfo);
                $ret = $this->save();//copies temp from scratch to file_upload it returns ulf_ID

                //unlink($tmp_name);

                return $ret;
       }else{
           return false;
       }

    }

    /**
    * Download url to server and register as local file
    *
    * $validate_same_file - 0: don't validate at all, 1: validata name only, 2: name and hash
    * if the same name exists - returns ulf_ID of existing registered file
    *
    * @param mixed $url
    */
    public function downloadAndRegisterdURL($url, $fields=null, $validate_same_file=0){

        $orig_name = basename($url);//get filename
        if(strpos($orig_name,'%')!==false){
            $orig_name = urldecode($orig_name);
        }

        $ulf_ID_already_reg = 0;

        if($orig_name){
            if($validate_same_file>0){
                //check filename
                $mysqli = $this->system->get_mysqli();
                $query2 = 'SELECT ulf_ID, concat(ulf_FilePath,ulf_FileName) as fullPath FROM recUploadedFiles '
                .'WHERE ulf_OrigFileName="'.$mysqli->real_escape_string($orig_name).'"';
                $fileinfo = mysql__select_row($mysqli, $query2);
                if($fileinfo!=null){

                    $filepath = $fileinfo[1];
                    $filepath = resolveFilePath($filepath);

                    if(file_exists($filepath))
                    {
                        $ulf_ID_already_reg = $fileinfo[0];

                        if($validate_same_file==1){
                            //already exist
                            return $ulf_ID_already_reg;
                        }elseif($validate_same_file==2){
                            //get file hash of already registered local file
                            $old_md5 = md5_file($filepath);
                        }
                    }
                }
            }
        }

        $tmp_file = HEURIST_SCRATCH_DIR.$orig_name;

        if(saveURLasFile($url, $tmp_file)>0){

            if($validate_same_file==2 && $ulf_ID_already_reg>0){
                //check file hash
                $new_md5 = md5_file($tmp_file);
                if($old_md5==$new_md5){
                    //skip - this file is quite the same
                    unlink($tmp_file);
                    return $ulf_ID_already_reg;
                }
            }

            //temp file will be removed in save method
            $ulf_ID = $this->registerFile($tmp_file, null, false, false, $fields);
            if($ulf_ID && is_array($ulf_ID)) {$ulf_ID = $ulf_ID[0];}

            return $ulf_ID;
        }else{
            return false;
        }

    }

    /**
    * Register remote resource - used to fix flaw in database - detail type "file" has value but does not have registered
    * It may happen when user converts text field to "file"
    *
    * $dtl_ID - update recDetails as well
    *
    * @param mixed $url
    * @param mixed $generate_thumbmail
    */
    public function registerURL($url, $tiledImageStack=false, $dtl_ID=0, $fields=null){

       $this->records = null; //reset

       if($fields==null) {$fields = array();}
       $fields['ulf_PreferredSource'] = $tiledImageStack?'tiled':'external';
       $fields['ulf_OrigFileName']    = $tiledImageStack?'_tiled@':'_remote';//or _iiif
       $fields['ulf_ExternalFileReference'] = $url;

       if(!@$fields['ulf_MimeExt']){
           if($tiledImageStack){
                $fields['ulf_MimeExt'] = 'png';
           }else{
               $ext = recognizeMimeTypeFromURL($this->system->get_mysqli(), $url);
               if(@$ext['extension']){
                   $fields['ulf_MimeExt'] = $ext['extension'];
               }else{
                   $fields['ulf_MimeExt'] = 'bin';//default value
               }
           }
       }
       $fields['ulf_UploaderUGrpID'] = $this->system->get_user_id();

       $fileinfo = array('entity'=>'recUploadedFiles', 'fields'=>$fields);

       $this->setData($fileinfo);
       $this->setRecords(null);//reset
       $ulf_ID = $this->save();
       if($ulf_ID && is_array($ulf_ID)) {$ulf_ID = $ulf_ID[0];}

       if( $ulf_ID>0 && $dtl_ID>0 ){ //register in recDetails

               //update in recDetails
               $query2 = 'update recDetails set dtl_Value=null, `dtl_UploadedFileID`='.intval($ulf_ID)
                                            .' where dtl_ID='.intval($dtl_ID);

               $this->system->get_mysqli()->query($query2);

               //get full file info
               $fileinfo = fileGetFullInfo($this->system, $ulf_ID);
               if(is_array($fileinfo) && count($fileinfo)>0){
                    return $fileinfo[0];
               }

       }
       return $ulf_ID;

    }
    
    //
    // $is_concatente - true, concatenate all unique values, otherwise takes first non empty
    //
    private function mergeDuplicatesFields($fieldName, $ulf_ID, $dup_IDs, $is_concatenate=false){
        
        $mysqli = $this->system->get_mysqli();
        
        $ulf_ID = intval($ulf_ID);
        
        $query = "SELECT $fieldName FROM recUploadedFiles WHERE ulf_ID=$ulf_ID AND $fieldName != ''";
        $main_value = mysql__select_value($mysqli, $query);

        if(!$is_concatenate && $main_value){
            return; //value is already set   
        }
        
        $query = "SELECT DISTINCT $fieldName FROM recUploadedFiles WHERE ulf_ID IN ($dup_IDs) AND $fieldName != ''";
        $values = mysql__select_list2($mysqli, $query);

        if(empty($values)){
            return; //nothing found
        }    

        $new_value = $main_value;
        
        foreach($values as $val){
            if($main_value!=$val){
                $new_value = ($new_value?($new_value."\n"):'').$val;
                if(!$is_concatenate){
                    break; //one non empty value is enough  
                }
            }
        }
        
        if($new_value){
            $upd_query = "UPDATE recUploadedFiles SET $fieldName=? WHERE ulf_ID=$ulf_ID";
            mysql__exec_param_query($mysqli, $upd_query, array('s', $new_value));
        }
        
    }

    //
    // actions on set of files (called from butch_action)
    //
    private function mergeDuplicates(){
        
            set_time_limit(0);
        
            define('SPECIAL_FOR_LIMC_FRANCE', true); //remove unlinked ref records and files from upload_files/...thumbnail
            define('TERMINATED_BY_USER', 'Merging Duplications has been terminated by user');

            $ret = true;
        
            $mysqli = $this->system->get_mysqli();
    
            $session_id = intval(@$this->data['session']);
            if($session_id>0){
                DbUtils::initialize();
                DbUtils::setSessionId($session_id);//start progress session
            }

    
            $ids = prepareIds($this->data['merge_duplicates']);
            $where_ids = '';

            $cnt_local_fixes_by_path = 0;
            $cnt_local_fixes_by_checksum  = 0;
            $cnt_remote_fixes = 0;
            $cnt_thumbnails = 0;
            
            $to_delete_ids_with_files = array();//for files with different upload names and same md5 and original name
            $to_delete_ids = array();
            
            $to_delete = array();

            if(is_array($ids) && count($ids) > 1){ // multiple
                $where_ids .= ' AND ulf_ID IN (' . implode(',', $ids) . ')';
                //elseif(is_int($ids) && $ids > 0){ 
                // single
                //$where_ids .= ' AND ulf_ID = ' . intval($ids);
            }
            // else use all
  
 /*           
            if(SPECIAL_FOR_LIMC_FRANCE){
                //remove files from thumbnail
$query = 'SELECT ulf_ID FROM recUploadedFiles WHERE ulf_FilePath LIKE "uploaded_files/%" AND ulf_FilePath LIKE "%/thumbnail/"';
                $to_delete_thumbs = mysql__select_list2($mysqli, $query);
                
if($is_verbose) {
        $msg = 'found  thumbs '.count($to_delete_thumbs);
        error_log($msg);
        echo $msg.'<br>';
}         

                if(!empty($to_delete_thumbs)){
                    
                    $mysqli->query('SET foreign_key_checks = 0');
                    
                    $total_cnt = count($to_delete_thumbs);
                    $offset = 0;
                    while ($offset<$total_cnt){

                        $to_delete_thumbs_chunk = array_slice($to_delete_thumbs, $offset, 250);
                        
                        $records_without_links = mysql__select_list2($mysqli,
        'SELECT dtl_RecID, count(rl_ID) as cnt FROM recDetails LEFT JOIN recLinks ON (rl_SourceID=dtl_RecID OR rl_TargetID=dtl_RecID)'
        .' WHERE dtl_UploadedFileID IN ('.implode(',',$to_delete_thumbs_chunk).') GROUP BY dtl_RecID HAVING cnt=0');

if($is_verbose) {
        $msg = 'found  records '.count($records_without_links);
        error_log($msg);
        echo $msg.'<br>';
}         

                        if(!empty($records_without_links)){
                            //recordDelete($this->system, $records_without_links);   
                            $records_without_links = '('.implode(',',$records_without_links).')';
                            
                            $mysqli->query('delete from recDetails where dtl_RecID IN ' . $records_without_links);
                            if ($mysqli->error) {
                                    $ret = false;
                                    $this->system->addError(HEURIST_DB_ERROR, 'error delete rec details' ,$mysqli->error);
                                    break;
                            }
                            //
                            $mysqli->query('delete from Records where rec_ID IN ' . $records_without_links);
                            if ($mysqli->error) {
                                    $ret = false;
                                    $this->system->addError(HEURIST_DB_ERROR, 'error delete records', $mysqli->error);
                                    break;
                            }
                        }
                        
                        //exclud thumbnails that still have references
                        $list = mysql__select_assoc2($mysqli, 'SELECT dtl_UploadedFileID, dtl_RecID '
                            .'FROM recDetails WHERE dtl_UploadedFileID in ('.implode(',', $to_delete_thumbs_chunk).')');
                        if(!empty($list)){
                            if($is_verbose) {
                                    $msg = 'found ref records '.count($list);
                                    error_log($msg);
                                    echo $msg.'<br>'.implode(',',$list).'<br>';
                            }         
                            //remove 
                            foreach($list as $ulf_ID=>$rec_ID){
                                $idx = array_search($ulf_ID, $to_delete_thumbs_chunk);
                                if($idx!==false){
                                    unset($to_delete_thumbs_chunk[$idx]);                           
                                }
                            }
                            
                            if($is_verbose) {
                                echo 'Removed '.count($to_delete_thumbs_chunk).'<br>';
                            }
                        }
                        //remove ulf entries 
                        $this->data[$this->primaryField] = $to_delete_thumbs_chunk;
                        $ret = $this->delete();
                        
                        if(!$ret) { break; }
                        
                        $offset = $offset + 250;

                        $mysqli->commit();
                        mysql__begin_transaction($mysqli);
                        
                        //break; //remove first 250 only                        
                    }//while

                    $mysqli->query('SET foreign_key_checks = 1');
                    
                    if($ret===false){
                        $mysqli->rollback();
                    }
                    
                    $cnt_thumbnails = $cnt_thumbnails + $total_cnt;
                }
if($is_verbose) {echo 'Thumnails DONE<br>';}         

                //return $ret;    
            }
*/
            
            
            //1. ---------------------------------------------------------------
            //search for duplicated local files - with the same name and path 
            $query = 'SELECT ulf_FilePath, ulf_FileName, count(*) as cnt FROM recUploadedFiles WHERE ulf_FileName IS NOT NULL' . $where_ids . ' GROUP BY ulf_FilePath, ulf_FileName HAVING cnt > 1';
            $local_dups = $mysqli->query($query);
            
            $cnt = 0;
            $tot_cnt = $local_dups->num_rows;

            if($local_dups &&  $tot_cnt > 0){

                //find id with duplicated path+name
                while($local_file = $local_dups->fetch_row()){

                    $path = ' IS NULL';
                    $fname = ' IS NULL';
                    $params = array('');
                    if(@$local_file[0]!=null){
                        $path = '=?';
                        $params[0] = 's';
                        $params[] = $local_file[0];
                    }
                    if(@$local_file[1]!=null){
                        $fname = '=?';
                        $params[0] = $params[0].'s';
                        $params[] = $local_file[1];
                    }

                    //$path = (@$local_file[0]!=null) ? ('="' . $mysqli->real_escape_string($local_file[0]) . '"') : ' IS NULL';
                    //$fname = (@$local_file[1]!=null) ? ('="' . $mysqli->real_escape_string($local_file[1]) . '"') : ' IS NULL';
                    
                    //find IDS with the same name and path
                    $query = 'SELECT ulf_ID FROM recUploadedFiles WHERE ulf_FilePath'
                        .  $path
                        .' AND ulf_FileName '.$fname
                        . $where_ids;

                    $res = mysql__select_param_query($mysqli, $query, $params);

                    //$res = $mysqli->query($query);

                    $dups_ids = array();
                    if($res){
                        while ($local_id = $res->fetch_row()) {
                            array_push($dups_ids, intval($local_id[0]));
                        }
                        $res->close();
                    }

                    //update references in recDetails    
                    $new_ulf_id = intval(array_shift($dups_ids)); //get first
                    $to_delete[$new_ulf_id] = $dups_ids;
                    $to_delete_ids = array_merge($to_delete_ids, $dups_ids);
                    
                    if(DbUtils::setSessionVal('0,'.$cnt.','.$tot_cnt)){            
                            //terminated by user
                            $this->system->addError(HEURIST_ACTION_BLOCKED, TERMINATED_BY_USER);
                            $ret = false;
                            break;
                    }
                    $cnt++;

                    $cnt_local_fixes_by_path = $cnt_local_fixes_by_path + count($dups_ids);

                }//while
                $local_dups->close();
            }
            
            
            if($ret){
            //2. ---------------------------------------------------------------
            // Check dup local file's size and checksum against each other
            $query = 'SELECT ulf_OrigFileName, count(*) AS cnt '
            . 'FROM recUploadedFiles '
            . 'WHERE ulf_OrigFileName IS NOT NULL AND ulf_OrigFileName<>"_remote" AND ulf_OrigFileName NOT LIKE "_iiif%"'. $where_ids . ' '
            . 'GROUP BY ulf_OrigFileName HAVING cnt > 1';
            $local_dups = $mysqli->query($query);
            $cnt = 0;
            $tot_cnt = $local_dups->num_rows;

            if($local_dups && $tot_cnt > 0){

                while($local_file = $local_dups->fetch_row()){

                    $fname = (@$local_file[0]!=null)?$local_file[0]:'';

                    $dup_query = 'SELECT ulf_ID, ulf_FilePath, ulf_FileName FROM recUploadedFiles WHERE ulf_OrigFileName=?';

                    $dup_local_files = mysql__select_param_query($mysqli, $dup_query, array('s', $fname));

                    $dups_files = array();//ulf_ID => path, size, md, array(dup_ulf_ids)

                    while ($file_dtls = $dup_local_files->fetch_assoc()) {
                        
                        $file_id = intval($file_dtls['ulf_ID']);
                        
                        if(array_key_exists($file_id, $to_delete) || in_array($file_id, $to_delete_ids))
                        {
                            continue; //already detected as duplicated in previous step
                        }

                        //compare files
                        if(@$file_dtls['ulf_FilePath']==null){
                            $res_fullpath = $file_dtls['ulf_FileName'];
                        }else{
                            $res_fullpath = resolveFilePath( $file_dtls['ulf_FilePath'].$file_dtls['ulf_FileName'] );//see recordFile.php
                        }

                        if(file_exists($res_fullpath)){
                            $f_size = filesize($res_fullpath);
                            $f_md5 = md5_file($res_fullpath);


                            $is_unique = true;
                            foreach ($dups_files as $ulf_ID => $file_arr){
                                if ($file_arr['size'] == $f_size && $file_arr['md5'] == $f_md5){ // same file
                                    $is_unique = false;
                                    $dups_files[$ulf_ID]['dups'][] = $file_id;
                                    break;
                                }
                            }
                            if($is_unique){
                                $dups_files[$file_id] = array('md5'=>$f_md5, 'size'=>intval($f_size), 'dups'=>array());//'path'=>$res_fullpath,
                            }
                        }
                    }//while
                    $dup_local_files->close();

                    
                    foreach ($dups_files as $ulf_ID => $file_arr) {
                        if(!empty($file_arr['dups'])){

                            $to_delete[$ulf_ID] = $file_arr['dups'];
                            $to_delete_ids_with_files = array_merge($to_delete_ids_with_files, $file_arr['dups']);
                            
                            $cnt_local_fixes_by_checksum = $cnt_local_fixes_by_checksum + count($file_arr['dups']);
                        }
                    }//foreach
                    
                    if($cnt%10==0){
                    if(DbUtils::setSessionVal('1,'.$cnt.','.$tot_cnt)){            
                            //terminated by user
                            $this->system->addError(HEURIST_ACTION_BLOCKED, TERMINATED_BY_USER);
                            $ret = false;
                            break;
                    }
                    }
                    $cnt++;
                    
                }//while main
            }

            }
            
            if($ret){

            //3. ---------------------------------------------------------------
            //search for duplicated remote files
            $query = 'SELECT ulf_ExternalFileReference, count(*) as cnt FROM recUploadedFiles WHERE ulf_ExternalFileReference IS NOT NULL'. $where_ids .' GROUP BY ulf_ExternalFileReference HAVING cnt > 1';
            $remote_dups = $mysqli->query($query);
            $cnt = 0;
            $tot_cnt = $remote_dups->num_rows;

            if ($remote_dups && $tot_cnt > 0) {

                //find id with duplicated url
                while ($res = $remote_dups->fetch_row()) {

                    if(@$res[0]==null || $res[0]=='') {
                        continue;
                    }

                    $query = 'SELECT ulf_ID FROM recUploadedFiles WHERE ulf_ExternalFileReference=? '
                             .$where_ids;
                    $res = mysql__select_param_query($mysqli, $query, array('s', $res[0]));

                    $dups_ids = array();
                    while ($remote_id = $res->fetch_row()) {
                        array_push($dups_ids, intval($remote_id[0]));
                    }
                    $res->close();

                    $new_ulf_id = intval(array_shift($dups_ids));
                    $to_delete[$new_ulf_id] = $dups_ids;
                    
                    $to_delete_ids = array_merge($to_delete_ids, $dups_ids);
                    
                    
                    if(DbUtils::setSessionVal('2,'.$cnt.','.$tot_cnt)){            
                            //terminated by user
                            $this->system->addError(HEURIST_ACTION_BLOCKED, TERMINATED_BY_USER);
                            $ret = false;
                            break;
                    }
                    $cnt++;

                    $cnt_remote_fixes = $cnt_remote_fixes + count($dups_ids);
                    //$del_query = 'DELETE FROM recUploadedFiles where ulf_ID in ('.implode(',',$dups_ids).')';
                    //$mysqli->query($del_query);
                }//while

                $remote_dups->close();
            }
            }
            
            //4. ------------------------------------------------------------------
            // Merge description fields to new main record, remove references in recDetails, then delete
            $cnt = 0;
            $tot_cnt = count($to_delete);
            
            if($ret &&  $tot_cnt > 0){
                
                DbUtils::setSessionVal('3');
                
                foreach ($to_delete as $ulf_ID => $d_ids) {

                    $dup_ids = $d_ids;
                    if(is_array($dup_ids)){
                        $dup_ids = implode(',', $dup_ids);
                        //$to_delete[$ulf_ID] = $dup_ids;
                    }else{
                        $d_ids = array($d_ids);
                    }
                    
                    //merge other fields ulf_Caption, ulf_Description, ulf_Copyright, ulf_Copyowner
                    $this->mergeDuplicatesFields('ulf_Description', $ulf_ID, $dup_ids, true);
                    $this->mergeDuplicatesFields('ulf_Caption', $ulf_ID, $dup_ids);
                    $this->mergeDuplicatesFields('ulf_Copyright', $ulf_ID, $dup_ids);
                    $this->mergeDuplicatesFields('ulf_Copyowner', $ulf_ID, $dup_ids);
                    
/*                    
                    if(SPECIAL_FOR_LIMC_FRANCE){                    
                    //remove referencing records if they are not linked anywhere
                    $records_without_links = mysql__select_list2($mysqli,
"SELECT dtl_RecID, count(rl_ID) as cnt FROM recDetails LEFT JOIN recLinks ON (rl_SourceID=dtl_RecID OR rl_TargetID=dtl_RecID)"
." WHERE dtl_UploadedFileID IN ($dup_ids) GROUP BY dtl_RecID HAVING cnt=0");
                    recordDelete($this->system, $records_without_links);
                    }
*/                    
                    //remove references in recDetails
                    $upd_query = 'UPDATE recDetails SET dtl_UploadedFileID='.intval($ulf_ID)
                                    .' WHERE dtl_UploadedFileID IN (' . $dup_ids .')';
                    $mysqli->query($upd_query);

                    if($mysqli->error !== ''){
                        $ret = false;
                        $this->system->addError(HEURIST_DB_ERROR, $mysqli->error);
                        break;
                    }
                    
                    if($cnt%10==0){
                    if(DbUtils::setSessionVal('3,'.$cnt.','.$tot_cnt)){            
                            //terminated by user
                            $this->system->addError(HEURIST_ACTION_BLOCKED, TERMINATED_BY_USER);
                            $ret = false;
                            break;
                    }
                    }
                    $cnt++;
                    
                }//for

                if($ret){
                
                    if(!empty($to_delete_ids)){
                        // Delete files - except local by path - remove only table entry and thumbnail!!!!!
                        $this->data[$this->primaryField] = $to_delete_ids;
                        $ret = $this->delete(true, false); //keep uploaded files
                        
                        foreach($to_delete_ids as $id){
                            $key = array_search($id, $to_delete_ids_with_files);
                            if($key!==false){
                                unset($to_delete_ids_with_files[$key]);
                            }    
                        }
                    }
                    if(!empty($to_delete_ids_with_files)){
                        $this->data[$this->primaryField] = $to_delete_ids_with_files;
                        $ret = $this->delete(false, false);
                    }
                }
            }

            if($ret){
                $ret = array('local' => $cnt_local_fixes_by_path, 
                            'local_checksum' => $cnt_local_fixes_by_checksum, 
                            'remote' => $cnt_remote_fixes,
                            'tumbnails'=>$cnt_thumbnails);
            }
            
            DbUtils::setSessionVal('REMOVE');
            
            return $ret;
        
    }
    
    //
    //
    //
    private function createMediaRecords()
    {
            $ret = false;
        
            $ids = prepareIds(@$this->data['create_media_records']);

            $cnt_skipped = 0;
            $cnt_error = array();
            $cnt_new = array();

            // ----- Reqruied
            $rty_id = 0;
            $dty_file = 0;
            $dty_title = 0;
            // ----- Recommended
            $dty_desc = defined('DT_SHORT_SUMMARY') ? DT_SHORT_SUMMARY : 0;
            $dty_name = defined('DT_FILE_NAME') ? DT_FILE_NAME : 0;
            // ----- Optional
            $dty_path = defined('DT_FILE_FOLDER') ? DT_FILE_FOLDER : 0;
            $dty_ext = defined('DT_FILE_EXT') ? DT_FILE_EXT : 0;
            $dty_size = defined('DT_FILE_SIZE') ? DT_FILE_SIZE : 0;
            // ulf_ExternalFileReference goes into rec_URL

            if(defined('RT_MEDIA_RECORD') || ($this->system->defineConstant('RT_MEDIA_RECORD') && RT_MEDIA_RECORD > 0)){
                $rty_id = RT_MEDIA_RECORD;
            }
            if(defined('DT_FILE_RESOURCE') || ($this->system->defineConstant('DT_FILE_RESOURCE') && DT_FILE_RESOURCE > 0)){
                $dty_file = DT_FILE_RESOURCE;
            }
            if(defined('DT_NAME') || ($this->system->defineConstant('DT_NAME') && DT_NAME > 0)){
                $dty_title = DT_NAME;
            }

            if(defined('DT_SHORT_SUMMARY') || ($this->system->defineConstant('DT_SHORT_SUMMARY') && DT_SHORT_SUMMARY > 0)){
                $dty_desc = DT_SHORT_SUMMARY;
            }
            if(defined('DT_FILE_NAME') || ($this->system->defineConstant('DT_FILE_NAME') && DT_FILE_NAME > 0)){
                $dty_name = DT_FILE_NAME;
            }

            if(defined('DT_FILE_FOLDER') || ($this->system->defineConstant('DT_FILE_FOLDER') && DT_FILE_FOLDER > 0)){
                $dty_path = DT_FILE_FOLDER;
            }
            if(defined('DT_FILE_EXT') || ($this->system->defineConstant('DT_FILE_EXT') && DT_FILE_EXT > 0)){
                $dty_ext = DT_FILE_EXT;
            }
            if(defined('DT_FILE_SIZE') || ($this->system->defineConstant('DT_FILE_SIZE') && DT_FILE_SIZE > 0)){
                $dty_size = DT_FILE_SIZE;
            }

            if($rty_id > 0 && $dty_file > 0 && $dty_title > 0){
                
                $mysqli = $this->system->get_mysqli();

                $rec_search = 'SELECT count(DISTINCT rec_ID) AS cnt '
                            . 'FROM Records, recDetails '
                            . 'WHERE rec_ID=dtl_RecID AND rec_FlagTemporary!=1 ' //AND rec_RecTypeID='.$rty_id
                            . ' AND dtl_UploadedFileID=';  // AND dtl_DetailTypeID='.$dty_file.'

                $file_search = 'SELECT ulf_OrigFileName, ulf_Caption, ulf_Description, ulf_FileName, ulf_FilePath, ulf_MimeExt, ulf_FileSizeKB, ulf_ExternalFileReference '
                            .  'FROM recUploadedFiles '
                            .  'WHERE ulf_ID=';

                $record = array(
                    'ID' => 0,
                    'RecTypeID' => $rty_id,
                    'no_validation' => true,
                    'URL' => '',
                    'ScratchPad' => null,
                    'AddedByUGrpID' => $this->system->get_user_id(), //ulf_UploaderUGrpID
                    'details' => array()
                );
                foreach ($ids as $ulf_id) {

                    $record['URL'] = '';
                    $record['details'] = array();

                    $rec_res = mysql__select_value($mysqli, $rec_search . $ulf_id);
                    if($rec_res > 0){ // already have a record
                        $cnt_skipped ++;
                        continue;
                    }

                    $file_details = mysql__select_row_assoc($mysqli, $file_search . $ulf_id);
                    if($file_details == null || $file_details == false){ // unable to retrieve file data
                        $cnt_error[] = $ulf_id;
                        continue;
                    }

                    $details = array(
                        $dty_file => $ulf_id,
                        $dty_title => $file_details['ulf_Caption']
                            ?$file_details['ulf_Caption']:$file_details['ulf_OrigFileName']
                    );

                    if($file_details['ulf_OrigFileName'] == '_remote'){
                        $record['URL'] = $file_details['ulf_ExternalFileReference'];
                    }

                    if($dty_desc > 0 && !empty($file_details['ulf_Description'])){
                        $details[$dty_desc] = $file_details['ulf_Description'];
                    }
                    if($dty_name > 0 && !empty($file_details['ulf_FileName'])){
                        $details[$dty_name] = $file_details['ulf_FileName'];
                    }
                    if($dty_path > 0 && !empty($file_details['ulf_FilePath'])){
                        $details[$dty_path] = $file_details['ulf_FilePath'];
                    }
                    if($dty_ext > 0 && !empty($file_details['ulf_MimeExt'])){
                        $details[$dty_ext] = $file_details['ulf_MimeExt'];
                    }
                    if($dty_size > 0 && !empty($file_details['ulf_FileSizeKB'])){
                        $details[$dty_size] = $file_details['ulf_FileSizeKB'];
                    }

                    $record['details'] = $details;

                    $res = recordSave($this->system, $record);//see recordModify.php
                    if(@$res['status'] != HEURIST_OK){
                        $cnt_error[] = $ulf_id;
                        continue;
                    }

                    $cnt_new[] = $res['data'];
                }

                $ret = array('new' => $cnt_new, 'error' => $cnt_error, 'skipped' => $cnt_skipped);
            }else{

                $extra = '';
                if($rty_id <= 0){
                    $extra = 'missing the Digital media record type (2-5)';
                }
                if($dty_file <= 0){
                    $extra .= (($extra == '' && $dty_title > 0) ? ', ': ($extra == '' ? ' and ' : '')) . 'missing the required file field (2-38)';
                }
                if($dty_title <= 0){
                    $extra .= (($extra == '') ? ' and ': '') . 'missing the required title field (2-1)';
                }

                $this->system->addError(HEURIST_ACTION_BLOCKED, 'Unable to proceed with Media record creations, due to ' . $extra);
            }
            
            return $ret;
    }
    
    //
    // Returns either IDs of referencing records OR file ulf_ID with referencing records
    // $mode - both
    //         records - referenced by field "file" (dtl_UploadedFileID)
    //         details - referenced in value (dtl_Value)
    // $return records - record ids
    //         rec_cnt   - count of recrds
    //         files   - file ids
    //
    private function getMediaRecords($ids, $mode='records', $return='records')
    {
        $ids = prepareIds($ids);

        if($return=='rec_cnt'){
            $ret = 0;    
        }else{
            $ret = array();
        }        
        
        $where_clause = '';
        $mysqli = $this->system->get_mysqli();      

        if($mode!='details'){
        
            if(count($ids) > 1){ // multiple
                $where_clause = ' AND dtl_UploadedFileID IN (' . implode(',', $ids) . ')';
            }elseif(!empty($ids)){ // single
                $where_clause = ' AND dtl_UploadedFileID = ' . $ids[0];
            }
            
            if($return=='files'){
                $fieldName = 'DISTINCT dtl_UploadedFileID';
            }elseif($return=='rec_cnt'){
                $fieldName = 'count(DISTINCT rec_ID)';
            }else{
                $fieldName = 'DISTINCT rec_ID';
            }

            $query = 'SELECT '.$fieldName
                            . ' FROM Records, recDetails '
                            . ' WHERE rec_ID=dtl_RecID AND rec_FlagTemporary!=1 '.$where_clause;

            $ret = ($return=='rec_cnt')?mysql__select_value($mysqli, $query)
                                       :mysql__select_list2($mysqli, $query);
        }
        if($mode!='records'){

            if(count($ids) > 1){ // multiple
                $where_clause = ' ulf_ID IN (' . implode(',', $ids) . ')';
            }elseif(!empty($ids)){ // single
                $where_clause = ' ulf_ID = ' . $ids[0];
            }
           
            $query = 'SELECT DISTINCT ulf_ID, ulf_ObfuscatedFileID  FROM ' 
                        . $this->config['tableName'] . $where_clause;
            $to_check = mysql__select_assoc2($mysqli, $query);

            if(count($to_check) > 0){
                
                if($return=='rec_cnt'){
                    $fieldName = 'count(DISTINCT dtl_RecID)';
                }else{
                    $fieldName = 'DISTINCT dtl_RecID';
                }
                
                // Check if Obfuscated ID is referenced in values
                foreach ($to_check as $ulf_ID => $ulf_ObfuscatedFileID) {

                    if(!$ulf_ObfuscatedFileID){ // missing ulf_ObfuscatedFileID
                        continue;
                    }
                    
                    $query = "SELECT $fieldName FROM recDetails WHERE dtl_Value LIKE '%". $ulf_ObfuscatedFileID ."%'";

                    if($return!='records'){
                        $cnt = mysql__select_value($mysqli, $query);
                        if($cnt>0){
                            if($return=='files'){
                                array_push($ret, $ulf_ID);   
                            }else{
                                $ret = $ret + $cnt;    
                            }
                        }
                    }else{
                        //record ids
                        $res = mysql__select_list2($mysqli, $query);
                        if(!empty($res)){
                            $ret = array_merge($ret, $res);    
                        }
                    }
                }//foreach    
            }        
        }
        
        return $ret;
    }

    //
    //
    //    
    private function deleteSelected()
    {
            
            $ids = prepareIds($this->data['delete_selected']);
            $mode = $this->data['mode'];
            
            //find files with referencing records
            $ulf_IDs_in_use = $this->getMediaRecords($ids, 'records', 'files'); //returns file ids referenced by field "file"
            $cnt_in_use = count($ulf_IDs_in_use);
            $cnt_ref_recs = $this->getMediaRecords($ids, 'records', 'rec_cnt');
            $cnt_ref_values = 0;
            
            $to_delete = array();
            $cnt_deleted = 0;
            
            //exclude files in use from list of selected
            $ids = array_diff($ids, $ulf_IDs_in_use);
            
            if(!empty($ids)){

                $where_clause = 'WHERE ';
                if(count($ids) > 1){ // multiple
                    $where_clause .= ' ulf_ID IN (' . implode(',', $ids) . ')';
                }elseif(!empty($ids)){ // single
                    $where_clause .= ' ulf_ID = ' . $ids[0];
                }// else use all

                $query = 'SELECT DISTINCT ulf_ID, ulf_OrigFileName as filename, ulf_ExternalFileReference as url, ulf_ObfuscatedFileID  FROM ' 
                            . $this->config['tableName'] . $where_clause;
                $to_delete = mysql__select_assoc($mysqli, $query);

                if(!empty($to_delete)){

                    // Check if Obfuscated ID is referenced in values
                    foreach ($to_delete as $ulf_ID => $details) {

                        $ulf_ObfuscatedFileID = $details['ulf_ObfuscatedFileID'];
                        
                        if(!$ulf_ObfuscatedFileID){ // missing ulf_ObfuscatedFileID
                            unset($to_delete[$ulf_ID]);
                            continue;
                        }

                        $cnt_used = mysql__select_value($mysqli, "SELECT count(dtl_ID) FROM recDetails WHERE dtl_Value LIKE '%". $ulf_ObfuscatedFileID ."%'");
                        if($cnt_used>0){
                            $cnt_in_use++;
                            $cnt_ref_values = $cnt_ref_values+$cnt_used;
                            unset($to_delete[$ulf_ID]);
                            continue;
                        }
                    }

                    if($mode == 'delete' && !empty($to_delete)){ // delete files

                        $to_delete = array_keys($to_delete);

                        $this->data[$this->primaryField] = $to_delete;
                        if($this->delete(false, false)){
                            $cnt_deleted = count($to_delete);
                        }else{
                            $cnt_deleted = false;
                        }
                    }
                }
            }
            
            if($mode == 'delete'){
                $ret = $cnt_deleted;
            }else{
                $ret = array('files'=>$to_delete, 'cnt_in_use'=>$cnt_in_use, 
                                                        'cnt_ref_recs'=>$cnt_ref_recs, 
                                                        'cnt_ref_values'=>$cnt_ref_values);
            }
            
            return $ret;

    }
}
?>
