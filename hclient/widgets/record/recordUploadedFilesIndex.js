/**
* recordUploadedFilesIndex.js - register files in specified folder in recUploadedFiles
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

$.widget( "heurist.recordUploadedFilesIndex", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 780,
        width:  800,
        modal:  true,
        title:  'Index uploaded files / external transfers',
        
        htmlContent: 'recordUploadedFilesIndex'
    },
    
    _init: function() {
        this.options.htmlContent = this.options.htmlContent
                    +(window.hWin.HAPI4.getLocale()=='FRE'?'_fre':'')+'.html';
        this._super();    
    },
    
    _initControls:function(){
        
        //fill media folders and exts
        let folders = window.hWin.HAPI4.sysinfo['mediaFolder'];
        folders = folders.split(';');
        folders.unshift('file_uploads');
        folders.unshift('all');        
        window.hWin.HEURIST4.ui.createSelector(this._$('#mediafolders').get(0), folders);
        
        this._$('#mediaexts').text(window.hWin.HAPI4.sysinfo['media_ext_index'] ?? window.hWin.HAPI4.sysinfo['media_ext']);
        
        this._$('.btnAction').button();
        
        this._on(this._$('.btnAction'), {click:this.doAction});
        
        return this._super();
    },
    
    
    //    
    //
    //
    _getActionButtons: function(){
        let res = this._super();
        res[1].text = window.hWin.HR('Proceed');
        res[0].text = window.hWin.HR('Close');
        return res;
    },    

    //
    //
    //
    _renderReport: function(data)
    {
        this._$('#div_header').hide();
        this._$('#div_result').show();
        this._$('#div_result_msg').html( data );
    },
        
    //
    // 
    //
    doAction: function(){
        
            const selected_folders = this._$('#mediafolders').val();
        
            let request = {
                'a': 'batch',
                'entity': 'recUploadedFiles',
                'request_id': window.hWin.HEURIST4.util.random(),
                'folders': selected_folders,
                'bulk_reg_filestore': 1
            };
            
            let that = this;

            window.hWin.HEURIST4.msg.bringCoverallToFront($('body'));

            window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){

                window.hWin.HEURIST4.msg.sendCoverallToBack();

                if(response.status == window.hWin.ResponseStatus.OK){
                    
                    if(window.hWin.HEURIST4.util.isempty(response.data)){
                        window.hWin.HEURIST4.msg.showMsgFlash('No new files to index', 3000);
                    }else{
                        that._renderReport( response.data );
                    }
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });
    }
  
});

