/**
* editReportSchedule.js
* A form to edit report schedules, or create a new one It is utilized as pop-up from manageReports
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


/**
* ReportScheduleEditor - class for pop-up edit report schedules
*
* public methods
*
* save - sends data to server and closes the pop-up window in case of success
* cancel - checks if changes were made, shows warning and closes the window
*
* @author Artem Osmakov <osmakov@gmail.com>
* @version 2011.0427
*/

function ReportScheduleEditor() {

        const _className = "ReportScheduleEditor";
        let _entity, //object (report schedule) to edit
            _recID,     // its ID
            _updatedFields = [], //field names which values were changed to be sent to server
            _updatedDetails = []; //field values
            
        let _reports = [];    

    /**
    * Initialization of input form
    */
    function _init() {

        document.getElementById("lblFilePathHelp").innerHTML = "Path to which report is published (leave blank for default path "+ window.hWin.HAPI4.database + "/generated-reports)";


        _recID = window.hWin.HEURIST4.util.getUrlParameter('recID', location.search);

        if (!Number(_recID)>0) { _recID = 0; }
            
        const _url = window.hWin.HAPI4.baseURL + 'export/publish/loadReports.php';
        const request = {method:'getreport', recID:_recID};
            
        window.hWin.HEURIST4.util.sendRequest(_url, request, null, _continueInit);
        
        
    }
    
    function _continueInit(response){
        
        if(response.status != window.hWin.ResponseStatus.OK){
            window.hWin.HEURIST4.msg.showMsgErr(response);
            return;   
        }

        _reports = response['data'];

        let qlabel;
        let typeID = window.hWin.HEURIST4.util.getUrlParameter('typeID', location.search);
        let templatefile = window.hWin.HEURIST4.util.getUrlParameter('template', location.search); 
        
        let hquery = window.hWin.HEURIST4.util.getUrlParameter('hquery', location.search);  
            
            
        if(window.hWin.HEURIST4.util.isempty(typeID)){
            typeID = "smarty";
        }
        if(window.hWin.HEURIST4.util.isnull(hquery)){
            hquery = '';
        }else{
            let _qlabel = window.hWin.HEURIST4.util.getUrlParameter('label', hquery);
            if(!window.hWin.HEURIST4.util.isnull(_qlabel)){
                qlabel = _qlabel;
            }
        }
        if(window.hWin.HEURIST4.util.isnull(templatefile)){
            templatefile = '';
        }


        if (Number(_recID>0) && window.hWin.HEURIST4.util.isnull(_entity) ){
            document.getElementById("statusMsg").innerHTML = "<strong>Error: Report Schedule #"+_recID+"  was not found. Clicking 'save' button will create a new Schedule.</strong><br><br>";
        }
        
        _entity = (_recID>0&& _reports)? _reports.records[_recID] :null; 
        //creates new empty field type in case ID is not defined
        if(window.hWin.HEURIST4.util.isnull(_entity)){
            _recID =  -1;
            //"rps_ID", "rps_Type", "rps_Title", "rps_FilePath", "rps_URL", "rps_FileName", "rps_HQuery", "rps_Template", "rps_IntervalMinutes"
            _entity = [-1,typeID,qlabel,'','','',hquery,templatefile,1440]; //interval 1 day
        }
        
        document.getElementById('rps_Title').onchange = function(event){
            document.getElementById('rps_FileName').value = window.hWin.HEURIST4.ui.cleanFilename(event.target.value);
        }

        _updateTemplatesList();

        //fills input with values from _entity array
        _fromArrayToUI();
    }

    /**
    *  show the list of available reports
    *  #todo - filter based on record types in result set
    */
    function _updateTemplatesList() {

            let sel = $('#rps_Template');
            const keepSelValue = sel.val();

            sel.empty();
            
            window.hWin.HEURIST4.ui.createTemplateSelector(sel, null, keepSelValue, null);
            
            /*
            //celear selection list
            while (sel.length>0){
                    sel.remove(0);
            }

            if(!window.hWin.HEURIST4.util.isnull(context) && context.length>0){
                for(let i in context){
                    if(i!==undefined){
                        window.hWin.HEURIST4.ui.addoption(sel, context[i].filename, context[i].name);
                    }
                } // for

                sel.selectedIndex = (keepSelIndex<0)?0:keepSelIndex;
            }*/

    }

    /**
    * Fills inputs with values from _entity array
    */
    function _fromArrayToUI(){

        let i,
            el,
            fnames = _reports.fieldNames;

        for(let i = 0, l = fnames.length; i < l; i++) {
            let fname = fnames[i];
            el = document.getElementById(fname);
            if(!window.hWin.HEURIST4.util.isnull(el)){
                el.value = _entity[i];
            }
        }

        if (_recID<0){
            document.getElementById("rps_ID").innerHTML = 'to be generated';
            document.title = "Create New Report Schedule";
        }else{
            document.getElementById("rps_ID").innerHTML =  _recID;
            document.title = "Report Schedule #: " + _recID+" '"+_entity[2]+"'";

            document.getElementById("statusMsg").innerHTML = "";
        }
        //set interval to 1440 (1 day) in case not defined
        let interval = document.getElementById("rps_IntervalMinutes").value;
        if(window.hWin.HEURIST4.util.isempty(interval) || isNaN(parseInt(interval)) || parseInt(interval)<0){
            document.getElementById("rps_IntervalMinutes").value = 1440;            
        }

        
    }


    /**
    * Stores the changed values and verifies mandatory fields
    *
    * Compares data in input with values and in _entity array, then
    * gathers changed values from UI elements (inputs) into 2 arrays _updatedFields and _updatedDetails
    * this function is invoked in 2 places:
    * 1) in cancel method - to check if something was changed and show warning
    * 2) in save (_updateOnServer) - to gather the data to send to server
    *
    * @param isShowWarn - show alert about empty mandatory fields, it is false for cancel
    * @return "mandatory" in case there are empty mandatory fields (it prevents further saving on server)
    *           or "ok" if all mandatory fields are filled
    */
    function _fromUItoArray(isShowWarn){

        _updatedFields = [];
        _updatedDetails = [];

        let interval = document.getElementById("rps_IntervalMinutes").value;
        if(window.hWin.HEURIST4.util.isempty(interval) || isNaN(parseInt(interval)) || parseInt(interval)<0){
            document.getElementById("rps_IntervalMinutes").value = 1440;            
        }
        
        let i,
            fnames = _reports.fieldNames;

        //take only changed values
        for(let i = 0, l = fnames.length; i < l; i++){
            let fname = fnames[i];
            let el = document.getElementById(fname);
            if( window.hWin.HEURIST4.util.isnull(el) || fname=='rps_ID' ){
                continue;
            }
            
                if(_recID<0 || (el.value!==String(_entity[i]) && !(el.value==="" && _entity[i]===null)))
                {
                    _updatedFields.push(fname);
                    _updatedDetails.push(el.value);
                }
                if(window.hWin.HEURIST4.util.isempty(el.value) && 
                    !(fname==='rps_FilePath' || fname==='rps_URL' || fname==='rps_IntervalMinutes') ) 
                {
                    if(isShowWarn) {
                        alert(fname.substr(4)+" is a mandatory field");
                    }
                    el.dispatchEvent(new Event('focus'));
                    _updatedFields = [];
                    return "mandatory";
                }
            
        }

        return "ok";
    }

    /**
    * Http responce listener
    *
    * shows information about result of operation of saving on server and closes this pop-up window in case of success
    *
    * @param context - data from server
    */
    function _updateResult(response) {

        if(response.status != window.hWin.ResponseStatus.OK){
            window.hWin.HEURIST4.msg.showMsgErr(response);
            return;   
        }
        
        let error = false,
            report = "",
            ind;

        for(ind in response.data){
            if( !window.hWin.HEURIST4.util.isnull(ind) ){
                let item = response.data[ind];
                if(isNaN(item)){
                    window.hWin.HEURIST4.msg.showMsgErr(item);
                    error = true;
                }else{
                    _recID = Number(item);
                    if(report!=="") {
                        report = report + ",";
                    }
                    report = report + Math.abs(_recID);
                }
            }
        }

        if(!error){
            let ss = (_recID < 0)?"added":"updated";

           
            window.close(response); //send back new HEURIST strcuture
        }
        
    }

    /**
    * Apply form
    * private method for public method "save"
    * 1. gather changed data from UI (_fromUItoArray) to _updatedFields, _updatedDetails
    * 2. creates object to be sent to server
    * 3. sends data to server
    */
    function _updateOnServer()
    {

        //1. gather changed data
        if(_fromUItoArray(true)==="mandatory"){ //save all changes
            return;
        }

        let str = null;

        //2. creates object to be sent to server
        if(_recID !== null && _updatedFields.length > 0){
            let k,
                val;
            let oDataToServer = {report:{
                colNames:[],
                defs: {}
            }};

            let values = [];
            for(k = 0; k < _updatedFields.length; k++) {
                oDataToServer.report.colNames.push(_updatedFields[k]);
                values.push(_updatedDetails[k]);
            }


            oDataToServer.report.defs[_recID] = [];
            for(val in values) {
                oDataToServer.report.defs[_recID].push(values[val]);
            }
            // 3. sends data to server
            let baseurl = window.hWin.HAPI4.baseURL + "export/publish/loadReports.php";
            let callback = _updateResult;
            let request = {method:'savereport', data:oDataToServer};
            window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, callback);
        } else {
            window.close(null);
        }
    }

    //public members
    let that = {

            /**
             *    Apply form - sends data to server and closes this pop-up window in case of success
             */
            save : function () {
                _updateOnServer();
            },

            /**
             * Cancel form - checks if changes were made, shows warning and closes the window
             */
            cancel : function () {
                _fromUItoArray(false);
                if(_updatedFields.length > 0) {
                    let areYouSure = confirm("Changes were made. By cancelling, all changes will be lost. Are you sure?");
                    if(areYouSure) {
                        window.close(null);
                    }
                }else{
                    window.close(null);
                }
            },

            getClass: function () {
                return _className;
            },

            isA: function (strClass) {
                return (strClass === _className);
        }

    };

    _init();
    return that;
}