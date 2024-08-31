<?php
/*
Add to httpd.conf

RewriteEngine On
#if URI starts with api/ redirect it to controller/api.php
RewriteRule ^/heurist/api/(.*)$ /heurist/hserv/controller/api.php

*/

$requestUri = explode('/', trim($_SERVER['REQUEST_URI'],'/'));


if(@$_REQUEST['method']){
    $method = $_REQUEST['method'];
}else{
    //get method  - GET POST PUT DELETE
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method == 'POST' && array_key_exists('HTTP_X_HTTP_METHOD', $_SERVER)) {  //add
        if ($_SERVER['HTTP_X_HTTP_METHOD'] == 'DELETE') {
            $method = 'DELETE';
        } elseif($_SERVER['HTTP_X_HTTP_METHOD'] == 'PUT' || $_SERVER['HTTP_X_HTTP_METHOD'] == 'PATCH') {
            $method = 'PUT';//replace
        } else {
            exitWithError('Unexpected Header', 400);
        }
    }
    if($method == 'PATCH'){
        $method = 'PUT';
    }
}

//$requestUri[1] = "api"
//$requestUri[2] - database name
//$requestUri[3] - resource(entity )
//$requestUri[4] - selector - id or name

//allowed entities for entityScrud
$entities = array(
'fieldgroups'=>'DefDetailTypeGroups',
'fields'=>'DefDetailTypes',
'rectypegroups'=>'DefRecTypeGroups',
'rectypes'=>'DefRecTypes',
'terms'=>'DefTerms',
'reminders'=>'DbUsrReminders',
'users'=>'SysUsers',
'groups'=>'SysGroups',
'records'=>'Records', //only search allowed
'login'=>'System',
'logout'=>'System',

'rem'=>'UsrReminders',
'annotations'=>'Annotations', //for iiif annotation server
'iiif'=>'iiif', //for iiif presenatation v3 (only GET allowed)
);
//records
    //controlles:
    //record_batch - batch actions for records
    //record_search
    //record_edit

//auth
    //usr_info

//echo print_r($requestUri,true);
//echo '<br>'.$method;
// hserv/controller/api.php?ent=

if(count($requestUri)>0){
    $params = array();
    foreach($requestUri as $prm){
        $k = strpos($prm, '?');
        if($k>0){
            $params[] = substr($prm,0,$k);
            break;
        }
        $params[] = $prm;
    }
    $requestUri = $params;
}

$req_params = USanitize::sanitizeInputArray();

if(@$requestUri[1]!== 'api' || @$req_params['ent']!=null){
    //takes all parameters from $req_params

    //try to detect entity as parameter
    if(@$entities[$req_params['ent']] != null ){
        $requestUri = array(0, 'api', $req_params['db'], $req_params['ent'], @$req_params['id']);
    }else{
        exitWithError('API Not Found', 400);
    }

}elseif(@$req_params['db'] && @$requestUri[2]!=null){ //backward when database is parameter

    if(@$entities[$requestUri[2]]!=null){
        $requestUri = array(0, 'api', $req_params['db'], $requestUri[2], @$requestUri[3]);
    }else{
        exitWithError('API Not Found', 400);
    }

}elseif(@$requestUri[2]!=null){
    $req_params['db'] = $requestUri[2];
}


$allowed_methods = array('search','add','save','delete');

$method = getAction($method);
if($method == null || !in_array($method, $allowed_methods)){
    exitWithError('Method Not Allowed', 405);
}

if($method=='save' || $method=='add'){
    //get request body
    if(!@$req_params['fields']){
        $data = json_decode(file_get_contents('php://input'), true);
        if($data){
            //request body
            $req_params['fields'] = $data;
        }else{
            $req_params['fields'] = $req_params;
        }
    }
    if(@$req_params['fields']['db']){ //may contain db
        $req_params['db'] = $req_params['fields']['db'];
        unset($req_params['fields']['db']);
    }
}else{

    if(@$req_params['limit']==null || $req_params['limit']>100 || $req_params['limit']<1){
        $req_params['limit']=100;
    }

}

// throw new RuntimeException('Unauthorized - authentication failed', 401);
if (@$requestUri[3]=='iiif') {

    if($method=='search'){
        $req_params['resource'] = @$requestUri[4];
        $req_params['id'] = @$requestUri[5];
        $req_params['restapi'] = 1; //set http response code

        include_once '../../hserv/controller/iiif_presentation.php';
    }else{
        exitWithError('Method Not Allowed', 405);
    }


}elseif (@$entities[@$requestUri[3]]=='System') {
    //login and logout actions

    include_once '../autoload.php';

    $system = new hserv\System();
    if( ! $system->init($req_params['db']) ){
        //get error and response
        $system->error_exit_api();//exit from script
    }

    if($requestUri[3]==='login'){

        if(!$system->doLogin(filter_var(@$req_params['fields']['login'], FILTER_SANITIZE_STRING),
                             @$req_params['fields']['password'], 'shared'))
        {
            $system->error_exit_api();
        }else{
                    $is_https = (@$_SERVER['HTTPS']!=null && $_SERVER['HTTPS']!='');
                    $session_id = session_id();
                    $lifetime = time() + 24*60*60;     //day
                    
                    $cres = setcookie('heurist-sessionid', $session_id, [
                        'expires' => $lifetime,
                        'path' => '/',
                        'domain' => '',
                        'secure' => $is_https,
                        'httponly' => true,
                        'SameSite' => 'Strict' //'Lax'
                    ]);
                    
        }

    }elseif($requestUri[3]==='logout'){
        $system->doLogout();
    }

    $system->dbclose();
}
else
{
    //action
    $req_params['entity'] = @$entities[@$requestUri[3]];
    $req_params['a'] = $method;
    $req_params['restapi'] = 1; //set http response code

    if(@$requestUri[4]!=null){
      $req_params['recID'] = $requestUri[4];
    }

    if($req_params['entity']=='Records'){
        if($method=='search'){
            include_once '../../hserv/controller/record_output.php';
        }else{
            exitWithError('Method Not Implemented', 405);
        }
    }else{
        include_once '../../hserv/controller/entityScrud.php';
    }
}
exit;
//header("HTTP/1.1 " . $status . " " . $this->requestStatus($status));
//echo json_encode($data);

//
//
//
function exitWithError($message, $code){

    header(HEADER_CORS_POLICY);
    header(CTYPE_JSON);//'text/javascript');

    http_response_code($code);
    print json_encode(array("status"=>'invalid', "message"=>$message));
    exit;
}

function getAction($method){
    if($method=='GET'){
        return 'search';
    }elseif($method=='POST'){ // add new
        return 'add';
    }elseif($method=='PUT'){ // replace
        return 'save';
    }elseif($method=='DELETE'){
        return 'delete';
    }else{
        return null;
    }
}
?>
