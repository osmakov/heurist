<?php
/**
* Simplesaml utilities
*
* logout
* login
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
$saml_script = '/var/simplesamlphp/lib/_autoload.php';
$is_debug = true;
if(file_exists($saml_script)){
    require_once $saml_script;
    $is_debug = false;
}

//
//
//
function samlLogout($system, $sp, $back_url)
{
    if($system->doLogout()){ //destroy session
        $system->user_LogActivity('Logout');
        $as = new \SimpleSAML\Auth\Simple($sp);
        //$as = new SimpleSAML_Auth_Simple($sp);
        $as->logout(["ReturnTo" => $back_url]);
    }
}

//
// $require_auth - true - opens saml login page
//                 false -  returns 0 if not authenticated
// $noframe - load SAML login in place of Heurist
//
function samlLogin($system, $sp, $dbname, $require_auth, $noframe=false){
    global $is_debug;

    $user_id = 0;
    $errMessage = null;
    $attr = null;

    $as = new \SimpleSAML\Auth\Simple($sp);
    //$as = new SimpleSAML_Auth_Simple($sp);
    if(!$as->isAuthenticated()){

        if($require_auth){
            $as->requireAuth();//after saml login - it returns to this page again
            exit;
        }else{
            $errMessage = 'Not externally authenticated';
        }
    }

    if($errMessage==null){

        $attr = $as->getAttributes();

        if(isEmptyArray($attr)){
            $errMessage = 'External authentication returns empty attributes. Please contact Service provider admin';
        }
    }

    //$idp = $as->getAuthData('saml:sp:IdP');
    //$nameId = $as->getAuthData('saml:sp:NameID')['Value'];

    //find user in sysUGrps by email and/or uid
    if(!isEmptyArray($attr) && ($system->is_inited() || $system->init( $dbname )) ){

            $mysqli = $system->get_mysqli();

            $attr_mail = @$attr['mail'][0]?$attr['mail'][0]:@$attr['urn:oid:0.9.2342.19200300.100.1.3'][0];
            $attr_uid = @$attr['uid'][0]?$attr['uid'][0]:@$attr['urn:oid:0.9.2342.19200300.100.1.1'][0];

            $query = 'SELECT ugr_ID,ugr_eMail,usr_ExternalAuthentication FROM sysUGrps where usr_ExternalAuthentication is not null';
            $res = $system->get_mysqli()->query($query);
            if ($res){
                while ($row = $res->fetch_row()){
                    $prm = json_decode($row[2],true);
                    if( @$prm[$sp]
                        && ($prm[$sp]['uid']=='' || $prm[$sp]['uid']==$attr_uid)
                        && (@$prm[$sp]['mail']=='n' || $row[1]==$attr_mail) ){

                        $user_id = $row[0];
                        break;
                    }
                }
                $res->close();
            }

            /*using MySQL feature to query fields with JSON - unfortunately it does not work for MariaDB
            $spe = $mysqli->real_escape_string($sp);
$query = 'SELECT ugr_ID FROM sysUGrps where usr_ExternalAuthentication is not null '
.' and  (usr_ExternalAuthentication->\'$."'.$spe.'".uid\'="" OR usr_ExternalAuthentication->\'$."'.$spe.'".uid\'="'.$mysqli->real_escape_string($attr['uid'][0]).'")'
.' and  (usr_ExternalAuthentication->\'$."'.$spe.'".mail\'="n" OR ugr_eMail="'.$mysqli->real_escape_string($attr['mail'][0]).'")';

            $user_id = mysql__select_value($system->get_mysqli(), $query);
            */

            //DEBUG  $user_id = 0;

            if(!($user_id>0)){

                if($system->settings->get('sys_AllowRegistration')){
                    //register new user
                    $givenName = @$attr['urn:oid:2.5.4.42'][0]?$attr['urn:oid:2.5.4.42'][0]:@$attr['givenName'][0];
                    $surName = @$attr['urn:oid:2.5.4.4'][0]?$attr['urn:oid:2.5.4.4'][0]:@$attr['sn'][0];

                    list($givenName2, $surName2) = explode(' ',
                        @$attr['displayName'][0]?$attr['displayName'][0]
                                                :@$attr['urn:oid:2.16.840.1.113730.3.1.241'][0]);

                    if(!$givenName){
                        $givenName = $givenName2?$givenName2:'Unknown';
                    }
                    if(!$surName){
                        $surName = $surName2?$surName2:'Unknown';
                    }

                    $ext_auth = array();
                    $ext_auth[$sp] = array('uid'=>$attr_uid, 'mail'=>'y');

                    $bytes = random_bytes(5);
                    $rand_pwd = bin2hex($bytes);

                    // displayName, givenName, sn, department
                    $record = array('ugr_ID'=>-1, 'ugr_Type'=>'user',
                        'ugr_Name'=>$attr_uid, //login
                        'ugr_eMail'=>$attr_mail, 'ugr_Password'=>$rand_pwd,
                        'ugr_FirstName'=>$givenName, //$attr['givenName'][0],
                        'ugr_LastName'=>$surName,  //$attr['sn'][0],
                        'ugr_Department'=>'na',
                        'ugr_Organisation'=>'na',
                        'ugr_Interests'=>'na',
                        //DEBUG 'ugr_IncomingEmailAddresses'=>substr(print_r($attr,true),0,3999),
                        'ugr_Enabled'=>'y',
                        'usr_ExternalAuthentication'=> json_encode($ext_auth) );
                    $user_id = user_Update($system, $record, true);

                }else{
                    $errMessage = 'Heurist Database '.$dbname
                    .' does not have an user with provided attributes ('.$attr_uid.','.$attr_mail.')';
                }
            }

            /*
            REGISTER
            $user_id = mysql__select_value($system->get_mysqli(),'SELECT ugr_ID FROM sysUGrps WHERE ugr_eMail="'
                .$attr['mail'][0].'"');
            if(false && !($user_id>0)){
                //add new user
                //$attr['uid'][0]

                $bytes = random_bytes(5);
                $rand_pwd = bin2hex($bytes);

                list($givenName, $surName) = explode(' ',$attr['displayName'][0]);

                // displayName, givenName, sn, department
                $record = array('ugr_ID'=>-1, 'ugr_Type'=>'user',
                    'ugr_Name'=>$attr['uid'][0], //login
                    'ugr_eMail'=>$attr['mail'][0], 'ugr_Password'=>$rand_pwd,
                    'ugr_FirstName'=>$givenName, //$attr['givenName'][0],
                    'ugr_LastName'=>$surName,  //$attr['sn'][0],
                    'ugr_Department'=>@$attr['department'][0],
                    'ugr_Organisation'=>'na',
                    'ugr_Interests'=>'na',
                    'ugr_Enabled'=>'y' );
                $user_id = user_Update($system, $record, true);
            }
            */
    }


    if($noframe) { //load heurist again

        if($user_id>0){
            //perform authorization
            $system->doLogin($user_id, null, 'remember', true, false);//skip pwd check
            //reload page
            redirectURL(HEURIST_BASE_URL . '?db=' . HEURIST_DBNAME);
            //DEBUG $params = '&usr='.$attr_uid.'&usrid='.$user_id;
        }else{
            $try_login = true;
            $message = $errMessage.'<br><br> '
            .' Please <a class="login-link" reload="home">try login again</a>';

            //define('ERROR_REDIR', dirname(__FILE__).'/../../hclient/framecontent/infoPage.php');
            include_once dirname(__FILE__).'/../../hclient/framecontent/infoPage.php';
        }


    }elseif($errMessage!=null){

        $system->addError(HEURIST_REQUEST_DENIED, $errMessage );
    }

    return $user_id;

}
?>
