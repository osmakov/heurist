/*
* imgFilter.js - define image filters css
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

//
//
//
function imgFilter( current_cfg, main_callback, $container=null ){

    const _className = 'imgFilter';
    let _default_values = {};
    let $dlg;
    
    function _init(){


        if($container && $container.length>0){
            //container provided
            $dlg = $container;

            $container.empty().load(window.hWin.HAPI4.baseURL
                +'hclient/widgets/editing/imgFilter.html',
                _initControls
            );

        }else{
            //open as popup
            let buttons= [
                {text:window.hWin.HR('Cancel'), 
                    class:'btnCancel',
                    css:{'float':'right','margin-left':'10px','margin-right':'20px'}, 
                    click: function() { 
                        $dlg.dialog( "close" );
                }},
                {text:window.hWin.HR('Reset'), 
                    class:'btnReset',
                    css:{'float':'right','margin-left':'10px'}, 
                    click: function() { 
                        _resetValues();
                }},
                {text:window.hWin.HR('Apply'), 
                    class:'ui-button-action btnDoAction',
                    //disabled:'disabled',
                    css:{'float':'right'}, 
                    click: function() { 
                            let config = _getValues();
                            main_callback.call(this, config);
                            $dlg.dialog( "close" );    
            }}];
    
            $dlg = window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL
                +"hclient/widgets/editing/imgFilter.html?t="+(new Date().getTime()), 
                buttons, 'Define Filters', 
                {   //container:'cms-add-widget-popup',
                    default_palette_class: 'ui-heurist-explore', //'ui-heurist-publish',
                    width: 300,
                    height: 450,
                    close: function(){
                        $dlg.dialog('destroy');       
                        $dlg.remove();
                    },
                    open: _initControls
            });

        }

        current_cfg = window.hWin.HEURIST4.util.isJSON(current_cfg);

    }
    
    //
    // Assign css values to UI
    //
    function _initControls(){
        _default_values = {};

        $.each($dlg.find('input'), function(idx, item){
            item = $(item);
            _default_values[item.attr('name')] = item.val();
            
            $(item).on({change:function(e){
                $(e.target).prev().text( $(e.target).val() );
            }});

            if(current_cfg && !window.hWin.HEURIST4.util.isempty(current_cfg[item.attr('name')])){
                let val = parseFloat(current_cfg[item.attr('name')]);
                item.val( val ).trigger('change');    
            }
            
        });
    }
    
    //
    //
    //
    function _resetValues(){
        $.each($dlg.find('input'), function(idx, item){
            $(item).val(_default_values[$(item).attr('name')])
        });
    }
   
    //
    // get css
    //
    function _getValues(){
        
        let filter_cfg = {};
        let filter = '';
        $.each($dlg.find('input'), function(idx, item){
            item = $(item);
            
            let val = item.val();
            if(val!=_default_values[item.attr('name')]){
                let suffix = item.attr('data-suffix');
                if(!suffix) suffix = '';
                
                filter_cfg[item.attr('name')] = val+suffix;
                filter = filter + item.attr('name')+'('+val+suffix+') ';
            }
        });
        return filter_cfg;
    }//_getValues



    //public members
    let that = {

        getClass: function () {
            return _className;
        },

        isA: function (strClass) {
            return (strClass === _className);
        },
    }

    _init();
    
    return that;
}



